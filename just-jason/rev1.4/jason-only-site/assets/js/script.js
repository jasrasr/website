/*
  Project: Just Jason Jamboree Junction
  File: assets/js/script.js
  Revision: 1.4.0
  Updated: 2026-06-04
  Change: Generates randomized Jason sentences and paragraphs, then darkens the body text one grayscale step every 100 Jason words.
*/
(function () {
  'use strict';

  const textArea = document.getElementById('jasonText');
  const countOutput = document.getElementById('jasonCount');

  const grayscaleStepSize = 100;
  const paragraphsPerBatch = 18;

  // The four cover words are visible Jason text.
  const coverJasonCount = document.querySelectorAll('.cover-title span').length;

  // Deterministic pseudo-random seed so the page has a random-looking rhythm
  // without changing every refresh like a caffeinated slot machine.
  let randomSeed = 441444;
  let generatedJasonCount = 0;
  let isAddingBatch = false;

  function seededRandom() {
    randomSeed = (randomSeed * 1664525 + 1013904223) % 4294967296;
    return randomSeed / 4294967296;
  }

  function getRandomInteger(minimum, maximum) {
    return Math.floor(seededRandom() * (maximum - minimum + 1)) + minimum;
  }

  function getGrayForBodyJason(bodyJasonNumber) {
    const darkenSteps = Math.min(255, Math.floor((bodyJasonNumber - 1) / grayscaleStepSize));
    const grayValue = 255 - darkenSteps;
    const hexPair = grayValue.toString(16).padStart(2, '0');

    return `#${hexPair}${hexPair}${hexPair}`;
  }

  function getTotalJasonCount() {
    return coverJasonCount + generatedJasonCount;
  }

  function updateJasonCount() {
    if (!countOutput) {
      return;
    }

    countOutput.textContent = getTotalJasonCount().toLocaleString();
  }

  function addJasonWord(sentence) {
    generatedJasonCount += 1;

    const word = document.createElement('span');
    word.className = 'jason-word';
    word.textContent = 'Jason';
    word.style.color = getGrayForBodyJason(generatedJasonCount);

    sentence.appendChild(word);
  }

  function addJasonSentence(paragraph, wordCount) {
    const sentence = document.createElement('span');
    sentence.className = 'jason-sentence';

    for (let index = 0; index < wordCount; index += 1) {
      addJasonWord(sentence);

      if (index < wordCount - 1) {
        sentence.appendChild(document.createTextNode(' '));
      }
    }

    sentence.appendChild(document.createTextNode('. '));
    paragraph.appendChild(sentence);
  }

  function addJasonParagraph(fragment) {
    const paragraph = document.createElement('p');
    paragraph.className = 'jason-paragraph';

    const sentenceCount = getRandomInteger(3, 8);

    for (let sentenceIndex = 0; sentenceIndex < sentenceCount; sentenceIndex += 1) {
      const wordCount = getRandomInteger(4, 18);
      addJasonSentence(paragraph, wordCount);
    }

    fragment.appendChild(paragraph);
  }

  function addJasonBatch() {
    if (isAddingBatch || !textArea) {
      return;
    }

    isAddingBatch = true;

    const fragment = document.createDocumentFragment();

    for (let index = 0; index < paragraphsPerBatch; index += 1) {
      addJasonParagraph(fragment);
    }

    textArea.appendChild(fragment);
    updateJasonCount();
    isAddingBatch = false;
  }

  function nearBottom() {
    return window.innerHeight + window.scrollY >= document.body.offsetHeight - 900;
  }

  function handleScroll() {
    if (nearBottom()) {
      addJasonBatch();
    }
  }

  function startInfiniteScroll() {
    window.addEventListener('scroll', handleScroll, { passive: true });
  }

  // Load enough Jason to immediately look like a page of repeated text.
  addJasonBatch();
  addJasonBatch();
  addJasonBatch();
  updateJasonCount();
  startInfiniteScroll();
})();
