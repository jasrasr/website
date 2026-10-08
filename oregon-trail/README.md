# Oregon Trail

A small browser game, with automatic device-local saves and editable game content.

## Saved journeys and recovery

- Packing the wagon creates a separate saved journey.
- Every completed action saves immediately, including the exact pending river or landmark choice. Reloading resumes the most recently saved unfinished journey without rerolling the last turn.
- "Start a new journey" keeps the existing journey. Open "Saved journeys on this device" to continue an older game or view a completed one.
- Saves include the party, date, supplies, health, miles, pace, rations, next stop, story, pending choice, and a snapshot of the content rules.
- Saves use browser localStorage on the current origin. They do not sync across devices, browsers, private windows, or domains. Clearing site data removes them. Storage failures appear on screen; unreadable data is not overwritten.
- Setup form drafts are not saved until the wagon is packed.
- Offline play and reload become available once the service worker successfully installs over HTTPS (or localhost). Wait for "Ready for offline play on this device." The initial visit needs connectivity. External fonts are optional; system fonts work offline.
- Active journeys retain their original rules. Content edits apply to new journeys after reloading the page.
- When changing app code, increment the cache version in `sw.js`. Close old game tabs and reopen to activate an updated worker.

## Edit stops, actions and probabilities

Edit [game-content.json](game-content.json). It is a plain-text JSON file; no JavaScript changes are required for the supported content below. JSON requires double quotes and does not allow comments or trailing commas. Bad content is reported without deleting saves; older saved journeys can still be opened with their embedded rules.

### Random travel events

`chancePercent` is an **absolute percentage per travel turn** (one week), not a relative weight. One event at most is selected. Event percentages must total 100 or less. The unused percentage is a quiet travel turn.

Defaults: wagon trouble 12%, illness 9%, food found 10%, quiet travel 69%. Zero disables an event. 100 makes a single event certain.

Add an object to `events`:

```json
{
  "id": "helpful-traveler",
  "chancePercent": 5,
  "title": "A traveler shares supplies.",
  "message": "There is still kindness on the trail.",
  "effects": { "food": [10, 25], "health": 3 }
}
```

With the default events unchanged, adding this 5% event leaves 64% quiet turns.

### Stops and actions

Add stops to `stops` in increasing mile order with unique IDs. `type` is `"stop"` or `"river"`. The final stop must be at `totalMiles`. Rivers use the configurable `crossings` choices. All crossed stops are offered in order, even when one travel turn passes more than one mile marker.

Example additional stop:

```json
{
  "id": "prairie-camp",
  "name": "Prairie Camp",
  "mile": 200,
  "type": "stop",
  "actions": [
    {
      "label": "Search for edible plants",
      "days": 1,
      "successPercent": 60,
      "message": "You find food for the journey.",
      "effects": { "food": [5, 20] },
      "failureMessage": "The search leaves the party tired.",
      "failureEffects": { "health": -3 }
    }
  ]
}
```

An action's `successPercent` is its own chance when selected; it does not contribute to travel-event percentages. If omitted, the action succeeds every time. `days` pass on both success and failure. Actions may be repeated at a stop; leaving the stop completes it. River crossing actions resolve the crossing and leave the stop.

The top-level `actions` list adds options to every trail camp. A stop's `actions` list adds options only at that stop.

Supported effects are `food` (pounds), `health` (points), and `miles`. Positive numbers add; negative numbers remove. An integer range such as `[5,20]` chooses inclusively between 5 and 20. `days` for custom actions can also be an integer range. Health is limited to 0–100; food and miles cannot go below zero. No code from JSON is executed. Health reaching zero ends the journey.

### Hunting and rivers

- `hunting.failurePercent`: chance that a hunting action finds nothing.
- `hunting.food`: inclusive food reward range.
- `hunting.days`: whole days spent hunting.
- `crossings[].successPercent`: chance each crossing method succeeds.
- `crossings[].days`: whole days spent crossing.
- `crossings[].costFood`: optional food cost, paid on either outcome; insufficient food disables that choice.
- `crossingFailure.foodLoss` and `milesLost`: inclusive loss ranges.
- `crossingFailure.travelerLossPercent`: chance of losing one traveler **conditional on a failed crossing**. For example, 40% safe with 25% traveler loss on failure means a 15% overall traveler-loss chance.

The ferry uses food as its cost because this game does not track money.

## Validation

Requires Node.js. The browser recovery test also requires Playwright and Chromium:

```sh
npm install --no-save playwright
npx playwright install chromium
node --test oregon-trail/tests/game.test.cjs
```

Run the rules, storage, runtime recovery, and service-worker tests without browser dependencies:

```sh
node --test oregon-trail/tests/*.test.cjs
```

To include the real Chromium recovery and offline reload test after installing Playwright and Chromium:

```sh
RUN_BROWSER_TESTS=1 node --test oregon-trail/tests/*.test.cjs
```

Tests cover probability boundaries, invalid content, month rollover, preserving separate journeys, unreadable storage, restored pace and river choices, and offline caching and saving. The real-browser test is opt-in.
