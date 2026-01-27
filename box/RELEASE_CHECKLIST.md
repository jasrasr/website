# ✅ v1.0.0 Release Checklist  
QR Box Inventory System

This checklist confirms the system is complete, stable, secure, and recoverable.

All items should be verified before tagging `v1.0.0`.

---

## 📦 Core Functionality

- [ ] Create new box
- [ ] Unique box ID generated correctly
- [ ] Box name editable without changing ID
- [ ] Box items add/remove/save correctly
- [ ] Item count updates dynamically
- [ ] Last-updated timestamp updates correctly (EST)
- [ ] Box deletion removes box data

---

## 🔗 Public Access & URLs

- [ ] Public box page loads without login
- [ ] Pretty URL works (`/box/BOX123`)
- [ ] Legacy URL works (`/box/box.php?c=BOX123`)
- [ ] Invalid box code shows friendly error
- [ ] No PHP warnings visible on public pages

---

## 🧾 QR Code Lifecycle

- [ ] QR auto-generated on box creation
- [ ] QR points to pretty URL
- [ ] Generate QR for single owned box works
- [ ] Generate missing QR codes works
- [ ] QR regeneration is safe (idempotent)
- [ ] Deleting a box deletes QR PNG
- [ ] QR scans correctly on mobile

---

## 🖨️ PDF Printing

- [ ] Print all QR labels (PDF)
- [ ] Small labels render correctly (16 per page)
- [ ] Medium labels render correctly (6 per page)
- [ ] Large labels render correctly (1 per page)
- [ ] Per-box PDF printing works
- [ ] PDFs open and print without errors

---

## 🔐 Security & Authentication

- [ ] Admin pages require login
- [ ] Public pages accessible without login
- [ ] Logout destroys session
- [ ] Back button does not restore admin access
- [ ] Ownership enforced for users
- [ ] Admin override works correctly

---

## 🗂️ Data Protection

- [ ] `/data/*.json` blocked from web access
- [ ] JSON readable only by PHP
- [ ] No plaintext passwords stored
- [ ] No sensitive data embedded in QR codes

---

## 🔄 Backup & Restore

- [ ] `boxes.json` backed up
- [ ] `users.json` backed up
- [ ] `/qrcodes/` backed up (optional)
- [ ] Restore steps tested using `RESTORE.md`
- [ ] Missing QR regeneration tested

---

## 📚 Documentation

- [ ] README.md present and accurate
- [ ] SECURITY.md present
- [ ] RESTORE.md present
- [ ] Folder structure matches documentation

---

## ⚙️ Environment

- [ ] PHP 8.1+ confirmed
- [ ] Apache `mod_rewrite` enabled
- [ ] Required folders writable (`/data`, `/qrcodes`)
- [ ] `display_errors` disabled in production

---

## 🏁 Final

- [ ] All checklist items completed
- [ ] Repository committed cleanly
- [ ] Tag repository as `v1.0.0`

---

## 📌 Release Tag Command

```bash
git tag v1.0.0
git push origin v1.0.0

---

## ✅ Status

With these three files updated, your project now has:

- Clear security boundaries  
- A recovery playbook  
- A formal release gate  

This is **v1.0.0-quality software**.