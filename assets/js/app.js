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
        if (mode === 'system') {
            html.removeAttribute('data-theme');
        } else {
            html.setAttribute('data-theme', mode);
        }
        localStorage.setItem(KEY, mode);
        updateButtons(mode);
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
    }

    return { init, apply, current, cycle };
})();

/* =============================================================
   Sidebar toggle (mobile)
   ============================================================= */
function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.wm-sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && e.target !== toggle) {
            sidebar.classList.remove('open');
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
    const res  = await fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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
function initSmtpPortSync(container) {
    const root = container || document;
    const port    = root.querySelector('[data-smtp-port]');
    const ssl     = root.querySelector('[data-smtp-ssl]');
    const starttl = root.querySelector('[data-smtp-starttls]');
    if (!port || !ssl || !starttl) return;

    const sync = () => {
        if (ssl.checked) {
            starttl.checked = false;
            port.value = SMTP_SSL_PORT;
        } else if (starttl.checked) {
            ssl.checked = false;
            port.value = SMTP_STARTTLS_PORT;
        } else if (!port.value) {
            port.value = SMTP_STARTTLS_PORT;
            starttl.checked = true;
        }
    };

    ssl.addEventListener('change', sync);
    starttl.addEventListener('change', sync);
    sync();
}

/* =============================================================
   Bootstrap
   ============================================================= */
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    initSidebar();
    initMailList();
    initMailRows();
    initImageProtection();
    initCompose();
    initAccountSwitcher();
    initAlerts();
    initCopyButtons();
    initSmtpPortSync();
});

// Expose for inline use
window.ThemeManager = ThemeManager;
