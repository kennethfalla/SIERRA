<?php
/** Bounded, literal-safe query parsing shared by results and suggestions. */
class SearchQuery {
    public static function clean($value): string {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', is_string($value) ? $value : '') ?? ''), 0, 160);
    }

    public static function terms(string $query): array {
        preg_match_all('/[\p{L}\p{N}*?]+/u', mb_strtolower($query), $matches);
        return array_slice(array_values(array_unique(array_filter($matches[0], function ($term) {
            return mb_strlen(str_replace(['*', '?'], '', $term)) >= 2 || ctype_digit($term);
        }))), 0, 8);
    }

    /** Common English inflections; other languages retain their literal terms. */
    public static function variants(string $term): array {
        if (strpbrk($term, '*?') !== false) return [$term];
        $groups = [
            ['run','ran','running','runs'], ['go','went','gone','going'],
            ['write','wrote','written','writing'], ['see','saw','seen','seeing'],
            ['child','children'], ['person','people'], ['foot','feet'],
        ];
        foreach ($groups as $group) if (in_array($term, $group, true)) return $group;
        $root = $term;
        if (preg_match('/^[a-z]{5,}$/', $term)) {
            if (str_ends_with($term, 'ies')) $root = substr($term, 0, -3) . 'y';
            elseif (str_ends_with($term, 'ing')) $root = substr($term, 0, -3);
            elseif (str_ends_with($term, 'ed')) $root = substr($term, 0, -2);
            elseif (preg_match('/(ches|shes|sses|xes|zes)$/', $term)) $root = substr($term, 0, -2);
            elseif (str_ends_with($term, 's') && !str_ends_with($term, 'ss')) $root = substr($term, 0, -1);
            if ($root !== $term && preg_match('/([b-df-hj-np-tv-z])\1$/', $root)) $root = substr($root, 0, -1);
        }
        $variants = [$term, $root];
        if ($root !== $term && strlen($root) >= 3) $variants[] = rtrim($root, 'e');
        if (preg_match('/^[a-z]{3,}e$/', $term)) $variants[] = substr($term, 0, -1);
        return array_values(array_unique(array_filter($variants)));
    }

    public static function pattern(string $term): string {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));
        return '%' . str_replace(['*', '?'], ['%', '_'], $escaped) . '%';
    }

    public static function condition(string $field, array $variants, array &$params): string {
        $parts = [];
        foreach ($variants as $variant) {
            $parts[] = "LOWER(COALESCE($field, '')) LIKE ? ESCAPE '!'";
            $params[] = self::pattern($variant);
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    public static function excerpt(string $body, string $query): string {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8')) ?? '');
        $start = 0;
        foreach (self::terms($query) as $term) {
            foreach (self::variants($term) as $variant) {
                $literal = preg_split('/[*?]/', $variant)[0];
                $position = $literal === '' ? false : mb_stripos($text, $literal);
                if ($position !== false) { $start = max(0, $position - 60); break 2; }
            }
        }
        return ($start ? '…' : '') . mb_substr($text, $start, 240) . (mb_strlen($text) > $start + 240 ? '…' : '');
    }
}
