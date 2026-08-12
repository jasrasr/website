<?php
// ============================================================================
// File: view_map.php
// Purpose: Display an interactive map of fuel stops with stored coordinates
// Revision: 1.0.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();

$plate = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_GET['plate'] ?? '')));
if ($plate === '') die('<h2>No license plate specified.</h2>');

$logFile = __DIR__ . "/logs/{$plate}.json";
if (!file_exists($logFile)) die('<h2>No log found for ' . htmlspecialchars($plate) . '.</h2>');

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data)) die('<h2>Fuel log is unreadable.</h2>');

$points = [];
foreach ($data as $entry) {
    if (!isset($entry['latitude'], $entry['longitude'])) continue;
    $lat = (float)$entry['latitude'];
    $lon = (float)$entry['longitude'];
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) continue;

    $fillType = strtolower((string)($entry['fill_type'] ?? 'full')) === 'partial' ? 'Partial' : 'Full';
    $station = trim((string)($entry['station_brand'] ?? ''));
    $locationLabel = trim((string)($entry['station_location_label'] ?? ''));
    if ($locationLabel !== '') $station = $station !== '' ? $station . ' — ' . $locationLabel : $locationLabel;

    $points[] = [
        'lat' => $lat,
        'lon' => $lon,
        'date' => $entry['date'] ?? '',
        'station' => $station,
        'gallons' => $entry['gallons'] ?? null,
        'price' => $entry['price_per_gallon'] ?? null,
        'total' => $entry['total_cost'] ?? null,
        'fillType' => $fillType,
        'mpg' => $entry['mpg'] ?? null,
        'comment' => $entry['comment'] ?? '',
        'source' => $entry['location_source'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fuel Stop Map - <?= htmlspecialchars($plate) ?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
body{font-family:sans-serif;max-width:1200px;margin:auto;padding:1rem;}
#map{height:70vh;min-height:420px;border:1px solid #ccc;border-radius:8px;margin-top:1rem;}
.note{color:#666;font-size:.9rem;}
a{color:#007bff;text-decoration:none;}
</style>
</head>
<body>
<h2>Fuel Stop Map — <?= htmlspecialchars($plate) ?></h2>
<p class="note">Shows only entries with saved coordinates. Location data may come from GPS, photo EXIF, or a saved station profile.</p>
<div id="map"></div>
<?php include 'menu.php'; ?>
<script>
const points = <?= json_encode($points, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) ?>;
const map = L.map('map');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

function esc(v){return String(v ?? '').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}

const bounds = [];
points.forEach(p => {
    const marker = L.marker([p.lat, p.lon]).addTo(map);
    bounds.push([p.lat, p.lon]);
    const mpgLine = p.mpg !== null && p.mpg !== '' ? `<br><b>MPG:</b> ${esc(p.mpg)}` : '<br><b>MPG:</b> pending / not applicable';
    const stationLine = p.station ? `<br><b>Station:</b> ${esc(p.station)}` : '';
    const commentLine = p.comment ? `<br><b>Comment:</b> ${esc(p.comment)}` : '';
    marker.bindPopup(`<b>${esc(p.date)}</b>${stationLine}<br><b>Fill:</b> ${esc(p.fillType)}<br><b>Gallons:</b> ${esc(p.gallons)}<br><b>Price:</b> $${esc(p.price)}<br><b>Total:</b> $${esc(p.total)}${mpgLine}${commentLine}`);
});

if (bounds.length) {
    map.fitBounds(bounds, {padding:[30,30], maxZoom:15});
} else {
    map.setView([39.5, -98.35], 4);
    L.popup().setLatLng([39.5, -98.35]).setContent('No mapped fuel stops yet.').openOn(map);
}
</script>
</body>
</html>
