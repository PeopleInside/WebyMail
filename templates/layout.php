<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // isRequestSecure() is defined in index.php. When layout.php is included
    // from setup.php the function does not exist yet, so we define a fallback.
    if (!function_exists('isRequestSecure')) {
        function isRequestSecure(): bool {
            return Config::isRequestSecure();
        }
    }
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
    <script nonce="<?= $cspNonce ?>">
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

    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(Config::VERSION) ?>">
    <script nonce="<?= $cspNonce ?>" src="assets/js/app.js?v=<?= urlencode(Config::VERSION) ?>" defer></script>

    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<?php if (!empty($setupBanner)): ?>
    <div class="wm-setup-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:.4rem"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?= $setupBanner ?>
    </div>
<?php endif; ?>

<?php if (!isRequestSecure()): ?>
    <div class="wm-https-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-right:.4rem"><path d="M12 1.5C8.41 1.5 5.5 4.41 5.5 8v3.5H4l8 8 8-8h-1.5V8c0-3.59-2.91-6.5-6.5-6.5z"/><path d="M9 12.5v-1c0-1.66 1.34-3 3-3s3 1.34 3 3v1"/></svg>
        WebyMail is loaded over HTTP. For your security, use HTTPS to protect login credentials and session data.
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
        <a href="?action=inbox" class="brand" target="_self">Weby<span>Mail</span></a>

        <div class="wm-topbar-spacer"></div>

        <!-- Mobile Search Toggle -->
        <button class="btn-ghost btn-icon md-hide" id="mobile-search-toggle" title="Search" style="color:#fff">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </button>

        <!-- Search -->
        <div class="wm-topbar-search">
            <form method="get" action="" target="_self" style="display:contents">
                <input type="hidden" name="action" value="search">
                <div class="search-input-wrapper">
                    <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="q" class="form-control" placeholder="Search mail…"
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </div>
            </form>
        </div>

        <div class="wm-topbar-spacer"></div>

        <!-- Theme toggle -->
        <button class="theme-toggle" title="Toggle theme"><!-- filled by JS --></button>

        <!-- User menu -->
        <div style="position:relative;margin-left:auto;flex-shrink:0">
            <button class="btn-ghost btn" style="color:rgba(255,255,255,.85);gap:.5rem" id="user-menu-btn">
                <div class="wm-account-avatar" style="width:28px;height:28px;font-size:.75rem">
                    <?= strtoupper(substr($activeLabel ?: $activeEmail ?: 'U', 0, 1)) ?>
                </div>
                <span class="wm-user-email" style="font-size:.82rem">
                    <?= htmlspecialchars($activeEmail ?: $activeLabel) ?>
                </span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div id="user-menu" class="wm-user-menu" style="display:none;">
                <?php if (!empty($accounts) && count($accounts) > 1): ?>
                <a href="?action=unified&ufolder=inbox" target="_self" style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;font-size:.85rem;font-weight:600;color:var(--wm-primary);text-decoration:none;border-bottom:1px solid var(--wm-border)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><polyline points="3 10 9 10 11 13 13 13 15 10 21 10"/></svg>
                    Unified Mailbox
                </a>
                <div style="padding:.6rem 1rem;font-size:.75rem;font-weight:600;color:var(--wm-text-muted);text-transform:uppercase;letter-spacing:.03em">
                    Accounts
                </div>
                <?php foreach ($accounts as $acc): ?>
                <button type="button" data-switch-account="<?= (int)$acc['id'] ?>"
                        style="display:flex;width:100%;align-items:center;gap:.6rem;padding:.55rem 1rem;font-size:.85rem;background:none;border:none;color:var(--wm-text);text-align:left;cursor:pointer">
                    <div class="wm-account-avatar" style="width:28px;height:28px;font-size:.75rem;flex-shrink:0">
                        <?= strtoupper(substr($acc['label'] ?: $acc['email'], 0, 1)) ?>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;min-width:0">
                        <span style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($acc['label'] ?: $acc['email']) ?></span>
                        <span style="font-size:.75rem;color:var(--wm-text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($acc['email']) ?></span>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.2rem;flex-shrink:0">
                        <?php if ($acc['id'] == $session['account_id']): ?>
                        <span style="font-size:.7rem;color:var(--wm-success);font-weight:700">Active</span>
                        <?php endif; ?>
                        <?php if (($acc['validation_status'] ?? '') === 'invalid'): ?>
                        <span style="font-size:.7rem;color:var(--wm-danger);font-weight:700;display:flex;align-items:center;gap:.2rem">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Invalid
                        </span>
                        <?php endif; ?>
                    </div>
                </button>
                <?php endforeach; ?>
                <div style="height:1px;background:var(--wm-border)"></div>
                <?php endif; ?>
                <a href="?action=settings" target="_self" style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;font-size:.85rem;color:var(--wm-text);text-decoration:none">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Settings
                </a>
                <div style="height:1px;background:var(--wm-border)"></div>
                <form id="logout-form" method="post" action="?action=logout" style="margin:0">
                    <?= csrfInput() ?>
                    <button type="submit" class="wm-link-button" style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;font-size:.85rem;color:var(--wm-danger);text-decoration:none;background:none;border:none;cursor:pointer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="wm-sidebar" data-user-id="<?= (int)($session['user_id'] ?? 0) ?>">
        <a href="?action=compose" class="btn btn-primary" target="_self" style="margin:.5rem .75rem .75rem;justify-content:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Compose
        </a>

        <button type="button" id="contacts-btn" class="btn btn-outline" style="margin:0 .75rem .75rem;justify-content:center;gap:.5rem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Contacts
        </button>

        <?php
        $getFolderIcon = static function(string $name): string {
            $n = strtolower($name);
            if (str_contains($n, 'inbox')) {
                return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';
            }
            if (str_contains($n, 'sent') || str_contains($n, 'inviat')) {
                return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
            }
            if (str_contains($n, 'draft') || str_contains($n, 'bozz')) {
                return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
            }
            if (str_contains($n, 'trash') || str_contains($n, 'deleted') || str_contains($n, 'cestino') || str_contains($n, 'eliminat')) {
                return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
            }
            if (str_contains($n, 'spam') || str_contains($n, 'junk') || str_contains($n, 'indesiderat')) {
                return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            }
            if (str_contains($n, 'archive') || str_contains($n, 'archivio')) {
                return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>';
            }
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
        };
        ?>
        <?php
            $curAccId      = (int) ($currentAccount['id'] ?? $session['account_id'] ?? 0);
            $curAccLabel   = trim((string) ($currentAccount['label'] ?? ''));
            $curAccEmail   = trim((string) ($currentAccount['email'] ?? ''));
            $curAccDisplay = $curAccLabel !== '' ? $curAccLabel : $curAccEmail;
        ?>
        <div class="wm-sidebar-section" style="display:flex;align-items:center;justify-content:space-between">
            <span style="display:flex;align-items:center;gap:.4rem">
                Folders
                <?php if (($currentAccount['validation_status'] ?? '') === 'invalid'): ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--wm-danger)" stroke-width="3" title="Account invalid"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?php endif; ?>
            </span>
            <button type="button" id="new-folder-btn" title="New folder"
                    style="background:none;border:none;cursor:pointer;color:var(--wm-text-muted);padding:.1rem .3rem;font-size:1rem;line-height:1">+</button>
        </div>
        <?php if (count($accounts ?? []) > 1 && $curAccDisplay !== ''): ?>
        <div class="wm-sidebar-account-tag" id="wm-sidebar-account-tag" title="<?= htmlspecialchars($curAccEmail ?: $curAccDisplay) ?>">
            <span class="wm-acc-dot" style="background:var(--wm-primary)"></span>
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($curAccDisplay) ?></span>
        </div>
        <?php endif; ?>
        <div id="wm-active-account-folders">
        <?php foreach ($folders ?? [] as $f): ?>
        <div class="wm-sidebar-folder-row" style="display:flex;align-items:center;position:relative">
            <a href="?action=inbox&folder=<?= urlencode($f['name']) ?>"
               class="<?= (empty($isUnified) && ($currentFolder ?? 'INBOX') === $f['name']) ? 'active' : '' ?>"
               data-folder="<?= htmlspecialchars($f['name']) ?>"
               target="_self"
               style="flex:1;min-width:0;display:flex;align-items:center;gap:.65rem">
                <?= $getFolderIcon($f['name']) ?>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1"><?= htmlspecialchars($f['display']) ?></span>
                <span class="badge" data-folder-unread="<?= htmlspecialchars($f['name']) ?>" <?= empty($f['unread']) ? 'style="display:none"' : '' ?>><?= (int)$f['unread'] ?></span>
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
        </div>

        <?php if (!empty($accounts) && count($accounts) > 1):
            $inUnifiedMode   = !empty($isUnified);
            $unifiedMenuOpen = $inUnifiedMode || !empty($unifiedSidebarOpen);
            $activeUfolder   = $unifiedFolder ?? 'inbox';
            $navUnifiedFolders = !empty($unifiedFolders) ? $unifiedFolders : [
                ['key' => 'inbox', 'label' => 'Inbox', 'unread' => 0],
                ['key' => 'sent',  'label' => 'Sent',  'unread' => 0],
                ['key' => 'spam',  'label' => 'Spam',  'unread' => 0],
                ['key' => 'trash', 'label' => 'Trash', 'unread' => 0],
            ];
        ?>
        <div class="wm-unified-nav">
            <div class="wm-sidebar-section" style="margin-top:.25rem">
                <span>Unified</span>
            </div>
            <div class="wm-unified-row <?= $inUnifiedMode ? 'active' : '' ?>" style="display:flex;align-items:center;position:relative">
                <a href="?action=unified&ufolder=inbox"
                   class="wm-unified-link <?= $inUnifiedMode ? 'active' : '' ?>"
                   target="_self"
                   style="flex:1;min-width:0;display:flex;align-items:center;gap:.6rem">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.9"><rect x="3" y="4" width="18" height="16" rx="2"/><polyline points="3 10 9 10 11 13 13 13 15 10 21 10"/></svg>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">All Accounts</span>
                </a>
                <button type="button" id="unified-toggle" class="wm-unified-toggle <?= $unifiedMenuOpen ? 'expanded' : '' ?>" title="Toggle unified folders" aria-expanded="<?= $unifiedMenuOpen ? 'true' : 'false' ?>" style="background:transparent;border:none;color:var(--wm-sidebar-text,#c8d8e8);appearance:none;-webkit-appearance:none;cursor:pointer;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;padding:.4rem .65rem;margin-right:.25rem;border-radius:6px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
            <div id="unified-subfolders" class="wm-unified-sub" style="display:<?= $unifiedMenuOpen ? 'block' : 'none' ?>">
                <?php foreach ($navUnifiedFolders as $uf):
                    $uKey   = (string) ($uf['key'] ?? 'inbox');
                    $uLabel = (string) ($uf['label'] ?? 'Inbox');
                    $uUnread = (int) ($uf['unread'] ?? 0);
                ?>
                <a href="?action=unified&ufolder=<?= urlencode($uKey) ?>" class="<?= ($inUnifiedMode && $activeUfolder === $uKey) ? 'active' : '' ?>" data-ufolder="<?= htmlspecialchars($uKey) ?>" target="_self">
                    <?= $getFolderIcon($uLabel) ?>
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1"><?= htmlspecialchars($uLabel) ?></span>
                    <?php if ($uUnread > 0): ?>
                    <span class="badge"><?= $uUnread ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
            $accDots = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2'];
            $hasUnifiedAccGroups = !empty($unifiedAccountFolders);
        ?>
        <div id="unified-accounts-section" style="display:<?= ($unifiedMenuOpen && $hasUnifiedAccGroups) ? 'block' : 'none' ?>">
            <div class="wm-sidebar-section" style="display:flex;align-items:center;justify-content:space-between;margin-top:.75rem">
                <span>Unified Account and Folders</span>
            </div>
            <div id="unified-accounts-list">
                <?php if ($hasUnifiedAccGroups): ?>
                <?php foreach ($unifiedAccountFolders as $accIdx => $accGroup):
                    $gAccId   = (int) ($accGroup['account_id'] ?? 0);
                    $gLabel   = (string) ($accGroup['label'] ?? $accGroup['email'] ?? 'Account');
                    $gEmail   = (string) ($accGroup['email'] ?? '');
                    $gFolders = is_array($accGroup['folders'] ?? null) ? $accGroup['folders'] : [];
                    $gUnread  = (int) ($accGroup['unread_total'] ?? 0);
                    $gError   = $accGroup['error'] ?? null;
                    $gDot     = $accDots[$accIdx % count($accDots)];
                    $groupHasActiveFolder = empty($isUnified)
                        && in_array($currentAction ?? 'inbox', ['inbox', 'view', 'search'], true)
                        && $gAccId === $curAccId;
                ?>
                <div class="wm-acc-group" data-account-id="<?= $gAccId ?>" data-has-active="<?= $groupHasActiveFolder ? '1' : '0' ?>">
                    <button type="button"
                            class="wm-acc-group-toggle"
                            aria-expanded="false"
                            title="<?= htmlspecialchars($gEmail) ?>">
                        <span class="wm-acc-dot" style="background:<?= $gDot ?>"></span>
                        <span class="wm-acc-label"><?= htmlspecialchars($gLabel) ?></span>
                        <?php if ($gError !== null): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--wm-danger)" stroke-width="2.5" style="flex-shrink:0" title="<?= htmlspecialchars($gError) ?>"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <?php elseif ($gUnread > 0): ?>
                        <span class="badge"><?= $gUnread ?></span>
                        <?php endif; ?>
                        <svg class="wm-acc-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="wm-acc-group-folders" style="display:none">
                        <?php if ($gError !== null && empty($gFolders)): ?>
                        <div class="wm-acc-group-error"><?= htmlspecialchars($gError) ?></div>
                        <?php else: ?>
                        <?php foreach ($gFolders as $f):
                            $isGroupFolderActive = $groupHasActiveFolder && ($currentFolder ?? 'INBOX') === $f['name'];
                        ?>
                        <a href="?action=inbox&account_id=<?= $gAccId ?>&folder=<?= urlencode($f['name']) ?>"
                           class="<?= $isGroupFolderActive ? 'active' : '' ?>"
                           data-account-id="<?= $gAccId ?>"
                           data-folder="<?= htmlspecialchars($f['name']) ?>"
                           target="_self"
                           style="display:flex;align-items:center;gap:.6rem">
                            <?= $getFolderIcon($f['name']) ?>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1"><?= htmlspecialchars($f['display']) ?></span>
                            <?php if (!empty($f['unread'])): ?>
                            <span class="badge"><?= (int)$f['unread'] ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Folder management forms (hidden) -->
        <form id="new-folder-form" method="post" action="?action=create_folder" target="_self"
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
            <?php $localAhead = Config::isLocalVersionAheadOfGitHub(); ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                <span>
                    WebyMail v<?= Config::VERSION ?>
                    <?php if ($localAhead === true): ?>
                    <a href="?action=settings&tab=system" id="version-mismatch-tag" style="margin-left:.35rem;font-size:.66rem;color:var(--wm-warning);font-weight:700;border:1px solid rgba(var(--wm-warning-rgb),.35);border-radius:999px;padding:.1rem .4rem;display:inline-flex;align-items:center;gap:.2rem;text-decoration:none" title="Installed version (v<?= htmlspecialchars(Config::VERSION) ?>) is newer than the latest GitHub release. This mismatch may occur if you are using a development version or if the latest check failed. Click for more details.">
                        ⚠ version mismatch
                    </a>
                    <?php endif; ?>
                </span>
                <?php 
                $suggestions = Config::getSecuritySuggestions();
                if (!empty($suggestions)): 
                ?>
                <div style="position:relative;display:inline-block">
                    <button type="button" id="security-suggestions-btn" class="btn btn-icon btn-xs" 
                            style="background:var(--wm-warning);color:#000;border-radius:6px;width:22px;height:22px;padding:0;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,0.1)"
                            title="Security Suggestions Available">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </button>
                    <div id="security-suggestions-popover" style="display:none;position:absolute;bottom:100%;right:0;background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:8px;box-shadow:var(--wm-shadow);width:220px;z-index:1000;padding:.75rem;margin-bottom:.75rem">
                        <div style="font-weight:600;font-size:.7rem;margin-bottom:.6rem;text-transform:uppercase;color:var(--wm-text-muted);letter-spacing:.03em">Security Suggestions</div>
                        <?php foreach ($suggestions as $s): ?>
                        <a href="<?= $s['url'] ?>" style="display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;font-size:.78rem;color:var(--wm-warning);text-decoration:none;border-radius:6px;background:rgba(var(--wm-warning-rgb),.08);margin-bottom:.4rem;border:1px solid rgba(var(--wm-warning-rgb),.15);transition:background .2s">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <?= htmlspecialchars($s['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php
            $newVer       = Config::getNewerVersion();
            $repoAvail    = Config::isGitHubRepoAvailable();
            $repoMissing  = ($repoAvail === false); // null = not checked yet
            ?>
            <?php if ($newVer): ?>
            <div style="margin-top:.5rem">
                <a href="<?= Config::UPDATE_URL ?>" target="_blank" class="alert alert-info" style="display:block;padding:.4rem .6rem;font-size:.75rem;text-decoration:none;border-radius:4px;color:var(--wm-primary);border-color:var(--wm-primary);background:rgba(var(--wm-primary-rgb),.1)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align:middle;margin-right:.2rem"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                    Update v<?= htmlspecialchars($newVer) ?> available
                </a>
            </div>
            <?php endif; ?>
            <?php if ($repoMissing): ?>
            <div style="margin-top:.5rem">
                <a href="?action=settings&tab=system" style="display:flex;align-items:center;gap:.35rem;padding:.4rem .6rem;font-size:.73rem;text-decoration:none;border-radius:4px;color:var(--wm-warning);border:1px solid rgba(var(--wm-warning-rgb),.4);background:rgba(var(--wm-warning-rgb),.08)" title="GitHub repository could not be reached – may be private or removed">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Repo unreachable
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
        <?php $dismissMs = flashDismissMs($flash['type'] ?? ''); ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-dismiss="<?= (int)$dismissMs ?>" style="margin:1rem;border-radius:8px">
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

<script nonce="<?= $cspNonce ?>">
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

// Unified sidebar subfolder & per-account accordion toggles (persistent via localStorage)
(function() {
    var sidebarEl  = document.querySelector('.wm-sidebar');
    var userIdTag  = sidebarEl ? ('u' + (sidebarEl.dataset.userId || '0') + '_') : 'u0_';
    var UNIFIED_OPEN_KEY = 'wm_unified_sidebar_open_' + userIdTag;
    var toggle     = document.getElementById('unified-toggle');
    var sub        = document.getElementById('unified-subfolders');
    var accSection = document.getElementById('unified-accounts-section');
    var accList    = document.getElementById('unified-accounts-list');
    var accDots    = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2'];

    function readStoredFlag(lsKey, legacySsKey) {
        try {
            var val = localStorage.getItem(lsKey);
            if (val === '1' || val === '0') return val;
            if (legacySsKey) {
                var legacy = sessionStorage.getItem(legacySsKey);
                if (legacy === '1' || legacy === '0') {
                    localStorage.setItem(lsKey, legacy);
                    return legacy;
                }
            }
        } catch (e) {}
        return null;
    }

    function writeStoredFlag(lsKey, isOpen) {
        try {
            localStorage.setItem(lsKey, isOpen ? '1' : '0');
        } catch (e) {}
    }

    function getFolderSvgHtml(name) {
        var n = (name || '').toLowerCase();
        if (n.indexOf('inbox') !== -1) {
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';
        }
        if (n.indexOf('sent') !== -1 || n.indexOf('inviat') !== -1) {
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
        }
        if (n.indexOf('draft') !== -1 || n.indexOf('bozz') !== -1) {
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
        }
        if (n.indexOf('trash') !== -1 || n.indexOf('deleted') !== -1 || n.indexOf('cestino') !== -1 || n.indexOf('eliminat') !== -1) {
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
        }
        if (n.indexOf('spam') !== -1 || n.indexOf('junk') !== -1 || n.indexOf('indesiderat') !== -1) {
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        }
        if (n.indexOf('archive') !== -1 || n.indexOf('archivio') !== -1) {
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>';
        }
        return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.85"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    }

    function populateUnifiedSubfolders(unifiedFolders) {
        if (!sub || !Array.isArray(unifiedFolders) || unifiedFolders.length === 0) return;
        var params = new URLSearchParams(window.location.search);
        var inUnified = params.get('action') === 'unified';
        var activeUfolder = inUnified ? (params.get('ufolder') || 'inbox').toLowerCase() : '';

        sub.innerHTML = '';
        unifiedFolders.forEach(function(uf) {
            var uKey = String(uf.key || 'inbox');
            var uLabel = String(uf.label || 'Inbox');
            var uUnread = parseInt(uf.unread || 0, 10) || 0;

            var a = document.createElement('a');
            a.href = '?action=unified&ufolder=' + encodeURIComponent(uKey);
            a.target = '_self';
            a.dataset.ufolder = uKey;
            if (inUnified && activeUfolder === uKey.toLowerCase()) {
                a.className = 'active';
            }

            var iconWrap = document.createElement('span');
            iconWrap.innerHTML = getFolderSvgHtml(uLabel);
            if (iconWrap.firstChild) a.appendChild(iconWrap.firstChild);

            var lblSpan = document.createElement('span');
            lblSpan.style.overflow = 'hidden';
            lblSpan.style.textOverflow = 'ellipsis';
            lblSpan.style.whiteSpace = 'nowrap';
            lblSpan.style.flex = '1';
            lblSpan.textContent = uLabel;
            a.appendChild(lblSpan);

            if (uUnread > 0) {
                var badge = document.createElement('span');
                badge.className = 'badge';
                badge.textContent = String(uUnread);
                a.appendChild(badge);
            }
            sub.appendChild(a);
        });
    }

    function bindAccountGroup(group) {
        var accId = group.dataset.accountId || '';
        var btn   = group.querySelector('.wm-acc-group-toggle');
        var list  = group.querySelector('.wm-acc-group-folders');
        if (!btn || !list) return;

        var lsKey = accId ? ('wm_unified_acc_open_' + userIdTag + accId) : '';
        var legacyKey = accId ? ('wm_unified_acc_open_' + accId) : '';
        var stored = lsKey ? readStoredFlag(lsKey, legacyKey) : null;
        var shouldOpen = stored === '1' || (stored === null && group.dataset.hasActive === '1');
        if (shouldOpen) {
            list.style.display = 'block';
            btn.classList.add('expanded');
            btn.setAttribute('aria-expanded', 'true');
        } else {
            list.style.display = 'none';
            btn.classList.remove('expanded');
            btn.setAttribute('aria-expanded', 'false');
        }

        if (btn.dataset.wmBound === '1') return;
        btn.dataset.wmBound = '1';
        btn.addEventListener('click', function() {
            var isOpen = list.style.display !== 'none';
            var nextOpen = !isOpen;
            list.style.display = nextOpen ? 'block' : 'none';
            btn.classList.toggle('expanded', nextOpen);
            btn.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
            if (lsKey) {
                writeStoredFlag(lsKey, nextOpen);
            }
        });
    }

    function populateAccountGroups(accounts) {
        if (!accList || !Array.isArray(accounts)) return;
        var params = new URLSearchParams(window.location.search);
        var curAction = params.get('action') || 'inbox';
        var curFolder = params.get('folder') || 'INBOX';
        var curAccParam = parseInt(params.get('account_id') || '0', 10);

        accList.innerHTML = '';
        accounts.forEach(function(accGroup, idx) {
            var gAccId = parseInt(accGroup.account_id || 0, 10);
            var group = document.createElement('div');
            group.className = 'wm-acc-group';
            group.dataset.accountId = String(gAccId);
            var isActiveAcc = (curAction === 'inbox' || curAction === 'view' || curAction === 'search') && curAccParam > 0 && curAccParam === gAccId;
            group.dataset.hasActive = isActiveAcc ? '1' : '0';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wm-acc-group-toggle';
            btn.setAttribute('aria-expanded', 'false');
            btn.title = accGroup.email || '';

            var dot = document.createElement('span');
            dot.className = 'wm-acc-dot';
            dot.style.background = accDots[idx % accDots.length];
            btn.appendChild(dot);

            var lbl = document.createElement('span');
            lbl.className = 'wm-acc-label';
            lbl.textContent = accGroup.label || accGroup.email || 'Account';
            btn.appendChild(lbl);

            var unreadTotal = parseInt(accGroup.unread_total || 0, 10);
            if (accGroup.error) {
                var warn = document.createElement('span');
                warn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--wm-danger)" stroke-width="2.5" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
                if (warn.firstChild) {
                    warn.firstChild.setAttribute('title', String(accGroup.error));
                    btn.appendChild(warn.firstChild);
                }
            } else if (unreadTotal > 0) {
                var badge = document.createElement('span');
                badge.className = 'badge';
                badge.textContent = String(unreadTotal);
                btn.appendChild(badge);
            }

            var chevWrap = document.createElement('span');
            chevWrap.innerHTML = '<svg class="wm-acc-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';
            if (chevWrap.firstChild) btn.appendChild(chevWrap.firstChild);

            group.appendChild(btn);

            var foldersDiv = document.createElement('div');
            foldersDiv.className = 'wm-acc-group-folders';
            foldersDiv.style.display = 'none';

            var gFolders = Array.isArray(accGroup.folders) ? accGroup.folders : [];
            if (accGroup.error && !gFolders.length) {
                var errDiv = document.createElement('div');
                errDiv.className = 'wm-acc-group-error';
                errDiv.textContent = String(accGroup.error);
                foldersDiv.appendChild(errDiv);
            } else {
                gFolders.forEach(function(f) {
                    var fName = String(f.name || 'INBOX');
                    var a = document.createElement('a');
                    a.href = '?action=inbox&account_id=' + encodeURIComponent(String(gAccId)) + '&folder=' + encodeURIComponent(fName);
                    a.target = '_self';
                    a.dataset.accountId = String(gAccId);
                    a.dataset.folder = fName;
                    if (isActiveAcc && curFolder === fName) {
                        a.className = 'active';
                    }
                    a.style.display = 'flex';
                    a.style.alignItems = 'center';
                    a.style.gap = '.6rem';

                    var iconWrap = document.createElement('span');
                    iconWrap.innerHTML = getFolderSvgHtml(fName);
                    if (iconWrap.firstChild) a.appendChild(iconWrap.firstChild);

                    var nameSpan = document.createElement('span');
                    nameSpan.style.overflow = 'hidden';
                    nameSpan.style.textOverflow = 'ellipsis';
                    nameSpan.style.whiteSpace = 'nowrap';
                    nameSpan.style.flex = '1';
                    nameSpan.textContent = String(f.display || fName);
                    a.appendChild(nameSpan);

                    var fUnread = parseInt(f.unread || 0, 10);
                    if (fUnread > 0) {
                        var fBadge = document.createElement('span');
                        fBadge.className = 'badge';
                        fBadge.textContent = String(fUnread);
                        a.appendChild(fBadge);
                    }
                    foldersDiv.appendChild(a);
                });
            }

            group.appendChild(foldersDiv);
            accList.appendChild(group);
            bindAccountGroup(group);
        });
    }

    function persistUnifiedSidebarState(open) {
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        return fetch('?action=unified_sidebar_state', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ open: !!open })
        }).then(function(r) { return r.json(); }).catch(function() { return null; });
    }

    function applyUnifiedOpenUi(nextOpen, shouldPersist) {
        if (!toggle || !sub) return;
        sub.style.display = nextOpen ? 'block' : 'none';
        toggle.classList.toggle('expanded', nextOpen);
        toggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');

        if (accSection) {
            var hasGroups = accList && accList.querySelectorAll('.wm-acc-group').length > 0;
            accSection.style.display = (nextOpen && hasGroups) ? 'block' : 'none';
        }

        if (!shouldPersist) return Promise.resolve(null);

        return persistUnifiedSidebarState(nextOpen).then(function(res) {
            if (nextOpen && res && res.ok) {
                if (Array.isArray(res.unifiedFolders) && res.unifiedFolders.length > 0) {
                    populateUnifiedSubfolders(res.unifiedFolders);
                }
                if (accSection && accList) {
                    if (accList.querySelectorAll('.wm-acc-group').length === 0 && Array.isArray(res.unifiedAccountFolders) && res.unifiedAccountFolders.length > 0) {
                        populateAccountGroups(res.unifiedAccountFolders);
                    }
                    if (accList.querySelectorAll('.wm-acc-group').length > 0) {
                        accSection.style.display = 'block';
                    }
                }
            }
            if (typeof window.wmRestoreSidebarScroll === 'function') {
                window.wmRestoreSidebarScroll();
            }
            return res;
        });
    }

    if (toggle && sub) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var nextOpen = sub.style.display === 'none';
            writeStoredFlag(UNIFIED_OPEN_KEY, nextOpen);
            applyUnifiedOpenUi(nextOpen, true);
        });

        // Restore persisted open/closed state on browser reopen while logged in
        var storedUnified = readStoredFlag(UNIFIED_OPEN_KEY, null);
        var inUnifiedMode = document.querySelector('.wm-unified-row.active') !== null;
        if (inUnifiedMode) {
            writeStoredFlag(UNIFIED_OPEN_KEY, true);
        } else if (storedUnified === '1') {
            var needsServerHydrate = sub.style.display === 'none' || !accList || accList.querySelectorAll('.wm-acc-group').length === 0;
            applyUnifiedOpenUi(true, needsServerHydrate);
        } else if (storedUnified === '0' && sub.style.display !== 'none') {
            applyUnifiedOpenUi(false, true);
        }
    }

    document.querySelectorAll('.wm-acc-group').forEach(bindAccountGroup);
    window.wmBindAccountGroup = bindAccountGroup;
})();

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

    function bindFolderMenuButtons() {
        document.querySelectorAll('.wm-sidebar-folder-row').forEach(function(row) {
            var btn = row.querySelector('.wm-folder-menu-btn');
            if (!btn || row.dataset.wmHoverBound === '1') return;
            row.dataset.wmHoverBound = '1';
            row.addEventListener('mouseenter', function() { btn.style.opacity = '1'; });
            row.addEventListener('mouseleave', function() { btn.style.opacity = '0'; });
        });

        document.querySelectorAll('.wm-folder-menu-btn').forEach(function(btn) {
            if (btn.dataset.wmMenuBound === '1') return;
            btn.dataset.wmMenuBound = '1';
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                activeFolder = btn.dataset.folder;
                var rect = btn.getBoundingClientRect();
                ctxMenu.style.top  = rect.bottom + 'px';
                ctxMenu.style.left = rect.left + 'px';
                ctxMenu.style.display = 'block';
            });
        });
    }
    bindFolderMenuButtons();
    window.wmBindFolderMenus = bindFolderMenuButtons;

    // Close context menu on outside click
    document.addEventListener('click', function() {
        if (ctxMenu) ctxMenu.style.display = 'none';
    });
    if (ctxMenu) {
        ctxMenu.addEventListener('click', function(e) { e.stopPropagation(); });
    }

    // Security suggestions popover
    var suggestionsBtn = document.getElementById('security-suggestions-btn');
    var suggestionsPopover = document.getElementById('security-suggestions-popover');
    if (suggestionsBtn && suggestionsPopover) {
        suggestionsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            suggestionsPopover.style.display = suggestionsPopover.style.display === 'none' ? 'block' : 'none';
        });
        document.addEventListener('click', function() {
            suggestionsPopover.style.display = 'none';
        });
        suggestionsPopover.addEventListener('click', function(e) {
            e.stopPropagation();
        });
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

    // Periodic version check
    (function() {
        function checkUpdates() {
            fetch('?action=check_updates', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        var isAhead = data.is_ahead;
                        // Hide main mismatch tag in sidebar
                        var mismatchTag = document.getElementById('version-mismatch-tag');
                        if (mismatchTag) {
                            mismatchTag.style.display = isAhead ? 'inline-flex' : 'none';
                        }
                        // Hide badges in settings page if we are there
                        document.querySelectorAll('.wm-version-mismatch-badge').forEach(function(el) {
                            el.style.display = isAhead ? '' : 'none';
                        });
                    }
                })
                .catch(function() {});
        }
        // Check more frequently as requested: every 2 minutes
        setInterval(checkUpdates, 120000);
        // Also run once on load
        setTimeout(checkUpdates, 5000);
    })();
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
            <div style="display:flex;gap:.25rem">
                <button type="button" class="btn-ghost btn-icon minimize-modal" title="Minimize" style="font-size:1.2rem;line-height:1">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <button type="button" class="btn-ghost btn-icon close-modal" style="font-size:1.5rem;line-height:1;padding:0 .5rem">&times;</button>
            </div>
        </div>
        <div class="wm-modal-body">
            <div style="display:flex;gap:.5rem;margin-bottom:1.5rem">
                <input type="text" id="contact-search" class="form-control" placeholder="Search contacts..." style="flex:1">
                <button type="button" id="add-contact-toggle" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New
                </button>
            </div>

            <!-- Add/Edit Contact Form (Hidden by default) -->
            <form id="add-contact-form" style="display:none;background:var(--wm-surface-2);padding:1rem;border-radius:8px;margin-bottom:1.5rem;border:1px solid var(--wm-border)">
                <input type="hidden" name="id" id="contact-id">
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
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
                    <div>
                        <label style="font-size:.75rem">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div>
                        <label style="font-size:.75rem">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                </div>
                <div style="margin-bottom:.75rem">
                    <label style="font-size:.75rem">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem">
                    <button type="button" id="cancel-add-contact" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="save-contact-btn">Save Contact</button>
                </div>
            </form>

            <!-- View Contact Detail Panel (Hidden by default) -->
            <div id="contact-view-panel" style="display:none;background:var(--wm-surface-2);padding:1rem;border-radius:8px;margin-bottom:1rem;border:1px solid var(--wm-border)"></div>

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

/* Floating Widget */
#contacts-widget {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 56px;
    height: 56px;
    background: var(--wm-primary);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    cursor: pointer;
    z-index: 2100;
    transition: transform .2s, background .2s;
}
#contacts-widget:hover {
    transform: scale(1.05);
    background: var(--wm-primary-hover);
}
#contacts-widget svg { width: 24px; height: 24px; }
.wm-modal.minimized {
    display: none !important;
}
.contact-item { display: flex; align-items: center; gap: .75rem; padding: .75rem; border-bottom: 1px solid var(--wm-border); border-radius: 8px; transition: background .15s; }
.contact-item:hover { background: var(--wm-surface-2); }
.contact-item:last-child { border-bottom: none; }
.contact-info { flex: 1; min-width: 0; }
.contact-name { font-weight: 600; font-size: .9rem; display: block; }
.contact-email { font-size: .8rem; color: var(--wm-text-muted); display: block; }
</style>

<!-- Floating Contacts Widget -->
<div id="contacts-widget" style="display:none" title="Restore Address Book">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
    </svg>
</div>

<script nonce="<?= $cspNonce ?>">
(function() {
    var contactsBtn = document.getElementById('contacts-btn');
    var modal = document.getElementById('contacts-modal');
    var closeBtn = modal.querySelector('.close-modal');
    var minimizeBtn = modal.querySelector('.minimize-modal');
    var widget = document.getElementById('contacts-widget');
    var listContainer = document.getElementById('contacts-list-container');
    var addToggle = document.getElementById('add-contact-toggle');
    var addForm = document.getElementById('add-contact-form');
    var cancelAdd = document.getElementById('cancel-add-contact');
    var viewPanel = document.getElementById('contact-view-panel');
    var searchInput = document.getElementById('contact-search');
    var allContacts = [];
    var currentTargetInput = null;

    function closeContactView() {
        if (viewPanel) {
            viewPanel.style.display = 'none';
            viewPanel.innerHTML = '';
        }
        listContainer.style.display = '';
    }

    function openContactView(contact) {
        if (!viewPanel || !contact) return;
        addForm.style.display = 'none';
        listContainer.style.display = 'none';
        var initial = escapeHtml((contact.name || contact.email || '?').charAt(0).toUpperCase());
        var hasNotes = !!(contact.notes && contact.notes.trim() !== '');
        viewPanel.innerHTML = `
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:1px solid var(--wm-border)">
                <div style="display:flex;align-items:center;gap:.75rem;min-width:0">
                    <div class="wm-account-avatar" style="width:42px;height:42px;font-size:1rem;flex-shrink:0">${initial}</div>
                    <div style="min-width:0">
                        <div style="font-weight:700;font-size:1rem;word-break:break-word">${escapeHtml(contact.name)}</div>
                        <div style="font-size:.84rem;color:var(--wm-text-muted);word-break:break-all">${escapeHtml(contact.email)}</div>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm close-contact-view" title="Back to contacts">← Back</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:.55rem;margin-bottom:1rem;font-size:.86rem">
                ${contact.phone ? `<div><strong style="color:var(--wm-text-muted);font-size:.76rem;text-transform:uppercase;display:block">Phone</strong><span>📞 ${escapeHtml(contact.phone)}</span></div>` : ''}
                ${contact.address ? `<div><strong style="color:var(--wm-text-muted);font-size:.76rem;text-transform:uppercase;display:block">Address</strong><span>📍 ${escapeHtml(contact.address)}</span></div>` : ''}
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.25rem">
                        <strong style="color:var(--wm-text-muted);font-size:.76rem;text-transform:uppercase">Notes</strong>
                        ${hasNotes ? `<button type="button" class="btn btn-ghost btn-xs copy-contact-notes">Copy notes</button>` : ''}
                    </div>
                    <div style="background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:6px;padding:.65rem .75rem;white-space:pre-wrap;word-break:break-word;max-height:220px;overflow-y:auto;line-height:1.45;${hasNotes ? '' : 'color:var(--wm-text-muted);font-style:italic'}">${hasNotes ? escapeHtml(contact.notes) : 'No notes saved for this contact.'}</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:.5rem;flex-wrap:wrap">
                ${currentTargetInput
                    ? `<button type="button" class="btn btn-primary btn-sm use-contact" data-email="${escapeHtml(contact.email)}">Use Email</button>`
                    : `<a href="?action=compose&to=${encodeURIComponent(contact.email)}" class="btn btn-outline btn-sm" target="_self">Compose</a>`
                }
                <button type="button" class="btn btn-outline btn-sm edit-from-view" data-id="${contact.id}">Edit</button>
            </div>
        `;
        viewPanel.style.display = 'block';

        var backBtn = viewPanel.querySelector('.close-contact-view');
        if (backBtn) backBtn.addEventListener('click', closeContactView);

        var copyBtn = viewPanel.querySelector('.copy-contact-notes');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(contact.notes || '').then(function() {
                        copyBtn.textContent = 'Copied!';
                        setTimeout(function() { copyBtn.textContent = 'Copy notes'; }, 1500);
                    });
                }
            });
        }

        var editFromView = viewPanel.querySelector('.edit-from-view');
        if (editFromView) {
            editFromView.addEventListener('click', function() {
                closeContactView();
                openContactEdit(contact);
            });
        }

        var useFromView = viewPanel.querySelector('.use-contact');
        if (useFromView && currentTargetInput) {
            useFromView.addEventListener('click', function() {
                var input = document.getElementById(currentTargetInput);
                if (input) {
                    var val = input.value.trim();
                    input.value = val ? (val + ', ' + contact.email) : contact.email;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    hideModal();
                }
            });
        }
    }

    function openContactEdit(contact) {
        if (!contact) return;
        closeContactView();
        document.getElementById('contact-id').value = contact.id;
        addForm.querySelector('input[name="name"]').value = contact.name;
        addForm.querySelector('input[name="email"]').value = contact.email;
        addForm.querySelector('input[name="phone"]').value = contact.phone || '';
        addForm.querySelector('input[name="address"]').value = contact.address || '';
        addForm.querySelector('textarea[name="notes"]').value = contact.notes || '';
        document.getElementById('save-contact-btn').textContent = 'Update Contact';
        addForm.style.display = 'block';
        addForm.querySelector('input[name="name"]').focus();
    }

    function loadContacts() {
        closeContactView();
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
            var notesPreview = c.notes ? c.notes.replace(/\s+/g, ' ').trim() : '';
            div.innerHTML = `
                <div class="wm-account-avatar" style="width:36px;height:36px;font-size:.9rem">
                    ${escapeHtml((c.name || c.email).charAt(0).toUpperCase())}
                </div>
                <div class="contact-info">
                    <span class="contact-name">${escapeHtml(c.name)}</span>
                    <span class="contact-email">${escapeHtml(c.email)}</span>
                    ${c.phone ? `<span style="font-size:.75rem;display:block;color:var(--wm-text-muted)">📞 ${escapeHtml(c.phone)}</span>` : ''}
                    ${c.address ? `<span style="font-size:.75rem;display:block;color:var(--wm-text-muted)">📍 ${escapeHtml(c.address)}</span>` : ''}
                    ${notesPreview ? `<span style="font-size:.75rem;display:block;color:var(--wm-text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">📝 ${escapeHtml(notesPreview)}</span>` : ''}
                </div>
                <div style="display:flex;gap:.25rem;flex-shrink:0">
                    ${currentTargetInput ? `<button type="button" class="btn btn-primary btn-sm use-contact" data-email="${escapeHtml(c.email)}">Use</button>` : ''}
                    <button type="button" class="btn btn-ghost btn-sm view-contact" data-id="${c.id}" title="View contact & notes">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm edit-contact" data-id="${c.id}" title="Edit">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm delete-contact" data-id="${c.id}" title="Delete">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    </button>
                </div>
            `;
            listContainer.appendChild(div);
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showModal() {
        modal.style.display = 'flex';
        modal.classList.remove('minimized');
        widget.style.display = 'none';
        sessionStorage.setItem('wm_contacts_minimized', '0');
        sessionStorage.setItem('wm_contacts_open', '1');
    }

    function hideModal() {
        modal.style.display = 'none';
        modal.classList.remove('minimized');
        widget.style.display = 'none';
        addForm.style.display = 'none';
        closeContactView();
        sessionStorage.setItem('wm_contacts_open', '0');
        sessionStorage.setItem('wm_contacts_minimized', '0');
    }

    function minimizeModal() {
        modal.classList.add('minimized');
        widget.style.display = 'flex';
        sessionStorage.setItem('wm_contacts_minimized', '1');
    }

    contactsBtn.addEventListener('click', function() {
        currentTargetInput = null;
        showModal();
        loadContacts();
    });

    // Listen for custom event from compose page
    window.addEventListener('open-contacts-picker', function(e) {
        currentTargetInput = e.detail.target;
        showModal();
        loadContacts();
    });

    closeBtn.addEventListener('click', hideModal);

    minimizeBtn.addEventListener('click', minimizeModal);

    widget.addEventListener('click', showModal);

    // Restore state on page load
    if (sessionStorage.getItem('wm_contacts_open') === '1') {
        if (sessionStorage.getItem('wm_contacts_minimized') === '1') {
            minimizeModal();
            loadContacts();
        } else {
            showModal();
            loadContacts();
        }
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeBtn.click();
    });

    addToggle.addEventListener('click', function() {
        closeContactView();
        addForm.reset();
        document.getElementById('contact-id').value = '';
        document.getElementById('save-contact-btn').textContent = 'Save Contact';
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
        var id = document.getElementById('contact-id').value;
        var action = id ? 'contacts_edit' : 'contacts_add';
        
        fetch('?action=' + action, {
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
                alert(data.error || 'Failed to save contact');
            }
        });
    });

    listContainer.addEventListener('click', function(e) {
        var viewBtn = e.target.closest('.view-contact');
        if (viewBtn) {
            var id = parseInt(viewBtn.dataset.id, 10);
            var contact = allContacts.find(c => parseInt(c.id, 10) === id);
            if (contact) openContactView(contact);
            return;
        }

        var editBtn = e.target.closest('.edit-contact');
        if (editBtn) {
            var id = parseInt(editBtn.dataset.id, 10);
            var contact = allContacts.find(c => parseInt(c.id, 10) === id);
            if (contact) openContactEdit(contact);
            return;
        }

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
                input.dispatchEvent(new Event('input', { bubbles: true }));
                hideModal();
            }
        }
    });

    searchInput.addEventListener('input', function() {
        closeContactView();
        var q = searchInput.value.toLowerCase();
        var filtered = allContacts.filter(c => 
            c.name.toLowerCase().includes(q)
            || c.email.toLowerCase().includes(q)
            || (c.notes || '').toLowerCase().includes(q)
        );
        renderContacts(filtered);
    });
})();
</script>

<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
