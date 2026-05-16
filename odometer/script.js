const odometer = document.getElementById("odometer");

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

const maxValue = 999999.999;

const decimalPlaces = 3;
const wholeDigits = 6;

let value = 0;

let animationFrame = null;
let lastTimestamp = null;

let unitMode = "MPH";

function buildOdometer() {

  odometer.innerHTML = "";

  const template = "000000.000";

  for (const char of template) {

    const div =
      document.createElement("div");

    if (char === ".") {

      div.className = "decimal";
      div.textContent = ".";

    } else {

      div.className = "digit";
      div.textContent = "0";

    }

    odometer.appendChild(div);
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

  for (let i = 0; i < formatted.length; i++) {

    const char =
      formatted[i];

    const item =
      items[i];

    if (item.textContent !== char) {

      item.classList.add("roll");

      item.textContent = char;

      setTimeout(() => {

        item.classList.remove("roll");

      }, 100);
    }
  }
}

function getSpeedValue() {

  const speed =
    parseInt(speedInput.value, 10);

  if (
    Number.isNaN(speed) ||
    speed < 0
  ) {
    return 0;
  }

  return speed;
}

function getDistancePerSecond() {

  const speed =
    getSpeedValue();

  return speed / 3600;
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
      Math.min(speed, maxGauge)
      / maxGauge
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
    (timestamp - lastTimestamp)
    / 1000;

  lastTimestamp = timestamp;

  const distancePerSecond =
    getDistancePerSecond();

  value +=
    distancePerSecond
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

    } else {

      unitMode = "MPH";

      unitToggle.textContent =
        "Mode : MPH";
    }

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

startReadingInput.value =
  "000000.000";

updateOdometer();
updateSpeedometer();