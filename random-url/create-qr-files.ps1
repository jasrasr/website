$tracker = '<script defer src="https://jasr.me/github/webstats/assets/js/tracker.js"></script>'
3..9 | ForEach-Object { Set-Content -Path "$_.html" -Value @("qr$_", $tracker) }
