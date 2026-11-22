# Feed the Pet

A simple touch-friendly feeding game for young children. Choose a pet, then tap each food to fill the bowl. It is a standalone static page with no account, saved data, or build step.

- **Free Feed** lets children give the pet any five foods from the tray.
- **Pet Orders** chooses one of three rotating sets. Each set contains three picture challenges that practice colors, counting, and patterns. A wrong tap leaves progress intact.

Both modes use short sound cues after a food tap. Use the **Sound** button to mute or unmute them.
Pet Orders also speaks each new order aloud. Tap **Hear again** to replay it.

## Custom voice recordings

Place custom MP3 recordings in [`audio/`](audio/README.md) using these exact filenames:

- `audio/order-red.mp3` — “Something red, please!”
- `audio/order-carrots.mp3` — “Two carrots, please!”
- `audio/order-pattern.mp3` — “Apple, banana, apple. In that order!”
- `audio/order-green-peas.mp3` — “Green peas, please!”
- `audio/order-two-bananas.mp3` — “Two bananas, please!”
- `audio/order-blueberries-apple.mp3` — “Blueberries, then apple!”
- `audio/order-orange.mp3` — “Something orange, please!”
- `audio/order-three-apples.mp3` — “Three apples, please!”
- `audio/order-carrot-apple-carrot.mp3` — “Carrot, apple, carrot. In that order!”

When an MP3 is missing, Pet Orders uses the browser voice. The recording and replacement instructions are annotated in `audio/README.md`.

Completed rounds appear under **This session** and survive reloads in the same browser tab. The game offers an optional link to User Management sign in. Session history stays in the browser tab and does not sync to an account.

Finishing all three Pet Orders earns one achievement. A session starts with Puppy; Kitten unlocks at 5 achievements, Bunny at 10, and Panda at 15.

Open `index.html` locally or serve the website directory with the local host launcher.
