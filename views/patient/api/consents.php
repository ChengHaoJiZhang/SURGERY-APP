<?php
// File: groupd/views/patient/api/consents.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();

    // 1. Obtener TODOS los IDs del Paciente
    $stmt = $pdo->prepare("SELECT P.patient_id FROM PATIENT P JOIN USERS U ON P.email = U.email WHERE U.user_id = ?");
    $stmt->execute([$user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($patient_ids)) {
        echo json_encode([]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    // =================================================================
    // 2. AUTO-GENERACIÓN DE CONSENTIMIENTOS FALTANTES (Self-Healing)
    // =================================================================

    // A. Insertar "Informed Surgical Consent" si falta
    $sqlFix1 = "
        INSERT INTO PATIENT_CONSENTS (episode_id, procedure_id, patient_id, consent_date, consent_type, signed)
        SELECT sp.episode_id, sp.procedure_id, sp.patient_id, CURDATE(), CONCAT('Informed Consent: ', p.procedure_name), 0
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        WHERE sp.patient_id IN ($placeholders)
        AND sp.status IN ('Scheduled', 'In Progress')
        AND NOT EXISTS (
            SELECT 1 FROM PATIENT_CONSENTS pc 
            WHERE pc.episode_id = sp.episode_id 
            AND pc.procedure_id = sp.procedure_id
            AND pc.consent_type LIKE 'Informed Consent%'
        )
    ";
    $pdo->prepare($sqlFix1)->execute($patient_ids);

    // B. Insertar "Anesthesia Consent" si falta
    $sqlFix2 = "
        INSERT INTO PATIENT_CONSENTS (episode_id, procedure_id, patient_id, consent_date, consent_type, signed)
        SELECT sp.episode_id, sp.procedure_id, sp.patient_id, CURDATE(), 'Anesthesia Consent', 0
        FROM SURGICAL_PROCEDURE sp
        WHERE sp.patient_id IN ($placeholders)
        AND sp.status IN ('Scheduled', 'In Progress')
        AND NOT EXISTS (
            SELECT 1 FROM PATIENT_CONSENTS pc 
            WHERE pc.episode_id = sp.episode_id 
            AND pc.procedure_id = sp.procedure_id
            AND pc.consent_type = 'Anesthesia Consent'
        )
    ";
    $pdo->prepare($sqlFix2)->execute($patient_ids);
    // =================================================================

    // 3. Obtener y Mostrar Consentimientos
    $sql = "
        SELECT 
            PC.consent_id,
            PC.consent_type,
            PC.consent_date,
            PC.signed,
            P.procedure_name
        FROM PATIENT_CONSENTS PC
        JOIN PROCEDURES P ON PC.procedure_id = P.procedure_id
        WHERE PC.patient_id IN ($placeholders)
        ORDER BY PC.signed ASC, PC.consent_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($patient_ids);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function ($row) {
        return [
            'id' => $row['consent_id'],
            'type' => htmlspecialchars($row['consent_type']),
            'procedure' => htmlspecialchars($row['procedure_name']),
            'date' => date('d/m/Y', strtotime($row['consent_date'])),
            'signed' => (bool) $row['signed'],
            'status_label' => $row['signed'] ? 'Signed' : 'Pending',
            'status_class' => $row['signed'] ? 'text-success' : 'text-danger fw-bold'
        ];
    }, $results);

    echo json_encode($formatted);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>