<?php
// groupd/views/surgeon/api_intraop_monitoring.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

header('Content-Type: application/json');
$pdo = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);

// POST: Save vital signs (Simulador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['action']) && $input['action'] === 'vital_signs') {
    
    // Primero verificamos si la cirugía ya está cerrada antes de permitir guardar
    $stmtCheck = $pdo->prepare("SELECT status FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?");
    $stmtCheck->execute([$input['surgical_procedure_id']]);
    $status = $stmtCheck->fetchColumn();

    if ($status === 'Completed' || $status === 'Cancelled') {
        echo json_encode(['success' => false, 'error' => 'Surgery is closed']);
        exit;
    }

    $sql = "INSERT INTO SURGERY_MONITORING 
            (surgical_procedure_id, card_freq, sys, dias, mean_pres, resp_freq, 
             tidal_CO2, tidal_volume, timestamp, observations)
            VALUES (:sp_id, :hr, :sys, :dias, :mean, :rr, :etco2, :tv, NOW(), :obs)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':sp_id' => $input['surgical_procedure_id'],
        ':hr'    => $input['heart_rate'],
        ':sys'   => $input['sys_bp'],
        ':dias'  => $input['dias_bp'],
        ':mean'  => $input['mean_bp'] ?? null,
        ':rr'    => $input['resp_rate'] ?? null,
        ':etco2' => $input['etco2'], 
        ':tv'    => $input['tidal_volume'] ?? null,
        ':obs'   => $input['observations'] ?? null
    ]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// GET: Get monitoring data AND STATUS
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $surgeryId = $_GET['surgery_id'];
    
    // 1. Obtener Datos de Monitorización
    $sql = "SELECT * FROM SURGERY_MONITORING 
            WHERE surgical_procedure_id = :sp_id 
            ORDER BY timestamp DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sp_id' => $surgeryId]);
    $monitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. NUEVO: Obtener Estado de la Cirugía
    $stmtStatus = $pdo->prepare("SELECT status FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?");
    $stmtStatus->execute([$surgeryId]);
    $surgeryStatus = $stmtStatus->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'monitoring' => $monitoring,
        'surgery_status' => $surgeryStatus // <--- Enviamos el estado al frontend
    ]);
    exit;
}
?>