<?php
// grupd/views/nurse/fill_alicia_data.php
// Script temporal para completar la información faltante de la paciente Alicia Ibañez Vera (SPID 103).

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
// No need for LoginController or session check as this is a utility script

$message = "";
$status_color = "danger";
$patient_id = 'AIV_0001';
$spid = 103;

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // --- Data Updates ---

    // 1. Update Surgery Status: Assign OR 4, Ward 7, Priority Emergency, and log initial times
    $sql1 = "
        UPDATE SURGICAL_PROCEDURE
        SET operating_room_id = 4, 
            ward_id = 7, 
            priority_level = 'Emergency',
            time_in_room = '2025-12-05 23:35:00', 
            time_incision = '2025-12-05 23:55:00'
        WHERE surgical_procedure_id = :spid AND patient_id = :pid
    ";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([':spid' => $spid, ':pid' => $patient_id]);
    $updates_sp = $stmt1->rowCount();

    // 2. Update Bed Status (Bed 7 is in Ward 7: General Surgery Ward)
    $sql2 = "UPDATE BEDS SET bed_status = 'Occupied' WHERE bed_id = 7";
    $pdo->exec($sql2);
    
    // 3. Add Critical Allergy (Penicillin/Beta-Lactams - AL002)
    $sql3 = "
        INSERT IGNORE INTO PATIENT_ALLERGIES (patient_id, allergen_ID, diagnosed_date, severity)
        VALUES (:pid, 'AL002', '2025-12-05', 'Life-threatening')
    ";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([':pid' => $patient_id]);
    $updates_allergies = $stmt3->rowCount();

    // 4. Insert initial Vitals Monitoring for the live case
    $sql4 = "
        INSERT IGNORE INTO SURGERY_MONITORING 
        (surgical_procedure_id, card_freq, sys, dias, tidal_CO2, timestamp)
        VALUES 
        (:spid, 85, 135, 90, 98.00, '2025-12-05 23:40:00'),
        (:spid, 82, 128, 86, 99.00, '2025-12-06 00:00:00')
    ";
    $stmt4 = $pdo->prepare($sql4);
    $stmt4->execute([':spid' => $spid]);
    $updates_vitals = $stmt4->rowCount();

    // 5. Record Intra-operative Medication (D0026: Propofol)
    $sql5 = "
        INSERT IGNORE INTO SURGICAL_PROCEDURE_MEDICATIONS 
        (surgical_procedure_id, drug_ID, prescribed_datetime, prescribed_by, dosage)
        VALUES (:spid, 'D0026', '2025-12-05 23:58:00', '105', '100mg IV Bolus')
    ";
    $stmt5 = $pdo->prepare($sql5);
    $stmt5->execute([':spid' => $spid]);
    $updates_meds = $stmt5->rowCount();


    $pdo->commit();
    
    $message = "✅ **DATA CONFIGURATION SUCCESSFUL for SPID $spid (Alicia Ibañez Vera)**";
    $message .= "<br><br>The following critical data was added/updated:";
    $message .= "<ul>";
    $message .= "<li>**Location:** Assigned to **OR-EMG-1 (Room 4)** and **General Surgery Ward (Bed 7)**.</li>";
    $message .= "<li>**Milestones:** `time_in_room` and `time_incision` recorded.</li>";
    $message .= "<li>**Critical Safety:** **Life-threatening Penicillin Allergy** (`AL002`) added ($updates_allergies rows inserted).</li>";
    $message .= "<li>**Vitals:** 2 initial vital sign entries logged (`HR`, `BP`, `SpO2`) ($updates_vitals rows inserted).</li>";
    $message .= "<li>**Medication History:** 1 dose of **Propofol** (`D0026`) recorded ($updates_meds rows inserted).</li>";
    $message .= "</ul>";
    $status_color = "success";


} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $message = "❌ **ERROR CRÍTICO EN DB:** Operación fallida. Verifique la conexión y que los IDs existan. Detalles: " . $e->getMessage();
    $status_color = "danger";
} catch (Exception $e) {
    $message = "❌ **ERROR GENERAL:** " . $e->getMessage();
    $status_color = "danger";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alicia Ibañez Data Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Resultado de la Carga de Datos Clínicos</h3>
        
        <div class="alert alert-<?php echo $status_color; ?>">
            <p class="lead fw-bold mb-0"><?php echo $message; ?></p>
        </div>

        <a href="nurse_dashboard.php" class="btn btn-primary">Go to Nurse Dashboard</a>
    </div>
</div>
</body>
</html>