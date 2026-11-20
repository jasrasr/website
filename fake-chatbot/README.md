<!--
File Name: README.md
Revision: 2.0.0
Description: Setup, privacy, administration and deployment of the deliberately fake AI web portal.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Document framework-backed PHP chat, weighted rules, private storage, optional account linking and protected admin portal. 1.0.0 - Original browser prototype and PowerShell archive.
-->
# Very Fake AI — a deliberately ridiculous but functional chatbot

A **completely fake AI** website. Nothing in this application uses an AI model, an LLM API, or an AI key. It matches visitor questions to **human-written jokes** using deterministic PHP rules. Anyone can chat anonymously; people with existing active **User Management** accounts may explicitly link their browser's saved questions to their account. Only authorized project administrators can edit replies and inspect all visitor questions.

The original PowerShell v1.1 chatbot and its two prompt files are copied **unchanged** into the legacy/ folder. The separate jasrasr/powershell repository remains untouched.

## Links after deployment

- Public portal: https://jasr.me/github/fake-chatbot/
- Admin portal: https://jasr.me/github/fake-chatbot/admin/
- Personal history / account linking: https://jasr.me/github/fake-chatbot/link-history.php

These are deployment targets, **not confirmation the site is already live**. This project uses PHP 8.1+, no external API keys, database, Composer, or runtime Node dependencies.

## 1-Framework and User Management integration

- The application bootstrap loads ../1-Framework/bootstrap.php. All state is saved using Jasr\Framework\JsonStore with its atomic, locked JSON writes.
- Only protected routes use the shared account provider Jasr\Framework\SharedIdentity::connect(). No second account/password database exists.
- Anonymous chatting does **not** use login or require a project registration. The web endpoint is public; the admin endpoint is role-protected.
- Register project ID **fake-chatbot** with its actual URL path **/github/fake-chatbot/** in User Management. This is necessary for safe post-login return links and for admin authorization.
- The existing super administrator (normally jasrasr with all-project privileges) may use /admin/ once the project is registered. Alternatively grant its existing account admin/super_admin rights for the fake-chatbot project.
- Other existing users do **not** need an explicit fake-chatbot project grant to voluntarily link their own history. They must be signed in with an active account and have completed required password changes. This does **not** let them see anyone else's account history or manage rules.
- No new default administrator credentials, accounts, project grants, or passwords are created on deploy.

Use the existing 1-Framework/README.md, 1-Framework/AI-INSTRUCTIONS.md and user-management/README.md conventions for deployment and permission management.

## Persistent data, privacy and hosting

- Tracked data/prompts.json contains the public seed rules. Admin edits go to private storage/rules.json, initialized from the seed only on the first write. Later Git changes do not replace server-edited responses.
- Other private files: storage/history.json (question text, answer, match, time and association), storage/links.json (opted-in browser/account association), and storage/limits.json (request-throttle counts).
- A random HttpOnly SameSite cookie identifies the anonymous browser, and only its SHA-256 hash is stored. No raw IP address, user agent or fingerprint is logged by this application.
- Anonymous questions expire after 90 days and are pruned on subsequent writes. Linked questions remain until their owner deletes them, subject to a global cap of 20,000 retained entries.
- Visitors are warned not to enter confidential information. Authorized project administrators can inspect all submitted questions to teach the joke bot.
- Signing in does NOT automatically link anonymous questions. The user explicitly selects **Link this browser's anonymous history** on the account page. Later questions are saved to that account only while the corresponding central account remains signed in.
- **Clear screen** removes only visible messages. **Delete anonymous history** removes unlinked stored questions for that browser. The account page can unlink future saving or delete all questions owned by that account.
- Default per-cookie rate limits: 20 questions/minute and 300/day; global limits: 120/minute and 6,000/day.

The tracked storage/.placeholder preserves an empty runtime directory. All runtime JSON and lock files are ignored by Git. PHP must have write permission to storage/. **Do not** wipe runtime files during Hostinger sync and include them in secure backups.

### Required HTTP denial verification

storage/.htaccess denies direct public HTTP access on Apache/LiteSpeed. Before accepting public questions, check that a real file under https://jasr.me/github/fake-chatbot/storage/ returns HTTP **403**, not 200. For nginx, add equivalent host-level deny rules or change storage_path to a writable directory **outside the web root**. An unprotected storage folder exposes saved visitor questions and account association IDs; do not launch until this is verified.

An optional ignored config.local.php can be made by copying config.local.example.php (remove ONLY .example). Change base_path if deployed to another subpath, or storage_path if using another private data location. No API configuration is needed.

## Matching and jokes

Each rule has a name, ordered alternate triggers, 1–8 human-written replies, a +/-20 tie-break priority, and an enabled flag. The first tier that matches wins, regardless of rule ordering.

| Tier | Trigger syntax | Behavior |
| --- | --- | --- |
| 1 | exact: what is potato salad | Whole normalized question |
| 2 | phrase: potato salad | Complete contiguous phrase anywhere in question |
| 3 | all: potato salad | Both words occur, in either order and any position |
| 4 | keyword: potato | One complete word anywhere in question |

A phrase outranks a single keyword even with the maximum manual tie-break priority. Longer and more-specific matches are favored within a tier, then administrator priority, then a stable rule ID tie-break.

- "How can I make potato salad?" chooses the potato salad recipe rule.
- "Does salad need potato?" chooses the all-words potato salad rule.
- "What about Mr. Potato Head from Toy Story?" chooses the toy rule.
- "What is a potato?" or simply "potato" chooses the vegetable rule.
- A totally unmatched question receives an obviously fictional fallback and still appears in admin question review.

The initial seed contains **107 rule categories and 143 comedy answers**. Replies are randomly chosen only from human-written alternatives; no question or answer is sent to any AI service. Dynamic {{time}} and {{date}} are simple server-side replacements using America/New_York. Arbitrary code, HTML and the old PowerShell Invoke-Expression execution are **never** evaluated from rule text. All UI output is HTML-escaped.

## Admin features

At /admin/, users with a server-enforced fake-chatbot admin project role can:
- Create, edit, enable/disable, or delete a rule.
- Specify multiple triggers, one per line, beginning with exact:, phrase:, all:, or keyword:.
- Enter several different one-line comedic answers for random variety.
- Set a tie-break priority without disrupting the four specificity tiers.
- Test any question and inspect the winning rule, matching trigger, score, and sample answer.
- Inspect the last 120 captured questions and their historic match IDs, including unmatched prompts; create a new rule from one of them.

The admin forms use the existing central User Management CSRF protection. Editing rules does not alter source files, other account records or historical saved answers.

## Testing

The Fake Chatbot Validation GitHub Actions workflow lints all PHP, runs matching and framework-storage tests, and runs an HTTP smoke test using PHP's built-in server and curl. No third-party packages are required. From the website repo root, run:

    find fake-chatbot -name '*.php' -print0 | xargs -0 -n1 php -l
    php fake-chatbot/tests/matching.test.php

PHP's localhost server does not honor .htaccess, so never use it to validate runtime-storage denial. On Hostinger, manually verify user-role isolation, anonymous history persistence, account opt-in and sign-out behavior, admin rule editing, and HTTP 403 for private JSON.

## Project files

- index.php, assets/app.js, assets/style.css — public website.
- bootstrap.php, api.php — matching logic and server-side history storage.
- link-history.php — voluntary User Management history linking.
- admin/index.php — protected rule CRUD, matching preview and question history.
- data/prompts.json — versioned, human-written seed rules.
- storage/ — ignored private runtime files with Apache/LiteSpeed HTTP denial.
- tests/matching.test.php — matching regression tests.
- legacy/ — untouched PowerShell chatbot and original prompt TXT/JSON data.

**Revision:** 2.0.0 · **Updated:** 2026-11-20
