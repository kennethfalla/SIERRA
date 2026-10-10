<?php
require_once dirname(__DIR__) . '/helpers/SearchQuery.php';

/** Live database search: no copied documents that can become stale. */
class Search {
    private PDO $db;
    private array $user;
    private array $permissions;
    private array $indexes = [];
    private array $context;

    public const PAGE_SCOPES = [
        'my-reports'=>['reports','My Reports'], 'verify-reports'=>['reports','Verify Reports'],
        'all-reports'=>['reports','All Reports'], 'manage-reports'=>['reports','All Reports'],
        'map'=>['reports','Map Reports'], 'analytics'=>['reports','Analytics Reports'],
        'announcements'=>['announcements','Announcements'], 'notifications'=>['notifications','Notifications'],
        'manage-users'=>['users','Users'], 'reporters-directory'=>['reporters','Reporters Directory'],
        'audit-logs'=>['audit','Audit Logs'],
    ];

    public function __construct(PDO $db, array $user, array $permissions = [], array $context = []) {
        $this->db = $db;
        $this->user = $user;
        $this->permissions = $permissions;
        $this->context = $context;
    }

    public function allowedTypes(): array {
        return array_keys($this->sources());
    }

    private function hasReports(): bool {
        return !empty($this->permissions['reports']);
    }

    private function sources(): array {
        $uid = (int)($this->user['id'] ?? 0);
        $bid = (int)($this->user['barangay_id'] ?? 0);
        $role = $this->user['role'] ?? '';
        if ($uid < 1 || !in_array($role, ['citizen','barangay_official','admin'], true)) return [];
        $sources = [];
        if ($role === 'citizen' || $this->hasReports()) {
            $scope = $role === 'citizen'
                ? 'r.user_id = ? OR EXISTS (SELECT 1 FROM report_verifications rv WHERE rv.report_id = r.id AND rv.user_id = ?)'
                : ($role === 'barangay_official' ? 'r.barangay_id = ?' : '1=1');
            $sources['reports'] = [
                'table'=>'reports', 'alias'=>'r', 'index'=>'ft_search_reports', 'index_fields'=>'title,description,location_address',
                'from'=>'reports r LEFT JOIN categories c ON c.id = r.category_id LEFT JOIN barangays b ON b.id = r.barangay_id LEFT JOIN users reporter ON reporter.id=r.user_id',
                'title'=>'r.title', 'body'=>'r.description', 'meta'=>"CONCAT(COALESCE(c.name,''), ' ', COALESCE(b.name,''), ' ', COALESCE(r.location_address,''), ' ', r.status, ' ', COALESCE(reporter.first_name,''), ' ', COALESCE(reporter.last_name,''))",
                'select'=>"r.status, r.risk_level, b.name AS location", 'scope'=>$scope,
                'params'=>$role === 'citizen' ? [$uid,$uid] : ($role === 'barangay_official' ? [$bid] : []),
            ];
        }
        $scope = 'a.is_archived = 0 AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP)';
        $params = [];
        if ($role === 'barangay_official') {
            $scope .= " AND (a.broadcast_type IN ('global_public','internal_global') OR (a.broadcast_type='localized_public' AND a.barangay_id=?) OR (a.broadcast_type='internal_direct' AND (a.target_admin_id=? OR (a.barangay_id=? AND a.target_admin_id IS NULL))))";
            $params = [$bid,$uid,$bid];
        } elseif ($role === 'citizen') {
            $resident = (int)($this->user['is_resident'] ?? ($bid ? 1 : 0));
            $scope .= $resident ? " AND (a.broadcast_type='global_public' OR (a.broadcast_type='localized_public' AND a.barangay_id=?))" : " AND a.broadcast_type='global_public'";
            if ($resident) $params[] = $bid;
        }
        $sources['announcements'] = [
            'table'=>'announcements', 'alias'=>'a', 'index'=>'ft_search_announcements', 'index_fields'=>'title,content',
            'from'=>'announcements a LEFT JOIN barangays b ON b.id=a.barangay_id', 'title'=>'a.title', 'body'=>'a.content',
            'meta'=>"COALESCE(b.name,'San Isidro')", 'select'=>"NULL AS status, NULL AS risk_level, b.name AS location",
            'scope'=>$scope, 'params'=>$params,
        ];
        $sources['notifications'] = [
            'table'=>'notifications', 'alias'=>'n', 'index'=>'ft_search_notifications', 'index_fields'=>'title,message',
            'from'=>'notifications n', 'title'=>'n.title', 'body'=>'n.message', 'meta'=>"n.type",
            'select'=>"NULL AS status, NULL AS risk_level, NULL AS location", 'scope'=>'n.user_id=?', 'params'=>[$uid],
        ];
        if ($role === 'admin' && !empty($this->permissions['users'])) {
            $sources['users'] = [
                'table'=>'users','alias'=>'u','index'=>'ft_search_users','index_fields'=>'first_name,last_name,email',
                'from'=>'users u LEFT JOIN barangays b ON b.id=u.barangay_id',
                'title'=>"CONCAT(u.first_name,' ',u.last_name)",
                'body'=>"CONCAT(COALESCE(u.email,''),' ',COALESCE(u.contact_number,''),' ',COALESCE(u.purok_street,''),' ',COALESCE(u.non_resident_address,''),' ',COALESCE(u.job_title,''))",
                'meta'=>"CONCAT(COALESCE(b.name,''),' ',COALESCE(u.user_type,'citizen'))",
                'select'=>'u.user_type AS status, NULL AS risk_level, b.name AS location', 'scope'=>'1=1', 'params'=>[],
            ];
        }
        if ($role === 'barangay_official' && $bid > 0 && !empty($this->permissions['reporters'])) {
            $sources['reporters'] = [
                'table'=>'users','alias'=>'u','index'=>'ft_search_users','index_fields'=>'first_name,last_name,email',
                'from'=>'users u LEFT JOIN barangays b ON b.id=u.barangay_id',
                'title'=>"CONCAT(u.first_name,' ',u.last_name)",
                'body'=>"CONCAT(COALESCE(u.email,''),' ',COALESCE(u.contact_number,''),' ',COALESCE(u.purok_street,''),' ',COALESCE(u.non_resident_address,''))",
                'meta'=>"COALESCE(b.name,'Non-resident')",
                'select'=>"CASE WHEN u.barangay_id=$bid AND u.is_resident=1 THEN 'residents' ELSE 'non_residents' END AS status, NULL AS risk_level, b.name AS location",
                'scope'=>"u.user_type IS NULL AND u.is_active=1 AND ((u.barangay_id=? AND u.is_resident=1) OR EXISTS (SELECT 1 FROM reports own_brgy WHERE own_brgy.user_id=u.id AND own_brgy.barangay_id=?))", 'params'=>[$bid,$bid],
            ];
        }
        if ($role === 'admin' && ($this->user['user_type'] ?? '') === 'admin' && !empty($this->permissions['audit'])) {
            $sources['audit'] = [
                'table'=>'activity_logs','alias'=>'a','index'=>'ft_search_activity','index_fields'=>'action,description,actor_name',
                'from'=>'activity_logs a', 'title'=>'a.action','body'=>'a.description',
                'meta'=>"CONCAT(COALESCE(a.actor_name,''),' ',COALESCE(a.actor_role,''),' ',COALESCE(a.target_module,''),' ',COALESCE(a.status,''))",
                'select'=>'a.status, NULL AS risk_level, NULL AS location', 'scope'=>'1=1', 'params'=>[],
            ];
        }
        $page = $this->context['page'] ?? '';
        if ($page !== '') {
            $type = self::PAGE_SCOPES[$page][0] ?? '';
            if (!isset($sources[$type])) return [];
            $sources = [$type=>$sources[$type]];
            $tab = $this->context['tab'] ?? '';
            if ($page === 'my-reports') {
                $condition = $tab === 'supported'
                    ? 'EXISTS (SELECT 1 FROM report_verifications page_rv WHERE page_rv.report_id=r.id AND page_rv.user_id=?)'
                    : 'r.user_id=?';
                $sources[$type]['scope'] = '('.$sources[$type]['scope'].') AND '.$condition;
                $sources[$type]['params'][] = $uid;
            } elseif ($page === 'manage-users') {
                $condition = $tab === 'barangay' ? "u.user_type='barangay_personnel'" : ($tab === 'menro' ? "u.user_type IN ('admin','menro_staff')" : 'u.user_type IS NULL');
                $sources[$type]['scope'] .= ' AND '.$condition;
            } elseif ($page === 'reporters-directory') {
                $resident = '(u.barangay_id=? AND u.is_resident=1)';
                $sources[$type]['scope'] .= ' AND '.($tab === 'non_residents' ? 'NOT (COALESCE(u.barangay_id,0)=? AND u.is_resident=1)' : $resident);
                $sources[$type]['params'][] = $bid;
            }
        }
        return $sources;
    }

    /** Use the native full-text index when all terms are suitable for its tokenizer. */
    private function indexedCondition(array $source, array $terms, array &$params): string {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return '';
        $boolean = [];
        $stopWords = ['about','after','again','also','because','been','before','being','between','could','from','have','into','more','most','only','other','over','same','some','such','than','that','their','them','then','there','these','they','this','those','through','under','until','very','were','what','when','where','which','while','with','would','your'];
        foreach ($terms as $term) {
            $variants = SearchQuery::variants(rtrim($term, '*'));
            foreach ($variants as $variant) {
                if (!preg_match('/^[a-z]{4,}$/', $variant) || in_array($variant, $stopWords, true)) return '';
            }
            $boolean[] = '+(' . implode(' ', array_map(fn($v)=>$v.'*', $variants)) . ')';
        }
        $table = $source['table'];
        if (!array_key_exists($table, $this->indexes)) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? AND index_type=\'FULLTEXT\'');
            $check->execute([$table,$source['index']]);
            $this->indexes[$table] = (int)$check->fetchColumn() > 0;
        }
        if (!$this->indexes[$table]) return '';
        // Metadata can match even when the body does not; retain those results.
        $fields = implode(',', array_map(fn($field)=>$source['alias'].'.'.$field, explode(',', $source['index_fields'])));
        $params[] = implode(' ', $boolean);
        return "MATCH($fields) AGAINST (? IN BOOLEAN MODE)";
    }

    public function find(string $query, string $type = 'all', int $page = 1, bool $suggest = false): array {
        $query = SearchQuery::clean($query);
        $terms = SearchQuery::terms($query);
        if (!$terms) return ['results'=>[], 'total'=>0, 'page'=>1, 'pages'=>0];
        $parts = []; $params = []; $countParts = []; $countParams = [];
        foreach ($this->sources() as $name=>$s) {
            if ($type !== 'all' && $type !== $name) continue;
            $score = []; $scoreParams = []; $where = []; $whereParams = [];
            $score[] = 'CASE WHEN LOWER('.$s['title'].') = ? THEN 120 ELSE 0 END';
            $scoreParams[] = mb_strtolower($query);
            $score[] = 'CASE WHEN '.SearchQuery::condition($s['title'], [$query], $scoreParams).' THEN 50 ELSE 0 END';
            foreach ($terms as $term) {
                $variants = SearchQuery::variants($term);
                $matches = [];
                foreach (['title'=>40,'body'=>8,'meta'=>12] as $field=>$weight) {
                    $matches[] = SearchQuery::condition($s[$field], $variants, $whereParams);
                    $score[] = 'CASE WHEN '.SearchQuery::condition($s[$field], $variants, $scoreParams)." THEN $weight ELSE 0 END";
                }
                if ($name === 'reports' && ctype_digit($term)) {
                    $matches[] = 'r.id=?'; $whereParams[] = (int)$term;
                }
                $where[] = '(' . implode(' OR ', $matches) . ')';
                if (!strpbrk($term, '*?')) {
                    // A small, capped frequency boost cannot outweigh title matches.
                    $score[] = 'CASE WHEN (LENGTH(LOWER(COALESCE('.$s['body'].",''))) - LENGTH(REPLACE(LOWER(COALESCE(".$s['body'].",'')),?,''))) / ? > 1 THEN 3 ELSE 0 END";
                    $scoreParams[] = $term; $scoreParams[] = max(1, strlen($term));
                }
            }
            $nativeParams = [];
            $native = $this->indexedCondition($s, $terms, $nativeParams);
            if ($native) {
                // Extra weight from native full-body matching; the literal query
                // also handles short words, arbitrary wildcards and metadata.
                $score[] = "CASE WHEN $native THEN 6 ELSE 0 END";
                $scoreParams = array_merge($scoreParams,$nativeParams);
            }
            $parts[] = "SELECT '$name' AS type, {$s['alias']}.id, {$s['title']} AS title, {$s['body']} AS body, {$s['select']}, {$s['alias']}.created_at, (".implode(' + ', $score).") AS relevance FROM {$s['from']} WHERE ({$s['scope']}) AND ".implode(' AND ', $where);
            $params = array_merge($params,$scoreParams,$s['params'],$whereParams);
            $countParts[] = "SELECT {$s['alias']}.id FROM {$s['from']} WHERE ({$s['scope']}) AND ".implode(' AND ', $where);
            $countParams = array_merge($countParams,$s['params'],$whereParams);
        }
        if (!$parts) return ['results'=>[], 'total'=>0, 'page'=>1, 'pages'=>0];
        $union = implode(' UNION ALL ', $parts);
        $page = $suggest ? 1 : max(1,$page); $limit = $suggest ? 6 : 15;
        $total = 0;
        if (!$suggest) {
            $count = $this->db->prepare('SELECT COUNT(*) FROM ('.implode(' UNION ALL ',$countParts).') search_results');
            $count->execute($countParams); $total = (int)$count->fetchColumn();
            $page = min($page,max(1,(int)ceil($total/$limit)));
        }
        $statement = $this->db->prepare("SELECT * FROM ($union) search_results ORDER BY relevance DESC, created_at DESC, type, id DESC LIMIT $limit OFFSET ".(($page-1)*$limit));
        $statement->execute($params);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$result) {
            $result['excerpt'] = SearchQuery::excerpt((string)$result['body'],$query);
            unset($result['body']);
        }
        return ['results'=>$results,'total'=>$suggest ? count($results) : $total,'page'=>$page,'pages'=>(int)ceil($total/$limit)];
    }
}
