// File Name: shared-auth.test.cjs
// Author: Jason Lamb (with help from ChatGPT)
// Description: Disposable HTTP integration checks for Box Inventory.
// Revision: 1.0.1
// Created Date: 2026-11-19
// Modified Date: 2026-11-20
// Changelog: 1.0.1 - Verify renaming preserves items and the existing QR code.
// 1.0.0 - Verify batch creation, validation, and individual QR files.
const {test, after} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'jasr-box-auth-'));
for (const name of ['1-Framework', 'user-management', 'box']) {
  fs.cpSync(path.join(root, name), path.join(temp, name), {recursive: true});
}
const data = path.join(temp, 'user-management/data');
for (const name of fs.readdirSync(data)) if (name !== '.htaccess') fs.rmSync(path.join(data, name), {recursive: true, force: true});
fs.writeFileSync(path.join(temp, 'user-management/config.local.php'),
  "<?php return ['secure_cookie'=>false,'setup_key'=>'test-only-setup-key-with-at-least-32-characters'];");
const boxData = path.join(temp, 'box/data');
fs.copyFileSync(path.join(temp, 'box/examples/boxes.example.json'), path.join(boxData, 'boxes.json'));
fs.cpSync(path.join(temp, 'box'), path.join(temp, 'boxes'), {recursive: true});
// QR generation is stubbed because the PHP test runtime does not have GD.
fs.writeFileSync(path.join(temp, 'box/lib/qr.php'), `<?php
function generateBoxQR(string $code): string { $path=__DIR__.'/../qrcodes/'.$code.'.png'; if (!is_dir(dirname($path))) mkdir(dirname($path),0755,true); file_put_contents($path,'fixture'); return $path; }
function qrExists(string $code): bool { return is_file(__DIR__.'/../qrcodes/'.$code.'.png'); }
function deleteBoxQR(string $code): void { @unlink(__DIR__.'/../qrcodes/'.$code.'.png'); }
`);
const base = 'http://127.0.0.1:18786';
const server = spawn('php', ['-S', '127.0.0.1:18786', '-t', temp], {stdio: ['ignore', 'pipe', 'pipe']});
let logs = '';
server.stderr.on('data', chunk => logs += chunk);
after(() => { server.kill(); fs.rmSync(temp, {recursive: true, force: true}); if (/Warning:|Fatal error/.test(logs)) console.error(logs); });

class Browser {
  cookie = ''; csrf = '';
  async request(route, values) {
    const headers = {Cookie: this.cookie};
    const options = {headers, redirect: 'manual'};
    if (values) {
      options.method = 'POST'; headers['Content-Type'] = 'application/x-www-form-urlencoded';
      options.body = new URLSearchParams({csrf: this.csrf, ...values});
    }
    const response = await fetch(base + route, options);
    const cookie = response.headers.get('set-cookie');
    if (cookie) this.cookie = cookie.split(';')[0];
    const body = await response.text();
    this.csrf = body.match(/name="csrf" value="([a-f0-9]+)"/)?.[1] || this.csrf;
    return {status: response.status, body, location: response.headers.get('location')};
  }
  async login(username, password) {
    await this.request('/user-management/');
    return this.request('/user-management/', {action: 'login', username, password});
  }
}
function directory() { return JSON.parse(fs.readFileSync(path.join(data, 'directory.json'), 'utf8')).records; }
function boxes() { return JSON.parse(fs.readFileSync(path.join(boxData, 'boxes.json'), 'utf8')).boxes; }

test('shared identity guards box admin routes, isolates demo data, and enforces CSRF', async () => {
  let ready = false;
  for (let i = 0; i < 80; i++) {
    try { await fetch(base + '/user-management/'); ready = true; break; }
    catch { await new Promise(resolve => setTimeout(resolve, 100)); }
  }
  assert.ok(ready, logs);
  const guest = new Browser();
  assert.equal((await guest.request('/box/admin/index.php')).status, 303);
  assert.equal((await guest.request('/box/admin/create_box.php')).status, 303);
  assert.equal((await guest.request('/box/edit_box.php?c=BOXABC123')).status, 303);
  assert.equal((await guest.request('/box/')).status, 200);
  assert.equal((await guest.request('/box/?c=BOXABC123')).status, 200);
  assert.equal((await guest.request('/boxes/')).status, 200);
  assert.equal((await guest.request('/boxes/?c=BOXABC123')).status, 200);
  assert.equal((await guest.request('/box/test.php')).status, 404);
  assert.equal((await guest.request('/box/create_hash.php')).status, 404);

  const admin = new Browser();
  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'setup', setup_key: 'test-only-setup-key-with-at-least-32-characters', username: 'jasrasr', name: 'Jason', password: 'test-admin-password-2026', confirm_password: 'test-admin-password-2026'})).status, 303);
  assert.equal((await admin.login('jasrasr', 'test-admin-password-2026')).status, 303);
  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'save-project', project: 'box', name: 'Box Inventory', path: '/box/'})).status, 303);
  assert.equal((await admin.request('/box/admin/index.php')).status, 200);
  assert.doesNotMatch((await admin.request('/box/admin/index.php')).body, /Demo Box/);
  assert.equal((await admin.request('/box/admin/create_box.php')).status, 200);
  assert.equal((await admin.request('/box/admin/create_box.php', {box_name: 'Denied', csrf: 'bad'})).status, 403);
  assert.equal((await admin.request('/box/admin/create_box.php', {box_name: 'New tools'})).status, 303);
  const beforeBatch = Object.keys(boxes()).length;
  const blank = await admin.request('/box/admin/create_box.php', {box_name: ' \n\t'});
  assert.match(blank.body, /Enter at least one box name/);
  const tooMany = await admin.request('/box/admin/create_box.php', {box_name: Array(51).fill('Extra').join('\n')});
  assert.match(tooMany.body, /Enter up to 50 box names/);
  assert.equal(Object.keys(boxes()).length, beforeBatch);
  const batch = await admin.request('/box/admin/create_box.php', {box_name: ' Garage \r\n\r\n0\rKitchen \n'});
  assert.equal(batch.status, 303);
  assert.match(batch.location, /created=3/);
  assert.match((await admin.request('/box/admin/' + batch.location)).body, /3 boxes created/);
  const batchBoxes = Object.entries(boxes()).filter(([, box]) => ['Garage', '0', 'Kitchen'].includes(box.name));
  assert.equal(batchBoxes.length, 3);
  assert.equal(Object.keys(boxes()).length, beforeBatch + 3);
  for (const [code, box] of batchBoxes) {
    assert.match(code, /^BOX[A-F0-9]{6}$/);
    assert.match(box.owner, /^id:/);
    assert.ok(fs.existsSync(path.join(temp, 'box/qrcodes', code + '.png')));
  }
  const created = Object.entries(boxes()).find(([, box]) => box.name === 'New tools');
  assert.ok(created, 'New box was not saved');
  const adminId = Object.values(directory().users).find(user => user.username === 'jasrasr').id;
  assert.equal(created[1].owner, `id:${adminId}`);
  assert.equal((await admin.request('/box/admin/edit_box.php?c=' + created[0])).status, 200);
  assert.equal((await admin.request('/box/admin/edit_box.php?c=' + created[0], {items: 'Changed', csrf: 'bad'})).status, 403);
  assert.equal((await admin.request('/box/admin/edit_box.php?c=' + created[0], {items: 'Changed'})).status, 303);
  assert.deepEqual(boxes()[created[0]].items, ['Changed']);
  assert.equal((await admin.request('/box/admin/generate_qr.php?c=' + created[0])).status, 405);
  assert.equal((await admin.request('/box/edit_box.php?c=' + created[0], {items: 'Bypass'})).status, 405);
  assert.deepEqual(boxes()[created[0]].items, ['Changed']);

  const editRoute = '/box/admin/edit_box.php?c=' + created[0];
  const invalidRename = await admin.request(editRoute, {box_name: '  ', items: 'Unsaved draft'});
  assert.equal(invalidRename.status, 422);
  assert.match(invalidRename.body, /Enter a box name/);
  assert.match(invalidRename.body, /Unsaved draft/);
  assert.equal(boxes()[created[0]].name, 'New tools');
  const renamed = await admin.request(editRoute, {box_name: '  Garage tools  '});
  assert.equal(renamed.status, 303);
  assert.equal(boxes()[created[0]].name, 'Garage tools');
  assert.deepEqual(boxes()[created[0]].items, ['Changed']);
  assert.ok(fs.existsSync(path.join(temp, 'box/qrcodes', created[0] + '.png')));
  assert.match((await admin.request('/box/?c=' + created[0])).body, /Garage tools/);
  assert.equal((await admin.request('/box/admin/edit_box.php?c=BOXABC123')).status, 404);
  assert.equal((await admin.request('/box/admin/generate_qr.php', {code: 'BOXABC123'})).status, 404);
  assert.equal((await admin.request('/box/admin/generate_qr.php', {code: created[0]})).status, 303);
  assert.ok(fs.existsSync(path.join(temp, 'box/qrcodes', created[0] + '.png')));

  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'create-user', username: 'member', name: 'Member', password: 'temporary-password-2026'})).status, 303);
  const memberId = Object.values(directory().users).find(user => user.username === 'member').id;
  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'grant', id: memberId, project: 'box', role: 'member'})).status, 303);
  const member = new Browser();
  assert.equal((await member.login('member', 'temporary-password-2026')).status, 303);
  assert.equal((await member.request('/box/admin/index.php')).status, 303); // Password change required.
  await member.request('/user-management/');
  assert.equal((await member.request('/user-management/', {action: 'change-password', current_password: 'temporary-password-2026', password: 'permanent-password-2026', confirm_password: 'permanent-password-2026'})).status, 303);
  assert.equal((await member.login('member', 'permanent-password-2026')).status, 303);
  assert.doesNotMatch((await member.request('/box/admin/index.php')).body, /Demo Box|Garage tools/);
  assert.equal((await member.request('/box/admin/edit_box.php?c=BOXABC123')).status, 404);
  assert.equal((await member.request('/box/admin/delete_box.php', {code: 'BOXABC123'})).status, 303);
  assert.ok(boxes().BOXABC123, 'Cross-owner delete succeeded');
  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'create-user', username: 'viewer', name: 'Viewer', password: 'viewer-temporary-2026'})).status, 303);
  const viewerId = Object.values(directory().users).find(user => user.username === 'viewer').id;
  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'grant', id: viewerId, project: 'box', role: 'viewer'})).status, 303);
  const viewer = new Browser();
  assert.equal((await viewer.login('viewer', 'viewer-temporary-2026')).status, 303);
  await viewer.request('/user-management/');
  assert.equal((await viewer.request('/user-management/', {action: 'change-password', current_password: 'viewer-temporary-2026', password: 'viewer-permanent-2026', confirm_password: 'viewer-permanent-2026'})).status, 303);
  assert.equal((await viewer.login('viewer', 'viewer-permanent-2026')).status, 303);
  assert.equal((await viewer.request('/box/admin/index.php')).status, 200);
  assert.equal((await viewer.request('/box/admin/create_box.php')).status, 403);
  assert.equal((await viewer.request('/box/admin/delete_box.php', {code: 'BOXABC123'})).status, 403);
  assert.equal((await member.request('/box/admin/logout.php')).status, 405);
  assert.equal((await member.request('/box/admin/logout.php', {})).status, 303);
  assert.equal((await member.request('/box/admin/index.php')).status, 303);
  assert.equal((await member.login('member', 'permanent-password-2026')).status, 303);
  await admin.request('/user-management/');
  assert.equal((await admin.request('/user-management/', {action: 'save-user', id: memberId})).status, 303);
  assert.equal((await member.request('/box/admin/index.php')).status, 303);
  assert.equal((await member.request('/box/admin/create_box.php')).status, 303);
});
