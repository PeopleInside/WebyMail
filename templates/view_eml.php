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
            </div>
        </div>

        <!-- Nested Attachments if any -->
        <?php if (!empty($eml['attachments'])): ?>
        <div style="padding:.75rem 1.5rem;background:var(--wm-bg);border-bottom:1px solid var(--wm-border);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            <span style="font-size:.78rem;font-weight:600;color:var(--wm-text-muted)">Nested attachments in this .eml:</span>
            <?php foreach ($eml['attachments'] as $nested): ?>
            <span class="badge badge-neutral" style="font-size:.75rem;padding:.2rem .5rem">
                📎 <?= htmlspecialchars($nested['filename']) ?> (<?= number_format($nested['size'] / 1024, 1) ?> KB)
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Email Body -->
        <div style="padding:1.5rem;background:var(--wm-surface)">
            <?php if (!empty($eml['has_html'])): ?>
                <div class="wm-email-body" style="line-height:1.6">
                    <?= $eml['body_html'] ?>
                </div>
            <?php else: ?>
                <pre style="white-space:pre-wrap;font-family:inherit;font-size:.9rem;line-height:1.6;margin:0"><?= htmlspecialchars($eml['body_text']) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>
