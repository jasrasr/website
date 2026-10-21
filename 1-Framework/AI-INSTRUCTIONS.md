<!--
File Name: AI-INSTRUCTIONS.md
Author: Jason Lamb (with help from ChatGPT)
Description: Framework-wide coding and deployment rules for AI-assisted projects.
Revision: 1.1.0
Created Date: 2026-09-20
Modified Date: 2026-10-21
Changelog: 1.1.0 - Standardize removable .example suffixes and tracked runtime placeholders.
-->
# AI Development Instructions

These instructions apply to all AI-assisted changes inside `1-Framework` and to projects that adopt framework components.

## Before writing code

1. Read this file, `README.md`, `ROADMAP.md`, and the relevant module README files.
2. Inspect existing modules before creating new functionality.
3. Prefer reuse or extension over duplicate implementations.
4. Identify project-specific assumptions before extracting code from another project.
5. Do not modify a source project during component extraction unless explicitly requested.

## Architecture rules

- Keep framework core independent of individual projects. Shared identity is the explicit exception: the optional adapter loads the configured user-management provider on demand.
- Use `bootstrap.php` as the primary framework entry point.
- Keep modules loosely coupled and document every dependency.
- Put shared infrastructure in `core/` only when multiple modules genuinely require it.
- Put optional capabilities in `modules/`.
- Keep frontend assets in `assets/css/` and `assets/js/`.
- Store runtime data outside publicly browsable paths whenever hosting permits.
- Never commit real passwords, secrets, tokens, session data, or production user records.

## Configuration templates and runtime placeholders

- **Always preserve the full destination filename and add `.example` immediately before the extension.** For private PHP configuration use `config.local.example.php` (tracked in Git) → copy to `config.local.php` (ignored in Git) on Hostinger, removing only `.example`. Do not create the misleading `config.example.php` when the deployed filename is `config.local.php`.
- For other host-only files, use the same naming rule: e.g. `settings.local.example.json` → `settings.local.json`, not `settings.example.json`. Document exactly what to copy or rename.
- Track a safe **`.placeholder`** in each otherwise empty runtime directory (including required nested directories) and add explicit `.gitignore` exceptions. `.placeholder` files keep folders present in the repository/deployment; they must contain no secrets or user data. Document which actual ignored files are auto-created by application code and which (especially credential configs) must be created manually on Hostinger.
- Never auto-create, reset, publish, or commit a real API key. Copy/rename the tracked example manually on the server, set host-only values there, and protect it from public HTTP access (prefer outside web root or deny rules). `.gitignore` alone does not prevent HTTP access.
- A host sync must preserve private settings, JSON storage, uploaded user files, and `.placeholder` files; do not wipe ignored runtime content. Validate deny rules and write access before deployment.
- If an app offers a deterministic parser and optional paid AI interpretation, make the local parser the no-key default, expose an **explicit** retry-with-AI option when its interpretation is incomplete, and require review of the AI suggestion before saving. Never send user data externally without a deliberate action.

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

- Use `SharedIdentity::connect()` for new account-based projects; centrally managed accounts live in `user-management`. Do not create another user store. Keep `PasswordSession` for existing compatible callers.
- Do not hard-code project URLs, filesystem paths, usernames, or credentials.
- Do not silently change a public function or JSON response contract.
- Do not copy an entire project into the framework and call it reusable.
- Do not replace working framework conventions with a library merely because it is fashionable this week.