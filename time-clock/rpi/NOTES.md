# RPi CM4 — Time Clock Kiosk Notes

## Hardware

- Raspberry Pi CM4
- HDMI monitor
- USB mouse + keyboard
- WiFi (no ethernet)

## OS

- Raspberry Pi OS based on **Debian 13 (Trixie)**, build date 2025-12-04
- Desktop session: **Wayland (Wayfire)**
- Confirm: `cat /etc/os-release` | `echo $XDG_SESSION_TYPE`

## Website

- Hosted externally at `https://jasr.me/time-clock`
- No local web server needed on the Pi
- PHP backend hosted on Hostinger

## Kiosk Setup

### `/usr/local/bin/kiosk.sh` ✅ Done

Note: command is `chromium` not `chromium-browser` on Trixie.

```bash
#!/bin/bash
until ping -c1 jasr.me &>/dev/null; do
    sleep 3
done

chromium --kiosk \
  --noerrdialogs \
  --disable-infobars \
  --disable-session-crashed-bubble \
  --password-store=basic \
  --ozone-platform=wayland \
  https://jasr.me/time-clock
```

Make executable: `sudo chmod +x /usr/local/bin/kiosk.sh`

### Autostart ✅ Done

`~/.config/autostart/kiosk.desktop` does **not** work on Wayfire. Use `~/.config/wayfire.ini` instead:

```ini
[autostart]
kiosk = /usr/local/bin/kiosk.sh
```

### Auto-login ✅ Done

Configured via `sudo raspi-config` → System Options → Boot / Auto Login → Desktop Autologin

### Screen Blanking — Wayland/Wayfire

`xset` does NOT work on Wayland. Use `~/.config/wayfire.ini` instead:

```ini
[idle]
screensaver_timeout = 0
dpms_timeout = 0
```

## Exit Kiosk Mode

- `Alt + F4` — close Chromium
- `pkill chromium` — from terminal or SSH

## Custom Boot Splash (Not Yet Implemented)

Replace the default RPi boot screen with a custom logo.

### Steps

**1 — Install Plymouth:**
```bash
sudo apt install plymouth plymouth-themes -y
```

**2 — Suppress boot text — edit `/boot/firmware/cmdline.txt`:**

Add to the existing single line (do not add a new line):
```
quiet splash plymouth.ignore-serial-consoles logo.nologo
```

**3 — Disable RPi rainbow splash — edit `/boot/firmware/config.txt`:**
```
disable_splash=1
```

**4 — Set custom logo:**
```bash
sudo cp /path/to/logo.png /usr/share/plymouth/themes/pix/splash.png
```

The `logo.png` from the time-clock repo can be used here.

**5 — Update initramfs:**
```bash
sudo update-initramfs -u
```

## TODO

- [x] Confirm OS version
- [x] Create and deploy kiosk.sh
- [x] Make kiosk.sh executable
- [x] Enable auto-login
- [x] Fix autostart via wayfire.ini
- [x] Test full reboot → kiosk launches after WiFi connects
- [ ] Disable screen blanking in wayfire.ini [idle] section
- [ ] Implement custom boot splash (Plymouth)
