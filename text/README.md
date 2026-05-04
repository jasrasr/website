# Shared Text

Small PHP page for editing text on one computer and retrieving it from another.

## Usage

Open `/text/`, type or paste text, enter the save password, then click **Save Shared Text**. Open `/text/` from another computer and click **Load Latest** or copy the loaded text.

## Passwords

Saving text requires a password. Configure it with either:

- Environment variable: `TEXT_COPY_SAVE_PASSWORD`
- Local untracked file: `text/data/save-password.txt`

Viewing the raw JSON file through the page also requires a password. Configure it with either:

- Environment variable: `TEXT_COPY_RAW_JSON_PASSWORD`
- Local untracked file: `text/data/raw-json-password.txt`

## Storage

The page stores text on the web server in `text/data/text-copy.json`. The `data` folder and JSON file are created automatically when the page runs.

The app includes `text/data/.htaccess` to block direct browser access to files in `text/data/` on Apache-compatible hosts. The JSON and password files are ignored by Git.
