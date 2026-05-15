# Heartbeat System — VERSION INDEX
Last Updated: 2025-11-13

This document lists the current revision number of every component in the Heartbeat System.  
Each file maintains its own independent revision history.

---

## 📁 PowerShell Components

### `heartbeat.ps1`
- **Revision:** 1.6
- **Purpose:** Sends heartbeat data to jasr.me and logs locally.

### `Heartbeat-Task.xml`
- **Revision:** 1.3
- **Purpose:** Windows Scheduled Task definition for automated heartbeats.

---

## 📁 PHP Server Components

### `save_log.php`
- **Revision:** 2.2  
- **Purpose:** Receives POST data from devices, validates, rate-limits, and logs JSON.

### `dashboard.php`
- **Revision:** 1.6  
- **Purpose:** Dashboard UI showing latest device entry, sortable and searchable.

### `view.php`
- **Revision:** 1.4  
- **Purpose:** Full historical view of a single device's log entries.

---

## 📁 Documentation

### `README.md`
- **Revision:** 1.0  
- **Purpose:** Installation, configuration, deployment instructions.

### `CHANGELOG.md`
- **Revision:** 1.0  
- **Purpose:** Project-wide feature log grouped by component.

---

## 📁 Directory Layout

computers/
│
├── dashboard.php
├── view.php
├── save_log.php
│
├── logs/ # Device JSON files
├── ratelimit/ # Per-device rate-limit keys
│
└── assets/
└── tablesort.js # (optional local copy)


---

If you add, remove, or update files — update this index.