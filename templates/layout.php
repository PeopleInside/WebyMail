<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $brandName = function_exists('appName') ? appName() : Config::get('app_name', 'WebyMail');
    $activeAccount = null;
    if (!empty($accounts ?? []) && isset($session['account_id'])) {
        foreach ($accounts as $acc) {
            if ((int)$acc['id'] === (int)$session['account_id']) {
                $activeAccount = $acc;
                break;
            }
        }
    }
    $activeEmail = $activeAccount['email'] ?? ($session['email'] ?? '');
    $activeLabel = $activeAccount['label'] ?? ($activeAccount['email'] ?? ($session['display_name'] ?? $activeEmail));
    ?>
    <title><?= htmlspecialchars($pageTitle ?? $brandName) ?></title>
    <meta name="robots" content="noindex,nofollow">

    <!--
        Theme is applied BEFORE CSS loads to prevent flash:
        Read localStorage immediately and set data-theme attribute.
    -->
    <script>
        (function(){
            var serverTheme = null;
            <?php
            $serverTheme = (isset($session) && isset($session['theme']) && in_array($session['theme'], Config::THEMES, true))
                ? $session['theme']
                : null;
            ?>
            <?php if ($serverTheme !== null): ?>
            serverTheme = <?= json_encode($serverTheme) ?>;
            <?php endif; ?>
            var t = localStorage.getItem('wm_theme') || serverTheme;
            if (serverTheme && !localStorage.getItem('wm_theme')) {
                localStorage.setItem('wm_theme', serverTheme);
            }
            if (t && t !== 'system') document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <link rel="stylesheet" href="assets/css/style.css">

    <?php
    $faviconPath = Config::get('favicon_path', '');
    if ($faviconPath !== '' && strncmp($faviconPath, 'assets/', 7) === 0) {
        $faviconExt  = strtolower(pathinfo($faviconPath, PATHINFO_EXTENSION));
        $faviconMime = $faviconExt === 'svg' ? 'image/svg+xml' : 'image/x-icon';
        echo '<link rel="icon" type="' . htmlspecialchars($faviconMime) . '" href="' . htmlspecialchars($faviconPath) . '">' . "\n    ";
    }
    ?>

    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>

<?php if (!empty($shellLayout)): ?>
<!-- App shell for authenticated pages -->
<div class="wm-shell">

    <!-- Top bar -->
    <header class="wm-topbar">
        <button class="btn-ghost btn-icon" id="sidebar-toggle" title="Toggle sidebar" style="color:#fff">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <a href="?action=inbox" class="brand">Weby<span>Mail</span></a>

        <!-- Search -->
        <div class="input-group" style="max-width:320px;flex:1">
            <form method="get" action="" style="display:contents">
                <input type="hidden" name="action" value="search">
                <input type="text" name="q" class="form-control" placeholder="Search mail…"
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                       style="border-radius:6px 0 0 6px;height:32px;font-size:.82rem">
                <button type="submit" class="btn btn-primary" style="border-radius:0 6px 6px 0;height:32px;padding:.2rem .75rem">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </button>
            </form>
        </div>

        <div class="wm-topbar-spacer"></div>

        <!-- Theme toggle -->
        <button class="theme-toggle" title="Toggle theme"><!-- filled by JS --></button>

        <!-- User menu -->
        <div style="position:relative">
            <button class="btn-ghost btn" style="color:rgba(255,255,255,.85);gap:.5rem" id="user-menu-btn">
                <div class="wm-account-avatar" style="width:28px;height:28px;font-size:.75rem">
                    <?= strtoupper(substr($activeLabel ?: $activeEmail ?: 'U', 0, 1)) ?>
                </div>
                <span style="font-size:.82rem;max-width:140px;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($activeEmail ?: $activeLabel) ?>
                </span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div id="user-menu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:8px;min-width:200px;box-shadow:var(--wm-shadow);z-index:300;overflow:hidden">
                <?php if (!empty($accounts) && count($accounts) > 1): ?>
                <div style="padding:.6rem 1rem;font-size:.75rem;font-weight:600;color:var(--wm-text-muted);text-transform:uppercase;letter-spacing:.03em">
                    Accounts
                </div>
                <?php foreach ($accounts as $acc): ?>
                <button type="button" data-switch-account="<?= (int)$acc['id'] ?>"
                        style="display:flex;width:100%;align-items:center;gap:.6rem;padding:.55rem 1rem;font-size:.85rem;background:none;border:none;color:var(--wm-text);text-align:left;cursor:pointer">
                    <div class="wm-account-avatar" style="width:28px;height:28px;font-size:.75rem">
                        <?= strtoupper(substr($acc['label'] ?: $acc['email'], 0, 1)) ?>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column">
                        <span style="font-weight:600;"><?= htmlspecialchars($acc['label'] ?: $acc['email']) ?></span>
                        <span style="font-size:.75rem;color:var(--wm-text-muted)"><?= htmlspecialchars($acc['email']) ?></span>
                    </div>
                    <?php if ($acc['id'] == $session['account_id']): ?>
                    <span style="font-size:.7rem;color:var(--wm-success);font-weight:700;">Active</span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
                <div style="height:1px;background:var(--wm-border)"></div>
                <?php endif; ?>
                <a href="?action=settings" style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;font-size:.85rem;color:var(--wm-text);text-decoration:none">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Settings
                </a>
                <div style="height:1px;background:var(--wm-border)"></div>
                <a href="?action=logout" style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;font-size:.85rem;color:var(--wm-danger);text-decoration:none">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign out
                </a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="wm-sidebar">
        <a href="?action=compose" class="btn btn-primary" style="margin:.5rem .75rem .75rem;justify-content:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Compose
        </a>

        <div class="wm-sidebar-section" style="display:flex;align-items:center;justify-content:space-between">
            <span>Folders</span>
            <button type="button" id="new-folder-btn" title="New folder"
                    style="background:none;border:none;cursor:pointer;color:var(--wm-text-muted);padding:.1rem .3rem;font-size:1rem;line-height:1">+</button>
        </div>
        <?php foreach ($folders ?? [] as $f): ?>
        <div class="wm-sidebar-folder-row" style="display:flex;align-items:center;position:relative">
            <a href="?action=inbox&folder=<?= urlencode($f['name']) ?>"
               class="<?= ($currentFolder ?? 'INBOX') === $f['name'] ? 'active' : '' ?>"
               style="flex:1;min-width:0">
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($f['display']) ?></span>
                <?php if (!empty($f['unread'])): ?>
                <span class="badge"><?= (int)$f['unread'] ?></span>
                <?php endif; ?>
            </a>
            <?php if (strtoupper($f['name']) !== 'INBOX'): ?>
            <button type="button"
                    class="wm-folder-menu-btn"
                    data-folder="<?= htmlspecialchars($f['name']) ?>"
                    title="Folder options"
                    style="background:none;border:none;cursor:pointer;color:var(--wm-text-muted);padding:.2rem .4rem;font-size:.85rem;opacity:0;flex-shrink:0">⋯</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Folder management forms (hidden) -->
        <form id="new-folder-form" method="post" action="?action=create_folder"
              style="display:none;padding:.4rem .75rem;gap:.3rem;flex-direction:column">
            <input type="text" name="name" id="new-folder-name" placeholder="Folder name"
                   style="font-size:.82rem;padding:.3rem .5rem;border:1px solid var(--wm-border);border-radius:5px;background:var(--wm-surface);color:var(--wm-text)">
            <div style="display:flex;gap:.3rem">
                <button type="submit" class="btn btn-primary btn-sm" style="flex:1;font-size:.78rem">Create</button>
                <button type="button" id="cancel-new-folder" class="btn btn-ghost btn-sm" style="font-size:.78rem">Cancel</button>
            </div>
        </form>
    </nav>

    <!-- Folder context menu (shared, positioned dynamically) -->
    <div id="folder-ctx-menu" style="display:none;position:fixed;background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:8px;box-shadow:var(--wm-shadow);z-index:500;min-width:140px;overflow:hidden">
        <button type="button" id="folder-rename-btn" style="display:flex;width:100%;padding:.55rem 1rem;font-size:.85rem;background:none;border:none;color:var(--wm-text);cursor:pointer;gap:.5rem;align-items:center">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Rename
        </button>
        <div style="height:1px;background:var(--wm-border)"></div>
        <button type="button" id="folder-delete-btn" style="display:flex;width:100%;padding:.55rem 1rem;font-size:.85rem;background:none;border:none;color:var(--wm-danger);cursor:pointer;gap:.5rem;align-items:center">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            Delete
        </button>
    </div>

    <!-- Hidden forms for folder rename/delete -->
    <form id="rename-folder-form" method="post" action="?action=rename_folder" style="display:none">
        <input type="hidden" name="old_name" id="rename-old-name">
        <input type="hidden" name="new_name" id="rename-new-name">
    </form>
    <form id="delete-folder-form" method="post" action="?action=delete_folder" style="display:none">
        <input type="hidden" name="name" id="delete-folder-name">
    </form>

    <!-- Main -->
    <main class="wm-main">
        <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-dismiss="4000" style="margin:1rem;border-radius:8px">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>

</div>

<script>
// User menu toggle
document.getElementById('user-menu-btn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    var m = document.getElementById('user-menu');
    m.style.display = m.style.display === 'none' ? 'block' : 'none';
});
document.addEventListener('click', function() {
    var m = document.getElementById('user-menu');
    if (m) m.style.display = 'none';
});

// Folder management
(function() {
    var newFolderBtn    = document.getElementById('new-folder-btn');
    var newFolderForm   = document.getElementById('new-folder-form');
    var cancelNewFolder = document.getElementById('cancel-new-folder');
    var newFolderName   = document.getElementById('new-folder-name');
    var ctxMenu         = document.getElementById('folder-ctx-menu');
    var renameFolderBtn = document.getElementById('folder-rename-btn');
    var deleteFolderBtn = document.getElementById('folder-delete-btn');
    var renameForm      = document.getElementById('rename-folder-form');
    var deleteForm      = document.getElementById('delete-folder-form');
    var renameOldName   = document.getElementById('rename-old-name');
    var renameNewName   = document.getElementById('rename-new-name');
    var deleteFolderName = document.getElementById('delete-folder-name');
    var activeFolder    = null;

    // Show/hide new folder form
    if (newFolderBtn && newFolderForm) {
        newFolderBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var visible = newFolderForm.style.display !== 'none';
            newFolderForm.style.display = visible ? 'none' : 'flex';
            if (!visible && newFolderName) { newFolderName.value = ''; newFolderName.focus(); }
        });
    }
    if (cancelNewFolder && newFolderForm) {
        cancelNewFolder.addEventListener('click', function() {
            newFolderForm.style.display = 'none';
        });
    }

    // Show folder menu buttons on hover
    document.querySelectorAll('.wm-sidebar-folder-row').forEach(function(row) {
        var btn = row.querySelector('.wm-folder-menu-btn');
        if (!btn) return;
        row.addEventListener('mouseenter', function() { btn.style.opacity = '1'; });
        row.addEventListener('mouseleave', function() { btn.style.opacity = '0'; });
    });

    // Open context menu
    document.querySelectorAll('.wm-folder-menu-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            activeFolder = btn.dataset.folder;
            var rect = btn.getBoundingClientRect();
            ctxMenu.style.top  = rect.bottom + 'px';
            ctxMenu.style.left = rect.left + 'px';
            ctxMenu.style.display = 'block';
        });
    });

    // Close context menu on outside click
    document.addEventListener('click', function() {
        if (ctxMenu) ctxMenu.style.display = 'none';
    });
    if (ctxMenu) {
        ctxMenu.addEventListener('click', function(e) { e.stopPropagation(); });
    }

    // Rename action
    if (renameFolderBtn) {
        renameFolderBtn.addEventListener('click', function() {
            if (!activeFolder) return;
            ctxMenu.style.display = 'none';
            var newName = prompt('Rename folder "' + activeFolder + '" to:', activeFolder);
            if (!newName || newName.trim() === '' || newName.trim() === activeFolder) return;
            renameOldName.value = activeFolder;
            renameNewName.value = newName.trim();
            renameForm.submit();
        });
    }

    // Delete action
    if (deleteFolderBtn) {
        deleteFolderBtn.addEventListener('click', function() {
            if (!activeFolder) return;
            ctxMenu.style.display = 'none';
            if (!confirm('Delete folder "' + activeFolder + '"? This cannot be undone.')) return;
            deleteFolderName.value = activeFolder;
            deleteForm.submit();
        });
    }
})();
</script>

<?php else: ?>
<!-- Auth page (no shell) – just render content -->
<?= $content ?? '' ?>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
