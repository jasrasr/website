
---

# 📘 `README.md`

```md
# 📦 QR Box Inventory System

A lightweight, database-free inventory system for tracking the contents of physical storage boxes using QR codes.

Each storage box has:
- A unique, immutable ID
- A printable QR code
- A mobile-friendly public page
- An editable item list
- A dynamic item count
- A last-updated timestamp

Scanning a QR code opens a public webpage showing the contents of that box.

---

## ✨ Features

- 📱 Mobile-friendly public box pages
- 🔗 Clean URLs (`/box/BOX123`)
- 🖨️ Printable QR label PDFs (multiple layouts)
- 🔐 Admin portal with authentication
- 🧾 JSON-based storage (no database)
- 🔄 QR codes auto-generated on box creation
- 🧹 QR cleanup on box deletion
- 👤 Per-user box ownership
- 🕒 Timestamps stored in Eastern Time (EST/EDT)

---

## 🧱 Tech Stack

- PHP 8.1+
- Apache (`mod_rewrite`)
- JSON file storage
- PHP QR Code library
- FPDF for PDF generation

No MySQL. No framework. No JavaScript build tooling.

---

## 📁 Folder Structure

/box
├── admin/ # Admin portal (auth required)
│ ├── index.php
│ ├── login.php
│ ├── create_box.php
│ ├── edit_box.php
│ ├── delete_box.php
│ ├── generate_qr.php
│ ├── generate_missing_qr.php
│ └── print_qr_pdf.php
│
├── data/ # JSON storage (web-protected)
│ ├── boxes.json
│ ├── users.json
│ └── .htaccess
│
├── lib/ # Shared libraries
│ ├── auth.php
│ ├── data.php
│ ├── qr.php
│ ├── qrlib.php
│ └── fpdf.php
│
├── qrcodes/ # Generated QR PNG files
│
├── box.php # Public box view
├── index.php # Pretty URL router
├── .htaccess
├── README.md
├── SECURITY.md
└── RESTORE.md

---

## 🔐 Authentication & Roles

- Users authenticate via the admin portal.
- Each user owns the boxes they create.
- Admin users can manage all boxes.
- Role is defined per user in `users.json`.

---

## 🗃️ Data Storage

### `boxes.json`
Stores:
- Box ID
- Box name
- Owner
- Item list
- Item count
- Last updated timestamp

### `users.json`
Stores:
- Username
- Password hash
- Role (`user` or `admin`)

Direct web access to JSON files is blocked.

---

## 🖨️ QR Codes & PDFs

- QR codes are created automatically when a box is created.
- QR codes point to clean URLs (`/box/BOX123`).
- Printable PDF layouts:
  - Small (16 per page)
  - Medium (6 per page)
  - Large (1 per page)
- Missing QR codes can be regenerated safely.

---

## 🧹 Lifecycle Rules

- Create box → QR created
- Rename box → QR unchanged
- Delete box → QR deleted
- Restore data → QR regenerable

All operations are idempotent and safe to repeat.

---

## 🔄 Backup & Restore

See `RESTORE.md` for full recovery instructions.

Minimum required backups:
- `/box/data/boxes.json`
- `/box/data/users.json`
- `/box/qrcodes/` (optional)

---

## 🧠 Design Philosophy

This project emphasizes:
- Simplicity over abstraction
- Explicit behavior over magic
- Recoverability over complexity
- Real-world physical workflows

---

## 🧑‍💻 Author

**Jason Lamb**  
(with help from AI)

---

## 📜 License

MIT (or your preferred license)
