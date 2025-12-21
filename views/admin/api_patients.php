<?php
// groupd/views/admin/api_patients.php

// 1. SILENCIAR ERRORES VISUALES
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. INICIAR BUFFER Y SESIÓN
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// Solo Admin
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

// 3. LIMPIEZA Y CABECERAS
ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();
    
    // --- GET: LISTAR PACIENTES ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $searchRaw = isset($_GET['search']) ? trim($_GET['search']) : '';
        $searchTerm = "%$searchRaw%";
        
        // CORRECCIÓN: Usamos parámetros únicos (:s1, :s2, etc.)
        // porque la emulación de PDO está desactivada en tu config.php
        $sql = "SELECT 
            patient_id,
            first_name,
            surnames,
            date_of_birth,
            gender,
            phone,
            email,
            address,
            ID_document,
            Health_Card_Number
        FROM PATIENT
        WHERE first_name LIKE :s1 
           OR surnames LIKE :s2
           OR patient_id LIKE :s3
           OR ID_document LIKE :s4
        ORDER BY surnames, first_name
        LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        
        // Asignamos el mismo valor a los 4 marcadores distintos
        $stmt->execute([
            ':s1' => $searchTerm,
            ':s2' => $searchTerm,
            ':s3' => $searchTerm,
            ':s4' => $searchTerm
        ]);
        
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'patients' => $patients]);
    }

    // --- POST: CREAR PACIENTE ---
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) throw new Exception("Invalid JSON input");

        $pid = !empty($data['patientId']) ? $data['patientId'] : ('P-' . date('Y') . '-' . rand(1000, 9999));

        $sql = "INSERT INTO PATIENT (patient_id, first_name, surnames, date_of_birth, gender, phone, email, address, ID_document)
                VALUES (:pid, :first_name, :surnames, :dob, :gender, :phone, :email, :address, :dni)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pid' => $pid,
            ':first_name' => $data['firstName'],
            ':surnames' => $data['surnames'],
            ':dob' => $data['dateOfBirth'],
            ':gender' => $data['gender'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':address' => $data['address'],
            ':dni' => $data['dni'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'patient_id' => $pid]);
    }

    // --- PUT: ACTUALIZAR ---
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) throw new Exception("Invalid JSON input");

        $sql = "UPDATE PATIENT SET 
                first_name = :first_name,
                surnames = :surnames,
                date_of_birth = :dob,
                gender = :gender,
                phone = :phone,
                email = :email,
                address = :address,
                ID_document = :dni
                WHERE patient_id = :patient_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $data['firstName'],
            ':surnames' => $data['surnames'],
            ':dob' => $data['dateOfBirth'],
            ':gender' => $data['gender'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':address' => $data['address'],
            ':dni' => $data['dni'],
            ':patient_id' => $data['patientId']
        ]);
        
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    // Enviamos el mensaje de error limpio en JSON
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
?>