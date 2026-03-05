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
function initAutoRefresh() {
    const REFRESH_INTERVAL = 120000; // 2 minutes
    let lastCheck = Date.now();

    setInterval(() => {
        // Only refresh if we are on the inbox/folder list and not currently interacting
        const isInbox = window.location.search.includes('action=inbox') || window.location.search === '';
        const isCompose = window.location.search.includes('action=compose');
        
        if (isInbox && !isCompose && !document.hidden) {
            // Check if we have any open modals or active inputs
            const hasModal = document.querySelector('.wm-modal[style*="display: flex"]') || 
                           document.querySelector('.wm-modal[style*="display: block"]');
            const activeEl = document.activeElement;
            const isInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.contentEditable === 'true');

            if (!hasModal && !isInput) {
                // Subtle refresh: just reload the page to get new mail
                // In a more advanced version, we could fetch just the list via AJAX
                window.location.reload();
            }
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

    function check() {
        fetch('?action=check_unread', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.counts) {
                // Update all folder badges
                for (const [folder, count] of Object.entries(data.counts)) {
                    window.updateFolderUnread(folder, count);
                }

                // Update tab title
                if (data.inbox_unread > 0) {
                    document.title = `(${data.inbox_unread}) ${originalTitle}`;
                } else {
                    document.title = originalTitle;
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
});

// Expose for inline use
window.ThemeManager = ThemeManager;
