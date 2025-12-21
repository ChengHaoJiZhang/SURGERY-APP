<?php
// groupd/views/surgeon/api_discharge_episode.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

header('Content-Type: application/json');

// 1. Seguridad
$controller = new LoginController();
if (!isset($_SESSION['user_id']) || strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// 2. Obtener datos
$input = json_decode(file_get_contents('php://input'), true);
$episodeId = $input['episode_id'] ?? null;

if (!$episodeId) {
    echo json_encode(['success' => false, 'error' => 'No episode ID provided']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // INICIAR TRANSACCIÓN (Para que se haga todo junto)
    $pdo->beginTransaction();

    // 3A. Cerrar el Episodio (Poner fecha fin y estado Discharged)
    $stmtEp = $pdo->prepare("UPDATE EPISODE 
                             SET date_time_end = NOW(), 
                                 status = 'Discharged' 
                             WHERE episode_id = ?");
    $stmtEp->execute([$episodeId]);

    // 3B. Completar las Cirugías asociadas (El cambio que faltaba)
    // Solo actualizamos las que no estén canceladas.
    $stmtSurg = $pdo->prepare("UPDATE SURGICAL_PROCEDURE 
                               SET status = 'Completed', 
                                   updated_at = NOW() 
                               WHERE episode_id = ? AND status != 'Cancelled'");
    $stmtSurg->execute([$episodeId]);

    // CONFIRMAR CAMBIOS
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Si algo falla, deshacemos todo
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>