<?php
// groupd/views/nurse/nurse_history.php - Equivalent of surgeon_reports.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) die("Access Denied.");

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get Nurse ID
$stmtNurse = $pdo->prepare("SELECT nurse_id FROM NURSES WHERE user_id = ?");
$stmtNurse->execute([$userId]);
$nurse = $stmtNurse->fetch();
$nurseId = $nurse['nurse_id'];

// OBTENER CIRUGÍAS HISTÓRICAS (Todas las asociadas a esta nurse)
$sql = "SELECT sp.surgical_procedure_id, p.procedure_name, sp.date_time, sp.nursery_report, sp.status,
               pat.first_name, pat.surnames, pat.patient_id, spn.role_in_surgery
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        JOIN EPISODE ep ON sp.episode_id = ep.episode_id
        JOIN PATIENT pat ON ep.patient_id = pat.patient_id
        JOIN SURGICAL_PROCEDURE_NURSES spn ON sp.surgical_procedure_id = spn.surgical_procedure_id
        
        WHERE spn.nurse_id = :nid
        ORDER BY sp.date_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':nid' => $nurseId]);
$history = $stmt->fetchAll();
?>

<div class="p-4">
    <h4 class="fw-bold text-secondary mb-4"><i class="bi bi-journal-text me-2"></i> My Surgical Documentation History</h4>
    
    <div class="row g-4">
        <?php if (empty($history)): ?>
            <div class="col-12 text-center text-muted p-5">No surgical records found in your history.</div>
        <?php else: ?>
            <?php foreach ($history as $h): ?>
                <?php 
                    $hasReport = !empty($h['nursery_report']);
                    $statusClass = $h['status'] == 'Completed' ? 'alert-success' : 'alert-secondary';
                    $statusBadge = $h['status'] == 'Completed' ? 'bg-success' : 'bg-primary';
                ?>
                <div class="col-md-6">
                    <div class="card card-depth border-0 shadow-sm p-4" style="border-left: 4px solid <?php echo $h['status'] == 'Completed' ? '#10b981' : '#ffc107'; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($h['procedure_name']); ?></h6>
                                <small class="text-muted d-block"><?php echo date('d M Y', strtotime($h['date_time'])); ?></small>
                            </div>
                            <span class="badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($h['status']); ?></span>
                        </div>
                        
                        <div class="small mt-2 mb-3">
                            Patient: **<?php echo htmlspecialchars($h['first_name'].' '.$h['surnames']); ?>** (<?php echo $h['patient_id']; ?>)
                            <br>Your Role: *<?php echo htmlspecialchars($h['role_in_surgery']); ?>*
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="nursery_report.php?spid=<?php echo $h['surgical_procedure_id']; ?>" class="btn btn-sm btn-outline-dark">
                                <i class="bi bi-eye"></i> View Notes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
    // Mark content as loaded to prevent future re-fetching unless needed
    document.getElementById('historyContent').dataset.loaded = true;
</script>