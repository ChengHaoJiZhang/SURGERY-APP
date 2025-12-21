<?php
// groupd/views/surgeon/api_patients.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

header('Content-Type: application/json');
$pdo = getDBConnection();

// GET: List patients with allergies
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $searchRaw = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Si no hay búsqueda, devolvemos vacío para no saturar
    if (strlen($searchRaw) < 2) {
        echo json_encode(['success' => true, 'patients' => []]);
        exit;
    }

    $searchTerm = "%$searchRaw%";
    
    // CORRECCIÓN CRÍTICA: Usamos :s1 y :s2 porque PDO sin emulación falla si repites nombres
    $sql = "SELECT 
        p.patient_id,
        p.first_name,
        p.surnames,
        p.date_of_birth,
        p.gender,
        p.phone,
        p.email,
        p.address,
        GROUP_CONCAT(DISTINCT a.allergen_name SEPARATOR ', ') as allergies
    FROM PATIENT p
    LEFT JOIN PATIENT_ALLERGIES pa ON p.patient_id = pa.patient_id
    LEFT JOIN ALLERGIES a ON pa.allergen_ID = a.allergen_ID
    WHERE p.first_name LIKE :s1 OR p.surnames LIKE :s2
    GROUP BY p.patient_id
    ORDER BY p.surnames, p.first_name
    LIMIT 20";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':s1' => $searchTerm, ':s2' => $searchTerm]);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'patients' => $patients]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ... (El resto de métodos POST/PUT/DELETE se mantienen igual si los usas) ...
?>