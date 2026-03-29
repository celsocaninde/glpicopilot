<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access directly to this file');
}

define('PLUGIN_GLPICOPILOT_VERSION', '1.3.0');

/**
 * Define the plugin's version code
 */
function plugin_version_glpicopilot(): array
{
    return [
        'name'           => __('GLPI Copilot (multi-provider AI)', 'glpicopilot'),
        'version'        => PLUGIN_GLPICOPILOT_VERSION,
        'author'         => 'Celso Caninde',
        'license'        => 'GPLv3+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0',
                'max' => '11.99',
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * GLPI 11: não usar registerPluginStatelessPath nestes AJAX.
 * Em paths stateless o Kernel desliga cookies e não chama Session::start(); só corre initVars(),
 * pelo que glpicsrftokens não reflete a sessão real e Session::checkCSRF falha no script.
 *
 * Solução: pedidos com X-Requested-With: XMLHttpRequest + X-Glpi-Csrf-Token (CheckCsrfListener).
 */
function plugin_glpicopilot_boot(): void
{
}

/**
 * Initialize plugin hooks.
 */
function plugin_init_glpicopilot(): void
{
    global $PLUGIN_HOOKS;

    if (defined('GLPI_ROOT')) {
        $hook = GLPI_ROOT . '/plugins/glpicopilot/hook.php';
        if (is_readable($hook)) {
            include_once $hook;
            if (function_exists('plugin_glpicopilot_migrate_config_columns')) {
                plugin_glpicopilot_migrate_config_columns();
            }
        }
    }

    $PLUGIN_HOOKS['pre_item_update']['glpicopilot'] = 'plugin_glpicopilot_pre_item_update';
    $PLUGIN_HOOKS['item_update']['glpicopilot']   = 'plugin_glpicopilot_item_update';

    $PLUGIN_HOOKS['csrf_compliant']['glpicopilot'] = true;
    $PLUGIN_HOOKS['config_page']['glpicopilot']    = 'front/config.php';

    Plugin::registerClass('PluginGlpicopilotConfig');
    Plugin::registerClass('PluginGlpicopilotMenu');
    $PLUGIN_HOOKS['menu_toadd']['glpicopilot'] = [
        'tools' => 'PluginGlpicopilotMenu',
    ];

    // GLPI 10/11: timeline_actions injeta botões na barra de ações do timeline.
    $PLUGIN_HOOKS['timeline_actions']['glpicopilot'] = 'plugin_glpicopilot_timeline_actions';

    // GLPI 11: post_item_form é disparado após renderização do form do item.
    $PLUGIN_HOOKS['post_item_form']['glpicopilot'] = 'plugin_glpicopilot_post_item_form';

    // Fallback JS: injeta o botão se os hooks PHP não funcionarem (ver js/ticket_inject.js).
    // GLPI 11: add_javascript deve ser array.
    $PLUGIN_HOOKS['add_javascript']['glpicopilot'] = ['js/ticket_inject.js'];
}
