# Questions For Review

## Current Status
- No blocking questions at the moment.

## Working Assumptions
- Audit revert restores the full prior user dataset snapshot for the selected audit entry.
- Daily automatic backups are triggered on the first successful login of the day for that user.
- Backup retention is capped at the newest 99 snapshots per user.
