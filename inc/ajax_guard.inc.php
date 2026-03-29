<?php

/**
 * POST fiável nos scripts AJAX: merge Request->request com $_POST (GLPI 11 / Symfony).
 * O CheckCsrfListener para XHR valida o header X-Glpi-Csrf-Token — não duplicar Session::checkCSRF aqui.
 */
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * @return array<string, mixed>
 */
function plugin_glpicopilot_merged_post(): array
{
    $post = $_POST;
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !class_exists(\Symfony\Component\HttpFoundation\Request::class)
    ) {
        return $post;
    }

    $req = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $bag = $req->request->all();
    if (!is_array($bag) || $bag === []) {
        return $post;
    }

    return array_merge($bag, $post);
}
