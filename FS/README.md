# Freshservice Ticket Tracker

Small PHP/JSON dashboard for tracking Jason's aggregate unresolved Freshservice ticket count toward a goal of zero.

## Current update method

1. Upload a Freshservice screenshot in ChatGPT.
2. Read the unresolved-ticket count and the image's EXIF capture time when available.
3. Append an entry to `data/ticket-counts.json`.
4. Commit the updated JSON file.

The dashboard reads the JSON file and calculates the latest count, change from the previous snapshot, tickets remaining, and reduction from the initial baseline.

## Privacy and security

Only aggregate ticket counts and timestamps belong in this repository. Do not commit Freshservice API keys, individual ticket data, requester names, subjects, or private screenshots.

## Future Freshservice API integration

A server-side PHP collector can call the Freshservice API on a schedule and append counts to the same JSON structure. Store credentials outside the public web root or in server environment variables—never in this repository or client-side JavaScript.
