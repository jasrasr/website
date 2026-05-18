/*
Revision      : 1.7.0
Description   : JavaScript logic for realistic odometer and speedometer emulator.
                Includes analog flip animation, continuous distance calculations,
                animated speedometer movement, and MPH/KPH switching.
Author        : Jason Lamb (with help from ChatGPT)
Created Date  : 2026-05-15
Modified Date : 2026-05-17

Features:
- Continuous odometer calculations
- Realistic MPH/KPH speed conversion
- Analog flip digit animation
- Animated speedometer needle
- Dynamic gauge tick generation
- Leading zero placeholder formatting
- Real-time display updates

Change Log:
1.0.0 - Initial odometer logic
1.1.0 - Added configurable starting value
1.2.0 - Added decimal precision support
1.3.0 - Added animated odometer updates
1.4.0 - Added speedometer calculations
1.5.0 - Corrected MPH distance math
1.6.0 - Added MPH/KPH switching
1.7.0 - Added analog flip animations and speed tick rendering
*/

const odometer =
  document.getElementById("odometer");

const startReadingInput =
  document.getElementById("startReading");

const speedInput =
  document.getElementById("speedInput");

const setBtn =
  document.getElementById("setBtn");

const startBtn =
  document.getElementById("startBtn");

const stopBtn =
  document.getElementById("stopBtn");

const resetBtn =
  document.getElementById("resetBtn");

const unitToggle =
  document.getElementById("unitToggle");

const needle =
  document.getElementById("needle");

const speedReadout =
  document.getElementById("speedReadout");

const speedLabel =
  document.getElementById("speedLabel");

const ticks =
  document.getElementById("ticks");

const maxValue = 999999.999;

const decimalPlaces = 3;
const wholeDigits = 6;

let value = 0;

let animationFrame = null;
let lastTimestamp = null;

let unitMode = "MPH";

function buildOdometer() {

  odometer.innerHTML = "";

  const template =
    "000000.000";

  for (const char of template) {

    const shell =
      document.createElement("div");

    if (char === ".") {

      shell.className = "decimal";

      shell.textContent = ".";

    } else {

      shell.className = "digit";

      const inner =
        document.createElement("div");

      inner.className =
        "digit-inner";

      inner.textContent = "0";

      shell.appendChild(inner);
    }

    odometer.appendChild(shell);
  }
}

function buildSpeedTicks() {

  ticks.innerHTML = "";

  const maxGauge =
    unitMode === "MPH"
      ? 130
      : 210;

  const minorStep =
    unitMode === "MPH"
      ? 5
      : 10;

  const majorStep =
    unitMode === "MPH"
      ? 10
      : 20;

  for (
    let speed = 0;
    speed <= maxGauge;
    speed += minorStep
  ) {

    const angle =
      -90 +
      (
        speed / maxGauge
      ) * 180;

    const tick =
      document.createElement("div");

    tick.className =
      speed % majorStep === 0
        ? "tick major"
        : "tick";

    tick.style.transform =
      `translateX(-50%) rotate(${angle}deg)`;

    if (speed % majorStep === 0) {

      const label =
        document.createElement("div");

      label.className =
        "tick-label";

      label.textContent =
        speed;

      label.style.transform =
        `translateX(-50%) rotate(${-angle}deg)`;

      tick.appendChild(label);
    }

    ticks.appendChild(tick);
  }
}

function clampValue(number) {

  if (Number.isNaN(number)) {
    return 0;
  }

  return Math.min(
    Math.max(number, 0),
    maxValue
  );
}

function formatReading(number) {

  const safeNumber =
    clampValue(number);

  const parts =
    safeNumber
      .toFixed(decimalPlaces)
      .split(".");

  const whole =
    parts[0].padStart(
      wholeDigits,
      "0"
    );

  const decimal =
    parts[1].padEnd(
      decimalPlaces,
      "0"
    );

  return `${whole}.${decimal}`;
}

function updateOdometer() {

  const formatted =
    formatReading(value);

  const items =
    [...odometer.children];

  for (
    let i = 0;
    i < formatted.length;
    i++
  ) {

    const char =
      formatted[i];

    const shell =
      items[i];

    if (char === ".") {
      continue;
    }

    const inner =
      shell.querySelector(
        ".digit-inner"
      );

    if (
      inner &&
      inner.textContent !== char
    ) {

      inner.classList.remove(
        "flip"
      );

      void inner.offsetWidth;

      inner.textContent = char;

      inner.classList.add(
        "flip"
      );

      setTimeout(() => {

        inner.classList.remove(
          "flip"
        );

      }, 180);
    }
  }
}

function getSpeedValue() {

  const speed =
    parseInt(
      speedInput.value,
      10
    );

  if (
    Number.isNaN(speed) ||
    speed < 0
  ) {
    return 0;
  }

  return speed;
}

function getDistancePerSecond() {

  return getSpeedValue() / 3600;
}

function updateSpeedometer() {

  const speed =
    getSpeedValue();

  const maxGauge =
    unitMode === "MPH"
      ? 130
      : 210;

  const angle =
    -90 +
    (
      Math.min(
        speed,
        maxGauge
      ) / maxGauge
    ) * 180;

  needle.style.transform =
    `translateX(-50%) rotate(${angle}deg)`;

  speedReadout.textContent =
    `${speed} ${unitMode}`;

  speedLabel.textContent =
    unitMode;
}

function setReading() {

  value =
    clampValue(
      Number(
        startReadingInput.value
      )
    );

  startReadingInput.value =
    formatReading(value);

  updateOdometer();
}

function animate(timestamp) {

  if (!lastTimestamp) {
    lastTimestamp = timestamp;
  }

  const elapsedSeconds =
    (
      timestamp -
      lastTimestamp
    ) / 1000;

  lastTimestamp = timestamp;

  value +=
    getDistancePerSecond()
    * elapsedSeconds;

  if (value >= maxValue) {

    value = maxValue;

    stopOdometer();
  }

  updateOdometer();

  updateSpeedometer();

  if (animationFrame) {

    animationFrame =
      requestAnimationFrame(
        animate
      );
  }
}

function startOdometer() {

  if (animationFrame) {
    return;
  }

  lastTimestamp = null;

  animationFrame =
    requestAnimationFrame(
      animate
    );
}

function stopOdometer() {

  if (animationFrame) {

    cancelAnimationFrame(
      animationFrame
    );
  }

  animationFrame = null;

  lastTimestamp = null;
}

function resetOdometer() {

  stopOdometer();

  value = 0;

  startReadingInput.value =
    "000000.000";

  updateOdometer();

  updateSpeedometer();
}

unitToggle.addEventListener(
  "click",
  () => {

    if (unitMode === "MPH") {

      unitMode = "KPH";

      unitToggle.textContent =
        "Mode : KPH";

      speedInput.max = "210";

    } else {

      unitMode = "MPH";

      unitToggle.textContent =
        "Mode : MPH";

      speedInput.max = "130";
    }

    buildSpeedTicks();

    updateSpeedometer();
  }
);

speedInput.addEventListener(
  "input",
  updateSpeedometer
);

setBtn.addEventListener(
  "click",
  setReading
);

startBtn.addEventListener(
  "click",
  startOdometer
);

stopBtn.addEventListener(
  "click",
  stopOdometer
);

resetBtn.addEventListener(
  "click",
  resetOdometer
);

buildOdometer();

buildSpeedTicks();

startReadingInput.value =
  "000000.000";

updateOdometer();

updateSpeedometer();