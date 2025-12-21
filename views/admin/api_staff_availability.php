<?php
// groupd/views/admin/api_staff_availability.php

// 1. Limpieza de buffer y manejo de errores silencioso
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. Sesión y Configuración
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../../config/config.php';

// 3. Seguridad
$controller = new LoginController();
$controller->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

// 4. Preparar respuesta JSON
ob_clean(); 
header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();

// 5. Recibir Parámetros
$date = $_GET['date'] ?? ''; // Formato: YYYY-MM-DDTHH:MM
$duration = $_GET['duration'] ?? 120; // Minutos
$specialityId = $_GET['spec'] ?? ''; // ID especialidad (ej. SP009)

if (empty($date)) {
    echo json_encode(['success' => false, 'error' => 'Date is required']);
    exit;
}

try {
    // Calcular rango de tiempo de la nueva cirugía
    $startTime = $date;
    $endTime = date('Y-m-d H:i:s', strtotime("$date + $duration minutes"));

    // --- LÓGICA DE DISPONIBILIDAD ---
    // Una persona está ocupada si tiene una cirugía (Scheduled/In Progress) 
    // que empieza antes de que termine la nuestra Y termina después de que empiece la nuestra.
    
    // Subconsulta genérica para encontrar IDs ocupados en SURGICAL_PROCEDURE_SURGEONS
    $busySql = "
        SELECT sps.surgeon_id 
        FROM SURGICAL_PROCEDURE_SURGEONS sps
        JOIN SURGICAL_PROCEDURE sp ON sps.surgical_procedure_id = sp.surgical_procedure_id
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        WHERE sp.status IN ('Scheduled', 'In Progress')
        AND (
            (sp.date_time < :end AND DATE_ADD(sp.date_time, INTERVAL p.default_duration MINUTE) > :start)
        )
    ";

    // A. BUSCAR CIRUJANOS DISPONIBLES (Filtrado por Especialidad + Disponibilidad)
    // Asumimos que la tabla SURGEONS tiene una columna 'specialization' o similar con el ID (SPxxx)
    $sqlSurg = "SELECT surgeon_id, first_name, surnames, specialization 
                FROM SURGEONS 
                WHERE specialization = :spec
                AND surgeon_id NOT IN ($busySql)
                ORDER BY surnames";
    
    $stmtS = $pdo->prepare($sqlSurg);
    $stmtS->execute([':spec' => $specialityId, ':start' => $startTime, ':end' => $endTime]);
    $surgeons = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    // B. BUSCAR ANESTESISTAS DISPONIBLES
    // Reutilizamos la lógica reemplazando tablas y columnas
    $busyAnesSql = str_replace(
        ['SURGICAL_PROCEDURE_SURGEONS', 'sps.surgeon_id'], 
        ['SURGICAL_PROCEDURE_ANESTHESIOLOGISTS', 'anesthesiologist_id'], 
        $busySql
    );
    
    $sqlAnes = "SELECT anesthesiologist_id, first_name, surnames 
                FROM ANESTHESIOLOGISTS 
                WHERE anesthesiologist_id NOT IN ($busyAnesSql)
                ORDER BY surnames";
    $stmtA = $pdo->prepare($sqlAnes);
    $stmtA->execute([':start' => $startTime, ':end' => $endTime]);
    $anes = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    // C. BUSCAR ENFERMERAS DISPONIBLES
    $busyNurseSql = str_replace(
        ['SURGICAL_PROCEDURE_SURGEONS', 'sps.surgeon_id'], 
        ['SURGICAL_PROCEDURE_NURSES', 'nurse_id'], 
        $busySql
    );
    
    $sqlNurse = "SELECT nurse_id, first_name, surnames 
                 FROM NURSES 
                 WHERE nurse_id NOT IN ($busyNurseSql)
                 ORDER BY surnames LIMIT 50"; // Límite para no saturar el select
    $stmtN = $pdo->prepare($sqlNurse);
    $stmtN->execute([':start' => $startTime, ':end' => $endTime]);
    $nurses = $stmtN->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'surgeons' => $surgeons,
        'anesthesiologists' => $anes,
        'nurses' => $nurses
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>