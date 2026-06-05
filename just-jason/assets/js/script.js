/*
  Project: Just Jason Jamboree Junction
  File: assets/js/script.js
  Revision: 1.4.3
  Updated: 2026-06-04
  Change: Fade is now controlled by two editable constants — fadePercentPerStep and paragraphsPerStep. Default: 5% darker per paragraph.
*/
(function () {
  'use strict';

  const textArea = document.getElementById('jasonText');
  const countOutput = document.getElementById('jasonCount');

  const paragraphsPerBatch = 14;

  // ---- Fade controls ----------------------------------------------------
  // Tweak these two values to change how fast the text fades to black.
  //   fadePercentPerStep = how many percentage points darker each step is
  //                        (5 = drop brightness by 5% of pure white per step).
  //   paragraphsPerStep  = how many paragraphs share a color before stepping.
  //
  // Examples:
  //   fadePercentPerStep=5, paragraphsPerStep=1 -> 5% darker every paragraph
  //                                                (fully black after 20 paragraphs)
  //   fadePercentPerStep=1, paragraphsPerStep=3 -> 1% darker every 3 paragraphs
  //                                                (fully black after 300 paragraphs)
  // -----------------------------------------------------------------------
  const fadePercentPerStep = 5;
  const paragraphsPerStep = 1;

  // Only cover words that actually say "Jason" count toward the tally.
  const coverJasonCount = document.querySelectorAll('.cover-title .is-jason').length;

  // Deterministic pseudo-random seed so the page has a random-looking rhythm
  // without changing every refresh like a caffeinated slot machine.
  let randomSeed = 441444;
  let generatedJasonCount = 0;
  let generatedParagraphCount = 0;
  let isAddingBatch = false;

  function seededRandom() {
    randomSeed = (randomSeed * 1664525 + 1013904223) % 4294967296;
    return randomSeed / 4294967296;
  }

  function getRandomInteger(minimum, maximum) {
    return Math.floor(seededRandom() * (maximum - minimum + 1)) + minimum;
  }

  function chance(probability) {
    return seededRandom() < probability;
  }

  function getGrayForParagraph(paragraphIndex) {
    const stepIndex = Math.floor(paragraphIndex / paragraphsPerStep);
    const brightnessPercent = Math.max(0, 100 - stepIndex * fadePercentPerStep);
    const grayValue = Math.round(255 * brightnessPercent / 100);
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

    // Sprinkle in a small amount of bold / italic / underline accents.
    const styleRoll = seededRandom();
    if (styleRoll < 0.06) {
      word.classList.add('is-bold');
    } else if (styleRoll < 0.11) {
      word.classList.add('is-italic');
    } else if (styleRoll < 0.15) {
      word.classList.add('is-underline');
    }

    // Independent size accent — small / large / huge — for emphasis.
    const sizeRoll = seededRandom();
    if (sizeRoll < 0.05) {
      word.classList.add('is-small');
    } else if (sizeRoll < 0.09) {
      word.classList.add('is-large');
    } else if (sizeRoll < 0.105) {
      word.classList.add('is-huge');
    }

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
    paragraph.style.color = getGrayForParagraph(generatedParagraphCount);

    // Some paragraphs open with a superscript "Jason" — like a citation mark.
    if (chance(0.32)) {
      const superscript = document.createElement('sup');
      superscript.className = 'jason-superscript';
      superscript.textContent = 'Jason';
      paragraph.appendChild(superscript);
      generatedJasonCount += 1;
    }

    const sentenceCount = getRandomInteger(2, 9);

    for (let sentenceIndex = 0; sentenceIndex < sentenceCount; sentenceIndex += 1) {
      const wordCount = getRandomInteger(3, 22);
      addJasonSentence(paragraph, wordCount);
    }

    fragment.appendChild(paragraph);
    generatedParagraphCount += 1;
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
