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
