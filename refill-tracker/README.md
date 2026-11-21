# Refill Tracker

Track unopened spares for household supplies and see which items need restocking. An item goes on the Restock list when its quantity is at or below its threshold. Use a threshold of zero to list it only when the last spare is gone.

## Deployment

1. Deploy this app alongside `1-Framework` and `user-management` on the same HTTPS host.
2. Register the `refill-tracker` project at its deployed path in User Management. Grant Member access to people who need to add or change their inventory, or Viewer access to read their own inventory.
3. Create a dedicated private directory outside the document root, writable by the PHP account with permissions restricted to that account. Copy `config.local.example.php` to the ignored `config.local.php`, and set `storage_path` to that directory's absolute path. Keep the directory in server backups.
4. Open the deployed project path and sign in with User Management.

Each account gets one framework JSON file keyed by the hash of its stable User Management ID. The framework creates the file on the first saved change and locks each read-modify-write transaction. The app derives the Restock list on each request.

## Manual verification

- Add toothpaste with one spare and a threshold of one. Verify it appears under Restock. Add a spare and verify it clears. Use two spares and confirm it returns at zero and the minus button becomes disabled.
- Edit the unit and threshold and verify that the spare count is preserved. Refresh after a successful change and verify it is applied only once.
- Open the app with a different Member account and confirm its list starts empty. Verify a Viewer sees no editing controls and a signed-in Member submitting an invalid form token receives HTTP 403.
- Point the configuration to a directory inside the document root and verify the app reports it is unavailable. Restore the external private path before use.
