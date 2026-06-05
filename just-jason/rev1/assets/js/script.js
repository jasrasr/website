/*
  Project: Just Jason Jamboree Junction
  File: assets/js/script.js
  Revision: 1.1.0
  Author: Jason Lamb / ChatGPT
  Created: 2026-06-02
  Modified: 2026-06-02
  Description: Adds repeatable Jason text blocks and simulates an infinite Jason stream.
*/

(function () {
  'use strict';

  // Main DOM references used by the Jason generator.
  const stream = document.getElementById('jasonStream');
  const countElement = document.getElementById('jasonCount');
  const addButton = document.getElementById('addJason');
  const shuffleButton = document.getElementById('shuffleJason');

  // Different safe sentence patterns. Every visible word remains Jason-adjacent.
  const sentencePatterns = [
    '<strong>Jason</strong> Jason Jason Jason Jason Jason Jason Jason. <em>Jason Jason Jason.</em>',
    'Just Jason Jamboree Junction: Jason Jason Jason Jason; Jason Jason Jason Jason Jason.',
    'Jason Jason Jason Jason Jason — Jason Jason Jason Jason Jason Jason.',
    'Jason? Jason. Jason! Jason Jason Jason Jason Jason Jason Jason.',
    'Jason Jason Jason Jason Jason Jason Jason Jason Jason Jason Jason Jason.',
    'Jolly Jason joins the Jamboree Junction. Jason Jason Jason Jason Jason.'
  ];

  // Returns one paragraph made of randomized Jason sentence patterns.
  function createJasonParagraph(sentenceCount) {
    const sentences = [];

    for (let index = 0; index < sentenceCount; index += 1) {
      const randomIndex = Math.floor(Math.random() * sentencePatterns.length);
      sentences.push(sentencePatterns[randomIndex]);
    }

    return sentences.join(' ');
  }

  // Adds a card to the stream. More cards means more Jason. This is science-adjacent.
  function addJasonCard() {
    const card = document.createElement('article');
    const sentenceCount = Math.floor(Math.random() * 5) + 4;

    card.className = 'jason-card';
    card.innerHTML = createJasonParagraph(sentenceCount);
    stream.appendChild(card);

    updateJasonCount();
  }

  // Clears the existing cards and creates a new set.
  function shuffleJasonCards() {
    stream.innerHTML = '';

    for (let index = 0; index < 7; index += 1) {
      addJasonCard();
    }
  }

  // Counts visible instances of the word Jason across the page body.
  function updateJasonCount() {
    const matches = document.body.innerText.match(/Jason/g) || [];
    countElement.textContent = matches.length.toLocaleString();
  }

  // Adds more Jason automatically when the user nears the bottom of the page.
  function handleInfiniteScroll() {
    const pixelsFromBottom = document.documentElement.scrollHeight - window.innerHeight - window.scrollY;

    if (pixelsFromBottom < 420) {
      addJasonCard();
    }
  }

  // Initial Jason load.
  shuffleJasonCards();

  // Button events.
  addButton.addEventListener('click', addJasonCard);
  shuffleButton.addEventListener('click', shuffleJasonCards);

  // Passive scroll listener keeps the page responsive while generating more Jason.
  window.addEventListener('scroll', handleInfiniteScroll, { passive: true });
})();
