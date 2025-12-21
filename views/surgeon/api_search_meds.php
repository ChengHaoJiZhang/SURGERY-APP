<?php
// grupd/views/surgeon/api_search_meds.php

// 1. Limpiar salida previa
ob_start();

// 2. Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Incluir controlador
require_once __DIR__ . '/../../controllers/LoginController.php';

// Validar Auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
ob_clean(); // Limpiar buffers

try {
    $pdo = getDBConnection();
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (strlen($search) < 2) {
        echo json_encode([]); 
        exit;
    }

    // CORRECCIÓN: Usamos :term1 y :term2 para evitar el error de parámetros
    $sql = "SELECT drug_ID, comercial_name, active_principle, presentation 
            FROM MEDICATIONS 
            WHERE comercial_name LIKE :term1 
               OR active_principle LIKE :term2 
            ORDER BY comercial_name ASC 
            LIMIT 15";
            
    $stmt = $pdo->prepare($sql);
    
    // Pasamos el mismo valor a ambas variables
    $stmt->execute([
        ':term1' => "%$search%",
        ':term2' => "%$search%"
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>