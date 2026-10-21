<?php
declare(strict_types=1);

/** Persistent encrypted reviews, isolated by tenant, agent, workspace and review token. */
final class ReviewCache
{
    private string $directory;
    private string $key;
    public function __construct(array $config, ?string $directory = null)
    {
        $scope=json_encode([$config['domain'],$config['agent_id'],$config['workspace_id'] ?? 0,$config['review_model'] ?? '',$config['review_token']],JSON_THROW_ON_ERROR);
        $this->key=hash_hmac('sha256','review-cache-encryption-v1:'.$scope,(string)$config['review_token'],true);
        $this->directory=$directory ?? dirname(__DIR__).'/storage/reviews-'.hash('sha256',$scope);
        if (!is_dir($this->directory) && !@mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new RuntimeException('Review cache storage is unavailable. No paid AI request was made.');
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('PHP OpenSSL is required for the encrypted review cache.');
    }
    public static function fingerprint(array $detail): string
    {
        $ticket=array_intersect_key($detail['ticket'],array_flip(['id','subject','priority','status','due_by']));
        $conversations=array_map(static fn($c)=>array_diff_key($c,array_flip(['updated_at'])), $detail['conversations']);
        return hash('sha256',json_encode(['version'=>1,'ticket'=>$ticket,'description'=>$detail['description'],'conversations'=>$conversations,'attachment_count'=>$detail['attachment_count'],'limitations'=>$detail['limitations']],JSON_THROW_ON_ERROR));
    }
    private function path(array $detail): string { return $this->directory.'/'.(int)$detail['ticket']['id'].'.json'; }
    public function load(array $detail): ?array
    {
        $raw=@file_get_contents($this->path($detail));
        if ($raw===false) return null;
        try {
            $envelope=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if (!is_array($envelope) || !is_string($envelope['data'] ?? null) || !is_string($envelope['iv'] ?? null) || !is_string($envelope['tag'] ?? null)) return null;
            $data=base64_decode($envelope['data'],true);$iv=base64_decode($envelope['iv'],true);$tag=base64_decode($envelope['tag'],true);
            if ($data===false || $iv===false || $tag===false || strlen($iv)!==12 || strlen($tag)!==16) return null;
            $plain=openssl_decrypt($data,'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$iv,$tag);
            if ($plain===false) return null;
            $entry=json_decode($plain,true,512,JSON_THROW_ON_ERROR);
            if (($entry['fingerprint'] ?? '')!==self::fingerprint($detail)) return null;
            $entry['review']=AiReviewer::validate($entry['review'],$detail);
            return $entry;
        } catch (Throwable $e) { return null; }
    }
    /** Lock covers read/generate/write so simultaneous tabs do not duplicate paid requests. */
    public function analyze(array $detail,bool $force,callable $generate): array
    {
        $lock=@fopen($this->path($detail).'.lock','c');
        if ($lock===false) throw new RuntimeException('Review cache lock is unavailable. No paid AI request was made.');
        try {
            if (!flock($lock,LOCK_EX)) throw new RuntimeException('Could not lock the review cache.');
            if (!$force && ($entry=$this->load($detail))!==null) return ['review'=>$entry['review'],'reviewed_at'=>$entry['reviewed_at'],'review_cached'=>true];
            $review=AiReviewer::validate($generate(),$detail);
            $entry=['fingerprint'=>self::fingerprint($detail),'review'=>$review,'reviewed_at'=>gmdate('c')];
            $iv=random_bytes(12);$tag='';
            $encrypted=openssl_encrypt(json_encode($entry,JSON_THROW_ON_ERROR),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$iv,$tag);
            if ($encrypted===false) throw new RuntimeException('Could not encrypt the AI review for caching.');
            $json=json_encode(['iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($encrypted)],JSON_THROW_ON_ERROR);
            $temporary=@tempnam($this->directory,'review-');
            try {
                if ($temporary===false || @file_put_contents($temporary,$json)!==strlen($json) || !@rename($temporary,$this->path($detail))) throw new RuntimeException('AI review completed but could not be cached. Check server storage before retrying.');
            } finally {if ($temporary!==false && is_file($temporary)) @unlink($temporary);}
            return ['review'=>$review,'reviewed_at'=>$entry['reviewed_at'],'review_cached'=>false];
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }
}
