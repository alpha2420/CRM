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

    // <select data-autosubmit>: apply the choice right away.
    document.querySelectorAll('select[data-autosubmit]').forEach((select) =>
        select.addEventListener('change', () => select.form.submit()));

    // <button data-busy="Thinking…">: show progress and prevent double submits.
    document.querySelectorAll('button[data-busy]').forEach((button) =>
        button.form?.addEventListener('submit', () => {
            button.disabled = true;
            button.textContent = button.dataset.busy;
        }));

    // <button data-copy="selector">: copy that element's text (nearest card first).
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', () => {
        const source = button.closest('.card')?.querySelector(button.dataset.copy) ?? document.querySelector(button.dataset.copy);
        navigator.clipboard?.writeText(source?.textContent.trim() ?? '').then(() => { button.textContent = 'Copied ✓'; });
    }));

    // Close open dropdown menus when clicking elsewhere.
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.dropdown[open], details.user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });
})();
