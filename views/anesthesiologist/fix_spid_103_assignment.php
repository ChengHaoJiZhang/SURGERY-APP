<?php
// grupd/views/anesthesiologist/fix_spid_103_assignment.php
// Assigns the 'In Progress' surgery (SPID 103) to Anesthesiologist ID 1 (dr_sheldon).

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';

$message = "";
$status_color = "danger";
$spid_to_fix = 103;
$target_anes_id = 1; // Anesthesiologist ID for dr_sheldon

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // 1. Delete old assignment to Anesthesiologist ID 5 (Dr. Wilson)
    $pdo->prepare("DELETE FROM SURGICAL_PROCEDURE_ANESTHESIOLOGISTS WHERE surgical_procedure_id = ? AND anesthesiologist_id = 5")
        ->execute([$spid_to_fix]);

    // 2. Insert new assignment to Anesthesiologist ID 1 (dr_sheldon)
    $sql = "INSERT INTO SURGICAL_PROCEDURE_ANESTHESIOLOGISTS 
            (surgical_procedure_id, anesthesiologist_id, role_in_surgery, created_at) 
            VALUES (?, ?, 'Lead Anesthesiologist', NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()"; // Upsert logic
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$spid_to_fix, $target_anes_id]);

    // 3. Ensure the surgery is still in 'In Progress' status
    $pdo->prepare("UPDATE SURGICAL_PROCEDURE SET status = 'In Progress', operating_room_id = 4, updated_at = NOW() WHERE surgical_procedure_id = ?")
        ->execute([$spid_to_fix]);

    $pdo->commit();
    
    $message = "✅ **RE-ASSIGNMENT SUCCESSFUL**";
    $message .= "<br>Surgery SPID **{$spid_to_fix} (Alicia Ibañez)** is now assigned to Anesthesiologist ID **{$target_anes_id}** and should appear as a **Live Operation** on the dashboard.";
    $status_color = "success";

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $message = "❌ **CRITICAL DB ERROR:** Failed to assign surgery. Details: " . $e->getMessage();
    $status_color = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Surgery Assignment Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Live Surgery Assignment Fix Result</h3>
        
        <div class="alert alert-<?php echo $status_color; ?>">
            <p class="lead fw-bold mb-0"><?php echo $message; ?></p>
        </div>

        <a href="anesthesiologist_dashboard.php" class="btn btn-primary">Go to Dashboard</a>
    </div>
</div>
</body>
</html>