<?php
// groupd/views/admin/admin_scheduler_advanced.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController(); 
$controller->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) die("Access Denied.");

$pdo = getDBConnection();

// 1. CARGAR LISTA DE ESPERA (Status = Pending)
// CORRECCIÓN: Join a través de EPISODE para asegurar que siempre salga el paciente
$pendingSql = "
    SELECT sp.surgical_procedure_id, p.procedure_name, p.default_duration, 
           pat.first_name, pat.surnames, pat.patient_id, sp.priority_level,
           p.speciality_id, s.Speciality_name
    FROM SURGICAL_PROCEDURE sp
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    JOIN EPISODE ep ON sp.episode_id = ep.episode_id        -- JOIN SEGURO
    JOIN PATIENT pat ON ep.patient_id = pat.patient_id      -- JOIN SEGURO
    LEFT JOIN SPECIALITIES s ON p.speciality_id = s.Speciality_ID
    WHERE sp.status = 'Pending'
    ORDER BY FIELD(sp.priority_level, 'Emergency', 'Urgent', 'Elective'), sp.created_at ASC
";
$waitingList = $pdo->query($pendingSql)->fetchAll(PDO::FETCH_ASSOC);

// 2. CARGAR TODOS LOS QUIRÓFANOS
$rooms = $pdo->query("SELECT * FROM OPERATING_ROOM ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Scheduler | Admin</title>
    
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏥</text></svg>">
    
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

        .main-area { flex-grow: 1; padding: 30px; overflow-y: auto; display: flex; gap: 25px; }
        .col-left { width: 350px; display: flex; flex-direction: column; flex-shrink: 0; }
        .col-right { flex-grow: 1; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow-y: auto; }
        
        .card-patient { background: white; border-radius: 12px; padding: 15px; border-left: 5px solid #ccc; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s; cursor: pointer; margin-bottom: 15px; }
        .card-patient:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        
        .prio-Emergency { border-left-color: #dc3545; }
        .prio-Urgent { border-left-color: #ffc107; }
        .prio-Elective { border-left-color: #0d6efd; }
        
        .badge-spec { font-size: 0.7rem; background: #e9ecef; color: #344767; padding: 3px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase; }
        .room-card { border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; height: 100%; transition: 0.2s; background: #f8f9fa; }
        .room-card:hover { background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .room-tag { font-size: 0.65rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="mb-4 px-2 text-white fw-bold fs-5"><i class="bi bi-hospital-fill me-2"></i> Les Corts Admin</div>
        <a href="admin_dashboard.php"><span class="icon-box"><i class="bi bi-grid-fill"></i></span> Dashboard</a>
        <a href="#" class="active"><span class="icon-box"><i class="bi bi-calendar-check-fill"></i></span> Scheduler</a>
        <a href="admin_requests.php"><span class="icon-box"><i class="bi bi-envelope-fill"></i></span> Requests</a>
        <a href="admin_patients.php"><span class="icon-box"><i class="bi bi-people-fill"></i></span> Patients</a>
        <div class="mt-auto mb-4"><a href="../../controllers/logout.php" class="text-danger ps-2 text-decoration-none fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></div>
    </div>

    <div class="main-area">
        <div class="col-left">
            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-hourglass-split me-2"></i> Waiting List</h5>
            <p class="text-muted small mb-3"><?php echo count($waitingList); ?> <?php echo count($waitingList) === 1 ? 'surgery' : 'surgeries'; ?> pending allocation.</p>
            
            <div class="pe-2 pb-5" style="overflow-y: auto; max-height: calc(100vh - 100px);">
                <?php if(empty($waitingList)): ?>
                    <div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> No pending surgeries!</div>
                <?php else: foreach($waitingList as $w): ?>
                    <div class="card-patient prio-<?php echo $w['priority_level']; ?>" onclick='openScheduleModal(<?php echo json_encode($w); ?>)'>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($w['procedure_name']); ?></h6>
                                <div class="text-secondary small mb-2"><i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($w['first_name'].' '.$w['surnames']); ?></div>
                                <span class="badge-spec"><?php echo htmlspecialchars($w['Speciality_name'] ?? 'General'); ?></span>
                            </div>
                            <div class="text-end">
                                <span class="badge <?php echo $w['priority_level']=='Emergency'?'bg-danger':($w['priority_level']=='Urgent'?'bg-warning text-dark':'bg-primary'); ?>"><?php echo $w['priority_level']; ?></span>
                                <div class="small text-muted mt-2 fw-bold"><i class="bi bi-clock"></i> <?php echo $w['default_duration']; ?>m slot</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="col-right">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark"><i class="bi bi-hospital me-2"></i> Operating Rooms Overview</h5>
                <a href="admin_schedule.php" class="btn btn-outline-dark btn-sm fw-bold">View Full Calendar</a>
            </div>
            <div class="row g-4">
                <?php foreach($rooms as $r): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="room-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($r['room_number']); ?></h5>
                            <span class="room-tag <?php echo $r['is_emergency_room']?'bg-danger text-white':'bg-info text-dark'; ?>">
                                <?php echo $r['is_emergency_room']?'TRAUMA':'SPECIALTY'; ?>
                            </span>
                        </div>
                        <div class="text-muted small mb-2"><i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($r['room_type']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold">Schedule Surgery</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="bookingForm">
                        <input type="hidden" id="spId"><input type="hidden" id="specId"><input type="hidden" id="duration">
                        <div class="bg-light p-3 rounded-3 border mb-4">
                            <div class="row">
                                <div class="col-md-6"><small class="text-uppercase text-muted fw-bold">Patient</small><div class="fw-bold fs-5" id="patName">...</div></div>
                                <div class="col-md-6 border-start ps-3"><small class="text-uppercase text-muted fw-bold">Procedure</small><div class="fw-bold text-primary" id="procName">...</div></div>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><label class="form-label fw-bold small text-muted">DATE & TIME</label><input type="datetime-local" id="dateInput" class="form-control form-control-lg" required onchange="checkAvailability()"></div>
                            <div class="col-md-6"><label class="form-label fw-bold small text-muted">ROOM</label><select id="roomInput" class="form-select form-select-lg" required><option value="">Select Date first...</option></select></div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-bold text-primary mb-0">Staff Assignment</h6><span class="badge bg-success bg-opacity-10 text-success border border-success" id="staffStatus" style="display:none;">Available Staff Found</span></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="small fw-bold text-muted">Surgeon</label><select id="surgeonSelect" class="form-select" required disabled><option>Select time...</option></select></div>
                            <div class="col-md-4"><label class="small fw-bold text-muted">Anesthesiologist</label><select id="anesSelect" class="form-select" required disabled><option>Select time...</option></select></div>
                            <div class="col-md-4"><label class="small fw-bold text-muted">Nurse</label><select id="nurseSelect" class="form-select" required disabled><option>Select time...</option></select></div>
                        </div>
                        <div class="d-grid mt-5"><button type="submit" class="btn btn-success fw-bold py-3 shadow-sm rounded-3">Confirm & Schedule</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const roomsData = <?php echo json_encode($rooms); ?>;
        const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        
        function openScheduleModal(surgery) {
            document.getElementById('spId').value = surgery.surgical_procedure_id;
            document.getElementById('patName').textContent = surgery.first_name + ' ' + surgery.surnames;
            document.getElementById('procName').textContent = surgery.procedure_name;
            document.getElementById('specId').value = surgery.speciality_id;
            document.getElementById('duration').value = surgery.default_duration;
            document.getElementById('dateInput').value = '';
            document.getElementById('staffStatus').style.display = 'none';
            populateRooms(surgery.speciality_id);
            disableStaffSelects(true);
            modal.show();
        }

        function populateRooms(specId) {
            const select = document.getElementById('roomInput');
            select.innerHTML = '<option value="">Select Room...</option>';
            roomsData.forEach(r => {
                if (r.speciality_id === specId || r.is_emergency_room == 1) {
                    let label = r.room_number + " (" + r.room_type + ")";
                    if (r.is_emergency_room == 1) label += " 🚨 Trauma";
                    const opt = document.createElement('option');
                    opt.value = r.room_id;
                    opt.textContent = label;
                    select.appendChild(opt);
                }
            });
        }

        async function checkAvailability() {
            const date = document.getElementById('dateInput').value;
            const duration = document.getElementById('duration').value;
            const specId = document.getElementById('specId').value;
            if (!date) return;

            disableStaffSelects(true, "Checking...");
            try {
                const res = await fetch(`api_staff_availability.php?date=${date}&duration=${duration}&spec=${specId}`);
                const data = await res.json();
                if(data.success) {
                    fillSelect('surgeonSelect', data.surgeons, 'surgeon_id');
                    fillSelect('anesSelect', data.anesthesiologists, 'anesthesiologist_id');
                    fillSelect('nurseSelect', data.nurses, 'nurse_id');
                    disableStaffSelects(false);
                    document.getElementById('staffStatus').style.display = 'inline-block';
                }
            } catch (e) { console.error(e); }
        }

        function fillSelect(id, list, idKey) {
            const sel = document.getElementById(id);
            sel.innerHTML = '';
            list.forEach(item => {
                sel.appendChild(new Option(item.first_name + ' ' + item.surnames, item[idKey]));
            });
        }

        function disableStaffSelects(disabled, msg = "Select...") {
            ['surgeonSelect', 'anesSelect', 'nurseSelect'].forEach(id => {
                const el = document.getElementById(id);
                el.disabled = disabled;
                if(disabled) el.innerHTML = `<option>${msg}</option>`;
            });
        }

        document.getElementById('bookingForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                action: 'confirm_schedule',
                surgery_id: document.getElementById('spId').value,
                room_id: document.getElementById('roomInput').value,
                date_time: document.getElementById('dateInput').value,
                staff: {
                    surgeon: document.getElementById('surgeonSelect').value,
                    anesthesiologist: document.getElementById('anesSelect').value,
                    nurse: document.getElementById('nurseSelect').value
                }
            };
            try {
                const res = await fetch('api_schedule_surgery.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
                });
                if((await res.json()).success) { alert("Scheduled!"); location.reload(); }
                else { alert("Error scheduling."); }
            } catch(e) { alert("System Error."); }
        });
    </script>
</body>
</html>
