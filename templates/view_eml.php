<?php
/**
 * Standalone .eml Attachment Preview Template
 * @var array  $eml        parsed eml payload
 * @var string $filename
 * @var string $folder
 * @var int    $msgNo
 * @var string $section
 */
$folderEnc       = urlencode($folder);
$sectionEnc      = urlencode($section);
$activeSessAccId = (int) ($session['account_id'] ?? 0);
$targetAccountId = (int) ($targetAccountId ?? $activeSessAccId);
$fromUnified     = (string) ($fromUnified ?? '');
$accountParam    = ($targetAccountId > 0 && ($targetAccountId !== $activeSessAccId || $fromUnified !== ''))
    ? '&account_id=' . $targetAccountId
    : '';
$unifiedParam    = $fromUnified !== '' ? '&from_unified=' . urlencode($fromUnified) : '';
$downloadUrl     = '?action=attachment&folder=' . $folderEnc . '&msg=' . (int)$msgNo . '&section=' . $sectionEnc . '&name=' . urlencode($filename) . $accountParam;
$backUrl         = '?action=view&folder=' . $folderEnc . '&msg=' . (int)$msgNo . $accountParam . $unifiedParam;
$replyBase       = 'folder=' . $folderEnc . '&msg=' . (int)$msgNo . '&section=' . $sectionEnc . '&images=1' . $accountParam . $unifiedParam;
$rawHeaders      = (string) ($eml['raw_headers'] ?? '');
?>
<div class="wm-toolbar">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-ghost btn-sm" target="_self">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to original email
    </a>
    <a href="?action=compose&reply_eml=1&<?= $replyBase ?>" class="btn btn-primary btn-sm" target="_self" title="Reply to this attached email">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 00-4-4H4"/></svg>
        Reply
    </a>
    <a href="?action=compose&reply_eml=1&reply_all=1&<?= $replyBase ?>" class="btn btn-outline btn-sm" target="_self" title="Reply All to this attached email">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 17 2 12 7 7"/><polyline points="12 17 7 12 12 7"/><path d="M22 18v-2a4 4 0 00-4-4H2"/></svg>
        Reply All
    </a>
    <a href="?action=compose&forward_eml=1&<?= $replyBase ?>" class="btn btn-outline btn-sm" target="_self" title="Forward this attached email">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 014-4h12"/></svg>
        Forward
    </a>
    <div class="wm-topbar-spacer"></div>
    <span class="badge badge-info" style="font-size:.75rem;padding:.2rem .6rem">
        Attached Email (.eml) Preview
    </span>
    <button type="button" class="btn btn-ghost btn-sm" id="eml-standalone-headers-btn" title="View original headers">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Headers
    </button>
    <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-outline btn-sm" download title="Download .eml file">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download .eml
    </a>
</div>

<div class="wm-view-wrap" style="padding:1.5rem;max-width:960px;margin:0 auto">
    <div class="wm-card" style="border-radius:12px;overflow:hidden;box-shadow:var(--wm-shadow)">
        <!-- Header -->
        <div style="padding:1.5rem;border-bottom:1px solid var(--wm-border);background:var(--wm-surface-2)">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem">
                <span class="badge badge-primary">Previewing Attached Email</span>
                <span style="font-size:.78rem;color:var(--wm-text-muted);font-family:monospace"><?= htmlspecialchars($filename) ?></span>
            </div>
            <h1 style="font-size:1.35rem;font-weight:700;margin:0 0 .75rem;line-height:1.3">
                <?= htmlspecialchars($eml['subject'] ?: '(No subject)') ?>
            </h1>
            <div style="display:grid;grid-template-columns:auto 1fr;gap:.4rem 1rem;font-size:.85rem">
                <span style="color:var(--wm-text-muted);font-weight:600">From:</span>
                <span><?= htmlspecialchars($eml['from']) ?></span>

                <span style="color:var(--wm-text-muted);font-weight:600">To:</span>
                <span><?= htmlspecialchars($eml['to']) ?></span>

                <?php if (!empty($eml['cc'])): ?>
                <span style="color:var(--wm-text-muted);font-weight:600">Cc:</span>
                <span><?= htmlspecialchars($eml['cc']) ?></span>
                <?php endif; ?>

                <?php if (!empty($eml['date'])): ?>
                <span style="color:var(--wm-text-muted);font-weight:600">Date:</span>
                <span><?= htmlspecialchars($eml['date']) ?></span>
                <?php endif; ?>

                <?php
                $emlAuth = $eml['auth_status'] ?? ['has_auth' => false, 'banner_level' => 'none', 'checks' => []];
                if (!empty($emlAuth['checks'])):
                ?>
                <span style="color:var(--wm-text-muted);font-weight:600">Auth:</span>
                <span class="wm-auth-badge" aria-label="Email authentication checks">
                    <?php foreach (['spf', 'dkim', 'dmarc'] as $mechKey):
                        if (empty($emlAuth['checks'][$mechKey])) continue;
                        $chk = $emlAuth['checks'][$mechKey];
                        $chipCls = match ($chk['status'] ?? 'warn') {
                            'pass' => 'is-pass',
                            'fail' => 'is-fail',
                            default => 'is-warn',
                        };
                        $detailText = (string) ($chk['detail'] ?? '');
                    ?>
                    <span class="wm-auth-chip <?= $chipCls ?>"
                          tabindex="0"
                          data-tooltip="<?= htmlspecialchars($detailText) ?>"
                          aria-label="<?= htmlspecialchars(($chk['text'] ?? strtoupper($mechKey)) . ($detailText !== '' ? ': ' . $detailText : '')) ?>">
                        <span class="wm-auth-chip-dot" aria-hidden="true"></span>
                        <?= htmlspecialchars((string) ($chk['text'] ?? strtoupper($mechKey))) ?>
                    </span>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($emlAuth['has_auth']) && in_array($emlAuth['banner_level'] ?? 'none', ['danger', 'warning'], true)):
            $bannerCls = ($emlAuth['banner_level'] === 'danger') ? 'wm-auth-banner-danger' : 'wm-auth-banner-warning';
        ?>
        <div class="wm-auth-banner is-compact <?= $bannerCls ?>" role="alert">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <div style="font-weight:700"><?= htmlspecialchars((string) ($emlAuth['banner_title'] ?? 'Authentication Warning')) ?></div>
                <div class="wm-auth-banner-text"><?= htmlspecialchars((string) ($emlAuth['banner_description'] ?? '')) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Nested Attachments if any -->
        <?php if (!empty($eml['attachments'])): ?>
        <div style="padding:.75rem 1.5rem;background:var(--wm-bg);border-bottom:1px solid var(--wm-border);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            <span style="font-size:.78rem;font-weight:600;color:var(--wm-text-muted)">Nested attachments in this .eml:</span>
            <?php foreach ($eml['attachments'] as $nestedIdx => $nested):
                $attIdx = (int) ($nested['index'] ?? $nestedIdx);
                $nestedUrl = '?action=eml_attachment&folder=' . $folderEnc . '&msg=' . (int)$msgNo . '&section=' . $sectionEnc . '&att_index=' . $attIdx . $accountParam;
            ?>
            <a href="<?= htmlspecialchars($nestedUrl) ?>"
               class="badge badge-neutral wm-nested-att-link"
               download="<?= htmlspecialchars((string) $nested['filename']) ?>"
               title="Download <?= htmlspecialchars((string) $nested['filename']) ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= htmlspecialchars($nested['filename']) ?> (<?= number_format($nested['size'] / 1024, 1) ?> KB)
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Email Body -->
        <div style="padding:0;background:var(--wm-surface)">
            <?php if (!empty($eml['has_html'])): ?>
                <iframe id="eml-standalone-frame"
                        class="wm-eml-standalone-frame"
                        sandbox="allow-popups allow-popups-to-escape-sandbox"
                        referrerpolicy="no-referrer"
                        srcdoc="<?= htmlspecialchars($eml['body_html'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Attached email preview"></iframe>
                <script nonce="<?= $cspNonce ?>">
                (function() {
                    var frame = document.getElementById('eml-standalone-frame');
                    if (!frame) return;
                    var rawHtml = frame.getAttribute('srcdoc') || '';
                    function sync() {
                        var t = document.documentElement.getAttribute('data-theme') || 'system';
                        if (t === 'system') {
                            t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                        }
                        var safeTheme = (t === 'dark') ? 'dark' : 'light';
                        frame.srcdoc = rawHtml
                            .replace(/<html([^>]*?)\sdata-theme=["'][^"']*["']/i, '<html$1 data-theme="' + safeTheme + '"')
                            .replace(/(<div[^>]*\sid=["']wm-shadow-wrapper["'][^>]*?)\sdata-theme=["'][^"']*["']/i, '$1 data-theme="' + safeTheme + '"');
                    }
                    sync();
                    var obs = new MutationObserver(sync);
                    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
                    if (window.matchMedia) {
                        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', sync);
                    }
                })();
                </script>
            <?php else: ?>
                <pre style="white-space:pre-wrap;font-family:inherit;font-size:.9rem;line-height:1.6;margin:0;padding:1.5rem"><?= htmlspecialchars($eml['body_text']) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Headers modal -->
<div id="headers-modal" style="display:none;position:fixed;inset:0;z-index:1010;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:10px;box-shadow:var(--wm-shadow);width:min(720px,96vw);max-height:80vh;display:flex;flex-direction:column">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--wm-border)">
            <span style="font-weight:600;font-size:.95rem">Attached Email Headers — <?= htmlspecialchars($filename) ?></span>
            <div style="display:flex;align-items:center;gap:.5rem">
                <button type="button" id="copy-headers-btn" class="btn btn-ghost btn-sm" title="Copy headers to clipboard">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span>Copy</span>
                </button>
                <button type="button" id="headers-modal-close" class="btn btn-ghost btn-icon" title="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div style="overflow:auto;padding:1rem">
            <pre id="headers-content" aria-live="polite" aria-busy="false" style="font-family:monospace;font-size:.8rem;line-height:1.6;white-space:pre-wrap;word-break:break-all;margin:0;color:var(--wm-text)"><?= htmlspecialchars($rawHeaders !== '' ? $rawHeaders : 'No headers found in this .eml attachment.') ?></pre>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
(function() {
    var btn = document.getElementById('eml-standalone-headers-btn');
    var modal = document.getElementById('headers-modal');
    var close = document.getElementById('headers-modal-close');
    var pre = document.getElementById('headers-content');
    var copyBtn = document.getElementById('copy-headers-btn');

    if (btn && modal) {
        btn.addEventListener('click', function() { modal.style.display = 'flex'; });
    }
    if (close && modal) {
        close.addEventListener('click', function() { modal.style.display = 'none'; });
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
        });
    }
    if (copyBtn && pre) {
        copyBtn.addEventListener('click', function() {
            var text = pre.textContent || '';
            if (!text) return;
            var updateFeedback = function() {
                var span = copyBtn.querySelector('span');
                if (span) {
                    var orig = span.textContent;
                    span.textContent = 'Copied!';
                    setTimeout(function() { span.textContent = orig; }, 2000);
                }
            };

            var safeText = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            var htmlContent = '<pre style="background: transparent !important; background-color: transparent !important; color: inherit !important; font-family: monospace, monospace; font-size: 13px; line-height: 1.5; white-space: pre-wrap !important; word-break: break-all; margin: 0; padding: 0;">' + safeText.replace(/\r?\n/g, '<br>') + '</pre>';

            if (navigator.clipboard && typeof navigator.clipboard.write === 'function' && typeof ClipboardItem !== 'undefined') {
                try {
                    var blobText = new Blob([text], { type: 'text/plain' });
                    var blobHtml = new Blob([htmlContent], { type: 'text/html' });
                    var item = new ClipboardItem({ 'text/plain': blobText, 'text/html': blobHtml });
                    navigator.clipboard.write([item]).then(updateFeedback).catch(execCopy);
                } catch (err) {
                    execCopy();
                }
            } else {
                execCopy();
            }

            function execCopy() {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(text).then(updateFeedback).catch(selectAndCopy);
                } else {
                    selectAndCopy();
                }
            }

            function selectAndCopy() {
                var range = document.createRange();
                range.selectNodeContents(pre);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
                document.execCommand('copy');
                sel.removeAllRanges();
                updateFeedback();
            }
        });
    }
})();
</script>

