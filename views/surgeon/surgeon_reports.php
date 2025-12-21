<?php
// grupd/views/surgeon/surgeon_reports.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) die("Access Denied.");

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Obtener ID del Cirujano
$stmtSurgeon = $pdo->prepare("SELECT surgeon_id, surnames FROM SURGEONS WHERE user_id = ?");
$stmtSurgeon->execute([$userId]);
$surgeon = $stmtSurgeon->fetch();

// OBTENER CIRUGÍAS PASADAS (Historial)
// Filtramos por fecha <= HOY
$sql = "SELECT sp.surgical_procedure_id, p.procedure_name, sp.date_time, sp.surgical_report,
               op.room_number, pat.first_name, pat.surnames, pat.patient_id
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
        JOIN EPISODE ep ON sp.episode_id = ep.episode_id
        JOIN PATIENT pat ON ep.patient_id = pat.patient_id
        JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
        
        WHERE sps.surgeon_id = :sid
        AND sp.date_time < NOW() -- Solo mostrar pasadas
        ORDER BY sp.date_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':sid' => $surgeon['surgeon_id']]);
$history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Surgical Reports | Dr. <?php echo htmlspecialchars($surgeon['surnames']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root { --hospital-blue: #0056b3; --bg-light: #f4f6f9; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-light); color: #343a40; }
        
        .brand-header { background: linear-gradient(180deg, rgba(0, 86, 179, 1) 0%, rgba(2, 48, 97, 1) 100%); color: white; padding: 2rem 0 5rem; }
        .main-container { margin-top: -4rem; }
        
        .report-card { border: none; border-radius: 10px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: transform 0.2s; margin-bottom: 20px; overflow: hidden; }
        .report-card:hover { transform: translateY(-3px); }
        
        .status-line { height: 5px; width: 100%; }
        .status-pending { background-color: #dc3545; } /* Rojo */
        .status-done { background-color: #198754; } /* Verde */
        
        .btn-action { border-radius: 50px; font-weight: 600; padding: 5px 20px; }
    </style>
</head>
<body>

    <header class="brand-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="surgeon_dashboard.php" class="text-white-50 text-decoration-none small mb-2 d-inline-block">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="h3 fw-bold mb-0">Surgical Reports</h1>
                <p class="text-white-50 small mb-0">History & Documentation</p>
            </div>
        </div>
    </header>

    <div class="container main-container">
        <div class="row">
            <?php if (empty($history)): ?>
                <div class="col-12">
                    <div class="card p-5 text-center text-muted border-0 shadow-sm">
                        <h4>No past surgeries found.</h4>
                        <p>Completed procedures will appear here for reporting.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <?php 
                        $hasReport = !empty($h['surgical_report']);
                        $statusClass = $hasReport ? 'status-done' : 'status-pending';
                        $statusText = $hasReport ? 'Report Saved' : 'Report Pending';
                        $btnText = $hasReport ? 'Edit Report' : 'Write Report';
                        $btnClass = $hasReport ? 'btn-outline-secondary' : 'btn-primary';
                    ?>
                    <div class="col-lg-6">
                        <div class="report-card">
                            <div class="status-line <?php echo $statusClass; ?>"></div>
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($h['procedure_name']); ?></h5>
                                    <small class="text-muted fw-bold"><?php echo date('d M Y', strtotime($h['date_time'])); ?></small>
                                </div>
                                
                                <p class="text-muted mb-3">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($h['first_name'] . ' ' . $h['surnames']); ?> 
                                    <span class="small opacity-50">(<?php echo $h['patient_id']; ?>)</span>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <span class="badge <?php echo $hasReport ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $hasReport ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                    
                                    <button class="btn <?php echo $btnClass; ?> btn-action" 
                                            onclick="openReportModal(<?php echo $h['surgical_procedure_id']; ?>, '<?php echo htmlspecialchars(addslashes($h['procedure_name'])); ?>')">
                                        <i class="bi bi-file-text me-1"></i> <?php echo $btnText; ?>
                                    </button>
                                    
                                    <textarea id="report-content-<?php echo $h['surgical_procedure_id']; ?>" style="display:none;"><?php echo htmlspecialchars($h['surgical_report'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="modalTitle">Surgical Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="reportForm">
                        <input type="hidden" id="procId">
                        
                        <div class="mb-3">
                            <label class="fw-bold small text-muted mb-2">OPERATIVE FINDINGS & PROCEDURE DETAILS</label>
                            <textarea id="reportText" class="form-control p-3" rows="10" placeholder="Describe the surgical procedure, findings, and any complications..." style="font-family: monospace; font-size: 0.95rem;"></textarea>
                            <div class="text-end mt-1">
                                <small class="text-muted" id="wordCount">0 words</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold px-4">Save Report & Sign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
        const txtArea = document.getElementById('reportText');
        const countSpan = document.getElementById('wordCount');

        // Contador de palabras
        txtArea.addEventListener('input', function() {
            const words = this.value.trim().split(/\s+/).length;
            countSpan.textContent = this.value.trim() === '' ? '0 words' : words + ' words';
        });

        function openReportModal(id, title) {
            document.getElementById('procId').value = id;
            document.getElementById('modalTitle').textContent = "Report: " + title;
            
            // Cargar contenido existente
            const existingContent = document.getElementById('report-content-' + id).value;
            txtArea.value = existingContent;
            txtArea.dispatchEvent(new Event('input')); // Actualizar contador
            
            reportModal.show();
        }

        // Guardar
        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const payload = {
                procedure_id: document.getElementById('procId').value,
                report: txtArea.value
            };

            try {
                const res = await fetch('api_save_report.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if(data.success) {
                    alert("Report saved successfully!");
                    location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(err) { console.error(err); alert("System error."); }
        });
    </script>
</body>
</html>