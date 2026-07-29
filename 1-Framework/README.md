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

Framework code must not directly depend on a specific project. Projects may depend on the framework, but the framework must remain portable.