<?php
/** @var array|null $challenge  POW captcha challenge payload */
/** @var string $error     Login error message */
/** @var bool $needs2fa    Show 2FA step instead of password */
$brandName = function_exists('appName') ? appName() : Config::get('app_name', 'WebyMail');
?>
<div class="wm-auth-page">
    <div class="wm-auth-box">
        <!-- Logo -->
        <div class="wm-auth-logo">
            <h1><?= htmlspecialchars($brandName) ?></h1>
            <p><?= htmlspecialchars($brandName) ?> — Secure Web Mail Client</p>
        </div>

        <!-- Theme toggle (top-right of card) -->
        <div style="position:absolute;top:1rem;right:1rem">
            <button class="theme-toggle" style="border-color:var(--wm-border);color:var(--wm-text-muted)"><!-- JS --></button>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($needs2fa ?? false): ?>
        <!-- ── Step 2: Two-Factor Authentication ── -->
        <div class="wm-card">
            <div class="wm-card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                Two-Factor Authentication
            </div>
            <div class="wm-card-body">
                <p style="font-size:.875rem;color:var(--wm-text-muted);margin-top:0">
                    Enter the 6-digit code from your authenticator app, or a recovery code.
                </p>
                <form method="post" action="?action=login2fa">
                    <div class="form-group">
                        <label for="code">Authentication code</label>
                        <input type="text" id="code" name="code" class="form-control"
                               autocomplete="one-time-code" inputmode="numeric"
                               maxlength="10" autofocus placeholder="000000"
                               style="text-align:center;letter-spacing:.2em;font-size:1.3rem">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Verify</button>
                </form>
                <p style="font-size:.78rem;text-align:center;margin-top:.75rem;color:var(--wm-text-muted)">
                    Lost access? Enter a recovery code above.
                </p>
            </div>
        </div>

        <?php else: ?>
        <!-- ── Step 1: IMAP Login ── -->
        <div class="wm-card">
            <div class="wm-card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Sign in to your mailbox
            </div>
            <div class="wm-card-body">
                <form method="post" action="?action=login" id="login-form">

                    <div class="form-group">
                        <label for="username">Email address</label>
                        <input type="email" id="username" name="username" class="form-control"
                               autocomplete="email" autofocus required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="you@example.com">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                   autocomplete="current-password" required placeholder="••••••••">
                            <button type="button" class="btn btn-outline" id="pw-toggle" title="Show/hide">
                                <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Collapsible server settings -->
                    <details style="margin-bottom:1rem">
                        <summary style="font-size:.82rem;color:var(--wm-text-muted);cursor:pointer;padding:.3rem 0">
                            ▸ Server settings (auto-detect or customise)
                        </summary>
                        <div style="margin-top:.75rem;display:grid;gap:.75rem">
                            <div style="display:grid;grid-template-columns:1fr 80px;gap:.5rem;align-items:end">
                                <div>
                                    <label for="imap_host">IMAP host</label>
                                    <input type="text" id="imap_host" name="imap_host" class="form-control"
                                           value="<?= htmlspecialchars($_POST['imap_host'] ?? Config::get('imap_host', '')) ?>"
                                           placeholder="mail.example.com">
                                </div>
                                <div>
                                    <label for="imap_port">Port</label>
                                    <input type="number" id="imap_port" name="imap_port" class="form-control"
                                           value="<?= htmlspecialchars($_POST['imap_port'] ?? Config::get('imap_port', '993')) ?>">
                                </div>
                            </div>
                            <div>
                                <label>
                                    <input type="checkbox" name="imap_ssl" value="1"
                                           <?= (!isset($_POST['imap_ssl']) || !empty($_POST['imap_ssl'])) ? 'checked' : '' ?>>
                                    Use SSL/TLS
                                </label>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 80px;gap:.5rem;align-items:end">
                                <div>
                                    <label for="smtp_host">SMTP host</label>
                                    <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                                           value="<?= htmlspecialchars($_POST['smtp_host'] ?? Config::get('smtp_host', '')) ?>"
                                           placeholder="mail.example.com">
                                </div>
                                <div>
                                    <label for="smtp_port">Port</label>
                                    <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                                           data-smtp-port
                                           value="<?= htmlspecialchars($_POST['smtp_port'] ?? Config::get('smtp_port', '587')) ?>">
                                </div>
                            </div>
                            <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
                                <label>
                                    <input type="checkbox" name="smtp_ssl" value="1"
                                            data-smtp-ssl
                                            <?= (!empty($_POST['smtp_ssl'])) ? 'checked' : '' ?>>
                                    SSL (port 465)
                                </label>
                                <label>
                                    <input type="checkbox" name="smtp_starttls" value="1"
                                            data-smtp-starttls
                                            <?= (!isset($_POST['smtp_starttls']) || $_POST['smtp_starttls']) ? 'checked' : '' ?>>
                                    STARTTLS (port 587)
                                </label>
                            </div>
                        </div>
                    </details>

                    <!-- Self-hosted proof-of-work captcha -->
                    <?php if (!empty($challenge)): ?>
                    <div class="form-group" id="pow-widget"
                         data-challenge='<?= htmlspecialchars(json_encode($challenge), ENT_QUOTES, 'UTF-8') ?>'
                         data-endpoint="?action=pow_challenge">
                        <div class="pow-box border rounded p-3" style="display:flex;gap:1rem;justify-content:space-between;align-items:center">
                            <div>
                                <div class="fw-bold">Security check</div>
                                <div class="fs-sm text-muted" data-pow-status>Preparing challenge...</div>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" data-pow-refresh>Refresh</button>
                        </div>
                        <input type="hidden" name="pow_solution" id="pow-solution" value="">
                        <input type="hidden" name="pow_token" id="pow-token" value="">
                        <noscript>
                            <p class="form-hint text-danger">JavaScript is required to complete the security check.</p>
                        </noscript>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-100" style="margin-top:.25rem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Sign in
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <p style="text-align:center;font-size:.75rem;color:var(--wm-text-muted);margin-top:1.25rem">
            WebyMail &mdash; Secure PHP Web Mail Client
        </p>
    </div>
</div>

<script>
// Password show/hide toggle
document.getElementById('pw-toggle')?.addEventListener('click', function() {
    var pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
});

// Auto-fill IMAP/SMTP host from email domain
document.getElementById('username')?.addEventListener('blur', function() {
    var email    = this.value;
    var atPos    = email.indexOf('@');
    if (atPos < 0) return;
    var domain   = email.slice(atPos + 1);
    var imapHost = document.getElementById('imap_host');
    var smtpHost = document.getElementById('smtp_host');
    if (imapHost && !imapHost.value) imapHost.value = 'mail.' + domain;
    if (smtpHost && !smtpHost.value) smtpHost.value = 'mail.' + domain;
});
</script>
<script src="assets/js/pow.js"></script>
