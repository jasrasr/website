# Hostinger Deployment Reminder

After changes are committed and synced to Hostinger's `/github` checkout, manually move the smart-404 files from `/github` to the site's web root.

Do not upload from the local computer directly to Hostinger for this workflow. Use the files already synced on Hostinger under `/github`.

Move code files from the Hostinger `/github/smart-404/` folder to the matching web-root locations. The YOURLS plugin is under `/github/smart-404/yourls-plugin/user/plugins/smart-404-jasrasr/` and should be moved into the YOURLS install's `user/plugins/` folder.

Do not overwrite live server data files with sample files. Use the sample files only as references when creating missing live files.

Live files that should stay untracked:

- `smart-404-data/404-requests.jsonl`
- `smart-404-data/smart-404-admin-password.php`
- `smart-404-data/smart-404-map.json`
- `smart-404-data/smart-404-malicious.json`

Sample files committed for reference:

- `smart-404-data/404-requests.sample.jsonl`
- `smart-404-data/smart-404-admin-password.sample.php`
- `smart-404-data/smart-404-map.sample.json`
- `smart-404-data/smart-404-malicious.sample.json`
