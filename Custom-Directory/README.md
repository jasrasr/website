# 📁 PHP Directory Browser (with Shared Favorites)

A lightweight, self-contained PHP file browser that:

- Displays files and subdirectories
- Supports centralized shared favorites (⭐)
- Provides safe “Up” navigation
- Sorts folders first, newest first
- Works from any folder
- Requires no database
- Designed for Apache + PHP hosting (e.g., Hostinger)

---

## 🚀 Features

### 📂 Subdirectory Support
- Folders are displayed alongside files
- Folders always appear first
- Click into folders naturally via links

### ⬆ Safe Up Navigation
- “Up” button appears when not at site root
- Prevents directory traversal outside `DOCUMENT_ROOT`
- Uses `realpath()` for safe resolution

### ⭐ Centralized Favorites
- Favorites stored in:
  
/custom-directory/favorites.json


- Shared across all instances of `directory.php`
- No database required
- JSON auto-creates if missing

### 🔎 Sorting
- Default: Folders first, newest modified first
- Clickable column headers
- Stable sorting behavior

### 🎨 UI
- Clean, modern layout
- Mobile friendly
- Extension color badges
- File-type icons
- Download button for files only

---

## 📂 Folder Structure

public_html/
│
├── some-folder/
│ └── directory.php
│
├── another-folder/
│ └── directory.php
│
└── custom-directory/
├── favorites.json
└── toggle_favorite.php


You can place `directory.php` in **any folder**, and it will still use the same centralized favorites file.

---

## 🛠 Installation

### 1️⃣ Upload Files

Place:

- `directory.php` in any folder you want to browse
- `toggle_favorite.php` inside:

/custom-directory/


Create this folder if it does not exist.

---

### 2️⃣ Set Permissions

On most shared hosting:

- Folders → `755`
- Files → `644`

Ensure `/custom-directory/` is writable.

---

### 3️⃣ Optional: Make It Default Page

Create `.htaccess` in any folder containing `directory.php`:

DirectoryIndex directory.php


Or globally:

DirectoryIndex directory.php index.php index.html


---

## 🔐 Security Notes

- Navigation is restricted to `$_SERVER['DOCUMENT_ROOT']`
- No directory traversal allowed
- Uses `realpath()` validation
- Does not expose hidden dot directories (except `.well-known`)
- No execution of user input

This is not intended as a hardened enterprise file manager, but it is safe for typical shared hosting use.

---

## 🧠 How Favorites Work

When you click ⭐:

1. JavaScript sends POST to:
/custom-directory/toggle_favorite.php


2. PHP toggles entry inside:
favorites.json


3. JSON structure:
```json
{
    "favorites": [
        "file1.pdf",
        "notes.txt"
    ]
}
Favorites are shared across all directory instances.

🧩 Requirements
PHP 8.0+ (uses match)

Apache (recommended)

AllowOverride All if using .htaccess

📌 Current Revision
directory.php → Revision 2.7

Features added in 2.7:

Safe Up navigation

Root restriction

Centralized favorites

Subdirectory support

📈 Possible Future Enhancements
Breadcrumb navigation

Per-user favorites (session-based)

Authentication layer

Recursive browsing

File upload support

Search

Role-based access

Dark mode

👨‍💻 Author
Jason Lamb
Built as a lightweight alternative to heavy file managers.

📜 License
MIT (or choose your preferred license)


---

If you want, I can also generate:

- `LICENSE` (MIT pre-filled with your name)
- A release tag template (v1.0.0 checklist)
- A hardened production version
- Or a GitHub-style animated GIF demo section

You’re one small step away from open-sourcing your own minimal file management system.
