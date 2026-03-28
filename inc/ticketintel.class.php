<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Copilot — contexto SLA / metadados para prompts de IA
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access directly to this file');
}

class PluginGlpicopilotTicketIntel
{
    /**
     * Bloco de texto com prazos, prioridade e estado do SLA (campos comuns do GLPI).
     */
    public static function buildSlaContextBlock(Ticket $ticket): string
    {
        $f = $ticket->fields;
        $lines = [];

        $lines[] = '--- SLA / deadlines (GLPI metadata) ---';
        $lines[] = 'ID: ' . (int) ($f['id'] ?? 0);
        $lines[] = 'Status: ' . self::fieldOrDash($f, 'status');
        $lines[] = 'Urgency: ' . self::fieldOrDash($f, 'urgency');
        $lines[] = 'Priority: ' . self::fieldOrDash($f, 'priority');
        $lines[] = 'Impact: ' . self::fieldOrDash($f, 'impact');

        foreach (
            [
                'date'                     => 'Opened',
                'date_mod'                 => 'Last change',
                'time_to_resolve'          => 'Time to resolve (minutes, target)',
                'time_to_own'              => 'Time to own (minutes, target)',
                'internal_time_to_resolve' => 'Internal time to resolve',
                'internal_time_to_own'     => 'Internal time to own',
                'due_date'                 => 'Due date',
                'sla_ttr_id'               => 'SLA TTR id',
                'sla_tto_id'               => 'SLA TTO id',
                'olas_id'                  => 'OLA id',
                'olas_tto_id'              => 'OLA TTO id',
                'olas_ttr_id'              => 'OLA TTR id',
                'solvedate'                => 'Solved at',
                'closedate'                => 'Closed at',
            ] as $key => $label
        ) {
            if (isset($f[$key]) && $f[$key] !== '' && $f[$key] !== null) {
                $lines[] = $label . ': ' . (string) $f[$key];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $f
     */
    private static function fieldOrDash(array $f, string $key): string
    {
        if (!isset($f[$key]) || $f[$key] === '' || $f[$key] === null) {
            return '—';
        }

        return (string) $f[$key];
    }

    /**
     * Prompt do utilizador: thread + bloco SLA.
     */
    public static function buildSlaUserPrompt(int $tickets_id): string
    {
        $thread = PluginGlpicopilotSummarizer::buildTicketContext($tickets_id);
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
            throw new RuntimeException('ticket_not_accessible');
        }
        $sla = self::buildSlaContextBlock($ticket);

        return $thread . "\n\n" . $sla;
    }
}
