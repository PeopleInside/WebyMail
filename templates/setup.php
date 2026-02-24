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
            <h1><?= htmlspecialchars($brandName) ?></h1>
            <p>Initial Setup Wizard</p>
        </div>

        <!-- Steps indicator -->
        <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;justify-content:center">
            <?php
            $steps = ['Welcome', 'Server', 'Done'];
            $stepIdx = array_search($step ?? 'welcome', ['welcome','server','done']);
            foreach ($steps as $i => $label):
                $cls = $i <= $stepIdx ? 'var(--wm-primary)' : 'var(--wm-border)';
            ?>
            <div style="display:flex;align-items:center;gap:.4rem">
                <div style="width:24px;height:24px;border-radius:50%;background:<?= $cls ?>;color:<?= $i <= $stepIdx ? 'var(--wm-primary-text)' : 'var(--wm-text-muted)' ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?= $i+1 ?></div>
                <span style="font-size:.78rem;color:<?= $i <= $stepIdx ? 'var(--wm-text)' : 'var(--wm-text-muted)' ?>"><?= $label ?></span>
                <?php if ($i < count($steps)-1): ?>
                <div style="width:30px;height:2px;background:var(--wm-border);margin:0 .2rem"></div>
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
                    <input type="hidden" name="step" value="server">
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:.5rem">
                        Continue →
                    </button>
                </form>
            </div>
        </div>

        <?php elseif (($step ?? '') === 'server'): ?>
        <!-- Step 2: Server config -->
        <div class="wm-card">
            <div class="wm-card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                Mail Server Settings
            </div>
            <div class="wm-card-body">
                <form method="post" action="?action=setup">
                    <input type="hidden" name="step" value="save">

                    <p style="font-size:.82rem;color:var(--wm-text-muted);margin-top:0">
                        These are your <strong>default</strong> server settings. Users can override
                        them on the login page. Leave blank to let users enter their own.
                    </p>

                    <div class="form-group">
                        <label>Application name</label>
                        <input type="text" name="app_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['app_name'] ?? $brandName) ?>">
                    </div>

                    <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <legend style="font-size:.82rem;font-weight:600;padding:0 .5rem;color:var(--wm-text-muted)">IMAP (incoming)</legend>
                        <div style="display:grid;grid-template-columns:1fr 90px;gap:.75rem">
                            <div class="form-group" style="margin:0">
                                <label>Host</label>
                                <input type="text" name="imap_host" class="form-control"
                                       placeholder="mail.example.com"
                                       value="<?= htmlspecialchars($_POST['imap_host'] ?? '') ?>">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label>Port</label>
                                <input type="number" name="imap_port" class="form-control"
                                       value="<?= htmlspecialchars($_POST['imap_port'] ?? '993') ?>">
                            </div>
                        </div>
                        <label style="margin-top:.5rem">
                            <input type="checkbox" name="imap_ssl" value="1"
                                   <?= empty($_POST) || !empty($_POST['imap_ssl']) ? 'checked' : '' ?>>
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
                                       value="<?= htmlspecialchars($_POST['smtp_host'] ?? '') ?>">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label>Port</label>
                                <input type="number" name="smtp_port" class="form-control"
                                       data-smtp-port
                                       value="<?= htmlspecialchars($_POST['smtp_port'] ?? '587') ?>">
                            </div>
                        </div>
                        <div style="display:flex;gap:1.5rem;margin-top:.5rem;flex-wrap:wrap">
                                 <label>
                                     <input type="checkbox" name="smtp_ssl" value="1"
                                            data-smtp-ssl
                                            <?= !empty($_POST['smtp_ssl']) ? 'checked' : '' ?>>
                                     SSL (port 465)
                                 </label>
                                 <label>
                                     <input type="checkbox" name="smtp_starttls" value="1"
                                            data-smtp-starttls
                                            <?= empty($_POST) || !empty($_POST['smtp_starttls']) ? 'checked' : '' ?>>
                                     STARTTLS (port 587)
                                 </label>
                             </div>
                     </fieldset>

                    <div class="form-group" style="margin-top:1rem">
                        <label style="font-weight:600">Login protection</label><br>
                        <label style="font-size:.85rem;color:var(--wm-text-muted)">
                            <input type="checkbox" name="altcha_enabled" value="1"
                                   <?= empty($_POST) || !empty($_POST['altcha_enabled']) ? 'checked' : '' ?>>
                            Require proof-of-work captcha on login
                        </label>
                        <p class="form-hint" style="font-size:.78rem;color:var(--wm-text-muted);margin:.35rem 0 0">
                            Disable if the captcha causes trouble; you can re-enable later by editing <code>config/config.php</code>.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save & Finish →</button>
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
                    For security, consider restricting access to <code>setup.php</code>
                    or deleting it after setup is complete.
                </div>
                <a href="index.php" class="btn btn-primary w-100">Go to Login →</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
