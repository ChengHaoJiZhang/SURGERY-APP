<?php
// grupd/views/surgeon/api_save_preference.php
session_start();
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
$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obtener ID del cirujano actual
        $stmtSurgeon = $pdo->prepare("SELECT surgeon_id FROM SURGEONS WHERE user_id = ?");
        $stmtSurgeon->execute([$_SESSION['user_id']]);
        $surgeonId = $stmtSurgeon->fetchColumn();

        if (!$surgeonId) throw new Exception("Surgeon profile not found.");

        // Guardar o Actualizar Preferencia para ESTE procedimiento
        $sql = "INSERT INTO SURGEON_PREFERENCES 
                (surgeon_id, procedure_id, glove_size, specific_instruments, notes) 
                VALUES (:sid, :pid, :gl, :ins, :nts)
                ON DUPLICATE KEY UPDATE 
                glove_size = VALUES(glove_size),
                specific_instruments = VALUES(specific_instruments),
                notes = VALUES(notes)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sid' => $surgeonId,
            ':pid' => $input['procedure_id'],
            ':gl'  => $input['glove_size'],
            ':ins' => $input['instruments'],
            ':nts' => $input['notes']
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>