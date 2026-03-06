<?php
/**
 * Setup wizard – runs on first visit when no config exists.
 * @var string $step     current step: 'welcome' | 'server' | 'done'
 * @var string $error
 */
$brandName = function_exists('appName') ? appName() : Config::get('app_name', 'WebyMail');
?>
<div class="wm-auth-page">
    <div class="wm-auth-box" style="max-width:520px">

        <!-- Theme toggle -->
        <div style="position:fixed;top:1rem;right:1rem">
            <button class="theme-toggle" style="border-color:var(--wm-border);color:var(--wm-text-muted)"><!-- JS --></button>
        </div>

        <div class="wm-auth-logo">
            <h1>Weby<span>Mail</span></h1>
            <p>Initial Setup Wizard &mdash; v<?= Config::VERSION ?></p>
        </div>

        <!-- Steps indicator -->
        <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;justify-content:center">
            <?php
            $steps = ['Welcome', 'Requirements', 'Server', 'Security', 'Done'];
            $stepMap = ['welcome'=>0, 'requirements'=>1, 'server'=>2, 'save'=>2, 'security'=>3, 'done'=>4];
            $stepIdx = $stepMap[$step ?? 'welcome'] ?? 0;
            foreach ($steps as $i => $label):
                $cls = $i <= $stepIdx ? 'var(--wm-primary)' : 'var(--wm-border)';
            ?>
            <div style="display:flex;align-items:center;gap:.4rem">
                <div style="width:24px;height:24px;border-radius:50%;background:<?= $cls ?>;color:<?= $i <= $stepIdx ? 'var(--wm-primary-text)' : 'var(--wm-text-muted)' ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?= $i+1 ?></div>
                <?php if ($stepIdx === $i): ?>
                <span style="font-size:.78rem;color:var(--wm-text);font-weight:600"><?= $label ?></span>
                <?php endif; ?>
                <?php if ($i < count($steps)-1): ?>
                <div style="width:10px;height:2px;background:var(--wm-border);margin:0 .1rem"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (($step ?? 'welcome') === 'welcome'): ?>
        <!-- Step 1: Welcome -->
        <div class="wm-card">
            <div class="wm-card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Welcome to <?= htmlspecialchars($brandName) ?>
            </div>
            <div class="wm-card-body">
                <p style="font-size:.9rem;margin-top:0">
                    This wizard will help you configure <?= htmlspecialchars($brandName) ?>. Your settings will be saved
                    as a PHP file on the server — <strong>no database server required</strong>.
                </p>
                <ul style="font-size:.85rem;color:var(--wm-text-muted);line-height:1.8">
                    <li>All data stored in a local SQLite file</li>
                    <li>IMAP login to your existing mail server</li>
                    <li>Two-Factor Authentication support</li>
                    <li>Multi-account management</li>
                    <li>6-month persistent sessions</li>
                </ul>
                <form method="post" action="?action=setup">
                    <input type="hidden" name="step" value="requirements">
                    
                    <div style="margin: 1.5rem 0; padding: 1rem; background: var(--wm-bg-subtle); border: 1px solid var(--wm-border); border-radius: 8px;">
                        <p style="font-size: .85rem; margin-top: 0; color: var(--wm-text);">
                            <strong>Disclaimer:</strong> WebyMail is provided "as is." While we strive for security, you use this software at your own risk.
                        </p>
                        <label style="font-size: .85rem; display: flex; align-items: center; gap: .5rem; cursor: pointer;">
                            <input type="checkbox" name="accept_disclaimer" required>
                            I accept the terms and conditions
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem">
                        Continue →
                    </button>
                </form>
            </div>
        </div>

        <?php elseif (($step ?? '') === 'requirements'): ?>
        <!-- Step 1.5: Requirements -->
        <div class="wm-card">
            <div class="wm-card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                System Requirements
            </div>
            <div class="wm-card-body">
                <p style="font-size:.9rem;margin-top:0">
                    Checking for necessary PHP extensions...
                </p>
                <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.5rem">
                    <?php
                    $needed = ['imap', 'pdo_sqlite', 'openssl', 'mbstring', 'iconv'];
                    $allOk = true;
                    foreach ($needed as $ext):
                        $ok = extension_loaded($ext);
                        if (!$ok) $allOk = false;
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem;background:var(--wm-bg-subtle);border:1px solid var(--wm-border);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:.75rem">
                            <div style="color:<?= $ok ? 'var(--wm-success)' : 'var(--wm-danger)' ?>">
                                <?php if ($ok): ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                <?php else: ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                <?php endif; ?>
                            </div>
                            <span style="font-family:var(--wm-font-mono);font-size:.85rem"><?= $ext ?></span>
                        </div>
                        <?php if (!$ok): ?>
                        <span style="font-size:.72rem;color:var(--wm-danger);font-weight:600">MISSING</span>
                        <?php else: ?>
                        <span style="font-size:.72rem;color:var(--wm-success);font-weight:600">OK</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$allOk): ?>
                <div class="alert alert-danger" style="font-size:.82rem">
                    Some required extensions are missing. Please install them using your package manager (e.g., <code>apt install php-imap</code>) and restart your web server.
                </div>
                <?php endif; ?>

                <form method="post" action="?action=setup">
                    <input type="hidden" name="step" value="requirements">
                    <?php if (!$allOk): ?>
                    <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;font-size:.82rem;cursor:pointer">
                        <input type="checkbox" name="ignore_requirements" value="1"> I understand the risks and want to continue anyway
                    </label>
                    <?php endif; ?>
                    <div style="display:flex;gap:.75rem">
                        <a href="?action=setup" class="btn btn-ghost" style="flex:1">← Back</a>
                        <button type="submit" class="btn btn-primary" style="flex:2" <?= !$allOk ? 'id="req-submit" disabled' : '' ?>>
                            Continue to Server Config →
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            const ignoreCheck = document.querySelector('input[name="ignore_requirements"]');
            const submitBtn = document.getElementById('req-submit');
            if (ignoreCheck && submitBtn) {
                ignoreCheck.addEventListener('change', function() {
                    submitBtn.disabled = !this.checked;
                });
            }
        </script>

        <?php elseif (($step ?? '') === 'server'): ?>
        <!-- Step 2: Server config -->
        <div class="wm-card">
            <div class="wm-card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                Mail Server Settings
            </div>
            <div class="wm-card-body">
                <form method="post" action="?action=setup" enctype="multipart/form-data">
                    <input type="hidden" name="step" value="save">

                    <p style="font-size:.82rem;color:var(--wm-text-muted);margin-top:0">
                        These are your <strong>default</strong> server settings. Users can override
                        them on the login page. Leave blank to let users enter their own.
                    </p>

                    <div class="form-group">
                        <label>Application name</label>
                        <input type="text" name="app_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['app_name'] ?? Config::get('app_name', 'WebyMail')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone" class="form-control">
                            <?php
                            $currentTz = $_POST['timezone'] ?? Config::get('timezone', 'Europe/Rome');
                            $timezones = DateTimeZone::listIdentifiers();
                            foreach ($timezones as $tz):
                            ?>
                                <option value="<?= htmlspecialchars($tz) ?>" <?= $tz === $currentTz ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tz) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Favicon (ico, png, svg)</label>
                        <?php if ($fav = Config::get('favicon_path')): ?>
                            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                                <div style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:var(--wm-bg-subtle);border:1px solid var(--wm-border);border-radius:6px">
                                    <img src="<?= htmlspecialchars($fav) ?>" style="max-width:24px;max-height:24px;object-fit:contain">
                                </div>
                                <label style="font-size:.82rem;color:var(--wm-danger);cursor:pointer;display:flex;align-items:center;gap:.3rem;margin:0">
                                    <input type="checkbox" name="remove_favicon" value="1"> 
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    Remove current favicon
                                </label>
                            </div>
                        <?php endif; ?>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <input type="file" name="favicon" id="favicon-input" class="form-control" accept=".ico,.png,.svg">
                            <button type="button" id="clear-favicon" class="btn btn-ghost btn-sm" style="display:none;white-space:nowrap;padding:.2rem .5rem;font-size:.75rem">✕ Clear</button>
                        </div>
                        <p class="form-hint">Optional. Upload a custom icon for the browser tab. If none is set, 📧 will be used.</p>
                    </div>

                    <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <legend style="font-size:.82rem;font-weight:600;padding:0 .5rem;color:var(--wm-text-muted)">IMAP (incoming)</legend>
                        <div style="display:grid;grid-template-columns:1fr 90px;gap:.75rem">
                            <div class="form-group" style="margin:0">
                                <label>Host</label>
                                <input type="text" name="imap_host" class="form-control"
                                       placeholder="mail.example.com"
                                       value="<?= htmlspecialchars($_POST['imap_host'] ?? Config::get('imap_host', '')) ?>">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label>Port</label>
                                <input type="number" name="imap_port" class="form-control"
                                       value="<?= htmlspecialchars($_POST['imap_port'] ?? Config::get('imap_port', '993')) ?>">
                            </div>
                        </div>
                        <label style="margin-top:.5rem">
                            <input type="checkbox" name="imap_ssl" value="1"
                                   <?= (($_POST['step'] ?? '') !== 'save' ? Config::get('imap_ssl', true) : !empty($_POST['imap_ssl'])) ? 'checked' : '' ?>>
                            Use SSL/TLS
                        </label>
                    </fieldset>

                     <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                         <legend style="font-size:.82rem;font-weight:600;padding:0 .5rem;color:var(--wm-text-muted)">SMTP (outgoing)</legend>
                         <div style="display:grid;grid-template-columns:1fr 90px;gap:.75rem">
                             <div class="form-group" style="margin:0">
                                 <label>Host</label>
                                <input type="text" name="smtp_host" class="form-control"
                                       placeholder="mail.example.com"
                                       value="<?= htmlspecialchars($_POST['smtp_host'] ?? Config::get('smtp_host', '')) ?>">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label>Port</label>
                                <input type="number" name="smtp_port" class="form-control"
                                       data-smtp-port
                                       value="<?= htmlspecialchars($_POST['smtp_port'] ?? Config::get('smtp_port', '465')) ?>">
                            </div>
                        </div>
                        <div style="display:flex;gap:1.5rem;margin-top:.5rem;flex-wrap:wrap">
                                 <label>
                                     <input type="checkbox" name="smtp_ssl" value="1"
                                            data-smtp-ssl
                                            <?= (($_POST['step'] ?? '') !== 'save' ? Config::get('smtp_ssl', true) : !empty($_POST['smtp_ssl'])) ? 'checked' : '' ?>>
                                     SSL (port 465)
                                 </label>
                                 <label>
                                     <input type="checkbox" name="smtp_starttls" value="1"
                                            data-smtp-starttls
                                            <?= (($_POST['step'] ?? '') !== 'save' ? Config::get('smtp_starttls', false) : !empty($_POST['smtp_starttls'])) ? 'checked' : '' ?>>
                                     STARTTLS (port 587)
                                 </label>
                             </div>
                     </fieldset>

                    <div class="form-group" style="margin-top:1rem">
                        <label style="font-weight:600">Login protection</label><br>
                        <label style="font-size:.85rem;color:var(--wm-text-muted)">
                            <input type="checkbox" name="captcha_enabled" value="1"
                                   <?= (($_POST['step'] ?? '') !== 'save' ? Config::get('captcha_enabled', true) : !empty($_POST['captcha_enabled'])) ? 'checked' : '' ?>>
                            Require proof-of-work captcha on login
                        </label>
                    </div>

                    <div class="form-group" style="margin-top:1rem">
                        <label style="font-weight:600">Login page options</label><br>
                        <label style="font-size:.85rem;color:var(--wm-text-muted)">
                            <input type="checkbox" name="hide_server_on_login" value="1"
                                   <?= (($_POST['step'] ?? '') !== 'save' ? Config::get('hide_server_on_login', true) : !empty($_POST['hide_server_on_login'])) ? 'checked' : '' ?>>
                            Hide server settings by default
                        </label>
                        <p class="form-hint" style="font-size:.78rem;color:var(--wm-text-muted);margin:.35rem 0 0">
                            If enabled, users will only see Email and Password fields. Server settings will be hidden in a collapsed menu.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save & Finish →</button>
                </form>
            </div>
        </div>

        <?php elseif (($step ?? '') === 'security'): ?>
        <!-- Step 2.5: Security Check -->
        <div class="wm-card">
            <?php
            $allSecurityOk = true;
            foreach ($securityChecks as $check) {
                if (empty($check['ok'])) {
                    $allSecurityOk = false;
                    break;
                }
            }
            ?>
            <div class="wm-card-header" style="justify-content:space-between">
                <div style="display:flex;align-items:center;gap:.5rem">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Security & Permissions Check
                </div>
                <form method="post" action="?action=setup">
                    <input type="hidden" name="step" value="fix_permissions">
                    <button type="submit" class="btn btn-outline btn-xs" <?= $allSecurityOk ? 'disabled style="cursor:not-allowed;opacity:0.6"' : '' ?>>Fix Permissions</button>
                </form>
            </div>
            <div class="wm-card-body">
                <p style="font-size:.9rem;margin-top:0">
                    Verifying file permissions and server security...
                </p>
                
                <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.5rem;max-height:300px;overflow-y:auto;padding-right:5px">
                    <?php 
                    $allSecurityOk = true;
                    $insecure = array_filter($securityChecks, fn($c) => !$c['ok']);
                    if (empty($insecure)): ?>
                    <div style="padding:1.25rem;text-align:center;color:var(--wm-success);font-size:.85rem">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom:.5rem"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <br>All files and folders have correct permissions.
                        <div style="margin-top:1rem">
                            <button type="button" class="btn btn-ghost btn-xs" onclick="document.getElementById('all-perms-setup').style.display='block';this.style.display='none'">View All Checked Permissions</button>
                        </div>
                    </div>
                    <?php else:
                    foreach ($insecure as $check): 
                        if (!$check['ok']) $allSecurityOk = false;
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem;background:var(--wm-bg-subtle);border:1px solid var(--wm-border);border-radius:8px">
                        <div style="display:flex;align-items:center;gap:.75rem">
                            <div style="color:var(--wm-warning)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </div>
                            <div>
                                <div style="font-family:var(--wm-font-mono);font-size:.85rem"><?= htmlspecialchars($check['path']) ?></div>
                                <?php if (isset($check['perms'])): ?>
                                <div style="font-size:.7rem;color:var(--wm-text-muted)">Current perms: <?= $check['perms'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span style="font-size:.72rem;color:var(--wm-warning);font-weight:600">INSECURE</span>
                    </div>
                    <?php endforeach; ?>
                    <div style="padding:.75rem;text-align:center">
                        <button type="button" class="btn btn-ghost btn-xs" onclick="document.getElementById('all-perms-setup').style.display='block';this.style.display='none'">View All Checked Permissions</button>
                    </div>
                    <?php endif; ?>

                    <div id="all-perms-setup" style="display:none;border-top:1px solid var(--wm-border);padding-top:.75rem">
                        <?php foreach ($securityChecks as $check): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem;border-bottom:1px solid var(--wm-border);font-size:.75rem">
                            <div style="font-family:var(--wm-font-mono)"><?= htmlspecialchars($check['path']) ?></div>
                            <div style="display:flex;align-items:center;gap:.75rem">
                                <span style="color:var(--wm-text-muted);font-size:.65rem"><?= $check['perms'] ?></span>
                                <span style="font-size:.6rem;font-weight:700;color:<?= $check['ok'] ? 'var(--wm-success)' : 'var(--wm-warning)' ?>">
                                    <?= $check['ok'] ? 'OK' : 'FAIL' ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!$allSecurityOk): ?>
                <div class="alert alert-warning" style="font-size:.82rem">
                    <strong>Security Warning:</strong> Some files or directories have overly permissive settings. 
                    We recommend setting directories to <code>750</code> and files to <code>640</code>.
                    If <code>.htaccess</code> is missing, ensure your web server is configured to deny access to <code>config/</code> and <code>data/</code>.
                </div>
                <?php endif; ?>

                <form method="post" action="?action=setup">
                    <input type="hidden" name="step" value="finish">
                    <button type="submit" class="btn btn-primary w-100">
                        Finalize Setup →
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- Step 3: Done -->
        <div class="wm-card">
            <div class="wm-card-header" style="color:var(--wm-success)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Setup Complete!
            </div>
            <div class="wm-card-body">
                <p style="font-size:.9rem;margin-top:0">
                    <?= htmlspecialchars($brandName) ?> is ready to use. Log in with your IMAP email credentials.
                </p>
                <div class="alert alert-info" style="font-size:.82rem">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    For security, <code>setup.php</code> has been renamed to <code>setup.php.bak</code>.
                    To run setup again, rename it back and visit <code>setup.php?force=1</code>.
                </div>
                <a href="index.php" class="btn btn-primary w-100">Go to Login →</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// SMTP Port auto-switching
(function() {
    const sslCheck = document.querySelector('[data-smtp-ssl]');
    const tlsCheck = document.querySelector('[data-smtp-starttls]');
    const portInput = document.querySelector('[data-smtp-port]');

    // Favicon clear button
    const favInput = document.getElementById('favicon-input');
    const favClear = document.getElementById('clear-favicon');
    if (favInput && favClear) {
        favInput.addEventListener('change', function() {
            favClear.style.display = this.value ? 'inline-block' : 'none';
        });
        favClear.addEventListener('click', function() {
            favInput.value = '';
            this.style.display = 'none';
        });
    }

    if (!sslCheck || !tlsCheck || !portInput) return;

    sslCheck.addEventListener('change', function() {
        if (this.checked) {
            tlsCheck.checked = false;
            portInput.value = '465';
        } else if (!tlsCheck.checked) {
            portInput.value = '25';
        }
    });

    tlsCheck.addEventListener('change', function() {
        if (this.checked) {
            sslCheck.checked = false;
            portInput.value = '587';
        } else if (!sslCheck.checked) {
            portInput.value = '25';
        }
    });
})();
</script>
