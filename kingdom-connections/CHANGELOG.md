# Changelog

## 1.2.3 — 2026-11-20

- Include 10 demo business logos and 10 synthetic representative portraits under versioned public assets.
- Link these to matching seeded demo listings as display-only defaults, without modifying persisted business data or overriding custom images.
- Verify demo-only matching and asset paths in directory tests.

## 1.2.2 — 2026-11-18

- Normalize clipboard whitespace and capitalization in the initial hexadecimal setup password.
- Add Show passwords to the initial setup form for phone users.

## 1.2.1 — 2026-11-18

- Add phone setup for the first super admin using a privately supplied temporary password and a required replacement.

## 1.2.0 — 2026-11-18

- Add a super-admin demo loader with ten businesses, ten customers and ten reviews linked to simulated verified payments.
- Expand isolated demo setup to the same dataset with unique generated credentials and varied services.
- Preserve the private seed plan so retries reuse accounts, listings, payments and reviews.
- Validate dataset counts, distinct reviewers, repeat loading and denial for customers.

## 1.1.0 — 2026-11-18

- Standalone accounts, business-owned listings and photo uploads, customer payment verification and reviews, super-admin record management, and isolated demo setup.

## 1.0.0 — 2026-11-17

- Introduce Kingdom Connections using shared PHP/JSON framework and central project administration.
- Add multi-skill browsing/search and public business profiles with testimony, About, contact details, company logo and employee picture URLs.
- Add 1–5-star admin-recorded feedback, averages, counts and public source labels; reserve customer service connections and verified reviews for a future phase.
- Protect writes using project admin authorization, CSRF, validation, file locking and atomic replacement; preserve ignored host data and private configuration.
