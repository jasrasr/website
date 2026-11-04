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

