<?php
// File: groupd/views/patient/api/implants.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();

    // 1. Get ALL Patient IDs
    $stmt = $pdo->prepare("SELECT P.patient_id FROM PATIENT P JOIN USERS U ON P.email = U.email WHERE U.user_id = ?");
    $stmt->execute([$user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($patient_ids)) {
        echo json_encode([]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    // 2. Get Implants
    $sql = "
        SELECT 
            IR.implant_id,
            IR.device_name,
            IR.serial_number,
            IR.implanted_date,
            IR.expiration_date,
            P.procedure_name
        FROM IMPLANT_REGISTRY IR
        JOIN SURGICAL_PROCEDURE SP ON IR.surgical_procedure_id = SP.surgical_procedure_id
        JOIN PROCEDURES P ON SP.procedure_id = P.procedure_id
        WHERE SP.patient_id IN ($placeholders)
        ORDER BY IR.implanted_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($patient_ids);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function ($row) {
        return [
            'id' => $row['implant_id'],
            'name' => htmlspecialchars($row['device_name']),
            'serial' => htmlspecialchars($row['serial_number']),
            'date' => $row['implanted_date'] ? date('d/m/Y', strtotime($row['implanted_date'])) : '-',
            'procedure' => htmlspecialchars($row['procedure_name'])
        ];
    }, $results);

    echo json_encode($formatted);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>