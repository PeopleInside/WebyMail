<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'WebyMail') ?></title>
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
                    <?= strtoupper(substr($session['display_name'] ?? $session['email'] ?? 'U', 0, 1)) ?>
                </div>
                <span style="font-size:.82rem;max-width:120px;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($session['display_name'] ?? $session['email'] ?? '') ?>
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

        <div class="wm-sidebar-section">Folders</div>
        <?php foreach ($folders ?? [] as $folder): ?>
        <a href="?action=inbox&folder=<?= urlencode($folder['name']) ?>"
           class="<?= ($currentFolder ?? 'INBOX') === $folder['name'] ? 'active' : '' ?>">
            <?= htmlspecialchars($folder['display']) ?>
            <?php if (!empty($folder['unread'])): ?>
            <span class="badge"><?= (int)$folder['unread'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

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
</script>

<?php else: ?>
<!-- Auth page (no shell) – just render content -->
<?= $content ?? '' ?>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
