import './bootstrap';

// Відбиток пристрою для обмеження голосів: не ідентифікує людину, лише налаштування браузера
async function deviceFingerprint() {
    const parts = [
        navigator.userAgent, navigator.language, navigator.hardwareConcurrency,
        screen.width, screen.height, screen.colorDepth, Intl.DateTimeFormat().resolvedOptions().timeZone,
    ].join('|');
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(parts));
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

document.querySelectorAll('[data-fingerprint-form]').forEach(async (form) => {
    try { form.elements.fingerprint.value = await deviceFingerprint(); } catch (e) {}
});

document.addEventListener('click', async (event) => {
    const copy = event.target.closest('[data-copy]');
    if (copy) {
        const text = copy.parentElement.querySelector('[data-copy-source]')?.textContent.trim();
        try {
            await navigator.clipboard.writeText(text);
            const label = copy.textContent;
            copy.textContent = copy.dataset.copiedLabel;
            setTimeout(() => { copy.textContent = label; }, 1500);
        } catch (e) {}
        return;
    }

    if (!event.target.closest('[data-theme-toggle]')) return;

    const root = document.documentElement;
    const next = root.dataset.theme === 'light' ? 'dark' : 'light';
    root.dataset.theme = next;
    try { localStorage.setItem('theme', next); } catch (e) {}
});
