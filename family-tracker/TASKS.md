# Family GPS Tracker Task List

## Next practical improvements

- Add per-member display colors.
- Add member nickname editing.
- Add stale/offline notification badges.
- Add optional geofence zones, such as Home, School, Work, Church, or Grandma’s House.
- Add owner ability to deactivate a member.
- Add push notification support through a proper provider if this moves beyond a toy/site project.
- Add emergency contact card per member.
- Add export/delete-all account data controls.
- Add a one-time setup health check page that verifies folder write permissions and `.htaccess` protection.

## Security hardening

- Move `data/` outside the public web root.
- Add login throttling by username and IP hash.
- Add password reset flow.
- Add optional TOTP MFA for owner accounts.
- Add CSP headers after testing CDN dependencies.
- Add server-side retention cleanup for old trail points.

## Known limitations

- Browser GPS pauses or becomes unreliable when the phone sleeps, locks, or the browser is backgrounded.
- iOS Safari and Android browsers may behave differently for long-running location watches.
- Accuracy depends on device, OS, permissions, signal, and battery mode.
- This is not a true native Life360 replacement unless paired with a native app or PWA/background-location strategy.
