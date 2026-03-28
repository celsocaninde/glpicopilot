<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot — SLA, sentimento, diagnóstico, e-mail de encerramento
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

include_once dirname(__DIR__) . '/inc/ticketintel.class.php';

header('Content-Type: application/json; charset=UTF-8');
Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

Session::checkCSRF($_POST);

$action = (string) ($_POST['action'] ?? '');
$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
if ($tickets_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_ticket']);
    exit;
}

$allowed = ['sla', 'sentiment', 'diagnosis', 'closing_email'];
if (!in_array($action, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_action']);
    exit;
}

$cfg = PluginGlpicopilotConfig::getConfig();
if ($cfg === [] || trim((string) ($cfg['api_key'] ?? '')) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'plugin_not_configured']);
    exit;
}

$provider = (string) ($cfg['provider'] ?? 'azure_openai');
$endpoint = trim((string) ($cfg['endpoint_url'] ?? ''));
if ($provider === 'azure_openai' && $endpoint === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'plugin_not_configured']);
    exit;
}

try {
    switch ($action) {
        case 'sla':
            $ctx = PluginGlpicopilotTicketIntel::buildSlaUserPrompt($tickets_id);
            if (trim($ctx) === '') {
                throw new RuntimeException('empty_context');
            }
            $sys = 'You are an IT service management analyst. Given the ticket thread and GLPI SLA metadata below, '
                . 'assess whether the ticket is at risk of missing its resolution SLA. Consider urgency, priority, '
                . 'elapsed time vs targets, and recent activity. Reply in the same language as the ticket. '
                . 'Use 2–4 short paragraphs: current risk level (low/medium/high), reasoning, and recommended next actions for the technician.';
            $text = PluginGlpicopilotSummarizer::callAI($cfg, $sys, $ctx);
            echo json_encode(['ok' => true, 'text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'sentiment':
            $ctx = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
            if (trim($ctx) === '') {
                throw new RuntimeException('empty_context');
            }
            $sys = 'You classify the requester\'s emotional tone in an IT support ticket. '
                . 'Read the title, description, and follow-ups. Reply with EXACTLY one English token: '
                . 'frustrated OR neutral OR satisfied (lowercase, no punctuation).';
            $raw = strtolower(trim(PluginGlpicopilotSummarizer::callAI($cfg, $sys, $ctx)));
            $raw = preg_replace('/[^a-z]/', '', $raw);
            if (str_contains($raw, 'frustrat')) {
                $key = 'frustrated';
            } elseif (str_contains($raw, 'satisf')) {
                $key = 'satisfied';
            } else {
                $key = 'neutral';
            }
            $labels = [
                'frustrated' => __('Frustrated', 'glpicopilot'),
                'neutral'    => __('Neutral', 'glpicopilot'),
                'satisfied'  => __('Satisfied', 'glpicopilot'),
            ];
            $emoji = [
                'frustrated' => '😠',
                'neutral'    => '😐',
                'satisfied'  => '🙂',
            ];
            echo json_encode(
                [
                    'ok'    => true,
                    'key'   => $key,
                    'label' => $labels[$key] ?? $labels['neutral'],
                    'emoji' => $emoji[$key] ?? '😐',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            break;

        case 'diagnosis':
            $ctx = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
            if (trim($ctx) === '') {
                throw new RuntimeException('empty_context');
            }
            $sys = 'You help IT technicians troubleshoot. Based on the ticket below, propose 5 to 7 concise diagnostic questions '
                . 'they should ask or verify next (yes/no checks, log locations, configuration checks). '
                . 'Reply with ONLY valid JSON: an array of strings, e.g. ["Question 1?", "Question 2?"]. '
                . 'Same language as the ticket.';
            $raw = PluginGlpicopilotSummarizer::callAI($cfg, $sys, $ctx);
            $raw = trim($raw);
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/```\s*$/', '', $raw);
            $arr = json_decode($raw, true);
            $questions = [];
            if (is_array($arr)) {
                foreach ($arr as $q) {
                    if (is_string($q) && trim($q) !== '') {
                        $questions[] = trim($q);
                    }
                }
            }
            if ($questions === []) {
                foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                    $line = trim($line);
                    if ($line !== '' && $line[0] === '-') {
                        $line = ltrim($line, "- \t");
                    }
                    if ($line !== '') {
                        $questions[] = $line;
                    }
                    if (count($questions) >= 7) {
                        break;
                    }
                }
            }
            echo json_encode(['ok' => true, 'questions' => array_slice($questions, 0, 10)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        case 'closing_email':
            $ctx = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
            if (trim($ctx) === '') {
                throw new RuntimeException('empty_context');
            }
            $sys = 'You write professional IT support closing emails. Based on the ticket thread below, '
                . 'write ONE email body to send to the requester confirming progress or resolution. '
                . 'Same language as the ticket. Warm but professional, 2–4 short paragraphs. '
                . 'No subject line. Neutral greeting if names are unknown.';
            $body = PluginGlpicopilotSummarizer::callAI($cfg, $sys, $ctx);
            echo json_encode(['ok' => true, 'body' => trim($body)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_action']);
    }
} catch (Throwable $e) {
    $msg  = $e->getMessage();
    $code = 'server_error';
    if ($msg === 'ticket_not_accessible') {
        http_response_code(403);
        $code = 'forbidden';
    } elseif (in_array($msg, ['missing_plugin_config', 'unknown_provider'], true)) {
        http_response_code(400);
        $code = 'plugin_not_configured';
    } elseif (str_starts_with($msg, 'http_')) {
        http_response_code(502);
        $code = 'upstream_error';
    } else {
        http_response_code(500);
    }
    error_log('[glpicopilot/copilot] ' . $msg);
    echo json_encode(['ok' => false, 'error' => $code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
