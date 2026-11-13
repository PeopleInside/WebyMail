<?php
/**
 * Unified multi-account mailbox view
 * @var array  $messages
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $ufolder       inbox|sent|spam|trash
 * @var string $folderLabel
 * @var array  $unifiedErrors
 * @var array  $accounts
 */
$messages      = $messages ?? [];
$total         = (int) ($total ?? 0);
$page          = (int) ($page ?? 1);
$pages         = (int) ($pages ?? 0);
$ufolder       = $ufolder ?? 'inbox';
$ufolderEnc    = urlencode($ufolder);
$folderLabel   = $folderLabel ?? 'Inbox';
$unifiedErrors = $unifiedErrors ?? [];
$isTrash       = ($ufolder === 'trash');

$badgePalette = [
    ['bg' => 'rgba(37, 99, 235, 0.14)', 'fg' => '#2563eb', 'bd' => 'rgba(37, 99, 235, 0.30)'],
    ['bg' => 'rgba(5, 150, 105, 0.14)', 'fg' => '#059669', 'bd' => 'rgba(5, 150, 105, 0.30)'],
    ['bg' => 'rgba(217, 119, 6, 0.14)', 'fg' => '#d97706', 'bd' => 'rgba(217, 119, 6, 0.30)'],
    ['bg' => 'rgba(124, 58, 237, 0.14)', 'fg' => '#7c3aed', 'bd' => 'rgba(124, 58, 237, 0.30)'],
    ['bg' => 'rgba(219, 39, 119, 0.14)', 'fg' => '#db2777', 'bd' => 'rgba(219, 39, 119, 0.30)'],
    ['bg' => 'rgba(8, 145, 178, 0.14)', 'fg' => '#0891b2', 'bd' => 'rgba(8, 145, 178, 0.30)'],
];

$accountColorMap = [];
foreach (($accounts ?? []) as $idx => $acc) {
    $accountColorMap[(int) $acc['id']] = $badgePalette[$idx % count($badgePalette)];
}

$unifiedTabs = [];
if (!empty($unifiedFolders)) {
    foreach ($unifiedFolders as $uf) {
        $unifiedTabs[(string)$uf['key']] = (string)$uf['label'];
    }
} else {
    $unifiedTabs = [
        'inbox' => 'Inbox',
        'sent'  => 'Sent',
        'spam'  => 'Spam',
        'trash' => 'Trash',
    ];
}
?>

<div class="wm-toolbar" id="unified-toolbar" data-is-trash="<?= $isTrash ? '1' : '0' ?>">
    <input type="checkbox" id="select-all" title="Select all">

    <div id="bulk-bar" style="display:none;align-items:center;gap:.5rem">
        <button class="btn btn-outline btn-sm" id="unified-bulk-read">Mark read</button>
        <button class="btn btn-outline btn-sm" id="unified-bulk-unread">Mark unread</button>
        <button class="btn btn-danger btn-sm" id="unified-bulk-delete">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
            Delete
        </button>
    </div>

    <div class="wm-unified-tabs">
        <?php foreach ($unifiedTabs as $key => $label): ?>
        <a href="?action=unified&ufolder=<?= urlencode($key) ?>"
           class="btn btn-sm <?= $ufolder === $key ? 'btn-primary' : 'btn-ghost' ?>"
           target="_self"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>

    <span id="inbox-total-count" style="font-size:.78rem;color:var(--wm-text-muted);margin-left:auto"><?= $total ?> message<?= $total !== 1 ? 's' : '' ?></span>

    <button class="btn btn-ghost btn-icon" title="Refresh" id="refresh-btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
    </button>
</div>

<?php if (!empty($unifiedErrors)): ?>
<div class="wm-unified-errors" style="padding:.75rem 1rem 0;display:flex;flex-direction:column;gap:.5rem">
    <?php foreach ($unifiedErrors as $uErr): ?>
    <div class="alert alert-danger" style="margin:0;border-radius:8px;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:.5rem;min-width:0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>
                <strong><?= htmlspecialchars($uErr['account_label'] ?? '') ?></strong>
                <?php if (!empty($uErr['account_email']) && ($uErr['account_email'] !== ($uErr['account_label'] ?? ''))): ?>
                    <span style="opacity:.85">(<?= htmlspecialchars($uErr['account_email']) ?>)</span>
                <?php endif; ?>:
                <?= htmlspecialchars($uErr['message'] ?? 'Connection error.') ?>
            </span>
        </div>
        <a href="?action=settings&tab=accounts" class="btn btn-outline btn-xs" target="_self">Account settings</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div id="inbox-content">
<div class="wm-mail-list" id="mail-list">
    <?php if (empty($messages)): ?>
    <div style="text-align:center;padding:4rem 2rem;color:var(--wm-text-muted)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.3;display:block;margin:0 auto 1rem">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
        <p>No messages in Unified <?= htmlspecialchars($folderLabel) ?></p>
    </div>
    <?php else: ?>
    <?php foreach ($messages as $msg):
        $mAccId  = (int) ($msg['account_id'] ?? 0);
        $mFolder = (string) ($msg['account_folder'] ?? 'INBOX');
        $mColor  = $accountColorMap[$mAccId] ?? $badgePalette[0];
        $viewUrl = '?action=view&account_id=' . $mAccId
                 . '&folder=' . urlencode($mFolder)
                 . '&msg=' . (int) ($msg['msg_no'] ?? 0)
                 . '&from_unified=' . $ufolderEnc;
        $senderStr     = (string) ($msg['from'] ?? '');
        $cleanSender   = trim(preg_replace('/<.*?>/', '', $senderStr), " \t\n\r\0\x0B\"'");
        $senderInitial = strtoupper(substr($cleanSender ?: 'M', 0, 1));
        $avatarColors  = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#4f46e5', '#ea580c'];
        $colorIdx      = abs(crc32($senderStr)) % count($avatarColors);
        $avatarBg      = $avatarColors[$colorIdx];
    ?>
    <div class="wm-mail-row <?= !empty($msg['is_read']) ? '' : 'unread' ?>"
         data-href="<?= htmlspecialchars($viewUrl) ?>">
        <div class="wm-mail-check">
            <input type="checkbox"
                   class="mail-checkbox"
                   data-uid="<?= (int) ($msg['uid'] ?? 0) ?>"
                   data-account-id="<?= $mAccId ?>"
                   data-folder="<?= htmlspecialchars($mFolder) ?>">
        </div>
        <div class="wm-mail-from" style="display:flex;align-items:center;gap:.45rem;min-width:0">
            <div class="wm-sender-avatar" style="width:24px;height:24px;border-radius:50%;background:<?= $avatarBg ?>;color:#ffffff;display:inline-flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;flex-shrink:0;box-shadow:0 1px 2px rgba(0,0,0,0.1)">
                <?= htmlspecialchars($senderInitial) ?>
            </div>
            <?php if (empty($msg['is_read'])): ?>
            <span style="display:inline-block;width:7px;height:7px;background:var(--wm-primary);border-radius:50%;flex-shrink:0" title="Unread"></span>
            <?php endif; ?>
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($msg['from'] ?? '') ?></span>
        </div>
        <div class="wm-mail-date"><?= htmlspecialchars($msg['date'] ?? '') ?></div>
        <div class="wm-mail-subject" style="display:flex;align-items:center;gap:.45rem;min-width:0">
            <span class="wm-account-badge"
                  style="background:<?= $mColor['bg'] ?>;color:<?= $mColor['fg'] ?>;border-color:<?= $mColor['bd'] ?>"
                  title="<?= htmlspecialchars($msg['account_email'] ?? '') ?>">
                <?= htmlspecialchars($msg['account_label'] ?? $msg['account_email'] ?? '') ?>
            </span>
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0">
                <?php if (($msg['priority'] ?? 'normal') === 'high'): ?>
                <span title="High Priority" style="color:var(--wm-danger);margin-right:4px;font-weight:bold;font-size:1.1rem;line-height:1">!</span>
                <?php elseif (($msg['priority'] ?? 'normal') === 'low'): ?>
                <span title="Low Priority" style="color:var(--wm-primary);margin-right:4px;font-weight:bold;font-size:1.1rem;line-height:1">↓</span>
                <?php endif; ?>
                <?php if (!empty($msg['has_attachments'])): ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;vertical-align:middle;opacity:.6" title="Has attachments">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                </svg>
                <?php endif; ?>
                <?= htmlspecialchars($msg['subject'] ?? '(no subject)') ?>
            </span>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<div class="wm-pagination">
    <?php if ($page > 1): ?>
    <a href="?action=unified&ufolder=<?= $ufolderEnc ?>&page=<?= $page - 1 ?>" target="_self">‹</a>
    <?php else: ?>
    <span class="disabled">‹</span>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($pages, $page + 2);
    if ($start > 1) echo '<span style="border:none;width:auto;padding:0 .25rem;color:var(--wm-text-muted)">…</span>';
    for ($i = $start; $i <= $end; $i++):
    ?>
    <?php if ($i === $page): ?>
    <span class="current"><?= $i ?></span>
    <?php else: ?>
    <a href="?action=unified&ufolder=<?= $ufolderEnc ?>&page=<?= $i ?>" target="_self"><?= $i ?></a>
    <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $pages) echo '<span style="border:none;width:auto;padding:0 .25rem;color:var(--wm-text-muted)">…</span>'; ?>

    <?php if ($page < $pages): ?>
    <a href="?action=unified&ufolder=<?= $ufolderEnc ?>&page=<?= $page + 1 ?>" target="_self">›</a>
    <?php else: ?>
    <span class="disabled">›</span>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
