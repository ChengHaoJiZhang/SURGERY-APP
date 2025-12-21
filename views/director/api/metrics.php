<?php
// views/director/api/metrics.php
header('Content-Type: application/json');

// CHANGED PATH: Go up 3 levels to find config
require_once __DIR__ . '/../../../config/config.php';

$period = $_GET['period'] ?? 'month';
$proc_filter = $_GET['proc'] ?? 'all';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'DB Connection Failed']);
    exit;
}

$params = [];

// Date Filter
$date_sql = "";
switch ($period) {
    case 'day':   $date_sql = "AND DATE(sp.date_time) = CURDATE()"; break;
    case 'week':  $date_sql = "AND YEARWEEK(sp.date_time, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'year':  $date_sql = "AND YEAR(sp.date_time) = YEAR(CURDATE())"; break;
    case 'month': 
    default:      $date_sql = "AND MONTH(sp.date_time) = MONTH(CURDATE()) AND YEAR(sp.date_time) = YEAR(CURDATE())"; break;
}

// Procedure Filter
$proc_sql = "";
if ($proc_filter !== 'all') {
    $proc_sql = "AND p.procedure_name = :proc_name";
    $params[':proc_name'] = $proc_filter;
}

// A. KPI METRICS (Total Count and Cost)
$sql_kpi = "SELECT 
                COUNT(*) as total_count,
                SUM(COALESCE(p.base_cost, 0)) as total_cost
            FROM SURGICAL_PROCEDURE sp
            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
            WHERE sp.status != 'Cancelled' 
            $date_sql $proc_sql";

$stmt = $pdo->prepare($sql_kpi);
$stmt->execute($params);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

$count = $kpi['total_count'] ?? 0;
$cost  = $kpi['total_cost'] ?? 0;
$avg   = ($count > 0) ? ($cost / $count) : 0;

// B. TOP PROCEDURE
$sql_top = "SELECT p.procedure_name
            FROM SURGICAL_PROCEDURE sp
            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
            WHERE sp.status != 'Cancelled' 
            $date_sql $proc_sql
            GROUP BY p.procedure_name
            ORDER BY COUNT(*) DESC LIMIT 1";

$stmt_top = $pdo->prepare($sql_top);
$stmt_top->execute($params);
$top_proc = $stmt_top->fetchColumn();

// C. COMPLICATION COUNT (NEW)
$sql_complications = "SELECT COUNT(DISTINCT spc.surgical_procedure_id)
                      FROM SURGICAL_PROCEDURE sp
                      JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                      JOIN SURGICAL_PROCEDURE_COMPLICATIONS spc ON sp.surgical_procedure_id = spc.surgical_procedure_id
                      WHERE sp.status != 'Cancelled' 
                      $date_sql $proc_sql";

$stmt_comp = $pdo->prepare($sql_complications);
$stmt_comp->execute($params);
$complication_count = $stmt_comp->fetchColumn();

echo json_encode([
    'count' => number_format($count),
    'cost'  => number_format($cost),
    'avg'   => number_format($avg),
    'top_proc' => $top_proc ?: 'N/A',
    'complications' => (int)$complication_count // Return as integer for easy checking
]);
?>