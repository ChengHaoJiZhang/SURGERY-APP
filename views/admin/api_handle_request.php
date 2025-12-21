<?php
// groupd/views/admin/api_handle_request.php
ob_start();
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    ob_clean(); http_response_code(403); echo json_encode(['error' => 'Access Denied']); exit;
}

header('Content-Type: application/json');
ob_clean(); 
$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $reqId = $input['request_id'];
        $action = $input['action'];

        if (empty($reqId) || empty($action)) throw new Exception("Missing parameters");

        $status = ($action === 'resolve') ? 'Resolved' : 'Rejected';

        $pdo->beginTransaction();

        // 1. ACTUALIZAR ESTADO DE LA SOLICITUD
        $stmt = $pdo->prepare("UPDATE SCHEDULE_REQUESTS SET status = :status WHERE request_id = :rid");
        $stmt->execute([':status' => $status, ':rid' => $reqId]);

        // 2. LÓGICA AUTOMÁTICA
        if ($status === 'Resolved') {
            
            // Obtener solicitud
            $req = $pdo->query("SELECT * FROM SCHEDULE_REQUESTS WHERE request_id = $reqId")->fetch();
            
            // CASO A: NUEVA CIRUGÍA -> CREAR EN PENDING
            // CASO A: NUEVA CIRUGÍA -> CREAR EN PENDING
            if (strpos($req['request_type'], 'New') !== false && !empty($req['patient_id'])) {
                
                // a. Crear Episodio
                // Creamos un episodio nuevo para agrupar todo el proceso clínico
                $pdo->prepare("INSERT INTO EPISODE (patient_id, date_time_start, reason, status) VALUES (?, NOW(), ?, 'Active')")
                    ->execute([$req['patient_id'], 'Surgery Request: ' . $req['details']]);
                $epId = $pdo->lastInsertId();

                // b. OBTENER EL PROCEDIMIENTO SOLICITADO (CORRECCIÓN PRINCIPAL)
                // Recuperamos el ID que el cirujano seleccionó en el modal.
                // Si por algún error antiguo no viniera (null), usamos 1 por defecto para que no falle el sistema.
                $procId = !empty($req['requested_procedure_id']) ? $req['requested_procedure_id'] : 1;

                // c. Crear Cirugía en estado 'Pending' (Lista de Espera)
                // Ahora usamos la variable $procId correcta, no una aleatoria.
                $sqlCreate = "INSERT INTO SURGICAL_PROCEDURE 
                              (episode_id, patient_id, procedure_id, status, priority_level, created_at, operating_room_id) 
                              VALUES (?, ?, ?, 'Pending', 'Elective', NOW(), NULL)";
                
                $pdo->prepare($sqlCreate)->execute([
                    $epId, 
                    $req['patient_id'], 
                    $procId // <--- ¡Aquí es donde ocurre la magia!
                ]);
            }

            // CASO B: CANCELACIÓN
            elseif (strpos($req['request_type'], 'Cancellation') !== false && !empty($req['original_surgery_id'])) {
                $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET status = 'Cancelled' WHERE surgical_procedure_id = ?")
                    ->execute([$req['original_surgery_id']]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>