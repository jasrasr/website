# Documentation-only placeholders

These files demonstrate JSON shapes. The application never loads this folder.

- `directory.example.json`: empty central storage shape, without credentials or seeded users.
- `budget.example.json`: empty Finances budget shape.
- `../config.example.php`: central configuration template.
- `../../finances/config.private.example.php`: private Finances override template.

**Never copy an empty example over a live file.** Initialize a new central directory through `setup.php`; link existing budgets in place. Do not initialize replacement budgets during migration. Runtime folders are retained in Git by their `.htaccess` protection files, not by empty live JSON files.
