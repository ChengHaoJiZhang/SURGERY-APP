<?php
// groupd/views/nurse/api_nurse_data.php
// INICIO DEL BUFFER: Captura cualquier salida indeseada
ob_start();

session_start();
ini_set('display_errors', 0); // Ocultar errores en pantalla (se verán en el JSON)
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

header('Content-Type: application/json');

try {
    $spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;
    if (!$spid) throw new Exception("Missing SPID");

    $pdo = getDBConnection();

    // 1. OBTENER DATOS DE LA CIRUGÍA Y CIRUJANO
    $stmt = $pdo->prepare("
        SELECT sp.episode_id, sp.procedure_id, 
               sps.surgeon_id
        FROM SURGICAL_PROCEDURE sp
        LEFT JOIN SURGICAL_PROCEDURE_SURGEONS sps 
               ON sp.surgical_procedure_id = sps.surgical_procedure_id 
               AND sps.role_in_surgery LIKE '%Lead%'
        WHERE sp.surgical_procedure_id = ?
        LIMIT 1
    ");
    $stmt->execute([$spid]);
    $surgery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$surgery) throw new Exception("Surgery not found for SPID $spid");

    $epId = $surgery['episode_id'];
    $procId = $surgery['procedure_id'];
    $surgeonId = $surgery['surgeon_id'] ?? 0;

    // 2. VERIFICAR CONSENTIMIENTO
    // Buscamos si existe al menos un consentimiento firmado (signed=1)
    $stmtConsent = $pdo->prepare("
        SELECT COUNT(*) 
        FROM PATIENT_CONSENTS 
        WHERE episode_id = ? 
          AND procedure_id = ? 
          AND signed = 1
    ");
    $stmtConsent->execute([$epId, $procId]);
    $count = $stmtConsent->fetchColumn();
    $isConsentSigned = ($count > 0);

    // 3. OBTENER PREFERENCIAS (Lógica a prueba de fallos)
    $prefData = [
        'glove_size' => 'Standard',
        'sutures' => ['General Set'],
        'notes' => 'No specific preferences.',
        'instruments' => ['Basic Surgical Set']
    ];

    if ($surgeonId) {
        // Verificar si la tabla existe antes de consultar (Prevención de errores)
        try {
            $stmtPref = $pdo->prepare("
                SELECT glove_size, specific_instruments, notes 
                FROM SURGEON_PREFERENCES 
                WHERE surgeon_id = ? AND procedure_id = ?
            ");
            $stmtPref->execute([$surgeonId, $procId]);
            $dbPref = $stmtPref->fetch(PDO::FETCH_ASSOC);

            if ($dbPref) {
                if (!empty($dbPref['glove_size'])) $prefData['glove_size'] = $dbPref['glove_size'];
                if (!empty($dbPref['notes'])) $prefData['notes'] = $dbPref['notes'];
                if (!empty($dbPref['specific_instruments'])) {
                    $prefData['instruments'] = (strpos($dbPref['specific_instruments'], ',') !== false) 
                        ? array_map('trim', explode(',', $dbPref['specific_instruments'])) 
                        : [$dbPref['specific_instruments']];
                }
            }
        } catch (Exception $ex) {
            // Si la tabla no existe o falla, ignoramos y usamos defaults
        }
    }

    // LIMPIEZA FINAL Y ENVÍO
    ob_clean(); 
    echo json_encode([
        'success' => true,
        'consent_status' => $isConsentSigned,
        'preferences' => $prefData
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>