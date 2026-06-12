<?php
// Filename: screensize.php
// Description: Live display of the current browser viewport size (Width x Height in pixels).
//              Updates in real time as the window is resized, docked, or zoomed.
// Author: Jason Lamb (with help from Claude Code CLI)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Screen Size</title>
<style>
  html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    background: #0f1115;
    color: #e6e6e6;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }
  .wrap {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 24px;
    box-sizing: border-box;
  }
  .label {
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.85rem;
    color: #8a8f98;
    margin-bottom: 12px;
  }
  .size {
    font-size: clamp(2rem, 8vw, 5rem);
    font-weight: 700;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
  }
  .size .x {
    color: #8a8f98;
    margin: 0 0.25em;
    font-weight: 400;
  }
  .unit {
    color: #8a8f98;
    font-size: 0.5em;
    margin-left: 0.2em;
    font-weight: 400;
  }
  .meta {
    margin-top: 20px;
    font-size: 0.95rem;
    color: #a7adb6;
    display: grid;
    grid-template-columns: auto auto;
    gap: 6px 18px;
    text-align: left;
    font-variant-numeric: tabular-nums;
  }
  .meta b { color: #e6e6e6; font-weight: 600; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="label">Current viewport</div>
    <div class="size">
      <span id="w">0</span><span class="unit">px</span>
      <span class="x">&times;</span>
      <span id="h">0</span><span class="unit">px</span>
    </div>
    <div class="meta">
      <span>Window inner:</span><span><b id="iw">0</b> &times; <b id="ih">0</b> px</span>
      <span>Window outer:</span><span><b id="ow">0</b> &times; <b id="oh">0</b> px</span>
      <span>Screen:</span><span><b id="sw">0</b> &times; <b id="sh">0</b> px</span>
      <span>Device pixel ratio:</span><span><b id="dpr">1</b></span>
      <span>Orientation:</span><span><b id="orient">&mdash;</b></span>
    </div>
  </div>

<script>
  (function () {
    const w = document.getElementById('w');
    const h = document.getElementById('h');
    const iw = document.getElementById('iw');
    const ih = document.getElementById('ih');
    const ow = document.getElementById('ow');
    const oh = document.getElementById('oh');
    const sw = document.getElementById('sw');
    const sh = document.getElementById('sh');
    const dpr = document.getElementById('dpr');
    const orient = document.getElementById('orient');

    function update() {
      const innerW = window.innerWidth  || document.documentElement.clientWidth;
      const innerH = window.innerHeight || document.documentElement.clientHeight;
      w.textContent = innerW;
      h.textContent = innerH;
      iw.textContent = innerW;
      ih.textContent = innerH;
      ow.textContent = window.outerWidth;
      oh.textContent = window.outerHeight;
      sw.textContent = screen.width;
      sh.textContent = screen.height;
      dpr.textContent = window.devicePixelRatio || 1;
      orient.textContent = innerW >= innerH ? 'Landscape' : 'Portrait';
      document.title = innerW + ' x ' + innerH + ' px';
    }

    update();
    window.addEventListener('resize', update, { passive: true });
    window.addEventListener('orientationchange', update, { passive: true });
    if (window.ResizeObserver) {
      new ResizeObserver(update).observe(document.documentElement);
    }
  })();
</script>
</body>
</html>
