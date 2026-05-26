# 📁 PHP Directory Browser (Master Architecture)

A centralized, path-aware PHP file browser powered by a single master renderer.

Update one file. Every folder updates automatically.

---

## 🚀 Features

- 📂 Subdirectory support
- ⬆ Safe “Up” navigation
- ⭐ Path-based favorites (files + folders)
- 🧼 Automatic JSON normalization
- 🔤 Auto-sorted favorites
- 📦 Folders first, newest first sorting
- 💾 Download buttons
- 📱 Mobile-friendly layout
- 🧠 No database required

---

## 🏗 Architecture Overview

Uses a single master renderer:

```
public_html/
│
├── some-folder/
│   └── directory.php
│
├── another-folder/
│   └── directory.php
│
└── custom-directory/
    ├── master-directory.php
    ├── toggle_favorite.php
    └── favorites.json
```


Each directory.php is a thin wrapper:

```
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/custom-directory/master-directory.php';
?>
```
---

## ⭐ Favorites System (Path-Based)

Favorites are stored using full relative paths:
```
{
    "favorites": [
        "/test.png",
        "/weather",
        "/tools/vlc-3.0.20-win32.exe"
    ]
}
```
Improvements:

- All favorites use full relative paths (no ambiguity)
- Directories and files both supported
- Automatic migration from legacy name-only entries
- JSON stored using:
  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
- Favorites auto-sorted before saving

Cleaner JSON example:
```
{
    "favorites": [
        "/sample-robots.txt",
        "/test.jpg",
        "/test.png",
        "/tools/7z2403-x64.exe",
        "/tools/vlc-3.0.20-win32.exe",
        "/vlc-3.0.20-win32.exe",
        "/weather"
    ]
}
```
---

## 🔐 Security

- Uses realpath() for safe resolution
- Prevents directory traversal
- Restricts navigation to DOCUMENT_ROOT
- Validates favorite paths before storing

---

## 🕒 Revision Tracking

Footer shows:

- Centralized APP_REVISION
- True modification timestamp of master-directory.php
- Displayed in Eastern Time (America/New_York)

---

## ⚙ Requirements

- PHP 8.0+
- Apache recommended
- Writable custom-directory/ folder

Optional .htaccess:

DirectoryIndex directory.php

---

## 📌 Current Revision

- master-directory.php → 3.1
- toggle_favorite.php → 2.0
- directory.php wrapper → 1.0

---

## 👨‍💻 Author

Jason Lamb

---

## 📜 License

MIT