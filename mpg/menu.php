<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$plate = isset($_GET['plate']) ? htmlspecialchars($_GET['plate']) : null;
?>

<style>
    .menu-bar {
        font-family: sans-serif;
        font-size: 14px;
        position: absolute;
        top: 10px;
        right: 20px;
        background: #f9f9f9;
        border: 1px solid #ccc;
        padding: 8px 12px;
        border-radius: 6px;
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .menu-bar a {
        text-decoration: none;
        color: #333;
    }

    .menu-bar a:hover {
        color: #007acc;
        text-decoration: underline;
    }

    .menu-bar .icon {
        margin-right: 3px;
    }
</style>

<div class="menu-bar">
    <span title="Menu Navigation">📂 MENU</span>
    <a href="index.php" title="Go to Home"><span class="icon">🏠</span>Home</a>

    <?php if ($plate): ?>
        <a href="view_chart.php?plate=<?= $plate ?>" title="View Chart for Plate <?= $plate ?>"><span class="icon">📈</span>Chart</a>
        <a href="view_latest.php?plate=<?= $plate ?>" title="Most Recent Entry"><span class="icon">🕒</span>Latest</a>
        <a href="logs/<?= $plate ?>.json" title="Raw JSON Data"><span class="icon">🗂️</span>JSON</a>
        <a href="export_csv.php?plate=<?= $plate ?>" title="Export CSV"><span class="icon">📤</span>CSV</a>
    <?php endif; ?>

    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
        <a href="admin.php" title="Admin Dashboard"><span class="icon">🛠️</span>Admin</a>
        <a href="logout.php" title="Log out of session"><span class="icon">🚪</span>Logout</a>
    <?php endif; ?>
</div>
