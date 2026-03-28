<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access directly to this file');
}

class PluginGlpicopilotSummarizer
{
    private static function systemPrompt(): string
    {
        return 'You are a senior IT support analyst. Summarize the ticket thread in at most 3 short paragraphs: '
            . 'the problem, what was already tried, and the current state. Ignore email signatures, disclaimers, '
            . 'and generic greetings when they add no technical value. Use the same language as the ticket when possible.';
    }

    private static function toPlainText(string $html): string
    {
        $t = preg_replace('@<script\b[^>]*>.*?</script>@is', '', $html);
        $t = strip_tags($t);

        return trim(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Build plain-text context from ticket + follow-ups.
     */
    public static function buildTicketContext(int $tickets_id): string
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
            throw new RuntimeException('ticket_not_accessible');
        }

        $blocks = [];

        $title = self::toPlainText((string) ($ticket->fields['name'] ?? ''));
        $desc  = self::toPlainText((string) ($ticket->fields['content'] ?? ''));
        $blocks[] = '=== ' . __('Ticket', 'glpicopilot') . " #{$tickets_id} ===\n" . $title . "\n\n" . $desc;

        $followup = new ITILFollowup();
        $rows     = $followup->find(
            [
                'items_id' => $tickets_id,
                'itemtype' => Ticket::getType(),
            ],
            ['date_creation ASC', 'id ASC']
        );

        $n = 0;
        foreach ($rows as $row) {
            ++$n;
            $blocks[] = '--- ' . __('Follow-up', 'glpicopilot') . " #{$n} (" . ($row['date_creation'] ?? '') . ") ---\n"
                . self::toPlainText((string) ($row['content'] ?? ''));
        }

        return implode("\n\n", array_filter($blocks, static fn ($b) => $b !== ''));
    }

    /**
     * Resumo usando a linha de configuração (provider + URL + chave + modelo).
     *
     * @param array<string, mixed> $cfg
     */
    public static function summarizeFromConfig(array $cfg, string $user_text): string
    {
        return self::completeFromConfig($cfg, self::systemPrompt(), $user_text, 600);
    }

    /**
     * Chamada genérica (SLA, sentimento, KB, etc.) com system prompt próprio.
     *
     * @param array<string, mixed> $cfg
     */
    public static function callAI(array $cfg, string $system, string $user_text): string
    {
        return self::completeFromConfig($cfg, $system, $user_text, 1200);
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function completeFromConfig(array $cfg, string $system_prompt, string $user_text, int $max_out_tokens): string
    {
        $provider = (string) ($cfg['provider'] ?? 'azure_openai');
        $endpoint = trim((string) ($cfg['endpoint_url'] ?? ''));
        $apiKey   = trim((string) ($cfg['api_key'] ?? ''));
        $model    = trim((string) ($cfg['model'] ?? ''));

        if ($apiKey === '') {
            throw new RuntimeException('missing_plugin_config');
        }

        switch ($provider) {
            case 'azure_openai':
                if ($endpoint === '') {
                    throw new RuntimeException('missing_plugin_config');
                }

                return self::chatCompletionAzure($endpoint, $apiKey, $user_text, $system_prompt, $max_out_tokens);

            case 'openai':
                $base = $endpoint !== '' ? rtrim($endpoint, '/') : 'https://api.openai.com/v1';
                $m    = $model !== '' ? $model : 'gpt-4o-mini';

                return self::chatCompletionBearer($base . '/chat/completions', $apiKey, $m, $user_text, $system_prompt, $max_out_tokens);

            case 'groq':
                $base = $endpoint !== '' ? rtrim($endpoint, '/') : 'https://api.groq.com/openai/v1';
                $m    = $model !== '' ? $model : 'llama-3.3-70b-versatile';

                return self::chatCompletionBearer($base . '/chat/completions', $apiKey, $m, $user_text, $system_prompt, $max_out_tokens);

            case 'grok':
                $base = $endpoint !== '' ? rtrim($endpoint, '/') : 'https://api.x.ai/v1';
                $m    = $model !== '' ? $model : 'grok-2-latest';

                return self::chatCompletionBearer($base . '/chat/completions', $apiKey, $m, $user_text, $system_prompt, $max_out_tokens);

            case 'gemini':
                $m = $model !== '' ? $model : 'gemini-1.5-flash';

                return self::chatCompletionGemini($m, $apiKey, $user_text, $system_prompt, $max_out_tokens);

            default:
                throw new RuntimeException('unknown_provider');
        }
    }

    /**
     * Azure OpenAI: URL completa do deployment; cabeçalho api-key (sem Bearer).
     */
    private static function chatCompletionAzure(string $endpoint_url, string $api_key, string $user_text, string $system_prompt, int $max_tokens): string
    {
        $endpoint_url = trim($endpoint_url);
        if ($endpoint_url === '') {
            throw new RuntimeException('missing_plugin_config');
        }

        $payload = self::openAiChatPayload(null, $user_text, $system_prompt, $max_tokens);
        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('json_encode_failed');
        }

        $response = self::curlPost(
            $endpoint_url,
            [
                'Content-Type: application/json',
                'api-key: ' . $api_key,
            ],
            $body
        );

        return self::parseOpenAiChatResponse($response);
    }

    /**
     * OpenAI / Groq / Grok: Bearer + modelo no corpo.
     */
    private static function chatCompletionBearer(string $url, string $api_key, string $model, string $user_text, string $system_prompt, int $max_tokens): string
    {
        $payload = self::openAiChatPayload($model, $user_text, $system_prompt, $max_tokens);
        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('json_encode_failed');
        }

        $response = self::curlPost(
            $url,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
            ],
            $body
        );

        return self::parseOpenAiChatResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private static function openAiChatPayload(?string $model, string $user_text, string $system_prompt, int $max_tokens): array
    {
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_text],
        ];
        $payload = [
            'messages'    => $messages,
            'temperature' => 0.2,
            'max_tokens'  => max(64, min(8192, $max_tokens)),
        ];
        if ($model !== null && $model !== '') {
            $payload['model'] = $model;
        }

        return $payload;
    }

    /**
     * @return array{body: string, http: int}
     */
    private static function curlPost(string $url, array $headers, string $body): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl_required');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 90,
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('curl_error:' . (string) $errno);
        }

        if (!is_string($response)) {
            throw new RuntimeException('empty_response');
        }

        if ($http < 200 || $http >= 300) {
            throw new RuntimeException('http_' . (string) $http);
        }

        return ['body' => $response, 'http' => $http];
    }

    private static function parseOpenAiChatResponse(array $response): string
    {
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('invalid_json');
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('empty_model_output');
        }

        return trim($content);
    }

    /**
     * Google Gemini REST (generateContent).
     */
    private static function chatCompletionGemini(string $model, string $api_key, string $user_text, string $system_prompt, int $max_out_tokens): string
    {
        $model = preg_replace('/^models\//', '', $model);
        if ($model === null || $model === '') {
            throw new RuntimeException('missing_plugin_config');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . rawurlencode($api_key);

        $maxOut = max(64, min(8192, $max_out_tokens));

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $system_prompt]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $user_text]],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => $maxOut,
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('json_encode_failed');
        }

        $response = self::curlPost(
            $url,
            [
                'Content-Type: application/json',
            ],
            $body
        );

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('invalid_json');
        }

        $parts = $decoded['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts) || !isset($parts[0]['text'])) {
            throw new RuntimeException('empty_model_output');
        }

        $text = (string) $parts[0]['text'];

        return trim($text);
    }

    /**
     * @deprecated Use summarizeFromConfig()
     */
    public static function summarizeText(string $endpoint_url, string $api_key, string $user_text): string
    {
        return self::summarizeFromConfig(
            [
                'provider'     => 'azure_openai',
                'endpoint_url' => $endpoint_url,
                'api_key'      => $api_key,
                'model'        => '',
            ],
            $user_text
        );
    }
}
