<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

// Compatibilidade GLPI 10 e 11: tenta diferentes localizações do bootstrap
$_glpi_root_candidates = [
    dirname(__DIR__, 3) . '/inc/includes.php',          // GLPI 10 (padrão)
    dirname(__DIR__, 4) . '/inc/includes.php',          // GLPI 11 (dentro de public/)
    dirname(__DIR__, 3) . '/public/index.php',          // fallback GLPI 11
];
$_glpi_loaded = false;
foreach ($_glpi_root_candidates as $_candidate) {
    if (is_readable($_candidate) && str_ends_with($_candidate, 'includes.php')) {
        include $_candidate;
        $_glpi_loaded = true;
        break;
    }
}
if (!$_glpi_loaded) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'glpi_bootstrap_not_found']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

Session::checkCSRF($_POST);

$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
if ($tickets_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_ticket']);
    exit;
}

$cfg = PluginGlpicopilotConfig::getConfig();
if ($cfg === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'plugin_not_configured']);
    exit;
}

$provider = (string) ($cfg['provider'] ?? 'azure_openai');
$endpoint = trim((string) ($cfg['endpoint_url'] ?? ''));
$apiKey   = trim((string) ($cfg['api_key'] ?? ''));

if ($apiKey === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'plugin_not_configured']);
    exit;
}

if ($provider === 'azure_openai' && $endpoint === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'plugin_not_configured']);
    exit;
}

try {
    $context = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
    if (trim($context) === '') {
        throw new RuntimeException('empty_context');
    }

    $summary = PluginGlpicopilotSummarizer::summarizeFromConfig($cfg, $context);
    echo json_encode(['ok' => true, 'summary' => $summary], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $code = 'server_error';
    $msg  = $e->getMessage();

    if ($msg === 'ticket_not_accessible') {
        http_response_code(403);
        $code = 'forbidden';
    } elseif ($msg === 'missing_plugin_config' || $msg === 'unknown_provider') {
        http_response_code(400);
        $code = 'plugin_not_configured';
    } elseif (str_starts_with($msg, 'http_')) {
        http_response_code(502);
        $code = 'upstream_error';
    } else {
        http_response_code(500);
    }

    error_log('[glpicopilot] ' . $msg);

    echo json_encode([
        'ok'    => false,
        'error' => $code,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
