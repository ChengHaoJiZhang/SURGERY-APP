<?php
// groupd/views/anesthesiologist/api_anesthesia.php

// 1. Configuración de errores para depuración limpia
ob_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../controllers/LoginController.php'; 

$controller = new LoginController();
$controller->checkAuth();

// PERMITIR ENFERMERÍA Y ADMIN
$role = strtolower($_SESSION['role_name'] ?? '');
if (strpos($role, 'anest') === false && strpos($role, 'admin') === false && strpos($role, 'nurse') === false) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
} 

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : [];

$spid = isset($_GET['spid']) ? (int)$_GET['spid'] : ($jsonInput['spid'] ?? 0);
$action = isset($_GET['action']) ? $_GET['action'] : ($jsonInput['action'] ?? 'get_data');

ob_clean(); // Limpiar cualquier salida previa

try {
    if (!$spid) throw new Exception("Error: No SPID provided.");
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    // Keep the original SQL dump usable until the local demo adds a dedicated SpO2 column.
    $oxygenColumn = $pdo->query("SHOW COLUMNS FROM SURGERY_MONITORING LIKE 'oxygen_saturation'")->fetch();
    $spo2Column = $oxygenColumn ? 'oxygen_saturation' : 'NULL';

    // ================= GET =================
    if ($method === 'GET' || $action === 'get_data') {
        // Obtener últimos signos vitales
        $stmtVitals = $pdo->prepare("SELECT card_freq, sys, dias, $spo2Column AS oxygen_saturation, timestamp FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? AND (card_freq IS NOT NULL OR sys IS NOT NULL) ORDER BY timestamp DESC LIMIT 1");
        $stmtVitals->execute([$spid]);
        $latest = $stmtVitals->fetch(PDO::FETCH_ASSOC);
        // NIBP is intermittent; keep its last measured value while HR/SpO2 refresh.
        if ($latest && ($latest['sys'] === null || $latest['dias'] === null)) {
            $stmtBp = $pdo->prepare("SELECT sys, dias FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? AND sys IS NOT NULL AND dias IS NOT NULL ORDER BY timestamp DESC, monitoring_id DESC LIMIT 1");
            $stmtBp->execute([$spid]);
            $bp = $stmtBp->fetch(PDO::FETCH_ASSOC);
            $latest['sys'] = $bp['sys'] ?? null;
            $latest['dias'] = $bp['dias'] ?? null;
        }
        $vitalsResponse = $latest ?: ['card_freq'=>'--', 'sys'=>'--', 'dias'=>'--', 'oxygen_saturation'=>'--'];

        // Obtener datos para el gráfico
        $stmtChart = $pdo->prepare("SELECT timestamp, card_freq, sys, dias, $spo2Column AS oxygen_saturation FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY timestamp DESC LIMIT 180");
        $stmtChart->execute([$spid]);

        // Construir Timeline
        $timeline = [];
        $stmtMeds = $pdo->prepare("SELECT spm.prescribed_datetime as timestamp, m.comercial_name as title, CONCAT('Dose: ', spm.dosage) as details, 'medication' as type FROM SURGICAL_PROCEDURE_MEDICATIONS spm JOIN MEDICATIONS m ON spm.drug_ID = m.drug_ID WHERE spm.surgical_procedure_id = ?");
        $stmtMeds->execute([$spid]);
        $timeline = array_merge($timeline, $stmtMeds->fetchAll(PDO::FETCH_ASSOC));

        $stmtObs = $pdo->prepare("SELECT timestamp, CASE WHEN observations LIKE '%[POC]%' THEN 'Gases / POC' WHEN observations LIKE '%[EVENT]%' THEN 'Event' WHEN observations LIKE '%TIME IN%' THEN 'Start' WHEN observations LIKE '%TIME OUT%' THEN 'End' ELSE 'Note' END as title, observations as details, 'event' as type FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? AND observations IS NOT NULL AND observations != 'Auto-Monitor' AND observations != 'Manual Entry' AND observations != 'Demo stream' AND observations != ''");
        $stmtObs->execute([$spid]);
        $timeline = array_merge($timeline, $stmtObs->fetchAll(PDO::FETCH_ASSOC));

        usort($timeline, function($a, $b) { return strtotime($b['timestamp']) - strtotime($a['timestamp']); });

        echo json_encode(['success' => true, 'latest_vitals' => $vitalsResponse, 'chart_data' => array_reverse($stmtChart->fetchAll(PDO::FETCH_ASSOC)), 'timeline' => $timeline]);
        exit;
    }

    // ================= POST =================
    if ($method === 'POST') {
        
        // --- 1. TIME IN / OUT + CHECKLIST ---
        if ($action === 'save_timestamp') {
            $type = $jsonInput['type']; 
            
            // GUARDAR CHECKLIST (Método compatible PHP)
            if ($type === 'in' && !empty($jsonInput['checklist_verified'])) {
                // 1. Obtener JSON actual
                $stmtGet = $pdo->prepare("SELECT safety_checklist_data FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?");
                $stmtGet->execute([$spid]);
                $currentJson = $stmtGet->fetchColumn();
                $checklistData = $currentJson ? json_decode($currentJson, true) : [];
                
                // 2. Añadir la marca
                $checklistData['anesthesia_checked'] = true;
                
                // 3. Guardar
                $stmtUpd = $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET safety_checklist_data = ? WHERE surgical_procedure_id = ?");
                $stmtUpd->execute([json_encode($checklistData), $spid]);
                
                // 4. Log
                $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, observations, timestamp) VALUES (?, ?, NOW())")
                    ->execute([$spid, "[CHECKLIST] Anesthesia Safety Check Verified"]);
            }
            
            // LÓGICA DE TIEMPOS
            $sql = "";
            $logMsg = "";

            if ($type === 'in') {
                $sql = "UPDATE SURGICAL_PROCEDURE SET status = 'In Progress', time_in_room = NOW() WHERE surgical_procedure_id = ?";
                $logMsg = "*** TIME IN / ANESTHESIA START ***";
            } elseif ($type === 'out') {
                $sql = "UPDATE SURGICAL_PROCEDURE SET status = 'Completed', time_out_room = NOW() WHERE surgical_procedure_id = ?";
                $logMsg = "*** TIME OUT / SURGERY COMPLETED ***";
            } else {
                // Para eventos custom
                $col = 'time_' . $type; // Asegurar que la columna existe en BD
                $sql = "UPDATE SURGICAL_PROCEDURE SET $col = NOW() WHERE surgical_procedure_id = ?";
                $logMsg = "*** " . strtoupper($type) . " ***";
            }

            if ($sql) {
                $pdo->prepare($sql)->execute([$spid]);
                // Insertar Log en SURGERY_MONITORING (Nombre correcto de la tabla)
                $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, observations, timestamp) VALUES (?, ?, NOW())")->execute([$spid, $logMsg]);
            }
            
            echo json_encode(['success' => true]);
        }
        
        // --- 2. DAR MEDICACIÓN ---
        elseif ($action === 'give_med') {
            $sql = "INSERT INTO SURGICAL_PROCEDURE_MEDICATIONS (surgical_procedure_id, drug_ID, prescribed_datetime, prescribed_by, dosage) VALUES (?, ?, NOW(), ?, ?)";
            $pdo->prepare($sql)->execute([$spid, $jsonInput['drug_id'], $userId, $jsonInput['dosage']]);
            echo json_encode(['success' => true]);
        }
        
        // --- 3. GUARDAR SIGNOS VITALES MANUALES ---
        elseif ($action === 'save_vitals') {
            // CORRECCIÓN: Nombre de tabla SURGERY_MONITORING (antes estaba mal como SURGICAL_MONITORING)
            $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, card_freq, sys, dias, $spo2Column, timestamp, observations) VALUES (?, ?, ?, ?, ?, NOW(), 'Manual Entry')")->execute([$spid, $jsonInput['hr'], $jsonInput['sys'], $jsonInput['dias'], $jsonInput['spo2']]);
            echo json_encode(['success' => true]);
        }
        
        // --- 4. GUARDAR POC/GASES ---
        elseif ($action === 'save_poc') {
            $details = sprintf("[POC] GASES: pH %s | pCO2 %s | pO2 %s | Lac %s | Na %s | K %s | Glu %s", $jsonInput['ph'], $jsonInput['pco2'], $jsonInput['po2'], $jsonInput['lac'], $jsonInput['na'], $jsonInput['k'], $jsonInput['glu']);
            // CORRECCIÓN: Nombre de tabla SURGERY_MONITORING
            $pdo->prepare("INSERT INTO SURGERY_MONITORING (surgical_procedure_id, observations, timestamp) VALUES (?, ?, NOW())")->execute([$spid, $details]);
            echo json_encode(['success' => true]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
