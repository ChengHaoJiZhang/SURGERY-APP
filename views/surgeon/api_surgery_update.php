<?php
// groupd/views/surgeon/api_surgery_update.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// Verificar Rol
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');
$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($input['action']) || empty($input['sp_id'])) {
            throw new Exception("Missing parameters.");
        }

        $sp_id = $input['sp_id'];
        $userId = $_SESSION['user_id'];

        // --- SEGURIDAD: VERIFICAR PROPIEDAD (Ownership Check) ---
        // Asegura que el cirujano actual está asignado a esta cirugía específica
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM SURGICAL_PROCEDURE_SURGEONS sps
            JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
            WHERE sps.surgical_procedure_id = ? AND s.user_id = ?
        ");
        $stmtCheck->execute([$sp_id, $userId]);
        
        if ($stmtCheck->fetchColumn() == 0) {
            throw new Exception("Security Violation: You are not assigned to this surgery.");
        }
        // -----------------------------------------------------------

        $action = $input['action'];

        // 1. UPDATE TIMESTAMPS
        if ($action === 'timestamp') {
            $field = $input['field'];
            $allowed = ['time_in_room', 'time_incision', 'time_closure', 'time_out_room'];
            if (!in_array($field, $allowed)) throw new Exception("Invalid field.");

            $stmt = $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET $field = NOW() WHERE surgical_procedure_id = ?");
            $stmt->execute([$sp_id]);
            echo json_encode(['success' => true, 'time' => date('H:i')]);
        }

        // 2. CHECKLIST
        elseif ($action === 'checklist') {
            $data = json_encode($input['data']);
            $stmt = $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET safety_checklist_data = ? WHERE surgical_procedure_id = ?");
            $stmt->execute([$data, $sp_id]);
            echo json_encode(['success' => true]);
        }

        // 3. START SURGERY (Quick Start)
        elseif ($action === 'start_surgery') {
            
            // MODIFICADO: Guardar estado de checklist si se envía
            if (!empty($input['checklist_verified'])) {
                $sqlCheck = "UPDATE SURGICAL_PROCEDURE 
                             SET safety_checklist_data = JSON_SET(COALESCE(safety_checklist_data, '{}'), '$.surgeon_checked', true) 
                             WHERE surgical_procedure_id = ?";
                $pdo->prepare($sqlCheck)->execute([$sp_id]);
                
                // Log de auditoría en monitorización
                $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, observations, timestamp) VALUES (?, ?, NOW())")
                    ->execute([$sp_id, "[CHECKLIST] Surgeon Safety Check: VERIFIED"]);
            }

            // Actualizar estado y tiempo (Lógica existente)
            $sql = "UPDATE SURGICAL_PROCEDURE 
                    SET status = 'In Progress', 
                        time_in_room = COALESCE(time_in_room, NOW()) 
                    WHERE surgical_procedure_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sp_id]);
            echo json_encode(['success' => true]);
        }

        // 4. FINISH SURGERY (Nuevo)
        elseif ($action === 'finish_surgery') {
            // Marca como completada y establece la hora de salida si no se ha puesto aún
            $sql = "UPDATE SURGICAL_PROCEDURE 
                    SET status = 'Completed', 
                        time_out_room = COALESCE(time_out_room, NOW()) 
                    WHERE surgical_procedure_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sp_id]);
            echo json_encode(['success' => true]);
        }
        
        else { throw new Exception("Invalid action."); }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>