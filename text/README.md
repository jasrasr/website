# Shared Text

Small PHP page for editing text on one computer and retrieving it from another.

## Usage

Open `/text/`, type or paste text, then click **Save Shared Text**. Open `/text/` from another computer and click **Load Latest** or copy the loaded text.

## Storage

The page stores text on the web server in `text/data/text-copy.json`. The `data` folder and JSON file are created automatically when the page runs.

