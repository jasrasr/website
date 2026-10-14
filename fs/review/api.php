<?php
declare(strict_types=1);
// Keep warnings and accidental config output from corrupting JSON responses.
ini_set('display_errors', '0');
ob_start();
$reviewBufferLevel = ob_get_level();
$reviewStage = 'configuration';
$reviewRequestId = bin2hex(random_bytes(8));
$reviewFinished = false;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function reply(int $status,array $data): never
{
    global $reviewBufferLevel, $reviewFinished, $reviewStage, $reviewRequestId;
    while (ob_get_level() >= $reviewBufferLevel) ob_end_clean();
    $reviewFinished = true;
    http_response_code($status);
    if ($status >= 400) $data += ['stage'=>$reviewStage, 'request_id'=>$reviewRequestId];
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
register_shutdown_function(static function (): void {
    global $reviewFinished, $reviewStage, $reviewRequestId;
    $error = error_get_last();
    if (!$reviewFinished && $error && in_array($error['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
        // Never log exception text, ticket content or credentials.
        error_log('Ticket review '.$reviewRequestId.' fatal during '.$reviewStage.' (type '.$error['type'].').');
        reply(500, ['error'=>'PHP stopped during '.$reviewStage.'. Check the hosting PHP error log using this request ID.']);
    }
});
try {
    if (function_exists('set_time_limit')) @set_time_limit(120);
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') reply(405,['error'=>'Use POST.']);
if (!is_file(dirname(__DIR__).'/config.local.php')) reply(503,['error'=>'Configure fs/config.local.php on the server first.']);
$config=require dirname(__DIR__).'/config.local.php';
$expected=is_array($config)?(string)($config['review_token'] ?? ''):'';
$authorization=(string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$provided=str_starts_with($authorization,'Bearer ')?substr($authorization,7):'';
if (strlen($expected)<32 || str_contains($expected,'REPLACE_')) reply(503,['error'=>'Set a separate random review_token (at least 32 characters) in server configuration.']);
if (!hash_equals($expected,$provided)) reply(401,['error'=>'Invalid review token.']);
if (empty($config['api_key']) || (int)($config['agent_id'] ?? 0)<1) reply(503,['error'=>'Freshservice API key and agent ID are required.']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>2048) reply(413,['error'=>'Request too large.']);
$body=file_get_contents('php://input',false,null,0,2049);
if (strlen($body)>2048) reply(413,['error'=>'Request too large.']);
$request=json_decode($body,true);
if (!is_array($request)) reply(400,['error'=>'Invalid JSON request.']);
    require_once __DIR__.'/ReviewService.php';
    require_once __DIR__.'/AiReviewer.php';
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required.');
    $service=new ReviewService($config);
    $action=$request['action'] ?? '';
    $reviewStage = 'Freshservice queue';
    if ($action==='queue') reply(200,['tickets'=>$service->queue(),'fetched_at'=>gmdate('c'),'ai_enabled'=>!empty($config['review_ai_enabled'])]);
    $id=filter_var($request['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id || !in_array($action,['detail','analyze'],true)) reply(400,['error'=>'Invalid action or ticket ID.']);
    $reviewStage = 'Freshservice ticket history';
    $detail=$service->detail($id);
    if ($action==='analyze') {
        $reviewStage = 'AI analysis';
        $detail['review']=AiReviewer::review($detail,$config);
    }
    reply(200,$detail);
} catch (RuntimeException $e) {
    reply(502,['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Ticket review '.$reviewRequestId.' failed during '.$reviewStage.' ('.get_class($e).').');
    reply(500,['error'=>'Review failed during '.$reviewStage.'. Check the hosting PHP error log using this request ID; no ticket was changed.']);
}
