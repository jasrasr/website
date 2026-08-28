<?php
// ============================================================================
// File: station_api.php
// Purpose: Manage saved fuel-station profiles and find nearby fuel stations
// Revision: 1.1.0
// Author: Jason Lamb
// ============================================================================

header('Content-Type: application/json');
$stationsFile = __DIR__ . '/stations.json';

function respond($payload, $status = 200){http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_SLASHES);exit;}
function loadStationData($file){$data=['brands'=>[],'locations'=>[]];if(file_exists($file)){$d=json_decode(file_get_contents($file),true);if(is_array($d))$data=array_merge($data,$d);}if(!is_array($data['brands']??null))$data['brands']=[];if(!is_array($data['locations']??null))$data['locations']=[];return$data;}
function saveStationData($file,$data){file_put_contents($file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function clean($value,$max=160){$v=trim((string)$value);return function_exists('mb_substr')?mb_substr($v,0,$max):substr($v,0,$max);}
function distanceMeters($lat1,$lon1,$lat2,$lon2){$earth=6371000;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=deg2rad($lat2-$lat1);$dl=deg2rad($lon2-$lon1);$a=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return$earth*2*atan2(sqrt($a),sqrt(1-$a));}

$method=$_SERVER['REQUEST_METHOD']??'GET';$action=$_GET['action']??$_POST['action']??'list';$data=loadStationData($stationsFile);

if($method==='GET'&&$action==='list')respond(['success'=>true,'brands'=>array_values($data['brands']),'locations'=>array_values($data['locations'])]);

if($method==='GET'&&$action==='nearby'){
    $lat=isset($_GET['lat'])?(float)$_GET['lat']:null;$lon=isset($_GET['lon'])?(float)$_GET['lon']:null;$radius=max(250,min(3000,(int)($_GET['radius']??1500)));
    if($lat===null||$lon===null||$lat<-90||$lat>90||$lon<-180||$lon>180)respond(['error'=>'Valid latitude and longitude are required.'],400);
    $query='[out:json][timeout:12];('.'node["amenity"="fuel"](around:'.$radius.','.$lat.','.$lon.');'.'way["amenity"="fuel"](around:'.$radius.','.$lat.','.$lon.');'.'relation["amenity"="fuel"](around:'.$radius.','.$lat.','.$lon.');'.');out center tags 20;';
    $ch=curl_init('https://overpass-api.de/api/interpreter?data='.rawurlencode($query));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['User-Agent: jasr.me MPG Tracker/1.1']]);$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($raw===false||$http>=400)respond(['error'=>'Nearby station lookup failed.','detail'=>$err?:'HTTP '.$http],502);
    $osm=json_decode($raw,true);if(!is_array($osm)||!isset($osm['elements']))respond(['error'=>'Nearby station lookup returned invalid data.'],502);
    $results=[];foreach($osm['elements'] as $el){$tags=$el['tags']??[];$eLat=$el['lat']??$el['center']['lat']??null;$eLon=$el['lon']??$el['center']['lon']??null;if($eLat===null||$eLon===null)continue;$brand=clean($tags['brand']??$tags['name']??$tags['operator']??'Fuel Station',80);$name=clean($tags['name']??$brand,120);$city=clean($tags['addr:city']??$tags['addr:town']??$tags['addr:village']??'',100);$street=clean(trim(($tags['addr:housenumber']??'').' '.($tags['addr:street']??'')),140);$results[]=['candidate_id'=>($el['type']??'osm').':'.($el['id']??uniqid()),'brand'=>$brand,'name'=>$name,'city'=>$city,'street'=>$street,'latitude'=>round((float)$eLat,6),'longitude'=>round((float)$eLon,6),'distance_meters'=>round(distanceMeters($lat,$lon,(float)$eLat,(float)$eLon)),'source'=>'openstreetmap'];}
    usort($results,fn($a,$b)=>$a['distance_meters']<=>$b['distance_meters']);respond(['success'=>true,'results'=>array_slice($results,0,8)]);
}

if($method==='POST'&&$action==='save_profile'){
    $profileId=clean($_POST['profile_id']??'',120);$brand=clean($_POST['brand']??'',80);$name=clean($_POST['name']??$brand,120);$city=clean($_POST['city']??'',100);$street=clean($_POST['street']??'',140);$intersection=clean($_POST['intersection']??'',140);$nickname=clean($_POST['nickname']??'',100);$lat=($_POST['latitude']??'')!==''?(float)$_POST['latitude']:null;$lon=($_POST['longitude']??'')!==''?(float)$_POST['longitude']:null;$source=clean($_POST['source']??'manual',40);$candidateId=clean($_POST['candidate_id']??'',120);
    if($lat!==null&&($lat<-90||$lat>90))$lat=null;if($lon!==null&&($lon<-180||$lon>180))$lon=null;if($brand===''&&$name==='')respond(['error'=>'Station brand or name is required.'],400);
    if($brand!==''){$found=false;foreach($data['brands'] as $existing){if(strcasecmp(trim((string)$existing),$brand)===0){$brand=trim((string)$existing);$found=true;break;}}if(!$found)$data['brands'][]=$brand;}
    $matchIndex=null;foreach($data['locations'] as $i=>$loc){if($profileId!==''&&($loc['id']??'')===$profileId){$matchIndex=$i;break;}if($candidateId!==''&&($loc['candidate_id']??'')===$candidateId){$matchIndex=$i;break;}if($lat!==null&&$lon!==null&&isset($loc['latitude'],$loc['longitude'])&&distanceMeters($lat,$lon,(float)$loc['latitude'],(float)$loc['longitude'])<40&&strcasecmp((string)($loc['brand']??''),$brand)===0){$matchIndex=$i;break;}}
    $id=$matchIndex!==null?($data['locations'][$matchIndex]['id']??''):'';if($id==='')$id='station_'.bin2hex(random_bytes(6));$profile=['id'=>$id,'brand'=>$brand,'name'=>$name,'city'=>$city,'street'=>$street,'intersection'=>$intersection,'nickname'=>$nickname,'latitude'=>$lat,'longitude'=>$lon,'source'=>$source,'candidate_id'=>$candidateId,'updated_et'=>(new DateTime('now',new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s T')];if($matchIndex!==null){$profile=array_merge($data['locations'][$matchIndex],$profile);$data['locations'][$matchIndex]=$profile;}else$data['locations'][]=$profile;natcasesort($data['brands']);$data['brands']=array_values(array_unique($data['brands']));saveStationData($stationsFile,$data);respond(['success'=>true,'profile'=>$profile]);
}

if($method==='POST'&&$action==='delete_profile'){
    $id=clean($_POST['profile_id']??'',120);if($id==='')respond(['error'=>'profile_id is required.'],400);$before=count($data['locations']);$data['locations']=array_values(array_filter($data['locations'],fn($loc)=>($loc['id']??'')!==$id));if(count($data['locations'])===$before)respond(['error'=>'Station profile not found.'],404);saveStationData($stationsFile,$data);respond(['success'=>true]);
}

respond(['error'=>'Unsupported action.'],400);
