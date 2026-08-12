<?php
// ============================================================================
// File: manage_entries.php
// Purpose: View, edit, delete, and safely recalculate fuel log entries
// Revision: 2.2
// Author: Jason Lamb
//
// Revision Notes:
// 2.2 - Recalculate all miles/full-to-full MPG after edits/deletes and expose
//       fill type, station brand, and comments in the editor.
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/device_init.php';
if (!$isAdminTrusted) die('<h2>Access denied.</h2>');

$plate=preg_replace('/[^A-Z0-9]/','',strtoupper(trim($_GET['plate']??$_POST['plate']??'')));
if($plate==='')die('<h2>No license plate specified.</h2>');
$logFile=__DIR__."/logs/{$plate}.json";if(!file_exists($logFile))die('<h2>No log file found.</h2>');

function loadEntries($f){$d=json_decode(file_get_contents($f),true);return is_array($d)?array_values($d):[];}
function saveEntries($f,$e){file_put_contents($f,json_encode(array_values($e),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function isFull($e){return strtolower((string)($e['fill_type']??'full'))!=='partial';}
function recalcAll(&$entries){
    $lastOdo=0;$lastFullIndex=null;
    foreach($entries as $i=>&$e){
        $odo=(float)($e['odometer']??0);$g=(float)($e['gallons']??0);
        $e['miles']=($lastOdo>0&&$odo>$lastOdo)?round($odo-$lastOdo,1):0;
        $e['mpg']=null;$e['mpg_miles']=null;$e['mpg_gallons']=null;
        if(isFull($e)){
            if($lastFullIndex!==null){
                $prevFullOdo=(float)($entries[$lastFullIndex]['odometer']??0);
                $spanMiles=$odo-$prevFullOdo;$spanGallons=$g;
                for($j=$lastFullIndex+1;$j<$i;$j++)$spanGallons+=(float)($entries[$j]['gallons']??0);
                if($spanMiles>0&&$spanGallons>0){$e['mpg_miles']=round($spanMiles,1);$e['mpg_gallons']=round($spanGallons,3);$e['mpg']=round($spanMiles/$spanGallons,2);}
            }
            $lastFullIndex=$i;
        }
        $lastOdo=$odo;
    }
    unset($e);
}

$message='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    $action=$_POST['action']??'';$index=isset($_POST['index'])?(int)$_POST['index']:-1;$entries=loadEntries($logFile);
    if($action==='delete'&&isset($entries[$index])){array_splice($entries,$index,1);recalcAll($entries);saveEntries($logFile,$entries);$message="Entry #{$index} deleted; all dependent MPG values recalculated.";}
    elseif($action==='save'&&isset($entries[$index])){
        $e=&$entries[$index];$e['date']=trim($_POST['date']??$e['date']);$e['odometer']=(float)($_POST['odometer']??$e['odometer']);$e['gallons']=(float)($_POST['gallons']??$e['gallons']);$e['price_per_gallon']=(float)($_POST['price_per_gallon']??$e['price_per_gallon']);$e['total_cost']=(float)($_POST['total_cost']??$e['total_cost']);$e['fill_type']=($_POST['fill_type']??'full')==='partial'?'partial':'full';$e['station_brand']=trim($_POST['station_brand']??($e['station_brand']??''));$e['comment']=trim($_POST['comment']??($e['comment']??''));$e['verified']=($_POST['verified']??'no')==='yes'?'yes':'no';unset($e);recalcAll($entries);saveEntries($logFile,$entries);$message="Entry #{$index} saved; all dependent MPG values recalculated.";
    }elseif($action==='save_json'){$raw=$_POST['raw_json']??'';$parsed=json_decode($raw,true);if(json_last_error()!==JSON_ERROR_NONE)$message='ERROR: Invalid JSON — '.json_last_error_msg();elseif(!is_array($parsed))$message='ERROR: JSON must be an array.';else{$parsed=array_values($parsed);recalcAll($parsed);saveEntries($logFile,$parsed);$message='Raw JSON saved and calculations rebuilt.';}}
    header('Location: manage_entries.php?plate='.urlencode($plate).'&msg='.urlencode($message));exit;
}
$entries=loadEntries($logFile);$editIndex=isset($_GET['edit'])?(int)$_GET['edit']:-1;$sortDir=($_GET['sort']??'asc')==='desc'?'desc':'asc';$display=$entries;uasort($display,function($a,$b)use($sortDir){$c=strcmp($a['date']??'',$b['date']??'');return$sortDir==='desc'?-$c:$c;});$msg=htmlspecialchars($_GET['msg']??'');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Manage Entries - <?=htmlspecialchars($plate)?></title><style>body{font-family:sans-serif;max-width:1300px;margin:auto;padding:1rem}table{width:100%;border-collapse:collapse;font-size:.84rem}th,td{border:1px solid #ccc;padding:.4rem;text-align:center}th{background:#f2f2f2}.msg{background:#d4edda;color:#155724;padding:.7rem;border-radius:6px}.edit{background:#fff8e1;border:1px solid #ffc107;padding:1rem;border-radius:8px;margin:1rem 0}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.grid input,.grid select,.grid textarea{width:100%;padding:.4rem;box-sizing:border-box}.btn{padding:.4rem .6rem;border:0;border-radius:4px;color:#fff;text-decoration:none;cursor:pointer}.editbtn{background:#007bff}.delbtn{background:#dc3545}.savebtn{background:#28a745}.verifybtn{background:#198754}.partial{background:#fff3cd}.note{font-size:.8rem;color:#666}@media(max-width:700px){.grid{grid-template-columns:1fr}table{display:block;overflow-x:auto}}</style></head><body>
<h2>Manage Entries — <?=htmlspecialchars($plate)?></h2><p><a href="admin.php">← Back to Admin</a></p><?php if($msg):?><div class="msg"><?=$msg?></div><?php endif;?>
<?php if($editIndex>=0&&isset($entries[$editIndex])):$e=$entries[$editIndex];?><div class="edit"><h3>Edit Entry #<?=$editIndex?></h3><form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="plate" value="<?=htmlspecialchars($plate)?>"><input type="hidden" name="index" value="<?=$editIndex?>"><div class="grid">
<label>Date<input type="date" name="date" value="<?=htmlspecialchars($e['date']??'')?>"></label><label>Odometer<input type="number" step="0.1" name="odometer" value="<?=htmlspecialchars($e['odometer']??'')?>"></label><label>Gallons<input type="number" step="0.001" name="gallons" value="<?=htmlspecialchars($e['gallons']??'')?>"></label><label>Price/Gal<input type="number" step="0.001" name="price_per_gallon" value="<?=htmlspecialchars($e['price_per_gallon']??'')?>"></label><label>Total Cost<input type="number" step="0.01" name="total_cost" value="<?=htmlspecialchars($e['total_cost']??'')?>"></label><label>Fill Type<select name="fill_type"><option value="full" <?=isFull($e)?'selected':''?>>Full</option><option value="partial" <?=!isFull($e)?'selected':''?>>Partial</option></select></label><label>Station Brand<input type="text" name="station_brand" value="<?=htmlspecialchars($e['station_brand']??'')?>"></label><label>Verified<select name="verified"><option value="no" <?=($e['verified']??'no')==='no'?'selected':''?>>No</option><option value="yes" <?=($e['verified']??'no')==='yes'?'selected':''?>>Yes</option></select></label><label style="grid-column:1/-1">Comment<textarea name="comment" maxlength="500"><?=htmlspecialchars($e['comment']??'')?></textarea></label></div><p class="note">Saving rebuilds miles and full-to-full MPG for the entire log, including partial-fill rollups.</p><button class="btn savebtn" type="submit">💾 Save Changes</button> <a href="manage_entries.php?plate=<?=urlencode($plate)?>">Cancel</a></form></div><?php endif;?>
<table><thead><tr><th>#</th><th><a href="?plate=<?=urlencode($plate)?>&sort=<?=$sortDir==='asc'?'desc':'asc'?>">Date</a></th><th>Odometer</th><th>Miles</th><th>Gallons</th><th>Fill</th><th>Price/Gal</th><th>Total</th><th>MPG</th><th>Station</th><th>Comment</th><th>Verified</th><th>Actions</th></tr></thead><tbody>
<?php foreach($display as $i=>$e):$partial=!isFull($e);?><tr class="<?=$partial?'partial':''?>"><td><?=$i?></td><td><?=htmlspecialchars($e['date']??'—')?></td><td><?=htmlspecialchars($e['odometer']??'—')?></td><td><?=htmlspecialchars($e['miles']??'—')?></td><td><?=htmlspecialchars($e['gallons']??'—')?></td><td><?=$partial?'Partial':'Full'?></td><td><?=isset($e['price_per_gallon'])?'$'.number_format((float)$e['price_per_gallon'],3):'—'?></td><td><?=isset($e['total_cost'])?'$'.number_format((float)$e['total_cost'],2):'—'?></td><td><?=$partial?'Pending':htmlspecialchars($e['mpg']??'—')?></td><td><?=htmlspecialchars($e['station_brand']??'—')?></td><td><?=htmlspecialchars($e['comment']??'')?></td><td><?=htmlspecialchars($e['verified']??'no')?></td><td style="white-space:nowrap"><?php if(($e['verified']??'no')!=='yes'):?><form method="post" action="verify_entry.php" style="display:inline"><input type="hidden" name="plate" value="<?=htmlspecialchars($plate)?>"><input type="hidden" name="index" value="<?=$i?>"><button class="btn verifybtn">✔</button></form><?php endif;?> <a class="btn editbtn" href="?plate=<?=urlencode($plate)?>&edit=<?=$i?>">✏️</a> <form method="post" style="display:inline" onsubmit="return confirm('Delete this entry and recalculate the log?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="plate" value="<?=htmlspecialchars($plate)?>"><input type="hidden" name="index" value="<?=$i?>"><button class="btn delbtn">🗑</button></form></td></tr><?php endforeach;?></tbody></table>
<details style="margin-top:2rem"><summary><strong>Raw JSON Editor</strong></summary><p class="note">Saving raw JSON also rebuilds miles and full-to-full MPG.</p><form method="post"><input type="hidden" name="action" value="save_json"><input type="hidden" name="plate" value="<?=htmlspecialchars($plate)?>"><textarea name="raw_json" style="width:100%;height:420px;font-family:monospace"><?=htmlspecialchars(json_encode($entries,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))?></textarea><br><button class="btn savebtn">Save JSON</button></form></details>
</body></html>
