// Спільний AJAX-захист. Жодної автоматичної повторної відправки POST.
'use strict';
window.ForumSecurity = (() => {
    const script = document.currentScript;
    const url = new URL('auth.php', script.src);
    let state = null;
    async function refresh() {
        const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
        const body = await response.json();
        if (!response.ok || !body.success) throw new Error(body.error || 'Помилка сесії.');
        state = body.data;
        return state;
    }
    const ready = refresh();
    // Обробник запобігає unhandled rejection до першої взаємодії з формою.
    ready.catch(() => {});
    async function options(options = {}) {
        await ready;
        const headers = new Headers(options.headers || {});
        headers.set('X-CSRF-Token', state.csrf_token);
        return { ...options, headers, credentials: 'same-origin' };
    }
    return { ready, refresh, options };
})();
