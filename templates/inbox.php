<?php
/**
 * Inbox / mail list view
 * @var array  $mailData   {messages, total, page, pages}
 * @var string $folder     current folder name
 * @var int    $accountId
 */
$messages   = $mailData['messages'] ?? [];
$total      = $mailData['total']    ?? 0;
$page       = $mailData['page']     ?? 1;
$pages      = $mailData['pages']    ?? 1;
$folderEnc  = urlencode($folder ?? 'INBOX');
$folderDisplay = htmlspecialchars($folder ?? 'INBOX');
?>

<div class="wm-toolbar">
    <!-- Bulk action toggle -->
    <input type="checkbox" id="select-all" title="Select all">

    <div id="bulk-bar" style="display:none;align-items:center;gap:.5rem">
        <button class="btn btn-outline btn-sm" onclick="bulkAction('read')">Mark read</button>
        <button class="btn btn-outline btn-sm" onclick="bulkAction('unread')">Mark unread</button>
        <button class="btn btn-outline btn-sm" onclick="bulkExport()">Export ZIP</button>
        <button class="btn btn-danger btn-sm" onclick="bulkAction('delete')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
            Delete
        </button>
    </div>

    <span style="font-size:.78rem;color:var(--wm-text-muted);margin-left:auto"><?= $total ?> message<?= $total !== 1 ? 's' : '' ?></span>

    <button class="btn btn-ghost btn-icon" title="Refresh" onclick="window.location.reload()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
    </button>

    <?php if (($isTrash ?? false) || ($isSpam ?? false)): ?>
    <form method="post" action="?action=empty_folder" onsubmit="return confirm('Permanently empty this folder? This cannot be undone.')" style="margin:0">
        <?= csrfInput() ?>
        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
        <button class="btn btn-outline btn-sm" style="color:var(--wm-danger);border-color:var(--wm-danger)">
            Empty <?= ($isTrash ?? false) ? 'Trash' : 'Spam' ?>
        </button>
    </form>
    <?php endif; ?>

    <div style="width:1px;height:20px;background:var(--wm-border);margin:0 .25rem"></div>

    <form id="import-eml-form" method="post" action="?action=import_eml" enctype="multipart/form-data" style="display:none">
        <?= csrfInput() ?>
        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
        <input type="file" name="eml_file" id="eml-file-input" accept=".eml" onchange="document.getElementById('import-eml-form').submit()">
    </form>
    <button class="btn btn-ghost btn-icon" title="Import .eml" onclick="document.getElementById('eml-file-input').click()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    </button>
</div>

<div class="wm-mail-list" id="mail-list">
    <?php if (empty($messages)): ?>
    <div style="text-align:center;padding:4rem 2rem;color:var(--wm-text-muted)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.3;display:block;margin:0 auto 1rem">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
        <p>No messages in <?= $folderDisplay ?></p>
    </div>
    <?php else: ?>
    <?php foreach ($messages as $msg): ?>
    <div class="wm-mail-row <?= $msg['is_read'] ? '' : 'unread' ?>"
         data-href="<?= '?action=view&folder=' . $folderEnc . '&msg=' . (int)$msg['msg_no'] ?>">
        <div class="wm-mail-check">
            <input type="checkbox" class="mail-checkbox" data-uid="<?= (int)$msg['msg_no'] ?>">
        </div>
        <div class="wm-mail-from">
            <?php if (!$msg['is_read']): ?>
            <span style="display:inline-block;width:8px;height:8px;background:var(--wm-primary);border-radius:50%;margin-right:6px;vertical-align:middle" title="Unread"></span>
            <?php endif; ?>
            <?= htmlspecialchars($msg['from']) ?>
        </div>
        <div class="wm-mail-date"><?= htmlspecialchars($msg['date']) ?></div>
        <div class="wm-mail-subject">
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
            <?= htmlspecialchars($msg['subject']) ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="wm-pagination">
    <?php if ($page > 1): ?>
    <a href="?action=inbox&folder=<?= $folderEnc ?>&page=<?= $page-1 ?>">‹</a>
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
    <a href="?action=inbox&folder=<?= $folderEnc ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $pages) echo '<span style="border:none;width:auto;padding:0 .25rem;color:var(--wm-text-muted)">…</span>'; ?>

    <?php if ($page < $pages): ?>
    <a href="?action=inbox&folder=<?= $folderEnc ?>&page=<?= $page+1 ?>">›</a>
    <?php else: ?>
    <span class="disabled">›</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function bulkAction(action) {
    var uids = getSelectedUids();
    if (!uids.length) return;
    
    if (action === 'delete') {
        var msg = '<?= ($isTrash ?? false) ? "Permanently delete selected messages? This cannot be undone." : "Move selected messages to Trash?" ?>';
        if (!confirm(msg)) return;
    }
    
    apiPost('?action=bulk', {
        action: action,
        uids:   uids,
        folder: '<?= addslashes($folder ?? 'INBOX') ?>'
    }).then(function(res) {
        if (res.ok) window.location.reload();
        else alert(res.error || 'Action failed.');
    });
}

function bulkExport() {
    var uids = getSelectedUids();
    if (!uids.length) return;
    window.location.href = '?action=export_zip&folder=<?= addslashes($folder ?? 'INBOX') ?>&uids=' + uids.join(',');
}
</script>
