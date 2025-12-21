<?php
// views/director/api/personnel.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/config.php';

try {
    $pdo = getDBConnection();
    
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    // Helper function with dynamic column selection
    function getStaff($pdo, $table, $idCol, $specCol, $roleLabel, $search) {
        $params = [];
        $where = " WHERE t.deleted_at IS NULL";
        
        if (!empty($search)) {
            $term = "%$search%";
            $where .= " AND (
                t.first_name LIKE ? OR 
                t.surnames LIKE ? OR 
                CONCAT(t.first_name, ' ', t.surnames) LIKE ? OR 
                t.license_number LIKE ?
            )";
            $params = [$term, $term, $term, $term];
        }

        $sql = "SELECT 
                    t.$idCol as id, 
                    t.first_name, 
                    t.surnames, 
                    t.license_number, 
                    t.work_extension, 
                    COALESCE(s.Speciality_name, t.$specCol, 'General') as spec_name,
                    t.$specCol as spec_id,
                    '$roleLabel' as role 
                FROM $table t
                LEFT JOIN SPECIALITIES s ON t.$specCol = s.Speciality_ID
                $where";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 1. SURGEONS
    $surgeons = getStaff($pdo, 'SURGEONS', 'surgeon_id', 'specialization', 'Surgeon', $search);

    // 2. NURSES
    $nurses = getStaff($pdo, 'NURSES', 'nurse_id', 'Speciality_ID', 'Nurse', $search);

    // 3. ANESTHESIOLOGISTS
    $anes = getStaff($pdo, 'ANESTHESIOLOGISTS', 'anesthesiologist_id', 'specialization', 'Anesthesiologist', $search);

    // 4. ADMINISTRATION (NEW)
    $admins = getStaff($pdo, 'ADMINISTRATION', 'admin_id', 'Speciality_ID', 'Administrator', $search);

    // 5. Combine for search all
    $all = array_merge($surgeons, $nurses, $anes, $admins);

    echo json_encode([
        'surgeons' => $surgeons,
        'nurses' => $nurses,
        'anesthesiologists' => $anes,
        'administration' => $admins, // Added to response
        'all_results' => $all
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>