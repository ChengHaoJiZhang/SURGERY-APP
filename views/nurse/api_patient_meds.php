<?php
// groupd/views/nurse/api_patient_meds.php
// Fetches intra-operative medication history (SURGICAL_PROCEDURE_MEDICATIONS)

ob_start();
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// FIX: Allow access for both Nurse and Anesthesiologist (and Admin) roles
$role = strtolower($_SESSION['role_name'] ?? '');
if (strpos($role, 'nurse') === false && strpos($role, 'anesthesiologist') === false && strpos($role, 'admin') === false) {
    ob_clean(); http_response_code(403); echo json_encode(['error' => 'Access Denied: Nurse or Anesthesiologist role required.']); exit;
}


header('Content-Type: application/json');
ob_clean(); 
$pdo = getDBConnection();

$spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;

if ($spid === 0) {
    echo json_encode(['success' => false, 'error' => 'Surgical Procedure ID (spid) is missing.']);
    exit;
}

try {
    $sql = "
        SELECT 
            spm.prescribed_datetime, 
            spm.dosage, 
            m.comercial_name, 
            u.username as prescribed_by_name
        FROM SURGICAL_PROCEDURE_MEDICATIONS spm
        JOIN MEDICATIONS m ON spm.drug_ID = m.drug_ID
        -- Join USERS for the name of the person who administered it (prescribed_by)
        -- Casting prescribed_by to unsigned integer for reliable join if column types mismatch
        LEFT JOIN USERS u ON CAST(spm.prescribed_by AS UNSIGNED) = u.user_id
        WHERE spm.surgical_procedure_id = :spid
        ORDER BY spm.prescribed_datetime DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':spid' => $spid]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'history' => $history,
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log("Medication API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>