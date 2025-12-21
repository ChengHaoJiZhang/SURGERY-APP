<?php
// groupd/views/admin/admin_patients.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) die("Access Denied.");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Directory | Admin</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏥</text></svg>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --admin-dark: #344767; --bg-light: #f5f7fb; --primary: #3a416f; }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', sans-serif; display: flex; height: 100vh; overflow: hidden; color: #495057; }
        
        .sidebar { width: 260px; background: linear-gradient(310deg, #141727 0%, #3a416f 100%); height: 100vh; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar a { color: rgba(255,255,255,0.8); padding: 12px 16px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 12px; font-weight: 500; transition: 0.2s; }
        .sidebar a:hover { background: rgba(255,255,255,0.1); color: white; transform: translateX(3px); }
        .sidebar a.active { background: white; color: #141727; font-weight: 700; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .sidebar .icon-box { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 6px; background: rgba(255,255,255,0.1); }
        .sidebar a.active .icon-box { background: #3a416f; color: white; }

        .content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; }
        
        .card-modern { background: white; border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 24px; }
        .search-input { border-radius: 10px; border: 1px solid #e2e8f0; padding: 12px 20px; font-size: 0.95rem; background-color: #fff; transition: 0.2s; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(58, 65, 111, 0.1); }
        
        .table-custom thead th { text-transform: uppercase; font-size: 0.75rem; color: #67748e; font-weight: 700; padding: 12px 15px; border-bottom: 1px solid #f0f2f5; }
        .table-custom tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; }
        .avatar-circle { width: 40px; height: 40px; background-color: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary); margin-right: 15px; }
        
        .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: 0.2s; border: none; }
        .btn-edit { background: #e3f2fd; color: #0d6efd; }
        .btn-edit:hover { background: #0d6efd; color: white; }
        .btn-cal { background: #e8f5e9; color: #198754; }
        .btn-cal:hover { background: #198754; color: white; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="mb-4 px-2 text-white fw-bold fs-5"><i class="bi bi-hospital-fill me-2"></i> Les Corts Admin</div>
        <a href="admin_dashboard.php"><span class="icon-box"><i class="bi bi-grid-fill"></i></span> Dashboard</a>
        <a href="admin_schedule.php"><span class="icon-box"><i class="bi bi-calendar-week-fill"></i></span> Schedule</a>
        <a href="admin_requests.php"><span class="icon-box"><i class="bi bi-envelope-fill"></i></span> Requests</a>
        <a href="#" class="active"><span class="icon-box"><i class="bi bi-people-fill"></i></span> Patients</a>
        <div class="mt-auto"><a href="../../controllers/logout.php" class="text-danger ps-2 text-decoration-none fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></div>
    </div>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Patient Directory</h3>
                <p class="text-muted mb-0">Manage administrative records. <i class="bi bi-shield-lock-fill text-success ms-1"></i> Clinical data hidden.</p>
            </div>
            <button class="btn btn-primary fw-bold shadow-sm px-4" onclick="openNewModal()">
                <i class="bi bi-person-plus-fill me-2"></i> New Patient
            </button>
        </div>

        <div class="card-modern">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="searchInput" class="form-control search-input ps-5" placeholder="Search by Name, ID, or DNI...">
                    </div>
                </div>
                <div class="col-md-6 text-end align-self-center">
                    <span class="text-muted small" id="recordCount">Loading...</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact Info</th>
                            <th>ID / DNI</th>
                            <th>DOB / Gender</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="patientTableBody">
                        <tr><td colspan="5" class="text-center py-5 text-muted">Start typing to search or wait for load...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="patientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">Edit Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="patientForm">
                        <input type="hidden" id="pId">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted">First Name</label>
                                <input type="text" id="pName" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Surnames</label>
                                <input type="text" id="pSurname" class="form-control" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Date of Birth</label>
                                <input type="date" id="pDob" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Gender</label>
                                <select id="pGender" class="form-select">
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted">DNI / Document ID</label>
                            <input type="text" id="pDni" class="form-control">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Phone</label>
                                <input type="text" id="pPhone" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Email</label>
                                <input type="email" id="pEmail" class="form-control">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="small fw-bold text-muted">Address</label>
                            <textarea id="pAddress" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-bold py-2">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('patientModal'));
        let isEditMode = false;

        document.addEventListener('DOMContentLoaded', () => loadPatients(''));

        let timeout = null;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => loadPatients(this.value), 300);
        });

        // FUNCIÓN MEJORADA: Detecta errores reales
        async function loadPatients(query) {
            const tbody = document.getElementById('patientTableBody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
            
            try {
                const res = await fetch(`api_patients.php?search=${query}`);
                
                // 1. Verificar el contenido crudo antes de parsear
                const text = await res.text();
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta inválida del servidor:", text);
                    // Muestra el error real que está enviando el servidor en la tabla
                    tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center fw-bold p-4">
                        <i class="bi bi-bug-fill"></i> System Error.<br>
                        <small class="text-muted fw-normal">Check console (F12) for raw output. Server sent: "${text.substring(0, 50)}..."</small>
                    </td></tr>`;
                    return;
                }
                
                if(!data.success) {
                    throw new Error(data.error || 'Unknown error');
                }

                tbody.innerHTML = '';
                document.getElementById('recordCount').textContent = `${data.patients.length} records found`;

                if(data.patients.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">No patients found in database.</td></tr>';
                    return;
                }

                data.patients.forEach(p => {
                    // Prevenir error si algún campo es null
                    const fName = p.first_name || '';
                    const sName = p.surnames || '';
                    const initials = (fName.charAt(0) + sName.charAt(0)).toUpperCase();
                    
                    const row = document.createElement('tr');
                    
                    row.innerHTML = `
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle">${initials}</div>
                                <div>
                                    <div class="fw-bold text-dark">${fName} ${sName}</div>
                                    <div class="small text-muted">ID: ${p.patient_id}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small"><i class="bi bi-telephone me-1"></i> ${p.phone || '-'}</div>
                            <div class="small text-muted"><i class="bi bi-envelope me-1"></i> ${p.email || '-'}</div>
                        </td>
                        <td><span class="badge bg-light text-dark border">${p.ID_document || 'N/A'}</span></td>
                        <td>
                            <div>${p.date_of_birth ? new Date(p.date_of_birth).toLocaleDateString() : '-'}</div>
                            <div class="small text-muted">${p.gender === 'M' ? 'Male' : (p.gender === 'F' ? 'Female' : 'Other')}</div>
                        </td>
                        <td class="text-end">
                            <button class="btn-icon btn-edit me-1" onclick='openEditModal(${JSON.stringify(p)})' title="Edit Details">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            </td>
                    `;
                    tbody.appendChild(row);
                });

            } catch (e) { 
                console.error(e); 
                tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">Error: ${e.message}</td></tr>`; 
            }
        }

        window.openNewModal = () => {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = "Register New Patient";
            document.getElementById('patientForm').reset();
            document.getElementById('pId').value = '';
            document.getElementById('pDob').disabled = false;
            modal.show();
        };

        window.openEditModal = (p) => {
            isEditMode = true;
            document.getElementById('modalTitle').textContent = "Edit Patient Details";
            document.getElementById('pId').value = p.patient_id;
            document.getElementById('pName').value = p.first_name;
            document.getElementById('pSurname').value = p.surnames;
            document.getElementById('pDob').value = p.date_of_birth;
            document.getElementById('pGender').value = p.gender;
            document.getElementById('pDni').value = p.ID_document;
            document.getElementById('pPhone').value = p.phone;
            document.getElementById('pEmail').value = p.email;
            document.getElementById('pAddress').value = p.address;
            document.getElementById('pDob').disabled = false; 
            modal.show();
        };

        document.getElementById('patientForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                patientId: document.getElementById('pId').value,
                firstName: document.getElementById('pName').value,
                surnames: document.getElementById('pSurname').value,
                dateOfBirth: document.getElementById('pDob').value,
                gender: document.getElementById('pGender').value,
                dni: document.getElementById('pDni').value,
                phone: document.getElementById('pPhone').value,
                email: document.getElementById('pEmail').value,
                address: document.getElementById('pAddress').value
            };

            const method = isEditMode ? 'PUT' : 'POST';
            
            try {
                const res = await fetch('api_patients.php', {
                    method: method,
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    alert(isEditMode ? "Changes Saved!" : "Patient Registered Successfully!");
                    modal.hide();
                    loadPatients(document.getElementById('searchInput').value);
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) { console.error(e); alert("System error."); }
        });
    </script>
</body>
</html>