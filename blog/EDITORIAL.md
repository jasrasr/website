# Automated project article voice

The project-series articles are automation-authored summaries, not personal statements written by Jason. Use neutral third-person narration in titles, excerpts, body text and image alt text.

- Describe the project, its behavior and its users: “The Budget Tracker puts those amounts into a common view.”
- Avoid first-person narration such as “I built,” “I like,” “my project,” or “we believe.”
- Do not merely replace “I like” with “Jason likes.” Remove unsupported personal opinions and motivations rather than attributing them to another person.
- Preserve factual qualifications, code examples, quoted UI labels, URLs and stable slugs. The letter `I` in a password example is not first-person narration.
- Keep existing WordPress authors and publication dates when correcting text. Source-file contributor metadata identifies file maintenance, not the live WordPress author.

Run `pwsh -File blog/tools/Test-AutomatedVoice.ps1` before publishing or syncing the automated series. This checks the source articles named in `wordpress-state.json`; it is a review aid, not a substitute for reading the copy.
