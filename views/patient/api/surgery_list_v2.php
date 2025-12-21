<?php
// 1. INICIA LA SESSIÓ (HA DE SER LA PRIMERA COSA QUE FA EL PHP)
session_start();
// Fitxer: groupd/views/dashboard/api/surgery_list.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php'; // 4 nivells amunt

// if (!isset($_SESSION['user_id'])) {
//     if (isset($_GET['debug_bypass'])) {
//         $_SESSION['user_id'] = 3; // Mock user
//     } else {
//         http_response_code(401);
//         echo json_encode(['error' => 'Unauthorized']);
//         exit;
//     }
// }

$_SESSION['user_id'] = 3; // HARDCODED FOR DEBUGGING
$user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$type = $_GET['type'] ?? 'all';

try {
    $pdo = getDBConnection();

    // Obtenció dels Patient IDs (Pot haver-hi duplicats, els agafem tots)
    $sql_pid = "
        SELECT P.patient_id FROM PATIENT P
        JOIN USERS U ON P.email = U.email
        WHERE U.user_id = :user_id;
    ";
    $stmt = $pdo->prepare($sql_pid);
    $stmt->execute(['user_id' => $user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // DEBUG LOGGING
    file_put_contents('/tmp/surgery_debug.txt', "User ID: $user_id\nPatient IDs: " . print_r($patient_ids, true) . "\nParams: " . print_r($params, true) . "\nSQL: $sql\n", FILE_APPEND);

    if (empty($patient_ids)) {
        throw new Exception("Patient not linked to user.");
    }

    // ... (rest of logic) ...

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    file_put_contents('/tmp/surgery_debug.txt', "Results count: " . count($results) . "\n", FILE_APPEND);

    // Format per a JSON
    $formatted_results = array_map(function ($row) {
        $status_colors = ['Scheduled' => 'text-bg-primary', 'Completed' => 'text-bg-success', 'Cancelled' => 'text-bg-danger', 'In Progress' => 'text-bg-warning'];

        return [
            'id' => $row['surgical_procedure_id'],
            'name' => htmlspecialchars($row['procedure_name']),
            'date_time' => date('d/m/Y H:i', strtotime($row['date_time'])),
            'room' => htmlspecialchars($row['room_number'] ?? 'N/A'),
            'duration' => htmlspecialchars($row['duration'] ?? '-'),
            'status' => htmlspecialchars($row['status']),
            'status_class' => $status_colors[$row['status']] ?? 'text-bg-secondary',
        ];
    }, $results);

    echo json_encode($formatted_results);

} catch (Exception $e) {
    file_put_contents('/tmp/surgery_error.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>