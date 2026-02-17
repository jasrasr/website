# 📁 PHP Directory Browser (with Shared Favorites)

A lightweight, self-contained PHP file browser that:

-   Displays files and subdirectories
-   Supports centralized shared favorites (⭐)
-   Provides safe "Up" navigation
-   Sorts folders first, newest first
-   Works from any folder
-   Requires no database
-   Designed for Apache + PHP hosting

------------------------------------------------------------------------

## 🚀 Features

### 📂 Subdirectory Support

-   Folders are displayed alongside files
-   Folders always appear first
-   Click into folders naturally via links

### ⬆ Safe Up Navigation

-   "Up" button appears when not at site root
-   Prevents directory traversal outside DOCUMENT_ROOT
-   Uses realpath() for safe resolution

### ⭐ Centralized Favorites

Favorites stored in:

/custom-directory/favorites.json

Shared across all instances of directory.php. JSON auto-creates if
missing.

------------------------------------------------------------------------

## 📂 Folder Structure

public_html/ │ ├── some-folder/ │ └── directory.php │ ├──
another-folder/ │ └── directory.php │ └── custom-directory/ ├──
favorites.json └── toggle_favorite.php

------------------------------------------------------------------------

## 🛠 Installation

1)  Upload directory.php anywhere you want browsing.
2)  Create /custom-directory/ in your web root.
3)  Place toggle_favorite.php inside that folder.
4)  Ensure the folder is writable (755 recommended).

Optional .htaccess:

DirectoryIndex directory.php

------------------------------------------------------------------------

## 🔐 Security Notes

-   Navigation restricted to DOCUMENT_ROOT
-   No directory traversal allowed
-   No database required
-   Designed for controlled hosting environments

------------------------------------------------------------------------

## 📌 Current Revision

directory.php → Revision 2.7

------------------------------------------------------------------------

## 👨‍💻 Author

Jason Lamb

------------------------------------------------------------------------

## 📜 License

MIT
