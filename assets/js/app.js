/**
 * WebyMail – client-side JS
 * Handles: theme toggle (dark/light/system), sidebar toggle,
 *          mail list actions, and misc UI helpers.
 */

/* =============================================================
   Theme management
   dark / light / system  –  persisted in localStorage
   ============================================================= */
const SMTP_SSL_PORT = '465';
const SMTP_STARTTLS_PORT = '587';

const ThemeManager = (() => {
    const KEY   = 'wm_theme';
    const MODES = ['system', 'light', 'dark'];
    const ICONS = {
        system: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`,
        light:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`,
        dark:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>`,
    };
    const LABELS = { system: 'System', light: 'Light', dark: 'Dark' };

    function current() {
        return localStorage.getItem(KEY) || 'system';
    }

    function apply(mode) {
        const html = document.documentElement;
        let themeToApply = mode;
        if (mode === 'system') {
            html.removeAttribute('data-theme');
            themeToApply = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } else {
            html.setAttribute('data-theme', mode);
        }
        localStorage.setItem(KEY, mode);
        // Set cookie for server-side components (expires in 1 year)
        document.cookie = `wm_theme=${mode}; path=/; max-age=31536000; SameSite=Strict`;
        updateButtons(mode);

        // Broadcast to email iframe if present
        const frame = document.getElementById('email-frame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({ type: 'wm-theme-change', theme: themeToApply }, '*');
        }
    }

    function cycle() {
        const idx  = MODES.indexOf(current());
        const next = MODES[(idx + 1) % MODES.length];
        apply(next);
    }

    function updateButtons(mode) {
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            btn.innerHTML = ICONS[mode] + '<span>' + LABELS[mode] + '</span>';
            btn.title = 'Theme: ' + LABELS[mode] + ' (click to cycle)';
        });
    }

    function init() {
        apply(current());
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            btn.addEventListener('click', cycle);
        });

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (current() === 'system') {
                apply('system');
            }
        });
    }

    return { init, apply, current, cycle };
})();

/* =============================================================
   Sidebar toggle (mobile & desktop)
   ============================================================= */
function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const shell = document.querySelector('.wm-shell');
    const sidebar = document.querySelector('.wm-sidebar');
    
    if (!toggle || !sidebar) return;

    // Create backdrop for mobile if it doesn't exist
    let backdrop = document.querySelector('.wm-sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'wm-sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('open');
            backdrop.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        } else {
            shell?.classList.toggle('sidebar-collapsed');
        }
    });

    // Close on outside click (mobile)
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
                backdrop.style.display = 'none';
            }
        }
    });

    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('open');
        backdrop.style.display = 'none';
    });
}

/* =============================================================
   Mobile Search Toggle
   ============================================================= */
function initMobileSearch() {
    const searchBtn = document.getElementById('mobile-search-toggle');
    const searchGroup = document.querySelector('.wm-topbar .wm-topbar-search');
    if (!searchBtn || !searchGroup) return;

    searchBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        searchGroup.classList.toggle('show-mobile');
        if (searchGroup.classList.contains('show-mobile')) {
            searchGroup.querySelector('input')?.focus();
        }
    });

    document.addEventListener('click', (e) => {
        if (!searchGroup.contains(e.target) && !searchBtn.contains(e.target)) {
            searchGroup.classList.remove('show-mobile');
        }
    });
}

/* =============================================================
   Mail list: checkbox select + bulk actions
   ============================================================= */
function initMailList() {
    const selectAll = document.getElementById('select-all');
    if (!selectAll) return;

    selectAll.addEventListener('change', () => {
        document.querySelectorAll('.mail-checkbox').forEach(cb => {
            cb.checked = selectAll.checked;
            cb.closest('.wm-mail-row')?.classList.toggle('selected', selectAll.checked);
        });
        updateBulkActions();
    });

    document.querySelectorAll('.mail-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.wm-mail-row')?.classList.toggle('selected', cb.checked);
            updateBulkActions();
        });
    });
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.mail-checkbox:checked').length;
    const bar = document.getElementById('bulk-bar');
    if (bar) bar.style.display = checked > 0 ? 'flex' : 'none';
}

function getSelectedUids() {
    return Array.from(document.querySelectorAll('.mail-checkbox:checked'))
        .map(cb => cb.dataset.uid)
        .filter(Boolean);
}

/* =============================================================
   Mail row click navigation
   ============================================================= */
function initMailRows() {
    document.querySelectorAll('.wm-mail-row[data-href]').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.type === 'checkbox') return;
            window.location.href = row.dataset.href;
        });
    });
}

/* =============================================================
   External image protection – show/hide images in email view
   ============================================================= */
function initImageProtection() {
    const banner  = document.getElementById('img-protection-banner');
    const showBtn = document.getElementById('show-images-btn');
    const frame   = document.getElementById('email-frame');

    if (!banner || !showBtn || !frame) return;

    showBtn.addEventListener('click', () => {
        const src = frame.dataset.src;
        if (src) {
            frame.src = src;
            banner.style.display = 'none';
        }
    });
}

/* =============================================================
   Compose: CC / BCC toggle
   ============================================================= */
function initCompose() {
    ['cc', 'bcc'].forEach(field => {
        const btn = document.getElementById(`show-${field}`);
        const row = document.getElementById(`${field}-row`);
        if (btn && row) {
            btn.addEventListener('click', () => {
                row.style.display = row.style.display === 'none' ? 'flex' : 'none';
                btn.style.display = 'none';
            });
        }
    });

    // Auto-expand reply quote
    const quoteToggle = document.getElementById('quote-toggle');
    const quoteBlock  = document.getElementById('quote-block');
    if (quoteToggle && quoteBlock) {
        quoteToggle.addEventListener('click', () => {
            quoteBlock.style.display = quoteBlock.style.display === 'none' ? 'block' : 'none';
        });
    }
}

/* =============================================================
   AJAX helpers
   ============================================================= */
async function apiPost(url, data) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res  = await fetch(url, {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body:    JSON.stringify(data),
    });
    return res.json();
}

/* =============================================================
   Account switcher
   ============================================================= */
function initAccountSwitcher() {
    document.querySelectorAll('[data-switch-account]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.switchAccount;
            await apiPost('?action=switch_account', { account_id: id });
            window.location.href = '?action=inbox';
        });
    });

    const select = document.querySelector('[data-account-select]');
    if (select) {
        select.addEventListener('change', async (e) => {
            const id = e.target.value;
            await apiPost('?action=switch_account', { account_id: id });
            window.location.href = '?action=inbox';
        });
    }
}

/* =============================================================
   Flash / notification auto-dismiss
   ============================================================= */
function initAlerts() {
    document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
        const ms = parseInt(el.dataset.dismiss, 10) || 4000;
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 400);
        }, ms);
    });
}

/* =============================================================
   Copy-to-clipboard helper (recovery codes)
   ============================================================= */
function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', () => {
            const text = document.querySelector(btn.dataset.copy)?.textContent;
            if (!text) return;
            navigator.clipboard.writeText(text.trim()).then(() => {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = orig, 2000);
            });
        });
    });
}

/* =============================================================
   SMTP port helpers – keep 465/587 in sync with SSL/STARTTLS
   ============================================================= */
function initSmtpPortSync() {
    document.querySelectorAll('fieldset').forEach(fieldset => {
        const port     = fieldset.querySelector('[data-smtp-port]');
        const ssl      = fieldset.querySelector('[data-smtp-ssl]');
        const starttls = fieldset.querySelector('[data-smtp-starttls]');
        if (!port || !ssl || !starttls) return;

        const sync = () => {
            if (ssl.checked) {
                starttls.checked = false;
                port.value = SMTP_SSL_PORT;
            } else if (starttls.checked) {
                ssl.checked = false;
                port.value = SMTP_STARTTLS_PORT;
            }
        };

        ssl.addEventListener('change', sync);
        starttls.addEventListener('change', sync);
        sync();
    });
}

/* =============================================================
   Auto-refresh / New mail check
   ============================================================= */
let inboxRefreshInFlight = false;

function getCurrentAction() {
    const params = new URLSearchParams(window.location.search);
    return params.get('action') || 'inbox';
}

function getCurrentFolder() {
    const params = new URLSearchParams(window.location.search);
    return (params.get('folder') || 'INBOX').toUpperCase();
}

function isInboxListView() {
    // Explicitly check for compose or view containers to avoid any false positives
    if (document.getElementById('compose-form') || document.getElementById('email-body-shadow') || document.getElementById('email-frame')) {
        return false;
    }

    const params = new URLSearchParams(window.location.search);
    const action = params.get('action');
    // We only consider it the mailbox listing view if action is missing (default) or explicitly 'inbox'
    return !action || action === 'inbox';
}

function isSafeToRefreshInboxView() {
    if (document.hidden) return false;

    // Don't refresh if a modal is open
    const hasModal = document.querySelector('.wm-modal[style*="display: flex"]') ||
        document.querySelector('.wm-modal[style*="display: block"]');
    if (hasModal) return false;

    // Don't refresh if the folder context menu is open
    const ctxMenu = document.getElementById('folder-ctx-menu');
    if (ctxMenu && ctxMenu.style.display === 'block') return false;

    // Don't refresh if the user is typing in any input, textarea or contenteditable element
    const activeEl = document.activeElement;
    const isInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.contentEditable === 'true');
    if (isInput) return false;

    // Don't refresh if the user has selected some text on the page
    const selection = window.getSelection();
    if (selection && selection.toString().length > 0) return false;

    return true;
}

async function refreshInboxViewPreservingState() {
    if (inboxRefreshInFlight || !isInboxListView()) return false;

    const currentContainer = document.getElementById('inbox-content');
    const currentMailList = document.getElementById('mail-list');
    if (!currentContainer || !currentMailList) return false;

    const previousScrollTop = currentMailList.scrollTop;
    const selectedUids = new Set(
        Array.from(document.querySelectorAll('.mail-checkbox:checked'))
            .map(cb => cb.dataset.uid)
            .filter(Boolean)
    );

    inboxRefreshInFlight = true;
    try {
        const refreshUrl = new URL(window.location.href);
        // Ensure we preserve currently viewed folder and page, but always force 'inbox' action
        // in case we were on the root URL without parameters.
        refreshUrl.searchParams.set('action', 'inbox');
        refreshUrl.hash = '';

        const response = await fetch(refreshUrl.pathname + refreshUrl.search, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) return false;

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextContainer = doc.getElementById('inbox-content');
        const nextTotal = doc.getElementById('inbox-total-count');
        
        // Safety check: if the fetched page doesn't have the mailbox content, 
        // it means we probably got redirected to Login or something else.
        // We MUST NOT replace the current list with nothing.
        if (!nextContainer) return false;

        const replacement = document.createDocumentFragment();
        Array.from(nextContainer.children).forEach(node => {
            replacement.appendChild(node);
        });
        currentContainer.replaceChildren(replacement);

        const currentTotal = document.getElementById('inbox-total-count');
        if (currentTotal && nextTotal) {
            currentTotal.textContent = nextTotal.textContent;
        }

        const newMailList = document.getElementById('mail-list');
        if (newMailList) {
            newMailList.scrollTop = previousScrollTop;
        }

        document.querySelectorAll('.mail-checkbox').forEach(cb => {
            if (!selectedUids.has(cb.dataset.uid)) return;
            cb.checked = true;
            cb.closest('.wm-mail-row')?.classList.add('selected');
        });

        initMailList();
        initMailRows();
        updateBulkActions();
        return true;
    } catch (err) {
        console.error('Inbox refresh failed:', err);
        return false;
    } finally {
        inboxRefreshInFlight = false;
    }
}

function initAutoRefresh() {
    const REFRESH_INTERVAL = 120000; // 2 minutes (reverted from 5 minutes)

    setInterval(() => {
        // Only auto-refresh if we are in the INBOX folder listing. 
        if (isInboxListView() && getCurrentFolder() === 'INBOX' && isSafeToRefreshInboxView()) {
            refreshInboxViewPreservingState();
        }
    }, REFRESH_INTERVAL);
}

/* =============================================================
   Sidebar unread count updates
   ============================================================= */
window.updateFolderUnread = function(folderName, count) {
    const badge = document.querySelector(`.badge[data-folder-unread="${folderName}"]`);
    if (!badge) return;
    
    const countInt = parseInt(count, 10) || 0;
    badge.textContent = countInt;
    badge.style.display = countInt > 0 ? 'inline-block' : 'none';
};

/* =============================================================
   Unread Checker Polling
   ============================================================= */
function initUnreadChecker() {
    const CHECK_INTERVAL = 60000; // 1 minute
    const originalTitle = document.title;
    let lastCounts = null;

    function check() {
        fetch('?action=check_unread', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.counts) {
                const currentCounts = JSON.stringify(data.counts);
                const countChanged = lastCounts !== null && lastCounts !== currentCounts;
                lastCounts = currentCounts;

                // Update all folder badges
                for (const [folder, count] of Object.entries(data.counts)) {
                    window.updateFolderUnread(folder, count);
                }

                const parsedInboxUnread = parseInt(data.inbox_unread, 10);
                if (!Number.isFinite(parsedInboxUnread)) {
                    console.warn('Unread checker received invalid inbox_unread value:', data.inbox_unread);
                    return;
                }
                // Update tab title
                if (parsedInboxUnread > 0) {
                    document.title = `(${parsedInboxUnread}) ${originalTitle}`;
                } else {
                    document.title = originalTitle;
                }

                if (countChanged && getCurrentFolder() === 'INBOX' && isInboxListView() && isSafeToRefreshInboxView()) {
                    refreshInboxViewPreservingState();
                }
            }
        })
        .catch(err => console.error('Unread check failed:', err));
    }

    // Initial check
    check();
    
    // Periodic check
    setInterval(check, CHECK_INTERVAL);
}

/* =============================================================
   Copy-to-clipboard background sanitizer
   Prevents page background color (light or dark) from being
   copied into clipboard HTML when copying headers, emails, or UI text.
   Preserves rich HTML structure, table layout, image alignment,
   and signature formatting without injecting pre-wrap or broken styles.
   ============================================================= */
function initCopySanitizer() {
    function isDarkColor(colorStr) {
        if (!colorStr) return false;
        const c = colorStr.trim().toLowerCase();
        if (c.includes('var(--wm-surface') || c.includes('var(--wm-bg') || 
            c === '#161b22' || c === '#0d1117' || c === '#1a1a1a' || 
            c === '#121212' || c === '#1f2937' || c === '#000000' || c === '#000') {
            return true;
        }
        if (c.startsWith('rgb')) {
            const matches = c.match(/\d+/g);
            if (matches && matches.length >= 3) {
                const r = parseInt(matches[0], 10);
                const g = parseInt(matches[1], 10);
                const b = parseInt(matches[2], 10);
                if (r < 55 && g < 55 && b < 55) {
                    return true;
                }
            }
        }
        return false;
    }

    function sanitizeRichHtml(container) {
        if (!container) return;

        // Clean dark theme background from top container if present
        if (container.style) {
            const bg = container.style.backgroundColor || container.style.background;
            if (isDarkColor(bg)) {
                container.style.removeProperty('background');
                container.style.removeProperty('background-color');
            }
        }

        const elements = container.querySelectorAll('*');
        elements.forEach(el => {
            // Remove shadow wrapper id/attributes that bind dark theme styles
            if (el.id === 'wm-shadow-wrapper') {
                el.removeAttribute('id');
                el.removeAttribute('data-theme');
            }
            if (el.hasAttribute('bgcolor') && isDarkColor(el.getAttribute('bgcolor'))) {
                el.removeAttribute('bgcolor');
            }
            if (el.style) {
                const bg = el.style.backgroundColor || el.style.background;
                if (isDarkColor(bg)) {
                    el.style.removeProperty('background');
                    el.style.removeProperty('background-color');
                }
                const color = el.style.color;
                if (color && (color.includes('rgb(230') || color.includes('rgb(240') || color.includes('rgb(255') || color.toLowerCase() === '#fff' || color.toLowerCase() === '#ffffff')) {
                    if (!el.style.backgroundColor || isDarkColor(el.style.backgroundColor)) {
                        el.style.removeProperty('color');
                    }
                }
            }
        });
    }

    function isRichHtml(container) {
        if (!container) return false;
        // Check for rich structural elements: img, table, td, tr, a, hr, form, etc.
        const richSelector = 'img, table, tr, td, th, a, iframe, svg, canvas, button, form, hr, ul, ol, li, blockquote';
        if (container.querySelector(richSelector)) {
            return true;
        }
        // Check for multiple element children with inline attributes or styles
        const children = container.querySelectorAll('*');
        for (let i = 0; i < children.length; i++) {
            const el = children[i];
            if (el.tagName === 'A' || el.tagName === 'IMG' || el.tagName === 'TABLE' || el.tagName === 'TD') return true;
            if (el.attributes && el.attributes.length > 0) return true;
        }
        return false;
    }

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function handleCopy(e) {
        // Do not intercept copying from standard form inputs or textareas
        const target = e.target;
        if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) {
            return;
        }

        // Get active selection from window or active shadow DOM
        let activeSel = window.getSelection();

        const shadowHost = document.getElementById('email-body-shadow');
        if (shadowHost && shadowHost.shadowRoot && typeof shadowHost.shadowRoot.getSelection === 'function') {
            try {
                const sSel = shadowHost.shadowRoot.getSelection();
                if (sSel && !sSel.isCollapsed && sSel.toString().length > 0) {
                    activeSel = sSel;
                }
            } catch (err) {}
        }

        if (!activeSel || activeSel.isCollapsed || activeSel.rangeCount === 0) {
            return;
        }

        const plainText = activeSel.toString();
        if (!plainText) return;

        // Determine if selection is preformatted / headers
        let isPreOrHeader = false;
        try {
            let anchor = activeSel.anchorNode;
            if (anchor && anchor.nodeType === Node.TEXT_NODE) anchor = anchor.parentNode;
            if (anchor && anchor.closest) {
                if (anchor.closest('pre') || anchor.closest('#headers-modal') || anchor.closest('#headers-content') || anchor.closest('.wm-view-meta')) {
                    isPreOrHeader = true;
                }
            }
        } catch (err) {}

        // Clone selected range contents into a container element
        const container = document.createElement('div');

        for (let i = 0; i < activeSel.rangeCount; i++) {
            container.appendChild(activeSel.getRangeAt(i).cloneContents());
        }

        let htmlPayload = '';

        if (isPreOrHeader) {
            const safeText = escapeHtml(plainText);
            const textWithBr = safeText.replace(/\r?\n/g, '<br>');
            htmlPayload = '<pre style="background: transparent !important; background-color: transparent !important; color: inherit !important; font-family: monospace, monospace; font-size: 13px; line-height: 1.5; white-space: pre-wrap !important; word-break: break-all; margin: 0; padding: 0;">' + textWithBr + '</pre>';
        } else if (isRichHtml(container)) {
            // Strip dark theme container background only, preserving rich HTML table layouts, signature formatting, image alignment & inline styles
            sanitizeRichHtml(container);
            htmlPayload = '<div style="color: inherit; font-family: inherit;">' + container.innerHTML + '</div>';
        } else {
            // Pure text selection: convert linebreaks to <br> with white-space: pre-wrap to preserve lines
            const safeText = escapeHtml(plainText);
            const textWithBr = safeText.replace(/\r?\n/g, '<br>');
            htmlPayload = '<div style="color: inherit; font-family: inherit; white-space: pre-wrap !important;">' + textWithBr + '</div>';
        }

        if (e.clipboardData) {
            e.clipboardData.setData('text/plain', plainText);
            e.clipboardData.setData('text/html', htmlPayload);
            e.preventDefault();
        }
    }

    document.addEventListener('copy', handleCopy, true);

    window.attachShadowCopySanitizer = function(shadowRoot) {
        if (shadowRoot) {
            shadowRoot.addEventListener('copy', handleCopy, true);
        }
    };
}

/* =============================================================
   Bootstrap
   ============================================================= */
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    initSidebar();
    initMobileSearch();
    initMailList();
    initMailRows();
    initImageProtection();
    initCompose();
    initAccountSwitcher();
    initAlerts();
    initCopyButtons();
    initSmtpPortSync();
    initAutoRefresh();
    initUnreadChecker();
    initCopySanitizer();
});

// Expose for inline use
window.ThemeManager = ThemeManager;
