# Runtime Storage

This folder documents the framework's storage conventions.

Expected runtime subfolders include:

```text
storage/
├── data/
├── logs/
├── audit/
├── cache/
└── backups/
```

Production runtime files should be placed outside the public web root whenever Hostinger's directory layout permits it. Runtime JSON, logs, audit records, sessions, lock files, and backups should not be committed to Git.