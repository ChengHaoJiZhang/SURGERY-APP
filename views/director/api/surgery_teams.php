<?php
// views/director/api/surgery_teams.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

try {
    $pdo = getDBConnection();
    
    // Inputs
    $date = $_GET['date'] ?? date('Y-m-d');
    $hour = $_GET['hour'] ?? ''; // Optional: "08", "14", etc.

    // 1. Base Query: Find Surgeries on that Date
    $sql = "SELECT 
                sp.surgical_procedure_id,
                sp.date_time,
                p.procedure_name,
                oroom.room_number,
                sp.status
            FROM SURGICAL_PROCEDURE sp
            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
            LEFT JOIN OPERATING_ROOM oroom ON sp.operating_room_id = oroom.room_id
            WHERE DATE(sp.date_time) = :date";
    
    $params = [':date' => $date];

    // Optional Hour Filter
    if ($hour !== '') {
        $sql .= " AND HOUR(sp.date_time) = :hour";
        $params[':hour'] = $hour;
    }
    
    $sql .= " ORDER BY sp.date_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $surgeries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Hydrate with Team Members
    // We iterate through each surgery to fetch the specific staff assigned
    foreach ($surgeries as &$surgery) {
        $procId = $surgery['surgical_procedure_id'];

        // A. Surgeons
        $sql_s = "SELECT s.first_name, s.surnames, sps.role_in_surgery 
                  FROM SURGICAL_PROCEDURE_SURGEONS sps
                  JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
                  WHERE sps.surgical_procedure_id = ?";
        $stmt_s = $pdo->prepare($sql_s);
        $stmt_s->execute([$procId]);
        $surgery['surgeons'] = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

        // B. Nurses
        $sql_n = "SELECT n.first_name, n.surnames, spn.role_in_surgery 
                  FROM SURGICAL_PROCEDURE_NURSES spn
                  JOIN NURSES n ON spn.nurse_id = n.nurse_id
                  WHERE spn.surgical_procedure_id = ?";
        $stmt_n = $pdo->prepare($sql_n);
        $stmt_n->execute([$procId]);
        $surgery['nurses'] = $stmt_n->fetchAll(PDO::FETCH_ASSOC);

        // C. Anesthesiologists
        $sql_a = "SELECT a.first_name, a.surnames, spa.role_in_surgery 
                  FROM SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa
                  JOIN ANESTHESIOLOGISTS a ON spa.anesthesiologist_id = a.anesthesiologist_id
                  WHERE spa.surgical_procedure_id = ?";
        $stmt_a = $pdo->prepare($sql_a);
        $stmt_a->execute([$procId]);
        $surgery['anesthesiologists'] = $stmt_a->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatting
        $surgery['time_formatted'] = date('H:i', strtotime($surgery['date_time']));
    }

    echo json_encode($surgeries);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>