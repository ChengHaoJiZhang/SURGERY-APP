<?php
// File: groupd/views/patient/api/surgery_list.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';

// 1. Seguridad
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Recibir Filtros
$period = $_GET['period'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$type = $_GET['type'] ?? 'all';

try {
    $pdo = getDBConnection();

    // 3. Obtener TODOS los IDs del Paciente (Multi-Patient Support)
    $stmt = $pdo->prepare("SELECT P.patient_id FROM PATIENT P JOIN USERS U ON P.email = U.email WHERE U.user_id = ?");
    $stmt->execute([$user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($patient_ids)) {
        echo json_encode([]);
        exit;
    }

    // Preparar placeholders para IN clause
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    // 4. QUERY PRINCIPAL
    $sql = "
        SELECT 
            SP.surgical_procedure_id, 
            SP.date_time, 
            SP.status, 
            P.default_duration as duration, -- FIXED COLUMN NAME
            COALESCE(P.procedure_name, 'Surgical Procedure') as procedure_name, 
            COALESCE(ORR.room_number, 'TBD') as room_number,
            (SELECT COUNT(*) FROM PATIENT_CONSENTS PC 
             WHERE PC.episode_id = SP.episode_id 
             AND PC.procedure_id = SP.procedure_id 
             AND PC.signed = 0
            ) as pending_consents_count
        FROM SURGICAL_PROCEDURE SP
        LEFT JOIN EPISODE E ON SP.episode_id = E.episode_id
        LEFT JOIN PROCEDURES P ON SP.procedure_id = P.procedure_id
        LEFT JOIN OPERATING_ROOM ORR ON SP.operating_room_id = ORR.room_id
        WHERE SP.patient_id IN ($placeholders)
    ";

    $params = $patient_ids; // Start with patient IDs

    // 5. APLICAR FILTROS
    if ($status !== 'all') {
        $sql .= " AND SP.status = ?";
        $params[] = $status;
    }

    if ($type !== 'all') {
        $sql .= " AND P.procedure_name = ?";
        $params[] = $type;
    }

    switch ($period) {
        case 'day':
            $sql .= " AND DATE(SP.date_time) = CURDATE()";
            break;
        case 'month':
            $sql .= " AND MONTH(SP.date_time) = MONTH(CURDATE()) AND YEAR(SP.date_time) = YEAR(CURDATE())";
            break;
        case 'year':
            $sql .= " AND YEAR(SP.date_time) = YEAR(CURDATE())";
            break;
    }

    $sql .= " ORDER BY SP.date_time DESC";

    // 6. Ejecutar
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Formatear
    $formatted = array_map(function ($row) {
        $colors = [
            'Scheduled' => 'bg-primary',
            'Completed' => 'bg-success',
            'Cancelled' => 'bg-danger',
            'In Progress' => 'bg-warning text-dark'
        ];

        return [
            'id' => $row['surgical_procedure_id'],
            'name' => htmlspecialchars($row['procedure_name']),
            'date_time' => date('d/m/Y H:i', strtotime($row['date_time'])),
            'room' => htmlspecialchars($row['room_number']),
            'duration' => htmlspecialchars($row['duration'] ?? '-'),
            'status' => htmlspecialchars($row['status']),
            'status_class' => $colors[$row['status']] ?? 'bg-secondary',
            'pending_consents' => (int) $row['pending_consents_count']
        ];
    }, $results);

    echo json_encode($formatted);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>