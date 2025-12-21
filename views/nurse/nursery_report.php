<?php
// groupd/views/nurse/nursery_report.php
// REDESIGNED: "Clean & Depth" Aesthetic
// UPDATED: Removed 'View Vitals' button from header

session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../nurse/nurse_controller.php'; 
require_once __DIR__ . '/../../config/config.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) die("Access Denied.");

$spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;
if ($spid === 0) {
    header("Location: nurse_dashboard.php?error=MissingSPID");
    exit();
}

$pdo = getDBConnection();
// Mock material list for display (In a real app, this would be fetched from SURGICAL_MATERIAL)
$mockMaterials = $pdo->query("SELECT material_id, material_name FROM SURGICAL_MATERIAL LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Report | SPID #<?php echo $spid; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* COLOR PALETTE */
        :root { 
            --bg-body: #eaeff2; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #0ea5e9; 
            --primary-dark: #0284c7;
            --surface: #ffffff;
            --success: #10b981;
            
            --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --glass-bg: rgba(255, 255, 255, 0.95);
        }
        
        body { background-color: var(--bg-body); font-family: 'Inter', system-ui, sans-serif; color: var(--text-dark); padding-bottom: 80px; }
        
        /* HEADER */
        .top-header { 
            background: var(--glass-bg); 
            backdrop-filter: blur(10px);
            height: 72px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 40px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: var(--shadow-soft);
        }
        
        /* CARDS */
        .card-depth { background: var(--surface); border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: var(--shadow-soft); overflow: hidden; height: 100%; transition: transform 0.2s; }
        .card-depth:hover { border-color: #cbd5e1; }
        
        .card-header-clean { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 10px; background: #fff; }
        
        /* INPUTS & TEXTAREA */
        .form-control { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; color: var(--text-dark); background: #f8fafc; font-size: 0.95rem; }
        .form-control:focus { border-color: var(--primary); background: white; outline: none; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        
        .input-group-text { background: white; border: 1px solid #e2e8f0; border-right: none; color: var(--text-muted); font-size: 0.9rem; font-weight: 600; border-radius: 10px 0 0 10px; }
        .input-group .form-control { border-radius: 0 10px 10px 0; border-left: 1px solid #e2e8f0; }
        
        /* BUTTONS */
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 10px; padding: 8px 16px; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
        
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; transition: 0.2s; width: 100%; box-shadow: 0 4px 6px rgba(14, 165, 233, 0.2); }
        .btn-save:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 6px 12px rgba(14, 165, 233, 0.3); }
        
        .btn-success-soft { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 10px 20px; border-radius: 10px; font-weight: 700; transition: 0.2s; width: auto; display: inline-flex; align-items: center; gap: 8px; }
        .btn-success-soft:hover { background: #bbf7d0; transform: translateY(-1px); }

        /* CHECKBOXES */
        .form-check-input { width: 1.2em; height: 1.2em; border: 2px solid #cbd5e1; margin-top: 0.1em; cursor: pointer; }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        .check-row { padding: 12px 16px; background: #f8fafc; border-radius: 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

    <header class="top-header">
        <div class="d-flex align-items-center gap-4">
            <a href="nurse_dashboard.php" class="btn-glass border-0 text-muted ps-0">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <div>
                <h1 class="h5 mb-0 fw-bold" id="patientInfo">Loading Patient...</h1>
                <div class="text-muted small">Procedure ID: #<?php echo $spid; ?></div>
            </div>
        </div>
        </header>

    <div class="container py-5" style="max-width: 1200px;">
        
        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="card-depth">
                    <div class="card-header-clean">
                        <i class="bi bi-file-earmark-text text-primary"></i> Clinical Notes & Documentation
                    </div>
                    <div class="p-4">
                        <form id="reportForm">
                            <div class="mb-4">
                                <label for="reportContent" class="form-label text-muted text-uppercase small fw-bold mb-2">Comprehensive Nursing Notes</label>
                                <textarea class="form-control" id="reportContent" rows="18" placeholder="Document patient preparation, intraoperative events, instrument counts, and recovery notes here..." style="resize: none;"></textarea>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span id="saveStatus" class="text-muted small fw-bold"></span>
                                <button type="submit" class="btn-success-soft px-4">
                                    <i class="bi bi-save"></i> Save Notes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-depth">
                    <div class="card-header-clean">
                        <i class="bi bi-box-seam text-dark"></i> Material Usage & Counts
                    </div>
                    <div class="p-4">
                        <form id="materialForm">
                            <p class="text-muted small mb-3">Log quantities used for billing and inventory.</p>
                            
                            <div class="mb-4">
                                <?php foreach($mockMaterials as $m): ?>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text w-50 text-truncate" title="<?php echo htmlspecialchars($m['material_name']); ?>"><?php echo htmlspecialchars($m['material_name']); ?></span>
                                        <input type="number" class="form-control text-center fw-bold material-input" 
                                               data-material-id="<?php echo $m['material_id']; ?>" 
                                               placeholder="0" min="0">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="border-light my-4">
                            
                            <h6 class="fw-bold text-dark text-uppercase small mb-3">Safety Counts</h6>
                            
                            <div class="check-row">
                                <label class="form-check-label fw-bold text-dark small" for="countPreOp">Pre-Op Count Verified</label>
                                <input type="checkbox" class="form-check-input" id="countPreOp" checked>
                            </div>
                            <div class="check-row">
                                <label class="form-check-label fw-bold text-dark small" for="countClosing">Closing Count Verified</label>
                                <input type="checkbox" class="form-check-input" id="countClosing">
                            </div>
                            
                            <button type="button" onclick="saveMaterials()" class="btn-save mt-4">
                                Confirm Usage & Counts
                            </button>
                            <div id="materialSaveStatus" class="text-center mt-2 text-muted small fw-bold"></div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetchReport();
            fetchPatientInfo(); 
        });

        const spid = <?php echo $spid; ?>;
        const reportContentArea = document.getElementById('reportContent');
        const saveStatus = document.getElementById('saveStatus');

        // Helper to fetch patient name
        async function fetchPatientInfo() {
             try {
                // Reusing api_patient_vitals for basic patient info retrieval
                const response = await fetch(`api_patient_vitals.php?spid=${spid}`);
                const data = await response.json();
                if (data.success) {
                    const p = data.patient;
                    document.getElementById('patientInfo').textContent = `${p.first_name} ${p.surnames}`;
                }
            } catch (e) { console.error("Could not fetch patient info."); }
        }

        // --- REPORT LOGIC ---
        async function fetchReport() {
            try {
                const res = await fetch(`api_nursery_report.php?spid=${spid}`);
                const data = await res.json();
                if (data.success) {
                    reportContentArea.value = data.report;
                }
            } catch (e) {
                console.error("Error loading report:", e);
            }
        }

        // Function to save report
        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            saveStatus.textContent = "Saving...";
            
            const payload = {
                spid: spid,
                report_content: reportContentArea.value
            };

            try {
                const res = await fetch('api_nursery_report.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if(data.success) {
                    saveStatus.textContent = `Saved at ${new Date().toLocaleTimeString()}`;
                    saveStatus.classList.add('text-success');
                } else {
                    saveStatus.textContent = `Error: ${data.error}`;
                    saveStatus.classList.add('text-danger');
                }
            } catch(err) { 
                console.error(err); 
                saveStatus.textContent = "Connection error.";
            }
        });

        // --- MATERIAL USAGE LOGIC ---
        async function saveMaterials() {
            const materialInputs = document.querySelectorAll('.material-input');
            const usageData = [];

            materialInputs.forEach(input => {
                const qty = parseInt(input.value);
                if (qty > 0) {
                    usageData.push({
                        material_id: input.getAttribute('data-material-id'),
                        amount_used: qty
                    });
                }
            });
            
            const counts = {
                pre_op: document.getElementById('countPreOp').checked,
                closing: document.getElementById('countClosing').checked
            };
            
            const payload = {
                action: 'log_material_usage',
                spid: spid,
                usage_data: usageData,
                counts: counts
            };

            const materialSaveStatus = document.getElementById('materialSaveStatus');
            materialSaveStatus.textContent = "Saving...";
            materialSaveStatus.className = "text-center mt-2 text-muted small fw-bold";
            
            try {
                const res = await fetch('api_nurse_actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    materialSaveStatus.textContent = "Saved successfully!";
                    materialSaveStatus.classList.replace('text-muted', 'text-success');
                } else {
                    materialSaveStatus.textContent = `Error: ${data.error}`;
                    materialSaveStatus.classList.replace('text-muted', 'text-danger');
                }
            } catch (e) {
                materialSaveStatus.textContent = "System error.";
                materialSaveStatus.classList.replace('text-muted', 'text-danger');
            }
        }
    </script>
</body>
</html>