(() => {
  'use strict';
  const pets = [
    { name: 'Puppy', emoji: '🐶', welcome: 'Woof! I am hungry!' },
    { name: 'Kitten', emoji: '🐱', welcome: 'Meow! Snack time!' },
    { name: 'Bunny', emoji: '🐰', welcome: 'Hop! What is for lunch?' },
    { name: 'Panda', emoji: '🐼', welcome: 'I love a tasty snack!' },
  ];
  const apple = { name: 'Apple', emoji: '🍎', color: 'red' };
  const banana = { name: 'Banana', emoji: '🍌', color: 'yellow' };
  const carrot = { name: 'Carrot', emoji: '🥕', color: 'orange' };
  const foods = [
    apple, banana, carrot,
    { name: 'Peas', emoji: '🫛', color: 'green' },
    { name: 'Blueberries', emoji: '🫐', color: 'blue' },
  ];
  const peas = foods[3];
  const blueberries = foods[4];
  const orderPool = [
    { id: 'red', prompt: 'Something red, please!', audio: 'audio/order-red.mp3', items: [apple], colorClue: 'red' },
    { id: 'green', prompt: 'Green peas, please!', audio: 'audio/order-green-peas.mp3', items: [peas], colorClue: 'green' },
    { id: 'orange', prompt: 'Something orange, please!', audio: 'audio/order-orange.mp3', items: [carrot], colorClue: 'orange' },
    { id: 'yellow', prompt: 'Something yellow, please!', audio: 'audio/order-yellow.mp3', items: [banana], colorClue: 'yellow' },
    { id: 'blue', prompt: 'Something blue, please!', audio: 'audio/order-blue.mp3', items: [blueberries], colorClue: 'blue' },
    { id: 'two-carrots', prompt: 'Two carrots, please!', audio: 'audio/order-carrots.mp3', items: [carrot, carrot] },
    { id: 'two-bananas', prompt: 'Two bananas, please!', audio: 'audio/order-two-bananas.mp3', items: [banana, banana] },
    { id: 'two-apples', prompt: 'Two apples, please!', audio: 'audio/order-two-apples.mp3', items: [apple, apple] },
    { id: 'two-blueberries', prompt: 'Two blueberries, please!', audio: 'audio/order-two-blueberries.mp3', items: [blueberries, blueberries] },
    { id: 'three-apples', prompt: 'Three apples, please!', audio: 'audio/order-three-apples.mp3', items: [apple, apple, apple] },
    { id: 'three-carrots', prompt: 'Three carrots, please!', audio: 'audio/order-three-carrots.mp3', items: [carrot, carrot, carrot] },
    { id: 'apple-banana-apple', prompt: 'Apple, banana, apple. In that order!', audio: 'audio/order-pattern.mp3', items: [apple, banana, apple] },
    { id: 'carrot-apple-carrot', prompt: 'Carrot, apple, carrot. In that order!', audio: 'audio/order-carrot-apple-carrot.mp3', items: [carrot, apple, carrot] },
    { id: 'banana-apple-banana', prompt: 'Banana, apple, banana. In that order!', audio: 'audio/order-banana-apple-banana.mp3', items: [banana, apple, banana] },
    { id: 'peas-carrot-peas', prompt: 'Peas, carrot, peas. In that order!', audio: 'audio/order-peas-carrot-peas.mp3', items: [peas, carrot, peas] },
    { id: 'blueberries-apple', prompt: 'Blueberries, then apple!', audio: 'audio/order-blueberries-apple.mp3', items: [blueberries, apple] },
    { id: 'banana-peas', prompt: 'Banana, then peas!', audio: 'audio/order-banana-peas.mp3', items: [banana, peas] },
    { id: 'apple-carrot', prompt: 'Apple, then carrot!', audio: 'audio/order-apple-carrot.mp3', items: [apple, carrot] },
  ];
  const sounds = {
    bite: [523, 659],
    retry: [392, 330],
    order: [523, 659, 784],
    finish: [523, 659, 784, 1047],
  };
  const petPicker = document.querySelector('#pet-picker');
  const foodTray = document.querySelector('#food');
  const pet = document.querySelector('#pet');
  const bowl = document.querySelector('#bowl');
  const speech = document.querySelector('#speech');
  const progress = document.querySelector('#progress');
  const newGame = document.querySelector('#new-game');
  const modeButtons = document.querySelectorAll('[data-mode]');
  const clues = document.querySelector('#order-clues');
  const feedback = document.querySelector('#order-feedback');
  const nextOrder = document.querySelector('#next-order');
  const repeatOrder = document.querySelector('#repeat-order');
  const nextIcon = nextOrder.querySelector('.next-icon');
  const nextLabel = nextOrder.querySelector('.next-label');
  const game = document.querySelector('.game');
  const soundToggle = document.querySelector('#sound-toggle');
  const sessionCount = document.querySelector('#session-count');
  const sessionList = document.querySelector('#session-list');
  const sessionKey = 'feed-the-pet-session-v1';
  const lastOrdersKey = 'feed-the-pet-last-orders-v1';
  let completedRounds = [];
  let achievements = 0;
  try {
    const stored = JSON.parse(sessionStorage.getItem(sessionKey) || 'null');
    const rounds = Array.isArray(stored) ? stored : stored?.rounds;
    if (Array.isArray(rounds)) {
      completedRounds = rounds.filter(entry =>
        entry && ['free', 'orders'].includes(entry.mode) &&
        pets.some(pet => pet.name === entry.pet) &&
        typeof entry.time === 'string'
      ).slice(-20);
      achievements = Number.isInteger(stored?.achievements)
        ? Math.max(0, stored.achievements)
        : completedRounds.filter(entry => entry.mode === 'orders').length;
    }
  } catch {
    // Continue with in-memory history when session storage is unavailable.
  }
  const AudioContext = window.AudioContext || window.webkitAudioContext;
  let selectedPet = pets[0];
  let activeOrders = orderPool.slice(0, 3);
  let lastOrderIds = [];
  try {
    const storedIds = JSON.parse(sessionStorage.getItem(lastOrdersKey) || '[]');
    if (Array.isArray(storedIds)) lastOrderIds = storedIds.filter(id => typeof id === 'string');
  } catch {
    // A repeated set is harmless when session storage is unavailable.
  }
  let mode = 'orders';
  let orderIndex = 0;
  let fed = 0;
  let soundEnabled = true;
  let audio;
  let volume;
  let lastCue = 0;
  let voiceAudio;

  function randomIndex(max) {
    if (window.crypto?.getRandomValues) {
      const value = new Uint32Array(1);
      const limit = Math.floor(0x100000000 / max) * max;
      do { window.crypto.getRandomValues(value); } while (value[0] >= limit);
      return value[0] % max;
    }
    return Math.floor(Math.random() * max);
  }

  function chooseOrders() {
    let chosen;
    for (let attempt = 0; attempt < 5; attempt += 1) {
      const shuffled = orderPool.slice();
      for (let index = shuffled.length - 1; index > 0; index -= 1) {
        const swapIndex = randomIndex(index + 1);
        [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
      }
      chosen = shuffled.slice(0, 3);
      const ids = chosen.map(order => order.id).sort();
      if (ids.join('|') !== lastOrderIds.slice().sort().join('|')) break;
    }
    lastOrderIds = chosen.map(order => order.id);
    try { sessionStorage.setItem(lastOrdersKey, JSON.stringify(lastOrderIds)); } catch { /* Continue in memory. */ }
    return chosen;
  }

  function speakWithBrowser(prompt) {
    if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) return;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(prompt);
    utterance.lang = 'en-US';
    utterance.rate = 0.85;
    window.speechSynthesis.speak(utterance);
  }

  function speakOrder() {
    if (!soundEnabled) return;
    const order = activeOrders[orderIndex];
    if (voiceAudio) voiceAudio.pause();
    let usedFallback = false;
    const fallback = () => {
      if (usedFallback || !soundEnabled) return;
      usedFallback = true;
      speakWithBrowser(order.prompt);
    };
    voiceAudio = new Audio(order.audio);
    voiceAudio.addEventListener('error', fallback, { once: true });
    voiceAudio.play().catch(fallback);
  }

  function achievementCount() {
    return achievements;
  }

  function unlockedPetCount() {
    return Math.min(pets.length, 1 + Math.floor(achievementCount() / 5));
  }

  function renderAchievements() {
    const achievements = achievementCount();
    const unlocked = unlockedPetCount();
    const count = document.querySelector('#achievement-count');
    const progress = document.querySelector('#unlock-progress');
    count.textContent = `${achievements} ${achievements === 1 ? 'achievement' : 'achievements'}`;
    if (unlocked === pets.length) {
      progress.textContent = 'All pets unlocked!';
    } else {
      const remaining = unlocked * 5 - achievements;
      progress.textContent = `${remaining} more ${remaining === 1 ? 'achievement' : 'achievements'} unlocks ${pets[unlocked].name}`;
    }
    document.querySelectorAll('.pet-choice').forEach((button, index) => {
      const locked = index >= unlocked;
      button.disabled = locked;
      button.classList.toggle('locked', locked);
      button.setAttribute('aria-label', locked
        ? `${button.dataset.pet} locked. Earn ${index * 5} achievements to unlock.`
        : `Choose ${button.dataset.pet}`);
      button.querySelector('.pet-lock').hidden = !locked;
    });
  }

  function renderHistory() {
    sessionCount.textContent = `${completedRounds.length} completed ${completedRounds.length === 1 ? 'round' : 'rounds'}`;
    sessionList.replaceChildren(...completedRounds.slice().reverse().map(entry => {
      const item = document.createElement('li');
      item.textContent = `${entry.pet} · ${entry.mode === 'orders' ? 'Pet Orders' : 'Free Feed'} · ${entry.time}`;
      return item;
    }));
  }

  function finishRound() {
    const unlockedBefore = unlockedPetCount();
    if (mode === 'orders') achievements += 1;
    completedRounds.push({
      pet: selectedPet.name,
      mode,
      time: new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }),
    });
    completedRounds = completedRounds.slice(-20);
    try {
      sessionStorage.setItem(sessionKey, JSON.stringify({ rounds: completedRounds, achievements }));
    } catch {
      // The in-memory list still works for this page.
    }
    renderHistory();
    renderAchievements();
    return unlockedPetCount() > unlockedBefore ? pets[unlockedBefore] : null;
  }

  async function playSound(cue) {
    if (!soundEnabled || !AudioContext) return;
    const request = ++lastCue;

    try {
      // Create and unlock audio on a tap so mobile browsers allow playback.
      audio ??= new AudioContext();
      if (!volume) {
        volume = audio.createGain();
        volume.gain.value = 0.1;
        volume.connect(audio.destination);
      }
      if (audio.state !== 'running') await audio.resume();
      if (!soundEnabled || request !== lastCue) return;

      sounds[cue].forEach((frequency, index) => {
        const start = audio.currentTime + index * 0.11;
        const end = start + 0.15;
        const note = audio.createOscillator();
        const envelope = audio.createGain();
        note.frequency.value = frequency;
        envelope.gain.setValueAtTime(0, start);
        envelope.gain.linearRampToValueAtTime(0.55, start + 0.018);
        envelope.gain.linearRampToValueAtTime(0, end);
        note.connect(envelope).connect(volume);
        note.onended = () => {
          note.disconnect();
          envelope.disconnect();
        };
        note.start(start);
        note.stop(end);
      });
    } catch {
      // Audio can be unavailable without interrupting play.
    }
  }

  function choosePet(nextPet) {
    selectedPet = nextPet;
    pet.textContent = selectedPet.emoji;
    pet.setAttribute('aria-label', selectedPet.name);
    document.querySelectorAll('.pet-choice').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.pet === selectedPet.name)));
    startRound();
  }

  function feed(food, button) {
    if (button.disabled) return;
    if (mode === 'orders') {
      serveOrder(food);
      return;
    }
    button.disabled = true;
    button.classList.add('used');
    fed += 1;
    addBite(food);
    progress.textContent = `Food: ${fed} of ${foods.length}`;
    speech.textContent = fed === foods.length ? `${selectedPet.name} is full! Yum! 🎉` : 'Yum! More please!';
    if (fed === foods.length) finishRound();
    playSound(fed === foods.length ? 'finish' : 'bite');
  }

  function addBite(food) {
    const bite = document.createElement('span');
    bite.className = 'bowl-food';
    bite.textContent = food.emoji;
    bowl.append(bite);
    pet.classList.remove('happy');
    void pet.offsetWidth;
    pet.classList.add('happy');
  }

  function serveOrder(food) {
    const order = activeOrders[orderIndex];
    if (food !== order.items[fed]) {
      feedback.textContent = 'Try the food in the highlighted picture!';
      clues.classList.add('retry');
      playSound('retry');
      return;
    }

    fed += 1;
    addBite(food);
    renderOrder();
    if (fed < order.items.length) {
      feedback.textContent = `Good! Next: ${order.items[fed].name}.`;
      playSound('bite');
      return;
    }

    const finished = orderIndex === activeOrders.length - 1;
    const unlockedPet = finished ? finishRound() : null;
    playSound(finished ? 'finish' : 'order');
    feedback.textContent = unlockedPet
      ? `You unlocked ${unlockedPet.name}! 🎉`
      : finished ? 'You earned an achievement! ⭐' : 'Order complete! ⭐';
    nextIcon.textContent = finished ? '↻' : '▶';
    nextLabel.textContent = finished ? 'Play again' : 'Next order';
    nextOrder.hidden = false;
    nextOrder.focus();
    foodTray.querySelectorAll('button').forEach((button) => { button.disabled = true; });
  }

  function renderOrder() {
    const order = activeOrders[orderIndex];
    clues.classList.remove('retry');
    progress.textContent = `Order ${orderIndex + 1} of ${activeOrders.length} · ${fed} of ${order.items.length} bites`;
    clues.replaceChildren(...order.items.map((food, index) => {
      const clue = document.createElement('span');
      clue.className = 'order-card';
      clue.textContent = food.emoji;
      if (order.colorClue) clue.classList.add('red-clue');
      clue.classList.toggle('served', index < fed);
      clue.classList.toggle('current', index === fed);
      return clue;
    }));
  }

  function resetFood() {
    fed = 0;
    bowl.querySelectorAll('.bowl-food').forEach((item) => item.remove());
    foodTray.querySelectorAll('button').forEach((button) => { button.disabled = false; button.classList.remove('used'); });
  }

  function startRound() {
    activeOrders = chooseOrders();
    orderIndex = 0;
    showRound();
  }

  function showRound() {
    resetFood();
    feedback.textContent = '';
    nextOrder.hidden = true;
    const takingOrders = mode === 'orders';
    clues.hidden = !takingOrders;
    repeatOrder.hidden = !takingOrders || !soundEnabled || !('speechSynthesis' in window);
    game.classList.toggle('taking-orders', takingOrders);
    if (takingOrders) {
      speech.textContent = activeOrders[orderIndex].prompt;
      renderOrder();
      speakOrder();
    } else {
      if ('speechSynthesis' in window) window.speechSynthesis.cancel();
      speech.textContent = selectedPet.welcome;
      progress.textContent = `Food: 0 of ${foods.length}`;
    }
  }
  pets.forEach((item) => {
    const button = document.createElement('button');
    button.className = 'pet-choice'; button.type = 'button'; button.dataset.pet = item.name;
    button.setAttribute('aria-label', `Choose ${item.name}`); button.setAttribute('aria-pressed', String(item === selectedPet));
    button.innerHTML = `<span>${item.emoji}</span><small>${item.name}</small><b class="pet-lock" hidden aria-hidden="true">🔒</b>`;
    button.addEventListener('click', () => choosePet(item)); petPicker.append(button);
  });
  foods.forEach((item) => {
    const button = document.createElement('button');
    button.className = `food-choice ${item.color}`;
    button.type = 'button';
    button.setAttribute('aria-label', `${item.color} ${item.name}`);
    button.innerHTML = `<span aria-hidden="true">${item.emoji}</span><small>${item.name}</small>`;
    button.addEventListener('click', () => feed(item, button)); foodTray.append(button);
  });
  newGame.addEventListener('click', () => {
    const unlocked = pets.slice(0, unlockedPetCount());
    choosePet(unlocked[Math.floor(Math.random() * unlocked.length)]);
  });
  modeButtons.forEach((button) => button.addEventListener('click', () => {
    if (mode === button.dataset.mode) return;
    mode = button.dataset.mode;
    modeButtons.forEach((option) => option.setAttribute('aria-pressed', String(option === button)));
    startRound();
  }));
  nextOrder.addEventListener('click', () => {
    if (orderIndex === activeOrders.length - 1) {
      startRound();
    } else {
      orderIndex += 1;
      showRound();
    }
    foodTray.querySelector('button').focus();
  });
  repeatOrder.addEventListener('click', speakOrder);
  if (!AudioContext) {
    soundToggle.hidden = true;
  }
  soundToggle.addEventListener('click', () => {
    soundEnabled = !soundEnabled;
    lastCue += 1;
    soundToggle.setAttribute('aria-pressed', String(soundEnabled));
    soundToggle.querySelector('span').textContent = soundEnabled ? 'on' : 'off';
    repeatOrder.hidden = !soundEnabled || mode !== 'orders' || !('speechSynthesis' in window);
    if (!soundEnabled && 'speechSynthesis' in window) window.speechSynthesis.cancel();
    if (!soundEnabled && voiceAudio) {
      voiceAudio.pause();
      voiceAudio = null;
    }
    if (!soundEnabled && volume) {
      volume.disconnect();
      volume = undefined;
    }
  });
  renderHistory();
  renderAchievements();
  startRound();
})();
