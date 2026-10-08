'use strict';
// Render the full invitation, including its team capability fragment, locally.
function gbTeamQr(value, name) {
  const code = qrcode(0, 'M');
  code.addData(value);
  code.make();
  const quiet = 4, scale = 8, size = code.getModuleCount();
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = (size + quiet * 2) * scale;
  canvas.className = 'team-qr';
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', `Scan to join ${name}`);
  const context = canvas.getContext('2d');
  context.fillStyle = '#ffffff'; context.fillRect(0, 0, canvas.width, canvas.height);
  context.fillStyle = '#000000';
  for (let row = 0; row < size; row++) {
    for (let col = 0; col < size; col++) {
      if (code.isDark(row, col)) context.fillRect((col + quiet) * scale, (row + quiet) * scale, scale, scale);
    }
  }
  return canvas;
}
