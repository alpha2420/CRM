// Small progressive enhancements. Every page works without this file.
(function () {
    // Mobile navigation drawer.
    document.querySelectorAll('[data-toggle-nav]').forEach((button) =>
        button.addEventListener('click', () => document.body.classList.toggle('nav-open')));
    document.querySelector('.nav-backdrop')?.addEventListener('click', () => document.body.classList.remove('nav-open'));

    // Press "/" to search leads.
    document.addEventListener('keydown', (event) => {
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
        if (event.key === '/' && !typing) {
            const search = document.getElementById('global-search');
            if (search) { event.preventDefault(); search.focus(); }
        }
    });

    // Success toasts fade away on their own.
    document.querySelectorAll('.toast').forEach((toast) => setTimeout(() => {
        toast.classList.add('leaving');
        setTimeout(() => toast.remove(), 300);
    }, 4000));

    // Forms with data-confirm ask before submitting.
    document.querySelectorAll('form[data-confirm]').forEach((form) =>
        form.addEventListener('submit', (event) => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));

    // Close open dropdown menus when clicking elsewhere.
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.dropdown[open], details.user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });
})();
