<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

include '../../../inc/includes.php';

/**
 * Dados POST visíveis para scripts legacy no GLPI 11: o Kernel Symfony expõe parâmetros
 * em Request->request enquanto $_POST pode ficar vazio ou incompleto.
 *
 * @return array<string, mixed>
 */
function plugin_glpicopilot_request_post(): array
{
    $post = $_POST;
    if (!class_exists(\Symfony\Component\HttpFoundation\Request::class)) {
        return $post;
    }

    $req = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    if (!$req->isMethod('POST')) {
        return $post;
    }

    $bag = $req->request->all();
    if (!is_array($bag) || $bag === []) {
        return $post;
    }

    // $_POST prevalece em chaves repetidas (comportamento clássico do PHP)
    return array_merge($bag, $post);
}

Session::checkRight('config', UPDATE);

global $DB;

if (!$DB->tableExists(PluginGlpicopilotConfig::getTable())) {
    Html::header(__('AI Assistant', 'glpicopilot'), '', 'tools', 'PluginGlpicopilotMenu');
    echo '<div class="alert alert-danger m-3">' .
        __('Plugin tables are missing. Enable or reinstall the GLPI Copilot plugin.', 'glpicopilot') . '</div>';
    Html::footer();
    return;
}

$providers = PluginGlpicopilotConfig::getProviderDefinitions();
$allowed   = array_keys($providers);

$requestPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    ? plugin_glpicopilot_request_post()
    : $_POST;

$config = new PluginGlpicopilotConfig();
$id     = (int) ($_GET['id'] ?? 0);

if (isset($requestPost['update'])) {
    $allowedEntitiesInput = $requestPost['allowed_entities'] ?? [];
    if (!is_array($allowedEntitiesInput)) {
        $allowedEntitiesInput = trim((string) $allowedEntitiesInput);
        $allowedEntitiesInput = $allowedEntitiesInput === '' ? [] : [$allowedEntitiesInput];
    }

    $provider = (string) ($requestPost['provider'] ?? 'azure_openai');
    if (!in_array($provider, $allowed, true)) {
        $provider = 'azure_openai';
    }

    $endpoint = trim((string) ($requestPost['endpoint_url'] ?? ''));
    $model    = trim((string) ($requestPost['model'] ?? ''));
    if ($provider === 'azure_openai') {
        $model = '';
    }
    if ($provider === 'gemini') {
        $endpoint = '';
    }

    $payload = [
        'name'             => trim((string) ($requestPost['name'] ?? '')),
        'is_active'        => !empty($requestPost['is_active']) ? 1 : 0,
        'provider'         => $provider,
        'endpoint_url'     => $endpoint,
        'api_key'          => trim((string) ($requestPost['api_key'] ?? '')),
        'model'            => $model,
        'allowed_entities' => plugin_glpicopilot_encode_entities($allowedEntitiesInput),
    ];

    $record_id = (int) ($requestPost['id'] ?? 0);
    if ($record_id > 0) {
        $payload['id'] = $record_id;
        $ok = $config->update($payload);
    } else {
        $new_id = $config->add($payload);
        $ok     = $new_id !== false;
    }

    if ($ok) {
        Session::addMessageAfterRedirect(__('Configuration saved.', 'glpicopilot'));
    } else {
        Session::addMessageAfterRedirect(__('Could not save configuration.', 'glpicopilot'), true, 'error');
    }

    Html::redirect(PluginGlpicopilotConfig::getSearchURL());
}

if ($id > 0) {
    if (!$config->getFromDB($id)) {
        Html::header(__('AI Assistant', 'glpicopilot'), '', 'tools', 'PluginGlpicopilotMenu');
        echo '<div class="alert alert-warning m-3">' . __('Profile not found.', 'glpicopilot') . ' ';
        echo '<a href="' . htmlspecialchars(PluginGlpicopilotConfig::getSearchURL(), ENT_QUOTES, 'UTF-8') . '">' .
            __('Back to list', 'glpicopilot') . '</a></div>';
        Html::footer();
        return;
    }
} else {
    $config->fields = [
        'id'               => 0,
        'name'             => '',
        'is_active'        => 0,
        'provider'         => 'azure_openai',
        'endpoint_url'     => '',
        'api_key'          => '',
        'model'            => '',
        'allowed_entities' => '[]',
    ];
}

$cur_provider = (string) ($config->fields['provider'] ?? 'azure_openai');
if (!in_array($cur_provider, $allowed, true)) {
    $cur_provider = 'azure_openai';
}

$saved_entities_raw = json_decode((string) ($config->fields['allowed_entities'] ?? '[]'), true);
$saved_entities     = is_array($saved_entities_raw)
    ? array_values(array_unique(array_map('intval', $saved_entities_raw)))
    : [];

$pdef_cur = $providers[$cur_provider] ?? [];
$endpoint_saved = trim((string) ($config->fields['endpoint_url'] ?? ''));

$model_summary = '';
if (($pdef_cur['model_mode'] ?? '') === 'unused') {
    if ($endpoint_saved !== '' && preg_match('#/deployments/([^/?]+)#', $endpoint_saved, $m)) {
        $model_summary = $m[1];
    } else {
        $model_summary = __('Defined by deployment URL (Azure)', 'glpicopilot');
    }
} else {
    $model_summary = trim((string) ($config->fields['model'] ?? ''));
    if ($model_summary === '' && !empty($pdef_cur['default_model'])) {
        $model_summary = (string) $pdef_cur['default_model'];
    }
    if ($model_summary === '') {
        $model_summary = '—';
    }
}

$endpoint_summary = $endpoint_saved;
if ($endpoint_summary === '') {
    $endpoint_summary = '—';
} elseif (strlen($endpoint_summary) > 96) {
    $endpoint_summary = substr($endpoint_summary, 0, 93) . '…';
}

$api_configured = trim((string) ($config->fields['api_key'] ?? '')) !== '';

Html::header(__('AI Assistant', 'glpicopilot'), '', 'tools', 'PluginGlpicopilotMenu');

$plugin_web = Plugin::getWebDir('glpicopilot', true);
echo PluginGlpicopilotConfig::embedConfigStylesheet();

$svg_spark = '<svg class="glpicopilot-svg glpicopilot-svg--lead" width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
    . '<path d="M12 2l1.2 4.2L17 7l-3.8 1.2L12 12l-1.2-3.8L7 7l3.8-.8L12 2z" fill="currentColor" opacity=".9"/>'
    . '<path d="M19 13l.7 2.5 2.5.7-2.5.7L19 19l-.7-2.5-2.5-.7 2.5-.7L19 13z" fill="currentColor" opacity=".55"/>'
    . '</svg>';

$svg_brand = '<svg class="glpicopilot-svg glpicopilot-svg--brand" width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
    . '<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="rgba(255,255,255,.12)"/>'
    . '</svg>';

$ver = defined('PLUGIN_GLPICOPILOT_VERSION') ? PLUGIN_GLPICOPILOT_VERSION : '';

$cur_model = (string) ($config->fields['model'] ?? '');

$hints_json = [];
foreach ($providers as $key => $def) {
    $hints_json[$key] = [
        'blurb'          => $def['config_blurb'] ?? '',
        'endpoint'       => $def['hint_endpoint'],
        'model'          => $def['hint_model'],
        'default'        => $def['default_model'],
        'bullets'        => $def['help_bullets'] ?? [],
        'endpoint_badge' => $def['endpoint_badge'] ?? '',
        'model_badge'    => $def['model_badge'] ?? '',
        'endpoint_mode'  => $def['endpoint_mode'] ?? '',
        'model_mode'     => $def['model_mode'] ?? '',
        'models'         => $def['models'] ?? [],
    ];
}

$glpicopilot_config_form_strings = [
    'provider_details'  => __('Provider details (optional)', 'glpicopilot'),
    'show_optional_url' => __('Show optional base URL field', 'glpicopilot'),
];

$custom_model_lbl = __('saved value', 'glpicopilot');

// Standalone: o token global (header/meta) pode ser consumido por AJAX antes do submit.
$csrf_token = Session::getNewCSRFToken(true);

echo '<div class="container-fluid py-4 glpicopilot-config-wrap">';
echo '<div class="glpicopilot-config">';

echo '<p class="mb-3"><a class="btn btn-outline-secondary btn-sm" href="' .
    htmlspecialchars(PluginGlpicopilotConfig::getSearchURL(), ENT_QUOTES, 'UTF-8') . '"><i class="ti ti-list me-1"></i>' .
    __('Back to list', 'glpicopilot') . '</a></p>';

$profile_id = (int) ($config->fields['id'] ?? 0);
if ($profile_id > 0) {
    echo '<div class="card glpicopilot-config-summary mb-4" role="region" aria-label="' .
        htmlspecialchars(__('Current configuration', 'glpicopilot'), ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="card-header glpicopilot-config-summary__header">';
    echo '<span class="glpicopilot-config-summary__title">' . __('Current configuration', 'glpicopilot') . '</span>';
    echo '<span class="badge bg-primary glpicopilot-config-summary__badge">' . __('Read-only', 'glpicopilot') . '</span>';
    echo '</div>';
    echo '<div class="card-body glpicopilot-config-summary__body">';
    echo '<p class="glpicopilot-config-summary__lead small text-muted mb-3">' .
        __('Summary of this profile: only the profile marked as active is used on tickets and AI actions.', 'glpicopilot') .
        '</p>';
echo '<dl class="row glpicopilot-config-summary__dl mb-0">';
echo '<dt class="col-sm-4 col-lg-3">' . __('AI provider', 'glpicopilot') . '</dt>';
echo '<dd class="col-sm-8 col-lg-9">' . htmlspecialchars($pdef_cur['label'] ?? $cur_provider, ENT_QUOTES, 'UTF-8') . '</dd>';
echo '<dt class="col-sm-4 col-lg-3">' . __('Model', 'glpicopilot') . '</dt>';
echo '<dd class="col-sm-8 col-lg-9"><code class="glpicopilot-config-summary__code">' .
    htmlspecialchars($model_summary, ENT_QUOTES, 'UTF-8') . '</code></dd>';
echo '<dt class="col-sm-4 col-lg-3">' . __('Endpoint / base URL', 'glpicopilot') . '</dt>';
echo '<dd class="col-sm-8 col-lg-9"><span class="glpicopilot-config-summary__endpoint" title="' .
    htmlspecialchars(trim((string) ($config->fields['endpoint_url'] ?? '')), ENT_QUOTES, 'UTF-8') . '">' .
    htmlspecialchars($endpoint_summary, ENT_QUOTES, 'UTF-8') . '</span></dd>';
echo '<dt class="col-sm-4 col-lg-3">' . __('API key', 'glpicopilot') . '</dt>';
echo '<dd class="col-sm-8 col-lg-9">';
echo $api_configured
    ? '<span class="text-success">' . __('Configured (hidden)', 'glpicopilot') . '</span>'
    : '<span class="text-warning">' . __('Not set', 'glpicopilot') . '</span>';
echo '</dd>';
echo '<dt class="col-sm-4 col-lg-3">' . __('Ticket scope (entities)', 'glpicopilot') . '</dt>';
echo '<dd class="col-sm-8 col-lg-9">';
if ($saved_entities === []) {
    echo '<span class="glpicopilot-config-summary__scope-all">' . __('All entities', 'glpicopilot') . '</span>';
    echo '<div class="form-text mt-1 mb-0">' .
        __('AI actions are available on tickets in every entity.', 'glpicopilot') .
        '</div>';
} else {
    echo '<ul class="glpicopilot-config-summary__entity-list mb-0">';
    foreach ($saved_entities as $eid) {
        if ($eid <= 0) {
            continue;
        }
        $ename = '';
        if (class_exists('Dropdown')) {
            $ename = (string) Dropdown::getDropdownName('glpi_entities', $eid);
        }
        if ($ename === '' || $ename === '&nbsp;') {
            $ename = '#' . $eid;
        }
        echo '<li>' . htmlspecialchars($ename, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
    echo '<div class="form-text mt-1 mb-0">' .
        __('Same provider and model; only tickets in these entities show the AI controls.', 'glpicopilot') .
        '</div>';
}
echo '</dd>';
echo '</dl>';
echo '</div></div>';
}

echo '<form method="post" action="' . htmlspecialchars($plugin_web . '/front/config.form.php', ENT_QUOTES, 'UTF-8') . '" class="glpicopilot-config-form" id="glpicopilot-config-form" autocomplete="off">';
echo '<input type="hidden" name="_glpi_csrf_token" value="' . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . '" />';
echo '<input type="hidden" name="id" value="' . (int) ($config->fields['id'] ?? 0) . '" />';

echo '<div class="card glpicopilot-config-card">';
echo '<div class="card-header glpicopilot-config-header">';
echo '<div class="glpicopilot-config-header__main">';
echo '<span class="glpicopilot-config-header__icon">' . $svg_brand . '</span>';
echo '<div class="glpicopilot-config-header__text">';
echo '<span class="glpicopilot-config-eyebrow">' . __('AI integration', 'glpicopilot') . '</span>';
echo '<h2 class="card-title h5 mb-0">' . __('Ticket summaries', 'glpicopilot') . '</h2>';
echo '</div>';
echo '</div>';
echo '<span class="glpicopilot-config-badge">' . __('Multi-provider', 'glpicopilot') . '</span>';
echo '</div>';

echo '<div class="card-body">';

echo '<div class="glpicopilot-field row g-3 mb-2">';
echo '<div class="col-12 col-md-6">';
echo '<label class="form-label" for="glpicopilot_profile_name">' . __('Profile name', 'glpicopilot') . '</label>';
echo '<input type="text" class="form-control" name="name" id="glpicopilot_profile_name" maxlength="255" value="' .
    htmlspecialchars((string) ($config->fields['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '" autocomplete="off" />';
echo '</div>';
echo '<div class="col-12 col-md-6 d-flex align-items-end">';
$is_active_checked = !empty($config->fields['is_active']) ? ' checked' : '';
echo '<div class="form-check mb-2">';
echo '<input class="form-check-input" type="checkbox" name="is_active" value="1" id="glpicopilot_is_active"' . $is_active_checked . ' />';
echo '<label class="form-check-label" for="glpicopilot_is_active">' .
    __('Active for tickets (only one profile can be active)', 'glpicopilot') . '</label>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="glpicopilot-config-lead" role="note">';
echo '<span class="glpicopilot-config-lead__icon">' . $svg_spark . '</span>';
echo '<span class="glpicopilot-config-lead__txt">' .
    __('Pick a provider: fields that do not apply are hidden. Only these settings are used for ticket AI features.', 'glpicopilot') .
    '</span>';
echo '</div>';

echo '<div class="glpicopilot-field">';
echo '<label class="form-label" for="glpicopilot_provider">' . __('AI provider', 'glpicopilot') . '</label>';
echo '<select class="form-select" name="provider" id="glpicopilot_provider">';
foreach ($providers as $pkey => $pdef) {
    $sel = ($cur_provider === $pkey) ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' .
        htmlspecialchars($pdef['label'], ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select>';
echo '<p class="form-text mb-0" id="glpicopilot_provider_blurb"></p>';
echo '</div>';

echo '<details id="glpicopilot_provider_details" class="glpicopilot-provider-details mb-2">';
echo '<summary class="glpicopilot-provider-details__summary">' .
    htmlspecialchars($glpicopilot_config_form_strings['provider_details'], ENT_QUOTES, 'UTF-8') . '</summary>';
echo '<div class="glpicopilot-provider-requirements alert alert-light border mt-2" id="glpicopilot_provider_requirements" role="region" aria-label="' .
    htmlspecialchars(__('Provider requirements', 'glpicopilot'), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '</details>';

echo '<p class="glpicopilot-endpoint-reveal mb-2 glpicopilot-field--hidden" id="glpicopilot_endpoint_reveal_wrap">';
echo '<button type="button" class="btn btn-link btn-sm p-0" id="glpicopilot_endpoint_reveal_btn"></button>';
echo '</p>';

echo '<div class="glpicopilot-field" id="glpicopilot_field_endpoint">';
echo '<label class="form-label" for="endpoint_url">' . __('Endpoint / base URL', 'glpicopilot') .
    ' <span class="badge bg-secondary glpicopilot-req-badge" id="glpicopilot_badge_endpoint"></span></label>';
echo '<input type="text" class="form-control" name="endpoint_url" id="endpoint_url" value="' .
    htmlspecialchars((string) ($config->fields['endpoint_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '" ';
echo 'autocomplete="off" />';
echo '<div class="form-text" id="glpicopilot_hint_endpoint"></div>';
echo '</div>';

echo '<div class="glpicopilot-field glpicopilot-field--model" id="glpicopilot_field_model">';
echo '<label class="form-label">' . __('Model', 'glpicopilot') .
    ' <span class="badge bg-secondary glpicopilot-req-badge" id="glpicopilot_badge_model"></span></label>';
echo '<div id="glpicopilot_model_container" class="glpicopilot-model-container" data-initial-provider="' .
    htmlspecialchars($cur_provider, ENT_QUOTES, 'UTF-8') . '" data-initial-model="' .
    htmlspecialchars($cur_model, ENT_QUOTES, 'UTF-8') . '" data-custom-label="' .
    htmlspecialchars($custom_model_lbl, ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="form-text" id="glpicopilot_hint_model"></div>';
echo '</div>';

echo '<div class="glpicopilot-field">';
echo '<label class="form-label" for="api_key">' . __('API key', 'glpicopilot') . '</label>';
echo '<input type="password" class="form-control" name="api_key" id="api_key" value="' .
    htmlspecialchars((string) ($config->fields['api_key'] ?? ''), ENT_QUOTES, 'UTF-8') . '" autocomplete="off" />';
echo '<div class="form-text">' .
    __('Stored in the GLPI database. Restrict DB and backups accordingly.', 'glpicopilot') .
    '</div>';
echo '</div>';

// ---- Campo: Entidades permitidas ----
echo '<div class="glpicopilot-field">';
echo '<label class="form-label" for="glpicopilot_entities">';
echo __('Allowed entities', 'glpicopilot');
echo ' <span class="badge bg-secondary ms-1">' . __('Optional', 'glpicopilot') . '</span>';
echo '</label>';
echo '<div class="mt-1">';
Entity::dropdown([
    'name'     => 'allowed_entities',
    'multiple' => true,
    'value'    => $saved_entities,
    'width'    => '100%'
]);
echo '</div>';
echo '<div class="form-text mt-1">';
echo __(
    'Select the entities where the AI button appears on tickets. Leave empty to enable in <strong>all entities</strong>.',
    'glpicopilot'
);
echo '</div>';
echo '</div>';


echo '<script>
(function(){
var hints = ' . json_encode($hints_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
var formStrings = ' . json_encode($glpicopilot_config_form_strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
var sel = document.getElementById("glpicopilot_provider");
var blurbEl = document.getElementById("glpicopilot_provider_blurb");
var hE = document.getElementById("glpicopilot_hint_endpoint");
var hM = document.getElementById("glpicopilot_hint_model");
var inpEp = document.getElementById("endpoint_url");
var boxHelp = document.getElementById("glpicopilot_provider_requirements");
var bE = document.getElementById("glpicopilot_badge_endpoint");
var bM = document.getElementById("glpicopilot_badge_model");
var wrapEp = document.getElementById("glpicopilot_field_endpoint");
var wrapMd = document.getElementById("glpicopilot_field_model");
var revealWrap = document.getElementById("glpicopilot_endpoint_reveal_wrap");
var revealBtn = document.getElementById("glpicopilot_endpoint_reveal_btn");
var modelBox = document.getElementById("glpicopilot_model_container");
var lastModelByProvider = {};
var optionalEpExpanded = {};
var glpicopilotFirstSync = true;
function setBullets(container, bullets) {
  if (!container) return;
  container.innerHTML = "";
  if (!bullets || !bullets.length) return;
  var ul = document.createElement("ul");
  ul.className = "mb-0 small glpicopilot-provider-requirements__list";
  bullets.forEach(function (t) {
    var li = document.createElement("li");
    li.textContent = t;
    ul.appendChild(li);
  });
  container.appendChild(ul);
}
function buildModelControl(p, h) {
  if (!modelBox) return;
  if (h.model_mode === "unused") {
    modelBox.innerHTML = "<input type=\\"hidden\\" name=\\"model\\" value=\\"\\" />";
    return;
  }
  var rows = h.models || [];
  var msel = document.createElement("select");
  msel.className = "form-select";
  msel.name = "model";
  msel.id = "glpicopilot_model";
  msel.setAttribute("aria-label", "Model");
  rows.forEach(function (row) {
    var id = row.id !== undefined && row.id !== null ? String(row.id) : "";
    var opt = document.createElement("option");
    opt.value = id;
    opt.textContent = row.label || id;
    msel.appendChild(opt);
  });
  var initial = "";
  if (lastModelByProvider[p] !== undefined) {
    initial = lastModelByProvider[p];
  } else if (glpicopilotFirstSync && modelBox.dataset.initialProvider === p) {
    initial = modelBox.dataset.initialModel || "";
  }
  var have = {};
  rows.forEach(function (row) {
    var id = row.id !== undefined && row.id !== null ? String(row.id) : "";
    have[id] = true;
  });
  if (initial !== "" && !have[initial]) {
    var o = document.createElement("option");
    o.value = initial;
    var cl = modelBox.dataset.customLabel || "custom";
    o.textContent = initial + " (" + cl + ")";
    msel.appendChild(o);
    have[initial] = true;
  }
  msel.value = initial;
  if (msel.selectedIndex < 0) {
    msel.selectedIndex = 0;
  }
  lastModelByProvider[p] = msel.value;
  msel.addEventListener("change", function () {
    lastModelByProvider[p] = msel.value;
  });
  modelBox.innerHTML = "";
  modelBox.appendChild(msel);
}
function sync() {
  var p = sel && sel.value ? sel.value : "azure_openai";
  var h = hints[p] || hints.azure_openai;
  if (blurbEl) {
    blurbEl.textContent = h.blurb || "";
  }
  if (hE) hE.textContent = h.endpoint || "";
  if (hM) hM.textContent = h.model || "";
  if (bE) bE.textContent = h.endpoint_badge || "";
  if (bM) bM.textContent = h.model_badge || "";
  var epMode = h.endpoint_mode || "";
  var ignoredEp = epMode === "ignored";
  var optionalEp = epMode === "optional_base";
  var requiredEp = !ignoredEp && !optionalEp;
  var epVal = inpEp && inpEp.value ? inpEp.value.trim() : "";
  var showOptionalEp = optionalEp && (epVal !== "" || optionalEpExpanded[p] === true);
  if (wrapEp) {
    var hideEp = ignoredEp || (optionalEp && !showOptionalEp);
    wrapEp.classList.toggle("glpicopilot-field--hidden", hideEp);
    wrapEp.setAttribute("aria-hidden", hideEp ? "true" : "false");
  }
  if (revealWrap) {
    var showReveal = optionalEp && !showOptionalEp;
    revealWrap.classList.toggle("glpicopilot-field--hidden", !showReveal);
    revealWrap.setAttribute("aria-hidden", showReveal ? "false" : "true");
  }
  if (revealBtn && formStrings && formStrings.show_optional_url) {
    revealBtn.textContent = formStrings.show_optional_url;
  }
  if (wrapMd) {
    var hideMd = h.model_mode === "unused";
    wrapMd.classList.toggle("glpicopilot-field--hidden", hideMd);
    wrapMd.setAttribute("aria-hidden", hideMd ? "true" : "false");
  }
  buildModelControl(p, h);
  setBullets(boxHelp, h.bullets || []);
  glpicopilotFirstSync = false;
}
if (revealBtn) {
  revealBtn.addEventListener("click", function () {
    var p = sel && sel.value ? sel.value : "azure_openai";
    optionalEpExpanded[p] = true;
    sync();
    if (inpEp) {
      setTimeout(function () { inpEp.focus(); }, 0);
    }
  });
}
if (inpEp) {
  inpEp.addEventListener("input", function () {
    var p = sel && sel.value ? sel.value : "azure_openai";
    var h = hints[p] || hints.azure_openai;
    if ((h.endpoint_mode || "") === "optional_base") {
      var v = inpEp.value ? inpEp.value.trim() : "";
      if (v === "") {
        optionalEpExpanded[p] = false;
      }
      sync();
    }
  });
}
if (sel) {
  sel.addEventListener("change", sync);
}
sync();
})();
</script>';

echo '</div>';

echo '<div class="card-footer glpicopilot-config-footer">';
echo '<div class="glpicopilot-config-footer__meta">';
echo '<span class="glpicopilot-config-footer__name">' . __('GLPI Copilot', 'glpicopilot') . '</span>';
if ($ver !== '') {
    echo '<span class="glpicopilot-config-footer__ver">' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '</span>';
}
echo '</div>';
echo '<div class="glpicopilot-config-actions">';
echo Html::submit(__('Save', 'glpicopilot'), ['name' => 'update', 'class' => 'btn btn-primary btn-lg glpicopilot-btn-save']);
echo '</div>';
echo '</div>';

echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

Html::footer();
