# 🔐 Security Overview – QR Box Inventory System

This document describes the security model, protections, and intentional limitations of the QR Box Inventory System.

This project is designed for **personal or small trusted-team use**, not for hostile public environments.

---

## Authentication

- Admin access is protected using PHP sessions.
- All admin pages enforce authentication via `requireLogin()`.
- User credentials are stored in `data/users.json`.
- Passwords are hashed using PHP’s `password_hash()` and verified with `password_verify()`.

There are no plaintext passwords stored anywhere in the system.

---

## Authorization & Ownership

- Each box is associated with a single owner (username).
- Admin actions (edit, delete, generate QR, print PDF) are restricted to the owning user.
- Users cannot view or modify boxes owned by other users.

There is no role hierarchy or privilege escalation logic.

---

## Public vs Private Areas

### Public (No Login Required)
- Public box view pages (`/box/BOX123`)
- QR code PNG images
- Static assets

These pages are intentionally public to allow QR scanning without authentication.

### Private (Login Required)
- `/box/admin/*`
- Box creation, editing, deletion
- QR generation
- PDF generation

---

## Data Protection

- JSON data files are stored in `/box/data/`
- Direct web access to `*.json` files is blocked using `.htaccess`
- PHP scripts access JSON files internally using filesystem reads

Example protection rule:

```apache
<FilesMatch "\.(json)$">
  Require all denied
</FilesMatch>


⚠️ Note:
- You must have **two sets** of triple backticks
- The inner ones define the code block
- The outer file remains normal Markdown

---

## Why this matters (quick nerd note)
Markdown is extremely literal. If the closing ``` is missing or malformed:
- Everything after becomes plain text
- GitHub doesn’t warn you
- The file still “works” but looks wrong

This is one of the most common README/SECURITY.md issues even in big repos.

---

## Quick verification checklist

After fixing:

- [ ] `<FilesMatch>` block renders in monospace
- [ ] Lines are syntax-highlighted (Apache)
- [ ] Text after renders normally again

If that’s true, you’re 100% clean.

---

## Status check
- 🔐 **Security model unchanged**
- 📄 **Documentation intent unchanged**
- ✍️ **Formatting corrected**
- 🚀 **Still v1.0.0 ready**

If you want, next we can:
- do a **final doc polish sweep** (README + SECURITY + RESTORE)
- or move on to **GitHub release notes**
- or officially **tag v1.0.0** and close the loop

You’re down to finishing touches now.
