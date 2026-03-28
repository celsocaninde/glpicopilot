<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access directly to this file');
}

require_once __DIR__ . '/inc/ticketintel.class.php';
require_once __DIR__ . '/inc/kb.class.php';

/**
 * Converte o array de IDs de entidades (vindo do POST) em JSON para armazenar no BD.
 * Se o array estiver vazio, retorna '[]' (= sem restrição).
 *
 * @param  array<mixed> $input
 */
function plugin_glpicopilot_encode_entities(array $input): string
{
    $ids = array_values(array_unique(array_map('intval', $input)));
    return json_encode($ids, JSON_UNESCAPED_UNICODE) ?: '[]';
}


/**
 * Hook: timeline_actions
 * GLPI 10/11 — disparado dentro de CommonITILObject.
 *
 * @param array{item: \CommonITILObject, rand?: int} $params
 */
function plugin_glpicopilot_timeline_actions($params): void
{
    if (!is_array($params) || !isset($params['item'])) {
        return;
    }
    $item = $params['item'];
    if (!is_a($item, 'Ticket', true)) {
        return;
    }
    plugin_glpicopilot_try_emit_summarize($item, true);
}

/**
 * Hook: post_item_form — GLPI 11 Twig.
 *
 * @param mixed $params
 */
function plugin_glpicopilot_post_item_form($params): void
{
    $item = null;
    if ($params instanceof Ticket) {
        $item = $params;
    } elseif (is_array($params) && isset($params['item']) && is_a($params['item'], 'Ticket', true)) {
        $item = $params['item'];
    }
    if ($item === null) {
        return;
    }
    plugin_glpicopilot_try_emit_summarize($item, false);
}

/**
 * Hook: pre_item_form — fallback para versões mais antigas.
 *
 * @param mixed $params
 */
function plugin_glpicopilot_pre_item_form($params): void
{
    $item = null;
    if ($params instanceof Ticket) {
        $item = $params;
    } elseif (is_array($params) && isset($params['item']) && is_a($params['item'], 'Ticket', true)) {
        $item = $params['item'];
    }
    if ($item === null) {
        return;
    }
    plugin_glpicopilot_try_emit_summarize($item, false);
}

/**
 * Verifica se a entidade do chamado está na lista de entidades permitidas.
 * Se allowed_entities for null/vazio, o plugin está ativo em TODAS as entidades.
 */
function plugin_glpicopilot_entity_allowed(Ticket $item): bool
{
    $cfg = PluginGlpicopilotConfig::getConfig();
    $raw = trim((string) ($cfg['allowed_entities'] ?? ''));

    // Vazio / null = sem restrição (todas as entidades)
    if ($raw === '' || $raw === '[]') {
        return true;
    }

    $allowed = json_decode($raw, true);
    if (!is_array($allowed) || count($allowed) === 0) {
        return true;
    }

    $entity_id = (int) ($item->fields['entities_id'] ?? -1);
    return in_array($entity_id, array_map('intval', $allowed), true);
}

/**
 * Emite o UI apenas uma vez por request.
 *
 * @param bool $wrap_in_li true = barra inferior (<ul>); false = painel (div).
 */
function plugin_glpicopilot_try_emit_summarize(Ticket $item, bool $wrap_in_li): void
{
    $id = (int) $item->getID();
    if ($id <= 0 || !$item->canViewItem()) {
        return;
    }

    if (!plugin_glpicopilot_entity_allowed($item)) {
        return;
    }

    if (!empty($GLOBALS['glpicopilot_summarize_emitted'])) {
        return;
    }

    $GLOBALS['glpicopilot_summarize_emitted'] = true;
    plugin_glpicopilot_echo_summarize_ui($item, $wrap_in_li);
}

/**
 * Gera o HTML + CSS + JS inline do botão de resumo.
 * Tudo inline para evitar problemas de 404 com arquivos estáticos no GLPI 11.
 */
function plugin_glpicopilot_echo_summarize_ui(Ticket $item, bool $wrap_in_li): void
{
    global $CFG_GLPI;

    $id          = (int) $item->getID();
    $ajax_url    = $CFG_GLPI['root_doc'] . '/plugins/glpicopilot/ajax/summary.php';

    $label_short = htmlspecialchars(__('Resumir chamado', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $label_tiny  = htmlspecialchars(__('Resumir', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $label_load  = htmlspecialchars(__('Gerando resumo…', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $label_err   = htmlspecialchars(__('Não foi possível gerar o resumo.', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $label_cfg   = htmlspecialchars(__('Plugin não configurado (URL / chave de API).', 'glpicopilot'), ENT_QUOTES, 'UTF-8');

    $lbl_sla   = htmlspecialchars(__('Analisar SLA', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $lbl_diag  = htmlspecialchars(__('Diagnóstico', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $lbl_mail  = htmlspecialchars(__('E-mail encerramento', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $lbl_sent  = htmlspecialchars(__('Sentimento', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $lbl_cerr  = htmlspecialchars(__('Copilot: erro ao contactar a IA.', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $lbl_pend  = htmlspecialchars(__('E-mail de encerramento (gerado ao resolver)', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    $lbl_close = htmlspecialchars(__('Fechar', 'glpicopilot'), ENT_QUOTES, 'UTF-8');

    $pending_email = class_exists('PluginGlpicopilotKb', false)
        ? PluginGlpicopilotKb::consumePendingClosingEmail($id)
        : null;
    $pending_js     = json_encode($pending_email ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    $copilot_url_js = json_encode($CFG_GLPI['root_doc'] . '/plugins/glpicopilot/ajax/copilot.php', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    $label_load_js  = json_encode(__('Gerando resumo…', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $label_err_js   = json_encode(__('Não foi possível gerar o resumo.', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $label_cfg_js   = json_encode(__('Plugin não configurado (URL / chave de API).', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $lbl_cerr_js    = json_encode(__('Copilot: erro ao contactar a IA.', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $lbl_pend_js    = json_encode(__('E-mail de encerramento (gerado ao resolver)', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $label_copy_js  = json_encode(__('Copiar', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $label_copied_js = json_encode(__('Copiado!', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $lbl_stack_sla_js  = json_encode(__('SLA', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    $lbl_stack_mail_js = json_encode(__('E-mail', 'glpicopilot'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);

    // ---- CSS inline: compacto (como os outros) + violeta (cor ainda não usada na barra) ----
    echo <<<CSS
    <style id="glpicopilot-style">
    /* Barra ITIL: permite mudança de linha quando o ecrã estreita (evita “quebras” feias) */
    #itil-footer.itil-footer .buttons-bar {
        flex-wrap: wrap !important;
        row-gap: 0.5rem;
        column-gap: 0.25rem;
    }
    #itil-footer.itil-footer .buttons-bar .timeline-buttons {
        flex-wrap: wrap !important;
        align-items: center !important;
        row-gap: 0.45rem;
        min-width: 0;
    }
    #itil-footer.itil-footer ul.legacy-timeline-actions {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 0.35rem !important;
        row-gap: 0.45rem !important;
        min-width: 0;
        max-width: 100%;
        padding-left: 0;
        margin-bottom: 0;
        list-style: none;
    }

    li.glpicopilot-timeline-item {
        flex: 0 1 auto !important;
        display: flex !important;
        align-items: center !important;
        align-self: center !important;
        min-width: 0;
        max-width: 100%;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    #glpicopilot-btn.glpicopilot-btn-timeline {
        box-sizing: border-box !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center;
        gap: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        line-height: 1.5;
        white-space: nowrap;
        padding: 0.375rem 0.75rem;
        margin: 0;
        min-height: 2.375rem;
        height: auto;
        min-width: 0;
        width: auto;
        max-width: min(100%, 18rem);
        border-radius: var(--bs-border-radius, var(--tblr-border-radius, 0.375rem)) !important;
        border-width: 1px;
        border-style: solid;
        background-color: #6d28d9 !important;
        border-color: #5b21b6 !important;
        color: #fff !important;
        background-image: none !important;
        box-shadow: none !important;
    }
    #glpicopilot-btn.glpicopilot-btn-timeline:hover:not(:disabled) {
        background-color: #7c3aed !important;
        border-color: #6d28d9 !important;
        color: #fff !important;
        filter: none;
    }
    #glpicopilot-btn.glpicopilot-btn-timeline:disabled {
        opacity: 0.65;
    }
    #glpicopilot-btn.glpicopilot-btn-timeline .ti {
        flex-shrink: 0;
        font-size: 1.125rem;
        line-height: 1;
        width: 1.125rem;
        height: 1.125rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: inherit;
        opacity: 1;
    }
    #glpicopilot-btn.glpicopilot-btn-timeline .glpicopilot-btn-text {
        line-height: 1.5;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .glpicopilot-btn-text-compact {
        display: none !important;
    }
    /* Tablet / janela estreita: texto curto */
    @media (max-width: 1199.98px) {
        #glpicopilot-btn.glpicopilot-btn-timeline {
            max-width: min(100%, 12rem);
            padding: 0.35rem 0.55rem;
            font-size: 0.8125rem;
        }
        .glpicopilot-btn-text-full {
            display: none !important;
        }
        .glpicopilot-btn-text-compact {
            display: inline !important;
        }
    }
    /* Móvel / painel estreito: só ícone (o nome completo fica em aria-label) */
    @media (max-width: 575.98px) {
        #glpicopilot-btn.glpicopilot-btn-timeline {
            max-width: none;
            padding: 0.4rem 0.55rem;
        }
        .glpicopilot-btn-text {
            display: none !important;
        }
    }
    #glpicopilot-output {
        display: none;
        margin-top: 0.5rem;
        white-space: pre-wrap;
        font-size: 0.875rem;
        max-width: 60ch;
    }
    #glpicopilot-copilot-bar .btn-sm { font-size: 0.8125rem; }
    #glpicopilot-stack { max-width: min(100%, 42rem); }
    #glpicopilot-stack .alert { margin-bottom: 0.35rem; font-size: 0.8125rem; }
    #glpicopilot-stack .glpicopilot-copy-btn { margin-top: 0.35rem; }
    #glpicopilot-sentiment { max-width: 14rem; white-space: normal; text-align: left; line-height: 1.2; }
    #glpicopilot-diag-panel {
        display: none;
        position: fixed;
        top: 0; right: 0;
        width: min(100%, 22rem);
        height: 100vh;
        z-index: 1055;
        background: var(--tblr-body-bg, #fff);
        border-left: 1px solid var(--tblr-border-color, #dee2e6);
        box-shadow: -4px 0 24px rgba(0,0,0,.12);
        padding: 1rem;
        overflow: auto;
    }
    #glpicopilot-diag-panel.open { display: block; }
    #glpicopilot-diag-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1054;
        background: rgba(0,0,0,.25);
    }
    #glpicopilot-diag-backdrop.open { display: block; }
    </style>
    CSS;

    // ---- Botão HTML (ícone Tabler + texto, como Responder / Solução) ----
    $btn_html = <<<HTML
    <button type="button" id="glpicopilot-btn" class="btn glpicopilot-btn glpicopilot-btn-timeline" aria-label="{$label_short}">
        <i class="ti ti-sparkles" aria-hidden="true"></i>
        <span class="glpicopilot-btn-text">
            <span class="glpicopilot-btn-text-full">{$label_short}</span>
            <span class="glpicopilot-btn-text-compact">{$label_tiny}</span>
        </span>
    </button>
    <div id="glpicopilot-output" class="alert alert-secondary" role="status"></div>
    HTML;

    $sent_badge = '<span id="glpicopilot-sentiment" class="badge bg-secondary text-wrap">' . $lbl_sent . ': …</span>';
    $copilot_bar = '<div id="glpicopilot-copilot-bar" class="d-flex flex-wrap gap-1 align-items-center">'
        . $sent_badge
        . '<button type="button" id="glpicopilot-btn-sla" class="btn btn-sm btn-outline-primary">' . $lbl_sla . '</button>'
        . '<button type="button" id="glpicopilot-btn-diag" class="btn btn-sm btn-outline-primary">' . $lbl_diag . '</button>'
        . '<button type="button" id="glpicopilot-btn-mail" class="btn btn-sm btn-outline-primary">' . $lbl_mail . '</button>'
        . '</div>'
        . '<div id="glpicopilot-stack" class="w-100 small mt-1"></div>';

    if ($wrap_in_li) {
        echo '<li class="nav-item d-flex align-items-center ms-2 glpicopilot-timeline-item">';
        echo $btn_html;
        echo '</li>';
        echo '<li class="nav-item d-flex flex-column align-items-start ms-1 gap-1 glpicopilot-timeline-item" id="glpicopilot-copilot-li">';
        echo $copilot_bar;
        echo '</li>';
    } else {
        echo '<div id="glpicopilot-root" class="d-flex flex-column gap-1">';
        echo $btn_html;
        echo $copilot_bar;
        echo '</div>';
    }

    echo '<div id="glpicopilot-diag-backdrop" aria-hidden="true"></div>';
    echo '<aside id="glpicopilot-diag-panel" aria-label="Diagnosis">'
        . '<div class="d-flex justify-content-between align-items-center mb-2">'
        . '<strong>' . $lbl_diag . '</strong>'
        . '<button type="button" class="btn-close" id="glpicopilot-diag-close" aria-label="' . $lbl_close . '"></button>'
        . '</div><ol id="glpicopilot-diag-list" class="mb-0 ps-3"></ol></aside>';

    // ---- JS inline ----
    echo '<script id="glpicopilot-script">';
    echo '(function () {';
    echo '"use strict";';
    echo 'var AJAX_URL = ' . json_encode($ajax_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) . ';';
    echo 'var COPILOT_URL = ' . $copilot_url_js . ';';
    echo 'var PENDING_EMAIL = ' . $pending_js . ';';
    echo 'var TICKET_ID = ' . (int) $id . ';';
    echo 'var LABEL_LOAD = ' . $label_load_js . ';';
    echo 'var LABEL_ERR = ' . $label_err_js . ';';
    echo 'var LABEL_CFG = ' . $label_cfg_js . ';';
    echo 'var LABEL_CERR = ' . $lbl_cerr_js . ';';
    echo 'var LABEL_PEND = ' . $lbl_pend_js . ';';
    echo 'var LABEL_COPY = ' . $label_copy_js . ';';
    echo 'var LABEL_COPIED = ' . $label_copied_js . ';';
    echo 'var LABEL_STACK_SLA = ' . $lbl_stack_sla_js . ';';
    echo 'var LABEL_STACK_MAIL = ' . $lbl_stack_mail_js . ';';
    echo <<<'JS'
    function getCsrf() {
        var sel = [
            'input[name="_glpi_csrf_token"]',
            'input[name="glpi_csrf_token"]',
            'meta[name="glpi-csrf-token"]'
        ];
        for (var i = 0; i < sel.length; i++) {
            var el = document.querySelector(sel[i]);
            if (el) return el.value || el.getAttribute('content') || '';
        }
        return '';
    }

    function show(el, cls, txt) {
        el.className = 'alert ' + cls;
        el.style.display = 'block';
        el.textContent = txt;
    }

    function copyTextToClipboard(text, btnEl) {
        var done = function () {
            if (!btnEl) return;
            var prev = btnEl.textContent;
            btnEl.textContent = LABEL_COPIED;
            setTimeout(function () { btnEl.textContent = prev; }, 1600);
        };
        var run = function () {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                done();
            } catch (e) {}
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(run);
        } else {
            run();
        }
    }

    function stackAlert(cls, title, body, allowCopy) {
        var st = document.getElementById('glpicopilot-stack');
        if (!st) return;
        var d = document.createElement('div');
        d.className = 'alert ' + cls;
        if (title) {
            var strong = document.createElement('strong');
            strong.textContent = title + ' ';
            d.appendChild(strong);
        }
        var span = document.createElement('div');
        span.style.whiteSpace = 'pre-wrap';
        var bodyStr = body || '';
        span.textContent = bodyStr;
        d.appendChild(span);
        if (allowCopy !== false && bodyStr.length > 0) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary glpicopilot-copy-btn';
            btn.textContent = LABEL_COPY;
            btn.addEventListener('click', function () {
                copyTextToClipboard(bodyStr, btn);
            });
            d.appendChild(btn);
        }
        st.appendChild(d);
    }

    function postCopilot(action, cb) {
        var token = getCsrf();
        if (!token) { cb(null, 'csrf'); return; }
        var fd = new FormData();
        fd.append('_glpi_csrf_token', token);
        fd.append('tickets_id', String(TICKET_ID));
        fd.append('action', action);
        fetch(COPILOT_URL, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json().then(function(d) { return {ok: r.ok, d: d}; }); })
        .then(function(res) {
            if (!res.d) { cb(null, 'err'); return; }
            if (res.d.ok) { cb(res.d, null); return; }
            if (res.d.error === 'plugin_not_configured') { cb(null, 'cfg'); return; }
            cb(null, 'err');
        })
        .catch(function() { cb(null, 'err'); });
    }

    function loadSentiment() {
        var el = document.getElementById('glpicopilot-sentiment');
        if (!el) return;
        postCopilot('sentiment', function (d, err) {
            if (err && err !== 'cfg') {
                el.textContent = el.textContent.replace('…', '?');
                return;
            }
            if (d && d.emoji && d.label) {
                el.className = 'badge text-wrap ' + (d.key === 'frustrated' ? 'bg-danger' : (d.key === 'satisfied' ? 'bg-success' : 'bg-secondary'));
                el.textContent = d.emoji + ' ' + d.label;
            }
        });
    }

    function openDiag(questions) {
        var panel = document.getElementById('glpicopilot-diag-panel');
        var back = document.getElementById('glpicopilot-diag-backdrop');
        var list = document.getElementById('glpicopilot-diag-list');
        if (!panel || !list) return;
        list.innerHTML = '';
        (questions || []).forEach(function (q) {
            var li = document.createElement('li');
            li.textContent = q;
            list.appendChild(li);
        });
        panel.classList.add('open');
        if (back) back.classList.add('open');
    }

    function closeDiag() {
        var panel = document.getElementById('glpicopilot-diag-panel');
        var back = document.getElementById('glpicopilot-diag-backdrop');
        if (panel) panel.classList.remove('open');
        if (back) back.classList.remove('open');
    }

    function init() {
        var btn = document.getElementById('glpicopilot-btn');
        var out = document.getElementById('glpicopilot-output');
        if (btn && out) {
            btn.addEventListener('click', function () {
                var token = getCsrf();
                if (!token) {
                    show(out, 'alert-warning', LABEL_ERR);
                    return;
                }
                btn.disabled = true;
                show(out, 'alert-secondary', LABEL_LOAD);

                var fd = new FormData();
                fd.append('_glpi_csrf_token', token);
                fd.append('tickets_id', TICKET_ID);

                fetch(AJAX_URL, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json().then(function(d) { return {ok: r.ok, d: d}; }); })
                .then(function(res) {
                    btn.disabled = false;
                    if (res.d && res.d.ok && res.d.summary) {
                        show(out, 'alert-success', res.d.summary);
                    } else if (res.d && res.d.error === 'plugin_not_configured') {
                        show(out, 'alert-warning', LABEL_CFG);
                    } else {
                        show(out, 'alert-warning', LABEL_ERR);
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    show(out, 'alert-warning', LABEL_ERR);
                });
            });
        }

        var bSla = document.getElementById('glpicopilot-btn-sla');
        if (bSla) bSla.addEventListener('click', function () {
            bSla.disabled = true;
            postCopilot('sla', function (d, err) {
                bSla.disabled = false;
                if (err === 'cfg') stackAlert('alert-warning', '', LABEL_CFG, false);
                else if (err || !d || !d.text) stackAlert('alert-warning', '', LABEL_CERR, false);
                else stackAlert('alert-info', LABEL_STACK_SLA, d.text, true);
            });
        });

        var bDiag = document.getElementById('glpicopilot-btn-diag');
        if (bDiag) bDiag.addEventListener('click', function () {
            bDiag.disabled = true;
            postCopilot('diagnosis', function (d, err) {
                bDiag.disabled = false;
                if (err === 'cfg') stackAlert('alert-warning', '', LABEL_CFG, false);
                else if (err || !d || !d.questions) stackAlert('alert-warning', '', LABEL_CERR, false);
                else openDiag(d.questions);
            });
        });

        var bMail = document.getElementById('glpicopilot-btn-mail');
        if (bMail) bMail.addEventListener('click', function () {
            bMail.disabled = true;
            postCopilot('closing_email', function (d, err) {
                bMail.disabled = false;
                if (err === 'cfg') stackAlert('alert-warning', '', LABEL_CFG, false);
                else if (err || !d || !d.body) stackAlert('alert-warning', '', LABEL_CERR, false);
                else stackAlert('alert-success', LABEL_STACK_MAIL, d.body, true);
            });
        });

        var closeB = document.getElementById('glpicopilot-diag-close');
        var back = document.getElementById('glpicopilot-diag-backdrop');
        if (closeB) closeB.addEventListener('click', closeDiag);
        if (back) back.addEventListener('click', closeDiag);

        loadSentiment();

        if (PENDING_EMAIL) {
            stackAlert('alert-success', LABEL_PEND, PENDING_EMAIL, true);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;
    echo '</script>';
}

function plugin_glpicopilot_pre_item_update($item): void
{
    PluginGlpicopilotKb::preItemUpdate($item);
}

function plugin_glpicopilot_item_update($item): void
{
    PluginGlpicopilotKb::itemUpdate($item);
}

/**
 * Metadados por ticket (rascunho KB já criado).
 */
function plugin_glpicopilot_migrate_ticketmeta(): void
{
    global $DB;

    $t = 'glpi_plugin_glpicopilot_ticketmeta';
    if ($DB->tableExists($t)) {
        return;
    }

    $DB->doQuery(
        "CREATE TABLE `$t` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL,
            `kbitems_id` int unsigned DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tickets_id` (`tickets_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
    );
}

/**
 * Install.
 */
function plugin_glpicopilot_install(): bool
{
    global $DB;

    if (!$DB->tableExists('glpi_plugin_glpicopilot_config')) {
        $DB->doQuery(
            "CREATE TABLE `glpi_plugin_glpicopilot_config` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `provider` varchar(32) NOT NULL DEFAULT 'azure_openai',
                `endpoint_url` text DEFAULT NULL,
                `api_key` text DEFAULT NULL,
                `model` varchar(128) DEFAULT NULL,
                `allowed_entities` text DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
        );

        $DB->insert(
            'glpi_plugin_glpicopilot_config',
            [
                'id'           => 1,
                'provider'     => 'azure_openai',
                'endpoint_url' => null,
                'api_key'      => null,
                'model'        => null,
            ]
        );
    }

    plugin_glpicopilot_migrate_ticketmeta();

    return true;
}

/**
 * Adiciona colunas provider/model em instalações antigas.
 */
function plugin_glpicopilot_migrate_config_columns(): void
{
    plugin_glpicopilot_migrate_ticketmeta();

    global $DB;

    $t = 'glpi_plugin_glpicopilot_config';
    if (!$DB->tableExists($t)) {
        return;
    }

    if (method_exists($DB, 'fieldExists')) {
        if (!$DB->fieldExists($t, 'provider')) {
            $DB->doQuery(
                "ALTER TABLE `$t` ADD COLUMN `provider` varchar(32) NOT NULL DEFAULT 'azure_openai' AFTER `id`"
            );
        }
        if (!$DB->fieldExists($t, 'model')) {
            $DB->doQuery(
                "ALTER TABLE `$t` ADD COLUMN `model` varchar(128) DEFAULT NULL AFTER `api_key`"
            );
        }
        if (!$DB->fieldExists($t, 'allowed_entities')) {
            $DB->doQuery(
                "ALTER TABLE `$t` ADD COLUMN `allowed_entities` text DEFAULT NULL AFTER `model`"
            );
        }

        return;
    }
}

/**
 * Uninstall.
 */
function plugin_glpicopilot_uninstall(): bool
{
    global $DB;

    if ($DB->tableExists('glpi_plugin_glpicopilot_ticketmeta')) {
        $DB->doQuery('DROP TABLE `glpi_plugin_glpicopilot_ticketmeta`');
    }

    if ($DB->tableExists('glpi_plugin_glpicopilot_config')) {
        $DB->doQuery('DROP TABLE `glpi_plugin_glpicopilot_config`');
    }

    return true;
}

/**
 * Upgrade.
 */
function plugin_glpicopilot_upgrade($currentversion): bool
{
    global $DB;

    if (!$DB->tableExists('glpi_plugin_glpicopilot_config')) {
        return plugin_glpicopilot_install();
    }

    plugin_glpicopilot_migrate_config_columns();

    return true;
}
