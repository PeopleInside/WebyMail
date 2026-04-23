<?php
/**
 * Settings page
 * @var string $tab        active tab: profile|security|accounts|appearance
 * @var array  $user       current user row
 * @var array  $accounts   all accounts for user
 * @var array|null $account active account row
 * @var array  $flash
 * @var string $totpSecret  (when enrolling 2FA)
 * @var array  $recoveryCodes (when enrolling 2FA)
 */
$tab = $tab ?? 'profile';
$brandName = function_exists('appName') ? appName() : Config::get('app_name', 'WebyMail');
$activeAccount  = is_array($account ?? null) ? $account : null;
$activeSignature = $activeAccount['signature'] ?? ($user['signature'] ?? '');
$activeSender    = $activeAccount['sender_name'] ?? ($user['display_name'] ?? '');
$activeEmail     = $activeAccount['email'] ?? ($user['email'] ?? 'this account');
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
        <a href="?action=settings&tab=system" class="<?= $tab==='system' ? 'active' : '' ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            System
        </a>
    </nav>

    <!-- Settings content -->
    <div class="wm-settings-content">

        <?php if (!empty($flash)): ?>
        <?php $dismissMs = flashDismissMs($flash['type'] ?? ''); ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-dismiss="<?= (int)$dismissMs ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- ── Profile ── -->
        <?php if ($tab === 'profile'): ?>
        <h2 style="margin-top:0;font-size:1.1rem">Profile</h2>

        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">Display Name (account specific)</div>
            <div class="wm-card-body">
                <form method="post" action="?action=settings_save&tab=profile">
                    <?= csrfInput() ?>
                    <div class="form-group">
                        <label for="display_name">Display name</label>
                        <input type="text" id="display_name" name="display_name" class="form-control"
                               value="<?= htmlspecialchars($activeSender) ?>">
                        <p class="form-hint" style="margin-top:.35rem">
                            Shown as the “From” name for <?= htmlspecialchars($activeEmail) ?>.
                        </p>
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
                    <?= csrfInput() ?>
                    <input type="hidden" name="signature" id="sig-hidden">
                    <div id="sig-toolbar" class="wm-editor-group" style="margin-bottom:.5rem;align-items:center;gap:.5rem;flex-wrap:wrap">
                        <button type="button" data-sig-cmd="bold" title="Bold"><b>B</b></button>
                        <button type="button" data-sig-cmd="italic" title="Italic"><i>I</i></button>
                        <button type="button" data-sig-cmd="underline" title="Underline"><u>U</u></button>
                        <button type="button" data-sig-cmd="strikeThrough" title="Strike"><s>S</s></button>
                        <div style="width:1px;height:16px;background:var(--wm-border)"></div>
                        <button type="button" data-sig-cmd="justifyLeft" title="Align left">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                        </button>
                        <button type="button" data-sig-cmd="justifyCenter" title="Align center">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
                        </button>
                        <button type="button" data-sig-cmd="justifyRight" title="Align right">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
                        </button>
                        <div style="width:1px;height:16px;background:var(--wm-border)"></div>
                        <button type="button" data-sig-cmd="createLink" title="Link">Link</button>
                        <span style="display:inline-flex;align-items:center;gap:.25rem">
                            <input type="color" data-sig-cmd="foreColor" value="#2563eb" title="Text color">
                            <button type="button" data-sig-apply-color style="padding:.2rem .45rem">Apply</button>
                        </span>
                        <button type="button" id="sig-toggle-source" class="btn btn-outline btn-sm" style="margin-left:auto">HTML source</button>
                    </div>
                    <div id="sig-editor" contenteditable="true" style="min-height:150px;border:1px solid var(--wm-border);border-radius:7px;overflow:hidden;padding:.75rem;background:var(--wm-surface-2)"></div>
                    <textarea id="sig-source" style="display:none;width:100%;min-height:150px;border:1px solid var(--wm-border);border-radius:7px;padding:.65rem;font-family:monospace;"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.75rem">Save Signature</button>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var toolbar = document.getElementById('sig-toolbar');
            var editor  = document.getElementById('sig-editor');
            var source  = document.getElementById('sig-source');
            var toggle  = document.getElementById('sig-toggle-source');
            editor.innerHTML = <?= json_encode($activeSignature) ?> || '<p><br></p>';
            try { document.execCommand('enableObjectResizing', false, true); } catch (e) {}

            // ── Link popover ──────────────────────────────────────────────────────
            var linkPopover = document.createElement('div');
            linkPopover.style.cssText = 'position:fixed;z-index:9999;display:none;align-items:center;gap:.4rem;' +
                'background:var(--wm-surface-2,#fff);border:1px solid var(--wm-border,#ccc);' +
                'border-radius:6px;padding:.35rem .5rem;box-shadow:0 2px 8px rgba(0,0,0,.15);' +
                'font-size:.82rem;max-width:320px;';
            document.body.appendChild(linkPopover);

            var currentAnchor = null;

            function removeLinkNode(a) {
                var frag = document.createDocumentFragment();
                while (a.firstChild) frag.appendChild(a.firstChild);
                a.parentNode.replaceChild(frag, a);
            }

            function hideLinkPopover() {
                linkPopover.style.display = 'none';
                currentAnchor = null;
            }

            function showLinkPopover(anchor) {
                currentAnchor = anchor;
                var href = anchor.getAttribute('href') || '';
                var rect = anchor.getBoundingClientRect();
                linkPopover.innerHTML = '';

                var urlSpan = document.createElement('span');
                urlSpan.title = href;
                urlSpan.textContent = href;
                urlSpan.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;color:var(--wm-primary,#2563eb);';
                linkPopover.appendChild(urlSpan);

                var sep = document.createElement('span');
                sep.style.cssText = 'width:1px;height:14px;background:var(--wm-border,#ccc);flex-shrink:0;';
                linkPopover.appendChild(sep);

                var openA = document.createElement('a');
                openA.href = href;
                openA.target = '_blank';
                openA.rel = 'noopener noreferrer';
                openA.title = 'Open in new tab';
                openA.style.cssText = 'display:flex;align-items:center;color:inherit;';
                openA.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
                linkPopover.appendChild(openA);

                var editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.title = 'Edit link';
                editBtn.style.cssText = 'border:none;background:none;cursor:pointer;padding:0;display:flex;align-items:center;color:inherit;';
                editBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
                editBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
                editBtn.addEventListener('click', function() {
                    var a = currentAnchor;
                    hideLinkPopover();
                    var newUrl = prompt('Edit URL', a.getAttribute('href') || '');
                    if (newUrl === null) return;
                    newUrl = newUrl.trim();
                    if (newUrl === '') { removeLinkNode(a); } else { a.href = newUrl; }
                    editor.focus();
                });
                linkPopover.appendChild(editBtn);

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.title = 'Remove link';
                removeBtn.style.cssText = 'border:none;background:none;cursor:pointer;padding:0;display:flex;align-items:center;color:inherit;';
                removeBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                removeBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
                removeBtn.addEventListener('click', function() {
                    var a = currentAnchor;
                    hideLinkPopover();
                    removeLinkNode(a);
                    editor.focus();
                });
                linkPopover.appendChild(removeBtn);

                linkPopover.style.display = 'flex';
                var left = Math.max(4, Math.min(rect.left, window.innerWidth - 324));
                var top  = rect.bottom + 5;
                if (top + 44 > window.innerHeight) top = rect.top - 49;
                linkPopover.style.left = left + 'px';
                linkPopover.style.top  = top  + 'px';
            }

            editor.addEventListener('click', function(e) {
                var node = e.target;
                while (node && node !== editor) {
                    if (node.tagName === 'A') {
                        e.preventDefault();
                        if (linkPopover.style.display !== 'none' && currentAnchor === node) {
                            hideLinkPopover();
                        } else {
                            showLinkPopover(node);
                        }
                        return;
                    }
                    node = node.parentNode;
                }
                hideLinkPopover();
            });

            document.addEventListener('mousedown', function(e) {
                if (linkPopover.style.display !== 'none' &&
                    !linkPopover.contains(e.target) &&
                    !editor.contains(e.target)) {
                    hideLinkPopover();
                }
            }, true);

            // ── Toolbar commands ──────────────────────────────────────────────────
            toolbar.querySelectorAll('button[data-sig-cmd]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    hideLinkPopover();
                    var cmd = btn.dataset.sigCmd;
                    var val = null;
                    if (cmd === 'createLink') {
                        var existingAnchor = null;
                        var existingUrl = '';
                        var sel = window.getSelection();
                        if (sel && sel.rangeCount > 0) {
                            var container = sel.getRangeAt(0).commonAncestorContainer;
                            var node = container.nodeType === 3 ? container.parentNode : container;
                            while (node && node !== editor) {
                                if (node.tagName === 'A') { existingAnchor = node; existingUrl = node.getAttribute('href') || ''; break; }
                                node = node.parentNode;
                            }
                        }
                        val = prompt('Enter URL', existingUrl);
                        if (val === null) return;
                        val = val.trim();
                        if (existingAnchor) {
                            if (val === '') { removeLinkNode(existingAnchor); } else { existingAnchor.href = val; }
                            editor.focus();
                            return;
                        }
                        if (val === '') {
                            document.execCommand('unlink', false, null);
                            editor.focus();
                            return;
                        }
                    }
                    document.execCommand(cmd, false, val);
                    editor.focus();
                });
            });

            toolbar.querySelectorAll('input[data-sig-cmd]').forEach(function(input) {
                input.addEventListener('change', function() {
                    var cmd = input.dataset.sigCmd;
                    document.execCommand(cmd, false, input.value);
                    editor.focus();
                });
            });

            toolbar.querySelector('[data-sig-apply-color]')?.addEventListener('click', function(e) {
                e.preventDefault();
                var picker = toolbar.querySelector('input[data-sig-cmd="foreColor"]');
                if (picker) {
                    document.execCommand('foreColor', false, picker.value);
                    editor.focus();
                }
            });

            function syncSourceFromEditor() {
                source.value = editor.innerHTML;
            }
            function syncEditorFromSource() {
                editor.innerHTML = source.value || '<p><br></p>';
            }
            toggle?.addEventListener('click', function() {
                if (source.style.display === 'none') {
                    syncSourceFromEditor();
                    source.style.display = 'block';
                    editor.style.display = 'none';
                    toggle.textContent = 'Visual editor';
                } else {
                    syncEditorFromSource();
                    source.style.display = 'none';
                    editor.style.display = 'block';
                    toggle.textContent = 'HTML source';
                }
            });

            document.getElementById('sig-form').addEventListener('submit', function() {
                if (source.style.display !== 'none') {
                    syncEditorFromSource();
                }
                document.getElementById('sig-hidden').value = editor.innerHTML;
            });
        })();
        </script>

        <!-- ── Security ── -->
        <?php elseif ($tab === 'security'): ?>
        <h2 style="margin-top:0;font-size:1.1rem">Security & Two-Factor Authentication</h2>

        <?php if (!Config::get('2fa_enabled', true)): ?>
        <div class="alert alert-danger" style="display:flex;align-items:center;gap:.75rem">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <strong>2FA is globally disabled.</strong>
                <p style="margin:.25rem 0 0;font-size:.82rem">The administrator has disabled Two-Factor Authentication in the configuration file. Individual settings are currently ignored.</p>
            </div>
        </div>
        <?php endif; ?>

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
                    <?= csrfInput() ?>
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
                    <?= csrfInput() ?>
                    <button class="btn btn-danger btn-sm">Disable 2FA</button>
                    <button type="button" class="btn btn-outline btn-sm" id="view-recovery-codes-btn" style="margin-left:.5rem">View Recovery Codes</button>
                </form>
            </div>
        </div>

        <!-- View Recovery Codes Modal -->
        <div id="recovery-codes-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
            <div style="background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:10px;box-shadow:var(--wm-shadow);width:min(400px,96vw);display:flex;flex-direction:column">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-weight:600;font-size:.95rem">Recovery Codes</span>
                    <button id="recovery-codes-modal-close" class="btn btn-ghost btn-icon" title="Close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style="padding:1rem">
                    <div id="password-verify-step">
                        <p style="font-size:.82rem;margin-bottom:1rem">Please enter your account password to view your recovery codes.</p>
                        <div class="form-group">
                            <input type="password" id="verify-password-input" class="form-control" placeholder="Password">
                        </div>
                        <button id="verify-password-btn" class="btn btn-primary btn-sm w-full">Verify Password</button>
                    </div>
                    <div id="codes-display-step" style="display:none">
                        <p style="font-size:.82rem;margin-bottom:1rem">Store these in a safe place. Each can only be used once.</p>
                        <div class="recovery-codes" id="settings-recovery-codes-list" style="margin-bottom:1rem"></div>
                        <button class="btn btn-outline btn-sm w-full" data-copy="#settings-recovery-codes-list">Copy codes</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var btn = document.getElementById('view-recovery-codes-btn');
            var modal = document.getElementById('recovery-codes-modal');
            var close = document.getElementById('recovery-codes-modal-close');
            var verifyStep = document.getElementById('password-verify-step');
            var displayStep = document.getElementById('codes-display-step');
            var passwordInput = document.getElementById('verify-password-input');
            var verifyBtn = document.getElementById('verify-password-btn');
            var codesList = document.getElementById('settings-recovery-codes-list');

            if (btn && modal) {
                btn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                    verifyStep.style.display = 'block';
                    displayStep.style.display = 'none';
                    passwordInput.value = '';
                });
                close.addEventListener('click', function() { modal.style.display = 'none'; });
                modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });

                verifyBtn.addEventListener('click', function() {
                    var pass = passwordInput.value;
                    if (!pass) return;
                    verifyBtn.disabled = true;
                    verifyBtn.textContent = 'Verifying…';

                    apiPost('?action=view_recovery_codes', { password: pass })
                    .then(function(res) {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify Password';
                        if (res.ok) {
                            verifyStep.style.display = 'none';
                            displayStep.style.display = 'block';
                            codesList.innerHTML = '';
                            res.codes.forEach(function(code) {
                                var div = document.createElement('div');
                                div.className = 'recovery-code';
                                div.textContent = code;
                                codesList.appendChild(div);
                            });
                        } else {
                            alert(res.error || 'Verification failed.');
                        }
                    })
                    .catch(function() {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify Password';
                        alert('An error occurred.');
                    });
                });
            }
        })();
        </script>

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
                    <?= csrfInput() ?>
                    <input type="hidden" name="start_2fa" value="1">
                    <button class="btn btn-primary btn-sm">Set up 2FA →</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Sessions -->
        <div class="wm-card">
            <div class="wm-card-header">Active Sessions</div>
            <div class="wm-card-body" style="padding:0">
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <p style="font-size:.82rem;color:var(--wm-text-muted);margin:0">
                        You can have up to 30 active sessions. The oldest session is automatically removed when you exceed this limit.
                    </p>
                </div>
                <?php foreach ($sessions as $s): ?>
                <?php 
                    $isCurrent = ($_COOKIE['wm_session'] ?? '') === $s['token'];
                    $ip = Config::decrypt($s['ip_address']);
                    $ua = $s['user_agent'];
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <div style="flex:1">
                        <div style="font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.5rem">
                            <?= htmlspecialchars($ip) ?>
                            <?php if ($isCurrent): ?>
                            <span class="badge badge-success" style="font-size:.65rem;padding:.1rem .4rem">Current Session</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.72rem;color:var(--wm-text-muted);margin-top:.15rem">
                            Logged in: <?= date('Y-m-d H:i:s', (int)$s['created_at']) ?> • 
                            Last seen: <?= date('Y-m-d H:i:s', (int)$s['last_seen']) ?>
                        </div>
                        <div style="font-size:.7rem;color:var(--wm-text-muted);margin-top:.1rem;max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($ua) ?>">
                            <?= htmlspecialchars($ua) ?>
                        </div>
                    </div>
                    <div style="margin-left:1rem">
                        <form method="post" action="?action=settings_save&tab=revoke_session" onsubmit="return confirm('<?= $isCurrent ? 'Revoke your current session and sign out?' : 'Revoke this session?' ?>')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="token" value="<?= htmlspecialchars($s['token']) ?>">
                            <button class="btn btn-outline btn-xs text-danger" style="display:inline-flex;align-items:center;gap:.25rem" title="<?= $isCurrent ? 'Logout and delete session' : 'Revoke and delete session' ?>">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                <?= $isCurrent ? 'Logout' : 'Revoke' ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="padding:1rem 1.25rem">
                    <form method="post" action="?action=settings_save&tab=revoke_sessions"
                          onsubmit="return confirm('This will sign you out of all devices including this one.')">
                        <?= csrfInput() ?>
                        <button class="btn btn-danger btn-sm">Revoke all sessions</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Accounts ── -->
        <?php elseif ($tab === 'accounts'): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <h2 style="margin:0;font-size:1.1rem">Connected Accounts</h2>
            <div style="display:flex;gap:.5rem">
                <a href="?action=settings&tab=accounts&add=1" class="btn btn-primary btn-sm">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add account
                </a>
            </div>
        </div>

        <?php if (!empty($_GET['add'])): ?>
        <!-- Add account form -->
        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">Add Email Account</div>
            <div class="wm-card-body">
                <div id="add-account-error" class="alert alert-danger" style="display:none;margin-bottom:1rem"></div>
                <form id="add-account-form" method="post" action="?action=settings_save&tab=add_account">
                    <?= csrfInput() ?>
                    <div class="form-group">
                        <label>Label</label>
                        <input type="text" name="label" class="form-control" placeholder="Work, Personal…" required>
                    </div>
                    <div class="form-group">
                        <label>Email address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Sender name (From)</label>
                        <input type="text" name="sender_name" class="form-control" placeholder="Shown in recipients' inbox">
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
                            <div><label>Port</label><input type="number" name="smtp_port" class="form-control" data-smtp-port value="587"></div>
                        </div>
                        <div style="display:flex;gap:1.5rem;margin-top:.5rem">
                            <label><input type="checkbox" name="smtp_ssl" value="1" data-smtp-ssl checked> SSL</label>
                            <label><input type="checkbox" name="smtp_starttls" value="1" data-smtp-starttls> STARTTLS</label>
                        </div>
                    </fieldset>
                    <div style="display:flex;gap:.5rem;margin-top:1rem">
                        <button type="submit" class="btn btn-primary btn-sm">Add account</button>
                        <a href="?action=settings&tab=accounts" class="btn btn-outline btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($accounts as $acc): ?>
        <?php $isEditing = isset($_GET['edit_account']) && (int)$_GET['edit_account'] === (int)$acc['id']; ?>
        <div class="wm-card" style="margin-bottom:1rem">
            <div class="wm-card-header" style="justify-content:space-between">
                <span>
                    <div class="wm-account-avatar" style="display:inline-flex;margin-right:.4rem">
                        <?= strtoupper(substr($acc['email'], 0, 1)) ?>
                    </div>
                    <?= htmlspecialchars($acc['label']) ?>
                    <?php if ($acc['is_primary']): ?>
                    <span title="Primary account" style="display:inline-flex;align-items:center;gap:.2rem;font-size:.75rem;color:var(--wm-primary)">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                        <?= htmlspecialchars($acc['email']) ?>
                    </span>
                    <?php endif; ?>
                    <?php
                    $vStatus = $acc['validation_status'] ?? 'pending';
                    $vColor = 'var(--wm-text-muted)';
                    $vLabel = 'Pending';
                    if ($vStatus === 'valid') { $vColor = 'var(--wm-success)'; $vLabel = 'Valid'; }
                    elseif ($vStatus === 'invalid') { $vColor = 'var(--wm-danger)'; $vLabel = 'Invalid'; }
                    elseif ($vStatus === 'checking') { $vColor = 'var(--wm-primary)'; $vLabel = 'Checking...'; }
                    ?>
                    <span class="account-validation-status" data-account-id="<?= (int)$acc['id'] ?>" 
                          style="font-size:.65rem;font-weight:700;color:<?= $vColor ?>;margin-left:.5rem;padding:.1rem .35rem;border-radius:4px;background:<?= $vColor ?>20;text-transform:uppercase">
                        <?= $vLabel ?>
                    </span>
                </span>
                <span style="display:flex;gap:.25rem">
                    <a href="?action=settings&tab=accounts<?= $isEditing ? '' : '&edit_account=' . (int)$acc['id'] ?>"
                       class="btn btn-ghost btn-sm" title="<?= $isEditing ? 'Close' : 'Edit' ?>">
                        <?php if ($isEditing): ?>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        <?php else: ?>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <?php endif; ?>
                    </a>
                    <?php if (!$acc['is_primary']): ?>
                    <form method="post" action="?action=settings_save&tab=delete_account"
                          onsubmit="return confirm('Remove this account?')">
                        <?= csrfInput() ?>
                        <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                        <button class="btn btn-ghost btn-sm text-danger" title="Remove">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                        </button>
                    </form>
                    <?php endif; ?>
                </span>
            </div>
            <div class="wm-card-body" style="padding:.75rem 1.25rem">
                <?php if ($isEditing): ?>
                <div id="edit-account-error-<?= (int)$acc['id'] ?>" class="alert alert-danger" style="display:none;margin-bottom:1rem"></div>
                <form id="edit-account-form-<?= (int)$acc['id'] ?>" method="post" action="?action=settings_save&tab=edit_account">
                    <?= csrfInput() ?>
                    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                        <div class="form-group">
                            <label>Label</label>
                            <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($acc['label']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($acc['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Sender name (From)</label>
                            <input type="text" name="sender_name" class="form-control" value="<?= htmlspecialchars($acc['sender_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Username (IMAP/SMTP)</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($acc['username'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Password <small style="color:var(--wm-text-muted)">(leave blank to keep current)</small></label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>
                    <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <legend style="font-size:.78rem;font-weight:600;color:var(--wm-text-muted);padding:0 .5rem">IMAP</legend>
                        <div style="display:grid;grid-template-columns:1fr 90px;gap:.5rem">
                            <div><label>Host</label><input type="text" name="imap_host" class="form-control" value="<?= htmlspecialchars($acc['imap_host']) ?>" required></div>
                            <div><label>Port</label><input type="number" name="imap_port" class="form-control" value="<?= (int)$acc['imap_port'] ?>"></div>
                        </div>
                        <label style="margin-top:.5rem"><input type="checkbox" name="imap_ssl" value="1" <?= $acc['imap_ssl'] ? 'checked' : '' ?>> SSL/TLS</label>
                    </fieldset>
                    <fieldset style="border:1px solid var(--wm-border);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <legend style="font-size:.78rem;font-weight:600;color:var(--wm-text-muted);padding:0 .5rem">SMTP</legend>
                        <div style="display:grid;grid-template-columns:1fr 90px;gap:.5rem">
                            <div><label>Host</label><input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($acc['smtp_host']) ?>" required></div>
                            <div><label>Port</label><input type="number" name="smtp_port" class="form-control" data-smtp-port value="<?= (int)$acc['smtp_port'] ?>"></div>
                        </div>
                        <div style="display:flex;gap:1.5rem;margin-top:.5rem">
                            <label><input type="checkbox" name="smtp_ssl" value="1" data-smtp-ssl <?= $acc['smtp_ssl'] ? 'checked' : '' ?>> SSL</label>
                            <label><input type="checkbox" name="smtp_starttls" value="1" data-smtp-starttls <?= $acc['smtp_starttls'] ? 'checked' : '' ?>> STARTTLS</label>
                        </div>
                    </fieldset>
                    <div style="display:flex;gap:.5rem;margin-top:1rem">
                        <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                        <a href="?action=settings&tab=accounts" class="btn btn-outline btn-sm">Cancel</a>
                    </div>
                </form>
                <?php else: ?>
                <div style="font-size:.82rem;color:var(--wm-text-muted);display:flex;flex-wrap:wrap;gap:.25rem 1.5rem">
                    <span><?= htmlspecialchars($acc['email']) ?></span>
                    <?php if (!empty($acc['sender_name'])): ?>
                    <span>From name: <?= htmlspecialchars($acc['sender_name']) ?></span>
                    <?php endif; ?>
                    <span>IMAP: <?= htmlspecialchars($acc['imap_host']) ?>:<?= (int)$acc['imap_port'] ?></span>
                    <span>SMTP: <?= htmlspecialchars($acc['smtp_host']) ?>:<?= (int)$acc['smtp_port'] ?></span>
                </div>
                <?php endif; ?>
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
                    Choose how <?= htmlspecialchars($brandName) ?> looks. <strong>System</strong> automatically follows your
                    operating system's dark/light preference.
                </p>
                <form method="post" action="?action=settings_save&tab=appearance" id="theme-form">
                    <?= csrfInput() ?>
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                        <button type="submit" name="theme" value="system"
                                onclick="return applyAndSubmitTheme('system')"
                                id="theme-system" class="wm-theme-card">
                            <div class="wm-theme-preview" style="background:linear-gradient(135deg,#fff 50%,#0d1117 50%)"></div>
                            <span>System (auto)</span>
                        </button>
                        <button type="submit" name="theme" value="light"
                                onclick="return applyAndSubmitTheme('light')"
                                id="theme-light" class="wm-theme-card">
                            <div class="wm-theme-preview" style="background:#f0f4f8"></div>
                            <span>Light</span>
                        </button>
                        <button type="submit" name="theme" value="dark"
                                onclick="return applyAndSubmitTheme('dark')"
                                id="theme-dark" class="wm-theme-card">
                            <div class="wm-theme-preview" style="background:#0d1117"></div>
                            <span>Dark</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── System ── -->
        <?php elseif ($tab === 'system'): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <h2 style="margin:0;font-size:1.1rem">System Health & Security</h2>
            <div style="text-align:right">
                <div style="font-size:.7rem;color:var(--wm-text-muted);margin-bottom:.25rem">
                    Last check: <?= ($lastAt = $_SESSION['system_check_cache']['at'] ?? null) ? date('Y-m-d H:i:s', (int)$lastAt) : 'Never' ?>
                </div>
                <a href="?action=settings&tab=system&recheck=1" class="btn btn-outline btn-xs">Run check now</a>
            </div>
        </div>
        
        <?php 
        $sys = Config::checkSystem(); 
        $ignoreBanner = Config::get('ignore_security_banner', false);
        $ignoreUpdate = Config::get('ignore_update_banner', false);
        ?>

        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">Legal Disclaimer</div>
            <div class="wm-card-body">
                <p style="font-size:.85rem;margin-top:0;color:var(--wm-text-muted)">
                    <strong>Responsibility:</strong> WebyMail is provided "as is." While we strive for security, you use this software at your own risk.
                </p>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-success);font-size:.82rem;font-weight:600">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    Accepted during installation
                </div>
            </div>
        </div>

        <?php
        $twoFaSystemEnabled = Config::get('2fa_enabled', true);
        $userHas2FA = (int)($user['totp_enabled'] ?? 0) === 1;
        $captchaEnabled = Config::get('captcha_enabled', true);
        $twoFaOk = $twoFaSystemEnabled && $userHas2FA;

        // Show "activation in progress" for 3 minutes after enabling; the flash message
        // advertises ~5 minutes as a conservative propagation estimate for the user.
        $captchaActivationTime = $_SESSION['captcha_activation_time'] ?? null;
        $captchaActivationPending = false;
        if ($captchaActivationTime !== null) {
            $elapsed = time() - $captchaActivationTime;
            if ($elapsed < 180) { // 3-minute "in progress" display window
                $captchaActivationPending = true;
            } else {
                unset($_SESSION['captcha_activation_time']);
            }
        }

        if ($twoFaOk && $captchaEnabled && !$captchaActivationPending) {
            $securityCardBorder = 'var(--wm-success)';
        } elseif ($captchaActivationPending) {
            $securityCardBorder = 'var(--wm-info)';
        } else {
            $securityCardBorder = 'var(--wm-warning)';
        }
        ?>
        <div class="wm-card" style="margin-bottom:1.5rem;border-left:4px solid <?= $securityCardBorder ?>">
            <div class="wm-card-header" style="color:<?= $securityCardBorder ?>">Security Status</div>
            <div class="wm-card-body">

                <?php if ($twoFaOk): ?>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-success);font-weight:600;margin-bottom:.5rem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    Two-Factor Authentication is active
                </div>
                <p style="font-size:.85rem;margin:0 0 .75rem">
                    Your account is protected with an extra layer of security. This is excellent for your account protection.
                </p>
                <?php elseif (!$twoFaSystemEnabled): ?>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-warning);font-weight:600;margin-bottom:.5rem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Two-Factor Authentication is globally disabled
                </div>
                <p style="font-size:.85rem;margin:0 0 .75rem">
                    2FA is disabled in the configuration file. Enable <code>2fa_enabled</code> in <code>config/config.php</code> to allow users to protect their accounts.
                </p>
                <?php else: ?>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-warning);font-weight:600;margin-bottom:.5rem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Two-Factor Authentication is not enabled for your account
                </div>
                <p style="font-size:.85rem;margin:0 0 .5rem">
                    Enabling 2FA adds an extra layer of protection beyond just your password.
                </p>
                <a href="?action=settings&tab=security" class="btn btn-primary btn-xs" style="margin-bottom:.75rem">Enable 2FA now</a>
                <?php endif; ?>

                <?php if ($captchaActivationPending): ?>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-info);font-weight:600;margin-bottom:.5rem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    CAPTCHA activation in progress
                </div>
                <p style="font-size:.85rem;margin:0">
                    CAPTCHA Proof-of-Work has been enabled and is propagating. Changes should be effective in about 5 minutes.
                </p>
                <?php elseif ($captchaEnabled): ?>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-success);font-weight:600;margin-bottom:.5rem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Login Protected: CAPTCHA Proof-of-Work is active
                </div>
                <p style="font-size:.85rem;margin:0">
                    CAPTCHA Proof-of-Work is active and protecting your account from automated attacks.
                </p>
                <?php else: ?>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--wm-warning);font-weight:600;margin-bottom:.5rem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    CAPTCHA Proof-of-Work is disabled
                </div>
                <p style="font-size:.85rem;margin:0 0 .5rem">
                    The login page is more vulnerable to automated brute-force attacks without CAPTCHA protection.
                </p>
                <form method="post" action="?action=settings_save&tab=enable_captcha" style="display:inline">
                    <?= csrfInput() ?>
                    <button type="submit" class="btn btn-outline btn-xs">Enable CAPTCHA now</button>
                </form>
                <?php endif; ?>

            </div>
        </div>

        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">System Information</div>
            <div class="wm-card-body" style="padding:0">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-size:.85rem">WebyMail Version</span>
                    <span style="font-size:.85rem;font-weight:600">v<?= Config::VERSION ?></span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-size:.85rem">Latest Releases</span>
                    <a href="<?= Config::UPDATE_URL ?>" target="_blank" style="font-size:.85rem;color:var(--wm-primary);text-decoration:none;display:flex;align-items:center;gap:.25rem">
                        GitHub Releases
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-size:.85rem">PHP Version</span>
                    <span style="font-size:.85rem;color:var(--wm-text-muted)"><?= PHP_VERSION ?></span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-size:.85rem">Server IP</span>
                    <span style="font-size:.85rem;color:var(--wm-text-muted)"><?= $_SERVER['SERVER_ADDR'] ?? 'Unknown' ?></span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-size:.85rem">Server Software</span>
                    <span style="font-size:.85rem;color:var(--wm-text-muted)"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></span>
                </div>
            </div>
        </div>

        <div class="wm-card" style="margin-bottom:1.5rem">
            <div class="wm-card-header">PHP Extensions</div>
            <div class="wm-card-body" style="padding:0">
                <?php foreach ($sys['requirements'] as $ext => $ok): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                    <span style="font-family:var(--wm-font-mono);font-size:.85rem"><?= $ext ?></span>
                    <span style="font-size:.72rem;font-weight:700;color:<?= $ok ? 'var(--wm-success)' : 'var(--wm-danger)' ?>">
                        <?= $ok ? 'OK' : 'MISSING' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wm-card">
            <div class="wm-card-header" style="justify-content:space-between">
                <span>File Permissions & Security</span>
                <form method="post" action="?action=fix_permissions">
                    <?= csrfInput() ?>
                    <button type="submit" class="btn btn-outline btn-xs" <?= $sys['all_ok'] ? 'disabled style="cursor:not-allowed;opacity:0.6"' : '' ?>>Fix Permissions</button>
                </form>
            </div>
            <div class="wm-card-body" style="padding:0">
                <?php 
                $insecure = array_filter($sys['security'], fn($c) => !$c['ok']);
                if (empty($insecure)): ?>
                <div style="padding:1.25rem;text-align:center;color:var(--wm-success);font-size:.85rem">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom:.5rem"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <br>All files and folders have correct permissions.
                    <div style="margin-top:1rem">
                        <button type="button" class="btn btn-ghost btn-xs" onclick="document.getElementById('all-perms').style.display='block';this.style.display='none'">View Checked Permissions</button>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($insecure as $check): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid var(--wm-border)">
                        <div>
                            <div style="font-family:var(--wm-font-mono);font-size:.85rem"><?= htmlspecialchars($check['path']) ?></div>
                            <?php if (isset($check['perms'])): ?>
                            <div style="font-size:.7rem;color:var(--wm-text-muted)">Perms: <?= $check['perms'] ?></div>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:.72rem;font-weight:700;color:var(--wm-warning)">
                            INSECURE
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <div style="padding:.75rem 1.25rem;text-align:center">
                        <button type="button" class="btn btn-ghost btn-xs" onclick="document.getElementById('all-perms').style.display='block';this.style.display='none'">View All Checked Permissions</button>
                    </div>
                <?php endif; ?>
                
                <div id="all-perms" style="display:none;max-height:400px;overflow-y:auto;border-top:1px solid var(--wm-border)">
                    <?php foreach ($sys['security'] as $check): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 1.25rem;border-bottom:1px solid var(--wm-border);font-size:.8rem">
                        <div style="font-family:var(--wm-font-mono)"><?= htmlspecialchars($check['path']) ?></div>
                        <div style="display:flex;align-items:center;gap:1rem">
                            <span style="color:var(--wm-text-muted);font-size:.7rem"><?= $check['perms'] ?></span>
                            <span style="font-size:.65rem;font-weight:700;color:<?= $check['ok'] ? 'var(--wm-success)' : 'var(--wm-warning)' ?>">
                                <?= $check['ok'] ? 'OK' : 'FAIL' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!$sys['all_ok']): ?>
        <div class="alert alert-warning" style="margin-top:1.5rem;font-size:.82rem">
            <strong>Action Required:</strong> Some security issues were detected. 
            Ensure your directories are set to <code>750</code> and files to <code>640</code>.
            Verify that <code>.htaccess</code> is present in the root directory.
        </div>
        <?php endif; ?>

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

function applyAndSubmitTheme(theme) {
    ThemeManager.apply(theme);
    updateThemeCards();
    return true;
}

// Sync current theme with server preference on load
(function() {
    function sync() {
        if (window.ThemeManager) {
            var allowed = <?= json_encode(Config::THEMES) ?>;
            var serverTheme = <?= json_encode($user['theme'] ?? 'system') ?>;
            if (allowed.indexOf(serverTheme) !== -1) {
                ThemeManager.apply(serverTheme);
            }
            if (typeof updateThemeCards === 'function') updateThemeCards();
        }
    }
    if (document.readyState === 'complete') sync();
    else window.addEventListener('load', sync);
})();

// AJAX for account forms
(function() {
    function handleAccountForm(formId, errorId) {
        var form = document.getElementById(formId);
        var error = document.getElementById(errorId);
        if (!form || !error) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            error.style.display = 'none';
            var btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            var origText = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving...';
            }

            var fd = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    window.location.href = '?action=settings&tab=accounts';
                } else {
                    error.textContent = data.error || 'An error occurred.';
                    error.style.display = 'flex';
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = origText;
                    }
                    error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            })
            .catch(function(err) {
                error.textContent = 'Connection error. Please try again.';
                error.style.display = 'flex';
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            });
        });
    }

    handleAccountForm('add-account-form', 'add-account-error');
    <?php foreach ($accounts as $acc): ?>
    handleAccountForm('edit-account-form-<?= (int)$acc['id'] ?>', 'edit-account-error-<?= (int)$acc['id'] ?>');
    <?php endforeach; ?>
})();
</script>
