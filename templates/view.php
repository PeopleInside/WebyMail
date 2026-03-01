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
$isInbox   = strtoupper($folder) === 'INBOX';
?>

<!-- Toolbar -->
<div class="wm-toolbar">
    <div class="wm-toolbar-content">
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

    <a href="?action=export_eml&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>" class="btn btn-outline btn-sm" title="Download as .eml">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
    </a>

    <div style="margin-left:auto;display:flex;gap:.25rem">
        <button class="btn btn-ghost btn-sm" id="show-headers-btn" title="View original headers">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Headers
        </button>

        <?php if ($isInbox): ?>
    <form method="post" action="?action=spam&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>" style="display:inline">
        <?= csrfInput() ?>
        <button class="btn btn-ghost btn-sm text-danger" title="Mark as spam">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            Spam
        </button>
    </form>
    <?php endif; ?>

    <form method="post" action="?action=delete&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>" style="display:inline"
          onsubmit="return confirm('<?= ($isTrash ?? false) ? 'Permanently delete this message? This cannot be undone.' : 'Delete this message?' ?>')">
        <?= csrfInput() ?>
        <button class="btn btn-ghost btn-sm text-danger" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            Delete
        </button>
    </form>
</div>
</div>
</div>

<!-- External image protection banner -->
<?php if ($hasExternal ?? false): ?>
<div class="wm-img-banner" id="img-protection-banner">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    <span>External images have been blocked to protect your privacy.</span>
    <button class="btn btn-outline btn-sm" id="show-images-btn" style="margin-left:auto">Show images</button>
</div>
<?php endif; ?>

<!-- Read receipt request alert -->
<?php if (!empty($message['read_receipt_to'])): ?>
<div class="alert alert-info" style="margin:1rem;border-radius:8px;display:flex;align-items:center;gap:.75rem">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <div style="flex:1">
        The sender has requested a read receipt.
    </div>
    <a href="?action=compose&reply=<?= $msgNo ?>&folder=<?= $folderEnc ?>" class="btn btn-primary btn-sm">Send a reply</a>
</div>
<?php endif; ?>

<!-- Message header -->
<div class="wm-view-header">
    <div class="wm-view-subject">
        <?php if (($message['priority'] ?? 'normal') === 'high'): ?>
        <span title="High Priority" style="color:var(--wm-danger);margin-right:8px;font-weight:bold;font-size:1.4rem;line-height:1">!</span>
        <?php elseif (($message['priority'] ?? 'normal') === 'low'): ?>
        <span title="Low Priority" style="color:var(--wm-primary);margin-right:8px;font-weight:bold;font-size:1.4rem;line-height:1">↓</span>
        <?php endif; ?>
        <?= htmlspecialchars($message['subject'] ?? '(no subject)') ?>
    </div>
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
<div class="wm-view-content-layout">
    <div class="wm-view-main">
        <?php if ($hasHtml): ?>
        <div class="wm-email-container">
            <div id="email-body-shadow" class="wm-email-body-shadow">
                <div class="wm-email-loading">
                    <div class="spinner"></div>
                    <span>Loading message content...</span>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="wm-email-container plain-text">
            <pre><?= htmlspecialchars($message['body_text'] ?? '') ?></pre>
        </div>
        <?php endif; ?>
    </div>

    <!-- Attachments Sidebar/Section -->
    <?php if (!empty($message['attachments'])): ?>
    <div class="wm-view-attachments">
        <div class="wm-attachments-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
            <span>Attachments (<?= count($message['attachments']) ?>)</span>
            <a href="?action=download_all&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>" class="btn btn-ghost btn-xs" title="Download all as ZIP">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
        </div>
        <div class="wm-attachments-list">
            <?php foreach ($message['attachments'] as $att): ?>
            <div class="wm-attachment-item">
                <div class="wm-attachment-icon">
                    <?php 
                    $ext = strtolower(pathinfo($att['filename'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <?php elseif ($ext === 'pdf'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <?php elseif ($ext === 'eml'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                    <?php endif; ?>
                </div>
                <div class="wm-attachment-info">
                    <div class="wm-attachment-name" title="<?= htmlspecialchars($att['filename']) ?>"><?= htmlspecialchars($att['filename']) ?></div>
                    <div class="wm-attachment-size"><?= number_format($att['size'] / 1024, 1) ?> KB</div>
                </div>
                <a href="?action=attachment&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>&section=<?= urlencode($att['section']) ?>&name=<?= urlencode($att['filename']) ?>" 
                   class="wm-attachment-download" download title="Download">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Headers modal -->
<div id="headers-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:var(--wm-surface);border:1px solid var(--wm-border);border-radius:10px;box-shadow:var(--wm-shadow);width:min(720px,96vw);max-height:80vh;display:flex;flex-direction:column">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--wm-border)">
            <span style="font-weight:600;font-size:.95rem">Original Email Headers</span>
            <button id="headers-modal-close" class="btn btn-ghost btn-icon" title="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="overflow:auto;padding:1rem">
            <pre id="headers-content" aria-live="polite" aria-busy="true" style="font-family:monospace;font-size:.8rem;line-height:1.6;white-space:pre-wrap;word-break:break-all;margin:0;color:var(--wm-text)">Loading…</pre>
        </div>
    </div>
</div>

<script>
(function() {
    // Headers modal logic
    var btn   = document.getElementById('show-headers-btn');
    var modal = document.getElementById('headers-modal');
    var close = document.getElementById('headers-modal-close');
    var pre   = document.getElementById('headers-content');
    var loaded = false;

    if (btn && modal) {
        btn.addEventListener('click', function() {
            modal.style.display = 'flex';
            if (loaded) return;
            fetch('?action=email_headers&folder=' + <?= json_encode($folderEnc) ?> + '&msg=<?= $msgNo ?>', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loaded = true;
                pre.setAttribute('aria-busy', 'false');
                pre.textContent = data.ok ? data.headers : ('Error: ' + (data.error || 'Unknown error'));
            })
            .catch(function(err) { pre.setAttribute('aria-busy', 'false'); pre.textContent = 'Failed to load headers.'; });
        });

        close.addEventListener('click', function() { modal.style.display = 'none'; });
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
    }

    // Shadow DOM Email Body logic
    var shadowHost = document.getElementById('email-body-shadow');
    if (shadowHost && <?php echo $hasHtml ? 'true' : 'false'; ?>) {
        var shadowRoot = shadowHost.attachShadow({mode: 'open'});
        
        function loadEmailBody(showImages) {
            var url = '?action=email_body&folder=<?= $folderEnc ?>&msg=<?= $msgNo ?>&images=' + (showImages ? '1' : '0');
            
            fetch(url)
            .then(function(r) { return r.text(); })
            .then(function(html) {
                // Create a temporary document to parse the HTML
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                
                // Clear shadow root
                shadowRoot.innerHTML = '';
                
                // Inject styles for shadow root
                var style = document.createElement('style');
                style.textContent = `
                    :host { display: block; background: #fff; color: #000; min-height: 200px; overflow-x: auto; }
                    .email-content-wrapper { padding: 20px; word-break: break-word; }
                    img { max-width: 100%; height: auto; }
                    * { max-width: 100%; box-sizing: border-box; }
                    
                    /* Dark mode support for shadow DOM */
                    :host-context([data-theme="dark"]) {
                        background: #161b22;
                        color: #e6edf3;
                    }
                    :host-context([data-theme="dark"]) a { color: #58a6ff; }
                    
                    /* System dark mode support */
                    @media (prefers-color-scheme: dark) {
                        :host-context(html:not([data-theme="light"])) {
                            background: #161b22;
                            color: #e6edf3;
                        }
                        :host-context(html:not([data-theme="light"])) a { color: #58a6ff; }
                    }
                    
                    /* Reset some common email styles that might break layout */
                    body { margin: 0; padding: 0; }
                `;
                shadowRoot.appendChild(style);
                
                // Create content wrapper
                var wrapper = document.createElement('div');
                wrapper.className = 'email-content-wrapper';
                
                // If it's a full document, we take the body content
                if (doc.body) {
                    // Copy body attributes (like background color) if they exist
                    if (doc.body.getAttribute('bgcolor')) wrapper.style.backgroundColor = doc.body.getAttribute('bgcolor');
                    if (doc.body.getAttribute('text')) wrapper.style.color = doc.body.getAttribute('text');
                    
                    // Move all children from doc.body to our wrapper
                    while (doc.body.firstChild) {
                        wrapper.appendChild(doc.body.firstChild);
                    }
                } else {
                    wrapper.innerHTML = html;
                }
                
                shadowRoot.appendChild(wrapper);
                
                // Handle theme changes
                function syncTheme() {
                    var theme = document.documentElement.getAttribute('data-theme') || 'light';
                    if (theme === 'system') {
                        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    }
                    // We use :host-context in CSS, but we can also manually adjust if needed
                }
                
                // Initial sync
                syncTheme();
                
                // Listen for theme changes from parent
                window.addEventListener('storage', function(e) {
                    if (e.key === 'wm_theme') syncTheme();
                });
                
                // Also listen for the custom event if your ThemeManager uses one
                // (ThemeManager in app.js doesn't dispatch an event, but we can observe the html attribute)
                var observer = new MutationObserver(syncTheme);
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
            })
            .catch(function(err) {
                shadowRoot.innerHTML = '<div style="padding:20px;color:red">Failed to load email content.</div>';
            });
        }

        // Initial load
        loadEmailBody(false);

        // Image protection button
        var showImagesBtn = document.getElementById('show-images-btn');
        var banner = document.getElementById('img-protection-banner');
        if (showImagesBtn) {
            showImagesBtn.addEventListener('click', function() {
                loadEmailBody(true);
                if (banner) banner.style.display = 'none';
            });
        }
    }
})();
</script>
