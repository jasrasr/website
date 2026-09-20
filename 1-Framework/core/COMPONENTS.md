# Core components (1.0.0)

Load through `1-Framework/bootstrap.php`. PHP 8.1+; standard JSON extension and local filesystem. No project imports, session startup or data creation happens during class loading.

## `Jasr\Framework\JsonStore`

- `read(string $path): array`: returns a validated `{schemaVersion:1, records:array}` envelope; missing file means empty records. Malformed files throw, without data loss.
- `update(string $path, callable $change): array`: obtains a stable `.lock` file, reads under the lock, passes existing records to the callback, writes its returned array to a same-directory temporary file, and atomically renames it. Adds UTC `updatedAt`. Throws on failures. The private directory must already exist.
- Callers own path validation, private permissions, schema-specific record validation, limits and retention. Use local storage supporting flock/rename. Readers observe complete old or new files; no lock is needed for reads. Do not remove active lock files.
- Shared by Webstats event storage and persisted login/collection throttling. No dependency on authentication.

## `Jasr\Framework\Response`

`json(int $status, string $message, mixed $data=null, array $errors=[]): never` sends the framework JSON envelope with timestamp/request ID and exits. Callers own headers such as CORS/cache policy and must provide public-safe messages. Depends on JSON and secure random bytes; no storage dependencies.

Validation: `node --test webstats/tests/server.test.cjs` exercises storage, deduplication, the response envelope, and persisted limits through actual HTTP requests. For concurrent writer validation, send separate accepted events from multiple PHP workers and confirm every ID is present; a PHP single-process development server serializes requests.
