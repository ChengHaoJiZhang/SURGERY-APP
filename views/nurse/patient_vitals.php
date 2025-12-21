<?php
// groupd/views/nurse/api_patient_vitals.php
require_once __DIR__ . '/../../config/config.php';

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

$spid = $_GET['spid'] ?? 0;

try {
    if (!$spid) throw new Exception("Missing SPID");
    $pdo = getDBConnection();

    // 1. DATOS DEL PACIENTE
    $stmt = $pdo->prepare("
        SELECT p.patient_id, p.first_name, p.surnames, p.date_of_birth, p.gender, 
               proc.procedure_name, sp.episode_id
        FROM SURGICAL_PROCEDURE sp
        JOIN PATIENT p ON sp.patient_id = p.patient_id
        JOIN PROCEDURES proc ON sp.procedure_id = proc.procedure_id
        WHERE sp.surgical_procedure_id = ?
    ");
    $stmt->execute([$spid]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) throw new Exception("Patient not found");

    // 2. ALERGIAS
    $stmtAllergies = $pdo->prepare("
        SELECT a.allergen_name, pa.severity 
        FROM PATIENT_ALLERGIES pa 
        JOIN ALLERGIES a ON pa.allergen_ID = a.allergen_ID 
        WHERE pa.patient_id = ?
    ");
    $stmtAllergies->execute([$patient['patient_id']]);
    $allergies = $stmtAllergies->fetchAll(PDO::FETCH_ASSOC);

    // 3. LABS (Opcional - ya lo eliminaste de la vista pero la API puede devolverlo vacío sin error)
    $labs = []; 
    // Si quisieras restaurarlo, aquí iría la query a LAB_TESTS_EPISODES

    ob_clean();
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'allergies' => $allergies,
        'labs' => $labs
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>