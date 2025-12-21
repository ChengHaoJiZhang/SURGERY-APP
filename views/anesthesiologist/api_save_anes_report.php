<?php
// groupd/views/anesthesiologist/api_save_anes_report.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

header('Content-Type: application/json');
$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $spid = $input['spid'];
        $report = $input['report'];
        
        // Actualizar la columna ESPECÍFICA de anestesia
        // Nota: No cambiamos el estado a 'Completed' aquí, eso lo hace el Time Out.
        $sql = "UPDATE SURGICAL_PROCEDURE SET anesthesiology_report = ?, updated_at = NOW() WHERE surgical_procedure_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report, $spid]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>