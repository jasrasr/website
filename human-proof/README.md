# Robot or Human? — Press & Hold Verification Gate

A single-page "human verification" gate: the visitor **presses and holds** a button
until a progress bar fills, then they're verified. Includes a comic robot in a
Groucho disguise sweating through its own humanity test.

## Features

- **Press & hold** to fill the bar (default 3 seconds).
- **Wiggle boost** — moving the mouse while holding fills the bar up to ~4× faster.
- **Forgiving release** — let go early and the bar drains back down (yellow) at the
  same speed; press again to resume from where it was.
- **Close (✕)** flags the visitor as *"not verified — could be a robot"*.
- **Success** congratulates them; a **Retest** button lets them run it again.
- Keyboard accessible (hold **Space** or **Enter**) and respects
  `prefers-reduced-motion`.

## Files

| File | Purpose |
|------|---------|
| `index.html` | The gate UI + all client logic. Works standalone. |
| `verify.php` | Server endpoint (server mode only): issues/validates a one-time token, marks the session verified, returns the next URL. |
| `next.php` | Protected destination (server mode only): checks the session server-side and bounces unverified visitors. |
| `README.md` | This file. |

---

## The two modes

All configuration lives at the top of the `<script>` block in **`index.html`**:

```js
const SERVER_MODE = false;          // ← the switch
const VERIFY_URL  = 'verify.php';
const STATIC_NEXT = '';             // base64 of the next URL (static mode only)
```

### Mode 1 — Static (default, `SERVER_MODE = false`)

- No backend needed. Works on GitHub Pages or any plain file host.
- The "next" URL, if any, is stored **base64-encoded** in `STATIC_NEXT` and only
  decoded on success.
- ⚠️ **Speed bump, not security.** The URL is still discoverable by anyone who
  opens DevTools (Network tab / Sources) or the JS console. Fine for a fun
  puzzle/scavenger chain; **not** fine if people genuinely must not skip ahead.

### Mode 2 — Server-side gate (`SERVER_MODE = true`)

- Requires PHP hosting (e.g. Hostinger). Uses `verify.php` + `next.php`.
- The destination lives **only on the server**. Viewing the source of
  `index.html` shows just a `fetch('verify.php')` — no next URL, no next content.
- Opening `next.php` directly without a verified session redirects back to the gate.
- This is **real** protection against reading the source to jump ahead.

---

## How to swap between modes

### → Switch to Static mode

1. In `index.html`, set:
   ```js
   const SERVER_MODE = false;
   ```
2. (Optional) To redirect somewhere on success, base64-encode your URL and put it
   in `STATIC_NEXT`. Leave it `''` to just show the congrats screen.

   Encode a URL:
   - Browser console: `btoa('next.html')` → `bmV4dC5odG1s`
   - PowerShell: `[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('next.html'))`

   ```js
   const STATIC_NEXT = 'bmV4dC5odG1s';   // decodes to next.html
   ```
3. Upload `index.html` (and your `next.html`) anywhere. Done.

### → Switch to Server mode

1. In `index.html`, set:
   ```js
   const SERVER_MODE = true;
   ```
2. Make sure `VERIFY_URL` points at `verify.php` (default is fine if they're in the
   same folder).
3. Put your real content inside **`next.php`** (replace the placeholder body).
4. Upload `index.html`, `verify.php`, and `next.php` to your PHP host (Hostinger),
   keeping them in the **same folder** so the relative paths and PHP session cookie
   line up.
5. Visit `index.html` and complete the hold — the browser calls `verify.php`, the
   server verifies, and it navigates to `next.php`.

That's the only change: **one boolean.** The UI, animations, and robot are
identical in both modes.

---

## Optional: real bot protection (Cloudflare Turnstile)

The press-and-hold gesture is **UX, not proof** — a script can fake a 3-second
hold. For genuine bot defense in server mode:

1. Get free Turnstile keys at <https://dash.cloudflare.com> → Turnstile.
2. Add the Turnstile widget to `index.html` and send its token in the `verify.php`
   POST body.
3. Uncomment the **TURNSTILE** block in `verify.php` and paste your secret key.

The hold animation then just wraps a real, server-verified challenge in a nicer UX.

---

## Config reference (`index.html`)

| Constant | Default | Meaning |
|----------|---------|---------|
| `SERVER_MODE` | `false` | `false` = static; `true` = use `verify.php`. |
| `VERIFY_URL` | `'verify.php'` | Endpoint called on success in server mode. |
| `STATIC_NEXT` | `''` | Base64 URL to redirect to on success (static mode only). |
| `DURATION` | `3000` | Milliseconds you must hold at base speed. |
| `MAX_BOOST` | `3` | Extra fill-speed multiplier at full mouse wiggle (`1 + 3` = 4×). |

Server-side token/session lifetimes live in `verify.php` (5-min nonce) and
`next.php` (10-min verified session, single use).
