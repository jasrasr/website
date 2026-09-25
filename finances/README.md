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
- Supports multiple configured users through `config.private.php`.
- Stores each user's budget in a separate generated JSON file.
- Tracks hourly or fixed-paycheck income.
- Tracks expenses by amount, frequency, type, and category.
- Calculates monthly income, monthly expenses, leftover money, and annual projection.
- Highlights food, car, and subscription spending.
- Supports editing, deleting, resetting, importing, and exporting budget data.

## Setup

1. Copy `config.sample.php` to `config.private.php`.
2. Replace the sample passwords in `config.private.php`.
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

- `config.private.php`
- `data/*.json`

Confirm `config.private.php` is ignored before publishing or committing. It contains the local user credentials for this installation and should stay out of Git.

`data/.htaccess` denies direct web reads of saved budget JSON on Apache-compatible hosting.

## Security Notes

- Replace the sample `budget123` password before making the page public.
- Prefer `password_hash` entries in `config.private.php` over plain passwords when practical.
- Keep generated budget JSON and local credentials out of version control.
- `security.md` is a local security review log and is ignored by the current `.gitignore`.

## Files

- `index.php` is the full PHP, HTML, CSS, and JavaScript app.
- `config.sample.php` shows the expected local user/password configuration.
- `config.private.php` is the untracked local credential file used by the deployed copy.
- `.gitignore` keeps generated user budget JSON and the local security review log out of Git.
- `data/.htaccess` blocks direct browser access to generated JSON files on Apache-compatible hosting.
- `security.md` records local security review notes and is not intended for the public repo.

## Shared user management (revision 1.3)

Finances supports an opt-in `authentication` setting: `legacy` (default) or `shared`. Follow the [account-linking rollout](../user-management/ACCOUNT-LINKING.md) before choosing shared mode. Keep your existing `users` configuration and budget files. A central ID maps to its verified old account; no filenames are renamed and no data is imported over the top of existing data.

An administrator previews and applies links in User Management. Each link saves a private backup first. The central user needs `finances` project access; `viewer` can read/export, `member` or project `admin` can also save. Site administrators do not automatically inherit anyone's private budget. Unlinked or invalid accounts fail closed instead of generating starter budgets. Shared mode rejects legacy sessions and local password login.

`config.private.php`, JSON budget files, stable lock files, and `data/identity/` mappings/backups are ignored by Git. Keep `data/.htaccess` in place and confirm its nested-file denial on your host. Do not deploy with ignored-file cleanup.

Both login modes now use CSRF checks for login/logout and budget saves. Sign out uses POST, and API writes send `X-CSRF-Token`. Existing budget JSON is validated and writes use stable locking plus atomic replacement; corrupt data produces an error instead of silently falling back to starter data.

Rollback the login switch by setting `authentication` back to `legacy`; the original account reads the latest budget, including shared-mode edits. Do not overwrite the latest budget with its pre-link backup just to roll back authentication.

The historical `finances/config.local.php` is already tracked in Git. This migration intentionally does not delete it, because a deployment deletion could remove live account configuration. `config.private.php` is a new ignored override loaded last; copy your existing settings there before switching modes. Never commit real credentials. Adding the old filename to `.gitignore` does not untrack its existing copy.
