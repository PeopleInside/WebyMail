// Simple proof-of-work solver for WebyMail login captcha.
(function() {
    const widget = document.getElementById('pow-widget');
    if (!widget) return;

    const statusEl    = widget.querySelector('[data-pow-status]');
    const spinnerEl   = widget.querySelector('[data-pow-spinner]');
    const progressEl  = widget.querySelector('[data-pow-progress]');
    const refreshBtn  = widget.querySelector('[data-pow-refresh]');
    const solutionEl  = document.getElementById('pow-solution');
    const tokenEl     = document.getElementById('pow-token');
    const challengeEl = document.getElementById('pow-challenge');
    const difficultyEl = document.getElementById('pow-difficulty');
    const expiresEl   = document.getElementById('pow-expires');
    const form        = document.getElementById('login-form');
    const submitBtn   = form?.querySelector('button[type="submit"]');

    // If SubtleCrypto is unavailable (non-secure context / old browser),
    // disable the submit button and show a clear error rather than silently
    // letting the form submit with empty captcha fields.
    if (!window.crypto || !window.crypto.subtle) {
        disableSubmit(true, 'Security check unavailable');
        setStatus('Security check requires HTTPS. Please use a secure connection.', true);
        return;
    }

    let current = null;
    let solving = false;
    let expiryTimer = null;

    function setStatus(text, isError = false) {
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.style.color = isError ? 'var(--wm-danger)' : '';
        }
        if (spinnerEl) {
            spinnerEl.style.display = (solving && !isError) ? 'block' : 'none';
        }
        if (progressEl) {
            if (isError) progressEl.style.backgroundColor = 'var(--wm-danger)';
            else progressEl.style.backgroundColor = '';
        }
    }

    function setProgress(percent) {
        if (progressEl) {
            progressEl.style.width = percent + '%';
            if (solving && percent < 100) {
                progressEl.classList.add('pow-pulse');
            } else {
                progressEl.classList.remove('pow-pulse');
            }
        }
    }

    function disableSubmit(disabled, text = null) {
        if (submitBtn) {
            submitBtn.disabled = disabled;
            if (text !== null) {
                if (!submitBtn.dataset.originalText) {
                    submitBtn.dataset.originalText = submitBtn.textContent;
                }
                submitBtn.textContent = text;
            } else if (submitBtn.dataset.originalText) {
                submitBtn.textContent = submitBtn.dataset.originalText;
            }
        }
    }

    async function hashString(str) {
        const data = new TextEncoder().encode(str);
        const buf  = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function clearFields() {
        if (solutionEl)   solutionEl.value   = '';
        if (tokenEl)      tokenEl.value      = '';
        if (challengeEl)  challengeEl.value  = '';
        if (difficultyEl) difficultyEl.value = '';
        if (expiresEl)    expiresEl.value    = '';
    }

    function loadChallenge(ch) {
        current = ch;
        clearFields();
        // Populate the static challenge fields immediately so the server can
        // verify them without needing a session lookup (stateless HMAC check).
        if (tokenEl)      tokenEl.value      = ch.token      || '';
        if (challengeEl)  challengeEl.value  = ch.challenge  || '';
        if (difficultyEl) difficultyEl.value = String(ch.difficulty ?? '');
        if (expiresEl)    expiresEl.value    = String(ch.expires    ?? '');
        disableSubmit(true, 'Waiting for security check...');
        setProgress(0);
        if (expiryTimer) clearInterval(expiryTimer);

        if (!ch.challenge || !ch.token) {
            setStatus('Could not load security challenge.', true);
            return;
        }

        // Set expiration timer based on server-provided expiry
        const expiresAt = parseInt(ch.expires || '0', 10) * 1000;

        expiryTimer = setInterval(() => {
            const now = Date.now();
            if (now >= expiresAt) {
                setStatus('Security check expired. Please refresh.', true);
                disableSubmit(true, 'Security check expired');
                clearFields();
                solving = false;
                setProgress(100);
                clearInterval(expiryTimer);
            } else {
                const remaining = Math.ceil((expiresAt - now) / 1000);
                if (!solving && solutionEl && solutionEl.value) {
                    // Already solved, just watch for expiry
                    if (remaining <= 30) {
                        setStatus(`Security check expires in ${remaining}s. Submit soon!`, true);
                    }
                }
            }
        }, 1000);

        solve(ch).catch(() => setStatus('Security check failed. Refresh to retry.', true));
    }

    async function solve(challenge) {
        solving = true;
        setStatus('Solving… this may take a moment.');
        
        const difficulty = Math.max(1, parseInt(challenge.difficulty || '5', 10));
        const target = '0'.repeat(difficulty);
        let nonce = 0;
        
        // We can't easily predict the exact number of iterations, 
        // but we can show some movement.
        let progress = 0;

        while (solving) {
            const candidate = challenge.challenge + ':' + nonce;
            const digest = await hashString(candidate);
            
            if (digest.startsWith(target)) {
                if (solutionEl) solutionEl.value = String(nonce);
                solving = false;
                setStatus('Security check solved.');
                setProgress(100);
                disableSubmit(false);
                return;
            }
            nonce++;
            
            if (nonce % 250 === 0) {
                // Update fake progress to show activity
                progress = Math.min(95, progress + (100 - progress) * 0.05);
                setProgress(progress);

                await new Promise(r => setTimeout(r, 0));
                if (Date.now() > (parseInt(challenge.expires || '0', 10) * 1000)) {
                    solving = false;
                    setStatus('Security check expired.', true);
                    return;
                }
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
        solving = false;
        setStatus('Refreshing challenge…');
        disableSubmit(true, 'Refreshing security check...');
        const ch = await fetchChallenge();
        if (ch) loadChallenge(ch);
        else setStatus('Could not refresh challenge. Try again.');
    });

    // Prevent submit until solved
    form?.addEventListener('submit', (e) => {
        if (!solutionEl || !solutionEl.value || !tokenEl || !tokenEl.value) {
            e.preventDefault();
            setStatus('Please wait for the security check to finish.');
        }
    });

    const initial = widget.dataset.challenge ? JSON.parse(widget.dataset.challenge) : null;
    if (initial) loadChallenge(initial);
    else refreshBtn?.click();
})();
