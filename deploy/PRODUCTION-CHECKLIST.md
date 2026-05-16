# WebyMail Production Checklist

## Pre-Go-Live

- [ ] `setup.php` removed from the production host.
- [ ] `config/config.php` permissions are restricted (`600` on Linux, admin-only ACL on Windows).
- [ ] `allow_insecure_imap_cert` is `false`.
- [ ] TLS certificate is valid and auto-renew is enabled.
- [ ] Reverse proxy uses HTTPS redirect from port 80 to 443.
- [ ] Reverse proxy logs are enabled and shipped to central monitoring.
- [ ] `data/`, `config/`, `src/`, `templates/` are not web-accessible.
- [ ] Backup policy for `data/webymail.db` is active and restore-tested.
- [ ] Outbound SMTP and IMAP connectivity tested with real accounts.
- [ ] CAPTCHA and 2FA enabled in production.

## Functional Validation

- [ ] Login succeeds with valid credentials.
- [ ] Login fails with invalid credentials and rate limiting works.
- [ ] 2FA challenge appears and accepts only valid code.
- [ ] Compose/send works with and without attachments.
- [ ] Draft save/resume works.
- [ ] Folder create/rename/delete works for allowed folders.
- [ ] Attachment download works and blocks invalid section values.
- [ ] Setup URL returns forbidden/locked state after install.

## Security Validation

- [ ] `Content-Security-Policy` present and does not use `unsafe-inline` for scripts.
- [ ] `Strict-Transport-Security` present on HTTPS.
- [ ] `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` present.
- [ ] OWASP ZAP baseline scan run on staging/public URL.
- [ ] Findings triaged and high/medium issues fixed before go-live.

## Post-Go-Live (Continuous)

- [ ] Weekly vulnerability scan (ZAP baseline).
- [ ] Daily log review for failed login bursts.
- [ ] Monthly dependency/runtime patch cycle.
- [ ] Quarterly restore drill for SQLite backups.
