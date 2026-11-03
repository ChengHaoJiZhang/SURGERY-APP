<?php
// groupd/views/nurse/api_nurse_actions.php
// API Centralizada para acciones de enfermería (Vitales, Checklist, Tiempos, Materiales)

// 1. Configuración de errores y sesión
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 2. Seguridad
$controller = new LoginController();
$controller->checkAuth();

// Permitir acceso a Enfermería, Anestesia y Admin
$role = strtolower($_SESSION['role_name'] ?? '');
if (strpos($role, 'nurse') === false && strpos($role, 'admin') === false && strpos($role, 'anest') === false) {
    ob_clean();
    http_response_code(403); 
    echo json_encode(['error' => 'Access Denied']); 
    exit;
}

// 3. Preparar Respuesta
header('Content-Type: application/json');
ob_clean(); 
$pdo = getDBConnection();

// Leer entrada (GET o POST)
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');
$spid = $_GET['spid'] ?? ($input['spid'] ?? 0);

try {
    if (!$spid) throw new Exception("Missing Surgical Procedure ID (spid).");

    // ================= GET REQUESTS =================
    if ($method === 'GET') {
        
        // A. OBTENER VITALES GUARDADOS (Para verificar si ya se hicieron)
        if ($action === 'get_vitals') {
            $stmt = $pdo->prepare("SELECT * FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? ORDER BY timestamp DESC, monitoring_id DESC");
            $stmt->execute([$spid]);
            $logs = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), static function (array $row): bool {
                return $row['card_freq'] !== null || $row['sys'] !== null
                    || ($row['oxygen_saturation'] ?? null) !== null;
            }));
            echo json_encode(['success' => true, 'logs' => $logs]);
            exit;
        }
    }

    // ================= POST REQUESTS =================
    if ($method === 'POST') {

        // 1. GUARDAR VITALES PRE-OP (Esta es la que te fallaba)
        if ($action === 'vital_signs') {
            $hr = $input['hr'] ?? null;
            $sys = $input['sys'] ?? null;
            $dias = $input['dias'] ?? null;
            $spo2 = $input['oxygen_saturation'] ?? null;
            $rr = $input['resp_freq'] ?? null;
            $obs = $input['observations'] ?? 'Nurse Pre-Op Check';

            $sql = "INSERT INTO SURGERY_MONITORING 
                    (surgical_procedure_id, card_freq, sys, dias, oxygen_saturation, resp_freq, observations, timestamp)
                    VALUES (:spid, :hr, :sys, :dias, :spo2, :rr, :obs, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':spid' => $spid,
                ':hr'   => $hr,
                ':sys'  => $sys,
                ':dias' => $dias,
                ':spo2' => $spo2,
                ':rr'   => $rr,
                ':obs'  => $obs
            ]);
            echo json_encode(['success' => true]);
        }

        // 2. REGISTRAR MATERIALES (Ya la tenías)
        elseif ($action === 'log_material') {
            $matId = $input['material_id'];
            $qty = $input['quantity'];
            
            $sql = "INSERT INTO SURGICAL_MATERIAL_USAGE (surgical_procedure_id, material_id, amount_used, created_at) 
                    VALUES (:spid, :mid, :qty, NOW())
                    ON DUPLICATE KEY UPDATE amount_used = amount_used + :qty_update";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':spid' => $spid, ':mid' => $matId, ':qty' => $qty, ':qty_update' => $qty]);
            
            // Log visual en timeline
            $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, observations, timestamp) VALUES (?, 'Material Logged', NOW())")->execute([$spid]);
            
            echo json_encode(['success' => true]);
        }
        
        // 3. GUARDAR CHECKLIST (Safety)
        elseif ($action === 'checklist') {
            // Obtener JSON actual para no sobrescribir datos del cirujano/anestesista
            $stmtGet = $pdo->prepare("SELECT safety_checklist_data FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?");
            $stmtGet->execute([$spid]);
            $currentJson = $stmtGet->fetchColumn();
            $checklistData = $currentJson ? json_decode($currentJson, true) : [];
            
            // Mezclar datos nuevos de enfermería
            $newData = $input['data'] ?? [];
            $checklistData['nurse_checked'] = true;
            $checklistData = array_merge($checklistData, $newData);

            $stmtUpd = $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET safety_checklist_data = ? WHERE surgical_procedure_id = ?");
            $stmtUpd->execute([json_encode($checklistData), $spid]);
            
            echo json_encode(['success' => true]);
        }

        // 4. MARCAR HITOS DE TIEMPO (Incision, etc)
        elseif ($action === 'timestamp') {
            $field = $input['field']; // time_in_room, time_incision, etc.
            $allowed = ['time_in_room', 'time_incision', 'time_closure', 'time_out_room'];
            
            if (in_array($field, $allowed)) {
                $sql = "UPDATE SURGICAL_PROCEDURE SET $field = NOW() WHERE surgical_procedure_id = ?";
                $pdo->prepare($sql)->execute([$spid]);
                
                // Log para el timeline visual
                $msg = "*** " . strtoupper(str_replace('time_', '', $field)) . " MARKED ***";
                $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, observations, timestamp) VALUES (?, ?, NOW())")->execute([$spid, $msg]);
                
                echo json_encode(['success' => true, 'time' => date('H:i')]);
            } else {
                throw new Exception("Invalid timestamp field.");
            }
        }
        
        // 5. GUARDAR USO DE MATERIALES MASIVO (Desde Reporte)
        elseif ($action === 'log_material_usage') {
            $items = $input['usage_data'];
            
            $pdo->beginTransaction();
            $sql = "INSERT INTO SURGICAL_MATERIAL_USAGE (surgical_procedure_id, material_id, amount_used, created_at) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE amount_used = VALUES(amount_used)"; // Reemplaza cantidad si ya existe
            $stmt = $pdo->prepare($sql);
            
            foreach ($items as $item) {
                $stmt->execute([$spid, $item['material_id'], $item['amount_used']]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        }

        else {
            throw new Exception("Invalid Action: " . htmlspecialchars($action));
        }
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
