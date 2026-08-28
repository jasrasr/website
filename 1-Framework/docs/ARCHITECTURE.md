# Framework Architecture

## Dependency direction

```text
Project code
    ↓
Framework bootstrap
    ↓
Core services
    ↓
Optional modules
    ↓
Storage and external services
```

The framework must never import or depend on a specific project.

## Responsibilities

### `bootstrap.php`

The single supported entry point. It loads configuration and will eventually initialize error handling, sessions, core services, and enabled modules.

### `config/`

Contains configuration documentation and safe examples. A deployed `config.php` may contain environment-specific values and should not contain secrets committed to Git.

### `core/`

Contains infrastructure required by several modules, such as configuration loading, JSON storage, validation, request handling, response generation, and error handling.

Core is not a miscellaneous drawer. A feature belongs here only when multiple modules depend on it.

### `modules/`

Contains optional, self-contained capabilities such as authentication, logging, and auditing. Each module must document:

- purpose
- public interface
- dependencies
- configuration keys
- storage files and schemas
- security considerations
- integration example

### `assets/`

Contains framework-owned CSS and JavaScript. Project branding and project-specific behavior remain in the project.

### `storage/`

Contains or points to runtime JSON data, logs, audit records, caches, and lock files. Production storage should be outside the public web root whenever possible.

## Module lifecycle

A module may expose an `init.php` file in the future. The bootstrap will load only enabled modules. Module initialization must be idempotent and must not produce browser output.

## Configuration precedence

The intended precedence is:

1. safe defaults
2. framework configuration
3. project configuration
4. environment-specific overrides

Later layers may override earlier values, but required configuration must be validated before request processing continues.

## Backwards compatibility

Public functions, classes, configuration keys, storage schemas, and API response structures are contracts. Breaking changes require documentation, a migration path, and a framework version change.
