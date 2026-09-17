<?php
/** Revision 1.1.0 | 2026-09-17 | Private contact fields, party details, proposed times and deadlines.
 * History: 1.0.0 — Initial responsive availability poll interface. */
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
  <link rel="stylesheet" href="styles.css?v=1.1.0"><script src="app.js?v=1.1.0" defer></script>
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
      <label>Location <span class="muted">(optional; can be tentative)</span><input name="location" maxlength="300" placeholder="Park, restaurant, address, or still deciding"></label>
      <div class="two-columns"><label>Event time zone<select name="timezone" id="timezone"></select><span class="muted">All proposed times and the voting deadline use this zone. Locked after the first response.</span></label><label>Voting expiration <span class="muted">(optional)</span><input name="expiresLocal" type="datetime-local"><span class="muted">Leave blank for no deadline. Voting closes automatically at this time in the event time zone.</span></label></div>
      <fieldset><legend>Proposed dates and times — not a confirmed schedule</legend><div class="date-add"><input id="date-input" type="date" aria-label="Add an event date"><input id="time-input" type="time" aria-label="Proposed time (optional)"><button id="add-date" type="button" class="secondary">+ Add option</button></div><div id="date-chips" class="chips"></div><p class="muted">Add the same date with different times to compare options, or leave time blank to vote on the day only. Up to 60 options. Click an option below to remove it.</p></fieldset>
      <label id="closed-label" class="inline" hidden><input type="checkbox" name="closed"> Close this poll to new or updated responses</label>
      <p id="edit-note" class="muted" hidden>Existing answers stay on retained dates. New dates start unanswered; removing a date discards its answers.</p>
      <button id="save-event" type="submit">Create event &amp; get links →</button><button id="cancel-edit" type="button" class="secondary" hidden>Cancel</button>
    </form>
  </section>
  <div id="poll" hidden>
    <div class="toolbar"><span id="event-meta" class="muted"></span><div><button id="copy-public" class="secondary">Copy invite link</button> <button id="edit-event" class="secondary" hidden>Edit event</button></div></div>
    <section id="private-links" class="panel" hidden><h2>Keep your private links</h2><p class="muted">Save these to return from another device. Only share the invite link with attendees.</p><div id="admin-link-row" hidden><label>Private admin link<input id="admin-link" readonly></label><button id="copy-admin" class="secondary">Copy admin link</button></div><div id="response-link-row" hidden><label>Your private response link<input id="response-link" readonly></label><button id="copy-response" class="secondary">Copy response link</button></div></section>
    <div id="schedule-note" class="best-summary"></div>
    <div class="poll-layout">
      <section class="panel vote-panel"><p class="eyebrow">YOUR TURN</p><h2>Your availability</h2><p class="muted">Answer each date, or use a shortcut and change the exceptions. Unanswered dates never count as a yes.</p>
        <form id="vote-form">
          <label>Public display name <span class="muted">(required; visible to everyone)</span><input id="voter-name" required maxlength="100" autocomplete="nickname" placeholder="Name others will see"></label>
          <fieldset class="private-fields"><legend>Private contact details (optional)</legend><p id="private-notice" class="muted">Only you and the admin of this event can see these details. Other attendees cannot see them.</p>
            <label>Private name <span class="muted">· only you and this event’s admin</span><input id="private-name" maxlength="100" autocomplete="name" aria-describedby="private-notice" placeholder="Your full name (optional)"></label>
            <label>Phone <span class="muted">· only you and this event’s admin</span><input id="phone" type="tel" maxlength="50" autocomplete="tel" aria-describedby="private-notice" placeholder="Phone number (optional)"></label>
            <label>Email <span class="muted">· only you and this event’s admin</span><input id="email" type="email" maxlength="254" autocomplete="email" aria-describedby="private-notice" placeholder="Email address (optional)"></label>
          </fieldset>
          <fieldset><legend>Your group (optional; visible to everyone)</legend><p class="muted">Include yourself. These counts apply to each option you mark available. Leave blank if not known yet.</p><div class="two-columns"><label>Total adults<input id="adults" type="number" min="0" max="1000" step="1" placeholder="Not specified"></label><label>Total kids<input id="kids" type="number" min="0" max="1000" step="1" placeholder="Not specified"></label></div></fieldset>
          <fieldset><legend>Food to share (optional; visible to everyone)</legend><label>Food type<select id="food-type"><option value="">Not decided</option><option>Main dish</option><option>Side dish</option><option>Dessert</option><option>Snack</option><option>Drinks</option><option>Other</option><option>Not bringing food</option></select></label><label>What are you bringing?<input id="food-note" maxlength="300" placeholder="For example: pasta salad for 8"></label></fieldset><div class="shortcuts"><button type="button" data-fill="yes" class="secondary">Can attend all</button><button type="button" data-fill="no" class="secondary">Can’t attend any</button><button type="button" data-fill="" class="text-button">Clear</button></div><div id="vote-dates"></div><p id="answer-summary" class="muted"></p><button id="save-vote" type="submit">Save my availability</button><p id="vote-state" class="muted" aria-live="polite"></p></form>
      </section>
      <section class="panel results-panel"><div class="section-heading"><div><p class="eyebrow">THE GROUP AT A GLANCE</p><h2>What’s looking good?</h2></div><span class="pill" id="response-count">0 responses</span></div><p id="live-state" class="muted" aria-live="polite">Refreshing every 5 seconds</p><div id="best-summary" class="best-summary"></div><div id="results"></div><p class="legend"><span class="yes-text">✓ Can attend</span> <span class="no-text">✕ Can’t attend</span> <span>— Unanswered</span></p></section>
    </div>
    <section class="panel"><h2>Everyone’s availability</h2><p class="muted">Public display names, availability, group counts, and food contributions are visible to anyone with this event’s invite link. Private names, phone numbers, and emails are never shown here.</p><div id="response-table" class="table-scroll" tabindex="0" aria-label="Scroll to see all dates"></div></section>
    <section id="admin-contacts" class="panel" hidden><h2>Private attendee details</h2><p class="muted">Visible only to this event’s admin. These details are not included in public results.</p><div id="admin-contact-table" class="table-scroll" tabindex="0" aria-label="Private attendee details"></div></section>
  </div>
</main>
<footer>availability <span>v1.1.0 · Updated September 17, 2026</span></footer>
</body></html>
