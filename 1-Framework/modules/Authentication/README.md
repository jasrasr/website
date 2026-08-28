# Authentication Module

## Purpose

Provide a reusable identity and session layer for framework-backed projects.

## Planned responsibilities

- shared user-record loading
- password verification
- login and logout
- secure session management
- current-user lookup
- authentication-required checks
- roles and permissions
- lockout or rate-limiting controls

## Boundaries

This module should answer who the user is and what permissions the user has. Project-specific business rules remain in each project.

No production credentials or user records belong in this folder.