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
4. After setup, `setup.php` is automatically renamed to `setup.php.bak` for security.
   - To re-run setup, rename it back to `setup.php` and visit `setup.php?force=1`.

## Security
- A self-hosted proof-of-work captcha is enabled by default on login. To disable it, set `'captcha_enabled' => false` in `config/config.php`.
- Two factor can be disabled in case of login issue also on `config/config.php`

**Light mode** 
![Light](https://github.com/user-attachments/assets/b93bbbd8-2350-494b-8125-9b5066266d83)
**Dark mode**
![Dark](https://github.com/user-attachments/assets/2b7010aa-0f26-444d-b681-95d2a447bb88)
