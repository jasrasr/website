<?php
// ============================================================================
// File: fuel_form.php
// Purpose: Manual fuel entry form with fill, station, and location assistance
// Revision: 2.7
// Author: Jason Lamb
//
// Revision Notes:
// 2.7 - Add saved station profiles, GPS nearby-station lookup, and data-driven
//       partial-fill prompting based on historical median fill volume.
// 2.6 - Add Full/Partial selector, comments, learned station brands, and GPS.
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/device_init.php';

$logDir = __DIR__ . '/logs/';
$knownPlates = [];
if (is_dir($logDir)) {
    foreach (glob($logDir . '*.json') as $file) {
        $p = basename($file, '.json');
        if ($p !== '') $knownPlates[] = $p;
    }
}
$knownPlates = array_values(array_unique($knownPlates));
sort($knownPlates);

$stationBrands = [];
$stationLocations = [];
$stationsFile = __DIR__ . '/stations.json';
if (file_exists($stationsFile)) {
    $stationData = json_decode(file_get_contents($stationsFile), true);
    if (is_array($stationData)) {
        if (is_array($stationData['brands'] ?? null)) $stationBrands = array_values(array_filter(array_map('trim', $stationData['brands'])));
        if (is_array($stationData['locations'] ?? null)) $stationLocations = array_values($stationData['locations']);
    }
}
natcasesort($stationBrands);
$stationBrands = array_values($stationBrands);

$canUseDropdown = $isIPWhitelisted || $isDeviceTrusted;
$activePlate = $_SESSION['active_plate'] ?? $defaultPlate;
if ($activePlate && empty($_SESSION['active_plate'])) $_SESSION['active_plate'] = $activePlate;
$today = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');

$prefill = [
    'odometer' => isset($_GET['odometer']) ? (float)$_GET['odometer'] : null,
    'pricePerGallon' => isset($_GET['pricePerGallon']) ? (float)$_GET['pricePerGallon'] : null,
    'totalPrice' => isset($_GET['totalCost']) ? (float)$_GET['totalCost'] : null,
    'gallons' => isset($_GET['gallons']) ? (float)$_GET['gallons'] : null,
];

function stationLabel($loc) {
    $brand = trim((string)($loc['brand'] ?? $loc['name'] ?? 'Station'));
    $place = trim((string)($loc['nickname'] ?? $loc['street'] ?? $loc['intersection'] ?? ''));
    $city = trim((string)($loc['city'] ?? ''));
    return implode(' — ', array_values(array_filter([$brand, trim($place . ($city ? ', ' . $city : ''))])));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fuel Entry</title>
<style>
body{font-family:sans-serif;max-width:900px;margin:auto;padding:2rem 1rem;}label{display:block;margin-top:.8rem;}input,select,textarea{width:100%;max-width:420px;padding:.45rem;margin-top:.2rem;box-sizing:border-box;}textarea{min-height:80px;resize:vertical;}button{margin-top:.7rem;padding:.5rem .9rem}.note{color:#666;font-size:.88rem;margin-top:.25rem;max-width:560px}.calculated{background:#f0f0f0}.clear-btn{padding:.35rem .6rem;border:1px solid #ccc;background:#f8f8f8;border-radius:4px}.radio-row{display:flex;gap:1.2rem;align-items:center;margin-top:.35rem;flex-wrap:wrap}.radio-row label{display:flex;align-items:center;gap:.35rem;margin:0}.radio-row input{width:auto;margin:0}.inline-row{display:flex;gap:6px;align-items:center;max-width:480px}.section{margin-top:1.15rem;padding-top:.2rem}.button-row{display:flex;gap:.5rem;flex-wrap:wrap}.button-row button{margin-top:.45rem}#otherStationWrap,#nearbyWrap{display:none}#locationStatus{font-size:.85rem;color:#666;margin-top:.45rem}.warning{background:#fff3cd;color:#664d03;padding:.6rem;border-radius:6px;max-width:520px;margin-top:.5rem;display:none}
</style>
</head>
<body>
<h2>Fuel Entry</h2>
<div id="formError" style="display:none;background:#f8d7da;color:#721c24;padding:.7rem 1rem;border-radius:7px;margin-bottom:1rem;font-size:.9rem;max-width:420px;"></div>
<div id="successCard" style="display:none;background:white;border-radius:10px;padding:1.2rem;margin-bottom:1rem;box-shadow:0 1px 4px rgba(0,0,0,.1);border-left:5px solid #28a745;max-width:440px;">
<h3 style="margin:0 0 .6rem;color:#28a745;">✅ Entry Saved!</h3><div id="successDetails" style="font-size:.9rem;line-height:1.8;"></div>
<div style="margin-top:1rem;display:flex;gap:.7rem;flex-wrap:wrap;"><a href="fuel_form.php" style="background:#007bff;color:white;padding:.5rem 1rem;border-radius:6px;text-decoration:none;">+ New Entry</a><a id="viewLatestLink" href="#" style="background:#6c757d;color:white;padding:.5rem 1rem;border-radius:6px;text-decoration:none;">🔍 View Entry</a></div></div>
<p><a href="scan_photos.php" style="background:#007bff;color:white;padding:.4rem .9rem;border-radius:6px;text-decoration:none;">📷 Scan Photos Instead</a></p>
<form id="fuelForm">
<?php if ($canUseDropdown && !empty($knownPlates)): ?>
<label for="plateDropdown">Select a License Plate:</label><select id="plateDropdown" name="plateDropdown"><option value="">-- Select Plate --</option><?php foreach ($knownPlates as $p): $isDefault=$defaultPlate&&strtoupper($p)===strtoupper($defaultPlate); ?><option value="<?=htmlspecialchars($p)?>" <?=$isDefault?'selected':''?>><?=htmlspecialchars($isDefault?"$p (default)":$p)?></option><?php endforeach; ?></select>
<?php endif; ?>
<label for="licensePlate">License Plate</label><input type="text" id="licensePlate" name="licensePlate" value="<?=htmlspecialchars($activePlate??'')?>" placeholder="Enter plate if not using dropdown">
<label>Date</label><input type="date" name="date" value="<?=$today?>">
<label>Odometer Reading</label><input type="number" name="odometer" step="0.1" min="0" value="<?=$prefill['odometer']!==null?$prefill['odometer']:''?>">
<label>Price per Gallon ($)</label><div class="inline-row"><input type="number" id="price" name="pricePerGallon" step="0.001" min="0" value="<?=$prefill['pricePerGallon']!==null?$prefill['pricePerGallon']:''?>"><button type="button" class="clear-btn" data-clear="price">✖</button></div>
<div class="note">Two decimals such as 3.69 are stored as 3.699. Three-decimal pump prices are kept as entered.</div>
<label>Total Price ($)</label><div class="inline-row"><input type="number" id="total" name="totalPrice" step="0.01" min="0" value="<?=$prefill['totalPrice']!==null?$prefill['totalPrice']:''?>"><button type="button" class="clear-btn" data-clear="total">✖</button></div>
<label>Total Gallons</label><div class="inline-row"><input type="number" id="gallons" name="gallons" step="0.001" min="0" value="<?=$prefill['gallons']!==null?$prefill['gallons']:''?>"><button type="button" class="clear-btn" data-clear="gallons">✖</button></div>
<div class="note">Enter any two of Price, Total, and Gallons. The third is calculated.</div><div id="partialHint" class="warning"></div>
<div class="section"><label>Fill Type</label><div class="radio-row"><label><input type="radio" name="fillType" value="full" checked> Full fill-up</label><label><input type="radio" name="fillType" value="partial"> Partial fill-up</label></div><div class="note">Partial fills stay in history. MPG waits until the next full fill closes the full-to-full interval.</div></div>
<div class="section"><label for="stationBrand">Station Brand (optional)</label><select id="stationBrand" name="stationBrand"><option value="">-- Not specified --</option><?php foreach($stationBrands as $brand):?><option value="<?=htmlspecialchars($brand)?>"><?=htmlspecialchars($brand)?></option><?php endforeach;?><option value="other">Other / Add new…</option></select><div id="otherStationWrap"><label for="stationBrandOther">New Station Brand</label><input type="text" id="stationBrandOther" name="stationBrandOther" maxlength="80" placeholder="e.g. BP"></div></div>
<div class="section"><label for="savedStation">Saved Station (optional)</label><select id="savedStation"><option value="">-- Select a saved station --</option><?php foreach($stationLocations as $loc): if(empty($loc['id']))continue;?><option value="<?=htmlspecialchars($loc['id'])?>"><?=htmlspecialchars(stationLabel($loc))?></option><?php endforeach;?></select><div class="note">Use this when entering a fill later or when you already know the exact station.</div></div>
<div class="section"><label>Location / Nearby Station</label><div class="button-row"><button type="button" id="gpsBtn">📍 Capture GPS</button><button type="button" id="nearbyBtn">⛽ Find Nearby Stations</button><button type="button" id="clearGpsBtn" style="display:none;">Clear</button></div><div id="locationStatus">No location attached.</div><div id="nearbyWrap"><label for="nearbyStation">Nearby stations</label><select id="nearbyStation"><option value="">-- Confirm the station --</option></select></div><input type="hidden" id="stationLocationId" name="stationLocationId"><input type="hidden" id="latitude" name="latitude"><input type="hidden" id="longitude" name="longitude"><input type="hidden" id="locationSource" name="locationSource"><div class="note">GPS suggests nearby stations. You confirm the exact station; the confirmed profile is saved for future use.</div></div>
<div class="section"><label for="comment">Comment (optional)</label><textarea id="comment" name="comment" maxlength="500" placeholder="Towing, road trip, winter blend, pump issue, etc."></textarea></div>
<button type="submit" id="submitBtn">Save Entry</button>
</form>
<?php include 'menu.php'; ?>
<div style="margin-top:2rem;padding-top:.5rem;border-top:1px solid #ddd;color:#aaa;font-size:.75rem;text-align:center;">fuel_form.php — Rev 2.7 — Updated: <?php $mt=new DateTime('@'.filemtime(__FILE__));$mt->setTimezone(new DateTimeZone('America/New_York'));echo $mt->format('Y-m-d H:i (g:i A T)');?></div>
<script>
const price=document.getElementById('price'),total=document.getElementById('total'),gallons=document.getElementById('gallons');
const stationProfiles=<?=json_encode($stationLocations,JSON_UNESCAPED_SLASHES)?>;
let nearbyCandidates=[];let lastPromptGallons=null;
function num(v){return v===''?null:parseFloat(v)}function resetCalc(el){el.readOnly=false;el.classList.remove('calculated')}function setCalc(el,val,dec){el.value=val.toFixed(dec);el.readOnly=true;el.classList.add('calculated')}
function normalizedPrice(p){if(p===null)return null;const raw=price.value.trim();const decimals=raw.includes('.')?raw.split('.')[1].length:0;return decimals<=2?p+.009:p}
function calculate(){const p=num(price.value),t=num(total.value),g=num(gallons.value);[price,total,gallons].forEach(resetCalc);const filled=[p,t,g].filter(v=>v!==null).length;if(filled!==2)return;const np=normalizedPrice(p);if(p!==null&&g!==null)setCalc(total,np*g,2);else if(p!==null&&t!==null&&np>0)setCalc(gallons,t/np,3);else if(g!==null&&t!==null&&g>0)setCalc(price,t/g,3)}
[price,total,gallons].forEach(el=>el.addEventListener('input',calculate));calculate();
function normalizePlateInput(v){return v.trim().toUpperCase().replace(/[^A-Z0-9]/g,'')}
function currentPlate(){return normalizePlateInput(document.getElementById('licensePlate').value||document.getElementById('plateDropdown')?.value||'')}
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
const stationBrand=document.getElementById('stationBrand'),otherWrap=document.getElementById('otherStationWrap');stationBrand.addEventListener('change',()=>otherWrap.style.display=stationBrand.value==='other'?'block':'none');
function setBrand(brand){if(!brand)return;const match=[...stationBrand.options].find(o=>o.value.toLowerCase()===String(brand).toLowerCase());if(match){stationBrand.value=match.value;otherWrap.style.display='none'}else{stationBrand.value='other';document.getElementById('stationBrandOther').value=brand;otherWrap.style.display='block'}}
const lat=document.getElementById('latitude'),lon=document.getElementById('longitude'),locSource=document.getElementById('locationSource'),locStatus=document.getElementById('locationStatus'),savedStation=document.getElementById('savedStation'),stationLocationId=document.getElementById('stationLocationId'),nearbyWrap=document.getElementById('nearbyWrap'),nearbySelect=document.getElementById('nearbyStation'),clearGpsBtn=document.getElementById('clearGpsBtn');
function applyProfile(p){if(!p)return;stationLocationId.value=p.id||'';setBrand(p.brand||p.name||'');if(p.latitude!=null)lat.value=Number(p.latitude).toFixed(6);if(p.longitude!=null)lon.value=Number(p.longitude).toFixed(6);locSource.value='saved_station';const place=p.nickname||p.street||p.intersection||'';locStatus.textContent=`Selected ${p.brand||p.name||'station'}${place?' — '+place:''}${p.city?', '+p.city:''}`;clearGpsBtn.style.display='inline-block'}
savedStation.addEventListener('change',()=>{const p=stationProfiles.find(x=>x.id===savedStation.value);if(p)applyProfile(p)});
function captureGps(){return new Promise((resolve,reject)=>{if(!navigator.geolocation)return reject(new Error('Geolocation is not supported.'));navigator.geolocation.getCurrentPosition(pos=>{lat.value=pos.coords.latitude.toFixed(6);lon.value=pos.coords.longitude.toFixed(6);locSource.value='gps';stationLocationId.value='';locStatus.textContent=`GPS captured (${lat.value}, ${lon.value})`;clearGpsBtn.style.display='inline-block';resolve()},err=>reject(err),{enableHighAccuracy:true,timeout:10000,maximumAge:60000})})}
document.getElementById('gpsBtn').addEventListener('click',async()=>{try{locStatus.textContent='Getting current location…';await captureGps()}catch(e){locStatus.textContent='Location not captured: '+e.message}});
document.getElementById('nearbyBtn').addEventListener('click',async()=>{try{if(!lat.value||!lon.value){locStatus.textContent='Getting location for nearby-station lookup…';await captureGps()}locStatus.textContent='Finding nearby stations…';const r=await fetch(`station_api.php?action=nearby&lat=${encodeURIComponent(lat.value)}&lon=${encodeURIComponent(lon.value)}&radius=1500`);const d=await r.json();if(d.error)throw new Error(d.error);nearbyCandidates=d.results||[];nearbySelect.innerHTML='<option value="">-- Confirm the station --</option>';nearbyCandidates.forEach((s,i)=>{const o=document.createElement('option');o.value=String(i);const place=s.street||s.city||'';o.textContent=`${s.brand||s.name}${place?' — '+place:''} (${s.distance_meters} m)`;nearbySelect.appendChild(o)});nearbyWrap.style.display='block';locStatus.textContent=nearbyCandidates.length?'Select the exact station below.':'No nearby fuel stations found.'}catch(e){locStatus.textContent='Nearby lookup failed: '+e.message}});
nearbySelect.addEventListener('change',async()=>{if(nearbySelect.value==='')return;const s=nearbyCandidates[Number(nearbySelect.value)];if(!s)return;locStatus.textContent='Saving confirmed station…';const body=new URLSearchParams({action:'save_profile',candidate_id:s.candidate_id||'',brand:s.brand||'',name:s.name||'',city:s.city||'',street:s.street||'',latitude:s.latitude,longitude:s.longitude,source:'gps_confirmed'});try{const r=await fetch('station_api.php',{method:'POST',body});const d=await r.json();if(d.error)throw new Error(d.error);const p=d.profile;stationProfiles.push(p);const o=document.createElement('option');o.value=p.id;o.textContent=`${p.brand||p.name}${p.street?' — '+p.street:''}${p.city?', '+p.city:''}`;savedStation.appendChild(o);savedStation.value=p.id;applyProfile(p);locSource.value='gps_confirmed'}catch(e){locStatus.textContent='Could not save station: '+e.message}});
clearGpsBtn.addEventListener('click',()=>{lat.value='';lon.value='';locSource.value='';stationLocationId.value='';savedStation.value='';nearbySelect.value='';nearbyWrap.style.display='none';locStatus.textContent='No location attached.';clearGpsBtn.style.display='none'});
async function maybePromptPartial(){const g=num(gallons.value),plate=currentPlate();if(!g||!plate||g===lastPromptGallons)return;if(document.querySelector('input[name="fillType"]:checked')?.value==='partial')return;try{const r=await fetch(`fill_baseline.php?plate=${encodeURIComponent(plate)}`);const d=await r.json();if(!d.ready||!d.promptBelowGallons||g>=d.promptBelowGallons)return;lastPromptGallons=g;const hint=document.getElementById('partialHint');hint.style.display='block';hint.textContent=`This fill (${g.toFixed(3)} gal) is unusually small versus your historical median (${Number(d.medianGallons).toFixed(3)} gal).`;if(confirm(hint.textContent+' Was this a partial fill-up?'))document.querySelector('input[name="fillType"][value="partial"]').checked=true}catch(e){}}
gallons.addEventListener('change',maybePromptPartial);
document.getElementById('fuelForm').addEventListener('submit',async e=>{e.preventDefault();const submitBtn=document.getElementById('submitBtn');submitBtn.disabled=true;submitBtn.textContent='Saving…';document.getElementById('formError').style.display='none';const f=e.target,plateDropdown=normalizePlateInput(f.plateDropdown?.value??''),licensePlate=normalizePlateInput(f.licensePlate?.value??'');if(f.licensePlate)f.licensePlate.value=licensePlate;const body=new URLSearchParams({plateDropdown,licensePlate,date:f.date?.value??'',odometer:f.odometer?.value??'',pricePerGallon:f.pricePerGallon?.value??'',totalPrice:f.totalPrice?.value??'',gallons:f.gallons?.value??'',fillType:f.fillType?.value??'full',stationBrand:f.stationBrand?.value??'',stationBrandOther:f.stationBrandOther?.value??'',stationLocationId:f.stationLocationId?.value??'',comment:f.comment?.value??'',latitude:f.latitude?.value??'',longitude:f.longitude?.value??'',locationSource:f.locationSource?.value??'',source:'manual'});try{const r=await fetch('auto_save.php',{method:'POST',body});const d=await r.json();if(d.error)showError(d.error);else{const station=d.stationBrand?`<br><b>Station:</b> ${esc(d.stationBrand)}${d.stationLocationLabel?' — '+esc(d.stationLocationLabel):''}`:'';const comment=d.comment?`<br><b>Comment:</b> ${esc(d.comment)}`:'';document.getElementById('successDetails').innerHTML=`<b>Plate:</b> ${esc(d.plate)}<br><b>Date:</b> ${esc(d.date)}<br><b>Odometer:</b> ${d.odometer}<br><b>Miles driven:</b> ${d.miles}<br><b>Gallons:</b> ${d.gallons}<br><b>Price/gal:</b> $${d.price}<br><b>Total:</b> $${d.total}<br><b>Fill type:</b> ${d.fillType==='partial'?'Partial':'Full'}<br><b>MPG:</b> ${esc(d.mpgDisplay)}${station}${comment}<br><b>Submitted:</b> ${esc(d.submitted)}`;document.getElementById('viewLatestLink').href=`view_latest.php?plate=${encodeURIComponent(d.plate)}`;document.getElementById('successCard').style.display='block';f.style.display='none';document.getElementById('successCard').scrollIntoView({behavior:'smooth'})}}catch(err){showError('Save failed: '+err.message)}finally{submitBtn.disabled=false;submitBtn.textContent='Save Entry'}});
function showError(msg){const el=document.getElementById('formError');el.textContent='⚠️ '+msg;el.style.display='block';el.scrollIntoView({behavior:'smooth'})}
document.querySelectorAll('.clear-btn').forEach(btn=>btn.addEventListener('click',()=>{const el=document.getElementById(btn.dataset.clear);el.value='';resetCalc(el);[price,total,gallons].forEach(resetCalc);calculate()}));
</script>
</body>
</html>
