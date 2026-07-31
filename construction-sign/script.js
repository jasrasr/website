'use strict';

const FONT = {
  'A':['01110','10001','10001','11111','10001','10001','10001'],
  'B':['11110','10001','10001','11110','10001','10001','11110'],
  'C':['01111','10000','10000','10000','10000','10000','01111'],
  'D':['11110','10001','10001','10001','10001','10001','11110'],
  'E':['11111','10000','10000','11110','10000','10000','11111'],
  'F':['11111','10000','10000','11110','10000','10000','10000'],
  'G':['01111','10000','10000','10111','10001','10001','01111'],
  'H':['10001','10001','10001','11111','10001','10001','10001'],
  'I':['11111','00100','00100','00100','00100','00100','11111'],
  'J':['00111','00010','00010','00010','10010','10010','01100'],
  'K':['10001','10010','10100','11000','10100','10010','10001'],
  'L':['10000','10000','10000','10000','10000','10000','11111'],
  'M':['10001','11011','10101','10101','10001','10001','10001'],
  'N':['10001','11001','10101','10011','10001','10001','10001'],
  'O':['01110','10001','10001','10001','10001','10001','01110'],
  'P':['11110','10001','10001','11110','10000','10000','10000'],
  'Q':['01110','10001','10001','10001','10101','10010','01101'],
  'R':['11110','10001','10001','11110','10100','10010','10001'],
  'S':['01111','10000','10000','01110','00001','00001','11110'],
  'T':['11111','00100','00100','00100','00100','00100','00100'],
  'U':['10001','10001','10001','10001','10001','10001','01110'],
  'V':['10001','10001','10001','10001','10001','01010','00100'],
  'W':['10001','10001','10001','10101','10101','10101','01010'],
  'X':['10001','10001','01010','00100','01010','10001','10001'],
  'Y':['10001','10001','01010','00100','00100','00100','00100'],
  'Z':['11111','00001','00010','00100','01000','10000','11111'],
  '0':['01110','10001','10011','10101','11001','10001','01110'],
  '1':['00100','01100','00100','00100','00100','00100','01110'],
  '2':['01110','10001','00001','00010','00100','01000','11111'],
  '3':['11110','00001','00001','01110','00001','00001','11110'],
  '4':['00010','00110','01010','10010','11111','00010','00010'],
  '5':['11111','10000','10000','11110','00001','00001','11110'],
  '6':['01110','10000','10000','11110','10001','10001','01110'],
  '7':['11111','00001','00010','00100','01000','01000','01000'],
  '8':['01110','10001','10001','01110','10001','10001','01110'],
  '9':['01110','10001','10001','01111','00001','00001','01110'],
  '&':['01000','10100','10100','01000','10101','10010','01101'],
  '!':['00100','00100','00100','00100','00100','00000','00100'],
  '?':['01110','10001','00001','00010','00100','00000','00100'],
  '-':['00000','00000','00000','11111','00000','00000','00000'],
  '/':['00001','00010','00100','01000','10000','00000','00000'],
  '.':['00000','00000','00000','00000','00000','00110','00110'],
  ',':['00000','00000','00000','00000','00110','00100','01000'],
  ':':['00000','00110','00110','00000','00110','00110','00000'],
  "'":['00100','00100','00010','00000','00000','00000','00000'],
  ' ':['00000','00000','00000','00000','00000','00000','00000']
};

const els = {
  message: document.querySelector('#messageInput'),
  maxLines: document.querySelector('#lineCount'),
  align: document.querySelector('#textAlign'),
  color: document.querySelector('#ledColor'),
  bulbSize: document.querySelector('#bulbSize'),
  bulbGap: document.querySelector('#bulbGap'),
  glow: document.querySelector('#glowToggle'),
  border: document.querySelector('#frameToggle'),
  frame: document.querySelector('#signFrame'),
  preview: document.querySelector('#signPreview'),
  count: document.querySelector('#characterCount'),
  code: document.querySelector('#codeOutput'),
  status: document.querySelector('#statusMessage')
};

const canvas = document.createElement('canvas');
canvas.width = 1280;
canvas.height = 420;
canvas.id = 'signCanvas';
els.preview.appendChild(canvas);

function normalizeText(value) {
  return value.toUpperCase().replace(/[^A-Z0-9 &!?.:,\-'\/\n]/g, '');
}

function linePixelWidth(text) {
  return text.length ? text.length * 6 - 1 : 0;
}

function wrapText(text, maxLines, maxPixelWidth) {
  const forcedLines = normalizeText(text).split('\n');
  const output = [];

  for (const forcedLine of forcedLines) {
    const words = forcedLine.split(/\s+/).filter(Boolean);
    let current = '';

    for (const word of words) {
      const candidate = current ? `${current} ${word}` : word;
      if (linePixelWidth(candidate) <= maxPixelWidth) {
        current = candidate;
      } else {
        if (current) output.push(current);
        current = word;
      }
    }
    if (current || !words.length) output.push(current);
  }

  if (output.length > maxLines) {
    const visible = output.slice(0, maxLines);
    let last = visible[maxLines - 1];
    while (linePixelWidth(`${last}...`) > maxPixelWidth && last.length) last = last.slice(0, -1);
    visible[maxLines - 1] = `${last.trimEnd()}...`;
    return visible;
  }

  return output.slice(0, maxLines);
}

function currentLines() {
  const pitch = Number(els.bulbSize.value) + Number(els.bulbGap.value);
  const availableColumns = Math.floor((canvas.width - 100) / pitch);
  return wrapText(els.message.value, Number(els.maxLines.value), availableColumns);
}

function lineStartX(width, lineWidth) {
  if (els.align.value === 'left') return 52;
  if (els.align.value === 'right') return width - lineWidth - 52;
  return (width - lineWidth) / 2;
}

function drawSign() {
  const ctx = canvas.getContext('2d');
  const bulb = Number(els.bulbSize.value);
  const gap = Number(els.bulbGap.value);
  const pitch = bulb + gap;
  const lines = currentLines();
  const color = els.color.value;

  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = '#050606';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  const totalRows = lines.length * 7 + Math.max(0, lines.length - 1) * 3;
  const totalHeight = totalRows * pitch - gap;
  const startY = (canvas.height - totalHeight) / 2;

  lines.forEach((line, lineIndex) => {
    const lineWidth = linePixelWidth(line) * pitch - gap;
    const startX = lineStartX(canvas.width, lineWidth);
    const rowOffset = lineIndex * 10;

    [...line].forEach((char, charIndex) => {
      const glyph = FONT[char] || FONT['?'];
      glyph.forEach((row, rowIndex) => {
        [...row].forEach((on, colIndex) => {
          if (on !== '1') return;
          const radius = bulb / 2;
          const x = startX + (charIndex * 6 + colIndex) * pitch + radius;
          const y = startY + (rowOffset + rowIndex) * pitch + radius;

          ctx.shadowColor = color;
          ctx.shadowBlur = els.glow.checked ? Math.max(7, bulb * 1.8) : 0;
          ctx.beginPath();
          ctx.arc(x, y, radius, 0, Math.PI * 2);
          ctx.fillStyle = color;
          ctx.fill();
          ctx.shadowBlur = 0;
        });
      });
    });
  });

  els.frame.classList.toggle('no-frame', !els.border.checked);
  updateCode(lines);
}

function escapeHtml(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

function buildEmbedCode(lines) {
  const options = {
    color: els.color.value,
    bulb: Number(els.bulbSize.value),
    gap: Number(els.bulbGap.value),
    glow: els.glow.checked,
    frame: els.border.checked,
    align: els.align.value
  };

  return `<div class="construction-sign-widget" data-message="${escapeHtml(lines.join('\n'))}"></div>\n<script>\n(() => {\n  const font = ${JSON.stringify(FONT)};\n  const options = ${JSON.stringify(options)};\n  document.querySelectorAll('.construction-sign-widget').forEach((host) => {\n    host.style.cssText = 'max-width:980px;margin:1rem auto;padding:' + (options.frame ? '14px' : '0') + ';background:#050606;border:' + (options.frame ? '8px solid #555d62' : '0') + ';border-radius:7px;box-sizing:border-box;';\n    const canvas = document.createElement('canvas');\n    canvas.width = 1280; canvas.height = 420; canvas.style.cssText = 'display:block;width:100%;height:auto;background:#050606;';\n    host.appendChild(canvas);\n    const ctx = canvas.getContext('2d');\n    const lines = host.dataset.message.split('\\n');\n    const pitch = options.bulb + options.gap;\n    const pixelWidth = s => s.length * 6 - 1;\n    ctx.fillStyle = '#050606'; ctx.fillRect(0,0,canvas.width,canvas.height);\n    const totalRows = lines.length * 7 + Math.max(0, lines.length - 1) * 3;\n    const startY = (canvas.height - (totalRows * pitch - options.gap)) / 2;\n    lines.forEach((line, li) => {\n      const lineWidth = pixelWidth(line) * pitch - options.gap;\n      const startX = options.align === 'left' ? 52 : options.align === 'right' ? canvas.width - lineWidth - 52 : (canvas.width - lineWidth) / 2;\n      [...line].forEach((ch, ci) => {\n        const glyph = font[ch] || font['?'];\n        glyph.forEach((row, ri) => [...row].forEach((on, co) => {\n          if (on !== '1') return;\n          const r = options.bulb / 2;\n          const x = startX + (ci * 6 + co) * pitch + r;\n          const y = startY + (li * 10 + ri) * pitch + r;\n          ctx.shadowColor = options.color; ctx.shadowBlur = options.glow ? Math.max(7, options.bulb * 1.8) : 0;\n          ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.fillStyle = options.color; ctx.fill(); ctx.shadowBlur = 0;\n        }));\n      });\n    });\n  });\n})();\n<\/script>`;
}

function buildStandaloneHtml(lines) {
  return `<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>Construction Sign</title>\n<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box;background:#1c2023}</style>\n</head>\n<body>\n${buildEmbedCode(lines)}\n</body>\n</html>`;
}

function updateCode(lines = currentLines()) {
  els.code.value = buildEmbedCode(lines);
}

async function copyText(value, message) {
  try {
    await navigator.clipboard.writeText(value);
  } catch {
    els.code.value = value;
    els.code.focus();
    els.code.select();
    document.execCommand('copy');
  }
  els.status.textContent = message;
}

function downloadPng() {
  const link = document.createElement('a');
  link.download = 'construction-sign.png';
  link.href = canvas.toDataURL('image/png');
  link.click();
}

els.message.addEventListener('input', () => {
  const cleaned = normalizeText(els.message.value);
  if (cleaned !== els.message.value) els.message.value = cleaned;
  els.count.textContent = `${els.message.value.length} characters`;
  drawSign();
});

[els.maxLines, els.align, els.color, els.bulbSize, els.bulbGap, els.glow, els.border].forEach((element) => {
  element.addEventListener('input', drawSign);
  element.addEventListener('change', drawSign);
});

document.querySelector('#copyEmbedButton').addEventListener('click', () => copyText(buildEmbedCode(currentLines()), 'Embed code copied.'));
document.querySelector('#copyStandaloneButton').addEventListener('click', () => copyText(buildStandaloneHtml(currentLines()), 'Standalone HTML copied.'));
document.querySelector('#downloadPngButton').addEventListener('click', downloadPng);
els.code.addEventListener('click', () => els.code.select());

els.count.textContent = `${els.message.value.length} characters`;
drawSign();
