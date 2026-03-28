/* global document, window, fetch, FormData, MutationObserver */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  CSRF                                                                */
    /* ------------------------------------------------------------------ */
    function getCsrfToken() {
        // GLPI 11: múltiplos campos possíveis
        var selectors = [
            'input[name="_glpi_csrf_token"]',
            'input[name="glpi_csrf_token"]',
            'meta[name="glpi-csrf-token"]',
        ];
        var i, el;
        for (i = 0; i < selectors.length; i++) {
            el = document.querySelector(selectors[i]);
            if (el) {
                return el.value || el.getAttribute('content') || '';
            }
        }
        return '';
    }

    /* ------------------------------------------------------------------ */
    /*  Reposicionar próximo de "Followup"                                 */
    /* ------------------------------------------------------------------ */
    function relocateNearFollowups(root) {
        var selectors = [
            'a[href*="ITILFollowup"]',
            '[data-bs-target*="ITILFollowup"]',
            'a[href*="Followup"]',
            'button[data-bs-target*="followup"]',
            'button[data-bs-target*="Followup"]',
        ];
        var i, node;
        for (i = 0; i < selectors.length; i++) {
            node = document.querySelector(selectors[i]);
            if (node) {
                var li = node.closest('li');
                if (li && li.parentElement) {
                    li.parentElement.insertBefore(root, li);
                    return;
                }
                var nav = node.closest('.nav-tabs, .nav, ul.navbar-nav');
                if (nav && nav.parentElement) {
                    nav.parentElement.insertBefore(root, nav);
                    return;
                }
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Montar o painel do botão dentro de #glpicopilot-root              */
    /* ------------------------------------------------------------------ */
    function mount(root) {
        if (!root || root.dataset.glpicopilotMounted === '1') {
            return;
        }
        root.dataset.glpicopilotMounted = '1';

        var ticketId = root.getAttribute('data-ticket-id');
        var base     = root.getAttribute('data-plugin-root') || '';
        if (!ticketId || !base) {
            return;
        }

        if (root.getAttribute('data-glpicopilot-no-relocate') !== '1') {
            relocateNearFollowups(root);
        }

        var labelSummarize    = root.getAttribute('data-label-summarize')    || '✨ Resumir chamado';
        var labelLoading      = root.getAttribute('data-label-loading')      || 'Gerando resumo…';
        var labelError        = root.getAttribute('data-label-error')        || 'Erro ao gerar resumo.';
        var labelNotConfigured = root.getAttribute('data-label-not-configured') || 'Plugin não configurado.';

        var labelText = String(labelSummarize).replace(/^\s*✨\s*/, '').trim();
        if (!labelText) {
            labelText = labelSummarize;
        }

        // ---- Painel ----
        var wrap = document.createElement('div');
        wrap.className = 'glpicopilot-panel';

        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'btn btn-primary answer-action glpicopilot-btn';

        var icon = document.createElement('i');
        icon.className = 'ti ti-sparkles';
        icon.setAttribute('aria-hidden', 'true');

        var span = document.createElement('span');
        span.textContent = labelText;

        btn.appendChild(icon);
        btn.appendChild(span);

        var out = document.createElement('div');
        out.className = 'glpicopilot-output alert alert-secondary mt-2 d-none';
        out.setAttribute('role', 'status');

        wrap.appendChild(btn);
        wrap.appendChild(out);
        root.appendChild(wrap);

        // ---- Ação do botão ----
        btn.addEventListener('click', function () {
            var token = getCsrfToken();
            if (!token) {
                showMessage(out, 'alert-warning', labelError);
                return;
            }

            btn.disabled = true;
            showMessage(out, 'alert-secondary', labelLoading, false);

            var fd = new FormData();
            fd.append('_glpi_csrf_token', token);
            fd.append('tickets_id',      ticketId);

            fetch(base + '/ajax/summary.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) {
                    return r.json().then(function (data) {
                        return { ok: r.ok, data: data };
                    });
                })
                .then(function (res) {
                    btn.disabled = false;

                    if (res.data && res.data.ok && res.data.summary) {
                        showMessage(out, 'alert-success', res.data.summary);
                        return;
                    }

                    var msg = labelError;
                    if (res.data && res.data.error === 'plugin_not_configured') {
                        msg = labelNotConfigured;
                    }
                    showMessage(out, 'alert-warning', msg);
                })
                .catch(function () {
                    btn.disabled = false;
                    showMessage(out, 'alert-warning', labelError);
                });
        });
    }

    function showMessage(out, cls, text, show) {
        out.className = 'glpicopilot-output alert mt-2';
        out.classList.add(cls);
        if (show !== false) {
            out.classList.remove('d-none');
        }
        out.textContent = text;
    }

    /* ------------------------------------------------------------------ */
    /*  Boot: monta quando #glpicopilot-root aparecer no DOM              */
    /* ------------------------------------------------------------------ */
    function boot() {
        var root = document.getElementById('glpicopilot-root');
        if (root) {
            mount(root);
            return;
        }

        // GLPI 11 carrega partes da página via AJAX: observa o DOM
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function () {
                var r = document.getElementById('glpicopilot-root');
                if (r) {
                    mount(r);
                    observer.disconnect();
                }
            });
            observer.observe(document.body || document.documentElement, {
                childList: true,
                subtree:   true,
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
