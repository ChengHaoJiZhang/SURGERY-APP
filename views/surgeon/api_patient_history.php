<?php
// groupd/views/surgeon/api_patient_history.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
$controller = new LoginController();
$controller->checkAuth();

header('Content-Type: application/json');
$pdo = getDBConnection();
$pid = $_GET['pid'] ?? '';

if (empty($pid)) {
    echo json_encode(['error' => 'No patient ID provided']);
    exit;
}

try {
    // 1. SURGICAL HISTORY
    $sqlHistory = "SELECT sp.surgical_procedure_id, sp.date_time, p.procedure_name, sp.status, sp.episode_id
                   FROM SURGICAL_PROCEDURE sp
                   JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                   JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                   WHERE ep.patient_id = :pid ORDER BY sp.date_time DESC";
    $stmtH = $pdo->prepare($sqlHistory);
    $stmtH->execute([':pid' => $pid]);
    $history = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    // 2. PATIENT DETAILS
    $sqlPatient = "SELECT first_name, surnames, date_of_birth, gender, phone, email, address, patient_id 
                   FROM PATIENT WHERE patient_id = :pid";
    $stmtP = $pdo->prepare($sqlPatient);
    $stmtP->execute([':pid' => $pid]);
    $patient = $stmtP->fetch(PDO::FETCH_ASSOC);

    // 3. ALLERGIES
    $sqlAllergies = "SELECT a.allergen_name, pa.severity 
                     FROM PATIENT_ALLERGIES pa 
                     JOIN ALLERGIES a ON pa.allergen_ID = a.allergen_ID 
                     WHERE pa.patient_id = :pid";
    $stmtA = $pdo->prepare($sqlAllergies);
    $stmtA->execute([':pid' => $pid]);
    $allergies = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    // Return composite object
    echo json_encode([
        'patient' => $patient,
        'history' => $history,
        'allergies' => $allergies
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>