const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const code = fs.readFileSync(__dirname + '/../assets/js/tracker.js', 'utf8');
function boot(options = {}) {
  const requests = [], listeners = {};
  class Element { closest() { return this; } matches() { return true; } getAttribute() { return null; } }
  const storage = new Map();
  const context = {window: {}, URL, Uint8Array, Element, crypto: require('node:crypto').webcrypto,
    navigator: options.navigator || {}, location: {href: 'https://example.test/app.php?private=token#secret'},
    document: {currentScript: {src: 'https://stats.test/github/webstats/assets/js/tracker.js'},
      documentElement: {hasAttribute: () => false}, referrer: 'https://ref.test/path?token=secret',
      addEventListener: (name, fn) => listeners[name] = fn},
    fetch: (url, init) => { requests.push({url, init, event: JSON.parse(init.body)}); return Promise.resolve(); },
    localStorage: {getItem: () => options.exclude ? '1' : null, setItem() {}},
    sessionStorage: {getItem: key => storage.get(key), setItem: (key, val) => storage.set(key, val)}};
  if (options.noStorage) context.localStorage.getItem = () => { throw Error('blocked'); };
  vm.createContext(context); vm.runInContext(code, context);
  return {context, requests, listeners, Element};
}
test('cross-origin endpoint, no credentials, query and fragment removed', () => {
  const {requests} = boot();
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, 'https://stats.test/github/webstats/collect.php');
  assert.equal(requests[0].init.credentials, 'omit');
  assert.equal(requests[0].event.page, 'https://example.test/app.php');
  assert.equal(requests[0].event.referrer, 'https://ref.test');
});
test('duplicate embeds emit only one initial view', () => {
  const {context, requests} = boot(); vm.runInContext(code, context); assert.equal(requests.length, 1);
});
test('privacy preferences and owner exclusion prevent collection', () => {
  for (const options of [{navigator:{doNotTrack:'1'}}, {navigator:{globalPrivacyControl:true}}, {exclude:true}]) assert.equal(boot(options).requests.length, 0);
});
test('blocked storage does not break page tracking', () => assert.equal(boot({noStorage:true}).requests.length, 1));
test('delegated click strips destination tokens and does not read text or input value', () => {
  const {requests, listeners, Element} = boot();
  const target = new Element(); target.href = 'https://other.test/go?token=secret#private'; target.tagName = 'A';
  target.closest = selector => selector === '[data-analytics-ignore]' ? null : target;
  Object.defineProperty(target, 'textContent', {get() { throw Error('must not read'); }});
  Object.defineProperty(target, 'value', {get() { throw Error('must not read'); }});
  listeners.click({target}); assert.equal(requests[1].event.target, 'https://other.test/go');
});
test('named actions and opt-out API', () => {
  const {context, requests} = boot(); context.window.JasrWebstats.event('save-availability');
  assert.equal(requests[1].event.label, 'save-availability');
  context.window.JasrWebstats.exclude(); context.window.JasrWebstats.pageview(); assert.equal(requests.length,2);
});
