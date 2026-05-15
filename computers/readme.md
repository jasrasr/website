# 💓 Heartbeat System  
Track Windows device status using PowerShell → PHP → JSON → Dashboard UI.

Designed by **Jason Lamb**, engineered with assistance from **ChatGPT**.  
Lightweight, secure, cross-version compatible (PowerShell 5 + 7).

---

# 📁 Features

### Device-Side (PowerShell)
- Sends hardware + OS + user info every minute
- Auto-retries network outages
- Logs locally and to the server
- Works under SYSTEM or user context
- Hybrid username detection (explorer.exe owner)

### Server-Side (PHP + JSON)
- Rate-limited POST endpoint
- Secure shared secret
- Creates logs/ + ratelimit/ automatically
- Stores each device's history into a JSON file

### Dashboard UI
- Search devices instantly
- Click-to-sort columns
- Online/Offline counter
- Model + Serial + User display
- 24-hour EST timestamps w/ relative time (‘3 minutes ago’)

### Historical View
- Full JSON history displayed
- Sortable, readable table
- Summaries at top (“Last User Seen”)

---

# 🚀 Installation

## 1. Upload Files to Web Host
Place into:

public_html/computers/

diff
Copy code

Upload:

- dashboard.php  
- view.php  
- save_log.php  
- (optional) assets/tablesort.js  

Ensure the server autogenerates:

logs/
ratelimit/

yaml
Copy code

These will be created automatically by `save_log.php`.

---

# 🔐 Permissions

### logs/
- Must be writable by PHP
- Recommended: `0755` or `0775`
- NOT publicly accessible via indexing

### ratelimit/
- Same rules as logs/
- Auto-generated

---

# 🖥 Windows Client Installation

1. Copy `heartbeat.ps1` to:

C:\ProgramData\Heartbeat\

bash
Copy code

2. Copy `Heartbeat-Task.xml` to the same folder.

3. Install scheduled task:

```powershell
schtasks /create /tn "DeviceHeartbeat" /xml "C:\ProgramData\Heartbeat\Heartbeat-Task.xml" /f
The task runs as SYSTEM, hidden, every 1 minute.

⚠ Troubleshooting
No logs appear on server?
Run manually to see error:

powershell
Copy code
powershell -file "C:\ProgramData\Heartbeat\heartbeat.ps1"
Check local debug log:

lua
Copy code
C:\ProgramData\Heartbeat\debug.log
401 Unauthorized?
Secret mismatch between:

heartbeat.ps1

save_log.php

Rate limit triggered?
Set high for testing:

bash
Copy code
$maxRequestsPerHour = 99;
📊 JSON File Format (Server + Local)
Each device has:

pgsql
Copy code
logs/COMPUTERNAME.json
Append-only list of entries like:

json
Copy code
{
  "Secret": "...",
  "ComputerName": "DESKTOP123",
  "UserName": "middough\\jason.lamb",
  "Domain": "MIDDOUGH",
  "OSVersion": "10.0.26200",
  "Model": "Surface Laptop 6",
  "SerialNumber": "XYZ123",
  "LocalIP": "10.0.1.5",
  "PublicIP": "44.111.22.33",
  "BootTime": "2025-11-12 13:41:15",
  "UptimeMinutes": 1396.89,
  "TimeLocal": "2025-11-13 12:58:16",
  "TimeUTC": "2025-11-13 17:58:16",
  "ServerReceived": "2025-11-13 17:58:17"
}
📁 Project Structure
pgsql
Copy code
computers/
├── dashboard.php
├── view.php
├── save_log.php
│
├── logs/
│   └── COMPUTERNAME.json
│
├── ratelimit/
│   └── COMPUTERNAME.txt
│
├── VERSION.md
├── README.md
└── CHANGELOG.md
🧱 Future Enhancements (optional)
Hide stale devices toggle

CSV export

Per-device charts

Push notifications for offline events

Bulk API mode

Mobile-friendly dashboard redesign

🎉 Credits
Developed collaboratively by:
Jason Lamb
ChatGPT (OpenAI GPT-5.1)

Lightweight. Transparent. Self-hosted. No agents required.