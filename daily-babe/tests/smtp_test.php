<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/smtp.php';

if (($argv[1] ?? '') === '--server') {
    $port = (int) ($argv[2] ?? 0);
    $transcriptPath = (string) ($argv[3] ?? '');
    $server = stream_socket_server('tcp://127.0.0.1:' . $port, $errorNumber, $errorMessage);
    if (!is_resource($server)) {
        fwrite(STDERR, $errorMessage . ' (' . $errorNumber . ')');
        exit(1);
    }
    $client = stream_socket_accept($server, 10);
    if (!is_resource($client)) {
        exit(1);
    }
    fwrite($client, "220 fake-smtp ready\r\n");
    $authStep = 0;
    $dataMode = false;
    $transcript = '';
    while (($line = fgets($client, 4096)) !== false) {
        $transcript .= $line;
        $command = rtrim($line, "\r\n");
        if ($dataMode) {
            if ($command === '.') {
                $dataMode = false;
                fwrite($client, "250 queued\r\n");
            }
            continue;
        }
        if (str_starts_with($command, 'EHLO ')) {
            fwrite($client, "250-fake-smtp\r\n250 AUTH LOGIN\r\n");
        } elseif ($command === 'AUTH LOGIN') {
            $authStep = 1;
            fwrite($client, "334 VXNlcm5hbWU6\r\n");
        } elseif ($authStep === 1) {
            $authStep = 2;
            fwrite($client, "334 UGFzc3dvcmQ6\r\n");
        } elseif ($authStep === 2) {
            $authStep = 0;
            fwrite($client, "235 authenticated\r\n");
        } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
            fwrite($client, "250 accepted\r\n");
        } elseif ($command === 'DATA') {
            $dataMode = true;
            fwrite($client, "354 send data\r\n");
        } elseif ($command === 'QUIT') {
            fwrite($client, "221 goodbye\r\n");
            break;
        } else {
            fwrite($client, "500 unexpected command\r\n");
        }
    }
    file_put_contents($transcriptPath, $transcript);
    fclose($client);
    fclose($server);
    exit(0);
}

function assert_contains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('SMTP transcript did not contain: ' . $needle);
    }
}

$probe = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
if (!is_resource($probe)) {
    throw new RuntimeException('Unable to reserve a test port: ' . $errorMessage);
}
$address = stream_socket_get_name($probe, false);
fclose($probe);
$port = (int) substr((string) $address, (int) strrpos((string) $address, ':') + 1);
$transcriptPath = tempnam(sys_get_temp_dir(), 'daily-babe-smtp-');
if ($transcriptPath === false) {
    throw new RuntimeException('Unable to create a test transcript.');
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --server ' . $port . ' ' . escapeshellarg($transcriptPath);
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Unable to start the fake SMTP server.');
}
fclose($pipes[0]);
usleep(250000);

try {
    smtp_send_message([
        'host' => '127.0.0.1',
        'port' => $port,
        'encryption' => 'none',
        'authentication' => true,
        'username' => 'notifications@example.test',
        'password' => 'test-password',
        'from_email' => 'notifications@example.test',
        'from_name' => 'Daily Babe',
    ], ['parent@example.test'], 'Missing daily photo for Hadley', ".First line\nSecond line");
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('Fake SMTP server failed: ' . $stderr);
    }
    $transcript = (string) file_get_contents($transcriptPath);
    assert_contains('AUTH LOGIN', $transcript);
    assert_contains(base64_encode('notifications@example.test'), $transcript);
    assert_contains(base64_encode('test-password'), $transcript);
    assert_contains('MAIL FROM:<notifications@example.test>', $transcript);
    assert_contains('RCPT TO:<parent@example.test>', $transcript);
    assert_contains('Subject: Missing daily photo for Hadley', $transcript);
    assert_contains("..First line\r\nSecond line", $transcript);
    fwrite(STDOUT, "SMTP authentication and message delivery test passed.\n");
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
    }
    @unlink($transcriptPath);
}

