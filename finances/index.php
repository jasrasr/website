<?php
// File: index.php
// Purpose: Runs the shared finance budget tracker web interface.

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

const APP_REVISION = '1.0';
const DEFAULT_SAMPLE_PASSWORD = 'budget123';

function app_config(): array
{
    $config = [
        'users' => [
            'student' => ['password' => DEFAULT_SAMPLE_PASSWORD],
            'parent' => ['password' => DEFAULT_SAMPLE_PASSWORD],
        ],
    ];

    $localPath = __DIR__ . '/config.local.php';
    if (is_file($localPath)) {
        $local = require $localPath;
        if (is_array($local)) {
            if (isset($local['users']) && is_array($local['users'])) {
                $config['users'] = $local['users'];
                unset($local['users']);
            }
            $config = array_replace_recursive($config, $local);
        }
    }

    return $config;
}

function current_user(): ?string
{
    return isset($_SESSION['budget_user']) && is_string($_SESSION['budget_user'])
        ? $_SESSION['budget_user']
        : null;
}

function clean_username(string $username): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $username) ?: 'user';
}

function data_path(string $username): string
{
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0750, true);
    }

    return $dataDir . '/' . clean_username($username) . '.json';
}

function default_budget(): array
{
    return [
        'income' => [
            'mode' => 'hourly',
            'schedule' => 'biweekly',
            'hourlyRate' => 15,
            'hoursPerCheck' => 36,
            'grossPerCheck' => 540,
            'otherIncome' => 0,
            'withholding' => 12,
            'savingsRate' => 10,
        ],
        'expenses' => [
            ['id' => bin2hex(random_bytes(8)), 'name' => 'Lunch and snacks', 'amount' => 75, 'frequency' => 'weekly', 'type' => 'recurring', 'category' => 'Food'],
            ['id' => bin2hex(random_bytes(8)), 'name' => 'Gas', 'amount' => 45, 'frequency' => 'weekly', 'type' => 'recurring', 'category' => 'Gas'],
            ['id' => bin2hex(random_bytes(8)), 'name' => 'Car insurance', 'amount' => 165, 'frequency' => 'monthly', 'type' => 'recurring', 'category' => 'Insurance'],
            ['id' => bin2hex(random_bytes(8)), 'name' => 'Phone', 'amount' => 35, 'frequency' => 'monthly', 'type' => 'recurring', 'category' => 'Phone'],
            ['id' => bin2hex(random_bytes(8)), 'name' => 'Music streaming', 'amount' => 10.99, 'frequency' => 'monthly', 'type' => 'subscription', 'category' => 'Entertainment'],
            ['id' => bin2hex(random_bytes(8)), 'name' => 'Car maintenance fund', 'amount' => 50, 'frequency' => 'monthly', 'type' => 'recurring', 'category' => 'Car'],
        ],
    ];
}

function read_budget(string $username): array
{
    $path = data_path($username);
    if (!is_file($path)) {
        $budget = default_budget();
        write_budget($username, $budget);
        return $budget;
    }

    $json = file_get_contents($path);
    $decoded = json_decode($json ?: '', true);

    return is_array($decoded) ? $decoded : default_budget();
}

function write_budget(string $username, array $budget): void
{
    $path = data_path($username);
    file_put_contents($path, json_encode($budget, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function send_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function is_default_login_active(array $config): bool
{
    foreach ($config['users'] ?? [] as $user) {
        if (($user['password'] ?? '') === DEFAULT_SAMPLE_PASSWORD) {
            return true;
        }
    }

    return false;
}

function valid_password(array $userConfig, string $password): bool
{
    $hash = (string) ($userConfig['password_hash'] ?? '');
    if ($hash !== '' && password_verify($password, $hash)) {
        return true;
    }

    $plainPassword = (string) ($userConfig['password'] ?? '');
    return $plainPassword !== '' && hash_equals($plainPassword, $password);
}

$config = app_config();
$loginError = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $userConfig = $config['users'][$username] ?? null;

    if (is_array($userConfig) && valid_password($userConfig, $password)) {
        session_regenerate_id(true);
        $_SESSION['budget_user'] = $username;
        header('Location: index.php');
        exit;
    }

    $loginError = 'Invalid username or password.';
}

if (isset($_GET['api'])) {
    $username = current_user();
    if ($username === null) {
        send_json(['ok' => false, 'error' => 'Not signed in.'], 401);
    }

    if ($_GET['api'] === 'budget' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        send_json(['ok' => true, 'budget' => read_budget($username), 'username' => $username]);
    }

    if ($_GET['api'] === 'budget' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload) || !isset($payload['income'], $payload['expenses']) || !is_array($payload['expenses'])) {
            send_json(['ok' => false, 'error' => 'Invalid budget payload.'], 400);
        }

        write_budget($username, [
            'income' => is_array($payload['income']) ? $payload['income'] : [],
            'expenses' => $payload['expenses'],
            'updatedAt' => gmdate('c'),
        ]);
        send_json(['ok' => true]);
    }

    send_json(['ok' => false, 'error' => 'Unknown API route.'], 404);
}

$signedInUser = current_user();
$defaultLoginActive = is_default_login_active($config);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Budget Tracker</title>
  <style>
    :root {
      --bg: #f7f7f4;
      --panel: #fff;
      --text: #1d2522;
      --muted: #63706a;
      --line: #d9ded7;
      --accent: #28745c;
      --accent-strong: #1f5f4a;
      --warn: #b24a28;
      --warn-bg: #fff0e8;
      --good: #1f6f49;
      --good-bg: #eaf7ef;
      --shadow: 0 8px 24px rgba(23, 35, 31, .08);
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
    }

    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); }
    button, input, select { font: inherit; }
    button { border: 0; cursor: pointer; }
    header { background: #102d25; color: #f8fffb; border-bottom: 4px solid #d6a84f; }
    .header-inner, main { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
    .header-inner { padding: 24px 0 18px; display: flex; align-items: end; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
    h1 { margin: 0; font-size: clamp(1.65rem, 3vw, 2.4rem); line-height: 1.05; letter-spacing: 0; }
    h2 { margin: 0; font-size: 1.05rem; letter-spacing: 0; }
    .subtitle { margin: 8px 0 0; color: #c9d7d2; max-width: 680px; line-height: 1.45; }
    main { padding: 24px 0 40px; display: grid; gap: 18px; }
    .actions, .toolbar, .row-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .actions { justify-content: flex-end; }
    .button { min-height: 40px; padding: 9px 13px; border-radius: 7px; background: #e8eee9; color: #14362d; border: 1px solid #c6d2cc; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
    .button.primary { background: var(--accent); color: #fff; border-color: var(--accent-strong); }
    .button.danger { background: var(--warn-bg); color: var(--warn); border-color: #f0c7b7; }
    .panel, .stat { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; box-shadow: var(--shadow); }
    .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .stat { padding: 15px; min-height: 118px; display: grid; align-content: space-between; gap: 10px; }
    .label { color: var(--muted); font-size: .88rem; font-weight: 700; text-transform: uppercase; }
    .value { font-size: clamp(1.45rem, 2.2vw, 2rem); font-weight: 800; line-height: 1.1; overflow-wrap: anywhere; }
    .detail, .footer-note, .save-status { color: var(--muted); font-size: .9rem; line-height: 1.35; }
    .layout { display: grid; grid-template-columns: minmax(300px, 390px) minmax(0, 1fr); gap: 18px; align-items: start; }
    .panel-header { padding: 16px 16px 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .panel-body { padding: 16px; }
    .form-grid { display: grid; gap: 12px; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    label { display: grid; gap: 6px; color: var(--muted); font-size: .85rem; font-weight: 700; }
    input, select { width: 100%; min-height: 40px; border: 1px solid #c7d0ca; border-radius: 7px; padding: 8px 10px; color: var(--text); background: #fff; }
    input:focus, select:focus, button:focus-visible { outline: 3px solid rgba(40, 116, 92, .25); outline-offset: 2px; }
    .insights { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .insight { border: 1px solid var(--line); border-radius: 8px; padding: 13px; background: #fbfcfa; min-height: 118px; }
    .insight strong { display: block; font-size: 1.1rem; margin-bottom: 6px; overflow-wrap: anywhere; }
    .insight span { color: var(--muted); line-height: 1.4; }
    .insight.warning, .login-warning { background: var(--warn-bg); border-color: #efc5b4; }
    .insight.good { background: var(--good-bg); border-color: #bedec9; }
    .login-card { width: min(430px, calc(100% - 32px)); margin: 52px auto; }
    .login-warning { border: 1px solid #efc5b4; border-radius: 8px; padding: 12px; color: var(--warn); }
    .error { color: var(--warn); font-weight: 700; }
    .table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
    table { width: 100%; border-collapse: collapse; min-width: 760px; }
    th, td { text-align: left; padding: 11px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; white-space: nowrap; }
    th { color: var(--muted); font-size: .8rem; text-transform: uppercase; background: #fafbf9; }
    td.money, th.money { text-align: right; }
    .tag { display: inline-flex; min-height: 24px; align-items: center; border-radius: 999px; padding: 3px 8px; font-size: .8rem; font-weight: 700; background: #edf1ee; color: #40504a; }
    .row-actions { justify-content: flex-end; }
    .icon-button { width: 44px; height: 36px; border-radius: 7px; border: 1px solid #ccd6d0; background: #fff; color: #22352f; font-weight: 800; }
    .empty { padding: 24px; color: var(--muted); text-align: center; border-top: 1px solid var(--line); }
    .hidden { display: none !important; }

    @media (max-width: 980px) {
      .summary-grid, .insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .layout { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
      .header-inner, main { width: min(100% - 20px, 1180px); }
      .summary-grid, .insights, .two-col { grid-template-columns: 1fr; }
      .actions, .toolbar { width: 100%; }
      .button { flex: 1 1 auto; }
    }
  </style>
</head>
<body>
<?php if ($signedInUser === null): ?>
  <main class="login-card">
    <section class="panel">
      <div class="panel-header"><h1>Budget Tracker</h1></div>
      <div class="panel-body">
        <form class="form-grid" method="post">
          <input type="hidden" name="action" value="login">
          <?php if ($defaultLoginActive): ?>
            <div class="login-warning">Default sample password is active. Create `config.local.php` before publishing this page.</div>
          <?php endif; ?>
          <?php if ($loginError !== ''): ?>
            <div class="error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <label>
            Username
            <input name="username" autocomplete="username" required>
          </label>
          <label>
            Password
            <input name="password" type="password" autocomplete="current-password" required>
          </label>
          <button class="button primary" type="submit">Sign In</button>
        </form>
      </div>
    </section>
  </main>
<?php else: ?>
  <header>
    <div class="header-inner">
      <div>
        <h1>Budget Tracker</h1>
        <p class="subtitle">Plan paychecks, expenses, food spending, car costs, gas, insurance, and subscriptions with monthly and annual projections.</p>
      </div>
      <div class="actions" aria-label="Budget actions">
        <span class="save-status" id="saveStatus">Signed in as <?= htmlspecialchars($signedInUser, ENT_QUOTES, 'UTF-8') ?></span>
        <button class="button" id="exportButton" type="button">Export</button>
        <label class="button" for="importFile">Import</label>
        <input class="hidden" id="importFile" type="file" accept="application/json">
        <button class="button danger" id="resetButton" type="button">Reset</button>
        <a class="button" href="?logout=1">Sign Out</a>
      </div>
    </div>
  </header>

  <main>
    <section class="summary-grid" aria-label="Budget summary">
      <article class="stat"><div class="label">Monthly Income</div><div class="value" id="monthlyIncome">$0</div><div class="detail" id="incomeDetail">0 paychecks per year</div></article>
      <article class="stat"><div class="label">Monthly Expenses</div><div class="value" id="monthlyExpenses">$0</div><div class="detail" id="expenseDetail">Recurring and one-time averaged</div></article>
      <article class="stat"><div class="label">Monthly Leftover</div><div class="value" id="monthlyLeftover">$0</div><div class="detail" id="leftoverDetail">After listed expenses</div></article>
      <article class="stat"><div class="label">Annual Projection</div><div class="value" id="annualLeftover">$0</div><div class="detail" id="annualDetail">Projected money left for the year</div></article>
    </section>

    <section class="layout">
      <div class="panel">
        <div class="panel-header"><h2>Income</h2></div>
        <div class="panel-body">
          <form class="form-grid" id="incomeForm">
            <div class="two-col">
              <label>Pay type<select id="incomeMode"><option value="hourly">Hourly</option><option value="fixed">Fixed paycheck</option></select></label>
              <label>Pay schedule<select id="paySchedule"><option value="weekly">Weekly</option><option value="biweekly">Bi-weekly</option><option value="monthly">Monthly</option></select></label>
            </div>
            <div class="two-col" id="hourlyFields">
              <label>Hourly rate<input id="hourlyRate" type="number" min="0" step="0.01" inputmode="decimal"></label>
              <label>Hours per check<input id="hoursPerCheck" type="number" min="0" step="0.25" inputmode="decimal"></label>
            </div>
            <div class="two-col hidden" id="fixedFields">
              <label>Gross pay per check<input id="grossPerCheck" type="number" min="0" step="0.01" inputmode="decimal"></label>
              <label>Other income per check<input id="otherIncome" type="number" min="0" step="0.01" inputmode="decimal"></label>
            </div>
            <div class="two-col">
              <label>Estimated tax and withholding %<input id="withholding" type="number" min="0" max="100" step="0.1" inputmode="decimal"></label>
              <label>Savings goal %<input id="savingsRate" type="number" min="0" max="100" step="0.1" inputmode="decimal"></label>
            </div>
          </form>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header"><h2>Add Expense</h2></div>
        <div class="panel-body">
          <form class="form-grid" id="expenseForm">
            <label>Expense name<input id="expenseName" maxlength="60" autocomplete="off" required></label>
            <div class="two-col">
              <label>Amount<input id="expenseAmount" type="number" min="0" step="0.01" inputmode="decimal" required></label>
              <label>Frequency<select id="expenseFrequency"><option value="weekly">Weekly</option><option value="biweekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="annual">Annual</option><option value="once">One-time</option></select></label>
            </div>
            <div class="two-col">
              <label>Type<select id="expenseType"><option value="recurring">Recurring</option><option value="subscription">Subscription</option><option value="one-time">One-time</option></select></label>
              <label>Category<select id="expenseCategory"><option value="Food">Food</option><option value="Car">Car</option><option value="Gas">Gas</option><option value="Insurance">Insurance</option><option value="Phone">Phone</option><option value="Savings">Savings</option><option value="Entertainment">Entertainment</option><option value="Other">Other</option></select></label>
            </div>
            <div class="toolbar">
              <button class="button primary" id="expenseSubmit" type="submit">Add Expense</button>
              <button class="button hidden" id="cancelEdit" type="button">Cancel Edit</button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <section class="insights" aria-label="Budget insights">
      <div class="insight" id="foodInsight"></div>
      <div class="insight" id="carInsight"></div>
      <div class="insight" id="subscriptionInsight"></div>
    </section>

    <section class="panel">
      <div class="panel-header"><h2>Expenses</h2></div>
      <div class="table-wrap" id="tableWrap">
        <table>
          <thead><tr><th>Name</th><th>Category</th><th>Type</th><th>Frequency</th><th class="money">Amount</th><th class="money">Monthly</th><th class="money">Annual</th><th class="money">Actions</th></tr></thead>
          <tbody id="expenseRows"></tbody>
        </table>
      </div>
      <div class="empty hidden" id="emptyState">Add income and expenses to start building a budget.</div>
    </section>

    <p class="footer-note">Revision <?= APP_REVISION ?>. Data saves to this user's JSON file on the server.</p>
  </main>

  <script>
    const currency = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 });
    const preciseCurrency = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 2 });
    let state = { income: {}, expenses: [] };
    let editingExpenseId = null;
    let saveTimer = null;

    const defaultState = {
      income: { mode: "hourly", schedule: "biweekly", hourlyRate: 15, hoursPerCheck: 36, grossPerCheck: 540, otherIncome: 0, withholding: 12, savingsRate: 10 },
      expenses: [
        { id: crypto.randomUUID(), name: "Lunch and snacks", amount: 75, frequency: "weekly", type: "recurring", category: "Food" },
        { id: crypto.randomUUID(), name: "Gas", amount: 45, frequency: "weekly", type: "recurring", category: "Gas" },
        { id: crypto.randomUUID(), name: "Car insurance", amount: 165, frequency: "monthly", type: "recurring", category: "Insurance" },
        { id: crypto.randomUUID(), name: "Phone", amount: 35, frequency: "monthly", type: "recurring", category: "Phone" },
        { id: crypto.randomUUID(), name: "Music streaming", amount: 10.99, frequency: "monthly", type: "subscription", category: "Entertainment" },
        { id: crypto.randomUUID(), name: "Car maintenance fund", amount: 50, frequency: "monthly", type: "recurring", category: "Car" }
      ]
    };

    const fields = {
      incomeMode: document.querySelector("#incomeMode"),
      paySchedule: document.querySelector("#paySchedule"),
      hourlyRate: document.querySelector("#hourlyRate"),
      hoursPerCheck: document.querySelector("#hoursPerCheck"),
      grossPerCheck: document.querySelector("#grossPerCheck"),
      otherIncome: document.querySelector("#otherIncome"),
      withholding: document.querySelector("#withholding"),
      savingsRate: document.querySelector("#savingsRate"),
      expenseName: document.querySelector("#expenseName"),
      expenseAmount: document.querySelector("#expenseAmount"),
      expenseFrequency: document.querySelector("#expenseFrequency"),
      expenseType: document.querySelector("#expenseType"),
      expenseCategory: document.querySelector("#expenseCategory")
    };

    function toNumber(value) {
      const number = Number(value);
      return Number.isFinite(number) ? number : 0;
    }

    function paychecksPerYear(schedule) {
      return { weekly: 52, biweekly: 26, monthly: 12 }[schedule] || 26;
    }

    function annualize(amount, frequency) {
      return amount * ({ weekly: 52, biweekly: 26, monthly: 12, annual: 1, once: 1 }[frequency] || 12);
    }

    function monthlyAmount(amount, frequency) {
      return annualize(amount, frequency) / 12;
    }

    function getIncome() {
      const income = state.income;
      const gross = income.mode === "hourly" ? toNumber(income.hourlyRate) * toNumber(income.hoursPerCheck) : toNumber(income.grossPerCheck);
      const grossPerCheck = gross + toNumber(income.otherIncome);
      const netPerCheck = grossPerCheck * (1 - toNumber(income.withholding) / 100);
      const checks = paychecksPerYear(income.schedule);
      const annual = netPerCheck * checks;
      const monthly = annual / 12;
      const savingsGoal = monthly * toNumber(income.savingsRate) / 100;
      return { netPerCheck, checks, annual, monthly, savingsGoal };
    }

    function getExpenseTotals() {
      return state.expenses.reduce((totals, expense) => {
        const annual = annualize(toNumber(expense.amount), expense.frequency);
        const monthly = annual / 12;
        totals.monthly += monthly;
        totals.annual += annual;
        if (expense.category === "Food") totals.foodMonthly += monthly;
        if (["Car", "Gas", "Insurance"].includes(expense.category)) totals.carMonthly += monthly;
        if (expense.type === "subscription") totals.subscriptionMonthly += monthly;
        return totals;
      }, { monthly: 0, annual: 0, foodMonthly: 0, carMonthly: 0, subscriptionMonthly: 0 });
    }

    function setStatus(message) {
      document.querySelector("#saveStatus").textContent = message;
    }

    async function loadBudget() {
      const response = await fetch("?api=budget", { credentials: "same-origin" });
      const payload = await response.json();
      if (!payload.ok) throw new Error(payload.error || "Unable to load budget.");
      state = {
        income: { ...defaultState.income, ...payload.budget.income },
        expenses: Array.isArray(payload.budget.expenses) ? payload.budget.expenses : []
      };
      setStatus(`Signed in as ${payload.username}`);
      render();
    }

    function queueSave() {
      setStatus("Saving...");
      clearTimeout(saveTimer);
      saveTimer = setTimeout(saveBudget, 350);
    }

    async function saveBudget() {
      const response = await fetch("?api=budget", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(state)
      });
      const payload = await response.json();
      setStatus(payload.ok ? `Saved ${new Date().toLocaleTimeString()}` : "Save failed");
    }

    function syncIncomeFields() {
      fields.incomeMode.value = state.income.mode;
      fields.paySchedule.value = state.income.schedule;
      fields.hourlyRate.value = state.income.hourlyRate;
      fields.hoursPerCheck.value = state.income.hoursPerCheck;
      fields.grossPerCheck.value = state.income.grossPerCheck;
      fields.otherIncome.value = state.income.otherIncome;
      fields.withholding.value = state.income.withholding;
      fields.savingsRate.value = state.income.savingsRate;
      document.querySelector("#hourlyFields").classList.toggle("hidden", state.income.mode !== "hourly");
      document.querySelector("#fixedFields").classList.toggle("hidden", state.income.mode !== "fixed");
    }

    function renderSummary() {
      const income = getIncome();
      const expenses = getExpenseTotals();
      const monthlyLeftover = income.monthly - expenses.monthly;
      const annualLeftover = income.annual - expenses.annual;
      document.querySelector("#monthlyIncome").textContent = currency.format(income.monthly);
      document.querySelector("#incomeDetail").textContent = `${income.checks} paychecks per year, ${preciseCurrency.format(income.netPerCheck)} net per check`;
      document.querySelector("#monthlyExpenses").textContent = currency.format(expenses.monthly);
      document.querySelector("#expenseDetail").textContent = `${currency.format(expenses.annual)} projected annually`;
      document.querySelector("#monthlyLeftover").textContent = currency.format(monthlyLeftover);
      document.querySelector("#leftoverDetail").textContent = `${currency.format(income.savingsGoal)} monthly savings target`;
      document.querySelector("#annualLeftover").textContent = currency.format(annualLeftover);
      document.querySelector("#annualDetail").textContent = `${monthlyLeftover >= income.savingsGoal ? "On pace" : "Below"} savings target after expenses`;
      document.querySelector("#monthlyLeftover").style.color = monthlyLeftover >= 0 ? "var(--good)" : "var(--warn)";
      document.querySelector("#annualLeftover").style.color = annualLeftover >= 0 ? "var(--good)" : "var(--warn)";
    }

    function renderInsights() {
      const income = getIncome();
      const totals = getExpenseTotals();
      const foodWeekly = totals.foodMonthly * 12 / 52;
      const carShare = income.monthly > 0 ? totals.carMonthly / income.monthly * 100 : 0;
      const subscriptionsAnnual = totals.subscriptionMonthly * 12;
      const food = document.querySelector("#foodInsight");
      food.className = `insight ${foodWeekly > 85 ? "warning" : "good"}`;
      food.innerHTML = `<strong>${currency.format(foodWeekly)} / week on food</strong><span>Cutting food spending by $15 per week would free up ${currency.format(15 * 52)} per year for car costs or savings.</span>`;
      const car = document.querySelector("#carInsight");
      car.className = `insight ${carShare > 35 ? "warning" : "good"}`;
      car.innerHTML = `<strong>${currency.format(totals.carMonthly)} / month for car needs</strong><span>Car, gas, and insurance currently use ${Math.round(carShare)}% of projected monthly income.</span>`;
      const subscriptions = document.querySelector("#subscriptionInsight");
      subscriptions.className = `insight ${subscriptionsAnnual > 600 ? "warning" : "good"}`;
      subscriptions.innerHTML = `<strong>${currency.format(subscriptionsAnnual)} / year in subscriptions</strong><span>Monthly subscriptions compete with registration, repairs, and emergency savings.</span>`;
    }

    function renderExpenses() {
      const rows = document.querySelector("#expenseRows");
      rows.innerHTML = "";
      document.querySelector("#tableWrap").classList.toggle("hidden", state.expenses.length === 0);
      document.querySelector("#emptyState").classList.toggle("hidden", state.expenses.length > 0);
      state.expenses.forEach((expense) => {
        const amount = toNumber(expense.amount);
        const row = document.createElement("tr");
        row.innerHTML = `<td>${escapeHtml(expense.name)}</td><td><span class="tag">${escapeHtml(expense.category)}</span></td><td>${escapeHtml(expense.type)}</td><td>${formatFrequency(expense.frequency)}</td><td class="money">${preciseCurrency.format(amount)}</td><td class="money">${currency.format(monthlyAmount(amount, expense.frequency))}</td><td class="money">${currency.format(annualize(amount, expense.frequency))}</td><td class="money"><div class="row-actions"><button class="icon-button" type="button" data-action="edit" data-id="${expense.id}">Edit</button><button class="icon-button" type="button" data-action="delete" data-id="${expense.id}">Del</button></div></td>`;
        rows.appendChild(row);
      });
    }

    function render() {
      syncIncomeFields();
      renderSummary();
      renderInsights();
      renderExpenses();
      document.querySelector("#cancelEdit").classList.toggle("hidden", !editingExpenseId);
      document.querySelector("#expenseSubmit").textContent = editingExpenseId ? "Save Expense" : "Add Expense";
    }

    function formatFrequency(frequency) {
      return { weekly: "Weekly", biweekly: "Bi-weekly", monthly: "Monthly", annual: "Annual", once: "One-time" }[frequency] || frequency;
    }

    function escapeHtml(value) {
      return String(value).replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    }

    function updateIncomeFromFields() {
      state.income = {
        mode: fields.incomeMode.value,
        schedule: fields.paySchedule.value,
        hourlyRate: toNumber(fields.hourlyRate.value),
        hoursPerCheck: toNumber(fields.hoursPerCheck.value),
        grossPerCheck: toNumber(fields.grossPerCheck.value),
        otherIncome: toNumber(fields.otherIncome.value),
        withholding: toNumber(fields.withholding.value),
        savingsRate: toNumber(fields.savingsRate.value)
      };
      render();
      queueSave();
    }

    function clearExpenseForm() {
      editingExpenseId = null;
      fields.expenseName.value = "";
      fields.expenseAmount.value = "";
      fields.expenseFrequency.value = "weekly";
      fields.expenseType.value = "recurring";
      fields.expenseCategory.value = "Food";
      render();
    }

    function handleExpenseSubmit(event) {
      event.preventDefault();
      const expense = {
        id: editingExpenseId || crypto.randomUUID(),
        name: fields.expenseName.value.trim(),
        amount: toNumber(fields.expenseAmount.value),
        frequency: fields.expenseFrequency.value,
        type: fields.expenseFrequency.value === "once" ? "one-time" : fields.expenseType.value,
        category: fields.expenseCategory.value
      };
      if (!expense.name || expense.amount <= 0) return;
      state.expenses = editingExpenseId ? state.expenses.map((item) => item.id === editingExpenseId ? expense : item) : [...state.expenses, expense];
      queueSave();
      clearExpenseForm();
    }

    function editExpense(id) {
      const expense = state.expenses.find((item) => item.id === id);
      if (!expense) return;
      editingExpenseId = id;
      fields.expenseName.value = expense.name;
      fields.expenseAmount.value = expense.amount;
      fields.expenseFrequency.value = expense.frequency;
      fields.expenseType.value = expense.type;
      fields.expenseCategory.value = expense.category;
      render();
      fields.expenseName.focus();
    }

    function deleteExpense(id) {
      state.expenses = state.expenses.filter((item) => item.id !== id);
      if (editingExpenseId === id) editingExpenseId = null;
      render();
      queueSave();
    }

    function exportBudget() {
      const blob = new Blob([JSON.stringify(state, null, 2)], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `budget-tracker-${new Date().toISOString().slice(0, 10)}.json`;
      link.click();
      URL.revokeObjectURL(url);
    }

    function importBudget(file) {
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const imported = JSON.parse(reader.result);
          state = { income: { ...defaultState.income, ...imported.income }, expenses: Array.isArray(imported.expenses) ? imported.expenses : [] };
          editingExpenseId = null;
          render();
          queueSave();
        } catch {
          alert("That file was not a valid budget export.");
        }
      };
      reader.readAsText(file);
    }

    Object.values(fields).forEach((field) => {
      if (field.id.startsWith("expense")) return;
      field.addEventListener("input", updateIncomeFromFields);
      field.addEventListener("change", updateIncomeFromFields);
    });
    document.querySelector("#expenseForm").addEventListener("submit", handleExpenseSubmit);
    document.querySelector("#cancelEdit").addEventListener("click", clearExpenseForm);
    document.querySelector("#expenseRows").addEventListener("click", (event) => {
      const button = event.target.closest("button[data-action]");
      if (!button) return;
      if (button.dataset.action === "edit") editExpense(button.dataset.id);
      if (button.dataset.action === "delete") deleteExpense(button.dataset.id);
    });
    document.querySelector("#resetButton").addEventListener("click", () => {
      if (!confirm("Reset this user's budget to the starter example?")) return;
      state = structuredClone(defaultState);
      queueSave();
      clearExpenseForm();
    });
    document.querySelector("#exportButton").addEventListener("click", exportBudget);
    document.querySelector("#importFile").addEventListener("change", (event) => {
      importBudget(event.target.files[0]);
      event.target.value = "";
    });
    fields.expenseFrequency.addEventListener("change", () => {
      if (fields.expenseFrequency.value === "once") fields.expenseType.value = "one-time";
    });

    loadBudget().catch((error) => setStatus(error.message));
  </script>
<?php endif; ?>
</body>
</html>
