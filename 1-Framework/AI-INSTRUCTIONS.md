# AI Development Instructions

These instructions apply to all AI-assisted changes inside `1-Framework` and to projects that adopt framework components.

## Before writing code

1. Read this file, `README.md`, `ROADMAP.md`, and the relevant module README files.
2. Inspect existing modules before creating new functionality.
3. Prefer reuse or extension over duplicate implementations.
4. Identify project-specific assumptions before extracting code from another project.
5. Do not modify a source project during component extraction unless explicitly requested.

## Architecture rules

- Keep framework code independent of individual projects.
- Use `bootstrap.php` as the primary framework entry point.
- Keep modules loosely coupled and document every dependency.
- Put shared infrastructure in `core/` only when multiple modules genuinely require it.
- Put optional capabilities in `modules/`.
- Keep frontend assets in `assets/css/` and `assets/js/`.
- Store runtime data outside publicly browsable paths whenever hosting permits.
- Never commit real passwords, secrets, tokens, session data, or production user records.

## PHP rules

- Use `declare(strict_types=1);` in new PHP files.
- Use `require_once` only in the bootstrap or module initialization layer.
- Prefer typed functions, explicit return types, and small focused classes.
- Escape browser output and validate all external input.
- Use `password_hash()` and `password_verify()` for passwords.
- Use secure session cookie settings and regenerate session IDs after login.
- Do not suppress errors with `@`.
- Do not expose stack traces or filesystem paths to end users.

## JSON rules

- Read and write JSON through shared framework helpers once they exist.
- Validate decoded data before use.
- Use file locking and atomic replacement for writes.
- Preserve backwards compatibility or provide an explicit migration.
- Follow `docs/JSON-STANDARD.md` for API responses.

## Change requirements

Every functional change should include:

- updated documentation
- configuration examples without secrets
- input and failure handling
- a changelog entry when behavior changes
- tests or a documented manual validation procedure

## Prohibited shortcuts

- Do not create a second authentication system when one already exists.
- Do not hard-code project URLs, filesystem paths, usernames, or credentials.
- Do not silently change a public function or JSON response contract.
- Do not copy an entire project into the framework and call it reusable.
- Do not replace working framework conventions with a library merely because it is fashionable this week.