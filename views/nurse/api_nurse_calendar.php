<?php
// views/nurse/api_nurse_calendar.php
// Fetches the global OR schedule for Nurse planning view.

ob_start();
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) {
    ob_clean(); http_response_code(403); echo json_encode(['error' => 'Access Denied']); exit;
}

header('Content-Type: application/json');
ob_clean(); 
$pdo = getDBConnection();

$start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-01');
$end   = isset($_GET['end'])   ? substr($_GET['end'], 0, 10)   : date('Y-m-t');

try {
    $sql = "SELECT 
        sp.surgical_procedure_id as id,
        sp.episode_id,
        p.procedure_name,
        sp.date_time as start,
        DATE_ADD(sp.date_time, INTERVAL p.default_duration MINUTE) as end,
        op.room_number,
        sp.status,
        CONCAT(pat.first_name, ' ', pat.surnames) as patient_name,
        pat.patient_id
    FROM SURGICAL_PROCEDURE sp
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id -- FIX: Changed JOIN to LEFT JOIN
    JOIN EPISODE ep ON sp.episode_id = ep.episode_id
    JOIN PATIENT pat ON ep.patient_id = pat.patient_id
    
    WHERE sp.date_time >= :start AND sp.date_time <= :end
    AND sp.status IN ('Scheduled', 'In Progress')
    ORDER BY sp.date_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $rawEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach($rawEvents as $row) {
        $color = '#0d6efd'; 
        if($row['status'] == 'In Progress') $color = '#dc3545';
        
        // FIX: Use 'TBD' if room_number is null
        $roomDisplay = $row['room_number'] ? $row['room_number'] : 'TBD';
        
        $title = "OR " . $roomDisplay . ": " . $row['procedure_name'] . " (" . date('H:i', strtotime($row['start'])) . ")";
        
        $events[] = [
            'id' => $row['id'],
            'title' => $title,
            'start' => $row['start'],
            'end'   => $row['end'],
            'color' => $color,
            'extendedProps' => [
                'patient' => $row['patient_name'],
                'patient_id' => $row['patient_id'],
                'episode_id' => $row['episode_id'],
                'status'     => $row['status']
            ]
        ];
    }
    echo json_encode($events);
} catch (Exception $e) { 
    echo json_encode(['error' => $e->getMessage()]); 
}
?>