<?php
// grupd/views/nurse/api_nursery_report.php
ob_start();
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied: Nurse role required.']);
    exit;
}

header('Content-Type: application/json');
ob_clean(); 
$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // FETCH: Obtener el reporte actual
    $spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;
    
    try {
        $stmt = $pdo->prepare("SELECT nursery_report FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = :spid");
        $stmt->execute([':spid' => $spid]);
        $report = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'report' => $report ?: ""]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SAVE: Guardar el reporte
    try {
        $spid = $input['spid'] ?? 0;
        $reportContent = $input['report_content'] ?? '';
        
        if ($spid === 0) {
            throw new Exception("Missing Surgical Procedure ID.");
        }

        $stmt = $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET nursery_report = :report WHERE surgical_procedure_id = :spid");
        $stmt->execute([
            ':report' => $reportContent,
            ':spid' => $spid
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Report saved successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>