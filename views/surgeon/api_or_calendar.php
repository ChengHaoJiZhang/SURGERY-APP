<?php
// groupd/views/surgeon/api_or_calendar.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

header('Content-Type: application/json');
$pdo = getDBConnection();

// 1. Obtener ID del cirujano logueado
$stmtSurgeon = $pdo->prepare("SELECT surgeon_id FROM SURGEONS WHERE user_id = ?");
$stmtSurgeon->execute([$_SESSION['user_id']]);
$mySurgeonId = $stmtSurgeon->fetchColumn();

if (!$mySurgeonId) { echo json_encode([]); exit; }

$start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-01');
$end   = isset($_GET['end'])   ? substr($_GET['end'], 0, 10)   : date('Y-m-t');

try {
    // 2. Consulta corregida (LEFT JOIN en OPERATING_ROOM)
    $sql = "SELECT 
        sp.surgical_procedure_id as id,
        sp.episode_id,
        p.procedure_name as title,
        sp.date_time as start,
        DATE_ADD(sp.date_time, INTERVAL p.default_duration MINUTE) as end,
        op.room_number,
        sp.status,
        pat.first_name, pat.surnames, pat.patient_id
    FROM SURGICAL_PROCEDURE sp
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id -- <--- CORRECCIÓN AQUÍ (era JOIN)
    JOIN EPISODE ep ON sp.episode_id = ep.episode_id
    JOIN PATIENT pat ON ep.patient_id = pat.patient_id
    JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
    
    WHERE sp.date_time >= :start AND sp.date_time <= :end
    AND sp.status != 'Cancelled'
    AND sps.surgeon_id = :my_id
    GROUP BY sp.surgical_procedure_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end, ':my_id' => $mySurgeonId]);
    $rawEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach($rawEvents as $row) {
        // Colores según estado
        $color = '#0d6efd'; // Scheduled (Azul)
        if($row['status'] == 'Completed') $color = '#198754'; // Verde
        if($row['status'] == 'In Progress') $color = '#dc3545'; // Rojo
        if($row['status'] == 'Pending') $color = '#ffc107'; // Amarillo (si saliera)
        
        // Manejo de sala nula
        $roomDisplay = $row['room_number'] ? $row['room_number'] : 'TBD';
        
        $events[] = [
            'id' => $row['id'],
            'title' => date('H:i', strtotime($row['start'])) . ' ' . $row['title'],
            'start' => $row['start'],
            'end'   => $row['end'],
            'color' => $color,
            'extendedProps' => [
                'room'       => $roomDisplay,
                'patient'    => $row['first_name'] . ' ' . $row['surnames'],
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