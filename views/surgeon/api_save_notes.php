<?php
// groupd/views/surgeon/api_save_notes.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../../config/config.php';

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
        $notes = isset($input['notes']) ? $input['notes'] : '';
        $userId = $_SESSION['user_id'];

        // Actualizamos la nota vinculada al user_id del cirujano
        $stmt = $pdo->prepare("UPDATE SURGEONS SET personal_notes = :notes WHERE user_id = :uid");
        $stmt->execute([
            ':notes' => $notes,
            ':uid' => $userId
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>