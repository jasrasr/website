# Just Jason Jamboree Junction

Revision 1.4.0

This is a single-page static website. The visible webpage is intentionally dominated by repeated instances of `Jason`.

## Revision 1.4.0 changes

- Changed the page to a black/gray design.
- Replaced chip-style Jason blocks with randomized paragraph-style Jason text.
- Randomized the number of Jason words per sentence and sentence counts per paragraph.
- Added a grayscale fade where body text starts at `#ffffff` and darkens by one grayscale step every 100 generated Jason words.
- Kept infinite scroll.
- Kept the fixed bottom count.

## Fade behavior

The body text uses only grayscale values:

- `#ffffff`
- `#fefefe`
- `#fdfdfd`
- ...
- `#000000`

At 100 Jason words per grayscale step, the body text reaches full black after roughly 25,600 generated Jason words.

## Files

- `index.html` - the single website page
- `assets/css/style.css` - visual layout and colors
- `assets/js/script.js` - randomized Jason paragraph generator, infinite scroll behavior, grayscale fading, and counter
- `.gitignore` - local cleanup rules

## Upload

Upload the contents of this folder to your hosting path, for example:

`/public_html/infinite-jason/`
