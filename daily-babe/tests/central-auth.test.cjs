const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('uses shared identity and a clean central storage namespace', () => {
  const bootstrap = read('bootstrap.php');
  assert.match(bootstrap, /SharedIdentity::connect\(\)/);
  assert.match(bootstrap, /BABY_TRACKER_PROJECT = 'daily-babe'/);
  assert.match(bootstrap, /central\/data\/state\.json/);
  assert.match(bootstrap, /central\/babies\//);
  assert.doesNotMatch(bootstrap, /session_start\s*\(/);
  assert.doesNotMatch(bootstrap, /password_(?:hash|verify)\s*\(/);
});

test('removes local login and password controls', () => {
  const index = read('index.php');
  const api = read('api.php');
  const app = read('assets/app.js');
  assert.doesNotMatch(index, /type="password"|id="authForm"|id="passwordForm"/);
  assert.doesNotMatch(app, /authForm|passwordForm|add_user|change_password/);
  assert.doesNotMatch(api, /passwordHash|password_hash\s*\(|password_verify\s*\(/);
  assert.match(api, /central User Management for accounts and passwords/);
});

test('enforces central roles on every mutation', () => {
  const api = read('api.php');
  for (const action of ['add_baby', 'users', 'update_user_access']) {
    assert.match(api, new RegExp(`if \\(\\$action === '${action}'\\)[\\s\\S]{0,180}require_project_role\\('admin'\\)`));
  }
  for (const action of ['settings', 'upload', 'delete_photo']) {
    assert.match(api, new RegExp(`if \\(\\$action === '${action}'\\)[\\s\\S]{0,180}require_project_role\\('member'\\)`));
  }
  assert.match(api, /updatedCentralUserId/);
  assert.match(api, /daily_babe_selected_baby_id/);
});

test('keeps private images protected and public images opt-in', () => {
  assert.match(read('image.php'), /daily_require_user\('viewer'\)/);
  assert.match(read('public.php'), /public_gallery_by_slug/);
  assert.match(read('public-image.php'), /public_gallery_by_slug/);
  assert.doesNotMatch(read('public.php'), /require_auth|daily_require_user/);
  assert.doesNotMatch(read('public-image.php'), /require_auth|daily_require_user/);
  assert.match(read('bootstrap.php'), /publicEnabled/);
  assert.match(read('public.php'), /unlisted_gallery_credentials_match/);
  assert.match(read('public-image.php'), /unlisted_gallery_access_granted/);
  assert.match(read('bootstrap.php'), /shared_gallery_identifier/);
});

test('uses immutable central user IDs for gallery memberships', () => {
  const api = read('api.php');
  const app = read('assets/app.js');
  assert.match(api, /\$userId = \(string\) \(\$_POST\['userId'\]/);
  assert.match(api, /\$auth\['memberships'\]\[\$userId\]/);
  assert.match(app, /name="userId"/);
  assert.doesNotMatch(app, /newUserBabies|userForm/);
});

test('validates gender and applies the matching gallery theme', () => {
  const bootstrap = read('bootstrap.php');
  const api = read('api.php');
  const index = read('index.php');
  const publicPage = read('public.php');
  const app = read('assets/app.js');
  const css = read('assets/app.css');
  assert.match(bootstrap, /'gender' => ''/);
  assert.match(api, /\['', 'boy', 'girl'\]/);
  assert.match(index, /name="gender"/);
  assert.match(index, /theme-' \. \$gender/);
  assert.match(publicPage, /theme-' \. \$gender/);
  assert.match(app, /theme-boy','theme-girl/);
  assert.match(css, /body\.theme-boy/);
  assert.match(css, /body\.theme-girl/);
});

test('reminder links open the authorized gallery upload for the missing date', () => {
  const reminder = read('send-missing-photo-reminders.php');
  const index = read('index.php');
  const app = read('assets/app.js');
  assert.match(reminder, /http_build_query/);
  assert.match(reminder, /'baby' => \$babyId/);
  assert.match(reminder, /'upload' => '1'/);
  assert.match(reminder, /'date' => \$today/);
  assert.match(reminder, /\$uploadUrl/);
  assert.match(index, /\$_GET\['baby'\]/);
  assert.match(index, /can_access_baby\(\$account, \$requestedBabyId, \$authData\)/);
  assert.match(index, /\$_SESSION\['daily_babe_selected_baby_id'\]/);
  assert.match(index, /openUpload/);
  assert.match(index, /uploadDate/);
  assert.match(app, /BABY_TRACKER\.openUpload/);
  assert.match(app, /uploadDialog'\)\.showModal/);
});

test('keeps Twilio secrets server-side and supports optional SMS reminders', () => {
  const config = read('config/config.example.php');
  const sms = read('lib/twilio.php');
  const reminder = read('send-missing-photo-reminders.php');
  const api = read('api.php');
  const index = read('index.php');
  assert.match(config, /'twilio' => \[/);
  assert.match(config, /'account_sid' => ''/);
  assert.match(config, /'auth_token' => ''/);
  assert.match(sms, /api\.twilio\.com\/2010-04-01\/Accounts/);
  assert.match(sms, /Authorization: Basic/);
  assert.match(sms, /verify_peer_name' => true/);
  assert.match(reminder, /--test-sms/);
  assert.match(reminder, /reminderSmsEnabled/);
  assert.match(reminder, /:sms/);
  assert.match(reminder, /:email/);
  assert.match(api, /INVALID_REMINDER_PHONE/);
  assert.match(api, /REMINDER_PHONE_REQUIRED/);
  assert.match(index, /name="reminderSmsEnabled"/);
  assert.match(index, /name="reminderSmsNumber"/);
  assert.match(read('TODO.md'), /inbound email photo uploads/);
});

test('keeps mobile settings controls contained and softens public photo actions', () => {
  const css = read('assets/app.css');
  const publicPage = read('public.php');
  assert.match(css, /label \{ min-width: 0;/);
  assert.match(css, /input, textarea, label > select \{ width: 100%; min-width: 0; max-width: 100%/);
  assert.match(css, /@media \(max-width: 520px\)[\s\S]*?\.settings-grid \{ grid-template-columns: 1fr; \}/);
  assert.match(publicPage, /background:rgba\(255,255,255,\.46\)/);
  assert.match(publicPage, /color:var\(--ink\)/);
});

test('schedules three independently tracked reminder slots each day', () => {
  const bootstrap = read('bootstrap.php');
  const api = read('api.php');
  const index = read('index.php');
  const reminder = read('send-missing-photo-reminders.php');
  assert.match(bootstrap, /'reminderTimes' => \['12:00', '16:00', '20:00'\]/);
  assert.match(bootstrap, /function normalized_reminder_times/);
  assert.match(api, /count\(\$reminderTimes\) !== 3/);
  assert.match(index, /name="reminderTimes\[\]"/);
  assert.match(index, /First reminder/);
  assert.match(index, /Second reminder/);
  assert.match(index, /Final reminder/);
  assert.match(reminder, /\$reminderSlot/);
  assert.match(reminder, /\$legacyLedgerKey \. ':' \. \$reminderSlot/);
  assert.match(reminder, /reminderTime' => \$reminderSlot/);
  assert.match(reminder, /\(\\d\{4\}-\\d\{2\}-\\d\{2\}\)/);
});

test('diagnoses email delivery and records scheduler health without exposing secrets', () => {
  const api = read('api.php');
  const index = read('index.php');
  const app = read('assets/app.js');
  const reminder = read('send-missing-photo-reminders.php');
  assert.match(api, /test_reminder_email/);
  assert.match(api, /reminder_status/);
  assert.match(api, /smtp_send_message/);
  assert.match(index, /id="testReminderEmail"/);
  assert.match(index, /id="checkReminderStatus"/);
  assert.match(app, /SMTP is configured, but the scheduled reminder checker has never reported a run/);
  assert.match(reminder, /reminder-job-status\.json/);
  assert.doesNotMatch(reminder, /reminder-job-status[\s\S]{0,400}(?:password|auth_token)/);
});

test('stores and applies per-photo focus to galleries and exports', () => {
  const bootstrap = read('bootstrap.php');
  const api = read('api.php');
  const index = read('index.php');
  const app = read('assets/app.js');
  const publicPage = read('public.php');
  assert.match(bootstrap, /function normalized_photo_focus/);
  assert.match(api, /update_photo_focus/);
  assert.match(api, /'focusX' => 50/);
  assert.match(index, /Adjust picture focus/);
  assert.match(index, /id="focusX"/);
  assert.match(index, /id="focusY"/);
  assert.match(app, /object-position:\$\{focus\.x\}% \$\{focus\.y\}%/);
  assert.match(app, /drawCover\(ctx,img,x,y,w,h,record\)/);
  assert.match(publicPage, /normalized_photo_focus/);
});

