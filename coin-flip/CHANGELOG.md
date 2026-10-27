<!-- File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Coin Flip cumulative release history.
Revision: 1.0.4
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.4 - Document mobile rendering fix. 1.0.3 - Document face and cross artwork. 1.0.2 - Document clickable coin. 1.0.1 - Document viewport-height layout fix. 1.0.0 - Initial release.
-->
# Changelog

## 1.0.4 - 2026-10-27
- Explicitly hide the inactive face after landing to prevent mirrored labels bleeding through on mobile.
- Add Safari backface visibility and separate the two faces in 3D space.

## 1.0.3 - 2026-10-27
- Heads shows a smiling face; tails shows a cross, both styled as gold coin engravings.
- Replace the hover brightness filter with an outline so the reverse face stays visible in 3D.

## 1.0.2 - 2026-10-27
- Click or tap the coin to flip; the separate button and Space shortcut remain available.
- Coin supports keyboard focus, Enter and Space activation, a hover cue, and disabled state during a toss.

## 1.0.1 - 2026-10-27
- Fit the entire interface to the dynamic viewport height, scaling the coin to the space remaining above the controls.
- Keep the Flip button and footer visible on shorter desktop and mobile screens.

## 1.0.0 - 2026-10-27
- Giant gold two-sided coin, animated toss, and colorful confetti.
- Independent random heads/tails results, a single flip button, and Space shortcut.
- Responsive layout, screen-reader announcements, and reduced-motion support.
