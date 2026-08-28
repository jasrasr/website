<?php

declare(strict_types=1);
require_once __DIR__ . '/../1-Framework/bootstrap.php';
require_once __DIR__ . '/lib/Auth.php';
header('Content-Type: application/json; charset=utf-8');
$auth = new Auth(__DIR__ . '/storage/users.json', __DIR__ . '/storage/invites.json'); $requestId = bin2hex(random_bytes(6));
function authResponse(bool $success, string $message, mixed $data = null, int $status = 200): never { global $requestId; http_response_code($status); echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data,'errors'=>$success?[]:[['code'=>'AUTH_ERROR','message'=>$message]],'meta'=>['timestamp'=>gmdate(DATE_ATOM),'requestId'=>$requestId]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); exit; }
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') authResponse(true, 'Session loaded.', ['user'=>$auth->user(),'csrf'=>$auth->csrf(),'setupRequired'=>$auth->setupRequired()]);
    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR); $action = (string) ($input['action'] ?? '');
    if ($action === 'login') authResponse(true, 'Signed in.', ['user'=>$auth->login((string)($input['email']??''),(string)($input['password']??'')),'csrf'=>$auth->csrf()]);
    if ($action === 'register') authResponse(true, 'Account created.', ['user'=>$auth->register((string)($input['name']??''),(string)($input['email']??''),(string)($input['password']??''),(string)($input['inviteCode']??'')),'csrf'=>$auth->csrf()]);
    $auth->verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if ($action === 'logout') { $auth->logout(); authResponse(true, 'Signed out.', ['csrf'=>$auth->csrf()]); }
    if ($action === 'createInvite') authResponse(true, 'Invite created.', $auth->createInvite());
    authResponse(false, 'Unknown account action.', null, 400);
} catch (InvalidArgumentException $e) { authResponse(false, $e->getMessage(), null, 422); } catch (Throwable $e) { error_log('Warranty auth ['.$requestId.']: '.$e->getMessage()); authResponse(false, 'The account request could not be completed.', null, 500); }
