# WebyMail
A PHP Web Mail Client

> **Disclaimer:** WebyMail is provided "as is." While we strive for security, you use this software at your own risk.

---

### Features:
1. Multi-account with just one login
2. Login CAPTCHA Protection
3. Two Factor Protection
4. Reply-To option during email composing
5. HTML signature
6. A modern look
7. An updated and maintained code
8. Easy to install without use of the SSH terminal
9. Possibility to use without MySQL database. SQL Lite is used and some config are stored in PHP config file.
10. Headers check
11. Folders manager

---

- You can monitor the status of this project here:
https://github.com/PeopleInside/WebyMail/releases

- For any question you can use https://github.com/PeopleInside/WebyMail/discussions

- For security policy you can read the right section: [https://github.com/PeopleInside/WebyMail/security](https://github.com/PeopleInside/WebyMail/security/policy)

## Installation
1. Upload the files to your web server.
2. Ensure the `config/` and `data/` directories are writable by the web server.
3. Visit `setup.php` in your browser to configure the application.
4. After setup, `setup.php` is automatically deleted for security.
   - To re-run setup, re-upload the `setup.php` file and visit `setup.php?force=1`.

## Update existing installation
1. **Critical:** Make a backup before updating. Ensure you have a copy of:
   - Your configuration file: `config/config.php`
   - Your database file (path defined in `config.php`)
   - Your encryption key file: `app_secret.wm` (located next to the database)
2. Replace all WebyMail files by FTP with the new release files.
3. Your data and settings are preserved as long as you keep `config/config.php` and your external data directory.

## Security
- A self-hosted proof-of-work captcha is enabled by default on login. To disable it, set `'captcha_enabled' => false` in `config/config.php`.
- Two factor can be disabled in case of login issue also on `config/config.php`
- The application uses a per-installation `app_secret` for encryption of account passwords, recovery data, and session tokens. For security, this secret is stored in an external file (`app_secret.wm`) in the same secure directory as your database.
- When upgrading, preserve your existing `config/config.php` and the directory containing your database and `app_secret.wm`.
- Existing installations will automatically migrate the `app_secret` from `config/config.php` to the new external file upon first run.

### Production Hardening Checklist
- **HTTPS & Reverse Proxy**: Serve WebyMail exclusively over HTTPS. If running behind a reverse proxy (Cloudflare, Nginx, Traefik), configure `'trusted_proxies'` in `config/config.php` so forwarded HTTPS and client IP headers are trusted only from your proxy.
- **Database Outside Webroot**: Store your SQLite database and `app_secret.wm` outside the web-accessible directory (configurable via setup or **Settings → System & Maintenance** by the primary administrator).
- **Web Server Rules**: On Apache, `.htaccess` files protect `config/`, `data/`, `src/`, and `templates/`. On Nginx or other servers, explicitly deny HTTP access to those directories and to `.db` / `.wm` / `.json` files.
- **Remove `setup.php`**: Ensure `setup.php` is removed once installation is complete.
- **Quick Fix Actions**: If the security banner alerts logged-in users to insecure file permissions or disabled login CAPTCHA, clicking **Fix Permissions** or **Enable CAPTCHA** in **Settings → System & Maintenance** safely applies the recommended hardening automatically.

## Changelog (Recent)

### 3.5.9
- Added missing `debugErrorMessage()` helper in `index.php` so failed login and account validation errors safely return generic messages in production without triggering HTTP 500 errors.
- Hardened `Config::moveDatabase()` (extension checks, control-character filtering, webroot rejection) and restricted database relocation/warning dismissal to the primary administrator (`user_id === 1`), while keeping safe **Fix Permissions** and **Enable CAPTCHA** actions working for any logged-in user via the security alert banner.
- Normalized email/username casing in `Auth` rate-limiting and user lookups (`LOWER(...)`), added session-bound CSRF protection on `login2fa`, sanitized SMTP sender headers (`From:`, `Disposition-Notification-To:`) and folder management inputs, validated account email addresses, and hardened `sanitizeHtml()` against HTML comments and legacy raw-text tags.
- Updated documentation (`README.md` and `SECURITY.md`).

### 3.5.8
- Preserved left-sidebar scroll position on folder navigation and persisted expanded/collapsed unified account states across browser restarts.
- Added non-standard unified folders under Unified Accounts in the sidebar.
- Isolated HTML email and `.eml` attachment previews inside `<iframe sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer">`, hardened SSRF / private-host validation (`HostValidator`), bound existing primary accounts against arbitrary IMAP host login takeover, and hardened `Config::isRequestSecure()` and TOTP enrollment.

**Light mode** 
![Light](https://github.com/user-attachments/assets/b93bbbd8-2350-494b-8125-9b5066266d83)
<img width="1919" height="953" alt="Screenshot 2026-03-01 173625" src="https://github.com/user-attachments/assets/cfcd40ed-5c0b-489a-baea-419f32c0e623" />

**Dark mode**
![Dark](https://github.com/user-attachments/assets/2b7010aa-0f26-444d-b681-95d2a447bb88)
<img width="1919" height="952" alt="Screenshot 2026-03-01 173614" src="https://github.com/user-attachments/assets/da00ceb7-175f-4dbd-ae09-28b91d01e63a" />

---

## Disclaimer

WebyMail is free, open-source software licensed under the [GNU General Public License v3.0](LICENSE) and is provided **"AS IS"** and **"AS AVAILABLE"**, without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose, title, and non-infringement.

In no event shall the authors, maintainers, or contributors be held liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, without limitation, loss of data, lost or undelivered emails, account compromise, service interruption, server misconfiguration, or security breaches) arising in any way out of the use of, or inability to use, this software, even if advised of the possibility of such damages.

By installing and using WebyMail, you acknowledge that you do so **at your own sole risk** and that you are solely responsible for:
- Securing and maintaining your hosting environment, PHP/web server configuration, HTTPS/TLS certificates, and firewall rules.
- Regularly backing up your database, `config/config.php`, and `app_secret.wm` encryption key.
- Verifying the suitability and security of the software for your own environment prior to production use.

