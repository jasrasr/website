<?php
declare(strict_types=1);

/** Read-only Freshservice client. No write operations or AI tools are exposed. */
final class ReviewService
{
    private string $base;
    private $transport;
    public function __construct(private array $config, ?callable $transport = null)
    {
        $host = preg_replace('#^https://#i', '', rtrim(trim((string)($config['domain'] ?? '')), '/'));
        if (!preg_match('/^[a-z0-9-]+\.freshservice\.com$/i', $host)) {
            throw new RuntimeException('Set domain to your tenant.freshservice.com hostname.');
        }
        $this->base = 'https://' . $host;
        $this->transport = $transport;
    }
    private function get(string $path): array
    {
        if ($this->transport !== null) return ($this->transport)($path);
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>$this->config['api_key'].':X',
            CURLOPT_HTTPAUTH=>CURLAUTH_BASIC, CURLOPT_HTTPHEADER=>['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>45, CURLOPT_FOLLOWLOCATION=>false]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status === 429) throw new RuntimeException('Freshservice rate limit reached. Wait a minute, then retry; no partial review was accepted.');
        if ($body === false || $status < 200 || $status >= 300) throw new RuntimeException('Freshservice request failed (HTTP '.$status.'). Check API permissions and configuration.');
        $data = json_decode($body, true);
        if (!is_array($data)) throw new RuntimeException('Freshservice returned invalid JSON.');
        return $data;
    }
    public function inScope(array $ticket): bool
    {
        return (int)($ticket['status'] ?? 0) > 0
            && (int)($ticket['responder_id'] ?? 0) === (int)$this->config['agent_id']
            && !in_array((int)($ticket['status'] ?? 0), array_map('intval', $this->config['resolved_status_ids'] ?? [4,5]), true)
            && empty($ticket['deleted']) && empty($ticket['spam'])
            && ((int)($this->config['workspace_id'] ?? 0) === 0 || (int)($ticket['workspace_id'] ?? 0) === (int)$this->config['workspace_id']);
    }
    /** Paginate against total, not a guessed page size. Do not accept silent truncation. */
    public function queue(): array
    {
        $found = [];
        for ($page=1; $page<=334; $page++) {
            $response = $this->get('/api/v2/tickets/filter?' . http_build_query([
                'query'=>'"agent_id:'.(int)$this->config['agent_id'].'"', 'workspace_id'=>(int)($this->config['workspace_id'] ?? 0), 'page'=>$page]));
            if (!isset($response['total']) || !is_array($response['tickets'] ?? null)) throw new RuntimeException('Incomplete ticket list response.');
            $total = (int)$response['total'];
            if ($total >= 10000) throw new RuntimeException('Agent history reaches the Freshservice filter limit. Narrow workspace scope before reviewing.');
            $before=count($found);
            foreach ($response['tickets'] as $ticket) {
                if (!is_array($ticket) || empty($ticket['id'])) throw new RuntimeException('Malformed ticket list.');
                $found[(int)$ticket['id']]=$ticket;
            }
            if (count($found) >= $total) {
                $queue=[];
                foreach ($found as $ticket) if ($this->inScope($ticket)) $queue[]=$this->summary($ticket);
                usort($queue, static fn($a,$b)=>($b['score']<=>$a['score']) ?: ($a['id']<=>$b['id']));
                return $queue;
            }
            if (count($found)===$before) throw new RuntimeException('Ticket pagination stopped early. Refresh the queue.');
        }
        throw new RuntimeException('Ticket page limit reached; queue is incomplete.');
    }
    public function summary(array $t): array
    {
        $now=time();
        $age=isset($t['created_at']) && strtotime($t['created_at'])!==false ? max(0,(int)floor(($now-strtotime($t['created_at']))/86400)) : null;
        $idle=isset($t['updated_at']) && strtotime($t['updated_at'])!==false ? max(0,(int)floor(($now-strtotime($t['updated_at']))/86400)) : null;
        $due=!empty($t['due_by']) ? strtotime($t['due_by']) : false;
        $overdue=$due!==false && $due<$now;
        $priority=(int)($t['priority'] ?? 0);
        $score=([1=>10,2=>25,3=>60,4=>100][$priority] ?? 0)+($overdue?50:0)+min(30,$age ?? 0)+min(20,$idle ?? 0);
        return ['id'=>(int)$t['id'],'subject'=>(string)($t['subject'] ?? ''),'priority'=>$priority,'status'=>(int)($t['status'] ?? 0),
            'age_days'=>$age,'idle_days'=>$idle,'overdue'=>$overdue,'due_by'=>$t['due_by'] ?? null,'updated_at'=>$t['updated_at'] ?? null,
            'score'=>$score,'url'=>$this->base.'/a/tickets/'.(int)$t['id']];
    }
    public function detail(int $id): array
    {
        $t=$this->get('/api/v2/tickets/'.$id)['ticket'] ?? null;
        if (!is_array($t) || !$this->inScope($t)) throw new RuntimeException('Ticket is no longer unresolved and assigned to the configured agent/workspace. Refresh the queue.');
        $conversations=[];
        for ($page=1; $page<=100; $page++) {
            $r=$this->get('/api/v2/tickets/'.$id.'/conversations?page='.$page);
            if (!is_array($r['conversations'] ?? null)) throw new RuntimeException('Conversation history could not be verified.');
            if ($r['conversations']===[]) break;
            foreach ($r['conversations'] as $c) {
                if (empty($c['id']) || isset($conversations[$c['id']])) throw new RuntimeException('Conversation pagination repeated or omitted an ID.');
                $conversations[$c['id']]=['id'=>(int)$c['id'],'user_id'=>$c['user_id'] ?? null,
                    'private'=>(bool)($c['private'] ?? true),'incoming'=>(bool)($c['incoming'] ?? false),
                    'created_at'=>$c['created_at'] ?? null,'updated_at'=>$c['updated_at'] ?? null,
                    'body'=>self::plain($c['body_text'] ?? null,$c['body'] ?? ''),'attachment_count'=>count($c['attachments'] ?? [])];
            }
            // An extra empty page confirms completion even if a tenant ignores page size.
            if ($page===100) throw new RuntimeException('Conversation page limit reached. No AI review will use incomplete history.');
        }
        $fresh=$this->get('/api/v2/tickets/'.$id)['ticket'] ?? [];
        if (!$this->inScope($fresh) || ($fresh['updated_at'] ?? null)!==($t['updated_at'] ?? null)) throw new RuntimeException('Ticket changed while loading. Reload it before reviewing.');
        $conversations=array_values($conversations);
        usort($conversations, static fn($a,$b)=>strcmp((string)$a['created_at'],(string)$b['created_at']) ?: ($a['id']<=>$b['id']));
        return ['ticket'=>$this->summary($t),'description'=>self::plain($t['description_text'] ?? null,$t['description'] ?? ''),
            'conversations'=>$conversations,'attachment_count'=>count($t['attachments'] ?? []),'fetched_at'=>gmdate('c'),
            'limitations'=>['Attachment contents, tasks, approvals and linked records are not included. Confirm these in Freshservice before resolving or closing.']];
    }
    private static function plain(?string $text,string $html): string
    {
        return $text ?? html_entity_decode(strip_tags(preg_replace('#<(br\s*/?|/p|/div)>#i', "\n", $html)), ENT_QUOTES|ENT_HTML5, 'UTF-8');
    }
}
