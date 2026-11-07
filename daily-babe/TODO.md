# Daily Babe TODO

- [ ] In Hostinger, confirm the `send-missing-photo-reminders.php` cron command exists and runs every 15 minutes; then use Settings → Check scheduler status and verify a recent successful run.
- [ ] When back at a computer, add the Twilio Account SID, Auth Token, and SMS-capable sender number to Hostinger’s ignored `config/config.php`; enable SMS reminders in the baby’s Settings, enter the recipient number, run the SMS test command, and verify one scheduled reminder.
- [ ] Add secure inbound email photo uploads: accept an image attachment for a missing day, map it to the correct baby/date using a revocable single-use token, validate sender and image contents/size, and record the upload in the gallery audit trail.
