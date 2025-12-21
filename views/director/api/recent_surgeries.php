<?php
// views/director/api/recent_surgeries.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

$period = $_GET['period'] ?? 'month';
$proc_filter = $_GET['proc'] ?? 'all';
$pdo = getDBConnection();

$params = [];
$date_sql = "";
switch ($period) {
    case 'day':   $date_sql = "AND DATE(sp.date_time) = CURDATE()"; break;
    case 'week':  $date_sql = "AND YEARWEEK(sp.date_time, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'year':  $date_sql = "AND YEAR(sp.date_time) = YEAR(CURDATE())"; break;
    case 'month': default: $date_sql = "AND MONTH(sp.date_time) = MONTH(CURDATE()) AND YEAR(sp.date_time) = YEAR(CURDATE())"; break;
}

$proc_sql = "";
if ($proc_filter !== 'all') {
    $proc_sql = "AND p.procedure_name = :proc_name";
    $params[':proc_name'] = $proc_filter;
}

$sql = "SELECT 
            sp.surgical_procedure_id,
            sp.date_time,
            p.procedure_name,
            CONCAT(s.first_name, ' ', s.surnames) as surgeon_name,
            oroom.room_number,
            COALESCE(p.base_cost, 0) as cost,
            sp.status
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        LEFT JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
        LEFT JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
        LEFT JOIN OPERATING_ROOM oroom ON sp.operating_room_id = oroom.room_id
        WHERE sp.status != 'Cancelled'
        $date_sql $proc_sql
        ORDER BY sp.date_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $row['date_formatted'] = date('d M', strtotime($row['date_time']));
    $row['time_formatted'] = date('H:i', strtotime($row['date_time']));
    $row['cost_formatted'] = number_format($row['cost']);
    
    $row['status_color'] = match($row['status']) {
        'Completed' => 'bg-success',
        'Scheduled' => 'bg-info',
        'Cancelled' => 'bg-danger',
        'In Progress' => 'bg-warning text-dark',
        default => 'bg-secondary'
    };
}

echo json_encode($rows);
?>