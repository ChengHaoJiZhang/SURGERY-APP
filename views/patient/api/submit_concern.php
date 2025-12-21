<?php
// File: groupd/views/patient/api/submit_concern.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['message'])) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

try {
    $pdo = getDBConnection();

    // 1. Get Patient ID (Use the first one found for simplicity, or could prompt user if multiple)
    // Ideally, we should link it to a specific patient record if they have multiple.
    // For now, we'll pick the first one associated with the user.
    $stmt = $pdo->prepare("SELECT patient_id FROM PATIENT P JOIN USERS U ON P.email = U.email WHERE U.user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $patient_id = $stmt->fetchColumn();

    if (!$patient_id) {
        throw new Exception("Patient not found.");
    }

    // 2. Insert Concern
    $sql = "INSERT INTO PATIENT_CONCERNS (patient_id, message) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$patient_id, $input['message']]);

    if ($result) {
        // Send Email Notification
        $subject = "New Patient Concern: " . $patient_id;
        $body = "Patient ID: $patient_id<br>Message: " . htmlspecialchars($input['message']);
        sendEmail('info@imtm2025.digital', $subject, $body);

        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to save concern.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>