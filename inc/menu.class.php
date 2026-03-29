<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot — entrada no menu Ferramentas (IA Assistente)
 * -------------------------------------------------------------------------
 */

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginGlpicopilotMenu extends CommonGLPI
{
    public static function getMenuContent(): array|false
    {
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }

        return [
            'title' => __('AI Assistant', 'glpicopilot'),
            'page'  => PluginGlpicopilotConfig::getSearchURL(false),
            'icon'  => 'ti ti-sparkles',
        ];
    }
}
