<?php
// groupd/views/anesthesiologist/anesthesia_report.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

$spid = $_GET['id'] ?? 0;
$pdo = getDBConnection();

// Obtener datos y el reporte ACTUAL de anestesia
$stmt = $pdo->prepare("SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames, sp.anesthesiology_report 
                       FROM SURGICAL_PROCEDURE sp 
                       JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                       JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                       JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                       WHERE sp.surgical_procedure_id = ?");
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$surgery) die("Surgery not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Anesthesia Report | <?php echo $spid; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="anesthesiologist_dashboard.php" class="text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            <h3 class="fw-bold mt-2">Anesthesia Record</h3>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($surgery['procedure_name']); ?> &bull; <?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames']); ?></p>
        </div>
        <button onclick="saveReport()" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-save me-2"></i> Save Record</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold py-3"><i class="bi bi-file-medical-fill text-warning me-2"></i> Anesthesia Notes & Summary</div>
        <div class="card-body p-4">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">PRE-OP / INTRA-OP / POST-OP NOTES</label>
                <textarea id="anesReport" class="form-control" rows="20" placeholder="Type detailed anesthesia report here..."><?php echo htmlspecialchars($surgery['anesthesiology_report'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
</div>

<script>
    async function saveReport() {
        const text = document.getElementById('anesReport').value;
        try {
            const res = await fetch('api_save_anes_report.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ spid: <?php echo $spid; ?>, report: text })
            });
            const data = await res.json();
            if(data.success) {
                alert("Report Saved Successfully!");
                window.location.href = "anesthesiologist_dashboard.php";
            } else {
                alert("Error: " + data.error);
            }
        } catch(e) { console.error(e); alert("Network Error"); }
    }
</script>
</body>
</html>