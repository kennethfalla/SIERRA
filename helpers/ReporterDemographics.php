<?php
/** Distinct reporters, using the residency field used by registration and profiles. */
class ReporterDemographics
{
    public static function summarize($db, string $where, array $params): array
    {
        $counts = ['resident' => 0, 'non_resident' => 0, 'unknown' => 0];
        $stmt = $db->prepare("SELECT u.is_resident, COUNT(DISTINCT r.user_id) AS total
            FROM reports r LEFT JOIN users u ON u.id = r.user_id
            WHERE {$where} GROUP BY u.is_resident");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $value = $row['is_resident'];
            $key = $value === null ? 'unknown' : ((int)$value === 1 ? 'resident' : 'non_resident');
            $counts[$key] += (int)$row['total'];
        }
        return $counts;
    }
}
