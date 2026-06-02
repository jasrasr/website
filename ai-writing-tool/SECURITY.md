# Security Notes

Revision : 1.0.0  
Updated : 2026-06-01

## Main security rule

Never place an API key in browser JavaScript. Anything in `assets/app.js`, `index.php`, or browser-rendered HTML can be viewed by users.

The API key belongs in:

```text
config/config.php
```

That file is intentionally ignored by Git.

## Included protections

This project includes:

- Server-side API proxy through `api/suggest.php`
- Direct-access block for `config/`
- Direct-access block for `data/`
- Basic per-IP rate limiting
- Input length limit
- Strict JSON request handling
- Output escaping in browser JavaScript

## Abuse risks

This project can still be abused if publicly exposed without additional controls. The two biggest risks are:

1. Someone burns your API credits by repeatedly submitting text.
2. Someone submits sensitive data into the editor and sends it to the AI provider.

The included rate limiting helps, but it is not enterprise-grade protection. For a private tool, add authentication before using it seriously.

## Recommended next hardening steps

- Put the folder behind basic authentication or a login system.
- Restrict access by IP if only you should use it.
- Lower `rate_limit_per_hour` in `config/config.php`.
- Keep `max_input_characters` reasonable.
- Review your AI provider's data handling terms before sending sensitive content.

## Hostinger/shared hosting notes

On shared hosting, `.htaccess` protections usually work when Apache is used. If your host uses LiteSpeed, these Apache-style directives usually still work. If your host disables `.htaccess`, move `config/` and `data/` outside the public web root if possible.
