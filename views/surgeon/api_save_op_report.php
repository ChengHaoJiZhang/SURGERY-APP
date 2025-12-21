<?php
// groupd/views/surgeon/api_save_op_report.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 1. Seguridad
$controller = new LoginController();
$controller->checkAuth();

// Solo cirujanos y admins
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false && strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');

try {
    // 2. Leer datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Invalid JSON data");

    $spid = $input['spid'] ?? 0;
    if (!$spid) throw new Exception("Missing Surgical Procedure ID");

    $pdo = getDBConnection();
    
    // DETECTAR LA ACCIÓN (Por defecto es guardar reporte si no viene especificada)
    $action = $input['action'] ?? 'save_report';

    // =========================================================
    // CASO 1: AÑADIR IMPLANTE
    // =========================================================
    if ($action === 'add_implant') {
        $name = $input['name'] ?? '';
        $serial = $input['serial'] ?? '';
        
        if(empty($name)) throw new Exception("Device name is required");

        // Insertamos en IMPLANT_REGISTRY
        $stmt = $pdo->prepare("INSERT INTO IMPLANT_REGISTRY (surgical_procedure_id, device_name, serial_number, implanted_date) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$spid, $name, $serial]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    // =========================================================
    // CASO 2: GUARDAR REPORTE OPERATORIO (Lógica existente)
    // =========================================================
    
    // Preparar datos del reporte
    $postOpDx = $input['post_op_diagnosis'] ?? '';
    $findings = $input['findings'] ?? '';
    $specimens = $input['specimens'] ?? '';
    $bloodLoss = $input['blood_loss'] ?? 0;
    $complicationsText = $input['complications'] ?? '';
    
    // Generar reporte completo
    $fullReport = "FINDINGS:\n$findings\n\nSPECIMENS:\n$specimens\n\nBLOOD LOSS: $bloodLoss ml\n\nCOMPLICATIONS:\n$complicationsText";

    // Actualizar SURGICAL_PROCEDURE
    $stmtUpdate = $pdo->prepare("
        UPDATE SURGICAL_PROCEDURE 
        SET 
            post_op_diagnosis = ?, 
            surgical_report = ?, 
            blood_loss_ml = ?, 
            complications = ?, 
            status = 'Completed', 
            time_out_room = COALESCE(time_out_room, NOW()), 
            updated_at = NOW()
        WHERE surgical_procedure_id = ?
    ");
    
    $stmtUpdate->execute([
        $postOpDx, 
        $fullReport, 
        $bloodLoss, 
        $complicationsText, 
        $spid
    ]);

    // Actualizar SNOMED (Si aplica)
    if (!empty($input['snomed_code']) && !empty($input['procedure_id'])) {
        $stmtProc = $pdo->prepare("UPDATE PROCEDURES SET snomed_id = ?, snomed_term = ? WHERE procedure_id = ?");
        $stmtProc->execute([
            $input['snomed_code'], 
            $input['snomed_term'] ?? '', 
            $input['procedure_id']
        ]);
    }

    // Guardar lista de complicaciones (Tabla pivote)
    if (!empty($input['complication_list']) && is_array($input['complication_list'])) {
        $stmtComp = $pdo->prepare("INSERT INTO SURGICAL_PROCEDURE_COMPLICATIONS (surgical_procedure_id, complication_id, documentation_date, notes) VALUES (?, ?, NOW(), ?)");
        
        foreach ($input['complication_list'] as $compId) {
            if (is_numeric($compId)) {
                $stmtComp->execute([$spid, $compId, 'Recorded in Op Report']);
            }
        }
    }
    
    // Actualizar Causa de Muerte (Si aplica)
    if (!empty($input['patient_deceased']) && $input['patient_deceased'] == 1) {
        // Asumiendo lógica de mortalidad aquí si es necesaria
        // (De momento solo actualizamos el reporte, pero podrías insertar en PATIENT_MORTALITY si recibes los datos)
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>