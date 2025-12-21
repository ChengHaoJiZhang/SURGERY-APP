<?php
// views/director/api/financial_analysis.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

$period = $_GET['period'] ?? 'year'; 
$pdo = getDBConnection();

$params = [];
$date_filter = "";
$group_by = "";
$x_label = "";

// 1. DETERMINE TIME GROUPING LOGIC (Same as before)
switch ($period) {
    case 'day':
        $date_filter = "AND DATE(sp.date_time) = CURDATE()";
        $group_by = "HOUR(sp.date_time)";
        $x_label = "CONCAT(HOUR(sp.date_time), ':00')";
        break;
    case 'week':
        $date_filter = "AND YEARWEEK(sp.date_time, 1) = YEARWEEK(CURDATE(), 1)";
        $group_by = "DAYNAME(sp.date_time)";
        $x_label = "DAYNAME(sp.date_time)";
        break;
    case 'month':
        $date_filter = "AND MONTH(sp.date_time) = MONTH(CURDATE()) AND YEAR(sp.date_time) = YEAR(CURDATE())";
        $group_by = "WEEK(sp.date_time, 1)";
        $x_label = "CONCAT('Week ', WEEK(sp.date_time, 1) - WEEK(DATE_SUB(sp.date_time, INTERVAL DAYOFMONTH(sp.date_time)-1 DAY), 1) + 1)";
        break;
    case 'year':
    default:
        $date_filter = "AND YEAR(sp.date_time) = YEAR(CURDATE())";
        $group_by = "MONTHNAME(sp.date_time)";
        $x_label = "MONTHNAME(sp.date_time)";
        break;
}

// 2. FETCH COST DATA (Now including Additional Delay Cost)
$all_labels = [];
$all_data = []; // [procedure][time_label] = ['base'=>0, 'inventory'=>0, 'delay'=>0]

// 2a. Base Cost & Additional Delay Cost
$sql_base = "SELECT 
            $x_label as time_label,
            p.procedure_name,
            SUM(COALESCE(p.base_cost, 0)) as total_base,
            SUM(COALESCE(sp.additional_cost, 0)) as total_delay
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        WHERE sp.status != 'Cancelled' 
        $date_filter
        GROUP BY $group_by, p.procedure_name
        ORDER BY MIN(sp.date_time) ASC";

$stmt_base = $pdo->prepare($sql_base);
$stmt_base->execute($params);
$raw_base_data = $stmt_base->fetchAll(PDO::FETCH_ASSOC);

foreach ($raw_base_data as $row) {
    if (!in_array($row['time_label'], $all_labels)) {
        $all_labels[] = $row['time_label'];
    }
    $proc = $row['procedure_name'];
    $time = $row['time_label'];
    
    if (!isset($all_data[$proc])) $all_data[$proc] = [];
    if (!isset($all_data[$proc][$time])) $all_data[$proc][$time] = ['base' => 0, 'inventory' => 0, 'delay' => 0];
    
    $all_data[$proc][$time]['base'] = $row['total_base'];
    $all_data[$proc][$time]['delay'] = $row['total_delay']; // NEW
}

// 2b. Inventory Used Cost
$sql_inventory = "SELECT 
        $x_label as time_label,
        p.procedure_name,
        SUM(smu.amount_used * sm.unit_cost) as total_inventory_cost
    FROM SURGICAL_MATERIAL_USAGE smu
    JOIN SURGICAL_PROCEDURE sp ON smu.surgical_procedure_id = sp.surgical_procedure_id
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    JOIN SURGICAL_MATERIAL sm ON smu.material_id = sm.material_id
    WHERE sp.status != 'Cancelled' 
    $date_filter
    GROUP BY $group_by, p.procedure_name
    ORDER BY MIN(sp.date_time) ASC";

$stmt_inv = $pdo->prepare($sql_inventory);
$stmt_inv->execute($params);
$raw_inv_data = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

foreach ($raw_inv_data as $row) {
    $proc = $row['procedure_name'];
    $time = $row['time_label'];
    if (!isset($all_data[$proc])) $all_data[$proc] = [];
    if (!isset($all_data[$proc][$time])) $all_data[$proc][$time] = ['base' => 0, 'inventory' => 0, 'delay' => 0];
    $all_data[$proc][$time]['inventory'] = $row['total_inventory_cost'];
}

// 3. TRANSFORM FOR CHART.JS (3 Datasets per Procedure: Base, Inventory, Delay)
$datasets = [];
$colors_base = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#fd7e14', '#198754'];
$colors_inv  = ['#5fa3fd', '#9e62f5', '#9f82d0', '#ec91b5', '#fec38b', '#62b27f'];
$colors_delay = ['#0a58ca', '#520dc2', '#59359a', '#ab296a', '#ca6510', '#146c43']; // Darker shades

$proc_idx = 0;
$total_combined_cost = 0;
$total_delay_cost = 0; // New KPI accumulator

foreach ($all_data as $proc_name => $data_points) {
    $idx = $proc_idx % count($colors_base);
    
    // Dataset 1: Base Cost
    $ds_base = ['label' => "$proc_name (Base)", 'data' => [], 'backgroundColor' => $colors_base[$idx], 'stack' => "stack-$proc_idx"];
    // Dataset 2: Inventory Cost
    $ds_inv = ['label' => "$proc_name (Items)", 'data' => [], 'backgroundColor' => $colors_inv[$idx], 'stack' => "stack-$proc_idx"];
    // Dataset 3: Delay Cost (NEW)
    $ds_delay = ['label' => "$proc_name (Delay)", 'data' => [], 'backgroundColor' => '#dc3545', 'stack' => "stack-$proc_idx"]; // Red for delays

    foreach ($all_labels as $lbl) {
        $b = $data_points[$lbl]['base'] ?? 0;
        $i = $data_points[$lbl]['inventory'] ?? 0;
        $d = $data_points[$lbl]['delay'] ?? 0;
        
        $ds_base['data'][] = $b;
        $ds_inv['data'][] = $i;
        $ds_delay['data'][] = $d;
        
        $total_combined_cost += ($b + $i + $d);
        $total_delay_cost += $d;
    }
    
    $datasets[] = $ds_base;
    $datasets[] = $ds_inv;
    $datasets[] = $ds_delay;
    $proc_idx++;
}

// 4. KPIS
$sql_metrics = "SELECT 
                    COUNT(*) as count, 
                    SUM(COALESCE(p.base_cost, 0)) as total_base,
                    SUM(COALESCE(sp.additional_cost, 0)) as total_delay
                FROM SURGICAL_PROCEDURE sp 
                JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id 
                WHERE sp.status != 'Cancelled' $date_filter";
$stmt_metrics = $pdo->prepare($sql_metrics);
$stmt_metrics->execute();
$metrics = $stmt_metrics->fetch();

// Calculate operational total (Base + Delay + Inventory handled in JS via inventory API call or sum above)
// We'll pass the components separately for clarity
$avg_base_cost = ($metrics['count'] > 0) ? ($metrics['total_base'] / $metrics['count']) : 0;

echo json_encode([
    'chart' => [
        'labels' => $all_labels,
        'datasets' => $datasets
    ],
    'kpi' => [
        'avg_base_cost' => number_format($avg_base_cost, 2),
        'total_base_cost' => number_format($metrics['total_base'], 2),
        'total_delay_cost' => number_format($metrics['total_delay'], 2),
        'total_combined_cost' => number_format($total_combined_cost, 2)
    ]
]);
?>