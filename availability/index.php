<?php
/** Revision 1.0.0 | 2026-09-17 | Initial responsive availability poll interface. */
declare(strict_types=1);
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Availability · Find a day that works</title>
  <link rel="stylesheet" href="styles.css?v=1.0.0"><script src="app.js?v=1.0.0" defer></script>
</head>
<body>
<header><a class="brand" href="./"><span class="brand-icon">✓</span> availability</a><a href="./" class="new-link">+ New event</a></header>
<main>
  <p class="eyebrow">LESS BACK-AND-FORTH. MORE GETTING TOGETHER.</p>
  <h1 id="title">Find a day<br>that works.</h1>
  <p id="description" class="intro">Pick a few dates, share a link, and let everyone weigh in.</p>
  <p id="message" role="status" aria-live="polite"></p>
  <section id="setup" class="panel">
    <div class="section-heading"><div><p class="eyebrow">START SOMETHING</p><h2>Create your event</h2></div><span class="pill">No account needed</span></div>
    <form id="event-form">
      <div class="two-columns"><label>Event name<input name="title" maxlength="150" required placeholder="Family dinner, team meetup…"></label><label>Your name<input name="adminName" maxlength="100" required autocomplete="name" placeholder="The organizer"></label></div>
      <label>A little context <span class="muted">(optional)</span><textarea name="description" maxlength="2000" rows="2" placeholder="Where, what time, or anything people should know"></textarea></label>
      <fieldset><legend>Dates to choose from</legend><div class="date-add"><input id="date-input" type="date" aria-label="Add an event date"><button id="add-date" type="button" class="secondary">+ Add date</button></div><div id="date-chips" class="chips"></div><p class="muted">Choose up to 60 dates. Click a date below to remove it.</p></fieldset>
      <label id="closed-label" class="inline" hidden><input type="checkbox" name="closed"> Close this poll to new or updated responses</label>
      <p id="edit-note" class="muted" hidden>Existing answers stay on retained dates. New dates start unanswered; removing a date discards its answers.</p>
      <button id="save-event" type="submit">Create event &amp; get links →</button><button id="cancel-edit" type="button" class="secondary" hidden>Cancel</button>
    </form>
  </section>
  <div id="poll" hidden>
    <div class="toolbar"><span id="event-meta" class="muted"></span><div><button id="copy-public" class="secondary">Copy invite link</button> <button id="edit-event" class="secondary" hidden>Edit event</button></div></div>
    <section id="private-links" class="panel" hidden><h2>Keep your private links</h2><p class="muted">Save these to return from another device. Only share the invite link with attendees.</p><div id="admin-link-row" hidden><label>Private admin link<input id="admin-link" readonly></label><button id="copy-admin" class="secondary">Copy admin link</button></div><div id="response-link-row" hidden><label>Your private response link<input id="response-link" readonly></label><button id="copy-response" class="secondary">Copy response link</button></div></section>
    <div class="poll-layout">
      <section class="panel vote-panel"><p class="eyebrow">YOUR TURN</p><h2>Your availability</h2><p class="muted">Answer each date, or use a shortcut and change the exceptions. Unanswered dates never count as a yes.</p>
        <form id="vote-form"><label>Your name<input id="voter-name" required maxlength="100" autocomplete="name" placeholder="First and last name"></label><div class="shortcuts"><button type="button" data-fill="yes" class="secondary">Can attend all</button><button type="button" data-fill="no" class="secondary">Can’t attend any</button><button type="button" data-fill="" class="text-button">Clear</button></div><div id="vote-dates"></div><p id="answer-summary" class="muted"></p><button id="save-vote" type="submit">Save my availability</button><p id="vote-state" class="muted" aria-live="polite"></p></form>
      </section>
      <section class="panel results-panel"><div class="section-heading"><div><p class="eyebrow">THE GROUP AT A GLANCE</p><h2>What’s looking good?</h2></div><span class="pill" id="response-count">0 responses</span></div><p id="live-state" class="muted" aria-live="polite">Refreshing every 5 seconds</p><div id="best-summary" class="best-summary"></div><div id="results"></div><p class="legend"><span class="yes-text">✓ Can attend</span> <span class="no-text">✕ Can’t attend</span> <span>— Unanswered</span></p></section>
    </div>
    <section class="panel"><h2>Everyone’s availability</h2><p class="muted">Names and responses are visible to anyone with this event’s invite link.</p><div id="response-table" class="table-scroll" tabindex="0" aria-label="Scroll to see all dates"></div></section>
  </div>
</main>
<footer>availability <span>v1.0.0 · Updated September 17, 2026</span></footer>
</body></html>
