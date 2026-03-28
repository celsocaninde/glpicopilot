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
        return _n('GLPI Copilot configuration', 'GLPI Copilot configuration', $nb, 'glpicopilot');
    }

    public static function getConfig(): array
    {
        global $DB;

        $it = $DB->request([
            'FROM'  => self::getTable(),
            'LIMIT' => 1,
        ]);

        foreach ($it as $row) {
            return $row;
        }

        return [];
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
                'endpoint_mode'   => 'optional_base',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (gpt-4o-mini)', 'glpicopilot')],
                    ['id' => 'gpt-4o-mini', 'label' => 'gpt-4o-mini'],
                    ['id' => 'gpt-4o', 'label' => 'gpt-4o'],
                    ['id' => 'gpt-4-turbo', 'label' => 'gpt-4-turbo'],
                    ['id' => 'gpt-3.5-turbo', 'label' => 'gpt-3.5-turbo'],
                    ['id' => 'o3-mini', 'label' => 'o3-mini'],
                    ['id' => 'o1-mini', 'label' => 'o1-mini'],
                    ['id' => 'o1', 'label' => 'o1'],
                ],
                'hint_endpoint'   => __('Optional: API base only, without /chat/completions. Example: https://api.openai.com/v1 — empty uses that default.', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list (or your saved custom id).', 'glpicopilot'),
                'default_model'   => 'gpt-4o-mini',
                'endpoint_badge'  => __('Optional', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: optional — only the API base (…/v1). The plugin appends /chat/completions. Empty: https://api.openai.com/v1', 'glpicopilot'),
                    __('Model: optional — defaults to gpt-4o-mini if empty.', 'glpicopilot'),
                    __('API key: OpenAI secret key (Authorization: Bearer).', 'glpicopilot'),
                ],
            ],
            'groq' => [
                'label'           => __('Groq', 'glpicopilot'),
                'endpoint_mode'   => 'optional_base',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (llama-3.3-70b-versatile)', 'glpicopilot')],
                    ['id' => 'llama-3.3-70b-versatile', 'label' => 'llama-3.3-70b-versatile'],
                    ['id' => 'llama-3.1-8b-instant', 'label' => 'llama-3.1-8b-instant'],
                    ['id' => 'llama-3.1-70b-versatile', 'label' => 'llama-3.1-70b-versatile'],
                    ['id' => 'mixtral-8x7b-32768', 'label' => 'mixtral-8x7b-32768'],
                    ['id' => 'gemma2-9b-it', 'label' => 'gemma2-9b-it'],
                ],
                'hint_endpoint'   => __('Optional: base URL only (…/v1). Empty: https://api.groq.com/openai/v1', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list.', 'glpicopilot'),
                'default_model'   => 'llama-3.3-70b-versatile',
                'endpoint_badge'  => __('Optional', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: optional — OpenAI-compatible base only. Empty: https://api.groq.com/openai/v1', 'glpicopilot'),
                    __('Model: optional — defaults to llama-3.3-70b-versatile if empty.', 'glpicopilot'),
                    __('API key: Groq API key (Bearer).', 'glpicopilot'),
                ],
            ],
            'grok' => [
                'label'           => __('xAI Grok', 'glpicopilot'),
                'endpoint_mode'   => 'optional_base',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (grok-2-latest)', 'glpicopilot')],
                    ['id' => 'grok-2-latest', 'label' => 'grok-2-latest'],
                    ['id' => 'grok-2-vision-latest', 'label' => 'grok-2-vision-latest'],
                    ['id' => 'grok-beta', 'label' => 'grok-beta'],
                ],
                'hint_endpoint'   => __('Optional: base URL only (…/v1). Empty: https://api.x.ai/v1', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list.', 'glpicopilot'),
                'default_model'   => 'grok-2-latest',
                'endpoint_badge'  => __('Optional', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: optional — OpenAI-compatible base only. Empty: https://api.x.ai/v1', 'glpicopilot'),
                    __('Model: optional — defaults to grok-2-latest if empty.', 'glpicopilot'),
                    __('API key: xAI API key (Bearer).', 'glpicopilot'),
                ],
            ],
            'gemini' => [
                'label'           => __('Google Gemini', 'glpicopilot'),
                'endpoint_mode'   => 'ignored',
                'model_mode'      => 'optional',
                'models'          => [
                    ['id' => '', 'label' => __('Default (gemini-1.5-flash)', 'glpicopilot')],
                    ['id' => 'gemini-2.0-flash', 'label' => 'gemini-2.0-flash'],
                    ['id' => 'gemini-2.0-flash-lite', 'label' => 'gemini-2.0-flash-lite'],
                    ['id' => 'gemini-1.5-flash', 'label' => 'gemini-1.5-flash'],
                    ['id' => 'gemini-1.5-flash-8b', 'label' => 'gemini-1.5-flash-8b'],
                    ['id' => 'gemini-1.5-pro', 'label' => 'gemini-1.5-pro'],
                ],
                'hint_endpoint'   => __('Not used: the plugin calls generativelanguage.googleapis.com (Google AI Studio / API key).', 'glpicopilot'),
                'hint_model'      => __('Pick a model from the list (Google AI Studio).', 'glpicopilot'),
                'default_model'   => 'gemini-1.5-flash',
                'endpoint_badge'  => __('Not used', 'glpicopilot'),
                'model_badge'     => __('Optional', 'glpicopilot'),
                'help_bullets'    => [
                    __('Endpoint: not used — URL is fixed for the standard Google Generative Language API; this box is ignored.', 'glpicopilot'),
                    __('Model: optional — defaults to gemini-1.5-flash if empty.', 'glpicopilot'),
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
