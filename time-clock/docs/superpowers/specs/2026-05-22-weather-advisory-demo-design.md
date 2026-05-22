# Weather Advisory Demo — Design Spec
**Date:** 2026-05-22
**Project:** jasr.me / time-clock
**Status:** Approved

---

## Overview

Two related improvements to the time-clock kiosk system:

1. **Fix real NWS overlay colors** — Real weather alerts on the kiosk currently always show a red overlay. They should use the same color standards broadcast TV uses (orange for Severe Thunderstorm, red for Tornado, etc.).

2. **Weather Advisory Demo button** — Add a new admin button that triggers a blue weather overlay for 5 minutes using pre-baked Girard, OH NWS-format content. Blue so it cannot be mistaken for a real emergency. Mirrors the existing Demo Alert pattern.

---

## Files Changed

| File | Change |
|------|--------|
| `index.html` | Fix NWS overlay colors; add weather demo overlay + poll |
| `admin.html` | Add Weather Advisory Demo section with countdown |
| `set-alarm.php` | Add `weather` alarm type with `expiresAt` auto-expiry |
| `weather-demo.json` | New file — demo state storage |
| `weather-demo.json.example` | New file — reference copy |

---

## 1. Real NWS Overlay — Correct TV Colors

### Color mapping (event-driven, checked first; severity fallback)

| Event (case-insensitive contains) | Background color |
|-----------------------------------|-----------------|
| Tornado Warning, Tornado Emergency, Extreme Wind Warning | `#cc0000` red |
| Severe Thunderstorm Warning | `#FF8C00` orange |
| Flash Flood Warning, Flash Flood Emergency | `#006400` dark green |
| Tornado Watch, Severe Thunderstorm Watch | `#b8860b` dark yellow |
| Anything else (Special Weather Statement, etc.) | `#444444` gray |

### Implementation

Add `getNWSColor(event)` to `index.html`. When `updateOverlay()` fires, call `getNWSColor()` and apply the result to `#nws-overlay`'s background via inline style instead of the current hardcoded CSS. The flashing animation keyframes stay but animate lighter/darker variants of the same color.

---

## 2. Weather Advisory Demo

### `weather-demo.json` structure

```json
{
  "active": false,
  "triggeredAt": null,
  "expiresAt": null,
  "event": "Severe Thunderstorm Warning",
  "headline": "Severe Thunderstorm Warning issued by NWS Cleveland OH",
  "senderName": "NWS Cleveland OH"
}
```

Pre-baked content is for **Trumbull County, OH (Girard area)** — always used regardless of whether real alerts exist at activation time.

### `set-alarm.php` changes

- Add `weather` entry to `$alarmTypes`:
  ```php
  'weather' => ['file' => 'weather-demo.json', 'message' => 'Severe Thunderstorm Warning']
  ```
- On `action=on`: set `expiresAt = time() + 300` (5 minutes from activation). Write all NWS content fields into the JSON alongside `active` and timestamps.
- On GET: if `active === true` and `time() > expiresAt`, return `active: false` without writing the file (lazy expiry — kiosk stops showing it, file cleans up on next admin load).

### `index.html` — weather demo overlay

New `#weather-demo-overlay` div inserted above the existing `#nws-overlay` at `z-index: 10003`.

- **Background:** `#1a5276` (matches existing Demo Alert blue)
- **Animation:** gentle blue pulse `#1a5276` ↔ `#2471a3` at 1.4s (matches existing demo-flash keyframe)
- **Content:**
  - Top label: `📋 WEATHER ADVISORY — DEMO` in `#5bc8f5`
  - Event title: `SEVERE THUNDERSTORM WARNING` large white bold
  - Headline line: `Severe Thunderstorm Warning issued by NWS Cleveland OH` in `#aad4f5`
  - Source: `⚠ NATIONAL WEATHER SERVICE — THIS IS A DRILL` in `#7fb8d8`
  - Clock (reuses existing `.overlay-clock` style)
- Polls `weather-demo.json` every 5 seconds
- Hides if `active: false` OR if local `Date.now() > expiresAt * 1000`

### `admin.html` — Weather Advisory Demo section

New section card, styled blue (`border-color: #1a5276; background: #eaf4fb`) matching the existing Demo Alert card.

**Elements:**
- Header: `🌩 Weather Advisory Demo`
- Description: "Triggers a blue weather overlay with NWS-format content for Girard, OH (Trumbull County). Auto-clears after 5 minutes. Blue overlay — will not cause public alarm."
- Status line: shows "● Inactive" (green) or "● ACTIVE — overlays showing on all displays" (red) + data source label
- Countdown bar: visible only when active — shows `⏱ Demo expires in: M:SS` updating every second; shows absolute expiry time
- Buttons: `🌩 Activate (5 min)` and `✓ Clear`

**JS behavior:**
- On activate: POST to `set-alarm.php`, on success start a `setInterval` countdown using `expiresAt` from response
- Countdown clears itself when it reaches 0:00 and refreshes status
- Follows existing `setAlarm()` / `loadAlarmStatus()` pattern; mutual exclusivity (activating clears all other alarms) already handled by `set-alarm.php`

---

## Data Flow

```
Admin clicks "Activate (5 min)"
  → POST set-alarm.php {type: "weather", action: "on"}
  → PHP writes weather-demo.json {active: true, expiresAt: now+300, event/headline/senderName}
  → Admin JS starts countdown timer

index.html polls weather-demo.json every 5s
  → active: true + not expired → show #weather-demo-overlay (blue)
  → active: false OR expired   → hide #weather-demo-overlay

After 5 min: PHP GET returns active:false (lazy expiry)
  → kiosk hides overlay automatically
  → admin countdown reaches 0, refreshes status to Inactive
```

---

## Out of Scope

- Fetching live NWS data at activation time (pre-baked template always used — Option A)
- Configurable duration (hardcoded 5 minutes)
- Per-zone selection on admin page
