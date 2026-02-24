/**
 * Lightweight, self-hosted ALTCHA client.
 * Fetches a challenge from the server, performs a small proof-of-work, and
 * injects the signed payload into the hidden input named "altcha".
 */
(function () {
    const DEFAULT_MAX_ATTEMPTS = 50000;
    const DEFAULT_HASH_PREFIX = '0000';
    const YIELD_INTERVAL = 2000;

    const widget = document.getElementById('altcha-container');
    if (!widget) return;

    const statusEl     = widget.querySelector('[data-altcha-status]');
    const retryBtn     = widget.querySelector('[data-altcha-retry]');
    const inputField   = widget.querySelector('input[name="altcha"]');
    const challengeUrl = widget.dataset.altchaUrl;
    const embedded     = widget.dataset.altchaPayload;

    function setStatus(text, isError = false) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.toggle('text-danger', isError);
        statusEl.classList.toggle('text-muted', !isError);
    }

    async function sha256(str) {
        const data = new TextEncoder().encode(str);
        const hash = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(hash))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

    async function solveChallenge(challenge) {
        const maxNumber = Number.isFinite(Number(challenge.maxnumber))
            ? Math.max(0, Number(challenge.maxnumber))
            : DEFAULT_MAX_ATTEMPTS;
        const targetPrefix = (typeof challenge.prefix === 'string' && challenge.prefix.trim() !== '')
            ? challenge.prefix.trim()
            : DEFAULT_HASH_PREFIX;

        // Simple PoW search: look for a hash with a small prefix of zeros.
        let number = 0;
        let signature = '';
        do {
            signature = await sha256(`${challenge.algorithm}:${challenge.challenge}:${number}`);
            number++;
            if (number % YIELD_INTERVAL === 0) {
                await new Promise((resolve) => setTimeout(resolve, 0));
            }
        } while (!signature.startsWith(targetPrefix) && number <= maxNumber);

        if (!signature.startsWith(targetPrefix)) {
            throw new Error('Unable to solve challenge within allowed attempts');
        }

        const payload = {
            algorithm: challenge.algorithm,
            challenge: challenge.challenge,
            salt: challenge.salt,
            maxnumber: maxNumber,
            number: number - 1,
            signature: signature,
        };

        inputField.value = btoa(unescape(encodeURIComponent(JSON.stringify(payload))));
        setStatus('Security check completed');
    }

    function decodeEmbedded() {
        if (!embedded) return null;
        try {
            const json = atob(embedded);
            return JSON.parse(json);
        } catch (_) {
            return null;
        }
    }

    async function loadChallenge() {
        setStatus('Preparing challenge...');
        retryBtn && (retryBtn.style.display = 'none');
        try {
            let challenge = decodeEmbedded();
            if (!challenge) {
                const res = await fetch(challengeUrl, { cache: 'no-store' });
                if (!res.ok) {
                    throw new Error('Challenge request failed');
                }
                challenge = await res.json();
            }
            await solveChallenge(challenge);
        } catch (e) {
            setStatus('Verification failed. Try again.', true);
            if (retryBtn) {
                retryBtn.style.display = 'inline-flex';
                retryBtn.disabled = false;
            }
        }
    }

    retryBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        loadChallenge();
    });

    loadChallenge();
})();
