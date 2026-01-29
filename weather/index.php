<?php
// ============================================================================
// File Name    : index.php
// Author       : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-01-24
// Modified Date: 2026-01-29
// Revision     : 2.7
// Description : Mobile-friendly weather dashboard UI using authoritative
//               lat/lon config entries with optional ZIP/manual overrides.
// Changelog    :
//   Rev 2.0 - ZIP add, UI revision display, history support
//   Rev 2.1 - Show state and remove humidity
//   Rev 2.2 - Defensive UI rendering for evolving schema
//   Rev 2.3 - Added distance display from browser location
//   Rev 2.4 - Added support for custom city/state entry
//   Rev 2.5 - Combined City and State into single required field
//   Rev 2.6 - Lat/lon authoritative config entries
//   Rev 2.7 - Update STATE in display
// ============================================================================

$weather = require __DIR__ . '/weather_update.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Weather Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body { background:#0f172a; color:#e5e7eb; font-family:system-ui; padding:16px }
.city { background:#1e293b; padding:16px; border-radius:12px; margin-bottom:12px }
.temp { font-size:2.5rem; font-weight:700 }
.updated { opacity:.8; margin-bottom:12px }
input, button { padding:8px; border-radius:6px; border:none }
button { background:#2563eb; color:white }
</style>

<script>
navigator.geolocation.getCurrentPosition(pos => {
    const params = new URLSearchParams(window.location.search);
    params.set('lat', pos.coords.latitude);
    params.set('lon', pos.coords.longitude);
    history.replaceState({}, '', '?' + params.toString());
});

setTimeout(() => location.reload(), 3600000);

document.addEventListener('DOMContentLoaded', () => {
    const iso = "<?= $weather['updated_iso'] ?>";
    const d = new Date(iso);
    document.getElementById('updated').textContent =
        d.toLocaleString() + " (" +
        Intl.DateTimeFormat().resolvedOptions().timeZone + ")";
});
</script>
</head>

<body>

<h1>Weather Dashboard</h1>
<div>UI Rev : <?= htmlspecialchars($weather['ui_revision'] ?? '—') ?></div>
<div class="updated">Last updated : <span id="updated">—</span></div>

<form method="get" style="margin:12px 0">
    <input name="zip" placeholder="ZIP (optional)">
    <input name="location" placeholder="City, ST (optional)">
    <button>Add Location</button>
</form>

<?php foreach ($weather['cities'] as $city): ?>
<div class="city">
<h2>
    <?= htmlspecialchars($city['name']) ?>
    <?php if (isset($city['distance'])): ?>
        <span style="opacity:.6">– <?= $city['distance'] ?> mi</span>
    <?php endif; ?>
</h2>

    <?php if (!empty($city['error'])): ?>
        <div style="color:#fca5a5">Error : <?= htmlspecialchars($city['error']) ?></div>
    <?php else: ?>
        <div class="temp"><?= round($city['temp']) ?> °F</div>
        <div>Feels like : <?= round($city['feels_like']) ?> °F</div>
        <div>
            High : <?= round($city['temp_high']) ?> °F |
            Low : <?= round($city['temp_low']) ?> °F
        </div>
        <div><?= ucfirst($city['condition']) ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

</body>
</html>
