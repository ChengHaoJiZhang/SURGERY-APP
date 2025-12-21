<?php
// groupd/views/admin/api_schedule_surgery.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

// Seguridad: Solo Admins
$controller = new LoginController();
$controller->checkAuth();
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
        $action = $input['action'] ?? '';

        // ==================================================================
        // ACCIÓN 1: CONFIRMAR PROGRAMACIÓN (El botón verde del modal)
        // ==================================================================
        if ($action === 'confirm_schedule') {
            
            // 1. Validar datos
            if (empty($input['surgery_id']) || empty($input['date_time']) || empty($input['room_id'])) {
                throw new Exception("Faltan datos obligatorios (Fecha o Sala).");
            }

            $spId = $input['surgery_id'];
            $room = $input['room_id'];
            $date = $input['date_time'];
            
            // Datos del personal (vienen del objeto 'staff')
            $staff = $input['staff'] ?? [];
            $surgId = $staff['surgeon'] ?? null;
            $anesId = $staff['anesthesiologist'] ?? null;
            $nurseId = $staff['nurse'] ?? null;

            if (!$surgId || !$anesId || !$nurseId) {
                throw new Exception("Debes asignar todo el equipo (Cirujano, Anestesista y Enfermera).");
            }

            // INICIAR TRANSACCIÓN (Para que se guarde todo o nada)
            $pdo->beginTransaction();

            // 2. Actualizar la Cirugía (Fecha, Sala, Estado)
            $sqlUpd = "UPDATE SURGICAL_PROCEDURE 
                       SET date_time = :dt, 
                           operating_room_id = :rid, 
                           status = 'Scheduled',
                           updated_at = NOW()
                       WHERE surgical_procedure_id = :sid";
            
            $stmt = $pdo->prepare($sqlUpd);
            $stmt->execute([':dt' => $date, ':rid' => $room, ':sid' => $spId]);

            // 3. Asignar CIRUJANO (Tabla SURGICAL_PROCEDURE_SURGEONS)
            // Primero borramos asignaciones previas por seguridad (limpieza)
            $pdo->prepare("DELETE FROM SURGICAL_PROCEDURE_SURGEONS WHERE surgical_procedure_id = ?")->execute([$spId]);
            
            $sqlSurg = "INSERT INTO SURGICAL_PROCEDURE_SURGEONS (surgical_procedure_id, surgeon_id, role_in_surgery) 
                        VALUES (?, ?, 'Lead Surgeon')";
            $pdo->prepare($sqlSurg)->execute([$spId, $surgId]);

            // 4. Asignar ANESTESISTA (Tabla SURGICAL_PROCEDURE_ANESTHESIOLOGISTS)
            $pdo->prepare("DELETE FROM SURGICAL_PROCEDURE_ANESTHESIOLOGISTS WHERE surgical_procedure_id = ?")->execute([$spId]);
            
            $sqlAnes = "INSERT INTO SURGICAL_PROCEDURE_ANESTHESIOLOGISTS (surgical_procedure_id, anesthesiologist_id, role_in_surgery) 
                        VALUES (?, ?, 'Main Anesthesiologist')";
            $pdo->prepare($sqlAnes)->execute([$spId, $anesId]);

            // 5. Asignar ENFERMERA (Tabla SURGICAL_PROCEDURE_NURSES)
            $pdo->prepare("DELETE FROM SURGICAL_PROCEDURE_NURSES WHERE surgical_procedure_id = ?")->execute([$spId]);
            
            $sqlNurse = "INSERT INTO SURGICAL_PROCEDURE_NURSES (surgical_procedure_id, nurse_id, role_in_surgery) 
                         VALUES (?, ?, 'Head Nurse')";
            $pdo->prepare($sqlNurse)->execute([$spId, $nurseId]);

            // CONFIRMAR TRANSACCIÓN
            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        }

        // ==================================================================
        // ACCIÓN 2: MOVER CIRUGÍA (Drag & Drop en calendario)
        // ==================================================================
        elseif ($action === 'move_surgery') {
            
            if (empty($input['surgery_id']) || empty($input['start'])) {
                throw new Exception("Datos incompletos para mover.");
            }

            $spId = $input['surgery_id'];
            $reqId = $input['request_id'] ?? null; // Recibimos el ID de solicitud

            // 1. Preparamos SQL base (Mover fecha y sala)
            $sql = "UPDATE SURGICAL_PROCEDURE 
                    SET date_time = :dt, 
                        operating_room_id = :rid,
                        status = 'Scheduled',
                        updated_at = NOW()"; // Importante actualizar fecha modif.
            
            $params = [
                ':dt' => $input['start'],
                ':rid' => !empty($input['room_id']) ? $input['room_id'] : null,
                ':sid' => $spId
            ];

            // 2. LÓGICA MÁGICA: Si viene de una solicitud, revisamos si hay cambio de procedimiento
            if ($reqId) {
                // Buscamos si la solicitud pedía un procedimiento nuevo
                $reqStmt = $pdo->prepare("SELECT requested_procedure_id FROM SCHEDULE_REQUESTS WHERE request_id = ?");
                $reqStmt->execute([$reqId]);
                $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);

                // Si hay un cambio de procedimiento pedido, lo aplicamos a la cirugía
                if ($reqData && !empty($reqData['requested_procedure_id'])) {
                    $sql .= ", procedure_id = :procId";
                    $params[':procId'] = $reqData['requested_procedure_id'];
                }

                // Además, cerramos la solicitud automáticamente
                $pdo->prepare("UPDATE SCHEDULE_REQUESTS SET status = 'Resolved' WHERE request_id = ?")->execute([$reqId]);
            }

            // 3. Ejecutamos la actualización de la cirugía
            $sql .= " WHERE surgical_procedure_id = :sid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true]);
            exit;
        }
        
        // ==================================================================
        // ACCIÓN 3: CREAR PENDIENTE (Add to Waiting List desde Dashboard)
        // ==================================================================
        elseif ($action === 'create_pending') {
             // Esta lógica ya la usas en admin_dashboard.php, la centralizamos aquí
             $patId = $input['patient_id'];
             $procId = $input['procedure_id'];
             $prio = $input['priority'] ?? 'Elective';

             $pdo->beginTransaction();
             // Crear Episodio
             $pdo->prepare("INSERT INTO EPISODE (patient_id, date_time_start, reason, status) VALUES (?, NOW(), 'Surgical Admission', 'Active')")
                 ->execute([$patId]);
             $epId = $pdo->lastInsertId();

             // Crear Cirugía
             $stmt = $pdo->prepare("INSERT INTO SURGICAL_PROCEDURE (episode_id, patient_id, procedure_id, status, priority_level) VALUES (?, ?, ?, 'Pending', ?)");
             $stmt->execute([$epId, $patId, $procId, $prio]);
             
             $pdo->commit();
             echo json_encode(['success' => true]);
             exit;
        }

        throw new Exception("Acción no válida o desconocida.");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>