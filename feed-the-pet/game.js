(() => {
  'use strict';
  const pets = [
    { name: 'Puppy', emoji: '🐶', welcome: 'Woof! I am hungry!' },
    { name: 'Kitten', emoji: '🐱', welcome: 'Meow! Snack time!' },
    { name: 'Bunny', emoji: '🐰', welcome: 'Hop! What is for lunch?' },
    { name: 'Panda', emoji: '🐼', welcome: 'I love a tasty snack!' },
  ];
  const foods = [
    { name: 'Red apple', emoji: '🍎', color: 'red' },
    { name: 'Yellow banana', emoji: '🍌', color: 'yellow' },
    { name: 'Orange carrot', emoji: '🥕', color: 'orange' },
    { name: 'Green peas', emoji: '🫛', color: 'green' },
    { name: 'Blue berry', emoji: '🫐', color: 'blue' },
  ];
  const petPicker = document.querySelector('#pet-picker');
  const foodTray = document.querySelector('#food');
  const pet = document.querySelector('#pet');
  const bowl = document.querySelector('#bowl');
  const speech = document.querySelector('#speech');
  const progress = document.querySelector('#progress');
  const newGame = document.querySelector('#new-game');
  let selectedPet = pets[0];
  let fed = 0;
  function choosePet(nextPet) {
    selectedPet = nextPet;
    pet.textContent = selectedPet.emoji;
    pet.setAttribute('aria-label', selectedPet.name);
    speech.textContent = selectedPet.welcome;
    document.querySelectorAll('.pet-choice').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.pet === selectedPet.name)));
    resetFood();
  }
  function feed(food, button) {
    if (button.disabled) return;
    button.disabled = true;
    button.classList.add('used');
    fed += 1;
    const bite = document.createElement('span');
    bite.className = 'bowl-food';
    bite.textContent = food.emoji;
    bowl.append(bite);
    pet.classList.remove('happy');
    void pet.offsetWidth;
    pet.classList.add('happy');
    progress.textContent = `Food: ${fed} of ${foods.length}`;
    speech.textContent = fed === foods.length ? `${selectedPet.name} is full! Yum! 🎉` : 'Yum! More please!';
    if (fed === foods.length) foodTray.querySelectorAll('button').forEach((foodButton) => { foodButton.disabled = true; });
  }
  function resetFood() {
    fed = 0;
    bowl.querySelectorAll('.bowl-food').forEach((item) => item.remove());
    progress.textContent = `Food: 0 of ${foods.length}`;
    foodTray.querySelectorAll('button').forEach((button) => { button.disabled = false; button.classList.remove('used'); });
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
    button.className = `food-choice ${item.color}`; button.type = 'button'; button.setAttribute('aria-label', item.name);
    button.innerHTML = `<span>${item.emoji}</span><small>${item.name.split(' ')[1]}</small>`;
    button.addEventListener('click', () => feed(item, button)); foodTray.append(button);
  });
  newGame.addEventListener('click', () => choosePet(pets[Math.floor(Math.random() * pets.length)]));
})();
