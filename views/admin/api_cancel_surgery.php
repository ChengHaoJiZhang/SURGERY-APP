<?php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// Only Admin can cancel
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');
$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($input['surgery_id'])) {
            throw new Exception("Surgery ID is required.");
        }

        // Update status to 'Cancelled'
        $stmt = $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET status = 'Cancelled' WHERE surgical_procedure_id = :id");
        $stmt->execute([':id' => $input['surgery_id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Surgery not found or already cancelled.");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>