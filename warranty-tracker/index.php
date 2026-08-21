<?php
declare(strict_types=1);
require_once __DIR__ . '/../1-Framework/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Warranty Tracker</title><link rel="stylesheet" href="assets/css/app.css?v=1.2.0"><link rel="stylesheet" href="assets/css/account.css?v=1.2.0">
</head>
<body>
<header><div><p class="eyebrow">HOME INVENTORY</p><h1>Warranty Tracker</h1><p>Know what is covered before the warranty quietly escapes.</p></div><div class="account"><span id="accountName"></span><button id="inviteButton" hidden>Create invite</button><button id="logoutButton">Sign out</button><button id="addButton" class="primary">+ Add warranty</button></div></header>
<main>
  <section class="stats" aria-label="Warranty summary">
    <article><span>Active</span><strong id="activeCount">0</strong></article><article><span>Expiring in 90 days</span><strong id="expiringCount">0</strong></article><article><span>Expired</span><strong id="expiredCount">0</strong></article><article><span>Tracked value</span><strong id="valueTotal">$0</strong></article>
  </section>
  <section class="toolbar"><input id="search" type="search" placeholder="Search product, seller, provider…"><select id="statusFilter"><option value="all">All warranties</option><option value="active">Active</option><option value="expiring">Expiring soon</option><option value="expired">Expired</option></select><select id="sort"><option value="endAsc">Expiration: soonest</option><option value="endDesc">Expiration: latest</option><option value="product">Product name</option><option value="costDesc">Item cost: highest</option></select></section>
  <div id="message" role="status"></div><section id="warrantyGrid" class="grid"></section>
</main>
<dialog id="editor"><form id="warrantyForm"><div class="dialog-head"><div><p class="eyebrow">WARRANTY RECORD</p><h2 id="formTitle">Add warranty</h2></div><button type="button" class="icon" id="closeButton" aria-label="Close">×</button></div><input type="hidden" name="id">
  <div class="fields"><label class="wide">Product name*<input name="product" required maxlength="120"></label><label>Visibility<select name="scope"><option value="personal">Only me</option><option value="shared">Shared household</option></select></label><label>Category<input name="category" maxlength="60"></label><label>Item cost<input name="itemCost" type="number" min="0" step="0.01"></label><label>Warranty cost<input name="warrantyCost" type="number" min="0" step="0.01"></label><label>Manufacturer<input name="manufacturer" maxlength="80"></label><label>Model<input name="model" maxlength="80"></label><label>Serial number<input name="serialNumber" maxlength="100"></label><label>Seller<input name="seller" maxlength="100"></label><label>Warranty provider<input name="provider" maxlength="100"></label><label>Purchase date*<input name="purchaseDate" type="date" required></label><label>Warranty end date*<input name="warrantyEndDate" type="date" required></label><label class="wide">Receipt or product URL<input name="receiptUrl" type="url" maxlength="500"></label><label class="wide">Notes<textarea name="notes" rows="3" maxlength="1000"></textarea></label></div>
  <div id="formErrors" class="errors"></div><div class="actions"><button type="button" id="cancelButton">Cancel</button><button class="primary" type="submit">Save warranty</button></div></form></dialog>
<dialog id="accountDialog"><form id="accountForm"><p class="eyebrow">HOUSEHOLD ACCESS</p><h2 id="accountTitle">Sign in</h2><div class="fields"><label id="nameField" class="wide" hidden>Name<input name="name" maxlength="80"></label><label class="wide">Email<input name="email" type="email" required autocomplete="email"></label><label class="wide">Password<input name="password" type="password" required minlength="12" autocomplete="current-password"></label><label id="inviteField" class="wide" hidden>Invite code<input name="inviteCode" maxlength="20"></label></div><div id="accountError" class="errors"></div><div class="actions"><button id="switchAccountMode" type="button">Create account</button><button class="primary" type="submit">Continue</button></div></form></dialog>
<dialog id="inviteDialog"><form method="dialog"><div class="dialog-head"><div><p class="eyebrow">HOUSEHOLD INVITE</p><h2>Invite code</h2></div><button class="icon" aria-label="Close">×</button></div><p>Share this one-time code. It expires in seven days.</p><output id="inviteCode" class="invite-code"></output><div class="actions"><button>Done</button></div></form></dialog>
<script src="assets/js/app.js?v=1.2.0"></script></body></html>
