// Revision 1.0.0 | 2026-09-18 | Attendee recovery, mode isolation, placement and focus regressions.
// Run against a disposable local PHP server; requires Playwright and Chromium.
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8090';
if (!['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname)) throw Error('Use a local disposable server.');
async function post(body) {
  const response = await fetch(`${base}/api.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
  const data = await response.json(); assert.equal(response.status,200,JSON.stringify(data)); return data;
}
(async () => {
  const definition = {action:'create',title:'Response recovery',adminName:'Organizer',dates:['2026-11-01','2026-11-02'],adminPassword:'admin-pass',eventPassword:'guest-pass'};
  const created = await post(definition), id = created.event.id;
  const saved = await post({action:'vote',id,revision:1,eventPassword:'guest-pass',name:'Attendee',phone:'555-0199',email:'attendee@example.test',adults:2,kids:3,foodType:'Side dish',foodNote:'Salad',answers:{'2026-11-01':'yes','2026-11-02':'no'}});
  const oldLink = `${base}/?event=${id}#response=${saved.responseId}&key=${saved.responseToken}`;
  const browser = await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined, headless:true});
  const errors = [];
  async function pageIn(context) {
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    page.on('dialog', dialog => { assert.ok(!dialog.message().includes('admin password'),'Response recovery must not ask for admin password'); dialog.accept('guest-pass'); });
    return page;
  }
  async function restored(page, kids = '3') {
    await page.waitForFunction(() => document.getElementById('voter-name').value === 'Attendee');
    assert.equal(await page.locator('#phone').inputValue(),'555-0199');
    assert.equal(await page.locator('#email').inputValue(),'attendee@example.test');
    assert.equal(await page.locator('#kids').inputValue(),kids);
    assert.equal(await page.locator('#food-note').inputValue(),'Salad');
    assert.equal(await page.locator('#vote-dates select').first().inputValue(),'yes');
    assert.equal(await page.locator('#edit-event').isVisible(),false);
    assert.equal(await page.locator('#admin-contacts').isVisible(),false);
    await page.waitForFunction(() => document.activeElement.id === 'response-section');
    assert.ok(Math.abs(await page.locator('#response-section').evaluate(e => e.getBoundingClientRect().top)) < 30);
    assert.equal(await page.locator('#response-link-row').evaluate(e => e.previousElementSibling.id),'vote-form');
    assert.equal(await page.locator('#copy-response').isEnabled(),true);
  }
  try {
    const fresh = await browser.newContext({viewport:{width:390,height:844}});
    const page = await pageIn(fresh);
    await page.goto(oldLink); await restored(page);
    const newLink = await page.locator('#response-link').inputValue();
    assert.equal(new URL(newLink).searchParams.get('view'),'response');
    assert.equal(new URL(page.url()).hash,'');
    await page.reload(); await restored(page);
    await page.evaluate(() => window.scrollTo(0,0));
    await page.waitForTimeout(5500);
    assert.equal(await page.evaluate(() => window.scrollY),0,'Polling must not steal scroll/focus');
    await page.locator('#kids').fill('4'); await page.locator('#save-vote').click();
    await page.waitForFunction(() => document.getElementById('vote-state').textContent.includes('saved response'));
    await page.reload(); await restored(page,'4');
    const privateRead = await post({action:'view',id,eventPassword:'guest-pass',responseId:saved.responseId,responseToken:saved.responseToken});
    assert.equal(privateRead.event.responses.length,1,'Save updates the same response');
    assert.equal(privateRead.myResponse.kids,4);

    const remembered = await browser.newContext();
    await remembered.addInitScript(({id}) => localStorage.setItem(`availability:${id}`,JSON.stringify({adminToken:'stale-admin-token',responseId:'wrong-response',responseToken:'wrong-token'})),{id});
    const shared = await pageIn(remembered);
    const requests=[]; shared.on('request', r => {if(r.url().endsWith('/api.php') && r.postData()) requests.push(JSON.parse(r.postData()));});
    await shared.goto(oldLink); await restored(shared,'4');
    // Stop reseeding on reload; the same stored admin is retained by remember().
    const stable = await browser.newContext({storageState:await remembered.storageState()});
    const returning = await pageIn(stable); await returning.goto(newLink); await restored(returning,'4');
    await returning.reload(); await restored(returning,'4');
    assert.ok(requests.every(r => !r.adminToken),'Attendee requests must never attach remembered admin credentials');
    assert.equal(await shared.evaluate(id => JSON.parse(localStorage.getItem(`availability:${id}`)).adminToken,id),'stale-admin-token');

    const blocked = await browser.newContext();
    await blocked.addInitScript(() => Object.defineProperty(window,'localStorage',{get(){throw new Error('Storage blocked');}}));
    const noStorage = await pageIn(blocked); await noStorage.goto(newLink); await restored(noStorage,'4');
    assert.ok(new URL(noStorage.url()).hash.includes('response='));
    await noStorage.reload(); await restored(noStorage,'4');

    await post({...definition,action:'update',id,revision:1,adminToken:created.adminToken,closed:true});
    await page.reload(); await restored(page,'4');
    assert.equal(await page.locator('#save-vote').isDisabled(),true);
    assert.equal(await page.locator('#copy-response').isEnabled(),true,'Closed polls still allow copying the recovery link');
    assert.deepEqual(errors,[]);
    console.log('Response recovery browser checks passed: legacy/new links, fresh browser, passwords, stale admin, reload, private details, edit without duplication, blocked storage, placement, focus and closed polls.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
