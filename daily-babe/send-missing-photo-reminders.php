<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib/smtp.php';
require __DIR__ . '/lib/twilio.php';

function reminder_recipients(string $value): array
{
    $items = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_map(
        static fn(string $email): string => strtolower(trim($email)),
        is_array($items) ? $items : []
    )));
}

function reminder_has_photo(array $entries, string $date): bool
{
    foreach ($entries['records'] ?? [] as $record) {
        if (is_array($record) && ($record['photoDate'] ?? '') === $date) {
            return true;
        }
    }
    return false;
}

function reminder_log(string $event, array $details = []): void
{
    $line = json_encode([
        'timestamp' => gmdate(DATE_ATOM),
        'event' => $event,
        'details' => $details,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    file_put_contents(central_storage_path('logs/reminders-' . date('Y-m') . '.jsonl'), $line, FILE_APPEND | LOCK_EX);
}

global $config;
$smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
$twilio = is_array($config['twilio'] ?? null) ? $config['twilio'] : [];
$appUrl = rtrim((string) ($config['app_url'] ?? 'https://jasr.me/github/daily-babe/'), '/') . '/';

if (($argv[1] ?? '') === '--test') {
    $recipient = trim((string) ($argv[2] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Usage: php send-missing-photo-reminders.php --test recipient@example.com\n");
        exit(2);
    }
    smtp_send_message(
        $smtp,
        [$recipient],
        'Daily Babe email test',
        "Titan SMTP is configured correctly for Daily Babe.\n\n" . $appUrl
    );
    fwrite(STDOUT, "Test email accepted by Titan SMTP.\n");
    exit(0);
}

if (($argv[1] ?? '') === '--test-sms') {
    $recipient = trim((string) ($argv[2] ?? ''));
    if (preg_match('/^\\+[1-9][0-9]{7,14}$/D', $recipient) !== 1) {
        fwrite(STDERR, "Usage: php send-missing-photo-reminders.php --test-sms +12165550123\n");
        exit(2);
    }
    $messageId = twilio_send_sms($twilio, $recipient, 'Daily Babe Twilio test. Text reminders are configured.');
    fwrite(STDOUT, 'Test SMS accepted by Twilio (' . $messageId . ').' . PHP_EOL);
    exit(0);
}

$lockPath = central_storage_path('logs/reminder-job.lock');
$lock = fopen($lockPath, 'c+');
if ($lock === false) {
    throw new RuntimeException('Unable to open the reminder job lock.');
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    fwrite(STDOUT, "Another reminder check is already running.\n");
    exit(0);
}

$today = date('Y-m-d');
$currentTime = date('H:i');
$ledger = json_read('central/data/reminder-state.json', ['schemaVersion' => 1, 'sent' => []]);
$sent = is_array($ledger['sent'] ?? null) ? $ledger['sent'] : [];
$checked = 0;
$delivered = 0;
$failed = 0;

try {
    foreach (tracker_state()['babies'] ?? [] as $babyId => $baby) {
        $babyId = (string) $babyId;
        if (preg_match('/^[a-f0-9]{24}$/D', $babyId) !== 1) {
            continue;
        }
        $settings = json_read('central/babies/' . $babyId . '/data/settings.json', []);
        if (($settings['reminderEnabled'] ?? false) !== true) {
            continue;
        }
        $checked++;
        $birthDate = (string) ($settings['birthDate'] ?? '');
        $reminderTimes = normalized_reminder_times($settings);
        $dueReminderTimes = array_values(array_filter(
            $reminderTimes,
            static fn(string $time): bool => $time <= $currentTime
        ));
        $reminderSlot = $dueReminderTimes === [] ? '' : (string) end($dueReminderTimes);
        $recipients = reminder_recipients((string) ($settings['reminderEmails'] ?? ''));
        $smsEnabled = ($settings['reminderSmsEnabled'] ?? false) === true;
        $smsNumber = trim((string) ($settings['reminderSmsNumber'] ?? ''));
        $smsReady = $smsEnabled && preg_match('/^\\+[1-9][0-9]{7,14}$/D', $smsNumber) === 1;
        if (!valid_date($birthDate) || $today < $birthDate || $reminderSlot === '' || ($recipients === [] && !$smsReady)) {
            continue;
        }
        $legacyLedgerKey = $babyId . ':' . $today;
        $ledgerKey = $legacyLedgerKey . ':' . $reminderSlot;
        $legacyEmailSent = $reminderSlot === '20:00' && (isset($sent[$legacyLedgerKey]) || isset($sent[$legacyLedgerKey . ':email']));
        $legacySmsSent = $reminderSlot === '20:00' && isset($sent[$legacyLedgerKey . ':sms']);
        $emailSent = $legacyEmailSent || isset($sent[$ledgerKey . ':email']);
        $smsSent = $legacySmsSent || isset($sent[$ledgerKey . ':sms']);
        if (($recipients === [] || $emailSent) && (!$smsReady || $smsSent)) {
            continue;
        }
        $entries = json_read('central/babies/' . $babyId . '/data/entries.json', ['records' => []]);
        if (reminder_has_photo($entries, $today)) {
            continue;
        }

        $babyName = trim((string) ($settings['babyName'] ?? $baby['name'] ?? 'Baby')) ?: 'Baby';
        $displayDate = (new DateTimeImmutable($today))->format('F j, Y');
        $uploadUrl = $appUrl . '?' . http_build_query([
            'baby' => $babyId,
            'upload' => '1',
            'date' => $today,
        ], '', '&', PHP_QUERY_RFC3986);
        $subject = 'Missing daily photo for ' . $babyName;
        $body = $babyName . " does not have a photo for " . $displayDate . ".\n\n"
            . "Open the correct gallery and add the missing photo:\n" . $uploadUrl;
        if ($recipients !== [] && !$emailSent) {
            try {
                smtp_send_message($smtp, $recipients, $subject, $body);
                $sent[$ledgerKey . ':email'] = gmdate(DATE_ATOM);
                $ledger['sent'] = $sent;
                $ledger['updatedAt'] = gmdate(DATE_ATOM);
                json_write('central/data/reminder-state.json', $ledger);
                reminder_log('reminder.email.sent', ['babyId' => $babyId, 'photoDate' => $today, 'reminderTime' => $reminderSlot, 'recipientCount' => count($recipients)]);
                $delivered++;
            } catch (Throwable $exception) {
                reminder_log('reminder.email.failed', ['babyId' => $babyId, 'photoDate' => $today, 'reminderTime' => $reminderSlot, 'error' => $exception->getMessage()]);
                fwrite(STDERR, 'Email reminder failed for ' . $babyName . ': ' . $exception->getMessage() . PHP_EOL);
                $failed++;
            }
        }
        if ($smsReady && !$smsSent) {
            try {
                $smsBody = $babyName . ' is missing the photo for ' . $displayDate . '. Add it here: ' . $uploadUrl;
                $messageId = twilio_send_sms($twilio, $smsNumber, $smsBody);
                $sent[$ledgerKey . ':sms'] = gmdate(DATE_ATOM);
                $ledger['sent'] = $sent;
                $ledger['updatedAt'] = gmdate(DATE_ATOM);
                json_write('central/data/reminder-state.json', $ledger);
                reminder_log('reminder.sms.sent', ['babyId' => $babyId, 'photoDate' => $today, 'reminderTime' => $reminderSlot, 'messageId' => $messageId]);
                $delivered++;
            } catch (Throwable $exception) {
                reminder_log('reminder.sms.failed', ['babyId' => $babyId, 'photoDate' => $today, 'reminderTime' => $reminderSlot, 'error' => $exception->getMessage()]);
                fwrite(STDERR, 'SMS reminder failed for ' . $babyName . ': ' . $exception->getMessage() . PHP_EOL);
                $failed++;
            }
        }
    }

    $cutoff = (new DateTimeImmutable('-90 days'))->format('Y-m-d');
    $sent = array_filter($sent, static function (mixed $_timestamp, string $key) use ($cutoff): bool {
        return preg_match('/^[a-f0-9]{24}:(\d{4}-\d{2}-\d{2})(?::|$)/D', $key, $match) === 1 && $match[1] >= $cutoff;
    }, ARRAY_FILTER_USE_BOTH);
    $ledger['sent'] = $sent;
    $ledger['updatedAt'] = gmdate(DATE_ATOM);
    json_write('central/data/reminder-state.json', $ledger);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

fwrite(STDOUT, sprintf("Checked %d enabled galleries; sent %d reminders; %d failed.\n", $checked, $delivered, $failed));
exit($failed === 0 ? 0 : 1);


