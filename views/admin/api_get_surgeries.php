<?php
require_once '../includes/functions.php';
checkAuth();

$pdo = getDBConnection();
$roomFilter = isset($_GET['room']) ? $_GET['room'] : '';

$sql = "SELECT 
    s.surgery_id,
    s.surgery_date,
    DATE_ADD(s.surgery_date, INTERVAL COALESCE(p.estimated_duration, 60) MINUTE) as end_time,
    CONCAT(pat.first_name, ' ', pat.surnames) as patient_name,
    CONCAT(sur.first_name, ' ', sur.surnames) as surgeon_name,
    pr.procedure_name,
    o.room_number,
    s.patient_id
FROM SURGERIES s
JOIN PATIENTS pat ON s.patient_id = pat.patient_id
JOIN SURGEONS sur ON s.surgeon_id = sur.surgeon_id
JOIN PROCEDURES pr ON s.procedure_id = pr.procedure_id
LEFT JOIN OPERATING_ROOM o ON s.room_id = o.room_id
WHERE s.status != 'cancelled'";

if ($roomFilter) {
    $sql .= " AND s.room_id = :room_id";
}

$stmt = $pdo->prepare($sql);
if ($roomFilter) {
    $stmt->bindParam(':room_id', $roomFilter);
}
$stmt->execute();

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));