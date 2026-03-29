<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot — ajax/suggest_reply.php
 * Gera uma sugestão de resposta ao requerente com base no histórico.
 * -------------------------------------------------------------------------
 */

$_glpi_root_candidates = [
    dirname(__DIR__, 3) . '/inc/includes.php',
    dirname(__DIR__, 4) . '/inc/includes.php',
];
$_glpi_loaded = false;
foreach ($_glpi_root_candidates as $_c) {
    if (is_readable($_c) && str_ends_with($_c, 'includes.php')) {
        include $_c;
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

require_once dirname(__DIR__) . '/inc/ajax_guard.inc.php';

header('Content-Type: application/json; charset=UTF-8');
Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$post = plugin_glpicopilot_merged_post();

$tickets_id = (int) ($post['tickets_id'] ?? 0);
if ($tickets_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_ticket']);
    exit;
}

$cfg = PluginGlpicopilotConfig::getConfig();
if ($cfg === [] || trim((string) ($cfg['api_key'] ?? '')) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'plugin_not_configured']);
    exit;
}

try {
    $context = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
    if (trim($context) === '') {
        throw new RuntimeException('empty_context');
    }

    $system = 'You are a senior IT support agent writing on behalf of the support team. '
        . 'Based on the ticket thread below, write a professional and empathetic reply to help the user. '
        . 'Use the SAME language as the ticket (detect it automatically). '
        . 'Write ONLY the body of the reply — no greeting like "Dear user", no closing signature, no "Best regards". '
        . 'Be concise (2–3 paragraphs). If the issue is not resolved, propose clear next steps. '
        . 'If resolved, confirm the resolution briefly.';

    $reply = PluginGlpicopilotSummarizer::callAI($cfg, $system, $context);

    echo json_encode(
        ['ok' => true, 'reply' => $reply],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    $msg  = $e->getMessage();
    $code = 'server_error';
    if ($msg === 'ticket_not_accessible') { http_response_code(403); $code = 'forbidden'; }
    elseif (in_array($msg, ['missing_plugin_config', 'unknown_provider'], true)) { http_response_code(400); $code = 'plugin_not_configured'; }
    elseif (str_starts_with($msg, 'http_')) { http_response_code(502); $code = 'upstream_error'; }
    else { http_response_code(500); }
    error_log('[glpicopilot/suggest_reply] ' . $msg);
    echo json_encode(['ok' => false, 'error' => $code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
