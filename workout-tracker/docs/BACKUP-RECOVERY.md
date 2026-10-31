<!--
File Name: BACKUP-RECOVERY.md
Author: Jason Lamb (with help from ChatGPT)
Description: Private Workout Tracker backup, verification and recovery runbook.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Document backup, private photos and non-destructive recovery.
-->
# Workout Tracker: backup and recovery

**Production workout data already exists. Never delete, reinitialize or replace live records/photos as part of deployment.** There is deliberately no automatic restore or import endpoint.

## Before deploying v1.5.0

1. Create and verify a separate restorable Hostinger backup of the existing workout-tracker/storage/ folder, including user JSON, lock files and storage/photos/ subfolders. Preserve ownership and permissions. Store backups **outside the public web root**.
2. Confirm the GitHub deployment webhook does not run destructive cleanup such as git clean -fdx, rsync --delete over runtime storage, or a full directory replacement. Git exclusions do not protect data against deletions.
3. Record the existing workout count, dates and attached photos in your signed-in account. The October 21 and October 22 entries must remain.
4. Verify storage/.htaccess is enforced (a request for a private test file must return HTTP 403); configure an equivalent deny rule for other web servers.

These Hostinger steps require authorized hosting access. They cannot be verified by a GitHub merge alone.

## Download an authenticated private backup (after deployment)

1. Sign into https://jasr.me/github/workout-tracker/ using central user-management with member access.
2. Select **Download my workout backup**. The server validates your session and CSRF token, then provides a ZIP including:
   - workouts.json: your **complete original JsonStore document**, including entry history.
   - photos/: only photos attached to your authenticated account's workout records.
   - README.txt: record/photo counts, export time and recovery warning.
3. Save the ZIP securely outside the site and GitHub. It contains private workouts and photos. Retain the independent Hostinger backup.
4. Verify that the ZIP opens and contains your expected workout count and photo files.

**Requirements:** Hostinger must provide PHP ZipArchive and fileinfo. ZIP download is disabled when ZipArchive is unavailable. An export fails if a referenced photo is missing/invalid rather than claiming a complete backup. Exporting never modifies workout JSON or images.

## Controlled recovery (never automatic)

1. Before restoring, make a **new backup of current live storage**, even if it appears damaged.
2. Extract and inspect the ZIP in a **private staging location**. Validate the JSON schema, photo names, entry IDs and edit history. Reject archive path traversal and mismatched account storage.
3. Reconcile old/new entries; never automatically replace newer submissions or duplicate records.
4. Obtain approval before production restoration and restore only the verified target user's JSON/photos under original private permissions.
5. Confirm all records, photos and authentication still work; keep the pre-restore backup.

There is **no Restore button** on the public site. A restore is a separate high-risk administrator operation.

## Hostinger / iPhone release checks

- Existing sessions and October 21/22 notes, distances and photos remain.
- Tap **Details** on a saved treadmill entry: complete metrics, notes and attached image appear. Tap **Open full-size photo** to view the original.
- Signed out, the same ?photo=entry-id request must be denied. A second account must not see another user's photo.
- A direct request to storage/photos/ must receive HTTP 403.
- The detail view works on a narrow iPhone without horizontal scrolling.
- Strength details list each weight/repetition set and correct aggregate volume.
- **Edit this workout** opens the existing editor. Cancel changes nothing; saving alters only that entry.
- Download a ZIP and verify record count, image content and opening the archive after a page refresh.
- Check that Hostinger deployment preserved old JSON, lock files and images.
- Ensure no private key or account data appears in public logs or GitHub.

CI uses **synthetic files only**, not real hosted storage. Live authentication, storage protection, backup success and physical iPhone behavior must be checked separately.
