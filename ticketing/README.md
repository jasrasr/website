# Ticketing

A lightweight Freshservice-style ticketing starter built with PHP, JavaScript, CSS, and JSON file storage.

## Current features

- Dashboard counts for Open, Pending, Resolved, Closed, and Total tickets
- Create new tickets
- Edit existing tickets
- Add comments/activity to tickets
- Statuses: Open, Pending, Resolved, Closed
- Priorities: Low, Medium, High, Urgent
- Requester name and email
- Category and assigned agent fields
- Search across ticket content
- Filter by status and priority
- Responsive desktop/mobile layout
- JSON persistence in `data/tickets.json`
- File locking during JSON writes
- Direct browser access to the data folder blocked by `.htaccess`

## Files

- `index.php` — page markup plus JSON API endpoints
- `app.js` — client-side UI and ticket behavior
- `styles.css` — responsive interface styling
- `data/tickets.json` — ticket database
- `data/.htaccess` — blocks direct web access to JSON ticket data

## Hosting requirements

- PHP 8.1+
- `ticketing/data/` must be writable by PHP
- Apache/Hostinger hosting should honor `.htaccess`

## API

The browser uses `index.php?api=1`.

- `GET` — list tickets
- `POST` — create a ticket
- `PUT` — update a ticket and optionally add a comment

## Suggested next features

Potential next layers include authentication and roles, users/requesters, agents/groups, ticket types, configurable categories/subcategories, attachments, email notifications, SLA/due dates, notes vs replies, audit history, canned responses, tags, ticket merging, reporting, dashboard charts, asset linking, and knowledge-base integration.
