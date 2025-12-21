<?php
// groupd/views/surgeon/api_clinical_actions.php

// 1. Limpieza de Búfer para asegurar que no salgan errores HTML en el JSON
ob_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 2. Seguridad
$controller = new LoginController();
$controller->checkAuth();

// Permitir acceso a roles clínicos
$role = strtolower($_SESSION['role_name'] ?? '');
if (strpos($role, 'surgeon') === false && strpos($role, 'anest') === false && strpos($role, 'admin') === false && strpos($role, 'nurse') === false) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');

// 3. Leer entrada
$input = json_decode(file_get_contents('php://input'), true);
$pdo = getDBConnection();

ob_clean(); // Borramos cualquier basura anterior

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed");
    }

    $action = $input['action'] ?? '';

    // =======================================================================
    // ACCIÓN 1: PRESCRIBIR MEDICACIÓN (Lo que necesitas ahora)
    // =======================================================================
    if ($action === 'prescribe_med') {
        
        // Validación de datos
        if (empty($input['episode_id']) || empty($input['drug_ID']) || empty($input['dosage'])) {
            throw new Exception("Missing data: Episode ID, Drug ID or Dosage.");
        }

        // Insertar en la tabla de prescripciones del episodio (Historial Clínico)
        $sql = "INSERT INTO EPISODE_PRESCRIPTIONS 
                (episode_id, drug_ID, dosage, prescribed_date, status) 
                VALUES (:eid, :did, :dose, NOW(), 'Active')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':eid' => $input['episode_id'],
            ':did' => $input['drug_ID'],
            ':dose' => $input['dosage']
        ]);

        echo json_encode(['success' => true]);
    }

    // =======================================================================
    // ACCIÓN 2: PEDIR TEST (Por si lo usas en el futuro)
    // =======================================================================
    elseif ($action === 'order_test') {
        if (empty($input['episode_id']) || empty($input['test_id'])) {
            throw new Exception("Missing data for lab test.");
        }
        
        $sql = "INSERT INTO LAB_TESTS_EPISODES (episode_id, test_id, test_date) 
                VALUES (:eid, :tid, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':eid' => $input['episode_id'],
            ':tid' => $input['test_id']
        ]);
        
        echo json_encode(['success' => true]);
    }
    
    else {
        throw new Exception("Invalid action: " . htmlspecialchars($action));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>