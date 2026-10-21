<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for 1-Framework.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Shared Framework TODO

## Documented plans

- [ ] Finish the foundation items in [ROADMAP.md](ROADMAP.md): configuration loading, error handling, JSON storage with locking, API responses, and validation.
- [ ] Extract logging and audit modules with schemas, retention, and request IDs.
- [ ] Establish shared frontend components and a blank-project scaffold with upgrade and rollback guidance.

## Code-informed follow-up

- [ ] Reconcile the roadmap's still-open JSON/API/authentication entries with the existing `JsonStore`, `Response`, `PasswordSession`, and `SharedIdentity` implementations; split unfinished behavior from completed foundations before closing checklist items.
