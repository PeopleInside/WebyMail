# Security Policy

## Reporting a Vulnerability

You can report a vulnerability at https://helpdesk.peopleinside.it
You can use the live chat or the ticket system.

## Security Fixes

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
