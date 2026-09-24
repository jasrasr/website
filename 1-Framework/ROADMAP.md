# Framework Roadmap

## Phase 1 — Foundation

- [x] Define repository structure
- [x] Add AI development instructions
- [x] Define architecture and JSON response conventions
- [ ] Create configuration loader
- [ ] Create error and exception handler
- [ ] Create JSON file read/write helper with locking
- [ ] Create common API response helper
- [ ] Add basic validation and manual test procedure

## Phase 2 — Observability

- [ ] Extract reusable application logging
- [ ] Define log levels and retention
- [ ] Extract audit-event recording
- [ ] Define audit event schema
- [ ] Add request or correlation IDs

## Phase 3 — Identity and access

- [ ] Inventory authentication implementations in existing projects
- [x] Define the shared user-record schema
- [x] Implement login, logout, and session validation
- [x] Add roles and permissions
- [x] Add password-change workflow
- [x] Add rate limiting and lockout controls
- [x] Decide how projects consume one centrally managed user source

## Phase 4 — Shared frontend

- [ ] Establish baseline CSS variables and layout
- [ ] Add shared JavaScript utilities
- [ ] Create accessible form and message components
- [ ] Create a reusable authenticated navigation component

## Phase 5 — Project scaffolding

- [ ] Create a blank-project template
- [ ] Add a PowerShell scaffolding script
- [ ] Add environment validation
- [ ] Add framework version metadata
- [ ] Document upgrade and rollback procedures

## Candidate future modules

- Notifications
- File uploads
- Search
- Settings
- Feature flags
- Scheduled jobs
- Backup and restore
- Data migration
- Health checks
- Administrative dashboard

## Extraction rule

An existing component is not framework-ready until project-specific paths, names, branding, credentials, data schemas, and assumptions have been removed or made configurable.
Identity work is provided by the sibling `user-management` project through the framework’s configurable `SharedIdentity` adapter. Existing app migration/ownership mapping remains explicit, per app.
