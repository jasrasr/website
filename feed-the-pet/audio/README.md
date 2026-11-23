# Pet Order Voice Files

Open [`RECORDING-SCRIPT.html`](RECORDING-SCRIPT.html) in a browser and print it for a checklist containing all phrases and filenames.

Store the custom voice recordings in this folder:

| Filename | Spoken words |
| --- | --- |
| `order-red.mp3` | “Something red, please!” |
| `order-carrots.mp3` | “Two carrots, please!” |
| `order-pattern.mp3` | “Apple, banana, apple. In that order!” |
| `order-green-peas.mp3` | “Green peas, please!” |
| `order-two-bananas.mp3` | “Two bananas, please!” |
| `order-blueberries-apple.mp3` | “Blueberries, then apple!” |
| `order-orange.mp3` | “Something orange, please!” |
| `order-three-apples.mp3` | “Three apples, please!” |
| `order-carrot-apple-carrot.mp3` | “Carrot, apple, carrot. In that order!” |
| `order-yellow.mp3` | “Something yellow, please!” |
| `order-blue.mp3` | “Something blue, please!” |
| `order-two-apples.mp3` | “Two apples, please!” |
| `order-two-blueberries.mp3` | “Two blueberries, please!” |
| `order-three-carrots.mp3` | “Three carrots, please!” |
| `order-banana-apple-banana.mp3` | “Banana, apple, banana. In that order!” |
| `order-peas-carrot-peas.mp3` | “Peas, carrot, peas. In that order!” |
| `order-banana-peas.mp3` | “Banana, then peas!” |
| `order-apple-carrot.mp3` | “Apple, then carrot!” |

Folder relative to the website repository:

```text
feed-the-pet/audio/
```

Keep the filenames lowercase and exactly as shown. MP3 is preferred for broad browser support. Replace a recording by overwriting the matching file with a new recording that uses the same filename.

Each achievement round randomly chooses three unique orders from the full pool. If a file is missing or cannot play, the browser voice reads that order instead.
