/* global document, window, fetch, FormData, MutationObserver */
/**
 * Fallback JS: se os hooks PHP não injetarem o botão,
 * este script cria o botão diretamente na barra de ações do chamado.
 *
 * Tudo é gerado inline — sem depender de arquivos CSS/JS externos.
 */
(function () {
    'use strict';

    var MAX_TRIES = 30;
    var RETRY_MS = 300;
    var _tries = 0;
    var _observer = null;

    /* ------------------------------------------------------------------ */
    function pluginBaseUrl() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            var idx = src.indexOf('/plugins/glpicopilot/');
            if (idx !== -1) {
                return src.substring(0, idx + '/plugins/glpicopilot'.length);
            }
        }
        return '';
    }

    function getTicketId() {
        var path = window.location.pathname || '';
        var search = window.location.search || '';
        var match = path.match(/\/Ticket\/(\d+)/i);
        if (match) { return match[1]; }
        if (path.indexOf('ticket.form.php') !== -1 || path.indexOf('Ticket') !== -1) {
            var params = new URLSearchParams(search);
            var tid = params.get('id');
            if (tid && tid !== '0') { return tid; }
        }
        return null;
    }

    function getCsrf() {
        var sel = [
            'input[name="_glpi_csrf_token"]',
            'input[name="glpi_csrf_token"]',
            'meta[name="glpi-csrf-token"]'
        ];
        for (var i = 0; i < sel.length; i++) {
            var el = document.querySelector(sel[i]);
            if (el) { return el.value || el.getAttribute('content') || ''; }
        }
        return '';
    }

    /* Encontra o container da barra de ações do GLPI 11 */
    function findActionContainer() {
        // GLPI 11: classe itil-footer-actions ou answer-action no pai
        var el = document.querySelector('.itil-footer-actions');
        if (el) { return el; }

        el = document.querySelector('button.answer-action, a.answer-action, .answer-action');
        if (el && el.parentElement) { return el.parentElement; }

        // GLPI 10: lista legacy
        el = document.querySelector('ul.legacy-timeline-actions, .legacy-timeline-actions');
        if (el) { return el; }

        return null;
    }

    /* ------------------------------------------------------------------ */
    function injectStyles() {
        if (document.getElementById('glpicopilot-style')) { return; }
        var style = document.createElement('style');
        style.id = 'glpicopilot-style';
        style.textContent = [
            '.glpicopilot-btn { white-space: nowrap; margin-left: 0.5rem; }',
            '#glpicopilot-output { display:none; margin-top:0.5rem; white-space:pre-wrap; font-size:0.875rem; max-width:60ch; }'
        ].join('\n');
        document.head.appendChild(style);
    }

    function show(el, cls, txt) {
        el.className = 'alert ' + cls;
        el.style.display = 'block';
        el.textContent = txt;
    }

    /* ------------------------------------------------------------------ */
    function tryInject() {
        // Já injetado pelo PHP ou tentativa anterior
        if (document.getElementById('glpicopilot-btn')) {
            stopObserver();
            return true;
        }

        var tid = getTicketId();
        if (!tid) { return false; }

        var base = pluginBaseUrl();
        if (!base) { return false; }

        var container = findActionContainer();
        if (!container) { return false; }

        injectStyles();

        var ajaxUrl = base + '/ajax/summary.php';

        // Botão
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'glpicopilot-btn';
        btn.className = 'btn btn-outline-secondary btn-sm glpicopilot-btn';
        btn.innerHTML = '\u2728 Resumir chamado';

        // Caixa de saída
        var out = document.createElement('div');
        out.id = 'glpicopilot-output';
        out.className = 'alert alert-secondary';
        out.setAttribute('role', 'status');

        // Encapsula em <li> se for lista (GLPI 10), senão appenda direto
        if (container.tagName === 'UL') {
            var li = document.createElement('li');
            li.className = 'nav-item d-flex align-items-center ms-2 glpicopilot-timeline-item';
            li.appendChild(btn);
            li.appendChild(out);
            container.appendChild(li);
        } else {
            container.appendChild(btn);
            container.appendChild(out);
        }

        // Ação do botão
        btn.addEventListener('click', function () {
            var token = getCsrf();
            if (!token) { show(out, 'alert-warning', 'Erro: CSRF token não encontrado.'); return; }

            btn.disabled = true;
            show(out, 'alert-secondary', 'Gerando resumo\u2026');

            var fd = new FormData();
            fd.append('_glpi_csrf_token', token);
            fd.append('tickets_id', tid);

            fetch(ajaxUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    btn.disabled = false;
                    if (res.d && res.d.ok && res.d.summary) {
                        show(out, 'alert-success', res.d.summary);
                    } else if (res.d && res.d.error === 'plugin_not_configured') {
                        show(out, 'alert-warning', 'Plugin n\u00e3o configurado (URL / chave de API).');
                    } else {
                        show(out, 'alert-warning', 'N\u00e3o foi poss\u00edvel gerar o resumo.');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    show(out, 'alert-warning', 'N\u00e3o foi poss\u00edvel gerar o resumo.');
                });
        });

        stopObserver();
        return true;
    }

    /* ------------------------------------------------------------------ */
    function stopObserver() {
        if (_observer) { _observer.disconnect(); _observer = null; }
    }

    function startPolling() {
        if (_tries >= MAX_TRIES) { return; }
        _tries++;
        if (!tryInject()) { setTimeout(startPolling, RETRY_MS); }
    }

    function init() {
        if (tryInject()) { return; }

        if (typeof MutationObserver !== 'undefined') {
            _observer = new MutationObserver(function () { tryInject(); });
            _observer.observe(document.body || document.documentElement, { childList: true, subtree: true });
        }

        setTimeout(startPolling, RETRY_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
