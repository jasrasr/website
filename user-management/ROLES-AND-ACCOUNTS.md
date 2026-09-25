# Roles, project scope, profiles, and requested accounts

## Authentication today

Login is handled by PHP against `data/directory.json`. Passwords are stored as `password_hash()` hashes and checked with `password_verify()`. The browser receives an HttpOnly PHP session cookie (`JASR_IDENTITY` by default), not the user database or password. The shared session has a 30-minute inactivity timeout, CSRF protection, login throttling, rotation on login/logout, and session-version checks after security changes.

`data/.htaccess` denies direct HTTP access to the JSON records, locks and backups on Apache/LiteSpeed. It is **not HTTP Basic Authentication** and does not prompt for credentials. Nginx needs equivalent server rules. There is no database server, OAuth/OIDC, cross-domain SSO, email verification, or persistent remember-me login in this service.

## Three separate decisions

| Setting | Meaning |
| --- | --- |
| Account role | `user`, `admin`, or `super_admin`: the highest project privilege the account can receive. |
| Project scope | Explicit grants to any number of projects, or the separately selected `allProjects` flag. Role alone never grants projects. |
| Central directory management | Explicit `directoryAdmin` permission to administer central users, roles, grants, project registration and account linking. Requires Super Admin, all-project access, and a non-demo account. |

A Super Admin restricted to selected projects cannot administer the central directory or grant themselves more scope. An Admin can administer assigned projects but does not manage central identities. A User can have read/write or read-only access to multiple assigned projects.

Project grant levels are `viewer`, `member`, `admin`, and `super_admin`. A User is capped at `member`, an Admin at `admin`, and a Super Admin at `super_admin`. Existing `viewer`/`member`/`admin` gates remain compatible. Apps that distinguish super admins can use `requireProject('project-id', 'super_admin')`.

All-project access means the account's maximum role on every **registered** project, including future registrations; it overrides individual grants. To restrict someone to selected projects, turn that flag off and set their explicit grants. Unknown project IDs and unknown gate roles still deny access. Lowering an account role caps its existing grants immediately and invalidates sessions; the stored grants are retained, so review them before raising the role again.

An app still owns its fine-grained rules. Project access is not automatic ownership of every budget, gallery or household. The Finances pilot requires a verified account link even for a central directory manager.

## Requested account provisioning

After deploying, sign in as a central directory manager and use **Jason and demo accounts → Provision requested accounts**. Confirm ownership of an existing `jasrasr` identity first. On a brand-new installation, use `jasrasr` as the first setup username, then use provisioning to add the demos.

| Username | Role | Project scope | Central directory management |
| --- | --- | --- | --- |
| `jasrasr` | Super Admin | Every registered project, including future ones | Yes |
| `demo-user` | User | None initially; explicitly assign isolated demo projects | No |
| `demo-admin` | Admin | None initially; explicitly assign isolated demo projects | No |
| `demo-super-admin` | Super Admin | None initially; explicitly assign isolated demo projects | No |

The action is explicit and authenticated; deployment itself does not create accounts or elevate usernames automatically. Newly created accounts receive independently generated random temporary passwords, shown only in the successful response, with a required first-login password change. Copy them then; if lost, use an administrator reset. The passwords are never committed or stored in plaintext in directory.json.

If `jasrasr` already exists, provisioning preserves its immutable ID, password, project mappings and contact email while assigning the requested role/scope and display name Jason Lamb. Changed security settings revoke its sessions. Existing demo accounts retain their passwords, grants and enabled/disabled state on repeated provisioning. If a demo username belongs to a non-demo account, the complete transaction is rejected rather than taking over that account.

Demo accounts cannot gain all-project scope or central directory control, even through a forged form submission. Their role can be tested only in selected projects. This release does not automatically create sandbox datasets inside every app: grant demos access only after that project's separate migration supplies isolated test data. Do not publish their passwords or point them at private production budgets/galleries. Public demo mode and automatic data reset are separate per-project work.

## Shared profile

Each central account has one immutable ID/username and an editable display name and optional contact email. `Auth::user()` returns the current profile without its password hash, so every integrated project can consume the same identity. Contact email is **unverified** and must not be used to auto-link legacy accounts or claim ticket ownership. Existing project-owned settings/data remain local until explicitly mapped.

## Existing installs and compatibility

Existing boolean `admin:true` records represented actual site-wide administrators. They are normalized to Super Admin + all-project access + directory management to preserve those existing explicit rights. Existing non-admin accounts with an admin project grant become Admin accounts while retaining only their existing scope; ordinary users remain User. Normalization is read-compatible and is persisted on the next directory write. No usernames, password hashes, IDs or mappings are replaced.

The legacy returned `admin` boolean remains a compatibility alias for **central directory management**, not for the new account role. New integrations must use `can()`/`requireProject()` for project access, not this boolean. New UI-created accounts default to User with no project access and no directory management. Legacy form submissions using `admin` without an explicit `account_role` retain their prior meaning for existing trusted callers; an explicit role selection does not silently inherit that checkbox.

Keep at least one active non-demo central directory manager. The last-manager safeguard applies to disable/demotion actions. Record actual live provisioning and project grants privately; the repository only contains the provisioning code and documentation.

Linking permission rule: add missing project access only after permission review; retain the lower existing permission and enforce a per-link ceiling even for global super admins. Finances requires an explicit viewer/member choice because its legacy login has no enforced roles. Mapping and new membership commit together. See [ACCOUNT-LINKING.md](ACCOUNT-LINKING.md).
