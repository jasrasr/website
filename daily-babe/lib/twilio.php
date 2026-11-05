<?php

declare(strict_types=1);

function twilio_send_sms(array $config, string $to, string $message): string
{
    $accountSid = trim((string) ($config['account_sid'] ?? ''));
    $authToken = (string) ($config['auth_token'] ?? '');
    $fromNumber = trim((string) ($config['from_number'] ?? ''));
    foreach ([$to, $fromNumber] as $number) {
        if (preg_match('/^\\+[1-9][0-9]{7,14}$/D', $number) !== 1) {
            throw new InvalidArgumentException('Twilio phone numbers must use international E.164 format.');
        }
    }
    if (preg_match('/^AC[0-9a-fA-F]{32}$/D', $accountSid) !== 1 || $authToken === '') {
        throw new RuntimeException('Twilio is not configured. Add the Account SID and Auth Token to the server config.');
    }
    if ($message === '' || strlen($message) > 1500) {
        throw new InvalidArgumentException('SMS reminder text must contain 1 to 1500 bytes.');
    }

    $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
    $payload = http_build_query(['To' => $to, 'From' => $fromNumber, 'Body' => $message], '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Authorization: Basic ' . base64_encode($accountSid . ':' . $authToken),
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'Content-Length: ' . strlen($payload),
                'Connection: close',
            ],
            'content' => $payload,
            'timeout' => 15,
            'ignore_errors' => true,
            'protocol_version' => 1.1,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\\/\\S+\\s+(\\d{3})/', $header, $match) === 1) {
            $status = (int) $match[1];
        }
    }
    $decoded = is_string($response) ? json_decode($response, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($decoded) || !isset($decoded['sid'])) {
        $providerMessage = is_array($decoded) ? trim((string) ($decoded['message'] ?? '')) : '';
        throw new RuntimeException('Twilio SMS request failed (HTTP ' . $status . ')' . ($providerMessage !== '' ? ': ' . $providerMessage : '.'));
    }
    return (string) $decoded['sid'];
}
