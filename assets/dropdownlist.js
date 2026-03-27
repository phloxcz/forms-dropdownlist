/**
 * dropdownlist.js  –  phloxcz/forms-dropdownlist
 *
 * Theme-aware vanilla-JS widget for Phlox\Forms\DropDownList\DropDownListInput.
 * CSS classes are read from data-cls-* attributes set by resolveThemeClasses(),
 * so Bootstrap, Tailwind, or any custom theme works without changing this file.
 *
 * Two modes
 * ---------
 *  DropDownList (default)
 *    Read-only <button> trigger – clicking opens a panel with a filter input
 *    above the item list. Only exact list items can be selected.
 *    Trigger element: <button data-dropdownlist>
 *
 *  ComboBox  (data-mode="combobox")
 *    Editable text input + dropdown. Allows typing to search;
 *    the hidden value is cleared until a concrete item is picked.
 *    Trigger element: <input data-dropdownlist data-mode="combobox">
 *
 * Label → focus
 * -------------
 *  Because the trigger is a <button>, a <label for="id"> natively focuses it.
 *  Space on the button natively fires a click, which toggles open/close.
 *  ArrowDown opens the dropdown from the keyboard without a click.
 */

(function () {
    'use strict';

    const DEBOUNCE_MS = 250;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlight(label, query, markClass) {
        if (!query) return escHtml(label);
        const idx = label.toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) return escHtml(label);
        const openTag = markClass ? `<mark class="${escHtml(markClass)}">` : '<mark>';
        return (
            escHtml(label.slice(0, idx)) +
            openTag + escHtml(label.slice(idx, idx + query.length)) + '</mark>' +
            escHtml(label.slice(idx + query.length))
        );
    }

    function setClasses(el, classStr, force) {
        if (!classStr) return;
        classStr.split(/\s+/).filter(Boolean).forEach((c) => el.classList.toggle(c, force));
    }

    // ─── DropDownList (read-only trigger + filter panel) ─────────────────────

    function initDropDownList(trigger) {
        const wrapper     = trigger.parentElement;
        const hidden      = wrapper.querySelector('input[type="hidden"]');
        const panel       = wrapper.querySelector('[data-dropdownlist-panel]');
        const filterInput = panel?.querySelector('[data-dropdownlist-filter]');
        const listbox     = panel?.querySelector('[role="listbox"]');

        if (!hidden || !panel || !filterInput || !listbox) return;

        const ajaxUrl = trigger.dataset.ajaxUrl;

        const cls = {
            item:         trigger.dataset.clsItem         ?? '',
            itemActive:   trigger.dataset.clsItemActive   ?? 'dropdownlist-active',
            itemSelected: trigger.dataset.clsItemSelected ?? 'dropdownlist-selected',
            noResults:    trigger.dataset.clsNoResults    ?? 'dropdownlist-no-results',
            mark:         trigger.dataset.clsMark         ?? '',
        };

        const txt = {
            noResults: trigger.dataset.txtNoResults ?? 'Žádné výsledky',
        };

        let activeIndex = -1;
        let fetchCtrl   = null;
        let isOpen      = false;

        // ── Rendering ────────────────────────────────────────────────────────

        function renderItems(items, query) {
            listbox.innerHTML = '';
            activeIndex = -1;

            if (items.length === 0) {
                const li = document.createElement('li');
                if (cls.noResults) li.className = cls.noResults;
                li.textContent = txt.noResults;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-disabled', 'true');
                listbox.appendChild(li);
                return;
            }

            for (const item of items) {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = item.value;
                li.dataset.label = item.label;
                li.innerHTML = highlight(item.label, query, cls.mark);
                if (cls.item) li.className = cls.item;
                if (String(item.value) === String(hidden.value)) {
                    setClasses(li, cls.itemSelected, true);
                    setTimeout(() => li.scrollIntoView({ block: 'nearest' }), 0);
                }
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();   // keep focus on filterInput until pick()
                    pick(item);
                });
                listbox.appendChild(li);
            }
        }

        function syncHighlight() {
            listbox.querySelectorAll('[role="option"][data-value]').forEach((li, i) => {
                setClasses(li, cls.itemActive, i === activeIndex);
            });
        }

        // ── Open / close ─────────────────────────────────────────────────────

        function open() {
            if (isOpen) return;
            isOpen = true;
            panel.style.display = 'block';
            trigger.setAttribute('aria-expanded', 'true');
            filterInput.value = '';
            // Focus moves to the filter input so the user can type immediately.
            // Use rAF to allow the panel to become visible first.
            requestAnimationFrame(() => filterInput.focus());
            fetchItems('');
        }

        function close() {
            if (!isOpen) return;
            isOpen = false;
            panel.style.display = 'none';
            activeIndex = -1;
            syncHighlight();
            trigger.setAttribute('aria-expanded', 'false');
        }

        // ── Selection ────────────────────────────────────────────────────────

        function pick(item) {
            hidden.value = item.value;

            const labelSpan = trigger.querySelector('.dropdownlist-trigger-text');
            if (labelSpan) {
                labelSpan.textContent = item.label;
                labelSpan.classList.remove('dropdownlist-trigger-placeholder');
            }

            close();
            // Return focus to trigger so the user can continue tabbing.
            trigger.focus();
            trigger.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // ── AJAX ─────────────────────────────────────────────────────────────

        async function fetchItems(query) {
            if (fetchCtrl) fetchCtrl.abort();
            fetchCtrl = new AbortController();
            const url = new URL(ajaxUrl, window.location.href);
            url.searchParams.set('q', query);
            try {
                const resp = await fetch(url.toString(), {
                    signal: fetchCtrl.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!resp.ok) return;
                const data = await resp.json();
                renderItems(Array.isArray(data) ? data : [], query);
            } catch (err) {
                if (err.name !== 'AbortError') console.error('[phloxcz/forms-dropdownlist] fetch error', err);
            }
        }

        const debouncedFetch = debounce(fetchItems, DEBOUNCE_MS);

        // ── Trigger events ───────────────────────────────────────────────────
        //
        // The trigger is a <button type="button">, so:
        //   – <label for="id"> natively focuses it (no JS needed)
        //   – Space natively fires click → toggles open/close via click handler
        //   – Enter is suppressed: on a button inside a form, Enter should submit
        //   – ArrowDown opens the dropdown from the keyboard without a click

        trigger.addEventListener('click', () => { isOpen ? close() : open(); });

        trigger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter')     { e.preventDefault(); }          // suppress – Enter submits form
            if (e.key === 'ArrowDown') { e.preventDefault(); open(); }
            if (e.key === 'Escape')    { e.preventDefault(); close(); }
        });

        // ── Filter input events ───────────────────────────────────────────────

        filterInput.addEventListener('input', () => {
            activeIndex = -1;
            debouncedFetch(filterInput.value.trim());
        });

        filterInput.addEventListener('keydown', (e) => {
            const items = [...listbox.querySelectorAll('[role="option"][data-value]')];

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, items.length - 1);
                    syncHighlight();
                    items[activeIndex]?.scrollIntoView({ block: 'nearest' });
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (activeIndex <= 0) {
                        activeIndex = -1;
                        syncHighlight();
                        // Return focus to the filter field (already there) – nothing to do.
                    } else {
                        activeIndex--;
                        syncHighlight();
                        items[activeIndex]?.scrollIntoView({ block: 'nearest' });
                    }
                    break;

                case 'Enter':
                    e.preventDefault();
                    if (activeIndex >= 0 && items[activeIndex]) {
                        const li = items[activeIndex];
                        pick({ value: li.dataset.value, label: li.dataset.label });
                    }
                    break;

                case 'Tab':
                    // Pick the highlighted item (if any) before Tab moves focus away.
                    if (activeIndex >= 0 && items[activeIndex]) {
                        const li = items[activeIndex];
                        pick({ value: li.dataset.value, label: li.dataset.label });
                    }
                    close();
                    break;

                case 'Escape':
                    e.preventDefault();
                    close();
                    trigger.focus();
                    break;
            }
        });

        // Close when focus leaves the whole widget.
        filterInput.addEventListener('blur', () => {
            setTimeout(() => {
                if (!wrapper.contains(document.activeElement)) close();
            }, 160);
        });

        trigger.addEventListener('blur', () => {
            setTimeout(() => {
                if (!wrapper.contains(document.activeElement)) close();
            }, 160);
        });

        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) close();
        });
    }

    // ─── ComboBox mode (editable text input) ─────────────────────────────────

    function initComboBox(input) {
        const wrapper  = input.parentElement;
        const hidden   = wrapper.querySelector('input[type="hidden"]');
        const dropdown = wrapper.querySelector('[data-dropdownlist-panel]');

        if (!hidden || !dropdown) return;

        const ajaxUrl = input.dataset.ajaxUrl;

        const cls = {
            item:         input.dataset.clsItem         ?? '',
            itemActive:   input.dataset.clsItemActive   ?? 'dropdownlist-active',
            itemSelected: input.dataset.clsItemSelected ?? 'dropdownlist-selected',
            noResults:    input.dataset.clsNoResults    ?? 'dropdownlist-no-results',
            mark:         input.dataset.clsMark         ?? '',
        };

        const txt = {
            noResults: input.dataset.txtNoResults ?? 'Žádné výsledky',
        };

        let activeIndex = -1;
        let prevLabel   = input.value;
        let prevValue   = hidden.value;
        let fetchCtrl   = null;

        function renderItems(items, query) {
            dropdown.innerHTML = '';
            activeIndex = -1;

            if (items.length === 0) {
                const li = document.createElement('li');
                if (cls.noResults) li.className = cls.noResults;
                li.textContent = txt.noResults;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-disabled', 'true');
                dropdown.appendChild(li);
                open();
                return;
            }

            for (const item of items) {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = item.value;
                li.dataset.label = item.label;
                li.innerHTML = highlight(item.label, query, cls.mark);
                if (cls.item) li.className = cls.item;
                if (String(item.value) === String(hidden.value)) {
                    setClasses(li, cls.itemSelected, true);
                }
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    pick(item);
                });
                dropdown.appendChild(li);
            }
            open();
        }

        function open() {
            dropdown.style.display = 'block';
            input.setAttribute('aria-expanded', 'true');
            const sel = dropdown.querySelector('[role="option"].' + (cls.itemSelected || 'dropdownlist-selected').split(/\s+/)[0]);
            if (sel) sel.scrollIntoView({ block: 'nearest' });
        }

        function close() {
            dropdown.style.display = 'none';
            activeIndex = -1;
            syncHighlight();
            input.setAttribute('aria-expanded', 'false');
        }

        function syncHighlight() {
            dropdown.querySelectorAll('[role="option"][data-value]').forEach((li, i) => {
                setClasses(li, cls.itemActive, i === activeIndex);
            });
        }

        function pick(item) {
            hidden.value = item.value;
            input.value  = item.label;
            prevValue    = item.value;
            prevLabel    = item.label;
            close();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        async function fetchItems(query) {
            if (fetchCtrl) fetchCtrl.abort();
            fetchCtrl = new AbortController();
            const url = new URL(ajaxUrl, window.location.href);
            url.searchParams.set('q', query);
            try {
                const resp = await fetch(url.toString(), {
                    signal: fetchCtrl.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!resp.ok) return;
                const data = await resp.json();
                renderItems(Array.isArray(data) ? data : [], query);
            } catch (err) {
                if (err.name !== 'AbortError') console.error('[phloxcz/forms-dropdownlist] fetch error', err);
            }
        }

        const debouncedFetch = debounce(fetchItems, DEBOUNCE_MS);

        input.addEventListener('focus', () => { prevLabel = input.value; prevValue = hidden.value; fetchItems(''); });
        input.addEventListener('input', () => { hidden.value = ''; debouncedFetch(input.value.trim()); });

        input.addEventListener('keydown', (e) => {
            const items   = [...dropdown.querySelectorAll('[role="option"][data-value]')];
            const visible = dropdown.style.display !== 'none';
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (!visible) { fetchItems(''); break; }
                    activeIndex = Math.min(activeIndex + 1, items.length - 1);
                    syncHighlight(); items[activeIndex]?.scrollIntoView({ block: 'nearest' }); break;
                case 'ArrowUp':
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    syncHighlight(); items[activeIndex]?.scrollIntoView({ block: 'nearest' }); break;
                case 'Enter':
                    e.preventDefault();
                    if (activeIndex >= 0 && items[activeIndex]) {
                        const li = items[activeIndex];
                        pick({ value: li.dataset.value, label: li.dataset.label });
                    }
                    break;
                case 'Tab':
                    if (visible && activeIndex >= 0 && items[activeIndex]) {
                        const li = items[activeIndex];
                        pick({ value: li.dataset.value, label: li.dataset.label });
                    }
                    close(); break;
                case 'Escape':
                    input.value = prevLabel; hidden.value = prevValue; close(); break;
            }
        });

        input.addEventListener('blur', () => {
            setTimeout(() => { close(); if (input.value.trim() === '') hidden.value = ''; }, 160);
        });

        document.addEventListener('click', (e) => { if (!wrapper.contains(e.target)) close(); });
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    function initAll(root) {
        root.querySelectorAll('[data-dropdownlist]').forEach((el) => {
            if (!el.dataset.dlInit) {
                el.dataset.dlInit = '1';
                if (el.dataset.mode === 'combobox') {
                    initComboBox(el);
                } else {
                    initDropDownList(el);
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => initAll(document));

    if (window.naja) {
        window.naja.addEventListener('complete', () => initAll(document));
    }

    new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType === 1) initAll(/** @type {Element} */ (node));
            }
        }
    }).observe(document.body, { childList: true, subtree: true });

}());
