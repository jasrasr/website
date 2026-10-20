# Four-voice ElevenLabs production guide

`four-voice-dialogue-upload.txt` is the upload-ready script for the Build Signal post-credits scene. It contains **36 spoken turns and 714 spoken words**, with no speaker names inside the speech. `four-voice-dialogue-by-speaker.md` separates those same turns by character and gives their order. At about 140 spoken words per minute, the scene targets **about 5.1 minutes**; generated duration varies with voice, pauses, and delivery. Preview the render and adjust pacing if an exact 5:00 runtime is needed.

For the paragraph-by-paragraph selection workflow shown in ElevenLabs Studio, use **`four-voice-dialogue-ordered-for-studio.txt`** instead. Each paragraph starts with one initial: `J:` for Jimmy, `L:` for Laura, `C:` for Charlie, and `R:` for Roger. The **last `J:` paragraph is Jason's sign-off**, not Jimmy's. It is a **voice-assignment worksheet**, not final spoken copy: remove every initial prefix before final generation, or Studio may speak it.

## Import and assign voices

1. In the current ElevenCreative Studio, create an audio project and upload or paste `four-voice-dialogue-ordered-for-studio.txt` as a TXT document. Each blank-line-separated paragraph is one turn.
2. Highlight each paragraph and assign its prefixed initial to the corresponding voice. The four speaking roles repeat in this order for all nine groups of four:

   | Turn in each group | Character | Direction |
   | --- | --- | --- |
   | 1 | Jimmy Glick | American male; theatrical talk-show host energy |
   | 2 | Laura | Female; enthusiastic, quirky, warm |
   | 3 | Charlie | Australian male; deep, confident, energetic |
   | 4 | Roger | American male; laid-back, casual, resonant |

   For example, turns 1, 5, and 9 (`J:`) are Jimmy; turns 4, 8, and 12 (`R:`) are Roger. The final paragraph (`J:`) is Jason's own authorized cloned voice. The prefixes are only visual cues; the TXT does **not** automatically assign speakers on import. Choose voices you are authorized to use; the character directions do not require impersonating any performer.
3. After assigning each paragraph, remove its `X:` prefix from Studio's text. Check the first paragraph before doing all 37 in case Studio resets a voice assignment when text changes. Alternatively, use the name-free `four-voice-dialogue-upload.txt` for the first 36 turns and `jason-corralling-signoff.txt` for the last turn, referring to the initialed worksheet alongside it.
4. Preview the first four turns and the Jason handoff to check separation, voice assignments, pronunciation, and bracketed performance cues. If Studio speaks a cue literally, remove that cue from the project text before generating the final audio. Add a short pause between turns, and a longer beat after turn 36.
5. Generate and export the completed conversation. Listen to the entire export and check its actual duration. The five-minute target applies to the four-person conversation; Jason's sign-off adds time. A 5:00 target may require timing adjustments in Studio.

The webpage now plays `4person-closing-convo.mp3`, which includes Jason's sign-off in the final caption cues. `jasonlamb-signoff.mp3` is available through a separate button. The player uses `4person-closing-convo.vtt` for captions and offers `4person-closing-convo.srt` for download. The linked script files are production text; compare them with the exported recording before calling them verbatim transcripts.

Keep any ElevenLabs API key out of the TXT, exported audio metadata, and webpage source.
