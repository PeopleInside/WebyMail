# WebyMail Production Deployment

This folder contains production-ready reverse proxy templates and security checks.

## Files

- `deploy/nginx/webymail.conf`: Nginx TLS reverse proxy with hardened headers and structured logs.
- `deploy/caddy/Caddyfile`: Caddy TLS reverse proxy with equivalent hardening.
- `deploy/PRODUCTION-CHECKLIST.md`: Go-live and post-go-live checklist.
- `deploy/security/zap-baseline.ps1`: Automated OWASP ZAP baseline scan for local/staging URLs.

## Backend Assumption

These templates assume WebyMail is served by an internal web server on `127.0.0.1:8080` (Apache or Nginx/PHP-FPM) and exposed publicly only through the reverse proxy.

## Important

- Keep `setup.php` removed from production after installation.
- Keep `allow_insecure_imap_cert=false` in `config/config.php`.
- Set real IMAP/SMTP hosts and credentials in production setup.
- Ensure HTTPS is always enabled at the proxy.
