// Simple proof-of-work solver for WebyMail login captcha.
(function() {
    const widget = document.getElementById('pow-widget');
    if (!widget || !window.crypto || !window.crypto.subtle) return;

    const statusEl   = widget.querySelector('[data-pow-status]');
    const refreshBtn = widget.querySelector('[data-pow-refresh]');
    const solutionEl = document.getElementById('pow-solution');
    const tokenEl    = document.getElementById('pow-token');
    const form       = document.getElementById('login-form');
    const submitBtn  = form?.querySelector('button[type="submit"]');

    let current = null;
    let solving = false;

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    function disableSubmit(disabled) {
        if (submitBtn) submitBtn.disabled = disabled;
    }

    async function hashString(str) {
        const data = new TextEncoder().encode(str);
        const buf  = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function loadChallenge(ch) {
        current = ch;
        solutionEl.value = '';
        tokenEl.value    = ch.token || '';
        disableSubmit(true);
        if (!ch.challenge || !ch.token) {
            setStatus('Could not load security challenge.');
            return;
        }
        setStatus('Solving… this may take a moment.');
        solve(ch).catch(() => setStatus('Security check failed. Refresh to retry.'));
    }

    async function solve(challenge) {
        solving = true;
        const difficulty = Math.max(1, parseInt(challenge.difficulty || '4', 10));
        const target = '0'.repeat(difficulty);
        let nonce = 0;

        while (solving) {
            const candidate = challenge.challenge + ':' + nonce;
            const digest = await hashString(candidate);
            if (digest.startsWith(target)) {
                solutionEl.value = String(nonce);
                setStatus('Security check solved.');
                disableSubmit(false);
                solving = false;
                return;
            }
            nonce++;
            // Yield to avoid blocking UI
            if (nonce % 500 === 0) {
                await new Promise(r => setTimeout(r, 0));
            }
        }
    }

    async function fetchChallenge() {
        const endpoint = widget.dataset.endpoint;
        try {
            const res = await fetch(endpoint, { cache: 'no-store' });
            if (!res.ok) throw new Error('Request failed');
            const ch = await res.json();
            return ch;
        } catch (e) {
            return null;
        }
    }

    refreshBtn?.addEventListener('click', async () => {
        setStatus('Refreshing challenge…');
        solving = false;
        disableSubmit(true);
        const ch = await fetchChallenge();
        if (ch) loadChallenge(ch);
        else setStatus('Could not refresh challenge. Try again.');
    });

    // Prevent submit until solved
    form?.addEventListener('submit', (e) => {
        if (!solutionEl.value || !tokenEl.value) {
            e.preventDefault();
            setStatus('Please wait for the security check to finish.');
        }
    });

    const initial = widget.dataset.challenge ? JSON.parse(widget.dataset.challenge) : null;
    if (initial) loadChallenge(initial);
    else refreshBtn?.click();
})();
