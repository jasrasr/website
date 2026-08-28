# Construction Sign Text Generator

A client-side construction message-board generator that converts entered text into an amber LED-style highway sign preview.

## Features

- Live text preview using a custom 5x7 LED dot-matrix renderer
- Supports one to four lines
- Manual line breaks or automatic wrapping
- Adjustable LED color, bulb size, bulb spacing, glow, alignment, and frame
- Copies self-contained embed code for another website
- Copies a complete standalone HTML document
- Downloads the sign preview as a PNG image
- No server-side processing and no stored user data

## Files

- `index.html` — page structure
- `style.css` — responsive layout and sign styling
- `script.js` — LED font renderer, preview logic, export, and clipboard functions

## Installation

Upload all files in the `construction-sign` folder to the same web directory. Open `index.html` or browse to the folder URL.

## Browser compatibility

Designed for current versions of Chrome, Edge, Firefox, and Safari. Clipboard copying generally requires HTTPS or localhost. A fallback copy method is included.

## Revision

- Revision: 1.0.0
- Updated: July 30, 2026
