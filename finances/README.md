<!--
File: README.md
Purpose: Documents setup and usage for the finances budget tracker.
Revision: 1.1
Revision Log:
- 2026-05-15: Added revision metadata to the file header.
-->

# Budget Tracker

Live page: [jasr.me/github/finances](https://jasr.me/github/finances)

Simple PHP budget tracker for shared Hostinger hosting. The project is meant to provide a private web page where separate users, such as a student and parent, can sign in and maintain their own budget data without needing a database.

Each login writes to its own JSON file under `data/`. The app helps model paycheck income, taxes or withholding, savings goals, recurring expenses, subscriptions, food spending, gas, insurance, car costs, and one-time costs. It then rolls those entries into monthly and annual projections.

## What This App Does

- Provides a password-protected budget page from `index.php`.
- Supports multiple configured users through `config.local.php`.
- Stores each user's budget in a separate generated JSON file.
- Tracks hourly or fixed-paycheck income.
- Tracks expenses by amount, frequency, type, and category.
- Calculates monthly income, monthly expenses, leftover money, and annual projection.
- Highlights food, car, and subscription spending.
- Supports editing, deleting, resetting, importing, and exporting budget data.

## Setup

1. Copy `config.sample.php` to `config.local.php`.
2. Replace the sample passwords in `config.local.php`.
3. Upload this folder to Hostinger with PHP enabled.

The sample password is only for setup reference. Do not use it for a public page.

Plain passwords work for simple setup:

```php
<?php

return [
    'users' => [
        'student' => ['password' => 'new-student-password'],
        'parent' => ['password' => 'new-parent-password'],
    ],
];
```

Hashed passwords also work. To create a password hash from a terminal with PHP available:

```powershell
php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
```

Then use:

```php
<?php

return [
    'users' => [
        'student' => ['password_hash' => 'paste-hash-here'],
    ],
];
```

Runtime files are intentionally not committed:

- `config.local.php`
- `data/*.json`

Confirm `config.local.php` is ignored before publishing or committing. It contains the local user credentials for this installation and should stay out of Git.

`data/.htaccess` denies direct web reads of saved budget JSON on Apache-compatible hosting.

## Security Notes

- Replace the sample `budget123` password before making the page public.
- Prefer `password_hash` entries in `config.local.php` over plain passwords when practical.
- Keep generated budget JSON and local credentials out of version control.
- `security.md` is a local security review log and is ignored by the current `.gitignore`.

## Files

- `index.php` is the full PHP, HTML, CSS, and JavaScript app.
- `config.sample.php` shows the expected local user/password configuration.
- `config.local.php` is the untracked local credential file used by the deployed copy.
- `.gitignore` keeps generated user budget JSON and the local security review log out of Git.
- `data/.htaccess` blocks direct browser access to generated JSON files on Apache-compatible hosting.
- `security.md` records local security review notes and is not intended for the public repo.
