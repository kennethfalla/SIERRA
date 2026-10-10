<?php

/** Keep templates scoped to the action they were configured for. */
final class QuickNoteTemplates
{
    public static function forAction(array $templates, string $category, string $status, string $action): array
    {
        $matches = [];
        foreach ($templates as $template) {
            $targetCategory = trim((string)($template['target_category'] ?? ''));
            $targetStatus = trim((string)($template['target_status'] ?? ''));
            if ($targetCategory !== '' && strcasecmp($targetCategory, $category) !== 0) {
                continue;
            }
            $purpose = $targetStatus === 'resolved' ? 'resolution'
                : (in_array($targetStatus, ['escalated_pending', 'escalated'], true) ? 'escalation' : 'investigation');
            if ($purpose !== $action) {
                continue;
            }
            if ($action === 'investigation' && ($status === 'resolved' || ($targetStatus !== '' && $targetStatus !== $status))) {
                continue;
            }
            $text = trim((string)($template['template_text'] ?? ''));
            if ($text !== '') {
                $matches[] = $text;
            }
        }
        return array_values(array_unique($matches));
    }
}
