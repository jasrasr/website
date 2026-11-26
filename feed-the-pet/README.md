# Feed the Pet

A simple touch-friendly feeding game for young children. Choose a pet, then tap each food to fill the bowl. It is a standalone static page with no account, saved data, or build step.

- **Free Feed** lets children give the pet any five foods from the tray.
- **Pet Orders** chooses three unique challenges from a pool of 18. Puppy gets orders of 1, 1, and 2 foods; Kitten gets 1, 2, and 2; Bunny gets 1, 2, and 3; Panda gets 2, 3, and 3. The pool practices colors, counting, and patterns, and the same trio is not repeated back-to-back. A wrong tap leaves progress intact.

Both modes use short sound cues after a food tap. Use the **Sound** button to mute or unmute them.
Pet Orders also speaks each new order aloud. Tap **Hear again** to replay it.
After a successful order, the next order starts automatically in three seconds. **Next order** can be tapped sooner; **Play again** appears immediately after the final order and waits for a tap.

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
- `audio/order-yellow.mp3` — “Something yellow, please!”
- `audio/order-blue.mp3` — “Something blue, please!”
- `audio/order-two-apples.mp3` — “Two apples, please!”
- `audio/order-two-blueberries.mp3` — “Two blueberries, please!”
- `audio/order-three-carrots.mp3` — “Three carrots, please!”
- `audio/order-banana-apple-banana.mp3` — “Banana, apple, banana. In that order!”
- `audio/order-peas-carrot-peas.mp3` — “Peas, carrot, peas. In that order!”
- `audio/order-banana-peas.mp3` — “Banana, then peas!”
- `audio/order-apple-carrot.mp3` — “Apple, then carrot!”

When an MP3 is missing, Pet Orders uses the browser voice. The recording and replacement instructions are annotated in `audio/README.md`.

The 18-order pool produces 816 unique three-order combinations and 4,896 sequences when order is considered.

Completed rounds appear under **This session** and survive reloads in the same browser tab. The game offers an optional link to User Management sign in. Session history stays in the browser tab and does not sync to an account.

Finishing all three Pet Orders earns one achievement. A session starts with Puppy; Kitten unlocks at 5 achievements, Bunny at 10, and Panda at 15.
At the end of a Pet Orders round, a congratulations card announces the achievement and also names any newly unlocked pet.

Open `index.html` locally or serve the website directory with the local host launcher.
