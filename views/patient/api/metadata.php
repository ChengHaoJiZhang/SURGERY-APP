<?php
// Fitxer: groupd/views/dashboard/api/metadata.php

// 1. INICIA LA SESSIÓ (HA DE SER LA PRIMERA COSA QUE FA EL PHP)
session_start(); 

// 2. ENVIA LA CAPÇALERA JSON (Ha d'anar abans del require)
header('Content-Type: application/json');

// 3. LA RESTA DEL CODI PHP
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['procedures' => [], 'patient_name' => 'Pacient', 'patient_id' => null];

try {
    $pdo = getDBConnection();

    // 1. Obtenció del nom i ID del Pacient
    $sql_patient = "
        SELECT P.patient_id, P.first_name, P.surnames
        FROM PATIENT P
        JOIN USERS U ON P.email = U.email
        WHERE U.user_id = :user_id;
    ";
    $stmt = $pdo->prepare($sql_patient);
    $stmt->execute(['user_id' => $user_id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($details) {
        $response['patient_name'] = htmlspecialchars($details['first_name'] . ' ' . $details['surnames']);
        $response['patient_id'] = $details['patient_id'];
    }

    // 2. Obtenció de la llista de tipus de procediments per al desplegable de filtres
    $sql_procs = "SELECT DISTINCT procedure_name FROM PROCEDURES ORDER BY procedure_name";
    $response['procedures'] = $pdo->query($sql_procs)->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    http_response_code(500);
    $response['error'] = "Database error: " . $e->getMessage();
}

echo json_encode($response);