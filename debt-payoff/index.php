<?php
/*
    Debt Payoff Planner
    Revision: 1.0.0
    Description: Main dashboard with login, registration, private debt tracking, payoff modeling, and strategy summaries.
*/

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$flash = '';
$error = '';

if (isset($_GET['logout'])) {
    logoutUser();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login') {
        if (authenticateUser((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid username or password.';
    } elseif ($action === 'register') {
        $result = registerUser((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($result['ok']) {
            authenticateUser((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
            header('Location: index.php');
            exit;
        }
        $error = $result['error'] ?? 'Registration failed.';
    } else {
        $account = requireLogin();
        $username = (string)$account['username'];

        if ($action === 'save_loan') {
            saveLoan($username, $_POST);
            $flash = 'Loan saved.';
        } elseif ($action === 'delete_loan') {
            deleteLoan($username, (string)($_POST['loan_id'] ?? ''));
            $flash = 'Loan deleted.';
        } elseif ($action === 'save_strategy') {
            updateStrategyBudget($username, (float)($_POST['strategy_extra_budget'] ?? 0));
            $flash = 'Strategy budget updated.';
        }
    }
}

$currentAccount = currentUser();
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();

if ($currentAccount === null):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <aside class="project-badge">
        <strong>Project Rev:</strong> <?= h($projectRevision) ?><br>
        <strong>Modified:</strong> <?= h($projectModifiedAt) ?>
    </aside>
    <nav class="nav">
        <a href="index.php">Home</a>
        <a href="changelog.php">Changelog</a>
        <a href="todo.php">Todo</a>
    </nav>

    <header class="page-header">
        <div>
            <h1><?= h(APP_NAME) ?></h1>
            <p class="small">Private, per-user debt tracking for credit cards, mortgages, auto loans, personal loans, and payoff planning.</p>
        </div>
        <div class="status-box">
            <strong>Test user:</strong> <?= h(DEFAULT_TEST_USERNAME) ?><br>
            <strong>Password:</strong> <?= h(DEFAULT_TEST_PASSWORD) ?><br>
            <strong>Admin setup:</strong> First newly registered account becomes admin.
        </div>
    </header>

    <?php if ($error !== ''): ?>
    <section class="card alert alert-error"><?= h($error) ?></section>
    <?php endif; ?>

    <section class="two-column">
        <form method="post" class="card">
            <h2>Login</h2>
            <input type="hidden" name="action" value="login">
            <label>Username
                <input type="text" name="username" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <div class="actions">
                <button type="submit">Sign In</button>
            </div>
        </form>

        <form method="post" class="card">
            <h2>Register</h2>
            <input type="hidden" name="action" value="register">
            <label>Username
                <input type="text" name="username" required minlength="3">
            </label>
            <label>Password
                <input type="password" name="password" required minlength="4">
            </label>
            <div class="actions">
                <button type="submit">Create Account</button>
            </div>
            <p class="small">If no admin exists yet, the first new account created here will receive admin access.</p>
        </form>
    </section>
</main>
</body>
</html>
<?php
exit;
endif;

$userData = readUserData((string)$currentAccount['username']);
$loans = $userData['loans'];
$loanSummaries = [];
foreach ($loans as $loan) {
    $loanSummaries[(string)($loan['id'] ?? '')] = summarizeLoan($loan);
}
$metrics = overallMetrics($loans, $loanSummaries);
$strategyBudget = (float)($userData['profile']['strategy_extra_budget'] ?? 0);
$snowball = simulateStrategy($loans, $strategyBudget, 'snowball');
$avalanche = simulateStrategy($loans, $strategyBudget, 'avalanche');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container container-wide">
    <aside class="project-badge">
        <strong>Project Rev:</strong> <?= h($projectRevision) ?><br>
        <strong>Modified:</strong> <?= h($projectModifiedAt) ?>
    </aside>
    <nav class="nav">
        <a href="index.php">Dashboard</a>
        <?php if (($currentAccount['role'] ?? 'user') === 'admin'): ?>
        <a href="admin.php">Admin</a>
        <?php endif; ?>
        <a href="changelog.php">Changelog</a>
        <a href="todo.php">Todo</a>
        <a href="index.php?logout=1" class="nav-button">Logout</a>
    </nav>

    <header class="page-header">
        <div>
            <h1><?= h(APP_NAME) ?></h1>
            <p class="small">Signed in as <strong><?= h((string)$currentAccount['username']) ?></strong>. Loan data is private to this account.</p>
        </div>
        <div class="status-box">
            <strong>Loans:</strong> <?= count($loans) ?><br>
            <strong>Total debt:</strong> <?= h(currency($metrics['total_debt'])) ?><br>
            <strong>Monthly minimums:</strong> <?= h(currency($metrics['total_minimums'])) ?>
        </div>
    </header>

    <?php if ($flash !== ''): ?>
    <section class="card alert alert-success"><?= h($flash) ?></section>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="card stat-card"><strong>Total debt</strong><span><?= h(currency($metrics['total_debt'])) ?></span></div>
        <div class="card stat-card"><strong>Total original balance</strong><span><?= h(currency($metrics['total_original_balance'])) ?></span></div>
        <div class="card stat-card"><strong>Paid down so far</strong><span><?= h(currency($metrics['total_paid_down'])) ?></span></div>
        <div class="card stat-card"><strong>Weighted APR</strong><span><?= h(pct($metrics['weighted_apr'])) ?></span></div>
        <div class="card stat-card"><strong>Baseline interest</strong><span><?= h(currency($metrics['baseline_interest'])) ?></span></div>
        <div class="card stat-card"><strong>Current accelerated interest</strong><span><?= h(currency($metrics['accelerated_interest'])) ?></span></div>
        <div class="card stat-card"><strong>Interest savings</strong><span><?= h(currency($metrics['interest_savings'])) ?></span></div>
        <div class="card stat-card"><strong>Latest projected payoff</strong><span><?= h(payoffDateLabel($metrics['latest_projected_date'])) ?></span></div>
    </section>

    <section class="two-column">
        <form method="post" class="card">
            <h2>Strategy Budget</h2>
            <input type="hidden" name="action" value="save_strategy">
            <label>Monthly extra budget available for strategy comparison
                <input type="number" step="0.01" min="0" name="strategy_extra_budget" value="<?= h((string)$strategyBudget) ?>">
            </label>
            <div class="actions">
                <button type="submit">Save Strategy Budget</button>
            </div>
            <p class="small">This compares the debt snowball method against the highest-APR avalanche method using the same extra monthly payoff budget.</p>
        </form>

        <div class="card">
            <h2>What This Baseline Covers</h2>
            <ul class="plain-list">
                <li>Private user registration and login</li>
                <li>Debt tracking across multiple loan types and categories</li>
                <li>Per-loan payoff modeling with extra monthly, annual blue moon, and lump-sum payments</li>
                <li>Snowball and avalanche strategy comparisons</li>
                <li>Admin-only user management that does not reveal loan contents</li>
            </ul>
        </div>
    </section>

    <section class="two-column">
        <div class="card">
            <h2>Snowball Plan</h2>
            <p class="small">Smallest balance first. Good for faster account count reduction and momentum.</p>
            <div class="summary-grid">
                <div><strong>Debt-free</strong><span><?= h(payoffDateLabel($snowball['payoff_date'])) ?></span></div>
                <div><strong>Months</strong><span><?= h((string)$snowball['months']) ?></span></div>
                <div><strong>Interest</strong><span><?= h(currency($snowball['interest_total'])) ?></span></div>
                <div><strong>Target now</strong><span><?= h($snowball['timeline'][0]['target'] ?? 'None') ?></span></div>
            </div>
        </div>

        <div class="card">
            <h2>Avalanche Plan</h2>
            <p class="small">Highest APR first. Good for the least interest cost.</p>
            <div class="summary-grid">
                <div><strong>Debt-free</strong><span><?= h(payoffDateLabel($avalanche['payoff_date'])) ?></span></div>
                <div><strong>Months</strong><span><?= h((string)$avalanche['months']) ?></span></div>
                <div><strong>Interest</strong><span><?= h(currency($avalanche['interest_total'])) ?></span></div>
                <div><strong>Target now</strong><span><?= h($avalanche['timeline'][0]['target'] ?? 'None') ?></span></div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Add Loan</h2>
        <form method="post" class="loan-form-grid">
            <input type="hidden" name="action" value="save_loan">
            <label>Loan name
                <input type="text" name="name" required>
            </label>
            <label>Type
                <select name="type"><?= renderOptions(loanTypes(), 'Credit Card') ?></select>
            </label>
            <label>Category
                <select name="category"><?= renderOptions(loanCategories(), 'Credit Cards') ?></select>
            </label>
            <label>Current principal
                <input type="number" step="0.01" min="0" name="current_balance" required>
            </label>
            <label>APR
                <input type="number" step="0.01" min="0" name="apr" required>
            </label>
            <label>Monthly payment
                <input type="number" step="0.01" min="0" name="monthly_payment" required>
            </label>
            <label>Original date
                <input type="date" name="original_date">
            </label>
            <label>Original balance
                <input type="number" step="0.01" min="0" name="original_balance">
            </label>
            <label>Extra principal per month
                <input type="number" step="0.01" min="0" name="extra_monthly" value="0">
            </label>
            <label>Blue moon / annual payment
                <input type="number" step="0.01" min="0" name="annual_extra" value="0">
            </label>
            <label>Blue moon month
                <select name="annual_extra_month">
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                    <option value="<?= $month ?>"><?= h(monthName($month)) ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>One-time lump sum
                <input type="number" step="0.01" min="0" name="lump_sum" value="0">
            </label>
            <label class="full-width">Notes
                <textarea name="notes" rows="2"></textarea>
            </label>
            <div class="actions full-width">
                <button type="submit">Add Loan</button>
            </div>
        </form>
    </section>

    <?php if (empty($loans)): ?>
    <section class="card">
        <p class="small">No loans have been added yet.</p>
    </section>
    <?php endif; ?>

    <?php foreach ($loans as $loan): ?>
    <?php $summary = $loanSummaries[(string)($loan['id'] ?? '')] ?? summarizeLoan($loan); ?>
    <section class="card loan-card">
        <div class="loan-card-header">
            <div>
                <h2><?= h((string)$loan['name']) ?></h2>
                <p class="small"><?= h((string)$loan['type']) ?> | <?= h((string)$loan['category']) ?></p>
            </div>
            <div class="status-box compact">
                <strong>Current balance:</strong> <?= h(currency((float)$loan['current_balance'])) ?><br>
                <strong>APR:</strong> <?= h(pct((float)$loan['apr'])) ?><br>
                <strong>Monthly payment:</strong> <?= h(currency((float)$loan['monthly_payment'])) ?>
            </div>
        </div>

        <div class="stats-grid loan-metrics">
            <div class="card stat-card"><strong>Baseline payoff</strong><span><?= h(payoffDateLabel($summary['baseline']['payoff_date'])) ?></span></div>
            <div class="card stat-card"><strong>Accelerated payoff</strong><span><?= h(payoffDateLabel($summary['accelerated']['payoff_date'])) ?></span></div>
            <div class="card stat-card"><strong>Months saved</strong><span><?= h((string)$summary['months_saved']) ?></span></div>
            <div class="card stat-card"><strong>Interest saved</strong><span><?= h(currency($summary['interest_saved'])) ?></span></div>
        </div>

        <?php if ($summary['baseline']['negative_amortization'] || $summary['accelerated']['negative_amortization']): ?>
        <p class="small alert-inline">Warning: payment settings fall below monthly interest for this loan, so the payoff projection is not fully amortizing.</p>
        <?php endif; ?>

        <form method="post" class="loan-form-grid">
            <input type="hidden" name="action" value="save_loan">
            <input type="hidden" name="loan_id" value="<?= h((string)$loan['id']) ?>">
            <label>Loan name
                <input type="text" name="name" value="<?= h((string)$loan['name']) ?>" required>
            </label>
            <label>Type
                <select name="type"><?= renderOptions(loanTypes(), (string)$loan['type']) ?></select>
            </label>
            <label>Category
                <select name="category"><?= renderOptions(loanCategories(), (string)$loan['category']) ?></select>
            </label>
            <label>Current principal
                <input type="number" step="0.01" min="0" name="current_balance" value="<?= h((string)$loan['current_balance']) ?>" required>
            </label>
            <label>APR
                <input type="number" step="0.01" min="0" name="apr" value="<?= h((string)$loan['apr']) ?>" required>
            </label>
            <label>Monthly payment
                <input type="number" step="0.01" min="0" name="monthly_payment" value="<?= h((string)$loan['monthly_payment']) ?>" required>
            </label>
            <label>Original date
                <input type="date" name="original_date" value="<?= h((string)$loan['original_date']) ?>">
            </label>
            <label>Original balance
                <input type="number" step="0.01" min="0" name="original_balance" value="<?= h((string)$loan['original_balance']) ?>">
            </label>
            <label>Extra principal per month
                <input type="number" step="0.01" min="0" name="extra_monthly" value="<?= h((string)$loan['extra_monthly']) ?>">
            </label>
            <label>Blue moon / annual payment
                <input type="number" step="0.01" min="0" name="annual_extra" value="<?= h((string)$loan['annual_extra']) ?>">
            </label>
            <label>Blue moon month
                <select name="annual_extra_month">
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                    <option value="<?= $month ?>"<?= (int)$loan['annual_extra_month'] === $month ? ' selected' : '' ?>><?= h(monthName($month)) ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>One-time lump sum
                <input type="number" step="0.01" min="0" name="lump_sum" value="<?= h((string)$loan['lump_sum']) ?>">
            </label>
            <label class="full-width">Notes
                <textarea name="notes" rows="2"><?= h((string)$loan['notes']) ?></textarea>
            </label>
            <div class="actions full-width">
                <button type="submit">Save Loan</button>
            </div>
        </form>

        <form method="post" class="inline-form">
            <input type="hidden" name="action" value="delete_loan">
            <input type="hidden" name="loan_id" value="<?= h((string)$loan['id']) ?>">
            <button type="submit" class="danger" onclick="return confirm('Delete this loan?');">Delete Loan</button>
        </form>

        <details class="schedule-block" open>
            <summary>Accelerated payoff table</summary>
            <div class="table-wrap">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Month</th>
                            <th>Start Balance</th>
                            <th>Payment</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Extra</th>
                            <th>End Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($summary['accelerated']['rows'] as $row): ?>
                        <tr>
                            <td><?= h((string)$row['payment_number']) ?></td>
                            <td><?= h($row['period']) ?></td>
                            <td><?= h(currency($row['starting_balance'])) ?></td>
                            <td><?= h(currency($row['payment'])) ?></td>
                            <td><?= h(currency($row['principal'])) ?></td>
                            <td><?= h(currency($row['interest'])) ?></td>
                            <td><?= h(currency($row['extra'])) ?></td>
                            <td><?= h(currency($row['ending_balance'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>
    <?php endforeach; ?>
</main>
</body>
</html>
