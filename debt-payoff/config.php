<?php
/*
    Debt Payoff Planner
    Revision: 0.1.0
    Description: Shared configuration, authentication, private per-user storage, payoff calculations, strategy simulations, changelog rendering, and admin helpers.
*/

declare(strict_types=1);

date_default_timezone_set('America/New_York');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

const APP_NAME = 'Debt Payoff Planner';
const APP_REVISION = '0.1.0';
const APP_UPDATED = '2026-07-25';
const DATA_DIR = __DIR__ . '/data';
const USER_DATA_DIR = DATA_DIR . '/users';
const ACCOUNTS_FILE = DATA_DIR . '/accounts.json';
const CHANGELOG_FILE = __DIR__ . '/CHANGELOG.md';
const TODO_FILE = __DIR__ . '/TODO.md';
const VERSION_FILE = __DIR__ . '/VERSION.txt';
const DEFAULT_TEST_USERNAME = 'user';
const DEFAULT_TEST_PASSWORD = 'test';

function ensureAppFolders(): void
{
    foreach ([DATA_DIR, USER_DATA_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function nowIso(): string
{
    return date('c');
}

function formatTimestamp(string $value): string
{
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone('America/New_York'))
            ->format('Y-m-d h:i:s A T');
    } catch (Throwable) {
        return $value;
    }
}

function readJsonFile(string $path, array $fallback = []): array
{
    if (!is_file($path)) {
        return $fallback;
    }

    $json = file_get_contents($path);
    $decoded = json_decode($json ?: '', true);
    return is_array($decoded) ? $decoded : $fallback;
}

function writeJsonFile(string $path, array $data): void
{
    ensureAppFolders();
    $tmpPath = $path . '.tmp';
    file_put_contents($tmpPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    rename($tmpPath, $path);
}

function cleanUsername(string $username): string
{
    $username = strtolower(trim($username));
    return preg_replace('/[^a-z0-9_-]/', '', $username) ?? '';
}

function userDataPath(string $username): string
{
    return USER_DATA_DIR . '/' . cleanUsername($username) . '.json';
}

function readAccounts(): array
{
    return readJsonFile(ACCOUNTS_FILE, ['users' => []]);
}

function upsertAccount(array $account): void
{
    $accounts = readAccounts();
    $users = $accounts['users'] ?? [];
    $updated = false;

    foreach ($users as $index => $existing) {
        if (($existing['username'] ?? '') === ($account['username'] ?? '')) {
            $users[$index] = $account;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $users[] = $account;
    }

    usort($users, fn(array $a, array $b) => strcmp((string)($a['username'] ?? ''), (string)($b['username'] ?? '')));
    writeJsonFile(ACCOUNTS_FILE, ['users' => $users]);
}

function removeAccount(string $username): void
{
    $username = cleanUsername($username);
    $users = array_values(array_filter(
        readAccounts()['users'] ?? [],
        fn(array $account) => ($account['username'] ?? '') !== $username
    ));
    writeJsonFile(ACCOUNTS_FILE, ['users' => $users]);
}

function findAccount(string $username): ?array
{
    $username = cleanUsername($username);
    foreach ((readAccounts()['users'] ?? []) as $account) {
        if (($account['username'] ?? '') === $username) {
            return $account;
        }
    }

    return null;
}

function adminExists(): bool
{
    foreach ((readAccounts()['users'] ?? []) as $account) {
        if (($account['role'] ?? 'user') === 'admin') {
            return true;
        }
    }

    return false;
}

function defaultLoan(string $name, string $type, string $category, float $balance, float $apr, float $monthlyPayment, string $originalDate, float $originalBalance, float $extraMonthly = 0, float $annualExtra = 0, float $lumpSum = 0, int $annualExtraMonth = 6): array
{
    return [
        'id' => bin2hex(random_bytes(8)),
        'name' => $name,
        'type' => $type,
        'category' => $category,
        'current_balance' => $balance,
        'apr' => $apr,
        'monthly_payment' => $monthlyPayment,
        'original_date' => $originalDate,
        'original_balance' => $originalBalance,
        'extra_monthly' => $extraMonthly,
        'annual_extra' => $annualExtra,
        'annual_extra_month' => $annualExtraMonth,
        'lump_sum' => $lumpSum,
        'notes' => '',
    ];
}

function defaultUserData(string $username, bool $withSampleData = false): array
{
    $timestamp = nowIso();
    $base = [
        'profile' => [
            'username' => $username,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'strategy_extra_budget' => $withSampleData ? 400 : 250,
        ],
        'loans' => [],
    ];

    if (!$withSampleData) {
        return $base;
    }

    $base['loans'] = [
        defaultLoan('Visa Rewards', 'Credit Card', 'Credit Cards', 6200, 24.99, 190, '2024-02-14', 7800, 40, 500, 250, 11),
        defaultLoan('Toyota Camry', 'Auto Loan', 'Auto Loans', 11850, 6.49, 365, '2023-09-01', 18400, 50, 300, 0, 12),
        defaultLoan('Starter Mortgage', 'Mortgage', 'Mortgage', 218400, 4.15, 1420, '2022-06-15', 245000, 150, 1200, 0, 1),
        defaultLoan('Personal Consolidation', 'Personal Loan', 'Personal Loans', 4100, 11.9, 210, '2025-01-10', 5500, 25, 0, 150, 7),
    ];

    return $base;
}

function bootstrapApp(): void
{
    ensureAppFolders();

    if (!is_file(ACCOUNTS_FILE)) {
        $timestamp = nowIso();
        writeJsonFile(ACCOUNTS_FILE, [
            'users' => [[
                'username' => DEFAULT_TEST_USERNAME,
                'role' => 'user',
                'password_hash' => password_hash(DEFAULT_TEST_PASSWORD, PASSWORD_DEFAULT),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
        ]);
    }

    $testUserPath = userDataPath(DEFAULT_TEST_USERNAME);
    if (!is_file($testUserPath)) {
        writeJsonFile($testUserPath, defaultUserData(DEFAULT_TEST_USERNAME, true));
    }
}

function readUserData(string $username): array
{
    $path = userDataPath($username);
    if (!is_file($path)) {
        $data = defaultUserData($username, false);
        writeJsonFile($path, $data);
        return $data;
    }

    $data = readJsonFile($path, defaultUserData($username, false));
    $data['profile'] ??= [];
    $data['loans'] ??= [];
    $data['profile']['username'] = $username;
    $data['profile']['updated_at'] ??= nowIso();
    $data['profile']['created_at'] ??= nowIso();
    $data['profile']['strategy_extra_budget'] = (float)($data['profile']['strategy_extra_budget'] ?? 0);

    return $data;
}

function writeUserData(string $username, array $data): void
{
    $data['profile'] ??= [];
    $data['loans'] ??= [];
    $data['profile']['username'] = $username;
    $data['profile']['updated_at'] = nowIso();
    $data['profile']['created_at'] ??= nowIso();
    writeJsonFile(userDataPath($username), $data);
}

function currentUser(): ?array
{
    $username = $_SESSION['debt_payoff_user'] ?? null;
    if (!is_string($username) || $username === '') {
        return null;
    }

    return findAccount($username);
}

function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: index.php');
        exit;
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();
    if (($user['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        exit('Admin access required.');
    }

    return $user;
}

function verifyPassword(array $account, string $password): bool
{
    $hash = (string)($account['password_hash'] ?? '');
    return $hash !== '' && password_verify($password, $hash);
}

function authenticateUser(string $username, string $password): bool
{
    $account = findAccount($username);
    if ($account === null || !verifyPassword($account, $password)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['debt_payoff_user'] = $account['username'];
    return true;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
}

function registerUser(string $username, string $password): array
{
    $username = cleanUsername($username);
    $password = trim($password);

    if ($username === '') {
        return ['ok' => false, 'error' => 'Username is required.'];
    }
    if (strlen($username) < 3) {
        return ['ok' => false, 'error' => 'Username must be at least 3 characters.'];
    }
    if (strlen($password) < 4) {
        return ['ok' => false, 'error' => 'Password must be at least 4 characters.'];
    }
    if (findAccount($username) !== null) {
        return ['ok' => false, 'error' => 'That username is already in use.'];
    }

    $timestamp = nowIso();
    $role = adminExists() ? 'user' : 'admin';
    upsertAccount([
        'username' => $username,
        'role' => $role,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    writeUserData($username, defaultUserData($username, false));

    return ['ok' => true, 'role' => $role];
}

function updateAccount(string $existingUsername, string $newUsername, string $role, ?string $newPassword = null): array
{
    $existingUsername = cleanUsername($existingUsername);
    $newUsername = cleanUsername($newUsername);
    $account = findAccount($existingUsername);

    if ($account === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }
    if ($newUsername === '') {
        return ['ok' => false, 'error' => 'Username is required.'];
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }
    if ($existingUsername !== $newUsername && findAccount($newUsername) !== null) {
        return ['ok' => false, 'error' => 'The new username is already taken.'];
    }

    $account['role'] = $role;
    $account['updated_at'] = nowIso();
    if ($newPassword !== null && $newPassword !== '') {
        if (strlen($newPassword) < 4) {
            return ['ok' => false, 'error' => 'Password must be at least 4 characters.'];
        }
        $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    if ($existingUsername !== $newUsername) {
        $oldPath = userDataPath($existingUsername);
        $newPath = userDataPath($newUsername);
        if (is_file($oldPath)) {
            rename($oldPath, $newPath);
        }
        removeAccount($existingUsername);
        $account['username'] = $newUsername;

        $data = readUserData($newUsername);
        $data['profile']['username'] = $newUsername;
        writeUserData($newUsername, $data);
    }

    upsertAccount($account);
    return ['ok' => true];
}

function deleteUserAccount(string $username): array
{
    $username = cleanUsername($username);
    $account = findAccount($username);
    if ($account === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $adminCount = count(array_filter(readAccounts()['users'] ?? [], fn(array $entry) => ($entry['role'] ?? 'user') === 'admin'));
    if (($account['role'] ?? 'user') === 'admin' && $adminCount <= 1) {
        return ['ok' => false, 'error' => 'At least one admin account must remain.'];
    }

    removeAccount($username);
    $path = userDataPath($username);
    if (is_file($path)) {
        unlink($path);
    }

    return ['ok' => true];
}

function resetUserPassword(string $username, string $newPassword): array
{
    $newPassword = trim($newPassword);
    if (strlen($newPassword) < 4) {
        return ['ok' => false, 'error' => 'Password must be at least 4 characters.'];
    }

    $account = findAccount($username);
    if ($account === null) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $account['updated_at'] = nowIso();
    upsertAccount($account);

    return ['ok' => true];
}

function numericField(array $source, string $key): float
{
    return round((float)($source[$key] ?? 0), 2);
}

function normalizeLoanPayload(array $payload, ?array $existing = null): array
{
    $loan = $existing ?? ['id' => bin2hex(random_bytes(8))];
    $loan['name'] = trim((string)($payload['name'] ?? ''));
    $loan['type'] = trim((string)($payload['type'] ?? 'Loan'));
    $loan['category'] = trim((string)($payload['category'] ?? 'Other'));
    $loan['current_balance'] = max(0, numericField($payload, 'current_balance'));
    $loan['apr'] = max(0, numericField($payload, 'apr'));
    $loan['monthly_payment'] = max(0, numericField($payload, 'monthly_payment'));
    $loan['original_date'] = trim((string)($payload['original_date'] ?? ''));
    $loan['original_balance'] = max(0, numericField($payload, 'original_balance'));
    $loan['extra_monthly'] = max(0, numericField($payload, 'extra_monthly'));
    $loan['annual_extra'] = max(0, numericField($payload, 'annual_extra'));
    $loan['annual_extra_month'] = min(12, max(1, (int)($payload['annual_extra_month'] ?? 1)));
    $loan['lump_sum'] = max(0, numericField($payload, 'lump_sum'));
    $loan['notes'] = trim((string)($payload['notes'] ?? ''));
    return $loan;
}

function saveLoan(string $username, array $payload): void
{
    $data = readUserData($username);
    $loanId = (string)($payload['loan_id'] ?? '');
    $updated = false;

    foreach ($data['loans'] as $index => $loan) {
        if (($loan['id'] ?? '') === $loanId && $loanId !== '') {
            $data['loans'][$index] = normalizeLoanPayload($payload, $loan);
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $data['loans'][] = normalizeLoanPayload($payload);
    }

    writeUserData($username, $data);
}

function deleteLoan(string $username, string $loanId): void
{
    $data = readUserData($username);
    $data['loans'] = array_values(array_filter(
        $data['loans'],
        fn(array $loan) => ($loan['id'] ?? '') !== $loanId
    ));
    writeUserData($username, $data);
}

function updateStrategyBudget(string $username, float $budget): void
{
    $data = readUserData($username);
    $data['profile']['strategy_extra_budget'] = max(0, round($budget, 2));
    writeUserData($username, $data);
}

function currency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function pct(float $amount): string
{
    return number_format($amount, 2) . '%';
}

function loanTypes(): array
{
    return ['Credit Card', 'Mortgage', 'Auto Loan', 'Personal Loan', 'Student Loan', 'Home Equity', 'Medical Debt', 'Other'];
}

function loanCategories(): array
{
    return ['Credit Cards', 'Mortgage', 'Auto Loans', 'Personal Loans', 'Student Loans', 'Home Equity', 'Medical', 'Other'];
}

function monthName(int $month): string
{
    return DateTimeImmutable::createFromFormat('!m', (string)$month)?->format('F') ?? 'January';
}

function renderOptions(array $values, string $selected): string
{
    $html = '';
    foreach ($values as $value) {
        $isSelected = $value === $selected ? ' selected' : '';
        $html .= '<option value="' . h($value) . '"' . $isSelected . '>' . h($value) . '</option>';
    }

    return $html;
}

function payoffDateLabel(?string $isoDate): string
{
    if ($isoDate === null || $isoDate === '') {
        return 'Not projected';
    }

    try {
        return (new DateTimeImmutable($isoDate))->format('Y-m');
    } catch (Throwable) {
        return $isoDate;
    }
}

function amortizeLoan(array $loan, bool $useExtras): array
{
    $balance = round((float)($loan['current_balance'] ?? 0), 2);
    $apr = max(0, (float)($loan['apr'] ?? 0));
    $monthlyPayment = max(0, (float)($loan['monthly_payment'] ?? 0));
    $extraMonthly = $useExtras ? max(0, (float)($loan['extra_monthly'] ?? 0)) : 0.0;
    $annualExtra = $useExtras ? max(0, (float)($loan['annual_extra'] ?? 0)) : 0.0;
    $annualExtraMonth = min(12, max(1, (int)($loan['annual_extra_month'] ?? 1)));
    $lumpSum = $useExtras ? max(0, (float)($loan['lump_sum'] ?? 0)) : 0.0;
    $monthlyRate = $apr / 100 / 12;
    $rows = [];
    $interestTotal = 0.0;
    $principalTotal = 0.0;
    $extraTotal = 0.0;
    $startDate = new DateTimeImmutable('first day of this month');
    $step = 0;
    $negativeAmortization = false;

    if ($balance <= 0) {
        return [
            'rows' => [],
            'interest_total' => 0.0,
            'principal_total' => 0.0,
            'extra_total' => 0.0,
            'months' => 0,
            'payoff_date' => null,
            'negative_amortization' => false,
            'remaining_balance' => 0.0,
        ];
    }

    while ($balance > 0.009 && $step < 1200) {
        $periodDate = $startDate->modify('+' . $step . ' month');
        $startingBalance = $balance;
        $interest = round($startingBalance * $monthlyRate, 2);
        $extraPayment = $extraMonthly;

        if ($step === 0 && $lumpSum > 0) {
            $extraPayment += $lumpSum;
        }
        if ($annualExtra > 0 && (int)$periodDate->format('n') === $annualExtraMonth) {
            $extraPayment += $annualExtra;
        }

        if (($monthlyPayment + $extraPayment) <= $interest && $monthlyRate > 0) {
            $negativeAmortization = true;
            $rows[] = [
                'payment_number' => $step + 1,
                'period' => $periodDate->format('Y-m'),
                'starting_balance' => $startingBalance,
                'payment' => round($monthlyPayment + $extraPayment, 2),
                'principal' => 0.0,
                'interest' => $interest,
                'extra' => $extraPayment,
                'ending_balance' => round($startingBalance + $interest, 2),
            ];
            $balance = round($startingBalance + $interest, 2);
            break;
        }

        $plannedPayment = $monthlyPayment + $extraPayment;
        $actualPayment = min(round($startingBalance + $interest, 2), $plannedPayment);
        $principal = max(0, round($actualPayment - $interest, 2));
        $endingBalance = max(0, round($startingBalance + $interest - $actualPayment, 2));

        $interestTotal += $interest;
        $principalTotal += $principal;
        $extraTotal += $extraPayment;

        $rows[] = [
            'payment_number' => $step + 1,
            'period' => $periodDate->format('Y-m'),
            'starting_balance' => $startingBalance,
            'payment' => $actualPayment,
            'principal' => $principal,
            'interest' => $interest,
            'extra' => $extraPayment,
            'ending_balance' => $endingBalance,
        ];

        $balance = $endingBalance;
        $step++;
    }

    $payoffDate = null;
    if (!$negativeAmortization && $balance <= 0.009 && !empty($rows)) {
        $lastRow = $rows[array_key_last($rows)];
        $payoffDate = (new DateTimeImmutable($lastRow['period'] . '-01'))->format('Y-m-d');
    }

    return [
        'rows' => $rows,
        'interest_total' => round($interestTotal, 2),
        'principal_total' => round($principalTotal, 2),
        'extra_total' => round($extraTotal, 2),
        'months' => count($rows),
        'payoff_date' => $payoffDate,
        'negative_amortization' => $negativeAmortization,
        'remaining_balance' => round($balance, 2),
    ];
}

function summarizeLoan(array $loan): array
{
    $baseline = amortizeLoan($loan, false);
    $accelerated = amortizeLoan($loan, true);

    return [
        'baseline' => $baseline,
        'accelerated' => $accelerated,
        'interest_saved' => max(0, round($baseline['interest_total'] - $accelerated['interest_total'], 2)),
        'months_saved' => max(0, $baseline['months'] - $accelerated['months']),
    ];
}

function simulateStrategy(array $loans, float $extraBudget, string $method): array
{
    $activeLoans = [];
    $interestTotal = 0.0;
    $timeline = [];
    $months = 0;
    $startDate = new DateTimeImmutable('first day of this month');

    foreach ($loans as $loan) {
        $balance = round((float)($loan['current_balance'] ?? 0), 2);
        if ($balance <= 0) {
            continue;
        }

        $activeLoans[] = [
            'name' => (string)($loan['name'] ?? 'Loan'),
            'balance' => $balance,
            'apr' => max(0, (float)($loan['apr'] ?? 0)),
            'min_payment' => max(0, (float)($loan['monthly_payment'] ?? 0)),
        ];
    }

    if (empty($activeLoans)) {
        return [
            'method' => $method,
            'months' => 0,
            'interest_total' => 0.0,
            'payoff_date' => null,
            'timeline' => [],
            'negative_amortization' => false,
        ];
    }

    $baseCapacity = array_sum(array_map(fn(array $loan) => $loan['min_payment'], $activeLoans)) + max(0, $extraBudget);
    $negativeAmortization = false;

    while (!empty($activeLoans) && $months < 1200) {
        usort($activeLoans, function (array $a, array $b) use ($method): int {
            if ($method === 'avalanche') {
                $aprSort = $b['apr'] <=> $a['apr'];
                return $aprSort !== 0 ? $aprSort : ($a['balance'] <=> $b['balance']);
            }

            $balanceSort = $a['balance'] <=> $b['balance'];
            return $balanceSort !== 0 ? $balanceSort : ($b['apr'] <=> $a['apr']);
        });

        $monthLabel = $startDate->modify('+' . $months . ' month')->format('Y-m');
        $minimumsDue = array_sum(array_map(fn(array $loan) => $loan['min_payment'], $activeLoans));
        $availableExtra = max(0, round($baseCapacity - $minimumsDue, 2));
        $payments = [];

        foreach ($activeLoans as $index => $loan) {
            $interest = round($loan['balance'] * ($loan['apr'] / 100 / 12), 2);
            $activeLoans[$index]['balance'] = round($loan['balance'] + $interest, 2);
            $interestTotal += $interest;

            if ($loan['min_payment'] <= $interest && $loan['apr'] > 0) {
                $negativeAmortization = true;
            }
        }

        foreach ($activeLoans as $index => $loan) {
            $payment = min($activeLoans[$index]['balance'], $loan['min_payment']);
            $activeLoans[$index]['balance'] = round($activeLoans[$index]['balance'] - $payment, 2);
            $payments[] = [
                'name' => $loan['name'],
                'payment' => $payment,
            ];
        }

        while ($availableExtra > 0.009 && !empty($activeLoans)) {
            usort($activeLoans, function (array $a, array $b) use ($method): int {
                if ($method === 'avalanche') {
                    $aprSort = $b['apr'] <=> $a['apr'];
                    return $aprSort !== 0 ? $aprSort : ($a['balance'] <=> $b['balance']);
                }

                $balanceSort = $a['balance'] <=> $b['balance'];
                return $balanceSort !== 0 ? $balanceSort : ($b['apr'] <=> $a['apr']);
            });

            $targetName = $activeLoans[0]['name'];
            $extraPayment = min($availableExtra, $activeLoans[0]['balance']);
            $activeLoans[0]['balance'] = round($activeLoans[0]['balance'] - $extraPayment, 2);
            $availableExtra = round($availableExtra - $extraPayment, 2);

            foreach ($payments as &$paymentRow) {
                if ($paymentRow['name'] === $targetName) {
                    $paymentRow['payment'] = round($paymentRow['payment'] + $extraPayment, 2);
                    break;
                }
            }
            unset($paymentRow);

            if ($activeLoans[0]['balance'] > 0.009) {
                break;
            }
        }

        $activeLoans = array_values(array_filter($activeLoans, fn(array $loan) => $loan['balance'] > 0.009));
        $timeline[] = [
            'period' => $monthLabel,
            'target' => $payments[0]['name'] ?? 'None',
            'payments' => $payments,
        ];
        $months++;
    }

    $payoffDate = !empty($timeline)
        ? (new DateTimeImmutable($timeline[array_key_last($timeline)]['period'] . '-01'))->format('Y-m-d')
        : null;

    return [
        'method' => $method,
        'months' => $months,
        'interest_total' => round($interestTotal, 2),
        'payoff_date' => $payoffDate,
        'timeline' => $timeline,
        'negative_amortization' => $negativeAmortization,
    ];
}

function overallMetrics(array $loans): array
{
    $totalDebt = 0.0;
    $totalOriginalBalance = 0.0;
    $totalMinimums = 0.0;
    $weightedAprNumerator = 0.0;
    $loanCount = 0;
    $baselineInterest = 0.0;
    $acceleratedInterest = 0.0;
    $latestProjectedDate = null;

    foreach ($loans as $loan) {
        $loanCount++;
        $balance = (float)($loan['current_balance'] ?? 0);
        $totalDebt += $balance;
        $totalOriginalBalance += (float)($loan['original_balance'] ?? 0);
        $totalMinimums += (float)($loan['monthly_payment'] ?? 0);
        $weightedAprNumerator += $balance * (float)($loan['apr'] ?? 0);

        $summary = summarizeLoan($loan);
        $baselineInterest += $summary['baseline']['interest_total'];
        $acceleratedInterest += $summary['accelerated']['interest_total'];

        foreach ([$summary['baseline']['payoff_date'], $summary['accelerated']['payoff_date']] as $candidate) {
            if ($candidate !== null && ($latestProjectedDate === null || $candidate > $latestProjectedDate)) {
                $latestProjectedDate = $candidate;
            }
        }
    }

    return [
        'loan_count' => $loanCount,
        'total_debt' => round($totalDebt, 2),
        'total_original_balance' => round($totalOriginalBalance, 2),
        'total_paid_down' => round($totalOriginalBalance - $totalDebt, 2),
        'total_minimums' => round($totalMinimums, 2),
        'weighted_apr' => $totalDebt > 0 ? round($weightedAprNumerator / $totalDebt, 2) : 0.0,
        'baseline_interest' => round($baselineInterest, 2),
        'accelerated_interest' => round($acceleratedInterest, 2),
        'interest_savings' => round($baselineInterest - $acceleratedInterest, 2),
        'latest_projected_date' => $latestProjectedDate,
    ];
}

function readProjectRevision(): string
{
    if (is_file(VERSION_FILE)) {
        $value = trim((string)file_get_contents(VERSION_FILE));
        if ($value !== '') {
            return $value;
        }
    }

    return APP_REVISION;
}

function readProjectModifiedAt(): string
{
    $path = resolveProjectFile([CHANGELOG_FILE, TODO_FILE, __FILE__]);
    $timestamp = $path !== null ? filemtime($path) : false;
    return $timestamp ? date('Y-m-d H:i:s T', $timestamp) : '';
}

function resolveProjectFile(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function markdownFileToHtml(string $path, string $fallback): string
{
    if (!is_file($path)) {
        return '<p class="small">' . h($fallback) . '</p>';
    }

    $lines = preg_split("/\r\n|\n|\r/", (string)file_get_contents($path)) ?: [];
    $html = [];
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^#\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<h2>' . h($matches[1]) . '</h2>';
            continue;
        }

        if (preg_match('/^##\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<h3>' . h($matches[1]) . '</h3>';
            continue;
        }

        if (preg_match('/^- (.+)$/', $trimmed, $matches)) {
            if (!$inList) {
                $html[] = '<ul>';
                $inList = true;
            }
            $html[] = '<li>' . h($matches[1]) . '</li>';
            continue;
        }

        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }

        $html[] = '<p>' . h($trimmed) . '</p>';
    }

    if ($inList) {
        $html[] = '</ul>';
    }

    return implode("\n", $html);
}

function changelogHtml(): string
{
    return markdownFileToHtml(CHANGELOG_FILE, 'No changelog is available.');
}

function todoHtml(): string
{
    return markdownFileToHtml(TODO_FILE, 'No project todo list is available.');
}

function userStorageSize(string $username): int
{
    $path = userDataPath($username);
    return is_file($path) ? (int)filesize($path) : 0;
}

function humanBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB'];
    $value = (float)$bytes;
    foreach ($units as $unit) {
        $value /= 1024;
        if ($value < 1024) {
            return number_format($value, 2) . ' ' . $unit;
        }
    }

    return number_format($value, 2) . ' TB';
}

bootstrapApp();
