<?php
// groupd/views/admin/api_admin_patient_details.php

// 1. Limpieza de seguridad
ob_start();
ini_set('display_errors', 0); // Ocultar errores visuales para no romper JSON
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// Solo Admin
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');
ob_clean(); 

$pdo = getDBConnection();
$pid = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($pid)) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

try {
    // 1. BUSCAR PACIENTE
    $stmtP = $pdo->prepare("SELECT * FROM PATIENT WHERE patient_id = :pid");
    $stmtP->execute([':pid' => $pid]);
    $patient = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(['success' => false, 'error' => 'Patient ID not found']);
        exit;
    }

    // 2. BUSCAR HISTORIAL DE CIRUGÍAS (CORREGIDO CON LEFT JOIN)
    $sqlSurg = "SELECT 
        sp.surgical_procedure_id,
        sp.date_time,
        sp.status,
        p.procedure_name,
        COALESCE(op.room_number, 'TBD') as room_number, -- Si es null, mostramos TBD
        (SELECT s.surnames FROM SURGICAL_PROCEDURE_SURGEONS sps 
         JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id 
         WHERE sps.surgical_procedure_id = sp.surgical_procedure_id 
         LIMIT 1) as surgeon_name
    FROM SURGICAL_PROCEDURE sp
    JOIN EPISODE ep ON sp.episode_id = ep.episode_id
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id -- <--- CORRECCIÓN CLAVE
    WHERE ep.patient_id = :pid
    ORDER BY sp.date_time DESC";

    $stmtS = $pdo->prepare($sqlSurg);
    $stmtS->execute([':pid' => $pid]);
    $surgeries = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'surgeries' => $surgeries
    ]);

} catch (Exception $e) {
    ob_clean(); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>