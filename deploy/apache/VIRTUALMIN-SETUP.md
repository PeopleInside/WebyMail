# Apache + Virtualmin Setup Notes

## 1) Deployment mode

Choose one mode:

- Direct PHP (recommended for your stack): use `virtualmin-direct-php.conf` snippets in Virtualmin Apache directives.
- Reverse proxy: use `virtualmin-reverse-proxy.conf` if WebyMail runs on an internal app server.

## 2) Virtualmin steps

1. Open Virtualmin for the target domain.
2. Go to `Server Configuration -> Website Options` and ensure SSL website is enabled.
3. Go to `Server Configuration -> Edit Directives` (Apache) and merge the chosen template directives.
4. In `Server Configuration -> PHP Options`:
   - disable `expose_php`
   - ensure secure cookies (`session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Strict`)
5. Reload Apache from Virtualmin (or `systemctl reload apache2`).

## 3) File permissions

- `config/config.php`: only web user + admin readable.
- `data/webymail.db`: only web user writable.
- Remove or block `setup.php` after installation.

## 4) Post-checks

Run these checks against your domain:

- CSP and security headers present
- `/setup.php` returns 403
- `/config/`, `/data/`, `/src/`, `/templates/` return 403/404
- login flow, compose, attachment download still work

## 5) OWASP ZAP usage

Use `deploy/security/zap-baseline.ps1` from a workstation that can reach the domain:

`PowerShell -ExecutionPolicy Bypass -File .\\deploy\\security\\zap-baseline.ps1 -TargetUrl "https://your-domain.tld" -ReportDir ".\\reports\\zap"`
