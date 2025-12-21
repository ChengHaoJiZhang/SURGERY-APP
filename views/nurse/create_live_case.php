<?php
// views/create_live_case.php
// Script temporal para crear un paciente y una cirugía 'In Progress' para demostración.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$pdo = getDBConnection();
$message = "";

// --- PARAMETERS ---
$new_patient_id = 'AIV_0001';
$nurse_id_carla = 1; 
$procedure_id = 1; // Appendectomy
$current_time = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    // 1. CLEANUP (Prevent duplicate patient ID if run multiple times)
    $pdo->prepare("DELETE FROM PATIENT WHERE patient_id = :pid")->execute([':pid' => $new_patient_id]);

    // 2. CREATE NEW PATIENT
    $stmtPat = $pdo->prepare("
        INSERT INTO PATIENT (patient_id, first_name, surnames, date_of_birth, gender, phone, email) 
        VALUES (:pid, 'Alicia', 'Ibañez Vera', '1985-06-15', 'F', '601234567', 'alicia.vera@test.com')
    ");
    $stmtPat->execute([':pid' => $new_patient_id]);
    
    // 3. CREATE NEW EPISODE
    $stmtEp = $pdo->prepare("
        INSERT INTO EPISODE (patient_id, date_time_start, reason, status)
        VALUES (:pid, :dt, 'Emergency Appendectomy', 'Active')
    ");
    $stmtEp->execute([':pid' => $new_patient_id, ':dt' => $current_time]);
    $new_episode_id = $pdo->lastInsertId();

    // 4. CREATE NEW SURGICAL PROCEDURE (Set to 'In Progress')
    $stmtSp = $pdo->prepare("
        INSERT INTO SURGICAL_PROCEDURE (procedure_id, episode_id, date_time, status, operating_room_id, patient_id)
        VALUES (:procid, :epid, :dt, 'In Progress', 1, :pid)
    ");
    $stmtSp->execute([
        ':procid' => $procedure_id,
        ':epid' => $new_episode_id,
        ':dt' => $current_time,
        ':pid' => $new_patient_id
    ]);
    $new_spid = $pdo->lastInsertId();

    // 5. ASSIGN NURSE CARLA (Nurse ID 1)
    $stmtAssign = $pdo->prepare("
        INSERT INTO SURGICAL_PROCEDURE_NURSES (surgical_procedure_id, nurse_id, role_in_surgery)
        VALUES (:spid, :nid, 'Circulating Nurse')
    ");
    $stmtAssign->execute([':spid' => $new_spid, ':nid' => $nurse_id_carla]);

    $pdo->commit();
    
    $message = "✅ **CASO CREADO:** Nueva paciente Alicia Ibañez Vera ($new_patient_id) creada.";
    $message .= "<br>✅ Cirugía Appendectomy (SPID $new_spid) forzada a estado: **'In Progress'** y asignada a Nurse Carla.";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = "❌ **ERROR DE DB:** La operación falló. Detalles: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Crear Caso en Vivo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h3>Resultado de Creación de Caso</h3>
        <div class="alert alert-info">
            <p class="lead fw-bold mb-0"><?php echo $message; ?></p>
        </div>
        <p class="mt-4">Por favor, vaya al Dashboard para confirmar que el nuevo caso aparece como 'Live Cockpit' y que el contador de tareas es ahora **3** (Scheduled, In Progress, Completed).</p>
        <a href="../../login.php" class="btn btn-primary">Ir a Login</a>
    </div>
</body>
</html>