import { expect, test } from '@playwright/test';

test('loads the walkthrough and opens the narrated tour', async ({ page }) => {
  const pageErrors: string[] = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  await page.goto('/');

  await expect(page).toHaveTitle(/Build Signal/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Signal.');
  await expect(page.locator('.hero-image')).toBeVisible();

  await page.getByRole('button', { name: /Hear the walkthrough/ }).click();
  await expect(page.locator('#tour')).toHaveClass(/open/);
  await expect(page.locator('#tour-count')).toContainText('1 / 7');
  expect(pageErrors).toEqual([]);
});

test('prefers Google UK English Male across chapters', async ({ page }) => {
  await page.addInitScript(() => {
    const voices = [
      { name: 'Other English voice', lang: 'en-US', voiceURI: 'other' },
      { name: 'Google UK English Male', lang: 'en-GB', voiceURI: 'google-uk-male' },
    ];
    Object.defineProperty(window, 'speechSynthesis', {
      configurable: true,
      value: { getVoices: () => voices, addEventListener() {}, cancel() {}, speak() {}, pause() {}, resume() {}, paused: false },
    });
    Object.defineProperty(window, 'SpeechSynthesisUtterance', {
      configurable: true,
      value: class { voice = null; rate = 1; pitch = 1; onstart = null; onend = null; onerror = null; },
    });
  });

  await page.goto('/');
  await expect(page.locator('#voice-picker')).toHaveValue('google-uk-male');
  await page.locator('#tour-start').click();
  await page.locator('#tour-next').click();
  await expect(page.locator('#tour-voice-select')).toHaveValue('google-uk-male');
});

test('plays the produced closing recordings', async ({ page }) => {
  await page.goto('/');

  const conversation = page.locator('#conversation-audio');
  const signoff = page.locator('#jason-signoff-audio');
  await expect(conversation).toHaveAttribute('src', '4person-closing-convo.mp3');
  await expect(conversation.locator('track')).toHaveAttribute('src', '4person-closing-convo.vtt');
  await expect(signoff).toHaveAttribute('src', 'jasonlamb-signoff.mp3');
  await page.locator('#finale-play').click();
  await expect.poll(() => conversation.evaluate((audio: HTMLAudioElement) => audio.paused)).toBe(false);
  await expect.poll(() => conversation.evaluate((audio: HTMLAudioElement) => audio.duration)).toBeGreaterThan(290.527);
  await expect.poll(() => conversation.evaluate((audio: HTMLAudioElement) => audio.textTracks[0]?.cues?.length ?? 0)).toBe(76);
  await conversation.evaluate((audio: HTMLAudioElement) => audio.dispatchEvent(new Event('ended')));
  await expect.poll(() => signoff.evaluate((audio: HTMLAudioElement) => audio.paused)).toBe(false);
  await expect.poll(() => conversation.evaluate((audio: HTMLAudioElement) => audio.paused)).toBe(true);
});
