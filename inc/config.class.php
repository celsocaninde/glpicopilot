<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access directly to this file');
}

class PluginGlpicopilotConfig extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_glpicopilot_config';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('AI configuration profile', 'AI configuration profiles', $nb, 'glpicopilot');
    }

    public static function getIcon()
    {
        return 'ti ti-sparkles';
    }

    public static function getFormURL($full = true)
    {
        return Plugin::getWebDir('glpicopilot', $full) . '/front/config.form.php';
    }

    public static function getSearchURL($full = true)
    {
        return Plugin::getWebDir('glpicopilot', $full) . '/front/config.php';
    }

    /**
     * Perfil usado nos tickets / AJAX: o que tem is_active = 1, senão o primeiro por id.
     *
     * @return array<string, mixed>
     */
    public static function getConfig(): array
    {
        global $DB;

        $t = self::getTable();
        if (!$DB->tableExists($t)) {
            return [];
        }

        if ($DB->fieldExists($t, 'is_active')) {
            $it = $DB->request([
                'FROM'  => $t,
                'WHERE' => ['is_active' => 1],
                'LIMIT' => 1,
            ]);
            foreach ($it as $row) {
                return $row;
            }
        }

        $it = $DB->request([
            'FROM'  => $t,
            'ORDER' => ['id ASC'],
            'LIMIT' => 1,
        ]);
        foreach ($it as $row) {
            return $row;
        }

        return [];
    }

    /**
     * Garante um único perfil ativo.
     */
    public static function deactivateOtherProfiles(int $keep_id): void
    {
        global $DB;

        $t = self::getTable();
        if (!$DB->tableExists($t) || !method_exists($DB, 'fieldExists') || !$DB->fieldExists($t, 'is_active')) {
            return;
        }

        $DB->update($t, ['is_active' => 0], ['id' => ['<>', $keep_id]]);
    }

    public function prepareInputForAdd($input)
    {
        if (!is_array($input)) {
            $input = [];
        }
        $input['name'] = trim((string) ($input['name'] ?? ''));
        if ($input['name'] === '') {
            $input['name'] = __('New profile', 'glpicopilot');
        }
        $input['is_active'] = !empty($input['is_active']) ? 1 : 0;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (!is_array($input)) {
            $input = [];
        }
        if (array_key_exists('name', $input)) {
            $input['name'] = trim((string) $input['name']);
            if ($input['name'] === '') {
                $input['name'] = __('Unnamed profile', 'glpicopilot');
            }
        }
        if (array_key_exists('is_active', $input)) {
            $input['is_active'] = !empty($input['is_active']) ? 1 : 0;
            if ($input['is_active'] === 1) {
                $kid = (int) ($input['id'] ?? $this->fields['id'] ?? 0);
                if ($kid > 0) {
                    self::deactivateOtherProfiles($kid);
                }
            }
        }

        return $input;
    }

    public function post_addItem($history = true)
    {
        if (!empty($this->fields['is_active'])) {
            self::deactivateOtherProfiles((int) $this->fields['id']);
        }
        parent::post_addItem($history);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(Session::getPluralNumber()),
        ];

        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
        ];

        $tab[] = [
            'id'              => '2',
            'table'           => $this->getTable(),
            'field'           => 'name',
            'name'            => __('Name'),
            'datatype'        => 'itemlink',
            'massiveaction'   => false,
        ];

        $tab[] = [
            'id'         => '3',
            'table'      => $this->getTable(),
            'field'      => 'is_active',
            'name'       => __('Active for tickets', 'glpicopilot'),
            'datatype'   => 'bool',
        ];

        $tab[] = [
            'id'         => '4',
            'table'      => $this->getTable(),
            'field'      => 'provider',
            'name'       => __('AI provider', 'glpicopilot'),
            'datatype'   => 'string',
        ];

        $tab[] = [
            'id'         => '5',
            'table'      => $this->getTable(),
            'field'      => 'model',
            'name'       => __('Model', 'glpicopilot'),
            'datatype'   => 'string',
        ];

        $tab[] = [
            'id'         => '6',
            'table'      => $this->getTable(),
            'field'      => 'date_mod',
            'name'       => __('Last update'),
            'datatype'   => 'datetime',
            'massiveaction' => false,
        ];

        return $tab;
    }

    /**
     * Provedores de IA suportados (só resumo de ticket).
     *
     * endpoint_mode: required = URL completa (Azure); optional = só base …/v1 (OpenAI-compat); ignored = plugin ignora o campo.
     * model_mode: unused = campo ignorado; optional = id do modelo com default no código.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getProviderDefinitions(): array
    {
        return [
            'azure_openai' => [
                'label'           => __('Microsoft Azure OpenAI', 'glpicopilot'),
                'config_blurb'    => __('Paste the full deployment URL below. The model is defined by the deployment — the model field is not used.', 'glpicopilot'),
                'endpoint_mode'   => 'required_full_url',
                'model_mode'      => 'unused',
                'hint_endpoint'   => __('Paste the full URL from Azure (must include …/chat/completions and usually api-version=…). Not a “base URL” only.', 'glpicopilot'),
                'hint_model'      => __('Ignored: the deployment in the URL defines the model.', 'glpicopilot'),
                'default_model'   => '',
                'endpoint_badge'  => __('Required', 'glpicopilot'),
                'model_badge'     => __('Not used', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: required — full deployment URL (…/deployments/{name}/chat/completions?api-version=…). Authentication uses header api-key (Azure key), not Bearer.', 'glpicopilot'),
                    __('Model: not used — leave empty.', 'glpicopilot'),
                ],
            ],
            'openai' => [
                'label'           => __('OpenAI', 'glpicopilot'),
                'config_blurb'    => __('API key and model below. The base URL field appears only if you need a custom endpoint (defaults to OpenAI).', 'glpicopilot'),
                'endpoint_mode'   => 'optional_base',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (gpt-5.4-mini)', 'glpicopilot')],
                    ['id' => 'gpt-5.4', 'label' => 'gpt-5.4'],
                    ['id' => 'gpt-5.4-mini', 'label' => 'gpt-5.4-mini'],
                    ['id' => 'gpt-5.4-nano', 'label' => 'gpt-5.4-nano'],
                    ['id' => 'gpt-5.3-codex', 'label' => 'gpt-5.3-codex'],
                    ['id' => 'gpt-5.3-instant', 'label' => 'gpt-5.3-instant'],
                    ['id' => 'o3', 'label' => 'o3'],
                    ['id' => 'o3-mini', 'label' => 'o3-mini'],
                ],
                'hint_endpoint'   => __('Optional: API base only, without /chat/completions. Example: https://api.openai.com/v1 — empty uses that default.', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list (or your saved custom id).', 'glpicopilot'),
                'default_model'   => 'gpt-5.4-mini',
                'endpoint_badge'  => __('Optional', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: optional — only the API base (…/v1). The plugin appends /chat/completions. Empty: https://api.openai.com/v1', 'glpicopilot'),
                    __('Model: optional — defaults to gpt-5.4-mini if empty (see OpenAI model docs for the latest IDs).', 'glpicopilot'),
                    __('API key: OpenAI secret key (Authorization: Bearer).', 'glpicopilot'),
                ],
            ],
            'groq' => [
                'label'           => __('Groq', 'glpicopilot'),
                'config_blurb'    => __('API key and model below. Show the base URL field only if you use a non-default Groq endpoint.', 'glpicopilot'),
                'endpoint_mode'   => 'optional_base',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (llama-3.3-70b-versatile)', 'glpicopilot')],
                    ['id' => 'llama-3.3-70b-versatile', 'label' => 'llama-3.3-70b-versatile'],
                    ['id' => 'llama-3.1-8b-instant', 'label' => 'llama-3.1-8b-instant'],
                    ['id' => 'openai/gpt-oss-120b', 'label' => 'openai/gpt-oss-120b'],
                    ['id' => 'openai/gpt-oss-20b', 'label' => 'openai/gpt-oss-20b'],
                    ['id' => 'meta-llama/llama-4-scout-17b-16e-instruct', 'label' => 'meta-llama/llama-4-scout-17b-16e-instruct'],
                    ['id' => 'qwen/qwen3-32b', 'label' => 'qwen/qwen3-32b'],
                ],
                'hint_endpoint'   => __('Optional: base URL only (…/v1). Empty: https://api.groq.com/openai/v1', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list.', 'glpicopilot'),
                'default_model'   => 'llama-3.3-70b-versatile',
                'endpoint_badge'  => __('Optional', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: optional — OpenAI-compatible base only. Empty: https://api.groq.com/openai/v1', 'glpicopilot'),
                    __('Model: optional — defaults to llama-3.3-70b-versatile; see Groq “Supported Models” for current IDs.', 'glpicopilot'),
                    __('API key: Groq API key (Bearer).', 'glpicopilot'),
                ],
            ],
            'grok' => [
                'label'           => __('xAI Grok', 'glpicopilot'),
                'config_blurb'    => __('API key and model below. Show the base URL field only if you need a custom xAI base URL.', 'glpicopilot'),
                'endpoint_mode'   => 'optional_base',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (grok-4-1-fast-non-reasoning)', 'glpicopilot')],
                    ['id' => 'grok-4-1-fast-non-reasoning', 'label' => 'grok-4-1-fast-non-reasoning'],
                    ['id' => 'grok-4-1-fast-reasoning', 'label' => 'grok-4-1-fast-reasoning'],
                    ['id' => 'grok-4.20-0309-non-reasoning', 'label' => 'grok-4.20-0309-non-reasoning'],
                    ['id' => 'grok-4.20-0309-reasoning', 'label' => 'grok-4.20-0309-reasoning'],
                    ['id' => 'grok-4.20-multi-agent-0309', 'label' => 'grok-4.20-multi-agent-0309'],
                    ['id' => 'grok-3', 'label' => 'grok-3'],
                    ['id' => 'grok-3-mini', 'label' => 'grok-3-mini'],
                ],
                'hint_endpoint'   => __('Optional: base URL only (…/v1). Empty: https://api.x.ai/v1', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list.', 'glpicopilot'),
                'default_model'   => 'grok-4-1-fast-non-reasoning',
                'endpoint_badge'  => __('Optional', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: optional — OpenAI-compatible base only. Empty: https://api.x.ai/v1', 'glpicopilot'),
                    __('Model: optional — defaults to grok-4-1-fast-non-reasoning; see xAI Models docs for the latest IDs.', 'glpicopilot'),
                    __('API key: xAI API key (Bearer).', 'glpicopilot'),
                ],
            ],
            'gemini' => [
                'label'           => __('Google Gemini', 'glpicopilot'),
                'config_blurb'    => __('Google AI Studio key and model below. No endpoint URL — requests use Google’s Generative Language API.', 'glpicopilot'),
                'endpoint_mode'   => 'ignored',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (gemini-2.5-flash)', 'glpicopilot')],
                    ['id' => 'gemini-2.5-flash', 'label' => 'gemini-2.5-flash'],
                    ['id' => 'gemini-2.5-flash-lite', 'label' => 'gemini-2.5-flash-lite'],
                    ['id' => 'gemini-2.5-pro', 'label' => 'gemini-2.5-pro'],
                    ['id' => 'gemini-3-flash-preview', 'label' => 'gemini-3-flash-preview'],
                    ['id' => 'gemini-3.1-pro-preview', 'label' => 'gemini-3.1-pro-preview'],
                    ['id' => 'gemini-3.1-flash-lite-preview', 'label' => 'gemini-3.1-flash-lite-preview'],
                ],
                'hint_endpoint'   => __('Not used: the plugin calls generativelanguage.googleapis.com (Google AI Studio / API key).', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list (Google AI Studio).', 'glpicopilot'),
                'default_model'   => 'gemini-2.5-flash',
                'endpoint_badge'  => __('Not used', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: not used — URL is fixed for the standard Google Generative Language API; this box is ignored.', 'glpicopilot'),
                    __('Model: optional — defaults to gemini-2.5-flash; preview IDs may change — check Google AI model docs.', 'glpicopilot'),
                    __('API key: Google AI Studio key (sent as query parameter key=).', 'glpicopilot'),
                ],
            ],
        ];
    }

    /**
     * Inline config page CSS from disk so styles apply even if Html::css() URL is blocked or cached wrongly.
     */
    public static function embedConfigStylesheet(): string
    {
        $path = dirname(__DIR__) . '/css/config.css';
        if (!is_readable($path)) {
            return '';
        }
        $css = file_get_contents($path);
        if ($css === false || $css === '') {
            return '';
        }

        return '<style id="glpicopilot-config-css">' . "\n" . $css . "\n" . '</style>';
    }
}
