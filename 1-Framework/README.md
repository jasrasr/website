<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Shared PHP/JSON framework overview and deployment conventions.
Revision: 1.2.0
Created Date: 2026-09-20
Modified Date: 2026-10-21
Changelog: 1.2.0 - Document config.local.example.php and runtime .placeholder convention.
-->
# 1-Framework

A reusable PHP and JSON foundation for projects in this repository.

The goal is to keep common backend behavior consistent across projects instead of recreating authentication, logging, auditing, configuration, shared JavaScript, and CSS each time.

## Design goals

- PHP-based applications that work on shared hosting
- JSON files as the default persistence layer
- No required database server
- Reusable, loosely coupled modules
- Consistent authentication, authorization, logging, and auditing
- Shared response formats and coding conventions
- Clear instructions for AI-assisted development
- Gradual adoption by existing projects without forcing rewrites

## Initial structure

```text
1-Framework/
├── README.md
├── ROADMAP.md
├── AI-INSTRUCTIONS.md
├── bootstrap.php
├── config/
│   ├── README.md
│   └── config.example.php
├── core/
│   └── README.md
├── modules/
│   ├── Authentication/
│   ├── Audit/
│   └── Logging/
├── assets/
│   ├── css/
│   └── js/
├── docs/
│   ├── ARCHITECTURE.md
│   └── JSON-STANDARD.md
└── storage/
    └── README.md
```

## Current status

This is an architectural baseline. The files define responsibilities and conventions before functional modules are extracted from existing projects.

## Adoption strategy

1. Inventory reusable components in existing projects.
2. Extract one component at a time without modifying the source project.
3. Remove project-specific assumptions.
4. Document the module interface and dependencies.
5. Test the framework copy independently.
6. Integrate it into a small project before broader adoption.

## Important rule

Framework core stays independent of projects. Shared authentication uses an explicitly configured provider; the repository default points to `../user-management/bootstrap.php`. The provider is loaded only when a project calls `SharedIdentity::connect()`.

## Implemented components (2026-09-20)

The initial baseline now includes `JsonStore`, `Response`, and `PasswordSession`.
See [core/COMPONENTS.md](core/COMPONENTS.md),
[Authentication](modules/Authentication/README.md), and [CHANGELOG.md](CHANGELOG.md).
Webstats consumes these helpers; remaining framework roadmap items are still planned.

## Shared user management (1.1.0)

New account-based projects should use [User Management](../user-management/README.md) through `Jasr\Framework\SharedIdentity::connect()`. This replaces the plan for separate per-project user stores with centrally managed users and project roles. Configure `authentication.provider_bootstrap` if the provider is relocated. Existing `PasswordSession` integrations remain supported and are not silently migrated.

See [the integration guide](../user-management/README.md#connect-a-php-project) for server-side gates, CSRF, stable user IDs, and deployment setup.

## Private configuration and directory placeholders

New hosted projects use **`config.local.example.php` → `config.local.php`**: the tracked example retains the entire target filename, and only the `.example` suffix is removed when copying it into place on Hostinger. Do not use `config.example.php` for a runtime file named `config.local.php`. Never commit the real config. For different destination names apply the same pattern (e.g., `settings.local.example.json` → `settings.local.json`).

Each runtime directory that must exist in the deployed source includes a safe, tracked **`.placeholder`**, and the app's `.gitignore` makes an exception for these marker files. The app should auto-create ignored storage/JSON files when safe; secrets and private credentials are instead created or supplied manually on Hostinger. Markers hold no personal data or tokens. Sync/deploy processes must preserve runtime files and must not confuse marker presence with an actual configured secret.

See [AI-INSTRUCTIONS.md](AI-INSTRUCTIONS.md#configuration-templates-and-runtime-placeholders) for the mandatory coding and safety rules.
