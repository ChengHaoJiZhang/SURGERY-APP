<?php
// File: groupd/views/patient/api/sign_consent.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['consent_id'])) {
    echo json_encode(['error' => 'Missing consent ID']);
    exit;
}

try {
    $pdo = getDBConnection();

    // 2. Get ALL Patient IDs from User ID
    $stmt = $pdo->prepare("SELECT patient_id FROM PATIENT P JOIN USERS U ON P.email = U.email WHERE U.user_id = ?");
    $stmt->execute([$user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($patient_ids)) {
        throw new Exception("Patient not found.");
    }

    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    // 3. Update the Consent (Securely ensuring it belongs to ONE of the patient's IDs)
    $sql = "UPDATE PATIENT_CONSENTS 
            SET signed = 1, consent_date = CURDATE() 
            WHERE consent_id = ? AND patient_id IN ($placeholders)";

    $params = array_merge([$input['consent_id']], $patient_ids);
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Consent not found or already signed.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>