<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

include '../../../inc/includes.php';

Session::checkRight('config', UPDATE);

$config = new PluginGlpicopilotConfig();
if (!$config->getFromDB(1)) {
    Html::header(__('GLPI Copilot', 'glpicopilot'), '', 'config', 'config');
    echo PluginGlpicopilotConfig::embedConfigStylesheet();
    echo '<div class="container-fluid py-3 glpicopilot-config">';
    echo '<div class="alert alert-warning mb-0">' .
        __('Configuration row missing. Try disabling and enabling the plugin.', 'glpicopilot') . '</div>';
    echo '</div>';
    Html::footer();
    return;
}

$providers = PluginGlpicopilotConfig::getProviderDefinitions();
$allowed   = array_keys($providers);

if (isset($_POST['update'])) {
    Session::checkCSRF($_POST);

    $provider = (string) ($_POST['provider'] ?? 'azure_openai');
    if (!in_array($provider, $allowed, true)) {
        $provider = 'azure_openai';
    }

    $endpoint = trim((string) ($_POST['endpoint_url'] ?? ''));
    $model    = trim((string) ($_POST['model'] ?? ''));
    if ($provider === 'azure_openai') {
        $model = '';
    }
    if ($provider === 'gemini') {
        $endpoint = '';
    }

    $ok = $config->update([
        'id'               => 1,
        'provider'         => $provider,
        'endpoint_url'     => $endpoint,
        'api_key'          => trim((string) ($_POST['api_key'] ?? '')),
        'model'            => $model,
        'allowed_entities' => plugin_glpicopilot_encode_entities($_POST['allowed_entities'] ?? []),
    ]);

    if ($ok) {
        Session::addMessageAfterRedirect(__('Configuration saved.', 'glpicopilot'));
    } else {
        Session::addMessageAfterRedirect(__('Could not save configuration.', 'glpicopilot'), true, 'error');
    }

    Html::back();
}

$cur_provider = (string) ($config->fields['provider'] ?? 'azure_openai');
if (!in_array($cur_provider, $allowed, true)) {
    $cur_provider = 'azure_openai';
}

Html::header(__('GLPI Copilot', 'glpicopilot'), '', 'config', 'config');

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

$custom_model_lbl = __('saved value', 'glpicopilot');

echo '<div class="container-fluid py-4 glpicopilot-config-wrap">';
echo '<div class="glpicopilot-config">';
echo '<form method="post" action="' . htmlspecialchars($plugin_web . '/front/config.form.php', ENT_QUOTES, 'UTF-8') . '" class="glpicopilot-config-form" id="glpicopilot-config-form">';

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

echo '<div class="glpicopilot-config-lead" role="note">';
echo '<span class="glpicopilot-config-lead__icon">' . $svg_spark . '</span>';
echo '<span class="glpicopilot-config-lead__txt">' .
    __('Choose a provider, API key, optional URL when needed, and a model from the list. Only used to generate ticket summaries.', 'glpicopilot') .
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
echo '<div class="form-text">' . __('OpenAI-compatible APIs use the same chat format; Gemini uses the Google Generative Language API.', 'glpicopilot') . '</div>';
echo '</div>';

echo '<div class="glpicopilot-provider-requirements alert alert-light border" id="glpicopilot_provider_requirements" role="region" aria-label="' .
    htmlspecialchars(__('Provider requirements', 'glpicopilot'), ENT_QUOTES, 'UTF-8') . '"></div>';

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
$saved_entities  = json_decode((string) ($config->fields['allowed_entities'] ?? '[]'), true);
$saved_entities  = is_array($saved_entities) ? array_map('intval', $saved_entities) : [];

echo '<div class="glpicopilot-field">';
echo '<label class="form-label" for="glpicopilot_entities">';
echo __('Entidades permitidas', 'glpicopilot');
echo ' <span class="badge bg-secondary ms-1">' . __('Opcional', 'glpicopilot') . '</span>';
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
echo __('Selecione as entidades onde o botão de IA será exibido nos chamados. Deixe em branco para ativar em <strong>todas as entidades</strong>.', 'glpicopilot');
echo '</div>';
echo '</div>';


echo '<script>
(function(){
var hints = ' . json_encode($hints_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
var sel = document.getElementById("glpicopilot_provider");
var hE = document.getElementById("glpicopilot_hint_endpoint");
var hM = document.getElementById("glpicopilot_hint_model");
var inpEp = document.getElementById("endpoint_url");
var boxHelp = document.getElementById("glpicopilot_provider_requirements");
var bE = document.getElementById("glpicopilot_badge_endpoint");
var bM = document.getElementById("glpicopilot_badge_model");
var wrapEp = document.getElementById("glpicopilot_field_endpoint");
var wrapMd = document.getElementById("glpicopilot_field_model");
var modelBox = document.getElementById("glpicopilot_model_container");
var lastModelByProvider = {};
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
  if (hE) hE.textContent = h.endpoint || "";
  if (hM) hM.textContent = h.model || "";
  if (bE) bE.textContent = h.endpoint_badge || "";
  if (bM) bM.textContent = h.model_badge || "";
  if (wrapEp) {
    var hideEp = h.endpoint_mode === "ignored";
    wrapEp.classList.toggle("glpicopilot-field--hidden", hideEp);
    wrapEp.setAttribute("aria-hidden", hideEp ? "true" : "false");
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
echo Html::submit(__('Save'), ['name' => 'update', 'class' => 'btn btn-primary btn-lg glpicopilot-btn-save']);
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo '</div>';
echo '</div>';

echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

Html::footer();
