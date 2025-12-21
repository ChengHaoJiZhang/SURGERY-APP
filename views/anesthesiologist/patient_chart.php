<?php
// groupd/views/anesthesiologist/patient_chart.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../../config/config.php';

$controller = new LoginController();
$controller->checkAuth();

// Access check: Anesthesiologist or Admin
$role = strtolower($_SESSION['role_name']);
if (strpos($role, 'anest') === false && strpos($role, 'admin') === false) {
    die("Access Denied.");
}

$spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;
if ($spid === 0) {
    header("Location: anesthesiologist_dashboard.php?error=MissingSPID");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anesthesia Patient Chart | SPID #<?php echo $spid; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --anes-color: #ffc107; --hospital-blue: #0d6efd; }
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .navbar { background-color: var(--anes-color); }
        .patient-header { background-color: #ffffff; padding: 20px 30px; border-bottom: 3px solid var(--hospital-blue); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-clinical { border-left: 5px solid var(--hospital-blue); }
        .alert-severe { border-color: #dc3545; background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: var(--anes-color) !important;">
  <div class="container-fluid">
    <a class="navbar-brand text-dark" href="anesthesiologist_dashboard.php">
        <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
    </a>
  </div>
</nav>

<div class="container-fluid patient-header" id="patientHeader">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-0 fw-bold" id="patientNameDisplay">Cargando Paciente...</h1>
            <p class="mb-0 text-muted">Procedure: <span id="procedureNameDisplay"></span> (SPID: <?php echo $spid; ?>)</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="live_cockpit.php?id=<?php echo $spid; ?>" class="btn btn-warning fw-bold">
                <i class="bi bi-activity me-2"></i> Go to Live Cockpit
            </a>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div id="loading" class="text-center p-5">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        <p class="mt-2 text-muted">Fetching clinical data...</p>
    </div>
    
    <div id="content" class="d-none">
        
        <div class="row mb-4">
            <div class="col-md-8">
                 <div class="card shadow-sm h-100">
                    <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-exclamation-triangle me-2"></i> Critical Alerts (Allergies)</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="allergiesList">
                            <li class="list-group-item text-muted">No allergies registered.</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                 <div class="card shadow-sm h-100 bg-light">
                    <div class="card-header bg-secondary text-white fw-bold"><i class="bi bi-geo-alt-fill me-2"></i> Location & Demographics</div>
                    <div class="card-body" id="demographics">
                         </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4 card-clinical">
                    <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-capsule me-2"></i> Active Prescriptions (Episode)</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="medicationsList">
                             <li class="list-group-item text-muted">No active medications prescribed in this episode.</li>
                        </ul>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 card-clinical">
                    <div class="card-header bg-success text-white fw-bold"><i class="bi bi-patch-check me-2"></i> Intra-Op Administered Drug History</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="administeredMedsList">
                             <li class="list-group-item text-muted">No intra-operative medications recorded.</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow-sm mb-4 card-clinical">
                    <div class="card-header bg-info text-white fw-bold"><i class="bi bi-clipboard-data me-2"></i> Latest Lab Results</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="labsList">
                             <li class="list-group-item text-muted">No lab results found for this episode.</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4 card-clinical">
                    <div class="card-header bg-warning text-dark fw-bold"><i class="bi bi-x-ray me-2"></i> Imaging Studies</div>
                    <div class="card-body p-0">
                         <ul class="list-group list-group-flush" id="imagingList">
                             <li class="list-group-item text-muted">No imaging studies found.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', fetchPatientChart);

    async function fetchPatientChart() {
        const spid = <?php echo $spid; ?>;
        const loadingDiv = document.getElementById('loading');
        const contentDiv = document.getElementById('content');
        
        try {
            // Reusing the data fetching APIs built for the nurse views
            const vitalsRes = await fetch(`../nurse/api_patient_vitals.php?spid=${spid}`);
            const checkRes = await fetch(`../nurse/api_nurse_data.php?spid=${spid}`);
            const administeredRes = await fetch(`../nurse/api_patient_meds.php?spid=${spid}`);
            
            const vitalsData = await vitalsRes.json();
            const checkData = await checkRes.json();
            const administeredData = await administeredRes.json();
            
            loadingDiv.classList.add('d-none');
            contentDiv.classList.remove('d-none');

            if (vitalsData.success && checkData.success) {
                renderData(vitalsData, checkData, administeredData);
            } else {
                alert(`Error fetching data: ${vitalsData.error || checkData.error}`);
            }

        } catch (e) {
            loadingDiv.classList.add('d-none');
            console.error("Fetch error:", e);
            document.getElementById('patientHeader').innerHTML = `<h1 class="text-danger">System Error: Check Console.</h1>`;
        }
    }

    function renderData(vitalsData, checkData, administeredData) {
        const p = vitalsData.patient;
        
        // 1. Header & Demographics
        const dob = new Date(p.date_of_birth);
        const ageDifMs = Date.now() - dob.getTime();
        const ageDate = new Date(ageDifMs);
        const age = Math.abs(ageDate.getUTCFullYear() - 1970);
        
        document.getElementById('patientNameDisplay').textContent = `${p.first_name} ${p.surnames} (ID: ${p.patient_id})`;
        document.getElementById('procedureNameDisplay').textContent = p.procedure_name;
        
        document.getElementById('demographics').innerHTML = `
            <p class="mb-1"><strong>Gender:</strong> ${p.gender}</p>
            <p class="mb-1"><strong>Age/DOB:</strong> ${age} / ${dob.toLocaleDateString()}</p>
            <hr class="my-2">
            <p class="mb-1"><strong>Current Location (Pre-Op/Ward):</strong> ${checkData.location.ward_name} / ${checkData.location.bed_id}</p>
            <p class="mb-0"><strong>Surgery Date:</strong> ${new Date(p.date_time).toLocaleString()}</p>
        `;
        
        // 2. Allergies (Critical Alerts)
        const allergiesList = document.getElementById('allergiesList');
        allergiesList.innerHTML = '';
        if (vitalsData.allergies.length === 0) {
             allergiesList.innerHTML = '<li class="list-group-item text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i> No known critical allergies.</li>';
        } else {
            vitalsData.allergies.forEach(a => {
                const severityBadge = a.severity.includes('threatening') || a.severity.includes('Severe') ? 'bg-danger' : 'bg-warning text-dark';
                const icon = a.severity.includes('threatening') ? 'bi-lightning-fill' : 'bi-exclamation-octagon-fill';
                allergiesList.innerHTML += `
                    <li class="list-group-item d-flex justify-content-between align-items-center ${a.severity.includes('threatening') ? 'alert-severe fw-bold' : ''}">
                        <div><i class="bi ${icon} me-2"></i> ${a.allergen_name} (${a.category})</div>
                        <span class="badge ${severityBadge}">${a.severity}</span>
                    </li>
                `;
            });
        }
        
        // 3. Medications (EPISODE PRESCRIPTIONS)
        const medsList = document.getElementById('medicationsList');
        medsList.innerHTML = '';
        if (vitalsData.medications.length === 0) {
            medsList.innerHTML = '<li class="list-group-item text-muted">No active medications prescribed in this episode.</li>';
        } else {
            vitalsData.medications.forEach(m => {
                const statusBadge = m.status === 'Active' ? 'bg-success' : 'bg-secondary';
                medsList.innerHTML += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><strong class="text-primary">${m.comercial_name}</strong> (${m.active_principle})</div>
                        <div>${m.dosage} <span class="badge ${statusBadge} ms-2">${m.status}</span></div>
                    </li>
                `;
            });
        }

        // 4. Administered Meds History (INTRA-OP)
        const administeredMedsList = document.getElementById('administeredMedsList');
        administeredMedsList.innerHTML = '';
        if (administeredData.success && administeredData.history.length > 0) {
            administeredData.history.forEach(m => {
                administeredMedsList.innerHTML += `
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-dark">${m.comercial_name}</span>
                            <small class="text-muted">${new Date(m.prescribed_datetime).toLocaleTimeString()} (${m.prescribed_by_name})</small>
                        </div>
                        <small class="text-secondary d-block mt-1">${m.dosage}</small>
                    </li>
                `;
            });
        } else {
            administeredMedsList.innerHTML = '<li class="list-group-item text-muted">No intra-operative medications recorded.</li>';
        }
        
        // 5. Labs
        const labsList = document.getElementById('labsList');
        labsList.innerHTML = '';
        if (vitalsData.labs.length === 0) {
            labsList.innerHTML = '<li class="list-group-item text-muted">No lab results found for this episode.</li>';
        } else {
            vitalsData.labs.forEach(l => {
                labsList.innerHTML += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><strong class="text-dark">${l.test_name}</strong></div>
                        <div>${l.result_value} <span class="text-muted small ms-2">(${new Date(l.result_date).toLocaleDateString()})</span></div>
                    </li>
                `;
            });
        }
        
        // 6. Imaging
        const imagingList = document.getElementById('imagingList');
        imagingList.innerHTML = '';
        if (vitalsData.imaging.length === 0) {
            imagingList.innerHTML = '<li class="list-group-item text-muted">No imaging studies found.</li>';
        } else {
            vitalsData.imaging.forEach(i => {
                imagingList.innerHTML += `
                    <li class="list-group-item">
                        <strong class="text-dark">${i.study_type}</strong> (${new Date(i.study_date).toLocaleDateString()})
                        <p class="small text-muted mb-0 mt-1 fst-italic">${i.results}</p>
                    </li>
                `;
            });
        }
    }
</script>
</body>
</html>