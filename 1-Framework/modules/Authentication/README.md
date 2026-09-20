# Authentication Module

## Current component: PasswordSession 1.0.0

`Jasr\Framework\PasswordSession` is a small reusable password/session primitive extracted from `box/lib/auth.php`. The source app is unchanged. The extraction removes hard-coded timezone, user file and redirect paths, and adds strict session cookies, CSRF, session rotation, inactivity expiration, and caller-supplied persistent throttling. It does not duplicate the source application's registration, user administration or business rules.

Load the framework bootstrap; it loads the class without starting a session. Requires PHP 8.1+, PHP sessions, secure random bytes and password hashing. No storage/config files are owned by this component.

## Interface

```php
$auth = new \Jasr\Framework\PasswordSession($cookieName, $cookiePath, $https, 1800);
$auth->csrf();                  // current token
$auth->validCsrf($token);       // constant-time check, rejects non-strings
$auth->loggedIn();              // active authenticated session
$auth->login($user, $password, $expectedUser, $passwordHash, $allowAttempt);
$auth->logout();                // clear identity, rotate ID and token
```

The constructor must run before output and requires no existing PHP session. Each project must provide a unique session cookie name and correct path. Session fields: `user`, `last`, `csrf`. Cookie flags: HttpOnly, SameSite=Strict, and caller-supplied Secure. The caller must enforce HTTPS, validate input, require CSRF on state-changing requests, and supply a server-side throttling callback. Webstats demonstrates persisted throttling using the shared JSON helper.

No public registration/default credentials. The caller supplies a privately stored username/password hash. Credential changes do not revoke existing sessions automatically. This is not yet a centrally managed user source, SSO, or role system. Future user records/roles must have an explicit migration contract.

## Remaining roadmap

- shared user-record loading and schema
- roles and permissions
- password-change workflow and session revocation
- centrally managed identity integration across projects

Validation: Webstats HTTP suite covers invalid credentials, CSRF, authenticated reports, and session ID rotation. Manual checks: HTTPS Secure/HttpOnly cookie flags, logout, 30-minute inactivity expiry, and limits across fresh browser sessions. Never commit production credentials, session files or user records.
