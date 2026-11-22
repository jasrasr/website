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
  const orders = [
    { prompt: 'Something red, please!', items: [apple], clues: ['🔴'] },
    { prompt: 'Two carrots, please!', items: [carrot, carrot] },
    { prompt: 'Apple, banana, apple. In that order!', items: [apple, banana, apple] },
  ];
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
  let selectedPet = pets[0];
  let mode = 'free';
  let orderIndex = 0;
  let fed = 0;

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
    const order = orders[orderIndex];
    if (food !== order.items[fed]) {
      feedback.textContent = 'Try the food in the highlighted picture!';
      return;
    }

    fed += 1;
    addBite(food);
    renderOrder();
    if (fed < order.items.length) {
      feedback.textContent = 'Yum! What comes next?';
      return;
    }

    const finished = orderIndex === orders.length - 1;
    feedback.textContent = finished ? 'You filled all three orders! 🎉' : 'Order complete! ⭐';
    nextOrder.textContent = finished ? 'Play again' : 'Next order';
    nextOrder.hidden = false;
    nextOrder.focus();
    foodTray.querySelectorAll('button').forEach((button) => { button.disabled = true; });
  }

  function renderOrder() {
    const order = orders[orderIndex];
    progress.textContent = `Order ${orderIndex + 1} of ${orders.length} · ${fed} of ${order.items.length} bites`;
    clues.replaceChildren(...order.items.map((food, index) => {
      const clue = document.createElement('span');
      clue.textContent = order.clues?.[index] ?? food.emoji;
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
    orderIndex = 0;
    showRound();
  }

  function showRound() {
    resetFood();
    feedback.textContent = '';
    nextOrder.hidden = true;
    clues.hidden = mode === 'free';
    if (mode === 'orders') {
      speech.textContent = orders[orderIndex].prompt;
      renderOrder();
    } else {
      speech.textContent = selectedPet.welcome;
      progress.textContent = `Food: 0 of ${foods.length}`;
    }
  }
  pets.forEach((item) => {
    const button = document.createElement('button');
    button.className = 'pet-choice'; button.type = 'button'; button.dataset.pet = item.name;
    button.setAttribute('aria-label', `Choose ${item.name}`); button.setAttribute('aria-pressed', String(item === selectedPet));
    button.innerHTML = `<span>${item.emoji}</span><small>${item.name}</small>`;
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
  newGame.addEventListener('click', () => choosePet(pets[Math.floor(Math.random() * pets.length)]));
  modeButtons.forEach((button) => button.addEventListener('click', () => {
    if (mode === button.dataset.mode) return;
    mode = button.dataset.mode;
    modeButtons.forEach((option) => option.setAttribute('aria-pressed', String(option === button)));
    startRound();
  }));
  nextOrder.addEventListener('click', () => {
    orderIndex = (orderIndex + 1) % orders.length;
    showRound();
    foodTray.querySelector('button').focus();
  });
})();
