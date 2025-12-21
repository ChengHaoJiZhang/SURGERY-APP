<?php
// groupd/views/surgeon/surgeon_patients.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) die("Access Denied.");

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get Surgeon
$stmt = $pdo->prepare("SELECT surgeon_id, surnames FROM SURGEONS WHERE user_id = ?");
$stmt->execute([$userId]);
$surgeon = $stmt->fetch();

// Fetch Patients (Solo los tratados por este cirujano)
$sql = "SELECT 
            pat.patient_id, pat.first_name, pat.surnames, pat.date_of_birth, pat.gender,
            MAX(sp.date_time) as last_surgery_date,
            COUNT(sp.surgical_procedure_id) as total_surgeries
        FROM PATIENT pat
        JOIN EPISODE ep ON pat.patient_id = ep.patient_id
        JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id
        JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
        WHERE sps.surgeon_id = ?
        GROUP BY pat.patient_id
        ORDER BY last_surgery_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$surgeon['surgeon_id']]);
$patients = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>My Patients | Dr. <?php echo htmlspecialchars($surgeon['surnames']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .brand-header { background: #0d6efd; color: white; padding: 1rem 0; margin-bottom: 2rem; }
        
        .nav-link { color: rgba(255,255,255,0.8); font-weight: 500; border: none; padding: 0.5rem 1rem; }
        .nav-link:hover { color: white; }
        .nav-link.active { background-color: white !important; color: #0d6efd !important; border-radius: 5px 5px 0 0; font-weight: 700; }

        .patient-row { transition: 0.2s; }
        .patient-row:hover { background-color: #f8f9fa; }
        .avatar-circle { width: 40px; height: 40px; background-color: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #495057; font-size: 0.9rem; }
    </style>
</head>
<body>

    <header class="brand-header shadow-sm">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-hospital fs-4"></i>
                    <span class="fw-bold fs-5">Surgical Portal</span>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <span>Dr. <?php echo htmlspecialchars($surgeon['surnames']); ?></span>
                    <a href="../../controllers/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
            <ul class="nav nav-tabs border-0">
                <li class="nav-item"><a class="nav-link" href="surgeon_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="#">My Patients</a></li>
                <li class="nav-item"><a class="nav-link" href="surgeon_preferences.php">Preference Cards</a></li>
            </ul>
        </div>
    </header>

    <div class="container pb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-people-fill me-2"></i> Patient Directory</h5>
                <input type="text" id="searchPat" class="form-control w-25" placeholder="Search name...">
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Patient Name</th>
                                <th>Gender/Age</th>
                                <th>Last Surgery</th>
                                <th>Total Cases</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody id="patTable">
                            <?php foreach($patients as $p): 
                                $dob = new DateTime($p['date_of_birth']);
                                $now = new DateTime();
                                $age = $now->diff($dob)->y;
                                $initials = strtoupper(mb_substr($p['first_name'],0,1) . mb_substr($p['surnames'],0,1));
                            ?>
                            <tr class="patient-row">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle"><?php echo $initials; ?></div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></div>
                                            <div class="small text-muted"><?php echo $p['patient_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $p['gender']; ?> / <?php echo $age; ?> yrs</td>
                                <td><?php echo date('d M Y', strtotime($p['last_surgery_date'])); ?></td>
                                <td><span class="badge bg-secondary rounded-pill"><?php echo $p['total_surgeries']; ?></span></td>
                                <td class="text-end pe-4">
                                    <button onclick="viewHistory('<?php echo $p['patient_id']; ?>', '<?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?>')" 
                                            class="btn btn-sm btn-outline-primary fw-bold">
                                        <i class="bi bi-clock-history me-1"></i> View History
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="histTitle">Patient History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="histLoader" class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading records...</p>
                    </div>
                    <div id="histContent" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Procedure</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Details</th>
                                    </tr>
                                </thead>
                                <tbody id="histTableBody">
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Buscador
        document.getElementById('searchPat').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            document.querySelectorAll('.patient-row').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
        });

        // Lógica del Historial
        const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));

        async function viewHistory(pid, name) {
            document.getElementById('histTitle').textContent = 'History: ' + name;
            document.getElementById('histLoader').classList.remove('d-none');
            document.getElementById('histContent').classList.add('d-none');
            historyModal.show();

            try {
                const res = await fetch(`api_patient_history.php?pid=${pid}`);
                const data = await res.json();
                
                const tbody = document.getElementById('histTableBody');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">No surgeries found.</td></tr>';
                } else {
                    data.forEach(s => {
                        let statusBadge = `<span class="badge bg-secondary">${s.status}</span>`;
                        if(s.status === 'Completed') statusBadge = `<span class="badge bg-success">Completed</span>`;
                        if(s.status === 'Scheduled') statusBadge = `<span class="badge bg-primary">Scheduled</span>`;

                        tbody.innerHTML += `
                            <tr>
                                <td class="ps-4 text-nowrap">${new Date(s.date_time).toLocaleDateString()}</td>
                                <td class="fw-bold text-dark">${s.procedure_name}</td>
                                <td>${statusBadge}</td>
                                <td class="text-end pe-4">
                                    <a href="clinical_episode.php?id=${s.episode_id}" class="btn btn-sm btn-light border text-primary">
                                        Open <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('histLoader').classList.add('d-none');
                document.getElementById('histContent').classList.remove('d-none');

            } catch (e) {
                console.error(e);
                alert("Error loading history.");
            }
        }
    </script>
</body>
</html>