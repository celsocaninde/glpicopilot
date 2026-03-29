<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot — listagem de perfis de configuração IA (Search nativo)
 * -------------------------------------------------------------------------
 */

include '../../../inc/includes.php';

Session::checkRight('config', READ);

Html::header(
    __('AI Assistant — profiles', 'glpicopilot'),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginGlpicopilotMenu'
);

if (Session::haveRight('config', UPDATE)) {
    echo '<div class="mb-3">';
    echo '<a class="btn btn-primary" href="' . htmlspecialchars(PluginGlpicopilotConfig::getFormURL() . '?id=0', ENT_QUOTES, 'UTF-8') . '">';
    echo '<i class="ti ti-plus me-1"></i>' . htmlspecialchars(__('Add profile', 'glpicopilot'), ENT_QUOTES, 'UTF-8');
    echo '</a>';
    echo '</div>';
}

Search::show('PluginGlpicopilotConfig');

Html::footer();
