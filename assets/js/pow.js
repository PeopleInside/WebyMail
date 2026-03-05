// Simple proof-of-work solver for WebyMail login captcha.
(function() {
    const widget = document.getElementById('pow-widget');
    if (!widget) return;

    const statusEl     = widget.querySelector('[data-pow-status]');
    const spinnerEl    = widget.querySelector('[data-pow-spinner]');
    const progressEl   = widget.querySelector('[data-pow-progress]');
    const refreshBtn   = widget.querySelector('[data-pow-refresh]');
    const solutionEl   = document.getElementById('pow-solution');
    const tokenEl      = document.getElementById('pow-token');
    const challengeEl  = document.getElementById('pow-challenge');
    const difficultyEl = document.getElementById('pow-difficulty');
    const expiresEl    = document.getElementById('pow-expires');
    const form         = document.getElementById('login-form');
    const submitBtn    = form?.querySelector('button[type="submit"]');

    let current     = null;
    let solving     = false;
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

    function sha256Fallback(ascii) {
        function rightRotate(value, amount) {
            return (value >>> amount) | (value << (32 - amount));
        }
        const mathPow    = Math.pow;
        const maxWord    = mathPow(2, 32);
        const lengthProp = 'length';
        const words = [];
        const asciiBitLength = ascii[lengthProp] * 8;
        let hash = sha256Fallback.h = sha256Fallback.h || [];
        let k    = sha256Fallback.k = sha256Fallback.k || [];
        let primeCounter = k[lengthProp];
        const isComposite = {};

        for (let candidate = 2; primeCounter < 64; candidate++) {
            if (!isComposite[candidate]) {
                for (let i = 0; i < 313; i += candidate) {
                    isComposite[i] = candidate;
                }
                hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
                k[primeCounter++]  = (mathPow(candidate, 1 / 3) * maxWord) | 0;
            }
        }

        ascii += '\x80';
        while (ascii[lengthProp] % 64 - 56) ascii += '\x00';
        for (let i = 0; i < ascii[lengthProp]; i++) {
            const j = ascii.charCodeAt(i);
            words[i >> 2] |= j << ((3 - i) % 4) * 8;
        }
        words[words[lengthProp]] = (asciiBitLength / maxWord) | 0;
        words[words[lengthProp]] = asciiBitLength;

        for (let j = 0; j < words[lengthProp];) {
            const w = words.slice(j, j += 16);
            const oldHash = hash;
            hash = hash.slice(0, 8);

            for (let i = 0; i < 64; i++) {
                const w15 = w[i - 15], w2 = w[i - 2];
                const a = hash[0], e = hash[4];
                const temp1 = hash[7]
                    + (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
                    + ((e & hash[5]) ^ ((~e) & hash[6]))
                    + k[i]
                    + (w[i] = (i < 16) ? w[i] : (
                        w[i - 16]
                        + (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3))
                        + (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))
                        + w[i - 7]
                    ) | 0);
                const temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
                    + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));

                hash = [(temp1 + temp2) | 0].concat(hash);
                hash[4] = (hash[4] + temp1) | 0;
            }

            for (let i = 0; i < 8; i++) {
                hash[i] = (hash[i] + oldHash[i]) | 0;
            }
        }

        let result = '';
        for (let i = 0; i < 8; i++) {
            for (let j = 3; j + 1; j--) {
                const b = (hash[i] >> (j * 8)) & 255;
                result += ((b < 16) ? 0 : '') + b.toString(16);
            }
        }
        return result;
    }

    // Always use the sync fallback for batched PoW hashing.
    // WebCrypto is async-only, which would require one Promise per hash and
    // create enormous overhead for large nonce searches.  The sync fallback
    // produces identical SHA-256 output for the ASCII inputs used here and
    // is fast enough for the configured difficulty level.

    function loadChallenge(ch) {
        current = ch;
        solutionEl.value    = '';
        tokenEl.value       = ch.token      || '';
        if (challengeEl)  challengeEl.value  = ch.challenge  || '';
        if (difficultyEl) difficultyEl.value = ch.difficulty != null ? String(ch.difficulty) : '';
        if (expiresEl)    expiresEl.value    = ch.expires    != null ? String(ch.expires)    : '';
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
                solutionEl.value = '';
                tokenEl.value = '';
                solving = false;
                setProgress(100);
                clearInterval(expiryTimer);
            } else {
                const remaining = Math.ceil((expiresAt - now) / 1000);
                if (!solving && solutionEl.value) {
                    // Already solved, just watch for expiry
                    if (remaining <= 30) {
                        setStatus(`Security check expires in ${remaining}s. Submit soon!`, true);
                    }
                } else if (!solving && !solutionEl.value) {
                    // Not solving and not solved (maybe failed)
                }
            }
        }, 1000);

        solve(ch).catch(() => setStatus('Security check failed. Refresh to retry.', true));
    }

    async function solve(challenge) {
        solving = true;
        setStatus('Solving… this may take a moment.');

        const difficulty  = Math.max(1, parseInt(challenge.difficulty || '3', 10));
        const target      = '0'.repeat(difficulty);
        const expiresAtMs = parseInt(challenge.expires || '0', 10) * 1000;
        const prefix      = challenge.challenge + ':';
        // Batch size: number of synchronous hashes before yielding to the
        // browser event loop.  Larger batches = faster solving; smaller
        // batches = more responsive UI.
        const BATCH = 2000;
        let nonce    = 0;
        let progress = 0;

        while (solving) {
            // Run a batch of synchronous SHA-256 computations.
            const end = nonce + BATCH;
            while (nonce < end) {
                if (sha256Fallback(prefix + nonce).startsWith(target)) {
                    solutionEl.value = String(nonce);
                    solving = false;
                    setStatus('Security check solved.');
                    setProgress(100);
                    disableSubmit(false);
                    return;
                }
                nonce++;
            }

            // Yield once per batch so the browser stays responsive.
            progress = Math.min(95, progress + (100 - progress) * 0.1);
            setProgress(progress);
            await new Promise(r => setTimeout(r, 0));

            if (Date.now() > expiresAtMs) {
                solving = false;
                setStatus('Security check expired.', true);
                return;
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
        if (!solutionEl.value || !tokenEl.value) {
            e.preventDefault();
            setStatus('Please wait for the security check to finish.');
        }
    });

    const initial = widget.dataset.challenge ? JSON.parse(widget.dataset.challenge) : null;
    if (initial) loadChallenge(initial);
    else refreshBtn?.click();
})();
