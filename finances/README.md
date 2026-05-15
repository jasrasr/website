<!--
File: README.md
Purpose: Documents setup and usage for the finances budget tracker.
-->

# Budget Tracker

Simple PHP budget tracker for shared Hostinger hosting. Each login writes to its own JSON file under `data/`.

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

`data/.htaccess` denies direct web reads of saved budget JSON on Apache-compatible hosting.
