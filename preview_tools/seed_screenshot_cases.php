<?php
declare(strict_types=1);

// Local, fictional presentation fixtures. The repository SQL dump is never edited.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
if (getenv('SURGERY_PREVIEW_FIXTURES') !== '1' || DB_TYPE !== 'local'
    || DB_NAME !== 'imtm2025_grupd' || DB_HOST_LOCAL !== '127.0.0.1:3308') {
    fwrite(STDERR, "Refusing to change a database outside the isolated preview.\n");
    exit(1);
}

$pdo = getDBConnection();
$nurseId = 900002;
$pendingId = 900003;
$marker = '[MOCK SCREENSHOT DEMO]';

try {
    $oxygenColumn = $pdo->query("SHOW COLUMNS FROM SURGERY_MONITORING LIKE 'oxygen_saturation'")->fetch();
    if (!$oxygenColumn) {
        throw new RuntimeException('Seed the anesthesia demo first to add oxygen_saturation.');
    }
    $pdo->beginTransaction();
    foreach ([$nurseId, $pendingId] as $id) {
        $stmt = $pdo->prepare('SELECT notes FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && !str_contains((string)$existing['notes'], $marker)) {
            throw new RuntimeException("Case ID $id belongs to non-demo data.");
        }
    }

    // Repair one incoherent case in the imported demo dataset: a knee replacement
    // cannot have incision, closure and room exit within about one minute.
    $pdo->exec("UPDATE SURGICAL_PROCEDURE SET
        time_in_room = '2025-12-11 10:30:00', time_intubation = '2025-12-11 10:38:00',
        time_incision = '2025-12-11 10:55:00', time_closure = '2025-12-11 12:20:00',
        time_extubation = '2025-12-11 12:25:00', time_out_room = '2025-12-11 12:35:00',
        safety_checklist_data = '{\"patient\":true,\"anesthesia\":true,\"timeout\":true,\"counts\":true,\"specimen\":true}',
        blood_loss_ml = 200, post_op_diagnosis = 'Osteoarthritis of knee',
        surgical_report = 'FINDINGS: Degenerative changes of the knee joint.\\n\\nTECHNIQUE: Femoral and tibial surfaces prepared; trial components assessed for alignment and stability; definitive components implanted.\\n\\nSPECIMENS: None.\\n\\nESTIMATED BLOOD LOSS: 200 mL.\\n\\nCOMPLICATIONS: None documented.',
        post_op_instructions = 'Routine postoperative monitoring, wound review and mobilisation per treating team.'
        WHERE surgical_procedure_id = 173");
    $pdo->exec("UPDATE SURGICAL_PROCEDURE_MEDICATIONS SET prescribed_datetime = '2025-12-11 10:35:00'
        WHERE surgical_procedure_id = 173 AND drug_ID = 'D0026'");
    $pdo->exec("UPDATE SURGICAL_PROCEDURE_MEDICATIONS SET prescribed_datetime = '2025-12-11 10:36:00'
        WHERE surgical_procedure_id = 173 AND drug_ID = 'D0034'");
    // The imported demonstration rows used tidal_CO2 to hold pulse oximetry.
    $pdo->exec("UPDATE SURGERY_MONITORING SET oxygen_saturation = ROUND(tidal_CO2), tidal_CO2 = NULL
        WHERE surgical_procedure_id = 173 AND oxygen_saturation IS NULL AND tidal_CO2 BETWEEN 90 AND 100");

    $pdo->exec("INSERT IGNORE INTO SURGICAL_PROCEDURE
        (surgical_procedure_id, procedure_id, episode_id, date_time, status, notes,
         operating_room_id, patient_id, asa_score, priority_level)
        SELECT 900002, procedure_id, episode_id, NOW(), 'In Progress',
               '[MOCK SCREENSHOT DEMO] Fictional ongoing knee-replacement example.',
               operating_room_id, patient_id, asa_score, priority_level
        FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = 173");
    $pdo->exec("UPDATE SURGICAL_PROCEDURE SET
        date_time = DATE_SUB(NOW(), INTERVAL 65 MINUTE),
        time_in_room = DATE_SUB(NOW(), INTERVAL 60 MINUTE),
        time_intubation = DATE_SUB(NOW(), INTERVAL 56 MINUTE),
        time_incision = DATE_SUB(NOW(), INTERVAL 40 MINUTE),
        expected_end_time = DATE_ADD(NOW(), INTERVAL 50 MINUTE),
        time_closure = NULL, time_extubation = NULL, time_out_room = NULL,
        status = 'In Progress', blood_loss_ml = 0, surgical_report = NULL,
        safety_checklist_data = '{\"patient\":true,\"anesthesia\":true,\"timeout\":true,\"counts\":false,\"specimen\":false}'
        WHERE surgical_procedure_id = 900002");
    $pdo->exec("DELETE FROM SURGERY_MONITORING WHERE surgical_procedure_id = 900002");
    $pdo->exec("DELETE FROM SURGICAL_PROCEDURE_MEDICATIONS WHERE surgical_procedure_id = 900002");
    $pdo->exec("DELETE FROM SURGICAL_MATERIAL_USAGE WHERE surgical_procedure_id = 900002");

    $now = new DateTimeImmutable((string)$pdo->query('SELECT NOW()')->fetchColumn());
    $samples = [
        // Minutes before capture, HR, systolic, diastolic, SpO2.
        [20, 78, 118, 76, 98], [17, 79, null, null, 98],
        [15, 79, 119, 77, 98], [12, 80, null, null, 99],
        [10, 81, 120, 78, 99], [7, 80, null, null, 98],
        [5, 80, 119, 77, 98], [2, 79, null, null, 98],
        [0, 79, 118, 76, 98],
    ];
    $insertVital = $pdo->prepare("INSERT INTO SURGERY_MONITORING
        (surgical_procedure_id, card_freq, sys, dias, oxygen_saturation, timestamp, observations)
        VALUES (?, ?, ?, ?, ?, ?, 'Synthetic screenshot data')");
    foreach ($samples as [$minutes, $hr, $sys, $dias, $spo2]) {
        $insertVital->execute([$nurseId, $hr, $sys, $dias, $spo2,
            $now->modify("-$minutes minutes")->format('Y-m-d H:i:s')]);
    }
    $pdo->exec("INSERT INTO SURGICAL_PROCEDURE_MEDICATIONS
        (surgical_procedure_id, drug_ID, prescribed_datetime, prescribed_by, dosage)
        VALUES (900002, 'D0026', DATE_SUB(NOW(), INTERVAL 56 MINUTE), '130', '100mg'),
               (900002, 'D0034', DATE_SUB(NOW(), INTERVAL 55 MINUTE), '130', '50mcg')");

    // A fictional, pending elective case makes the scheduler demonstration useful.
    $pdo->exec("INSERT IGNORE INTO SURGICAL_PROCEDURE
        (surgical_procedure_id, procedure_id, episode_id, date_time, status, notes,
         operating_room_id, patient_id, asa_score, priority_level)
        SELECT 900003, procedure_id, episode_id, DATE_ADD(CURDATE(), INTERVAL 7 DAY),
               'Pending', '[MOCK SCREENSHOT DEMO] Fictional elective scheduling example.',
               NULL, patient_id, asa_score, 'Elective'
        FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = 156");
    $pdo->exec("UPDATE SURGICAL_PROCEDURE SET status = 'Pending',
        date_time = DATE_ADD(CURDATE(), INTERVAL 7 DAY), operating_room_id = NULL,
        expected_end_time = NULL, time_in_room = NULL, time_intubation = NULL,
        time_incision = NULL, time_closure = NULL, time_extubation = NULL,
        time_out_room = NULL, priority_level = 'Elective'
        WHERE surgical_procedure_id = 900003");

    // Replace past expiries from the 2025 source snapshot in this local demo only.
    $pdo->exec("UPDATE SURGICAL_INVENTORY SET expiration_date = DATE_ADD(CURDATE(), INTERVAL 12 MONTH)
        WHERE material_id = 2");
    $pdo->exec("UPDATE SURGICAL_INVENTORY SET expiration_date = DATE_ADD(CURDATE(), INTERVAL 9 MONTH)
        WHERE material_id = 10");
    $pdo->exec("UPDATE SURGICAL_INVENTORY SET expiration_date = DATE_ADD(CURDATE(), INTERVAL 15 MONTH)
        WHERE material_id = 12");
    $pdo->exec("UPDATE SURGICAL_INVENTORY SET expiration_date = NULL WHERE material_id = 14");
    $pdo->exec("UPDATE SURGICAL_INVENTORY SET expiration_date = DATE_ADD(CURDATE(), INTERVAL 9 MONTH)
        WHERE material_id IN (11, 13)");
    // Available stock reflects the 12 fictional units used by the ongoing case.
    $pdo->exec("UPDATE SURGICAL_INVENTORY SET quantity_available = CASE material_id
        WHEN 1 THEN 698 WHEN 2 THEN 499 WHEN 3 THEN 2499 WHEN 5 THEN 7992
        ELSE quantity_available END WHERE material_id IN (1, 2, 3, 5)");
    $pdo->exec("INSERT INTO SURGICAL_MATERIAL_USAGE
        (surgical_procedure_id, material_id, amount_used, created_at)
        VALUES (900002, 1, 2, DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
               (900002, 2, 1, DATE_SUB(NOW(), INTERVAL 32 MINUTE)),
               (900002, 3, 1, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
               (900002, 5, 8, DATE_SUB(NOW(), INTERVAL 20 MINUTE))");

    $pdo->commit();
    echo "Seeded coherent fictional cases 900002 and 900003; corrected the local 2025 source example.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Screenshot fixture failed: " . $e->getMessage() . "\n");
    exit(1);
}
