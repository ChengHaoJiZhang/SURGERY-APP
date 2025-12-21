<?php
// grupd/views/surgeon/api_save_op_report.php
session_start();

// Asumiendo que esta ruta es correcta y carga la configuración y funciones DB
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// Solo Cirujanos
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');

// La función getDBConnection() debe estar definida en config.php
$pdo = getDBConnection(); 
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($input['action']) || empty($input['spid'])) {
            throw new Exception("Missing action or Surgical Procedure ID (spid).");
        }
        
        $action = $input['action'];
        $spid = (int)$input['spid'];

        if ($action === 'save_report') {
            // --- Lógica para guardar el Reporte Quirúrgico completo ---
            
            // Si el paciente está fallecido, validamos que al menos se envíen la hora y la causa.
            $isDeceased = $input['patient_deceased'] ?? 0;
            $deathTime = ($isDeceased == 1 && !empty($input['death_time'])) ? $input['death_time'] : null;
            $deathCause = ($isDeceased == 1 && !empty($input['death_cause'])) ? $input['death_cause'] : null;

            $sql = "UPDATE SURGICAL_PROCEDURE 
                    SET post_op_diagnosis = :snomed_term,
                        snomed_code = :snomed_code,
                        surgical_report = :report, 
                        blood_loss_ml = :blood,
                        complications_yes_no = :comp_yn,
                        complications_details = :comp_det,
                        post_op_instructions = :orders,
                        patient_deceased = :patient_deceased,
                        death_time = :death_time,
                        cause_of_death = :death_cause,
                        updated_at = NOW(),
                        status = 'Completed' -- Marca la cirugía como completada
                    WHERE surgical_procedure_id = :spid";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':snomed_term' => $input['snomed_term'] ?? null,
                ':snomed_code' => $input['snomed_code'] ?? null,
                ':report' => $input['report'] ?? null,
                ':blood' => $input['blood'] ?? 0,
                ':comp_yn' => $input['comp_yn'] ?? 0,
                ':comp_det' => $input['comp_det'] ?? null,
                ':orders' => $input['orders'] ?? null,
                ':patient_deceased' => $isDeceased,
                ':death_time' => $deathTime,
                ':death_cause' => $deathCause,
                ':spid' => $spid
            ]);

            echo json_encode(['success' => true]);

        } elseif ($action === 'add_implant') {
            // --- Lógica para insertar un nuevo Implante ---
            
            if (empty($input['name'])) {
                throw new Exception("Device Name is required for implant registry.");
            }
            
            $sql = "INSERT INTO IMPLANT_REGISTRY 
                    (surgical_procedure_id, device_name, serial_number, implanted_date) 
                    VALUES (:spid, :name, :serial, NOW())"; // NOW() usa la hora actual del servidor
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':spid' => $spid,
                ':name' => $input['name'],
                ':serial' => $input['serial'] ?? null 
            ]);

            // Se devuelve el ID para confirmación (opcional)
            echo json_encode(['success' => true, 'implant_id' => $pdo->lastInsertId()]);
        
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        }

    } catch (Exception $e) {
        // En un entorno de producción, deberías loggear $e->getMessage() y mostrar un error genérico.
        http_response_code(500); 
        echo json_encode(['success' => false, 'error' => "Server Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>