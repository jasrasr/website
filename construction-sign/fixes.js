'use strict';

(() => {
  const status = document.querySelector('#statusMessage');
  const toast = document.querySelector('#toast');
  let toastTimer;

  function showFeedback(message, type = 'success') {
    status.textContent = message;
    status.className = `notice ${type}`;

    toast.textContent = message;
    toast.className = `toast show ${type}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.className = 'toast';
    }, 3200);
  }

  function replaceButton(id) {
    const oldButton = document.querySelector(id);
    const newButton = oldButton.cloneNode(true);
    oldButton.replaceWith(newButton);
    return newButton;
  }

  function setButtonFeedback(button, label) {
    const original = button.dataset.originalLabel || button.textContent;
    button.dataset.originalLabel = original;
    button.textContent = label;
    button.disabled = true;
    window.setTimeout(() => {
      button.textContent = original;
      button.disabled = false;
    }, 1800);
  }

  async function reliableCopy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }

    const helper = document.createElement('textarea');
    helper.value = text;
    helper.setAttribute('readonly', '');
    helper.style.position = 'fixed';
    helper.style.left = '-9999px';
    helper.style.top = '0';
    document.body.appendChild(helper);
    helper.focus();
    helper.select();
    helper.setSelectionRange(0, helper.value.length);

    const copied = document.execCommand('copy');
    helper.remove();
    if (!copied) throw new Error('The browser blocked clipboard access.');
  }

  async function handleCopy(button, type) {
    try {
      const lines = currentLines();
      const text = type === 'embed' ? buildEmbedCode(lines) : buildStandaloneHtml(lines);
      await reliableCopy(text);
      const message = type === 'embed'
        ? 'Embed code copied to the clipboard.'
        : 'Complete standalone HTML copied to the clipboard.';
      setButtonFeedback(button, 'Copied!');
      showFeedback(message);
    } catch (error) {
      document.querySelector('#codeOutput').focus();
      document.querySelector('#codeOutput').select();
      showFeedback('Automatic copying was blocked. The code has been selected; use Copy from the browser menu.', 'error');
      console.error(error);
    }
  }

  function saveBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    if (isIOS) {
      const opened = window.open(url, '_blank');
      if (!opened) {
        URL.revokeObjectURL(url);
        throw new Error('The browser blocked the image tab.');
      }
      window.setTimeout(() => URL.revokeObjectURL(url), 60000);
      return 'PNG opened in a new tab. Touch and hold the image to save it to Photos or Files.';
    }

    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    return 'PNG download started.';
  }

  async function handlePng(button) {
    try {
      const signCanvas = document.querySelector('#signCanvas');
      if (!signCanvas) throw new Error('Sign canvas was not found.');

      const blob = await new Promise((resolve, reject) => {
        signCanvas.toBlob(result => result ? resolve(result) : reject(new Error('PNG creation failed.')), 'image/png');
      });

      const message = saveBlob(blob, 'construction-sign.png');
      setButtonFeedback(button, 'Saved!');
      showFeedback(message);
    } catch (error) {
      showFeedback('The image could not be saved. Try opening the page directly in Chrome or Safari and allow pop-ups/downloads.', 'error');
      console.error(error);
    }
  }

  const embedButton = replaceButton('#copyEmbedButton');
  const standaloneButton = replaceButton('#copyStandaloneButton');
  const pngButton = replaceButton('#downloadPngButton');

  embedButton.addEventListener('click', () => handleCopy(embedButton, 'embed'));
  standaloneButton.addEventListener('click', () => handleCopy(standaloneButton, 'standalone'));
  pngButton.addEventListener('click', () => handlePng(pngButton));
})();
