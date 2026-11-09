const state={records:[],settings:{},filter:'all',exportBlob:null,exportUrl:null,exportType:'collage'};
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
function dateKey(d){return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`}
function localToday(){return dateKey(new Date())}
async function api(action,options={}){const response=await fetch(`api.php?action=${encodeURIComponent(action)}`,options);let body;try{body=await response.json()}catch{throw new Error('The server returned an unreadable response.')}if(!response.ok||!body.success)throw new Error(body.message||'Request failed.');return body.data}
function formOptions(form){return{method:'POST',headers:{'X-CSRF-Token':window.BABY_TRACKER.csrf},body:new FormData(form)}}
function toast(message){const el=$('#toast');el.textContent=message;el.classList.add('show');clearTimeout(toast.timer);toast.timer=setTimeout(()=>el.classList.remove('show'),2800)}
function daysBetween(a,b){return Math.floor((new Date(`${b}T12:00:00`)-new Date(`${a}T12:00:00`))/86400000)}
function dayNumber(date){const start=state.settings.birthDate||state.settings.dueDate;if(!start||date<start)return null;return daysBetween(start,date)+1}
function dateLabel(date){return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',year:'numeric'}).format(new Date(`${date}T12:00:00`))}
function arrivalTiming(){const{birthDate,dueDate}=state.settings;if(!birthDate||!dueDate)return'';const difference=daysBetween(birthDate,dueDate);if(difference>0)return`Born ${difference} day${difference===1?'':'s'} early.`;if(difference<0){const late=Math.abs(difference);return`Born ${late} day${late===1?'':'s'} after the due date.`}return'Born right on the due date.'}
function missingDates(){const start=state.settings.birthDate;if(!start)return[];const today=new Date(`${localToday()}T12:00:00`),found=new Set(state.records.map(r=>r.photoDate)),dates=[];for(let d=new Date(`${start}T12:00:00`);d<=today;d.setDate(d.getDate()+1)){const key=dateKey(d);if(!found.has(key))dates.push(key)}return dates.reverse()}
function streak(){if(!state.records.length)return 0;const dates=[...new Set(state.records.map(r=>r.photoDate))].sort().reverse();let count=1;for(let i=1;i<dates.length;i++){if(daysBetween(dates[i],dates[i-1])===1)count++;else break}return count}
function escapeHtml(value){const div=document.createElement('div');div.textContent=value;return div.innerHTML}
function photoFocus(record){const x=Number(record?.focusX),y=Number(record?.focusY);return{x:Number.isFinite(x)?Math.max(0,Math.min(100,x)):50,y:Number.isFinite(y)?Math.max(0,Math.min(100,y)):50}}
function photoRotation(record){const value=Number(record?.rotation),rotation=Number.isFinite(value)?((Math.round(value)%360)+360)%360:0;return[0,90,180,270].includes(rotation)?rotation:0}
function photoPresentation(record){const focus=photoFocus(record),rotation=photoRotation(record);if(rotation===90)return{rotation,x:focus.y,y:100-focus.x};if(rotation===180)return{rotation,x:100-focus.x,y:100-focus.y};if(rotation===270)return{rotation,x:100-focus.y,y:focus.x};return{rotation,x:focus.x,y:focus.y}}
function rotationText(rotation){return rotation===0?'No rotation':`${rotation}° clockwise`}
function applyPhotoPresentation(image,record){const presentation=photoPresentation(record);image.classList.remove('rotation-0','rotation-90','rotation-180','rotation-270');image.classList.add(`rotation-${presentation.rotation}`);image.style.objectPosition=`${presentation.x}% ${presentation.y}%`;return presentation}
function applyTheme(settings){document.body.classList.remove('theme-boy','theme-girl');if(['boy','girl'].includes(settings.gender))document.body.classList.add(`theme-${settings.gender}`);const field=$('#settingsForm')?.gender;if(field)field.value=settings.gender||''}
function render(){const gallery=$('#gallery');if(!gallery)return;const name=state.settings.babyName||'Baby',missing=missingDates();$('#photoCount').textContent=state.records.length;$('#missingCount').textContent=missing.length;$('#streakCount').textContent=streak();$('#heroTitle').textContent=name;if(state.settings.birthDate){const age=dayNumber(localToday()),timing=arrivalTiming();$('#heroStatus').textContent=age>0?`Today is day ${age}. ${timing} ${missing.length?`${missing.length} ${missing.length===1?'day is':'days are'} waiting for a photo.`:'Every day is filled in.'}`:`${timing} Ready for the first photo.`}else{const remaining=daysBetween(localToday(),state.settings.dueDate);$('#heroStatus').textContent=remaining>=0?`${remaining} days until the due date. Add the actual birth date when the big day arrives.`:'Add the actual birth date in Settings to begin accurate day numbering.'}gallery.innerHTML='';const items=state.filter==='missing'?missing:state.records;$('#emptyState').hidden=items.length!==0;if(state.filter==='missing'){items.forEach(date=>{const el=document.createElement('article');el.className='tile missing-tile';el.innerHTML=`<div><strong>${dateLabel(date)}</strong><p>Photo missing</p><button data-date="${date}">Add photo</button></div>`;gallery.append(el)});
return}items.forEach(record=>{const day=dayNumber(record.photoDate),presentation=photoPresentation(record),el=document.createElement('article');el.className='tile';el.tabIndex=0;el.innerHTML=`<div class="tile-photo rotatable-frame"><img class="rotation-${presentation.rotation}" loading="lazy" src="image.php?id=${encodeURIComponent(record.id)}&size=thumb" alt="${escapeHtml(name)} on ${dateLabel(record.photoDate)}" style="object-position:${presentation.x}% ${presentation.y}%"></div><div class="caption"><div><strong>${dateLabel(record.photoDate)}</strong><small>${escapeHtml(record.note||'Daily photo')}</small></div>${day?`<span class="day-chip">DAY ${day}</span>`:''}</div>`;el.addEventListener('click',()=>openPhoto(record));el.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' ')openPhoto(record)});
gallery.append(el)})}
function updateFocusPreview(){const preview=$('#focusPreview');if(!preview)return;const record={focusX:$('#focusX').value,focusY:$('#focusY').value,rotation:preview.dataset.rotation||0};applyPhotoPresentation(preview,record);$('#rotationLabel').textContent=rotationText(photoRotation(record))}
function openPhoto(record){const source=`image.php?id=${encodeURIComponent(record.id)}`;const fullPhoto=$('#fullPhoto'),rotation=photoRotation(record);fullPhoto.src=source;fullPhoto.alt=`Photo from ${dateLabel(record.photoDate)}`;fullPhoto.style.transform=`rotate(${rotation}deg)`;$('#deletePhotoButton').dataset.id=record.id;const focus=photoFocus(record),preview=$('#focusPreview');if(preview){preview.src=source;preview.dataset.id=record.id;preview.dataset.rotation=rotation;$('#focusX').value=focus.x;$('#focusY').value=focus.y;updateFocusPreview()}const day=dayNumber(record.photoDate);$('#photoDetails').textContent=`${day?`Day ${day} · `:''}${dateLabel(record.photoDate)}${record.note?` — ${record.note}`:''}`;$('#photoDialog').showModal()}
async function load(){const data=await api('list');state.records=data.records;state.settings=data.settings;state.account=data.account;applyTheme(data.settings);render();updatePublicSharing(data.settings)}
function publicGalleryUrl(slug){if(!slug)return'';const url=new URL('public.php',window.location.href);url.searchParams.set('baby',slug);return url.toString()}
function updatePublicSharing(settings){const form=$('#settingsForm');if(!form)return;const mode=settings.visibility||'private',slug=settings.publicSlug||'',choice=form.querySelector(`input[name="visibility"][value="${mode}"]`),panel=$('#publicLinkPanel'),input=$('#publicLink'),copy=$('#copyPublicLink'),open=$('#openPublicLink'),help=$('#publicLinkHelp');if(choice)choice.checked=true;panel.hidden=mode==='private';const active=mode!=='private'&&mode===state.settings.visibility&&Boolean(slug);input.value=active?publicGalleryUrl(slug):'Save settings to activate this link';copy.disabled=!active;open.disabled=!active;if(help)help.textContent=mode==='unlisted'?'The opaque link does not contain the baby’s name or birth date. Visitors must enter both to open it.':'The public link uses the baby’s first name and birthday: firstname-MMDDYYYY. It opens without a prompt.'}
async function copyPublicLink(){const value=$('#publicLink').value;if(!value||$('#copyPublicLink').disabled)return;try{await navigator.clipboard.writeText(value);toast('Gallery link copied.')}catch{$('#publicLink').select();document.execCommand('copy');toast('Gallery link copied.')}}
function loadImage(url){return new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>reject(new Error('An export photo could not be loaded.'));img.src=url})}
function canvasBlob(canvas){return new Promise((resolve,reject)=>canvas.toBlob(blob=>blob?resolve(blob):reject(new Error('The image could not be encoded.')),'image/jpeg',.9))}
function orderedRecords(){return state.records.slice().sort((a,b)=>a.photoDate.localeCompare(b.photoDate)).slice(0,100)}
function orientedImage(img,record){const rotation=photoRotation(record);if(rotation===0)return img;const canvas=document.createElement('canvas'),swap=rotation===90||rotation===270;canvas.width=swap?img.height:img.width;canvas.height=swap?img.width:img.height;const ctx=canvas.getContext('2d');ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(rotation*Math.PI/180);ctx.drawImage(img,-img.width/2,-img.height/2);return canvas}
function drawCover(ctx,img,x,y,w,h,record){const source=orientedImage(img,record),scale=Math.max(w/source.width,h/source.height),dw=source.width*scale,dh=source.height*scale,focus=photoFocus(record);ctx.save();ctx.beginPath();ctx.rect(x,y,w,h);ctx.clip();ctx.drawImage(source,x-(dw-w)*(focus.x/100),y-(dh-h)*(focus.y/100),dw,dh);ctx.restore()}
function drawDayLabel(ctx,record,x,y,w,h,fontSize=20){const day=dayNumber(record.photoDate),label=day?`Day ${day}`:dateLabel(record.photoDate),bar=Math.max(40,Math.round(fontSize*2));ctx.fillStyle='rgba(0,0,0,.72)';ctx.fillRect(x,y+h-bar,w,bar);ctx.fillStyle='#fff';ctx.font=`700 ${fontSize}px system-ui,sans-serif`;ctx.textBaseline='middle';ctx.fillText(label,x+12,y+h-bar/2,w-24)}
async function setExport(blob,type){if(state.exportUrl)URL.revokeObjectURL(state.exportUrl);state.exportBlob=blob;state.exportType=type;state.exportUrl=URL.createObjectURL(blob);$('#exportPreview').src=state.exportUrl;$('#exportTitle').textContent=type==='gif'?'Your daily GIF':'Your collage';$('#exportPreview').alt=type==='gif'?'Animated Baby Daily slideshow':'Generated Baby Daily collage';$('#exportHelp').innerHTML=type==='gif'?'Tap <strong>Share or save</strong> to keep the animated GIF. It includes every available day in order.':'On iPhone, tap <strong>Share or save</strong> and choose Save Image. You can also press and hold the preview.';$('#collageDialog').showModal()}
async function makeCollage(){if(!state.records.length){toast('Add at least one photo first.');return}const button=$('#collageButton');button.disabled=true;button.textContent='Building…';try{const chosen=orderedRecords(),cols=Math.min(10,Math.ceil(Math.sqrt(chosen.length))),rows=Math.ceil(chosen.length/cols),size=360,canvas=document.createElement('canvas');canvas.width=cols*size;canvas.height=rows*size;const ctx=canvas.getContext('2d');ctx.fillStyle='#10182d';ctx.fillRect(0,0,canvas.width,canvas.height);const images=[];for(const record of chosen)images.push(await loadImage(`image.php?id=${encodeURIComponent(record.id)}`));images.forEach((img,i)=>drawCover(ctx,img,(i%cols)*size,Math.floor(i/cols)*size,size,size,chosen[i]));chosen.forEach((record,i)=>drawDayLabel(ctx,record,(i%cols)*size,Math.floor(i/cols)*size,size,size,19));await setExport(await canvasBlob(canvas),'collage');toast('Collage ready to save.')}finally{button.disabled=false;button.textContent='Make collage'}}
function adaptiveGifFrame(data){
    const src=data.data,width=data.width,height=data.height,counts=new Uint32Array(32768),colors=[];
    for(let s=0;s<src.length;s+=4)counts[((src[s]>>3)<<10)|((src[s+1]>>3)<<5)|(src[s+2]>>3)]++;
    for(let color=0;color<counts.length;color++)if(counts[color])colors.push(color);
    const channel=(color,axis)=>axis===0?(color>>10)&31:axis===1?(color>>5)&31:color&31;
    const makeBox=boxColors=>{let total=0,min=[31,31,31],max=[0,0,0];for(const color of boxColors){const count=counts[color];total+=count;for(let axis=0;axis<3;axis++){const value=channel(color,axis);if(value<min[axis])min[axis]=value;if(value>max[axis])max[axis]=value}}return{colors:boxColors,total,min,max}};
    const boxes=[makeBox(colors)];
    while(boxes.length<256){
        let selected=-1,selectedScore=-1;
        for(let i=0;i<boxes.length;i++){const box=boxes[i];if(box.colors.length<2)continue;const range=Math.max(box.max[0]-box.min[0],box.max[1]-box.min[1],box.max[2]-box.min[2]),score=range*box.total;if(score>selectedScore){selected=i;selectedScore=score}}
        if(selected<0)break;
        const box=boxes.splice(selected,1)[0],ranges=box.max.map((value,axis)=>value-box.min[axis]),axis=ranges.indexOf(Math.max(...ranges));
        box.colors.sort((a,b)=>channel(a,axis)-channel(b,axis));
        const midpoint=box.total/2;let sum=0,split=1;
        for(let i=0;i<box.colors.length-1;i++){sum+=counts[box.colors[i]];if(sum>=midpoint){split=i+1;break}}
        boxes.push(makeBox(box.colors.slice(0,split)),makeBox(box.colors.slice(split)));
    }
    const palette=[],paletteColors=[];
    for(const box of boxes){let r=0,g=0,b=0,total=0;for(const color of box.colors){const count=counts[color];total+=count;r+=(((color>>10)&31)*8+4)*count;g+=(((color>>5)&31)*8+4)*count;b+=((color&31)*8+4)*count}const value=[Math.round(r/total),Math.round(g/total),Math.round(b/total)];palette.push(...value);paletteColors.push(value)}
    while(palette.length<768)palette.push(0,0,0);
    const pixels=new Uint8Array(width*height),cache=new Int16Array(32768);cache.fill(-1);
    let current=new Float32Array((width+2)*3),next=new Float32Array((width+2)*3);
    const clamp=value=>Math.max(0,Math.min(255,value));
    for(let y=0,p=0;y<height;y++){
        for(let x=0;x<width;x++,p++){
            const source=p*4,errorOffset=(x+1)*3,r=clamp(src[source]+current[errorOffset]),g=clamp(src[source+1]+current[errorOffset+1]),b=clamp(src[source+2]+current[errorOffset+2]),key=((r>>3)<<10)|((g>>3)<<5)|(b>>3);
            let best=cache[key];
            if(best<0){let distance=Infinity;for(let i=0;i<paletteColors.length;i++){const color=paletteColors[i],dr=r-color[0],dg=g-color[1],db=b-color[2],candidate=dr*dr+dg*dg+db*db;if(candidate<distance){distance=candidate;best=i}}cache[key]=best}
            pixels[p]=best;
            const chosen=paletteColors[best],errors=[r-chosen[0],g-chosen[1],b-chosen[2]];
            for(let axis=0;axis<3;axis++){const error=errors[axis];current[errorOffset+3+axis]+=error*7/16;next[errorOffset-3+axis]+=error*3/16;next[errorOffset+axis]+=error*5/16;next[errorOffset+3+axis]+=error/16}
        }
        const swap=current;current=next;next=swap;next.fill(0);
    }
    return{pixels,palette};
}
function lzwEncode(pixels){const clear=256,end=257;let size=9,next=258,dict=new Map(),out=[],acc=0,bits=0;const write=code=>{acc|=code<<bits;bits+=size;while(bits>=8){out.push(acc&255);acc>>>=8;bits-=8}},reset=()=>{dict=new Map();size=9;next=258};write(clear);let prefix=pixels[0];for(let i=1;i<pixels.length;i++){const value=pixels[i],key=prefix*256+value;if(dict.has(key)){prefix=dict.get(key);continue}write(prefix);if(next<4096){dict.set(key,next++);if(next>(1<<size)&&size<12)size++}else{write(clear);reset()}prefix=value}write(prefix);write(end);if(bits>0)out.push(acc&255);return out}
function pushWord(bytes,value){bytes.push(value&255,(value>>8)&255)}
function pushString(bytes,value){for(let i=0;i<value.length;i++)bytes.push(value.charCodeAt(i))}
function pushBlocks(bytes,data){for(let i=0;i<data.length;i+=255){const block=data.slice(i,i+255);bytes.push(block.length,...block)}bytes.push(0)}
function encodeGif(frames,w,h,delay=100){const bytes=[];pushString(bytes,'GIF89a');pushWord(bytes,w);pushWord(bytes,h);bytes.push(0x70,0,0,0x21,0xff,0x0b);pushString(bytes,'NETSCAPE2.0');bytes.push(3,1,0,0,0);frames.forEach(frame=>{bytes.push(0x21,0xf9,4,8);pushWord(bytes,delay);bytes.push(0,0,0x2c);pushWord(bytes,0);pushWord(bytes,0);pushWord(bytes,w);pushWord(bytes,h);bytes.push(0x87,...frame.palette,8);pushBlocks(bytes,lzwEncode(frame.pixels))});
bytes.push(0x3b);return new Blob([new Uint8Array(bytes)],{type:'image/gif'})}
async function makeGif(){if(!state.records.length){toast('Add at least one photo first.');return}const button=$('#gifButton');button.disabled=true;button.textContent='Building…';try{const chosen=orderedRecords(),size=720,canvas=document.createElement('canvas');canvas.width=size;canvas.height=size;const ctx=canvas.getContext('2d',{willReadFrequently:true}),frames=[];for(let i=0;i<chosen.length;i++){button.textContent=`Building ${i+1}/${chosen.length}…`;const record=chosen[i],img=await loadImage(`image.php?id=${encodeURIComponent(record.id)}`);ctx.fillStyle='#10182d';ctx.fillRect(0,0,size,size);drawCover(ctx,img,0,0,size,size,record);drawDayLabel(ctx,record,0,0,size,size,48);frames.push(adaptiveGifFrame(ctx.getImageData(0,0,size,size)));await new Promise(resolve=>setTimeout(resolve,0))}await setExport(encodeGif(frames,size,size),'gif');toast('High-quality animated GIF ready to save.')}finally{button.disabled=false;button.textContent='Make GIF'}}
function exportFilename(){const ext=state.exportType==='gif'?'gif':'jpg';return`baby-daily-${state.exportType}-${localToday()}.${ext}`}
async function shareExport(){if(!state.exportBlob)return;const file=new File([state.exportBlob],exportFilename(),{type:state.exportBlob.type});
if(navigator.share&&navigator.canShare?.({files:[file]})){try{await navigator.share({files:[file],title:state.exportType==='gif'?'Baby Daily GIF':'Baby Daily collage'});
return}catch(error){if(error.name==='AbortError')return}}downloadExport()}
function downloadExport(){if(!state.exportUrl)return;const link=document.createElement('a');link.href=state.exportUrl;link.download=exportFilename();document.body.append(link);link.click();link.remove();setTimeout(()=>{if(/iPhone|iPad|iPod/i.test(navigator.userAgent))window.open(state.exportUrl,'_blank')},300)}
function clearUploadPreview(){const old=$('#preview img');if(old?.dataset.objectUrl)URL.revokeObjectURL(old.dataset.objectUrl);$('#preview').textContent='Photo preview'}
function resetUploadForm(){const form=$('#uploadForm');clearUploadPreview();form.reset();form.photoDate.value=localToday();form.photoDate.max=localToday()}
function setReminderStatus(message,type=''){const status=$('#reminderStatus');if(!status)return;status.textContent=message;status.className=`reminder-status${type?` ${type}`:''}`}
async function checkReminderStatus(){const button=$('#checkReminderStatus');button.disabled=true;button.textContent='Checking…';try{const status=await api('reminder_status');if(!status.smtpConfigured){setReminderStatus('SMTP is not fully configured on Hostinger. Add the mailbox password in config/config.php, then send a test email.','error');return}if(!status.lastRunAt){setReminderStatus('SMTP is configured, but the scheduled reminder checker has never reported a run. The Hostinger cron job is missing or has not executed.','error');return}const lastRun=new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(status.lastRunAt));const result=status.failed?`${status.failed} delivery attempt${status.failed===1?'':'s'} failed.`:`${status.delivered} reminder${status.delivered===1?'':'s'} sent during that run.`;setReminderStatus(`Last scheduler run: ${lastRun}. ${result}`,(status.state==='finished'&&!status.failed)?'success':'error')}catch(error){setReminderStatus(error.message,'error')}finally{button.disabled=false;button.textContent='Check scheduler status'}}
async function sendReminderTest(){const button=$('#testReminderEmail');button.disabled=true;button.textContent='Sending…';try{const result=await api('test_reminder_email',{method:'POST',headers:{'X-CSRF-Token':window.BABY_TRACKER.csrf}});setReminderStatus(`Test email accepted by the mail server for ${result.recipientCount} recipient${result.recipientCount===1?'':'s'}. Check the inbox and junk folder.`,'success')}catch(error){setReminderStatus(error.message,'error')}finally{button.disabled=false;button.textContent='Send test email'}}
document.addEventListener('DOMContentLoaded',()=>{
if(document.body.dataset.authenticated!=='true')return;const today=localToday();$('#uploadForm').photoDate.value=today;$('#uploadForm').photoDate.max=today;$('#settingsForm').birthDate.max=today;load().catch(e=>toast(e.message));$('#logoutButton').addEventListener('click',async()=>{try{const data=await api('logout',{method:'POST',headers:{'X-CSRF-Token':window.BABY_TRACKER.csrf}});
location.href=data?.portal||window.location.href}catch(e){toast(e.message)}});
$('#openUpload').addEventListener('click',()=>{resetUploadForm();$('#uploadDialog').showModal()});
$('#settingsButton').addEventListener('click',()=>$('#settingsDialog').showModal());$('#openBabyDialog').addEventListener('click',()=>{$('#settingsDialog').close();$('#babyDialog').showModal()});
$$('.close-dialog').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));$$('[data-filter]').forEach(button=>button.addEventListener('click',()=>{$$('[data-filter]').forEach(b=>b.classList.remove('active'));button.classList.add('active');state.filter=button.dataset.filter;render()}));$('#gallery').addEventListener('click',e=>{const button=e.target.closest('[data-date]');if(button&&document.body.dataset.canWrite==='true'){resetUploadForm();$('#uploadForm').photoDate.value=button.dataset.date;$('#uploadDialog').showModal()}});
$('#uploadForm').photo.addEventListener('change',e=>{clearUploadPreview();const file=e.target.files[0];if(!file)return;const url=URL.createObjectURL(file),img=document.createElement('img');img.src=url;img.alt='Selected photo preview';img.dataset.objectUrl=url;$('#preview').replaceChildren(img)});
$('#uploadForm').addEventListener('submit',async e=>{e.preventDefault();const button=e.submitter||$('#uploadForm button[type="submit"]');button.disabled=true;button.textContent='Saving…';try{await api('upload',formOptions(e.currentTarget));$('#uploadDialog').close();resetUploadForm();toast('Daily photo saved.');try{await load()}catch{toast('Photo saved. Refresh to reload the gallery.')}}catch(error){toast(error.message)}finally{button.disabled=false;button.textContent='Save daily photo'}});
$('#settingsForm').addEventListener('submit',async e=>{e.preventDefault();try{state.settings=await api('settings',formOptions(e.currentTarget));applyTheme(state.settings);updatePublicSharing(state.settings);$('#settingsDialog').close();render();toast('Settings saved.')}catch(error){toast(error.message)}});
$('#testReminderEmail').addEventListener('click',sendReminderTest);$('#checkReminderStatus').addEventListener('click',checkReminderStatus);
$$('#settingsForm input[name="visibility"]').forEach(choice=>choice.addEventListener('change',e=>updatePublicSharing({...state.settings,visibility:e.target.value})));
$('#copyPublicLink').addEventListener('click',()=>copyPublicLink());$('#openPublicLink').addEventListener('click',()=>{const value=$('#publicLink').value;if(value&&!$('#openPublicLink').disabled)window.open(value,'_blank','noopener')});
$('#babyForm').addEventListener('submit',async e=>{e.preventDefault();const button=e.submitter;button.disabled=true;button.textContent='Creating…';try{await api('add_baby',formOptions(e.currentTarget));location.reload()}catch(error){toast(error.message);button.disabled=false;button.textContent='Create and open gallery'}});
$('#collageButton').addEventListener('click',()=>makeCollage().catch(error=>toast(error.message||'The collage could not be created.')));$('#gifButton').addEventListener('click',()=>makeGif().catch(error=>toast(error.message||'The GIF could not be created.')));$('#shareExportButton').addEventListener('click',()=>shareExport().catch(()=>toast('The file could not be shared.')));$('#downloadExportButton').addEventListener('click',downloadExport);
if(window.BABY_TRACKER.openUpload){resetUploadForm();if(/^\d{4}-\d{2}-\d{2}$/.test(window.BABY_TRACKER.uploadDate||''))$('#uploadForm').photoDate.value=window.BABY_TRACKER.uploadDate;$('#uploadDialog').showModal()}});


document.addEventListener('DOMContentLoaded',()=>{
    if(document.body.dataset.authenticated!=='true')return;
    const selector=$('#babySelector');
    if(selector)selector.addEventListener('change',async()=>{
        selector.disabled=true;
        const body=new FormData();body.set('babyId',selector.value);
        try{await api('select_baby',{method:'POST',headers:{'X-CSRF-Token':window.BABY_TRACKER.csrf},body});
location.reload()}
        catch(error){selector.disabled=false;toast(error.message)}
    });

    const focusX=$('#focusX'),focusY=$('#focusY'),focusPreview=$('#focusPreview');
    if(focusX&&focusY&&focusPreview){
        focusX.addEventListener('input',updateFocusPreview);focusY.addEventListener('input',updateFocusPreview);
        $('#centerFocusButton').addEventListener('click',()=>{focusX.value=50;focusY.value=50;updateFocusPreview()});
        const rotate=change=>{focusPreview.dataset.rotation=(photoRotation({rotation:focusPreview.dataset.rotation})+change+360)%360;updateFocusPreview()};
        $('#rotateCounterclockwiseButton').addEventListener('click',()=>rotate(-90));
        $('#rotateClockwiseButton').addEventListener('click',()=>rotate(90));
        $('#saveFocusButton').addEventListener('click',async event=>{
            const button=event.currentTarget,id=focusPreview.dataset.id;if(!id)return;
            button.disabled=true;button.textContent='Saving…';const body=new FormData();body.set('id',id);body.set('focusX',focusX.value);body.set('focusY',focusY.value);body.set('rotation',focusPreview.dataset.rotation||0);
            try{const updated=await api('update_photo_focus',{method:'POST',headers:{'X-CSRF-Token':window.BABY_TRACKER.csrf},body});const index=state.records.findIndex(record=>record.id===id);if(index!==-1)state.records[index]=updated;$('#fullPhoto').style.transform=`rotate(${photoRotation(updated)}deg)`;render();toast('Picture crop and rotation saved.')}catch(error){toast(error.message)}finally{button.disabled=false;button.textContent='Save crop & rotation'}
        });
    }

    $('#deletePhotoButton').addEventListener('click',async e=>{
        const button=e.currentTarget,id=button.dataset.id;
        if(!id||!window.confirm('Remove this photo? This cannot be undone.'))return;
        button.disabled=true;
        const body=new FormData();body.set('id',id);
        try{
            await api('delete_photo',{method:'POST',headers:{'X-CSRF-Token':window.BABY_TRACKER.csrf},body});

            $('#photoDialog').close();$('#fullPhoto').removeAttribute('src');$('#fullPhoto').style.removeProperty('transform');if(focusPreview){focusPreview.removeAttribute('src');delete focusPreview.dataset.rotation}button.dataset.id='';
            await load();toast('Photo removed.');
        }catch(error){toast(error.message)}finally{button.disabled=false}
    });

});


document.addEventListener('DOMContentLoaded',()=>{
    const dialog=$('#usersDialog');
    if(!dialog)return;
    const galleryChoices=(babies,selected=[])=>Object.entries(babies).map(([id,baby])=>`<label class="access-choice"><input type="checkbox" name="babyIds[]" value="${escapeHtml(id)}" ${selected.includes(id)?'checked':''}> ${escapeHtml(baby.name||'Baby')}</label>`).join('');
    async function loadUsers(){
        const data=await api('users');
        $('#userList').innerHTML=data.users.map(user=>['admin','super_admin'].includes(user.role)
            ? `<div class="user-row"><strong>${escapeHtml(user.name||user.username)}</strong><small>${escapeHtml(user.username)} · central admin · all galleries</small></div>`
            : `<form class="user-row access-form"><strong>${escapeHtml(user.name||user.username)}</strong><small>${escapeHtml(user.username)} · ${escapeHtml(user.role)}</small><input type="hidden" name="userId" value="${escapeHtml(user.id)}"><fieldset><legend>Gallery access</legend>${galleryChoices(data.babies,user.babyIds)}</fieldset><button type="submit" class="secondary compact">Save access</button></form>`).join('');
    }
    $('#openUsersDialog').addEventListener('click',async()=>{
        $('#settingsDialog').close();
        try{await loadUsers();dialog.showModal()}catch(error){toast(error.message)}
    });

    $('#userList').addEventListener('submit',async event=>{
        if(!event.target.matches('.access-form'))return;
        event.preventDefault();
        try{await api('update_user_access',formOptions(event.target));await loadUsers();toast('Gallery access updated.')}catch(error){toast(error.message)}
    });

});







