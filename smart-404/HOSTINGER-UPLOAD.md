# Hostinger Upload Reminder

Manually upload the smart-404 files from this GitHub repo to the Hostinger site after local changes are committed.

Upload code files from this folder to the matching web-root locations on Hostinger.

Do not overwrite live server data files with sample files. Use the sample files only as references when creating missing live files.

Live files that should stay untracked:

- `data/404-requests.jsonl`
- `data/smart-404-admin-password.php`
- `data/smart-404-map.json`
- `data/smart-404-malicious.json`

Sample files committed for reference:

- `data/404-requests.sample.jsonl`
- `data/smart-404-admin-password.sample.php`
- `data/smart-404-map.sample.json`
- `data/smart-404-malicious.sample.json`
