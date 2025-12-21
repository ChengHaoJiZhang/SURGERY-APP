<?php
// views/director/api/calendar_events.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

try {
    $pdo = getDBConnection();
    
    // FullCalendar passes 'start' and 'end' parameters (e.g., 2025-12-01 to 2026-01-12)
    $start = $_GET['start'] ?? date('Y-m-01');
    $end   = $_GET['end']   ?? date('Y-m-t');

    $sql = "SELECT 
                sp.surgical_procedure_id as id,
                sp.date_time as start,
                p.procedure_name,
                sp.status,
                CONCAT(s.first_name, ' ', s.surnames) as surgeon_name,
                oroom.room_number
            FROM SURGICAL_PROCEDURE sp
            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
            LEFT JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id AND sps.role_in_surgery = 'Lead Surgeon'
            LEFT JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
            LEFT JOIN OPERATING_ROOM oroom ON sp.operating_room_id = oroom.room_id
            WHERE sp.date_time BETWEEN :start AND :end
            AND sp.status != 'Cancelled'"; // Optionally hide cancelled

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $raw_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($raw_events as $row) {
        // Color coding based on Surgeon (Visual distinction) or Status
        $color = '#3788d8'; // Default Blue
        if ($row['status'] === 'Completed') $color = '#198754'; // Green
        if ($row['status'] === 'In Progress') $color = '#ffc107'; // Yellow
        
        $events[] = [
            'id' => $row['id'],
            'title' => ($row['surgeon_name'] ?: 'Unassigned') . ' - ' . $row['procedure_name'],
            'start' => $row['start'],
            'color' => $color,
            'extendedProps' => [
                'room' => $row['room_number'],
                'status' => $row['status']
            ]
        ];
    }

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
}
?>