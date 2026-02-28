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
    <?php if ($fav = Config::get('favicon_path')): ?>
    <link rel="icon" href="<?= htmlspecialchars($fav) ?>">
    <?php else: ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📧</text></svg>">
    <?php endif; ?>
    <?php if (function_exists('csrfToken')): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <?php endif; ?>

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

    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<?php if (!empty($setupBanner)): ?>
    <div class="wm-setup-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:.4rem"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?= $setupBanner ?>
    </div>
<?php endif; ?>

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

        <div class="wm-topbar-spacer"></div>

        <!-- Search -->
        <div class="input-group" style="max-width:400px;flex:1">
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

        <button type="button" id="contacts-btn" class="btn btn-outline" style="margin:0 .75rem .75rem;justify-content:center;gap:.5rem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Contacts
        </button>

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
            <?php if (function_exists('csrfInput')) echo csrfInput(); ?>
            <input type="text" name="name" id="new-folder-name" placeholder="Folder name"
                   style="font-size:.82rem;padding:.3rem .5rem;border:1px solid var(--wm-border);border-radius:5px;background:var(--wm-surface);color:var(--wm-text)">
            <div style="display:flex;gap:.3rem">
                <button type="submit" class="btn btn-primary btn-sm" style="flex:1;font-size:.78rem">Create</button>
                <button type="button" id="cancel-new-folder" class="btn btn-ghost btn-sm" style="font-size:.78rem">Cancel</button>
            </div>
        </form>
        <div style="padding:1.5rem .75rem 1rem;font-size:.78rem;color:var(--wm-text-muted);border-top:1px solid rgba(255,255,255,.1)">
            WebyMail v<?= Config::VERSION ?>
            <?php if ($newVer = Config::getNewerVersion()): ?>
            <div style="margin-top:.5rem">
                <a href="<?= Config::UPDATE_URL ?>" target="_blank" class="alert alert-info" style="display:block;padding:.4rem .6rem;font-size:.75rem;text-decoration:none;border-radius:4px;color:var(--wm-primary);border-color:var(--wm-primary);background:rgba(var(--wm-primary-rgb),.1)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align:middle;margin-right:.2rem"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                    Update v<?= htmlspecialchars($newVer) ?> available
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main -->
    <main class="wm-main">
        <?php if (Config::shouldShowSecurityBanner()): ?>
        <div class="alert alert-warning" style="margin:1rem;border-radius:8px;display:flex;align-items:center;gap:.75rem">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div style="flex:1">
                <strong>Security issues detected.</strong> Some system requirements or file permissions are not optimal.
            </div>
            <a href="?action=settings&tab=system" class="btn btn-outline btn-sm" style="background:rgba(0,0,0,.05)">Review</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-dismiss="4000" style="margin:1rem;border-radius:8px">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
</div>

<!-- Hidden elements (outside grid shell) -->
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

<form id="rename-folder-form" method="post" action="?action=rename_folder" style="display:none">
    <?php if (function_exists('csrfInput')) echo csrfInput(); ?>
    <input type="hidden" name="old_name" id="rename-old-name">
    <input type="hidden" name="new_name" id="rename-new-name">
</form>
<form id="delete-folder-form" method="post" action="?action=delete_folder" style="display:none">
    <?php if (function_exists('csrfInput')) echo csrfInput(); ?>
    <input type="hidden" name="name" id="delete-folder-name">
</form>

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

<!-- Contacts Modal -->
<div id="contacts-modal" class="wm-modal" style="display:none">
    <div class="wm-modal-content" style="max-width:600px">
        <div class="wm-modal-header">
            <h3 style="margin:0;font-size:1.1rem">Address Book</h3>
            <button type="button" class="btn-ghost btn-icon close-modal">&times;</button>
        </div>
        <div class="wm-modal-body">
            <div style="display:flex;gap:.5rem;margin-bottom:1.5rem">
                <input type="text" id="contact-search" class="form-control" placeholder="Search contacts..." style="flex:1">
                <button type="button" id="add-contact-toggle" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New
                </button>
            </div>

            <!-- Add Contact Form (Hidden by default) -->
            <form id="add-contact-form" style="display:none;background:var(--wm-surface-2);padding:1rem;border-radius:8px;margin-bottom:1.5rem;border:1px solid var(--wm-border)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
                    <div>
                        <label style="font-size:.75rem">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label style="font-size:.75rem">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div style="margin-bottom:.75rem">
                    <label style="font-size:.75rem">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem">
                    <button type="button" id="cancel-add-contact" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Contact</button>
                </div>
            </form>

            <div id="contacts-list-container" style="max-height:400px;overflow-y:auto">
                <div class="wm-email-loading">
                    <div class="spinner"></div>
                    <span>Loading contacts...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wm-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.5); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.wm-modal-content { background: var(--wm-surface); border-radius: 12px; width: 100%; box-shadow: var(--wm-shadow); display: flex; flex-direction: column; overflow: hidden; }
.wm-modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--wm-border); display: flex; align-items: center; justify-content: space-between; }
.wm-modal-body { padding: 1.25rem; overflow-y: auto; }
.wm-modal-header .close-modal { font-size: 1.5rem; line-height: 1; padding: 0 .5rem; }
.contact-item { display: flex; align-items: center; gap: .75rem; padding: .75rem; border-bottom: 1px solid var(--wm-border); border-radius: 8px; transition: background .15s; }
.contact-item:hover { background: var(--wm-surface-2); }
.contact-item:last-child { border-bottom: none; }
.contact-info { flex: 1; min-width: 0; }
.contact-name { font-weight: 600; font-size: .9rem; display: block; }
.contact-email { font-size: .8rem; color: var(--wm-text-muted); display: block; }
</style>

<script>
(function() {
    var contactsBtn = document.getElementById('contacts-btn');
    var modal = document.getElementById('contacts-modal');
    var closeBtn = modal.querySelector('.close-modal');
    var listContainer = document.getElementById('contacts-list-container');
    var addToggle = document.getElementById('add-contact-toggle');
    var addForm = document.getElementById('add-contact-form');
    var cancelAdd = document.getElementById('cancel-add-contact');
    var searchInput = document.getElementById('contact-search');
    var allContacts = [];
    var currentTargetInput = null;

    function loadContacts() {
        listContainer.innerHTML = '<div class="wm-email-loading"><div class="spinner"></div><span>Loading contacts...</span></div>';
        fetch('?action=contacts_list', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                allContacts = data.contacts;
                renderContacts(allContacts);
            }
        });
    }

    function renderContacts(contacts) {
        if (contacts.length === 0) {
            listContainer.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--wm-text-muted)">No contacts found.</div>';
            return;
        }
        listContainer.innerHTML = '';
        contacts.forEach(c => {
            var div = document.createElement('div');
            div.className = 'contact-item';
            div.innerHTML = `
                <div class="wm-account-avatar" style="width:36px;height:36px;font-size:.9rem">
                    ${(c.name || c.email).charAt(0).toUpperCase()}
                </div>
                <div class="contact-info">
                    <span class="contact-name">${escapeHtml(c.name)}</span>
                    <span class="contact-email">${escapeHtml(c.email)}</span>
                </div>
                <div style="display:flex;gap:.25rem">
                    ${currentTargetInput ? `<button type="button" class="btn btn-primary btn-sm use-contact" data-email="${escapeHtml(c.email)}">Use</button>` : ''}
                    <button type="button" class="btn btn-ghost btn-sm delete-contact" data-id="${c.id}" title="Delete">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    </button>
                </div>
            `;
            listContainer.appendChild(div);
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    contactsBtn.addEventListener('click', function() {
        currentTargetInput = null;
        modal.style.display = 'flex';
        loadContacts();
    });

    // Listen for custom event from compose page
    window.addEventListener('open-contacts-picker', function(e) {
        currentTargetInput = e.detail.target;
        modal.style.display = 'flex';
        loadContacts();
    });

    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        addForm.style.display = 'none';
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeBtn.click();
    });

    addToggle.addEventListener('click', function() {
        addForm.style.display = addForm.style.display === 'none' ? 'block' : 'none';
        if (addForm.style.display === 'block') addForm.querySelector('input[name="name"]').focus();
    });

    cancelAdd.addEventListener('click', function() {
        addForm.style.display = 'none';
    });

    addForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(addForm);
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fetch('?action=contacts_add', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                addForm.reset();
                addForm.style.display = 'none';
                loadContacts();
            } else {
                alert(data.error || 'Failed to add contact');
            }
        });
    });

    listContainer.addEventListener('click', function(e) {
        var delBtn = e.target.closest('.delete-contact');
        if (delBtn) {
            if (!confirm('Delete this contact?')) return;
            var fd = new FormData();
            fd.append('id', delBtn.dataset.id);
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
            fetch('?action=contacts_delete', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) loadContacts();
            });
            return;
        }

        var useBtn = e.target.closest('.use-contact');
        if (useBtn && currentTargetInput) {
            var email = useBtn.dataset.email;
            var input = document.getElementById(currentTargetInput);
            if (input) {
                var val = input.value.trim();
                if (val) {
                    input.value = val + ', ' + email;
                } else {
                    input.value = email;
                }
                modal.style.display = 'none';
            }
        }
    });

    searchInput.addEventListener('input', function() {
        var q = searchInput.value.toLowerCase();
        var filtered = allContacts.filter(c => 
            c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q)
        );
        renderContacts(filtered);
    });
})();
</script>

<script src="assets/js/app.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
