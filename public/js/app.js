// Small progressive enhancements. Every page works without this file.
(function () {
    // Mobile navigation drawer.
    document.querySelectorAll('[data-toggle-nav]').forEach((button) =>
        button.addEventListener('click', () => document.body.classList.toggle('nav-open')));
    document.querySelector('.nav-backdrop')?.addEventListener('click', () => document.body.classList.remove('nav-open'));

    // Success toasts fade away on their own.
    document.querySelectorAll('.toast').forEach((toast) => setTimeout(() => {
        toast.classList.add('leaving');
        setTimeout(() => toast.remove(), 300);
    }, 4000));

    // Forms with data-confirm ask before submitting.
    document.querySelectorAll('form[data-confirm]').forEach((form) =>
        form.addEventListener('submit', (event) => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));

    // To-do forms: show the date-and-time box only for "Pick a time…".
    document.querySelectorAll('form[data-due-picker]').forEach((form) => {
        const select = form.querySelector('select[name="due"]');
        const input = form.querySelector('input[name="due_at"]');
        const sync = () => { input.hidden = select.value !== 'custom'; input.required = select.value === 'custom'; };
        select.addEventListener('change', () => { sync(); if (!input.hidden) input.focus(); });
        sync();
    });

    // <select data-preview-on-change>: refresh the form's summary (e.g. a broadcast's template fields).
    document.querySelectorAll('select[data-preview-on-change]').forEach((select) =>
        select.addEventListener('change', () => select.form.querySelector('button[name="preview"]')?.click()));

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

    // ---- Theme: Light, Dark or match the device (user menu) -------------
    // The public website (<html data-theme-locked>) is always light.
    const applyTheme = (choice) => {
        if (document.documentElement.hasAttribute('data-theme-locked')) return;
        const dark = choice === 'dark' || (choice === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-choice]').forEach((button) =>
            button.classList.toggle('active', button.dataset.themeChoice === choice));
    };
    const savedTheme = () => { try { return localStorage.getItem('crm-theme') || 'light'; } catch (e) { return 'light'; } };
    const chooseTheme = (choice) => { try { localStorage.setItem('crm-theme', choice); } catch (e) { /* not saved */ } applyTheme(choice); };
    applyTheme(savedTheme());
    matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => applyTheme(savedTheme()));
    document.querySelectorAll('[data-theme-choice]').forEach((button) =>
        button.addEventListener('click', () => chooseTheme(button.dataset.themeChoice)));

    // ---- Command palette (Ctrl/⌘ K) ---------------------------------------
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform);
    if (isMac) document.querySelectorAll('kbd[data-mod]').forEach((kbd) => { kbd.textContent = '⌘'; });

    const palette = document.getElementById('palette');
    const shortcuts = document.getElementById('shortcuts');
    const paletteInput = palette?.querySelector('input');
    const leadGroup = palette?.querySelector('[data-leads]');
    let searchTimer = null;
    let searchToken = 0;

    const paletteItems = () => [...palette.querySelectorAll('.palette-results a')].filter((a) => !a.closest('[hidden]') && !a.hidden);
    const highlight = (index) => {
        const items = paletteItems();
        items.forEach((a, i) => a.classList.toggle('active', i === index));
        items[index]?.scrollIntoView({ block: 'nearest' });
    };
    const activeIndex = () => paletteItems().findIndex((a) => a.classList.contains('active'));

    const filterCommands = () => {
        const words = paletteInput.value.toLowerCase().split(/\s+/).filter(Boolean);
        palette.querySelectorAll('[data-keywords]').forEach((a) => {
            a.closest('li').hidden = !words.every((word) => a.dataset.keywords.includes(word));
        });
        palette.querySelectorAll('.palette-group:not([data-leads])').forEach((group) => {
            group.hidden = !group.querySelector('li:not([hidden])');
        });
        palette.querySelector('.palette-empty').hidden = paletteItems().length > 0;
        highlight(0);
    };

    const searchLeads = () => {
        const query = paletteInput.value.trim();
        const token = ++searchToken;
        if (query.length < 2) { leadGroup.hidden = true; filterCommands(); return; }
        fetch(palette.dataset.searchUrl + '?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } })
            .then((response) => response.ok ? response.json() : { leads: [] })
            .then(({ leads }) => {
                if (token !== searchToken) return; // a newer search is on its way
                const list = leadGroup.querySelector('ul');
                list.replaceChildren(...leads.map((lead) => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = lead.url;
                    a.setAttribute('role', 'option');
                    const name = document.createElement('strong');
                    name.textContent = lead.name;
                    const detail = document.createElement('span');
                    detail.textContent = lead.detail;
                    a.append(name, detail);
                    li.append(a);
                    return li;
                }));
                leadGroup.hidden = leads.length === 0;
                filterCommands();
            })
            .catch(() => { /* offline: commands still work */ });
    };

    const runItem = (a) => {
        if (a.getAttribute('href') === '#theme') {
            chooseTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
            palette.close();
        } else if (a.getAttribute('href') === '#shortcuts') {
            palette.close();
            shortcuts.showModal();
        } else {
            window.location.href = a.href;
        }
    };

    const openPalette = () => {
        if (!palette || palette.open) return;
        paletteInput.value = '';
        leadGroup.hidden = true;
        filterCommands();
        palette.showModal();
        paletteInput.focus();
    };

    if (palette) {
        paletteInput.addEventListener('input', () => { filterCommands(); clearTimeout(searchTimer); searchTimer = setTimeout(searchLeads, 150); });
        paletteInput.addEventListener('keydown', (event) => {
            const items = paletteItems();
            if (event.key === 'ArrowDown') { event.preventDefault(); highlight(Math.min(activeIndex() + 1, items.length - 1)); }
            if (event.key === 'ArrowUp') { event.preventDefault(); highlight(Math.max(activeIndex() - 1, 0)); }
            if (event.key === 'Enter' && items[activeIndex()]) { event.preventDefault(); runItem(items[activeIndex()]); }
        });
        palette.addEventListener('click', (event) => {
            const a = event.target.closest('a');
            if (a) { event.preventDefault(); runItem(a); } else if (event.target === palette) { palette.close(); }
        });
        palette.addEventListener('mousemove', (event) => {
            const a = event.target.closest('a');
            if (a) highlight(paletteItems().indexOf(a));
        });
        shortcuts.addEventListener('click', (event) => {
            if (event.target === shortcuts || event.target.closest('[data-close]')) shortcuts.close();
        });
        document.querySelectorAll('[data-open-palette]').forEach((button) => button.addEventListener('click', openPalette));
    }

    // ---- Keyboard shortcuts (press ? for the list) --------------------------
    let pendingG = 0;
    const goTo = { d: 'dashboard', m: 'today', l: 'leads', i: 'inbox', r: 'reports', s: 'settings/workspace' };
    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            palette?.open ? palette.close() : openPalette();
            return;
        }

        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
        if (typing || event.metaKey || event.ctrlKey || event.altKey || document.querySelector('dialog[open]')) return;

        const key = event.key.toLowerCase();
        if (Date.now() - pendingG < 1000 && goTo[key]) {
            event.preventDefault();
            window.location.href = '/' + goTo[key];
        } else if (key === 'g') {
            pendingG = Date.now();
        } else if (event.key === '/') {
            const search = document.getElementById('global-search');
            if (search) { event.preventDefault(); search.focus(); }
        } else if (event.key === '?') {
            shortcuts?.showModal();
        } else if (key === 'n') {
            window.location.href = '/leads/create';
        } else if (key === 't') {
            window.location.href = '/today#add';
        }
    });

    // My day: "Add a to-do" (T, or the palette) lands in the box.
    if (location.hash === '#add') document.querySelector('.add-task input[name="title"]')?.focus();

    // ---- "How did the call go?" after tapping a Call button ------------------
    const callSheet = document.getElementById('call-sheet');
    if (callSheet) {
        const form = callSheet.querySelector('form');
        let started = 0;
        let timer = null;
        let logUrl = '';
        const seconds = () => Math.max(0, Math.round((Date.now() - started) / 1000));
        const tick = () => {
            const s = seconds();
            callSheet.querySelector('[data-call-timer]').textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        };
        const stop = () => { clearInterval(timer); callSheet.close(); };

        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-call]');
            if (!link) return;
            form.reset();
            form.action = link.dataset.call;
            logUrl = link.dataset.callLog;
            callSheet.querySelector('[data-call-name]').textContent = link.dataset.callName;
            started = Date.now();
            tick();
            clearInterval(timer);
            timer = setInterval(tick, 1000);
            // The phone app opens first; the sheet is waiting when they come back.
            setTimeout(() => { if (!callSheet.open) callSheet.showModal(); }, 300);
        });
        form.addEventListener('submit', (event) => {
            form.elements.seconds.value = seconds();
            form.elements.call_back.value = event.submitter?.dataset.callBack || '';
            clearInterval(timer);
        });
        callSheet.querySelector('[data-call-talked]').addEventListener('click', (event) => {
            event.preventDefault();
            window.location.href = logUrl + '?call=talked&seconds=' + seconds() + '#log';
        });
        callSheet.addEventListener('click', (event) => {
            if (event.target === callSheet || event.target.closest('[data-close]')) stop();
        });
    }

    // "How this page works": remember per page whether it was hidden.
    document.querySelectorAll('details.page-guide').forEach((guide) => {
        const key = 'crm-guide:' + guide.dataset.guide;
        const label = guide.querySelector('.page-guide-toggle');
        const sync = () => { label.textContent = guide.open ? label.dataset.openText : label.dataset.closedText; };
        try { if (localStorage.getItem(key) === 'hidden') guide.open = false; } catch (e) { /* stay open */ }
        sync();
        guide.addEventListener('toggle', () => {
            sync();
            try { guide.open ? localStorage.removeItem(key) : localStorage.setItem(key, 'hidden'); } catch (e) { /* not saved */ }
        });
    });

    // Close open dropdown menus when clicking elsewhere.
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.dropdown[open], details.user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });
})();
