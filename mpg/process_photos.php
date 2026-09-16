<?php
// ============================================================================
// File: process_photos.php
// Purpose: Accept fuel-stop images with OpenAI vision + OCR.space fallback
// Revision: 2.2
// Author: Jason Lamb
//
// Revision Notes:
// 2.2 - Add OCR.space fallback when OpenAI is unavailable or fails.
// 2.1 - Add station sign/logo recognition and EXIF GPS extraction.
// ============================================================================
header('Content-Type: application/json');
$env = @parse_ini_file(__DIR__ . '/.env') ?: [];
$apiKey = trim((string)($env['OPENAI_API_KEY'] ?? ''));
$ocrKey = trim((string)($env['OCRSPACE_API_KEY'] ?? ''));

function imageToBase64Jpeg($tmpPath,$mimeType,$maxDim=1200){
    $isHeic=in_array(strtolower($mimeType),['image/heic','image/heif'],true);
    if($isHeic){if(extension_loaded('imagick')){try{$im=new Imagick($tmpPath);$im->setImageFormat('jpeg');$im->setImageCompressionQuality(88);$d=$im->getImageBlob();$im->clear();return base64_encode($d);}catch(Exception $e){}}return base64_encode(file_get_contents($tmpPath));}
    if(!function_exists('imagecreatefromjpeg'))return base64_encode(file_get_contents($tmpPath));
    switch(strtolower($mimeType)){case'image/jpeg':$src=@imagecreatefromjpeg($tmpPath);break;case'image/png':$src=@imagecreatefrompng($tmpPath);break;case'image/webp':$src=@imagecreatefromwebp($tmpPath);break;default:$src=false;}
    if(!$src)return base64_encode(file_get_contents($tmpPath));$w=imagesx($src);$h=imagesy($src);
    if($w>$maxDim||$h>$maxDim){if($w>=$h){$nw=$maxDim;$nh=(int)round($h*$maxDim/$w);}else{$nh=$maxDim;$nw=(int)round($w*$maxDim/$h);}$dst=imagecreatetruecolor($nw,$nh);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);imagedestroy($src);$src=$dst;}
    ob_start();imagejpeg($src,null,88);$d=ob_get_clean();imagedestroy($src);return base64_encode($d);
}
function callVision($key,$b64,$prompt){
    if($key==='')return ['ok'=>false,'error'=>'OpenAI API key not configured'];
    $payload=json_encode(['model'=>'gpt-4o-mini','max_tokens'=>250,'messages'=>[['role'=>'user','content'=>[['type'=>'image_url','image_url'=>['url'=>'data:image/jpeg;base64,'.$b64,'detail'=>'high']],['type'=>'text','text'=>$prompt]]]]]);
    $ch=curl_init('https://api.openai.com/v1/chat/completions');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key]]);$response=curl_exec($ch);$curlError=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($response===false||$curlError!=='')return ['ok'=>false,'error'=>'OpenAI connection error'];$data=json_decode($response,true);if($status<200||$status>=300)return ['ok'=>false,'error'=>$data['error']['message']??('OpenAI HTTP '.$status),'status'=>$status];$text=trim((string)($data['choices'][0]['message']['content']??''));return $text!==''?['ok'=>true,'text'=>$text]:['ok'=>false,'error'=>'OpenAI returned no readable result'];
}
function extractOpenAI($key,$tmp,$mime){
    $prompt='You are analyzing one photo taken during a vehicle fuel stop. Classify it as ODOMETER, PRICE, PUMP, STATION, or OTHER and extract only clearly visible information. For PRICE convert 9/10 to the third decimal (3.69 9/10 = 3.699). Return ONLY JSON: {"type":"odometer","odometer":84824.8}, {"type":"price","pricePerGallon":3.699}, {"type":"pump","totalCost":42.76,"gallons":12.290}, {"type":"station","stationBrand":"Speedway"}, or {"type":"other"}.';
    $r=callVision($key,imageToBase64Jpeg($tmp,$mime),$prompt);if(!$r['ok'])return $r;preg_match('/\{[^}]+\}/',$r['text'],$m);if(empty($m[0]))return ['ok'=>false,'error'=>'OpenAI response was not valid extraction JSON'];$d=json_decode($m[0],true);return is_array($d)?['ok'=>true,'data'=>$d]:['ok'=>false,'error'=>'OpenAI extraction JSON could not be parsed'];
}
function callOcrSpace($key,$tmp,$mime){
    if($key==='')return ['ok'=>false,'error'=>'OCR.space API key not configured'];$b64=imageToBase64Jpeg($tmp,$mime);$post=['apikey'=>$key,'base64Image'=>'data:image/jpeg;base64,'.$b64,'language'=>'eng','isOverlayRequired'=>'false','OCREngine'=>'2','scale'=>'true'];$ch=curl_init('https://api.ocr.space/parse/image');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);$response=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($response===false||$err!=='')return ['ok'=>false,'error'=>'OCR.space connection error'];$d=json_decode($response,true);if($status<200||$status>=300||!is_array($d))return ['ok'=>false,'error'=>'OCR.space HTTP '.$status];if(!empty($d['IsErroredOnProcessing']))return ['ok'=>false,'error'=>is_array($d['ErrorMessage']??null)?implode(' ', $d['ErrorMessage']):($d['ErrorMessage']??'OCR.space processing error')];$text='';foreach(($d['ParsedResults']??[]) as $p)$text.=' '.($p['ParsedText']??'');$text=trim(preg_replace('/\s+/',' ',$text));return $text!==''?['ok'=>true,'text'=>$text]:['ok'=>false,'error'=>'OCR.space found no text'];
}
function parseFuelOcr($text){
    $out=[];$t=str_replace([',','$'],['',''],$text);
    if(preg_match('/(?:odometer|odo|mileage)\D{0,20}(\d{4,7}(?:\.\d)?)/i',$t,$m))$out['odometer']=(float)$m[1];
    if(preg_match('/(?:gallons?|gal)\D{0,12}(\d{1,3}\.\d{2,3})/i',$t,$m))$out['gallons']=(float)$m[1];
    if(preg_match('/(?:total|sale|amount)\D{0,12}(\d{1,4}\.\d{2})/i',$t,$m))$out['totalCost']=(float)$m[1];
    if(preg_match('/(?:price|per\s*gal|\/gal)\D{0,15}(\d\.\d{2,3})/i',$t,$m))$out['pricePerGallon']=(float)$m[1];
    if(!isset($out['pricePerGallon'])&&preg_match('/\b([1-9]\.\d{3})\b/',$t,$m))$out['pricePerGallon']=(float)$m[1];
    if(!isset($out['odometer'])&&preg_match('/\b(\d{5,6}(?:\.\d)?)\b/',$t,$m))$out['odometer']=(float)$m[1];
    return $out;
}
function frac($v){if(is_numeric($v))return(float)$v;$p=explode('/',(string)$v,2);return count($p)===2&&(float)$p[1]!=0?(float)$p[0]/(float)$p[1]:(float)$v;}
function gpsDec($c,$h){if(!is_array($c)||count($c)<3)return null;$d=frac($c[0])+frac($c[1])/60+frac($c[2])/3600;return in_array(strtoupper((string)$h),['S','W'],true)?-$d:$d;}
function exifGps($tmp,$mime){if(strtolower($mime)!=='image/jpeg'||!function_exists('exif_read_data'))return null;$e=@exif_read_data($tmp,'GPS',true,false);$g=$e['GPS']??null;if(!is_array($g)||empty($g['GPSLatitude'])||empty($g['GPSLongitude']))return null;$la=gpsDec($g['GPSLatitude'],$g['GPSLatitudeRef']??'N');$lo=gpsDec($g['GPSLongitude'],$g['GPSLongitudeRef']??'E');if($la===null||$lo===null||$la< -90||$la>90||$lo< -180||$lo>180)return null;return ['latitude'=>round($la,6),'longitude'=>round($lo,6)];}
function mergeExtract(&$result,$d){$type=strtolower((string)($d['type']??''));if($type==='odometer'&&isset($d['odometer'])&&!isset($result['odometer']))$result['odometer']=(float)$d['odometer'];if($type==='price'&&isset($d['pricePerGallon'])&&!isset($result['pricePerGallon']))$result['pricePerGallon']=(float)$d['pricePerGallon'];if($type==='pump'){if(isset($d['totalCost'])&&!isset($result['totalCost']))$result['totalCost']=(float)$d['totalCost'];if(isset($d['gallons'])&&!isset($result['gallons']))$result['gallons']=(float)$d['gallons'];}if($type==='station'&&!empty($d['stationBrand'])&&!isset($result['stationBrand']))$result['stationBrand']=trim((string)$d['stationBrand']);}
$result=[];$fallbackUsed=false;$openAiErrors=[];$ocrErrors=[];
if(empty($_FILES['images'])){echo json_encode(['error'=>'No images received']);exit;}$files=[];foreach($_FILES['images']['tmp_name'] as $i=>$tmp){if(($_FILES['images']['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK&&!empty($tmp))$files[]=['tmp_name'=>$tmp,'type'=>$_FILES['images']['type'][$i]??'image/jpeg'];}if(!$files){echo json_encode(['error'=>'No valid images uploaded']);exit;}if(count($files)>4){echo json_encode(['error'=>'A maximum of 4 photos can be processed per entry.']);exit;}
foreach($files as $file){if(!isset($result['latitude'],$result['longitude'])){$g=exifGps($file['tmp_name'],$file['type']);if($g){$result=array_merge($result,$g);$result['locationSource']='photo_exif';}}
    $ai=extractOpenAI($apiKey,$file['tmp_name'],$file['type']);if($ai['ok']){mergeExtract($result,$ai['data']);continue;}$openAiErrors[]=$ai['error'];
    $ocr=callOcrSpace($ocrKey,$file['tmp_name'],$file['type']);if($ocr['ok']){$fallbackUsed=true;$parsed=parseFuelOcr($ocr['text']);foreach($parsed as $k=>$v)if(!isset($result[$k]))$result[$k]=$v;}else{$ocrErrors[]=$ocr['error'];}
}
$result['extractionProvider']=$fallbackUsed?'ocr.space':'openai';$result['fallbackUsed']=$fallbackUsed;if($fallbackUsed)$result['notice']='OpenAI was unavailable for one or more photos; OCR.space fallback was used. Even when OCR.space supplies the numeric fields, any photos successfully handled by OpenAI may still contribute other fields.';if(empty(array_intersect(['odometer','pricePerGallon','totalCost','gallons','stationBrand'],array_keys($result)))&&$openAiErrors)$result['error']='Photo extraction failed. OpenAI: '.end($openAiErrors).($ocrErrors?' OCR.space: '.end($ocrErrors):'');echo json_encode($result);
