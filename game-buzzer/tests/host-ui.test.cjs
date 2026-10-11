const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
class Element {
  constructor(){this.children=[];this.dataset={};this.hidden=false;this.disabled=false;this.textContent='';this.value='15';}
  append(...nodes){this.children.push(...nodes);}
  replaceChildren(...nodes){this.children=nodes;}
  addEventListener(){}
  setAttribute(){}
}
test('host controls send authorized actions and practice UI never promises points',()=>{
  const nodes={};
  const html=fs.readFileSync(path.join(__dirname,'../index.php'),'utf8');
  for(const match of html.matchAll(/id="([^"]+)"/g))nodes[match[1]]=new Element();
  nodes.links.append(new Element()); // invitations are independently tested
  const storage=new Map(),calls=[];
  const context=vm.createContext({URLSearchParams,URL,Uint8Array,crypto:require('node:crypto').webcrypto,Date,performance,
    location:{search:'?room=123456789abc&view=host',hash:'',pathname:'/game-buzzer/',origin:'https://example.test'},
    localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},
    document:{getElementById:id=>nodes[id],createElement:()=>new Element(),createTextNode:text=>text,querySelectorAll:()=>[]},
    confirm:()=>true,setTimeout:()=>{},setInterval:()=>{},calls});
  const script=fs.readFileSync(path.join(__dirname,'../app.js'),'utf8').replace(/start\(\);\s*$/, '');
  vm.runInContext(script,context);
  vm.runInContext('command=(action,extra={})=>calls.push({action,extra});lastGood=Date.now();',context);
  const base={title:'Test',teamLinks:[],devices:Array(6).fill(0),teams:Array.from({length:6},(_,id)=>({id,name:`Team ${id+1}`,score:0})),
    practice:false,demoVotes:false,playStarted:false,ballotCount:0,surveyOpen:true,question:null,revealed:false,predictionTeams:[],myTeam:null,myPrediction:null,
    round:0,speedScored:false,openAt:null,closeAt:null,ranking:[],results:null,questions:Array(10).fill({prompt:'Question?',a:'One',b:'Two'})};
  const render=s=>vm.runInContext(`state=${JSON.stringify(s)};render();`,context);
  render(base);
  assert.equal(nodes.nextStep.disabled,true);
  assert.equal(nodes.topQuestions.disabled,false);
  nodes.practiceMode.onclick();nodes.resetScores.onclick();nodes.topQuestions.onclick();nodes.demoVotes.onclick();
  assert.deepEqual(calls.map(c=>c.action),['practice','resetScores','topQuestions','demo']);
  assert.equal(calls[0].extra.enabled,true);
  render({...base,practice:true,demoVotes:true,question:0,round:2,speedScored:true,predictionOpen:true});
  assert.match(nodes.nextStep.textContent,/no points/);
  assert.match(nodes.reveal.textContent,/no points/);
  assert.equal(nodes.closeSurvey.disabled,true);
  assert.equal(nodes.topQuestions.disabled,true);
  assert.equal(nodes.practiceBanner.hidden,false);
  nodes.nextStep.onclick();assert.equal(calls.at(-1).action,'reveal');
  for(const row of nodes.scores.children)for(const b of row.children[1].children.slice(1))assert.equal(b.disabled,true);
  render({...base,practice:true,round:3,speedScored:true,ranking:[{team:0,elapsedMs:100,gapMs:0}]});
  assert.match(nodes.ranking.children[0].children[0].textContent,/no points/);
  assert.doesNotMatch(nodes.ranking.children[0].children[0].textContent,/\+60 points/);
  nodes.practiceMode.onclick();assert.equal(calls.at(-1).extra.enabled,false);
  render({...base,round:4,speedScored:true});
  assert.equal(nodes.practiceBanner.hidden,true);
});
