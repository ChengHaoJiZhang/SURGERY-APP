<?php
// File: groupd/views/patient/api/clinical_data_fixed.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';

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

    // 1. Obtener TODOS los IDs del Paciente (Multi-Patient Support)
    $stmt = $pdo->prepare("SELECT P.patient_id FROM PATIENT P JOIN USERS U ON P.email = U.email WHERE U.user_id = ?");
    $stmt->execute([$user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($patient_ids)) {
        echo json_encode([]);
        exit;
    }

    // Preparar placeholders para IN clause
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    $results = [];

    // === Lògica de dades clíniques ===
    if ($data_type === 'imaging') {
        $sql = "
            SELECT study_type, study_date, results
            FROM IMAGING_STUDIES
            WHERE patient_id IN ($placeholders)
            ORDER BY study_date DESC
            LIMIT ?;
        ";
        // Merge patient_ids with limit for params
        $params = $patient_ids;
        $params[] = (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = array_map(function ($row) {
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
            WHERE E.patient_id IN ($placeholders)
            ORDER BY LR.result_date DESC
            LIMIT ?;
        ";
        // Merge patient_ids with limit for params
        $params = $patient_ids;
        $params[] = (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = array_map(function ($row) {
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