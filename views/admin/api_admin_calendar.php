<?php
// groupd/views/admin/api_admin_calendar.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

// Seguridad
$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    http_response_code(403); echo json_encode([]); exit;
}

header('Content-Type: application/json');
$pdo = getDBConnection();

// Parámetros de FullCalendar (Fechas visibles)
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-t');
$room  = $_GET['room']  ?? 'all';

try {
    // Consulta a la tabla correcta: SURGICAL_PROCEDURE
    $sql = "SELECT 
                sp.surgical_procedure_id as id,
                p.procedure_name,
                sp.date_time as start,
                DATE_ADD(sp.date_time, INTERVAL p.default_duration MINUTE) as end,
                op.room_number,
                op.room_id,
                sp.status,
                sp.is_emergency,
                pat.first_name, pat.surnames
            FROM SURGICAL_PROCEDURE sp
            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
            LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
            JOIN EPISODE e ON sp.episode_id = e.episode_id
            JOIN PATIENT pat ON e.patient_id = pat.patient_id
            WHERE sp.date_time BETWEEN :start AND :end
            AND sp.status != 'Cancelled'";

    $params = [':start' => $start, ':end' => $end];

    // Filtro de Sala
    if ($room !== 'all' && is_numeric($room)) {
        $sql .= " AND sp.operating_room_id = :room";
        $params[':room'] = $room;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear eventos para el calendario
    $events = [];
    foreach ($rows as $row) {
        // Colores según estado
        $color = '#6c757d'; // Gris (Pending)
        if ($row['status'] === 'Scheduled') $color = '#0d6efd'; // Azul
        if ($row['status'] === 'In Progress') $color = '#fd7e14'; // Naranja
        if ($row['status'] === 'Completed') $color = '#198754'; // Verde
        if ($row['is_emergency']) $color = '#dc3545'; // Rojo (Emergencia)

        // Título visual
        $title = ($row['room_number'] ? "Q{$row['room_number']}: " : "") . $row['procedure_name'];
        
        $events[] = [
            'id' => $row['id'],
            'title' => $title,
            'start' => $row['start'],
            'end'   => $row['end'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'patient' => $row['first_name'] . ' ' . $row['surnames'],
                'status'  => $row['status'],
                'room_id' => $row['room_id']
            ]
        ];
    }

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
}
?>