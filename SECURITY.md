# Security Policy

## Reporting a Vulnerability

You can report a vulnerability at https://helpdesk.peopleinside.it
You can use the live chat or the ticket system.

## Security Fixes

### 3.5.9
- **Missing `debugErrorMessage()` helper on login/account error paths.** Added `debugErrorMessage()` in `index.php` so failed login attempts, `add_account`, `edit_account`, and `test_credentials` errors safely return generic messages in production (when `debug` is disabled) instead of throwing an undefined-function fatal error.
- **Database relocation & multi-user privilege hardening.** Restricted `move_database` and `dismiss_db_warning` to the primary administrator (`user_id === 1`), while keeping safe hardening actions (`fix_permissions`, `enable_captcha`) accessible to authenticated users via the security alert banner. `Config::moveDatabase()` now validates target filenames (`.db`, `.sqlite`, `.sqlite3`), strips control characters, and refuses paths inside the webroot.
- **Case-insensitive login rate-limiting & user lookup.** Normalized email/username casing (`LOWER(...)`) in `Auth::loginWithImap()`, `isBanned()`, `recordFailedAttempt()`, and `clearAttempts()` so letter-case variations cannot bypass the 5-attempt lockout window.
- **SMTP sender & folder control-character sanitization.** Stripped `\r`, `\n`, and `\0` from `From:` / `Disposition-Notification-To:` in `SmtpClient::buildRaw()` and from folder management inputs (`create_folder`, `rename_folder`, `delete_folder`); added `FILTER_VALIDATE_EMAIL` validation on `add_account` and `edit_account`.
- **2FA pre-auth CSRF & HTML sanitizer defense in depth.** Added a session-bound `login_2fa_csrf` token on `login2fa`, and updated `sanitizeHtml()` to strip HTML comments and legacy raw-text tags (`noembed`, `noframes`, `xmp`, `plaintext`).

### 3.5.8
- **Sandboxed email & `.eml` rendering.** Replaced Shadow DOM / direct HTML injection with `<iframe sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer">` populated via `srcdoc` across message view, modal `.eml` preview, and standalone `.eml` preview.
- **Trusted proxy HTTPS detection & TOTP enrollment hardening.** `Config::isRequestSecure()` honors `X-Forwarded-*` headers only when `REMOTE_ADDR` matches configured `trusted_proxies`; removed client-modifiable hidden TOTP secret/recovery fields in `settings.php` and compared hashed session tokens with `hash_equals()`.
- **SSRF & IMAP host takeover protection.** Added `HostValidator::validateHostAndPort()` (blocking private/loopback/link-local/metadata ranges unless `allow_private_mail_hosts` is enabled) and prevented login against arbitrary IMAP hosts when `hide_server_on_login` is active or when an existing primary account is bound to another host.

### 3.5.2
- **SMTP command injection (CRLF) in outgoing mail recipients.** `SmtpClient::extractAddress()` did not
  strip CR/LF characters before using the value to build `RCPT TO:<...>` SMTP commands. An authenticated
  user could craft a "To"/"Cc"/"Bcc" value containing `\r\n` to inject additional SMTP commands (e.g. add
  a hidden recipient not visible in the message headers or application logs). Fixed by stripping
  CR/LF/NUL at the start of `extractAddress()`, consistent with the existing `sanitizeHeader()` helper.
- **HTML sanitizer: legacy `background`/`lowsrc` attribute bypass.** `sanitizeHtml()` did not strip the
  obsolete `background` and `lowsrc` HTML attributes, which old browser engines (pre-modern IE/Gecko)
  resolved as `javascript:` URLs. Not exploitable on current browsers, but removed for defense in depth
  and to cover non-standard/legacy clients. These attributes have no legitimate use in HTML5 markup.
- **HTML sanitizer: whitespace-split `javascript:` bypass in `style` attributes.** A `style` value such as
  `background:url(java\nscript:alert(1))` evaded the `javascript:`/`vbscript:`/`expression(...)` substring
  checks because the keyword was split by whitespace. CSS ignores such whitespace inside tokens, so the
  check now normalizes whitespace before testing for dangerous keywords; not exploitable on current
  browsers (modern engines no longer execute `javascript:` from `url()`), fixed for defense in depth.
