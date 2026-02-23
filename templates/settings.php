<?php
/**
 * Settings page
 * @var string $tab        active tab: profile|security|accounts|appearance
 * @var array  $user       current user row
 * @var array  $accounts   all accounts for user
 * @var array  $flash
 * @var string $totpSecret  (when enrolling 2FA)
 * @var array  $recoveryCodes (when enrolling 2FA)
 */
$tab = $tab ?? 'profile';
?>

<div class="wm-settings-grid">

    <!-- Settings nav -->
    <nav class="wm-settings-nav">
        <a href="?action=settings&tab=profile"    class="<?= $tab==='profile'    ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profile
        </a>
        <a href="?action=settings&tab=security"   class="<?= $tab==='security'   ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
            Security & 2FA
        </a>
        <a href="?action=settings&tab=accounts"   class="<?= $tab==='accounts'   ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Accounts
        </a>
        <a href="?action=settings&tab=appearance" class="<?= $tab==='appearance' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            Appearance
        </a>
    </nav>

    <!-- Settings content -->
    <div class="wm-settings-content">

        <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-dismiss="4000">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- ── Profile ── -->
        <?php if ($tab === 'profile'): ?>
        <h2 style="margin-top:0;font-size:1.1rem">Profile</h2>

        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">Display Name</div>
            <div class="wm-card-body">
                <form method="post" action="?action=settings_save&tab=profile">
                    <div class="form-group">
                        <label for="display_name">Display name</label>
                        <input type="text" id="display_name" name="display_name" class="form-control"
                               value="<?= htmlspecialchars($user['display_name'] ?? '') ?>">
                    </div>
                    <button class="btn btn-primary btn-sm">Save</button>
                </form>
            </div>
        </div>

        <!-- HTML Signature -->
        <div class="wm-card">
            <div class="wm-card-header">Email Signature</div>
            <div class="wm-card-body">
                <form method="post" action="?action=settings_save&tab=signature" id="sig-form">
                    <input type="hidden" name="signature" id="sig-hidden">
                    <div id="sig-editor" style="min-height:150px;border:1px solid var(--wm-border);border-radius:7px;overflow:hidden">
                        <div id="sig-quill" style="min-height:120px"></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.75rem">Save Signature</button>
                </form>
            </div>
        </div>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css">
        <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
        <script>
        var sigQuill = new Quill('#sig-quill', { theme: 'snow' });
        sigQuill.clipboard.dangerouslyPasteHTML(0, <?= json_encode($user['signature'] ?? '') ?>);
        document.getElementById('sig-form').addEventListener('submit', function() {
            document.getElementById('sig-hidden').value = sigQuill.root.innerHTML;
        });
        </script>

        <!-- ── Security ── -->
        <?php elseif ($tab === 'security'): ?>
        <h2 style="margin-top:0;font-size:1.1rem">Security & Two-Factor Authentication</h2>

        <?php if (!empty($totpSecret)): ?>
        <!-- Enroll 2FA -->
        <div class="alert alert-warning">
            Save your recovery codes somewhere safe before continuing. You will not see them again.
        </div>

        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">Scan QR code with your authenticator app</div>
            <div class="wm-card-body">
                <div class="wm-qr-box">
                    <img src="<?= htmlspecialchars($qrUrl ?? '') ?>" width="200" height="200" alt="QR Code">
                    <p style="font-size:.78rem;color:var(--wm-text-muted);margin:0">
                        Or enter manually: <code><?= htmlspecialchars($totpSecret) ?></code>
                    </p>
                </div>

                <h4 style="font-size:.9rem;margin:1.25rem 0 .5rem">Recovery Codes</h4>
                <p style="font-size:.82rem;color:var(--wm-text-muted)">
                    Store these in a safe place. Each can only be used once.
                </p>
                <div class="recovery-codes" id="recovery-codes-list">
                    <?php foreach ($recoveryCodes as $code): ?>
                    <div class="recovery-code"><?= htmlspecialchars($code) ?></div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-outline btn-sm" data-copy="#recovery-codes-list">Copy codes</button>

                <form method="post" action="?action=settings_save&tab=enable_2fa" style="margin-top:1.25rem">
                    <input type="hidden" name="totp_secret" value="<?= htmlspecialchars($totpSecret) ?>">
                    <input type="hidden" name="recovery_codes" value="<?= htmlspecialchars(json_encode($recoveryCodes)) ?>">
                    <div class="form-group">
                        <label for="verify_code">Enter the 6-digit code from your app to confirm</label>
                        <input type="text" id="verify_code" name="verify_code" class="form-control"
                               inputmode="numeric" maxlength="6" placeholder="000000"
                               style="max-width:160px;text-align:center;letter-spacing:.2em;font-size:1.2rem">
                    </div>
                    <button class="btn btn-primary btn-sm">Enable 2FA</button>
                </form>
            </div>
        </div>

        <?php elseif ((int)($user['totp_enabled'] ?? 0) === 1): ?>
        <!-- 2FA is ON -->
        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header" style="color:var(--wm-success)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Two-Factor Authentication is enabled
            </div>
            <div class="wm-card-body">
                <p style="font-size:.875rem">
                    Your account is protected with TOTP two-factor authentication.
                </p>
                <form method="post" action="?action=settings_save&tab=disable_2fa"
                      onsubmit="return confirm('Are you sure? This will disable 2FA protection.')">
                    <button class="btn btn-danger btn-sm">Disable 2FA</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- 2FA is OFF -->
        <div class="wm-card">
            <div class="wm-card-header">Enable Two-Factor Authentication</div>
            <div class="wm-card-body">
                <p style="font-size:.875rem;margin-top:0">
                    Protect your account with a time-based one-time password (TOTP) authenticator app
                    such as Google Authenticator, Authy, or Bitwarden.
                </p>
                <form method="post" action="?action=settings&tab=security">
                    <input type="hidden" name="start_2fa" value="1">
                    <button class="btn btn-primary btn-sm">Set up 2FA →</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Sessions -->
        <div class="wm-card">
            <div class="wm-card-header">Active Sessions</div>
            <div class="wm-card-body">
                <p style="font-size:.82rem;color:var(--wm-text-muted)">
                    Sessions are kept alive for 6 months.
                </p>
                <form method="post" action="?action=settings_save&tab=revoke_sessions"
                      onsubmit="return confirm('This will sign you out of all devices including this one.')">
                    <button class="btn btn-danger btn-sm">Revoke all sessions</button>
                </form>
            </div>
        </div>

        <!-- ── Accounts ── -->
        <?php elseif ($tab === 'accounts'): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <h2 style="margin:0;font-size:1.1rem">Connected Accounts</h2>
            <a href="?action=settings&tab=accounts&add=1" class="btn btn-primary btn-sm">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add account
            </a>
        </div>

        <?php if (!empty($_GET['add'])): ?>
        <!-- Add account form -->
        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">Add Email Account</div>
            <div class="wm-card-body">
                <form method="post" action="?action=settings_save&tab=add_account">
                    <div class="form-group">
                        <label>Label</label>
                        <input type="text" name="label" class="form-control" placeholder="Work, Personal…" required>
                    </div>
                    <div class="form-group">
                        <label>Email address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username (IMAP/SMTP)</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <legend style="font-size:.78rem;font-weight:600;color:var(--wm-text-muted);padding:0 .5rem">IMAP</legend>
                        <div style="display:grid;grid-template-columns:1fr 90px;gap:.5rem">
                            <div><label>Host</label><input type="text" name="imap_host" class="form-control" placeholder="mail.example.com" required></div>
                            <div><label>Port</label><input type="number" name="imap_port" class="form-control" value="993"></div>
                        </div>
                        <label style="margin-top:.5rem"><input type="checkbox" name="imap_ssl" value="1" checked> SSL/TLS</label>
                    </fieldset>
                    <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <legend style="font-size:.78rem;font-weight:600;color:var(--wm-text-muted);padding:0 .5rem">SMTP</legend>
                        <div style="display:grid;grid-template-columns:1fr 90px;gap:.5rem">
                            <div><label>Host</label><input type="text" name="smtp_host" class="form-control" placeholder="mail.example.com" required></div>
                            <div><label>Port</label><input type="number" name="smtp_port" class="form-control" value="587"></div>
                        </div>
                        <div style="display:flex;gap:1.5rem;margin-top:.5rem">
                            <label><input type="checkbox" name="smtp_ssl" value="1"> SSL</label>
                            <label><input type="checkbox" name="smtp_starttls" value="1" checked> STARTTLS</label>
                        </div>
                    </fieldset>
                    <button class="btn btn-primary btn-sm">Add account</button>
                    <a href="?action=settings&tab=accounts" class="btn btn-outline btn-sm">Cancel</a>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($accounts as $acc): ?>
        <div class="wm-card" style="margin-bottom:1rem">
            <div class="wm-card-header" style="justify-content:space-between">
                <span>
                    <div class="wm-account-avatar" style="display:inline-flex;margin-right:.4rem">
                        <?= strtoupper(substr($acc['email'], 0, 1)) ?>
                    </div>
                    <?= htmlspecialchars($acc['label']) ?>
                    <?php if ($acc['is_primary']): ?><span class="badge badge-primary" style="font-size:.65rem">Primary</span><?php endif; ?>
                </span>
                <?php if (!$acc['is_primary']): ?>
                <form method="post" action="?action=settings_save&tab=delete_account"
                      onsubmit="return confirm('Remove this account?')">
                    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                    <button class="btn btn-ghost btn-sm text-danger" title="Remove">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="wm-card-body" style="padding:.75rem 1.25rem">
                <div style="font-size:.82rem;color:var(--wm-text-muted);display:flex;flex-wrap:wrap;gap:.25rem 1.5rem">
                    <span><?= htmlspecialchars($acc['email']) ?></span>
                    <span>IMAP: <?= htmlspecialchars($acc['imap_host']) ?>:<?= (int)$acc['imap_port'] ?></span>
                    <span>SMTP: <?= htmlspecialchars($acc['smtp_host']) ?>:<?= (int)$acc['smtp_port'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ── Appearance ── -->
        <?php elseif ($tab === 'appearance'): ?>
        <h2 style="margin-top:0;font-size:1.1rem">Appearance</h2>

        <div class="wm-card">
            <div class="wm-card-header">Theme</div>
            <div class="wm-card-body">
                <p style="font-size:.875rem;color:var(--wm-text-muted);margin-top:0">
                    Choose how WebyMail looks. <strong>System</strong> automatically follows your
                    operating system's dark/light preference.
                </p>
                <form method="post" action="?action=settings_save&tab=appearance" id="theme-form">
                    <input type="hidden" name="theme" id="theme-input" value="<?= htmlspecialchars($user['theme'] ?? 'system') ?>">
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                        <button type="submit" name="theme" value="system"
                                onclick="return submitTheme('system')"
                                id="theme-system" class="wm-theme-card">
                            <div class="wm-theme-preview" style="background:linear-gradient(135deg,#fff 50%,#0d1117 50%)"></div>
                            <span>System (auto)</span>
                        </button>
                        <button type="submit" name="theme" value="light"
                                onclick="return submitTheme('light')"
                                id="theme-light" class="wm-theme-card">
                            <div class="wm-theme-preview" style="background:#f0f4f8"></div>
                            <span>Light</span>
                        </button>
                        <button type="submit" name="theme" value="dark"
                                onclick="return submitTheme('dark')"
                                id="theme-dark" class="wm-theme-card">
                            <div class="wm-theme-preview" style="background:#0d1117"></div>
                            <span>Dark</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /grid -->

<style>
.wm-theme-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1rem;
    border: 2px solid var(--wm-border);
    border-radius: 10px;
    background: var(--wm-surface);
    cursor: pointer;
    color: var(--wm-text);
    font-size: .82rem;
    transition: border-color .15s;
    min-width: 90px;
}
.wm-theme-card.active { border-color: var(--wm-primary); }
.wm-theme-preview {
    width: 64px;
    height: 40px;
    border-radius: 6px;
    border: 1px solid var(--wm-border);
}
</style>

<script>
function updateThemeCards() {
    var current = ThemeManager.current();
    document.querySelectorAll('.wm-theme-card').forEach(function(btn) {
        btn.classList.remove('active');
    });
    var active = document.getElementById('theme-' + current);
    if (active) active.classList.add('active');
}
document.addEventListener('DOMContentLoaded', updateThemeCards);

function submitTheme(theme) {
    ThemeManager.apply(theme);
    updateThemeCards();
    document.getElementById('theme-input').value = theme;
    return true;
}

// Sync current theme with server preference on load
(function() {
    var serverTheme = <?= json_encode($user['theme'] ?? 'system') ?>;
    if (['system','light','dark'].indexOf(serverTheme) !== -1) {
        ThemeManager.apply(serverTheme);
    }
})();
</script>
