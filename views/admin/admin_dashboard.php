<?php
// groupd/views/admin/admin_dashboard.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) die("Access Denied.");

$pdo = getDBConnection();

// Estadísticas rápidas
$stats = [
    'requests' => $pdo->query("SELECT COUNT(*) FROM SCHEDULE_REQUESTS WHERE status = 'Pending'")->fetchColumn(),
    'active_ops' => $pdo->query("SELECT COUNT(*) FROM SURGICAL_PROCEDURE WHERE status = 'In Progress'")->fetchColumn(),
    'waiting' => $pdo->query("SELECT COUNT(*) FROM SURGICAL_PROCEDURE WHERE status = 'Pending'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Hospital Les Corts</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏥</text></svg>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root { 
            --bg-body: #f5f7fb; 
            --text-dark: #344767; 
            --text-muted: #67748e;
            --primary-grad: linear-gradient(310deg, #141727 0%, #3a416f 100%);
            --sidebar-width: 260px;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; color: var(--text-dark); overflow-x: hidden; }
        
        /* SIDEBAR */
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--primary-grad); 
            height: 100vh; 
            position: fixed; 
            top: 0; left: 0; 
            padding: 20px; 
            z-index: 1000; 
            display: flex; flex-direction: column;
        }
        .sidebar-brand { color: white; font-weight: 700; font-size: 1.2rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px; }
        .nav-link { 
            color: rgba(255,255,255,0.8); 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 5px; 
            transition: all 0.2s; 
            font-weight: 500; 
            display: flex; align-items: center; gap: 12px;
            text-decoration: none;
        }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; transform: translateX(3px); }
        .nav-link.active { background: white; color: #141727; font-weight: 700; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav-link .icon-box { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: rgba(255,255,255,0.1); }
        .nav-link.active .icon-box { background: #3a416f; color: white; }

        /* MAIN CONTENT */
        .main-content { margin-left: var(--sidebar-width); padding: 30px 40px; }
        
        /* CARDS */
        .card-modern { 
            background: white; 
            border: none; 
            border-radius: 16px; 
            box-shadow: var(--card-shadow); 
            padding: 24px; 
            transition: transform 0.2s;
        }
        .card-search { border-left: 6px solid #3a416f; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        /* TABLA */
        .table-custom thead th { 
            text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid #f0f2f5; padding-bottom: 10px; 
        }
        .table-custom tbody td { padding: 15px 10px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
        .badge-status { padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .bg-scheduled { background: #e3f2fd; color: #0d6efd; }
        .bg-completed { background: #e8f5e9; color: #198754; }
        .bg-cancelled { background: #fbe9e7; color: #d63384; }
        
        /* BUTTONS */
        .btn-action { background: #3a416f; color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600; transition: 0.2s; }
        .btn-action:hover { background: #141727; color: white; transform: translateY(-1px); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand"><i class="bi bi-hospital-fill"></i> Les Corts Admin</div>
        
        <nav class="nav flex-column">
            <a href="#" class="nav-link active"><span class="icon-box"><i class="bi bi-grid-fill"></i></span> Dashboard</a>
            <a href="admin_scheduler_advanced.php" class="nav-link"><span class="icon-box"><i class="bi bi-calendar-check-fill"></i></span> Smart Scheduler</a>
            <a href="admin_requests.php" class="nav-link">
                <span class="icon-box"><i class="bi bi-envelope-fill"></i></span> Requests
                <?php if($stats['requests'] > 0): ?><span class="badge bg-warning text-dark ms-auto"><?php echo $stats['requests']; ?></span><?php endif; ?>
            </a>
            <a href="admin_patients.php" class="nav-link"><span class="icon-box"><i class="bi bi-people-fill"></i></span> Patient Directory</a>
        </nav>

        <div class="mt-auto">
            <a href="../../controllers/logout.php" class="nav-link text-danger"><span class="icon-box bg-danger bg-opacity-10 text-danger"><i class="bi bi-box-arrow-right"></i></span> Logout</a>
        </div>
    </div>

    <div class="main-content">
        
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h3 class="fw-bold mb-1">Administrative Console</h3>
                <p class="text-muted mb-0">Welcome back, Admin. System is operational.</p>
            </div>
            <button class="btn btn-success fw-bold shadow-sm px-4 py-2" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#newPatientModal">
                <i class="bi bi-person-plus-fill me-2"></i> Register New Patient
            </button>
        </div>

        <div class="row mb-4 g-4">
            <div class="col-md-4">
                <div class="card-modern d-flex align-items-center justify-content-between">
                    <div><p class="text-muted text-uppercase small fw-bold mb-1">Active Surgeries</p><h2 class="fw-bold mb-0"><?php echo $stats['active_ops']; ?></h2></div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle"><i class="bi bi-activity fs-3"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-modern d-flex align-items-center justify-content-between" style="cursor: pointer;" onclick="window.location.href='admin_scheduler_advanced.php'">
                    <div><p class="text-muted text-uppercase small fw-bold mb-1">Waiting List</p><h2 class="fw-bold mb-0 text-dark"><?php echo $stats['waiting']; ?></h2></div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle"><i class="bi bi-hourglass-split fs-3"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-modern d-flex align-items-center justify-content-between" style="cursor: pointer;" onclick="window.location.href='admin_requests.php'">
                    <div><p class="text-muted text-uppercase small fw-bold mb-1">Pending Requests</p><h2 class="fw-bold mb-0 text-warning"><?php echo $stats['requests']; ?></h2></div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle"><i class="bi bi-envelope-paper fs-3"></i></div>
                </div>
            </div>
        </div>

        <div class="card-modern card-search mb-5">
            <div class="row align-items-center">
                <div class="col-lg-5">
                    <h5 class="fw-bold mb-1"><i class="bi bi-search me-2 text-primary"></i> Surgical Lookup</h5>
                    <p class="text-muted small mb-0">Search Patient ID to manage.</p>
                </div>
                <div class="col-lg-7">
                    <form id="searchForm" class="d-flex gap-3">
                        <input type="text" id="patientIdInput" class="form-control form-control-lg" placeholder="Enter Patient ID..." required>
                        <button type="submit" class="btn btn-action px-4">Search</button>
                    </form>
                </div>
            </div>

            <div id="searchResult" class="d-none mt-4 border-top pt-4">
                <div class="d-flex justify-content-between align-items-center mb-4 bg-light p-3 rounded-3">
                    <div>
                        <h4 class="mb-0 fw-bold text-dark" id="pName">Patient Name</h4>
                        <div class="text-muted small">ID: <span id="pIdDisplay" class="fw-bold"></span></div>
                    </div>
                    <button class="btn btn-success fw-bold shadow-sm" onclick="addToWaitingList()">
                        <i class="bi bi-plus-circle me-2"></i> Add to Waiting List
                    </button>
                </div>

                <h6 class="text-uppercase text-muted fw-bold small mb-3 ps-1">History</h6>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead><tr><th>Date</th><th>Procedure</th><th>Surgeon</th><th>Status</th></tr></thead>
                        <tbody id="surgeryListBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4"><div class="card-modern h-100 text-center p-4 hover-lift"><i class="bi bi-calendar-check fs-1 text-primary mb-3 d-block"></i><h6 class="fw-bold">Smart Scheduler</h6><p class="text-muted small">Manage waiting list.</p><a href="admin_scheduler_advanced.php" class="btn btn-outline-primary btn-sm w-100 mt-2">Open Scheduler</a></div></div>
            <div class="col-md-4"><div class="card-modern h-100 text-center p-4 hover-lift"><i class="bi bi-inbox fs-1 text-warning mb-3 d-block"></i><h6 class="fw-bold">Request Inbox</h6><p class="text-muted small">Manage requests.</p><a href="admin_requests.php" class="btn btn-outline-warning btn-sm w-100 mt-2 text-dark">View Requests</a></div></div>
            <div class="col-md-4"><div class="card-modern h-100 text-center p-4 hover-lift"><i class="bi bi-people fs-1 text-success mb-3 d-block"></i><h6 class="fw-bold">Patient Directory</h6><p class="text-muted small">Manage records.</p><a href="admin_patients.php" class="btn btn-outline-success btn-sm w-100 mt-2">Manage Directory</a></div></div>
        </div>
    </div>

    <div class="modal fade" id="newPatientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold">Register New Patient</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="registerPatientForm">
                        <div class="row g-3 mb-3">
                            <div class="col-6"><label class="small fw-bold text-muted">First Name</label><input type="text" id="regName" class="form-control" required></div>
                            <div class="col-6"><label class="small fw-bold text-muted">Surnames</label><input type="text" id="regSurname" class="form-control" required></div>
                        </div>
                        <div class="mb-3"><label class="small fw-bold text-muted">DOB</label><input type="date" id="regDob" class="form-control" required></div>
                        <div class="row g-3 mb-3">
                            <div class="col-6"><label class="small fw-bold text-muted">Gender</label><select id="regGender" class="form-select"><option value="M">Male</option><option value="F">Female</option></select></div>
                            <div class="col-6"><label class="small fw-bold text-muted">Phone</label><input type="text" id="regPhone" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Email</label><input type="email" id="regEmail" class="form-control" required></div>
                        <div class="mb-4"><label class="small fw-bold text-muted">Address</label><textarea id="regAddress" class="form-control" rows="2"></textarea></div>
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">Save & Register</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // LOGICA BÚSQUEDA
        let currentPatientId = '';
        let currentPatientName = '';

        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            searchPatient(document.getElementById('patientIdInput').value.trim());
        });

        async function searchPatient(pid) {
            const resultDiv = document.getElementById('searchResult');
            const tbody = document.getElementById('surgeryListBody');
            
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr>';
            resultDiv.classList.remove('d-none');

            try {
                const res = await fetch(`api_admin_patient_details.php?id=${pid}`);
                const data = await res.json();
                
                tbody.innerHTML = ''; 

                if(data.success) {
                    currentPatientId = data.patient.patient_id;
                    currentPatientName = data.patient.first_name + ' ' + data.patient.surnames;
                    document.getElementById('pName').textContent = currentPatientName;
                    document.getElementById('pIdDisplay').textContent = currentPatientId;
                    
                    if (data.surgeries.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4 fst-italic">No surgeries found.</td></tr>';
                    } else {
                        data.surgeries.forEach(s => {
                            let badgeClass = 'bg-secondary';
                            if(s.status === 'Scheduled') badgeClass = 'bg-scheduled';
                            else if (s.status === 'Completed') badgeClass = 'bg-completed';
                            else if (s.status === 'Cancelled') badgeClass = 'bg-cancelled';

                            tbody.innerHTML += `
                                <tr>
                                    <td>${new Date(s.date_time).toLocaleDateString()}</td>
                                    <td class="fw-bold text-dark">${s.procedure_name}</td>
                                    <td>${s.surgeon_name || 'Unassigned'}</td>
                                    <td><span class="badge-status ${badgeClass}">${s.status}</span></td>
                                </tr>`;
                        });
                    }
                } else {
                    alert("Patient not found.");
                    resultDiv.classList.add('d-none');
                }
            } catch(err) { console.error(err); alert("Search error."); }
        }

        // --- FUNCIÓN QUE CREA ENTRADA EN LISTA DE ESPERA ---
        async function addToWaitingList() {
            if (!currentPatientId) {
                alert("Please search and select a patient first.");
                return;
            }

            if(!confirm(`Add patient ${currentPatientName} to Surgery Waiting List?`)) return;

            try {
                // Llamamos a la API con acción 'create_pending'
                const payload = {
                    action: 'create_pending',
                    patient_id: currentPatientId,
                    procedure_id: 1, // Por defecto Apendicectomía (ID 1). 
                    priority: 'Elective'
                };

                const res = await fetch('api_schedule_surgery.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch(e) { throw new Error("Server Error: " + text); }
                
                if(data.success) {
                    window.location.href = 'admin_scheduler_advanced.php';
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) { console.error(e); alert("System Error: Could not add to list."); }
        }

        // Registro Paciente
        document.getElementById('registerPatientForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const payload = {
                firstName: document.getElementById('regName').value,
                surnames: document.getElementById('regSurname').value,
                dateOfBirth: document.getElementById('regDob').value,
                gender: document.getElementById('regGender').value,
                phone: document.getElementById('regPhone').value,
                email: document.getElementById('regEmail').value,
                address: document.getElementById('regAddress').value
            };

            try {
                const res = await fetch('api_patients.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
                });
                const data = await res.json();
                if(data.success) {
                    alert("Patient Registered! ID: " + data.patient_id);
                    bootstrap.Modal.getInstance(document.getElementById('newPatientModal')).hide();
                    document.getElementById('patientIdInput').value = data.patient_id;
                    searchPatient(data.patient_id);
                } else alert("Error: " + data.error);
            } catch(e) { alert("System error."); }
        });
    </script>
</body>
</html>