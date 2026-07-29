# JSON Standard

## API response envelope

Framework-backed endpoints should return a predictable top-level structure:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": [],
  "meta": {
    "timestamp": "2026-07-29T11:00:00Z",
    "requestId": "example-request-id"
  }
}
```

## Field rules

- `success` is always a Boolean.
- `message` is a concise human-readable summary.
- `data` contains the requested resource or result and may be an object, array, scalar, or `null`.
- `errors` is always an array. It is empty for successful responses.
- `meta.timestamp` uses ISO 8601 format.
- `meta.requestId` supports correlation between browser errors, application logs, and audit events.

## Error objects

```json
{
  "code": "VALIDATION_FAILED",
  "field": "email",
  "message": "A valid email address is required."
}
```

Do not expose stack traces, credentials, private paths, internal exception messages, or sensitive record contents.

## Storage JSON

Runtime JSON files are not required to use the API response envelope. Each storage file must have a documented schema and should include a schema version when future migrations are plausible.

Example:

```json
{
  "schemaVersion": 1,
  "updatedAt": "2026-07-29T11:00:00Z",
  "records": []
}
```

## File-writing requirements

JSON storage helpers must eventually provide:

- UTF-8 output
- explicit decoding error handling
- file locking
- atomic writes through a temporary file and rename
- optional backup before destructive schema migration
- stable formatting suitable for troubleshooting
