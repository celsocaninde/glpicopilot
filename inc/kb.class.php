<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot — rascunho de KB ao resolver + e-mail de encerramento em sessão
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access directly to this file');
}

class PluginGlpicopilotKb
{
    /** @var array<int, int> tickets_id => status anterior */
    public static array $prev_status = [];

    public static function preItemUpdate(CommonDBTM $item): void
    {
        if (!$item instanceof Ticket) {
            return;
        }
        $id = (int) $item->getID();
        if ($id <= 0) {
            return;
        }
        $t = new Ticket();
        if ($t->getFromDB($id)) {
            self::$prev_status[$id] = (int) ($t->fields['status'] ?? 0);
        }
    }

    public static function itemUpdate(CommonDBTM $item): void
    {
        if (!$item instanceof Ticket) {
            return;
        }
        $id = (int) $item->getID();
        if ($id <= 0) {
            return;
        }

        $old = self::$prev_status[$id] ?? null;
        unset(self::$prev_status[$id]);

        $new = (int) ($item->fields['status'] ?? 0);
        $solved = self::statusSolved();

        if ($old === null || $new !== $solved || $old === $solved) {
            return;
        }

        if (!plugin_glpicopilot_entity_allowed($item)) {
            return;
        }

        self::runSolvedAutomation($item);
    }

    public static function statusSolved(): int
    {
        if (defined('Ticket::SOLVED')) {
            return (int) constant('Ticket::SOLVED');
        }

        return 5;
    }

    /**
     * Gera artigo KB + texto de e-mail de encerramento (sem bloquear UI: execução síncrona no request).
     */
    public static function runSolvedAutomation(Ticket $ticket): void
    {
        $cfg = PluginGlpicopilotConfig::getConfig();
        if ($cfg === [] || trim((string) ($cfg['api_key'] ?? '')) === '') {
            return;
        }

        $tickets_id = (int) $ticket->getID();
        if ($tickets_id <= 0) {
            return;
        }

        global $DB;
        $meta = self::getMetaRow($tickets_id);
        if ($meta !== null && !empty($meta['kbitems_id'])) {
            return;
        }

        try {
            $ctx = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
            if (trim($ctx) === '') {
                return;
            }

            $sysKb = 'You are a knowledge base editor. Based on the resolved IT ticket thread below, '
                . 'produce a concise knowledge article for end users. Reply with ONLY valid JSON (no markdown fence) '
                . 'with keys: "title" (string, max 120 chars), "body" (string, plain text, 2–6 short paragraphs in the same language as the ticket). '
                . 'Focus on symptoms, cause, and resolution steps. No internal jargon unless necessary.';

            $rawKb = PluginGlpicopilotSummarizer::callAI($cfg, $sysKb, $ctx);
            $data  = self::parseJsonObject($rawKb);
            $title = isset($data['title']) ? trim((string) $data['title']) : '';
            $body  = isset($data['body']) ? trim((string) $data['body']) : '';
            if ($title === '' || $body === '') {
                return;
            }

            $kbId = self::createKnowbaseDraft($ticket, $title, $body);
            if ($kbId > 0) {
                self::upsertMeta($tickets_id, $kbId);
            }

            $sysMail = 'You write professional IT support closing emails. Based on the ticket thread below, '
                . 'write ONE email body to send to the requester confirming the ticket is resolved. '
                . 'Same language as the ticket. Warm but professional, 2–4 short paragraphs. '
                . 'No subject line. No placeholder names — use neutral wording or "Olá" / "Hello" as greeting.';

            $closing = PluginGlpicopilotSummarizer::callAI($cfg, $sysMail, $ctx);
            $closing = trim($closing);
            if ($closing !== '') {
                $_SESSION['glpicopilot_pending'] ??= [];
                $_SESSION['glpicopilot_pending']['closing_email'][$tickets_id] = $closing;
            }
        } catch (Throwable $e) {
            error_log('[glpicopilot/kb] ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function getMetaRow(int $tickets_id): ?array
    {
        global $DB;
        $t = 'glpi_plugin_glpicopilot_ticketmeta';
        if (!$DB->tableExists($t)) {
            return null;
        }
        $it = $DB->request(['FROM' => $t, 'WHERE' => ['tickets_id' => $tickets_id], 'LIMIT' => 1]);
        foreach ($it as $row) {
            return $row;
        }

        return null;
    }

    private static function upsertMeta(int $tickets_id, int $kbitems_id): void
    {
        global $DB;
        $t = 'glpi_plugin_glpicopilot_ticketmeta';
        if (!$DB->tableExists($t)) {
            return;
        }
        $exists = self::getMetaRow($tickets_id);
        if ($exists === null) {
            $DB->insert($t, [
                'tickets_id' => $tickets_id,
                'kbitems_id' => $kbitems_id,
            ]);
        } else {
            $DB->update($t, ['kbitems_id' => $kbitems_id], ['tickets_id' => $tickets_id]);
        }
    }

    private static function createKnowbaseDraft(Ticket $ticket, string $title, string $body): int
    {
        if (!class_exists('KnowbaseItem')) {
            return 0;
        }
        $kb = new KnowbaseItem();

        $safe = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $input = [
            'name'                        => '[Draft AI] ' . $title,
            'answer'                      => $safe,
            'entities_id'                 => (int) ($ticket->fields['entities_id'] ?? 0),
            'users_id'                    => Session::getLoginUserID(),
            'is_faq'                      => 0,
            'knowbaseitemcategories_id'   => 0,
        ];

        $id = $kb->add($input);
        if ($id === false) {
            error_log('[glpicopilot/kb] KnowbaseItem::add failed');
        }
        if (!is_int($id) && !is_numeric($id)) {
            return 0;
        }

        return (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseJsonObject(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/```\s*$/', '', $raw);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Para mostrar alerta no formulário (consumido uma vez).
     */
    public static function consumePendingClosingEmail(int $tickets_id): ?string
    {
        if (empty($_SESSION['glpicopilot_pending']['closing_email'][$tickets_id])) {
            return null;
        }
        $txt = (string) $_SESSION['glpicopilot_pending']['closing_email'][$tickets_id];
        unset($_SESSION['glpicopilot_pending']['closing_email'][$tickets_id]);

        return $txt;
    }
}
