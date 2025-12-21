<?php
// 1. INICIA LA SESSIÓ (HA DE SER LA PRIMERA COSA QUE FA EL PHP)
session_start();
// Fitxer: groupd/views/dashboard/api/clinical_data.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php'; // 4 nivells amunt

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data_type = $_GET['type'] ?? '';
$limit = $_GET['limit'] ?? 5;

try {
    $pdo = getDBConnection();

    // Obtenció del Patient ID
    $sql_pid = "
        SELECT P.patient_id FROM PATIENT P
        JOIN USERS U ON P.email = U.email
        WHERE U.user_id = :user_id;
    ";
    $stmt = $pdo->prepare($sql_pid);
    $stmt->execute(['user_id' => $user_id]);
    $patient_id = $stmt->fetchColumn();

    if (!$patient_id) {
        throw new Exception("Patient not linked to user.");
    }
    
    $results = [];

    // === Lògica de dades clíniques ===
    if ($data_type === 'imaging') {
        $sql = "
            SELECT study_type, study_date, results
            FROM IMAGING_STUDIES
            WHERE patient_id = :pid
            ORDER BY study_date DESC
            LIMIT :limit;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':pid', $patient_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = array_map(function($row) {
            return [
                'study_type' => htmlspecialchars($row['study_type']),
                'date' => date('d/m/Y', strtotime($row['study_date'])),
                'results' => htmlspecialchars($row['results'])
            ];
        }, $results);
        
    } elseif ($data_type === 'lab') {
         $sql = "
            SELECT LT.test_name, LR.result_value, LR.result_date
            FROM LAB_RESULTS LR
            JOIN LAB_TESTS_EPISODES LTE ON LR.test_episode_id = LTE.test_episode_id
            JOIN LAB_TESTS LT ON LTE.test_id = LT.test_id
            JOIN EPISODE E ON LTE.episode_id = E.episode_id
            WHERE E.patient_id = :pid 
            ORDER BY LR.result_date DESC
            LIMIT :limit;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':pid', $patient_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = array_map(function($row) {
            return [
                'test_name' => htmlspecialchars($row['test_name']),
                'date' => date('d/m/Y', strtotime($row['result_date'])),
                'result_value' => htmlspecialchars($row['result_value'])
            ];
        }, $results);
    }

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>