<?php
/**
 * Email view template
 * @var array  $message    parsed message array from ImapClient::getMessage()
 * @var string $folder
 * @var bool   $hasExternal  true if email body contains external URLs
 */
$folder    = $folder ?? 'INBOX';
$folderEnc = urlencode($folder);
$msgNo     = (int) ($message['msg_no'] ?? 0);
$hasHtml   = !empty($message['body_html']);
?>

<!-- Toolbar -->
<div class="wm-toolbar">
    <a href="?action=inbox&folder=<?= $folderEnc ?>" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back
    </a>

    <div style="width:1px;height:20px;background:var(--wm-border);margin:0 .25rem"></div>

    <a href="?action=compose&reply=<?= $msgNo ?>&folder=<?= $folderEnc ?>" class="btn btn-outline btn-sm" title="Reply">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 00-4-4H4"/></svg>
        Reply
    </a>
    <a href="?action=compose&reply_all=<?= $msgNo ?>&folder=<?= $folderEnc ?>" class="btn btn-outline btn-sm" title="Reply All">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 17 2 12 7 7"/><polyline points="12 17 7 12 12 7"/><path d="M22 18v-2a4 4 0 00-4-4H2"/></svg>
        Reply All
    </a>
    <a href="?action=compose&forward=<?= $msgNo ?>&folder=<?= $folderEnc ?>" class="btn btn-outline btn-sm" title="Forward">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 014-4h12"/></svg>
        Forward
    </a>

    <div class="wm-topbar-spacer"></div>

    <form method="post" action="?action=delete&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>" style="display:inline"
          onsubmit="return confirm('Delete this message?')">
        <button class="btn btn-ghost btn-sm text-danger" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            Delete
        </button>
    </form>
</div>

<!-- External image protection banner -->
<?php if ($hasExternal ?? false): ?>
<div class="wm-img-banner" id="img-protection-banner">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    <span>External images have been blocked to protect your privacy.</span>
    <button class="btn btn-outline btn-sm" id="show-images-btn" style="margin-left:auto">Show images</button>
</div>
<?php endif; ?>

<!-- Message header -->
<div class="wm-view-header">
    <div class="wm-view-subject"><?= htmlspecialchars($message['subject'] ?? '(no subject)') ?></div>
    <div class="wm-view-meta">
        <span><strong>From:</strong> <?= htmlspecialchars($message['from'] ?? '') ?></span>
        <span><strong>To:</strong> <?= htmlspecialchars($message['to'] ?? '') ?></span>
        <?php if (!empty($message['cc'])): ?>
        <span><strong>Cc:</strong> <?= htmlspecialchars($message['cc']) ?></span>
        <?php endif; ?>
        <?php if (!empty($message['reply_to']) && $message['reply_to'] !== $message['from']): ?>
        <span><strong>Reply-To:</strong> <?= htmlspecialchars($message['reply_to']) ?></span>
        <?php endif; ?>
        <span><strong>Date:</strong> <?= htmlspecialchars($message['date'] ?? '') ?></span>
    </div>
</div>

<!-- Message body -->
<div class="wm-view-body">
    <?php if ($hasHtml): ?>
    <!-- HTML email rendered in sandboxed iframe -->
    <?php if ($hasExternal ?? false): ?>
    <iframe id="email-frame" class="wm-email-frame"
            data-src="?action=email_body&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>&images=1"
            src="?action=email_body&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>&images=0"
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            referrerpolicy="no-referrer"
            loading="lazy"
            onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px'">
    </iframe>
    <?php else: ?>
    <iframe id="email-frame" class="wm-email-frame"
            src="?action=email_body&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>&images=1"
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            referrerpolicy="no-referrer"
            loading="lazy"
            onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px'">
    </iframe>
    <?php endif; ?>
    <?php else: ?>
    <!-- Plain-text fallback -->
    <pre style="font-family:inherit;white-space:pre-wrap;word-break:break-word;font-size:.9rem;line-height:1.7;margin:0"><?= htmlspecialchars($message['body_text'] ?? '') ?></pre>
    <?php endif; ?>

    <!-- Attachments -->
    <?php if (!empty($message['attachments'])): ?>
    <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--wm-border)">
        <div style="font-size:.82rem;font-weight:600;color:var(--wm-text-muted);margin-bottom:.6rem">
            <?= count($message['attachments']) ?> Attachment<?= count($message['attachments']) > 1 ? 's' : '' ?>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem">
            <?php foreach ($message['attachments'] as $att): ?>
            <a href="?action=attachment&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>&section=<?= urlencode($att['section']) ?>&name=<?= urlencode($att['filename']) ?>"
               class="btn btn-outline btn-sm" download>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= htmlspecialchars($att['filename']) ?>
                <span class="text-muted">(<?= number_format($att['size'] / 1024, 1) ?> KB)</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
