<?php

declare(strict_types=1);

function smtp_read_response($socket, array $expectedCodes): string
{
    $response = '';
    do {
        $line = fgets($socket, 4096);
        if ($line === false) {
            throw new RuntimeException('The SMTP server closed the connection unexpectedly.');
        }
        $response .= $line;
    } while (strlen($line) >= 4 && $line[3] === '-');

    $code = (int) substr($line, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP command failed with response code ' . $code . '.');
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Unable to write to the SMTP server.');
    }
    return smtp_read_response($socket, $expectedCodes);
}

function smtp_header_text(string $value): string
{
    $value = trim(str_replace(["\r", "\n"], ' ', $value));
    if (preg_match('/^[\x20-\x7E]*$/D', $value) === 1) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_address_header(string $email, string $name = ''): string
{
    return $name === '' ? $email : smtp_header_text($name) . ' <' . $email . '>';
}

function smtp_send_message(array $smtp, array $recipients, string $subject, string $body): void
{
    $host = trim((string) ($smtp['host'] ?? ''));
    $port = (int) ($smtp['port'] ?? 0);
    $encryption = strtolower(trim((string) ($smtp['encryption'] ?? 'ssl')));
    $authentication = ($smtp['authentication'] ?? true) === true;
    $username = trim((string) ($smtp['username'] ?? ''));
    $password = (string) ($smtp['password'] ?? '');
    $fromEmail = trim((string) ($smtp['from_email'] ?? $username));
    $fromName = trim((string) ($smtp['from_name'] ?? 'Daily Babe'));

    if ($host === '' || $port < 1 || $port > 65535 || !in_array($encryption, ['ssl', 'tls', 'none'], true)) {
        throw new RuntimeException('SMTP host, port, or encryption is not configured correctly.');
    }
    if ($encryption !== 'none' && !in_array('ssl', stream_get_transports(), true)) {
        throw new RuntimeException('PHP OpenSSL stream support is required for encrypted SMTP.');
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The SMTP sender address is invalid.');
    }
    if ($authentication && ($username === '' || $password === '')) {
        throw new RuntimeException('SMTP authentication credentials are incomplete.');
    }

    $recipients = array_values(array_unique(array_map(
        static fn(string $email): string => strtolower(trim($email)),
        $recipients
    )));
    foreach ($recipients as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A reminder recipient address is invalid.');
        }
    }
    if ($recipients === []) {
        throw new RuntimeException('No reminder recipients are configured.');
    }

    $transport = $encryption === 'ssl' ? 'ssl' : 'tcp';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);
    $socket = @stream_socket_client(
        $transport . '://' . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to connect to the SMTP server: ' . $errorMessage . ' (' . $errorNumber . ').');
    }
    stream_set_timeout($socket, 20);

    try {
        smtp_read_response($socket, [220]);
        smtp_command($socket, 'EHLO daily-babe', [250]);
        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start SMTP TLS encryption.');
            }
            smtp_command($socket, 'EHLO daily-babe', [250]);
        }
        if ($authentication) {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password), [235]);
        }

        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        foreach ($recipients as $email) {
            smtp_command($socket, 'RCPT TO:<' . $email . '>', [250, 251]);
        }
        smtp_command($socket, 'DATA', [354]);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . smtp_address_header($fromEmail, $fromName),
            'To: ' . implode(', ', $recipients),
            'Subject: ' . smtp_header_text($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^a-z0-9.-]/i', '', $host) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $normalizedBody = preg_replace('/\r\n|\r|\n/', "\r\n", $body) ?? $body;
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
        $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
        if (fwrite($socket, $message . "\r\n.\r\n") === false) {
            throw new RuntimeException('Unable to send the SMTP message body.');
        }
        smtp_read_response($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

