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

function gbPrintTeamCodes(teams, gameTitle, singleTeam = false) {
  let sheet = document.getElementById('teamQrPrint');
  if (!sheet) { sheet = document.createElement('section'); sheet.id = 'teamQrPrint'; document.body.append(sheet); }
  sheet.replaceChildren();
  sheet.className = singleTeam ? 'qr-print-single' : 'qr-print-all';
  const heading = document.createElement('h1'); heading.textContent = gameTitle || 'Game Buzzer'; sheet.append(heading);
  const grid = document.createElement('div'); grid.className = 'qr-print-grid';
  for (const team of teams) {
    const card = document.createElement('section'); card.className = 'qr-print-card';
    const label = document.createElement('h2'); label.textContent = team.name;
    const instruction = document.createElement('p'); instruction.textContent = 'Scan to join this team · Each phone scans the same code';
    card.append(label, gbTeamQr(team.invitation, team.name), instruction); grid.append(card);
  }
  sheet.append(grid);
  document.body.classList.add('printing-team-qr');
  const cleanup = () => { document.body.classList.remove('printing-team-qr'); sheet.replaceChildren(); };
  window.addEventListener('afterprint', cleanup, {once:true});
  try { window.print(); }
  catch (error) { cleanup(); throw error; }
}
