<?php
// groupd/views/surgeon/api_send_request.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = getDBConnection();

    $userId    = $_SESSION['user_id'];
    $reqType   = $input['type'] ?? 'General';
    $details   = trim($input['details'] ?? '');
    $patientId = !empty($input['patient_id']) ? $input['patient_id'] : null;
    $surgeryId = !empty($input['surgery_id']) ? $input['surgery_id'] : null;
    
    // --- NUEVO: Recibir el ID del procedimiento ---
    // Si no viene (porque es una cancelación o reprog.), será null
    $procId    = !empty($input['procedure_id']) ? $input['procedure_id'] : null;

    // --- VALIDACIÓN DE INTEGRIDAD ---
    if (strpos($reqType, 'New') !== false) {
        if (empty($patientId)) {
            throw new Exception("⚠️ Debes seleccionar un paciente de la lista desplegable.");
        }
        // Opcional: Si quieres ser estricto, puedes descomentar la siguiente línea:
        // if (empty($procId)) throw new Exception("⚠️ Error: No se recibió el procedimiento.");
    }
    
    if ((strpos($reqType, 'Reschedule') !== false || strpos($reqType, 'Cancellation') !== false) && empty($surgeryId)) {
        throw new Exception("⚠️ Error de sistema: No se ha identificado la cirugía original.");
    }

    // --- INSERT ACTUALIZADO ---
    // Añadimos la columna 'requested_procedure_id' y el marcador ':rpid'
    $stmt = $pdo->prepare("
        INSERT INTO SCHEDULE_REQUESTS 
        (user_id, request_type, details, status, created_at, patient_id, original_surgery_id, requested_procedure_id) 
        VALUES (:uid, :type, :details, 'Pending', NOW(), :pid, :oid, :rpid)
    ");

    $stmt->execute([
        ':uid'     => $userId,
        ':type'    => $reqType,
        ':details' => $details,
        ':pid'     => $patientId,
        ':oid'     => $surgeryId,
        ':rpid'    => $procId // <--- Aquí guardamos el ID que elegiste en el modal
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>