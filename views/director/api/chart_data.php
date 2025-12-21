<?php
// views/director/api/chart_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

$period = $_GET['period'] ?? 'month';
$surgeon_id = $_GET['surgeon_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;

$pdo = getDBConnection();
$params = [];

// --- 1. Date Filter Setup ---
$date_sql = "";
switch ($period) {
    case 'day':   $date_sql = "AND DATE(sp.date_time) = CURDATE()"; break;
    case 'week':  $date_sql = "AND YEARWEEK(sp.date_time, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'year':  $date_sql = "AND YEAR(sp.date_time) = YEAR(CURDATE())"; break;
    case 'month': default: $date_sql = "AND MONTH(sp.date_time) = MONTH(CURDATE()) AND YEAR(sp.date_time) = YEAR(CURDATE())"; break;
}

// --- 2. Dynamic Filters Setup ---
$dynamic_sql = "";

if (!empty($surgeon_id)) {
    $dynamic_sql .= " AND EXISTS (SELECT 1 FROM SURGICAL_PROCEDURE_SURGEONS sps WHERE sps.surgical_procedure_id = sp.surgical_procedure_id AND sps.surgeon_id = :surgeon_id)";
    $params[':surgeon_id'] = $surgeon_id;
}
if (!empty($room_id)) {
    $dynamic_sql .= " AND sp.operating_room_id = :room_id";
    $params[':room_id'] = $room_id;
}


// --- 3. Volume by Type Chart ---
$sql_volume = "SELECT p.procedure_name, COUNT(*) as count
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        WHERE sp.status != 'Cancelled' 
        $date_sql 
        GROUP BY p.procedure_name
        ORDER BY count DESC LIMIT 5";

$stmt_volume = $pdo->prepare($sql_volume);
$stmt_volume->execute(); 
$volume_data = $stmt_volume->fetchAll(PDO::FETCH_ASSOC);


// --- 4. Complications by Type Chart (FILTERED) ---
$sql_complications = "SELECT c.complication_name, COUNT(*) as count
                      FROM SURGICAL_PROCEDURE_COMPLICATIONS spc
                      JOIN SURGICAL_PROCEDURE sp ON spc.surgical_procedure_id = sp.surgical_procedure_id
                      JOIN COMPLICATION c ON spc.complication_id = c.complication_id
                      WHERE sp.status != 'Cancelled' $date_sql $dynamic_sql
                      GROUP BY c.complication_name
                      ORDER BY count DESC
                      LIMIT 5";

$stmt_comp = $pdo->prepare($sql_complications);
$stmt_comp->execute($params);
$comp_data = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);


// --- 5. Mortality by Complication ---
$sql_mortality = "SELECT 
                    c.complication_name, 
                    COUNT(pm.mortality_id) as count
                  FROM PATIENT_MORTALITY pm
                  JOIN COMPLICATION c ON pm.complication_id = c.complication_id 
                  JOIN SURGICAL_PROCEDURE sp ON pm.surgical_procedure_id = sp.surgical_procedure_id
                  WHERE 1=1 $date_sql $dynamic_sql
                  GROUP BY c.complication_name
                  ORDER BY count DESC
                  LIMIT 5";

$stmt_mortality = $pdo->prepare($sql_mortality);
$stmt_mortality->execute($params);
$mortality_data = $stmt_mortality->fetchAll(PDO::FETCH_ASSOC);


// --- 6. Surgeon Time Performance Analysis ---
$sql_performance = "SELECT 
                        CONCAT(s.first_name, ' ', s.surnames) as surgeon_name,
                        AVG(TIMESTAMPDIFF(MINUTE, sp.time_in_room, sp.time_out_room) - p.default_duration) as avg_deviation
                    FROM SURGICAL_PROCEDURE sp
                    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                    JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
                    JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
                    WHERE sp.status = 'Completed'
                        AND sp.time_in_room IS NOT NULL AND sp.time_out_room IS NOT NULL
                        $date_sql $dynamic_sql
                    GROUP BY surgeon_name
                    ORDER BY avg_deviation DESC
                    LIMIT 5";

$stmt_performance = $pdo->prepare($sql_performance);
$stmt_performance->execute($params);
$performance_data = $stmt_performance->fetchAll(PDO::FETCH_ASSOC);


// --- 7. NEW: Surgeon Delay Count (Metric for 'All Surgeons' View) ---
// Counts occurrences where Start > 10m Late OR End > 15m Late
$sql_s_delays = "SELECT 
                    CONCAT(s.first_name, ' ', s.surnames) as surgeon_name,
                    SUM(CASE 
                        WHEN TIMESTAMPDIFF(MINUTE, sp.date_time, sp.time_in_room) > 10 
                          OR TIMESTAMPDIFF(MINUTE, DATE_ADD(sp.date_time, INTERVAL p.default_duration MINUTE), sp.time_out_room) > 15 
                        THEN 1 
                        ELSE 0 
                    END) as delay_count
                  FROM SURGICAL_PROCEDURE sp
                  JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                  JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
                  JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
                  WHERE sp.status = 'Completed' 
                    AND sp.time_in_room IS NOT NULL 
                    AND sp.time_out_room IS NOT NULL
                    $date_sql $dynamic_sql
                  GROUP BY surgeon_name
                  HAVING delay_count > 0
                  ORDER BY delay_count DESC
                  LIMIT 5";

$stmt_s_delays = $pdo->prepare($sql_s_delays);
$stmt_s_delays->execute($params);
$surgeon_delay_data = $stmt_s_delays->fetchAll(PDO::FETCH_ASSOC);


// --- 8. NEW: Room Punctuality (Late Starts vs Late Finishes) ---
$sql_rooms = "SELECT 
                oroom.room_number,
                -- Count Late Starts (>10 mins delay from scheduled)
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, sp.date_time, sp.time_in_room) > 10 THEN 1 ELSE 0 END) as late_starts,
                -- Count Late Finishes (>15 mins overrun from expected end)
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, DATE_ADD(sp.date_time, INTERVAL p.default_duration MINUTE), sp.time_out_room) > 15 THEN 1 ELSE 0 END) as late_finishes
              FROM SURGICAL_PROCEDURE sp
              JOIN OPERATING_ROOM oroom ON sp.operating_room_id = oroom.room_id
              JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
              WHERE sp.status = 'Completed' 
                AND sp.time_in_room IS NOT NULL 
                AND sp.time_out_room IS NOT NULL
                $date_sql $dynamic_sql
              GROUP BY oroom.room_number
              ORDER BY (late_starts + late_finishes) DESC";

$stmt_rooms = $pdo->prepare($sql_rooms);
$stmt_rooms->execute($params);
$room_data = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

// Calculate rooms with at least one delay for the KPI
$rooms_with_delays = 0;
foreach($room_data as $r) {
    if ($r['late_starts'] > 0 || $r['late_finishes'] > 0) $rooms_with_delays++;
}


// --- 9. Total Metrics for Rate Calculation ---
// Total Surgeries (denominator)
$sql_total_base = "SELECT COUNT(sp.surgical_procedure_id)
                   FROM SURGICAL_PROCEDURE sp
                   WHERE sp.status != 'Cancelled' $date_sql $dynamic_sql";
$stmt_total_base = $pdo->prepare($sql_total_base);
$stmt_total_base->execute($params);
$total_surgeries_filtered = $stmt_total_base->fetchColumn();

// Total Unsuccessful Cases (Complication or Mortality)
$sql_unsuccessful = "SELECT COUNT(DISTINCT sp.surgical_procedure_id) 
                     FROM SURGICAL_PROCEDURE sp
                     LEFT JOIN SURGICAL_PROCEDURE_COMPLICATIONS spc ON sp.surgical_procedure_id = spc.surgical_procedure_id
                     LEFT JOIN PATIENT_MORTALITY pm ON sp.surgical_procedure_id = pm.surgical_procedure_id
                     WHERE sp.status != 'Cancelled' 
                       AND (spc.surgical_procedure_id IS NOT NULL OR pm.surgical_procedure_id IS NOT NULL)
                       $date_sql $dynamic_sql";
$stmt_unsuccessful = $pdo->prepare($sql_unsuccessful);
$stmt_unsuccessful->execute($params);
$total_unsuccessful_cases = $stmt_unsuccessful->fetchColumn();

// Total Cases with Complications (for rate)
$sql_comp_cases = "SELECT COUNT(DISTINCT spc.surgical_procedure_id)
                   FROM SURGICAL_PROCEDURE_COMPLICATIONS spc
                   JOIN SURGICAL_PROCEDURE sp ON spc.surgical_procedure_id = sp.surgical_procedure_id
                   WHERE sp.status != 'Cancelled' $date_sql $dynamic_sql";
$stmt_comp_cases = $pdo->prepare($sql_comp_cases);
$stmt_comp_cases->execute($params);
$total_comp_cases = $stmt_comp_cases->fetchColumn();


$complication_rate = ($total_surgeries_filtered > 0) ? ($total_comp_cases / $total_surgeries_filtered) * 100 : 0;
$success_rate = ($total_surgeries_filtered > 0) ? (($total_surgeries_filtered - $total_unsuccessful_cases) / $total_surgeries_filtered) * 100 : 0;


echo json_encode([
    'volume' => [
        'labels' => array_column($volume_data, 'procedure_name'),
        'data' => array_column($volume_data, 'count')
    ],
    'complications' => [
        'labels' => array_column($comp_data, 'complication_name'),
        'data' => array_column($comp_data, 'count')
    ],
    'mortality' => [
        'labels' => array_column($mortality_data, 'complication_name'),
        'data' => array_column($mortality_data, 'count')
    ],
    'performance' => [
        'labels' => array_column($performance_data, 'surgeon_name'),
        'data' => array_column($performance_data, 'avg_deviation')
    ],
    'surgeon_delays' => [
        'labels' => array_column($surgeon_delay_data, 'surgeon_name'),
        'data' => array_column($surgeon_delay_data, 'delay_count')
    ],
    'rooms' => [
        'labels' => array_column($room_data, 'room_number'),
        'late_starts' => array_column($room_data, 'late_starts'),
        'late_finishes' => array_column($room_data, 'late_finishes')
    ],
    'metrics' => [
        'total_surgeries_filtered' => (int)$total_surgeries_filtered,
        'complication_rate' => number_format($complication_rate, 2),
        'success_rate' => number_format($success_rate, 2),
        'unsuccessful_cases' => (int)$total_unsuccessful_cases,
        'rooms_with_delays' => $rooms_with_delays
    ]
]);
?>