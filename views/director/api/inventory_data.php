<?php
// groupd/view/api/inventory_data.php 

header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

try {
    $pdo = getDBConnection();
    
    // --- MODE: LIST ALL (For Modal) ---
    if (isset($_GET['action']) && $_GET['action'] === 'list_all') {
        $sql = "SELECT sm.material_name, sm.category, sm.unit_cost, mi.quantity_available, mi.location, mi.expiration_date 
                FROM SURGICAL_INVENTORY mi 
                JOIN SURGICAL_MATERIAL sm ON mi.material_id = sm.material_id 
                ORDER BY sm.material_name ASC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // --- MODE: DASHBOARD DATA ---
    $results = [];

    // Filter Setup
    $period = $_GET['period'] ?? 'month';
    $date_sql_usage = "";
    switch ($period) {
        case 'day':   $date_sql_usage = "AND DATE(smu.created_at) = CURDATE()"; break;
        case 'week':  $date_sql_usage = "AND YEARWEEK(smu.created_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
        case 'year':  $date_sql_usage = "AND YEAR(smu.created_at) = YEAR(CURDATE())"; break;
        case 'month': default: $date_sql_usage = "AND MONTH(smu.created_at) = MONTH(CURDATE()) AND YEAR(smu.created_at) = YEAR(CURDATE())"; break;
    }
    
    // 1. KPI Metrics
    $sql_kpi = "SELECT 
                    COUNT(DISTINCT mi.material_id) as total_unique_items,
                    SUM(mi.quantity_available) as total_quantity,
                    SUM(CASE WHEN mi.quantity_available < mi.minimum_stock THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(CASE WHEN mi.expiration_date < CURDATE() OR mi.expiration_date < DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as expired_count
                FROM SURGICAL_INVENTORY mi";
    $kpi = $pdo->query($sql_kpi)->fetch(PDO::FETCH_ASSOC);

    $results['kpi'] = [
        'unique_items' => number_format($kpi['total_unique_items']),
        'total_quantity' => number_format($kpi['total_quantity']),
        'low_stock' => $kpi['low_stock_count'],
        'expired' => $kpi['expired_count'] 
    ];

    // 2. Stock Distribution
    $sql_chart = "SELECT sm.category, SUM(mi.quantity_available) as total_stock
                  FROM SURGICAL_INVENTORY mi
                  JOIN SURGICAL_MATERIAL sm ON mi.material_id = sm.material_id
                  GROUP BY sm.category ORDER BY total_stock DESC";
    $chart_data = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
    $results['chart'] = [ 'labels' => array_column($chart_data, 'category'), 'data' => array_column($chart_data, 'total_stock') ];

    // 3. Usage Metrics
    $sql_usage_chart = "SELECT sm.material_name, SUM(smu.amount_used) AS total_used
                        FROM SURGICAL_MATERIAL_USAGE smu
                        JOIN SURGICAL_MATERIAL sm ON smu.material_id = sm.material_id
                        WHERE 1=1 $date_sql_usage
                        GROUP BY sm.material_name ORDER BY total_used DESC LIMIT 5";
    $usage_data = $pdo->query($sql_usage_chart)->fetchAll(PDO::FETCH_ASSOC);
    $results['usage_chart'] = [ 'labels' => array_column($usage_data, 'material_name'), 'data' => array_column($usage_data, 'total_used') ];
    
    $stmt_total_consumed = $pdo->query("SELECT SUM(amount_used) FROM SURGICAL_MATERIAL_USAGE smu WHERE 1=1 $date_sql_usage");
    $results['usage_kpi'] = [ 'total_consumed' => number_format($stmt_total_consumed->fetchColumn() ?: 0) ];

    // 4. Cost Metrics
    $stmt_value = $pdo->query("SELECT SUM(mi.quantity_available * sm.unit_cost) FROM SURGICAL_INVENTORY mi JOIN SURGICAL_MATERIAL sm ON mi.material_id = sm.material_id");
    $stmt_spent = $pdo->query("SELECT SUM(smu.amount_used * sm.unit_cost) FROM SURGICAL_MATERIAL_USAGE smu JOIN SURGICAL_MATERIAL sm ON smu.material_id = sm.material_id WHERE 1=1 $date_sql_usage");
    $results['cost_kpi'] = [
        'total_inventory_value' => number_format($stmt_value->fetchColumn() ?: 0, 2),
        'total_consumption_cost' => number_format($stmt_spent->fetchColumn() ?: 0, 2)
    ];
    
    // 5. Critical Stock Table (UPDATED to include lifespan_years)
    $sql_low_stock = "SELECT sm.material_name, mi.quantity_available, mi.minimum_stock, mi.expiration_date, sm.category, sm.lifespan_years
                      FROM SURGICAL_INVENTORY mi JOIN SURGICAL_MATERIAL sm ON mi.material_id = sm.material_id
                      WHERE mi.quantity_available < mi.minimum_stock OR mi.expiration_date < DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                      ORDER BY mi.quantity_available ASC";
    $results['table_data'] = $pdo->query($sql_low_stock)->fetchAll(PDO::FETCH_ASSOC);

    // 6. NEW: General Inventory Preview (Top 5 by Quantity)
    $sql_preview = "SELECT sm.material_name, sm.category, mi.quantity_available, mi.location 
                    FROM SURGICAL_INVENTORY mi 
                    JOIN SURGICAL_MATERIAL sm ON mi.material_id = sm.material_id 
                    ORDER BY mi.quantity_available DESC LIMIT 5";
    $results['inventory_preview'] = $pdo->query($sql_preview)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
?>