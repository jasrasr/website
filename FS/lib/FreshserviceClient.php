<?php
declare(strict_types=1);

final class FreshserviceClient
{
    public function __construct(
        private readonly string $domain,
        private readonly string $apiKey,
        private readonly int $workspaceId = 0
    ) {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listTicketsForAgent(int $agentId): array
    {
        $tickets = [];
        $page = 1;
        $perPage = 100;

        do {
            $query = sprintf('"agent_id:%d"', $agentId);
            $params = [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
            ];
            if ($this->workspaceId > 0) {
                $params['workspace_id'] = $this->workspaceId;
            }

            $response = $this->get('/api/v2/tickets/filter?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
            $batch = isset($response['tickets']) && is_array($response['tickets']) ? $response['tickets'] : [];
            foreach ($batch as $ticket) {
                if (is_array($ticket)) {
                    $tickets[] = $ticket;
                }
            }
            $page++;
        } while (count($batch) === $perPage);

        return $tickets;
    }

    /** @return array<string, mixed>|null */
    public function getTicket(int $ticketId): ?array
    {
        try {
            $response = $this->get('/api/v2/tickets/' . $ticketId);
            return isset($response['ticket']) && is_array($response['ticket']) ? $response['ticket'] : null;
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'HTTP 404')) {
                return null;
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        $url = 'https://' . trim($this->domain, '/') . $path;
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->apiKey . ':X',
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $error !== '') {
            throw new RuntimeException('Freshservice request failed: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Freshservice returned HTTP %d.', $status));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Freshservice returned invalid JSON.');
        }

        return $decoded;
    }
}
