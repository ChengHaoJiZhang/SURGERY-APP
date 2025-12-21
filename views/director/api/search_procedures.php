<?php
// views/director/api/search_procedures.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

try {
    $pdo = getDBConnection();
    $query = $_GET['q'] ?? '';
    $spec_id = $_GET['spec_id'] ?? '';

    // --- SCENARIO 1: FILTER BY SPECIALTY (Sidebar Click) ---
    if (!empty($spec_id)) {
        // Query directly by Speciality_ID column
        $sql = "SELECT procedure_id, procedure_name, description, icd_code, snomed_id 
                FROM PROCEDURES 
                WHERE Speciality_ID = :spec_id
                ORDER BY procedure_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':spec_id' => $spec_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } 
    // --- SCENARIO 2: GLOBAL SEARCH BAR (Predictive) ---
    elseif (strlen($query) > 0) {
        $term = "%$query%";
        // Searches Name, ICD, SNOMED, and Description
        $sql = "SELECT procedure_id, procedure_name, description, icd_code, snomed_id 
                FROM PROCEDURES 
                WHERE procedure_name LIKE ? 
                   OR icd_code LIKE ? 
                   OR icd_term LIKE ?
                   OR snomed_id LIKE ?
                   OR snomed_term LIKE ?
                ORDER BY procedure_name ASC LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$term, $term, $term, $term, $term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } else {
        echo json_encode([]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>