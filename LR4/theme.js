// Перемикач світлої/темної теми. Вибір зберігається в localStorage;
// якщо його немає — береться системна тема (prefers-color-scheme).
(function () {
    var root = document.documentElement;
    var saved = null;
    try { saved = localStorage.getItem('theme'); } catch (e) {}

    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    root.setAttribute('data-theme', theme);

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('theme-toggle');
        if (!btn) { return; }

        function render() {
            var dark = root.getAttribute('data-theme') === 'dark';
            btn.textContent = dark ? 'Світла тема' : 'Темна тема';
        }

        btn.addEventListener('click', function () {
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
            render();
        });
        render();
    });
})();
