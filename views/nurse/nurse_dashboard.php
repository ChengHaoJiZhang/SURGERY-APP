<?php
// groupd/views/nurse/nurse_dashboard.php 
// REDESIGNED: Clean Aesthetic (Surgeon Style) + Patient Concerns + Pending Reports Section + Reports Tab

session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/nurse_controller.php'; 
require_once __DIR__ . '/../../config/config.php'; 

// 1. AUTHENTICATION
$loginController = new LoginController();
$loginController->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false && strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    header("Location: /login.php"); exit();
}

// 2. CONNECTION & DATA
$pdo = getDBConnection();
$pdo->exec("SET NAMES utf8mb4");

$userId = $_SESSION['user_id'];

// Get Nurse Profile
$stmt = $pdo->prepare("SELECT nurse_id, first_name, surnames FROM NURSES WHERE user_id = ?");
$stmt->execute([$userId]);
$nurseProfile = $stmt->fetch();
$nurseDisplayName = $nurseProfile ? htmlspecialchars($nurseProfile['first_name']) : htmlspecialchars(ucfirst($_SESSION['username']));
$nurseId = $nurseProfile['nurse_id'] ?? 0;

// Get Surgeries (Using existing controller)
$nurseController = new NurseController();
$scheduledSurgeries = $nurseController->getScheduledSurgeriesByNurse($userId);

// Quick Stats
$pendingTasks = array_filter($scheduledSurgeries, function($s) {
    return $s['status'] !== 'Completed';
});
$pendingTaskCount = count($pendingTasks);
$completedCount = count($scheduledSurgeries) - $pendingTaskCount;

// --- FETCH PATIENT CONCERNS ---
$concerns = [];
if ($nurseId) {
    $sqlConcerns = "SELECT DISTINCT pc.message, pc.created_at, p.first_name, p.surnames, p.patient_id
                    FROM PATIENT_CONCERNS pc
                    JOIN PATIENT p ON pc.patient_id = p.patient_id
                    JOIN EPISODE ep ON p.patient_id = ep.patient_id
                    JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id
                    JOIN SURGICAL_PROCEDURE_NURSES spn ON sp.surgical_procedure_id = spn.surgical_procedure_id
                    WHERE spn.nurse_id = ?
                    ORDER BY pc.created_at DESC
                    LIMIT 5";
    try {
        $stmtC = $pdo->prepare($sqlConcerns);
        $stmtC->execute([$nurseId]);
        $concerns = $stmtC->fetchAll();
    } catch (Exception $e) {
        error_log("Concerns fetch error: " . $e->getMessage());
    }
}

// --- FETCH PENDING REPORTS (For the new section) ---
$pendingReports = [];
if ($nurseId) {
    $sqlPending = "SELECT sp.surgical_procedure_id, p.procedure_name, sp.date_time, pat.first_name, pat.surnames
                   FROM SURGICAL_PROCEDURE sp
                   JOIN SURGICAL_PROCEDURE_NURSES spn ON sp.surgical_procedure_id = spn.surgical_procedure_id
                   JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                   JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                   JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                   WHERE spn.nurse_id = :nid 
                     AND sp.status = 'Completed' 
                     AND (sp.nursery_report IS NULL OR sp.nursery_report = '')
                   ORDER BY sp.date_time DESC";
    try {
        $stmtP = $pdo->prepare($sqlPending);
        $stmtP->execute([':nid' => $nurseId]);
        $pendingReports = $stmtP->fetchAll();
    } catch (Exception $e) {
        error_log("Pending reports error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nurse Portal | Hospital Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <style>
        /* COLOR PALETTE - MATCHING SURGEON DASHBOARD */
        :root { 
            --bg-body: #eaeff2; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #0ea5e9; /* Light Blue for Nurses */
            --primary-dark: #0284c7;
            --surface: #ffffff;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            
            --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-depth: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
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
        .brand { font-weight: 800; color: var(--primary-dark); font-size: 1.25rem; display: flex; align-items: center; gap: 12px; letter-spacing: -0.02em; }
        .brand-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); }

        /* TABS */
        .nav-pills { gap: 8px; background: #f1f5f9; padding: 4px; border-radius: 12px; display: inline-flex; flex-wrap: nowrap; margin-bottom: 30px; }
        .nav-pills .nav-link { color: var(--text-muted); font-weight: 600; border-radius: 8px; padding: 8px 20px; font-size: 0.9rem; transition: all 0.2s; white-space: nowrap; }
        .nav-pills .nav-link.active { background-color: var(--surface); color: var(--primary-dark); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        /* CARDS */
        .card-depth { background: var(--surface); border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: var(--shadow-soft); transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; height: 100%; position: relative; }
        .card-depth:hover { transform: translateY(-2px); box-shadow: var(--shadow-depth); border-color: #cbd5e1; }
        
        /* KPI GRID */
        .kpi-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 30px; }
        .kpi-card { background: var(--surface); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid #f1f5f9; display: flex; flex-direction: column; justify-content: center; text-align: center; transition: transform 0.2s; }
        .kpi-card:hover { transform: scale(1.02); }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 8px; }
        .kpi-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; color: var(--text-muted); }
        
        /* TABLE STYLES */
        .table-custom thead th { text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid #e2e8f0; padding: 15px 20px; background: #f8fafc; }
        .table-custom tbody td { padding: 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.95rem; }
        .table-custom tbody tr:hover { background-color: #f8fafc; }

        /* BUTTONS & BADGES */
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 10px; padding: 8px 16px; font-weight: 600; transition: 0.2s; box-shadow: var(--shadow-soft); }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
        
        .btn-action-primary { background: var(--text-dark); color: white; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-action-primary:hover { background: #0f172a; color: white; transform: translateY(-1px); }

        .btn-action-danger { background: var(--danger); color: white; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2); animation: pulse 2s infinite; }
        
        .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-scheduled { background-color: var(--primary); }
        .status-progress { background-color: var(--danger); box-shadow: 0 0 8px rgba(239, 68, 68, 0.6); }
        .status-completed { background-color: var(--success); }

        /* Concern Card Styles */
        .concern-item { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
        .concern-item:last-child { border-bottom: none; }
        .concern-item:hover { background: #fffbeb; }
        .concern-text { font-size: 0.9rem; color: #475569; font-style: italic; }
        .concern-meta { font-size: 0.75rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; }

        /* PENDING REPORTS STYLES (New) */
        .strip-pending { background: linear-gradient(to bottom, var(--warning), #f59e0b); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }
        .section-title { font-size: 0.9rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ''; flex-grow: 1; height: 1px; background: #e2e8f0; }

        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
    </style>
</head>
<body>

    <header class="top-header">
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-clipboard-pulse"></i></div>
            <span>Nurse Portal</span>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo $nurseDisplayName; ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">Registered Nurse</div>
            </div>
            <div class="border-start ps-3 ms-2">
                <a href="../../controllers/logout.php" class="btn btn-glass btn-sm text-danger border-0"><i class="bi bi-power"></i></a>
            </div>
        </div>
    </header>

    <div class="container py-5" style="max-width: 1400px;">
        
        <ul class="nav nav-pills" id="nurseTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dashboard-tab"><i class="bi bi-grid-fill me-2"></i> Dashboard</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar-tab" onclick="initCalendar()"><i class="bi bi-calendar-range me-2"></i> Calendar</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports-tab" onclick="loadReports()"><i class="bi bi-file-earmark-text me-2"></i> Reports</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#patients-tab" onclick="loadPatients()"><i class="bi bi-people-fill me-2"></i> Patients</button></li>
        </ul>

        <div class="tab-content">
            
            <div class="tab-pane fade show active" id="dashboard-tab">
                
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="kpi-container mb-0">
                            <div class="kpi-card">
                                <div class="kpi-value text-primary"><?php echo $pendingTaskCount; ?></div>
                                <div class="kpi-title">Active Cases</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-value text-success"><?php echo $completedCount; ?></div>
                                <div class="kpi-title">Completed Today</div>
                            </div>
                            <div class="kpi-card" style="background: #fffbeb; border-color: #fef3c7;">
                                <div class="kpi-value text-warning"><i class="bi bi-bell"></i></div>
                                <div class="kpi-title text-warning-emphasis">System Alerts</div>
                                <small class="text-muted mt-2">Check Pre-Op status.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card-depth h-100">
                            <div class="p-3 border-bottom bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0 text-uppercase ls-1" style="font-size: 0.8rem;">
                                    <i class="bi bi-chat-quote-fill me-2 text-warning"></i> Patient Voices
                                </h6>
                                <span class="badge bg-white text-warning border"><?php echo count($concerns); ?> New</span>
                            </div>
                            <div class="overflow-auto" style="max-height: 200px;">
                                <?php if (empty($concerns)): ?>
                                    <div class="p-4 text-center text-muted small fst-italic">No recent patient concerns recorded.</div>
                                <?php else: ?>
                                    <?php foreach ($concerns as $c): ?>
                                        <div class="concern-item">
                                            <span class="concern-meta">
                                                <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['surnames']); ?> 
                                                &bull; <?php echo date('H:i', strtotime($c['created_at'])); ?>
                                            </span>
                                            <div class="concern-text">"<?php echo htmlspecialchars($c['message']); ?>"</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if(!empty($pendingReports)): ?>
                <div class="section-title text-warning"><i class="bi bi-file-earmark-text me-2"></i> Pending Documentation</div>
                <div class="row g-4 mb-4">
                    <?php foreach($pendingReports as $pr): ?>
                    <div class="col-md-4">
                        <div class="card-depth ps-4">
                            <div class="strip-pending"></div>
                            <div class="p-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($pr['procedure_name']); ?></div>
                                    <small class="text-muted d-block mt-1"><?php echo date('d M', strtotime($pr['date_time'])); ?> • <?php echo htmlspecialchars($pr['first_name']); ?></small>
                                </div>
                                <a href="nursery_report.php?spid=<?php echo $pr['surgical_procedure_id']; ?>" class="btn btn-glass text-warning border-warning"><i class="bi bi-pencil-fill"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="card-depth">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0 text-uppercase ls-1"><i class="bi bi-list-task me-2 text-primary"></i> Assigned Workload</h6>
                        <span class="badge bg-light text-dark border"><?php echo count($scheduledSurgeries); ?> Records</span>
                    </div>
                    
                    <div class="table-responsive">
                        <?php if (empty($scheduledSurgeries)): ?>
                            <div class="p-5 text-center text-muted fst-italic">
                                <i class="bi bi-clipboard-check fs-1 d-block mb-3 opacity-25"></i>
                                No active surgeries assigned to you at the moment.
                            </div>
                        <?php else: ?>
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Time</th>
                                        <th>Patient Details</th>
                                        <th>Procedure / Room</th>
                                        <th>Your Role</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduledSurgeries as $s): ?>
                                    <?php 
                                        $isCompleted = $s['status'] === 'Completed';
                                        $isLive = $s['status'] === 'In Progress';
                                        $spid = $s['surgical_procedure_id'];
                                        
                                        $dotClass = 'status-scheduled';
                                        if($isLive) $dotClass = 'status-progress';
                                        if($isCompleted) $dotClass = 'status-completed';
                                        
                                        if ($isCompleted) {
                                            $btnHtml = '<a href="nursery_report.php?spid='.$spid.'" class="btn btn-glass btn-sm"><i class="bi bi-file-text me-1"></i> Report</a>';
                                        } elseif ($isLive) {
                                            $btnHtml = '<a href="surgery_live.php?id='.$spid.'" class="btn btn-action-danger"><i class="bi bi-activity me-1"></i> Live Monitor</a>';
                                        } else {
                                            $btnHtml = '<a href="pre_op_check.php?spid='.$spid.'" class="btn btn-action-primary"><i class="bi bi-shield-check me-1"></i> Pre-Op Check</a>';
                                        }
                                    ?>
                                    <tr style="<?php echo $isCompleted ? 'opacity: 0.7;' : ''; ?>">
                                        <td class="ps-4 fw-bold text-muted font-monospace">
                                            <?php echo date('H:i', strtotime($s['date_time'])); ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['patient_name']); ?></div>
                                            <div class="small text-muted">ID: <?php echo htmlspecialchars($s['patient_id']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-primary fw-bold mb-1"><?php echo htmlspecialchars($s['procedure_name']); ?></div>
                                            <div class="badge bg-light text-dark border"><i class="bi bi-door-open me-1"></i> <?php echo htmlspecialchars($s['room_number'] ?? 'TBD'); ?></div>
                                        </td>
                                        <td>
                                            <span class="small text-muted fw-bold text-uppercase"><?php echo htmlspecialchars($s['role_in_surgery']); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="status-dot <?php echo $dotClass; ?>"></span>
                                                <span class="small fw-bold text-dark"><?php echo htmlspecialchars($s['status']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2">
                                                <?php echo $btnHtml; ?>
                                                <a href="clinical_episode.php?id=<?php echo $s['episode_id'] ?? 0; ?>" class="btn btn-glass btn-sm text-secondary" title="View Episode">
                                                    <i class="bi bi-folder2-open"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="calendar-tab">
                <div class="card-depth p-4">
                    <h6 class="fw-bold text-muted text-uppercase small mb-4">Operating Room Schedule</h6>
                    <div id="calendar"></div>
                </div>
            </div>

            <div class="tab-pane fade" id="reports-tab">
                <div id="reportsContent" class="card-depth p-0">
                    <div class="p-5 text-center text-muted">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <div>Loading reports...</div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="history-tab">
                <div id="historyContent" class="card-depth p-0">
                    <div class="p-5 text-center text-muted">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <div>Loading history...</div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="patients-tab">
                <div id="patientsContent" class="card-depth p-0">
                    <div class="p-5 text-center text-muted">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <div>Loading patients directory...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- JAVASCRIPT LOGIC ---
        
        let calendarInitialized = false;

        function initCalendar() {
            if (calendarInitialized) return;
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                themeSystem: 'bootstrap5',
                events: 'api_nurse_calendar.php', 
                height: 600,
                eventClick: function(info) {
                    alert(`Procedure: ${info.event.title}\nStatus: ${info.event.extendedProps.status}`);
                }
            });
            calendar.render();
            calendarInitialized = true;
        }

        async function loadHistory() {
            const container = document.getElementById('historyContent');
            if (container.dataset.loaded) return; 
            try {
                const res = await fetch('nurse_history.php');
                if (res.ok) {
                    container.innerHTML = await res.text();
                    container.dataset.loaded = true;
                } else {
                    container.innerHTML = '<div class="alert alert-danger m-4">Error loading history content.</div>';
                }
            } catch (e) {
                container.innerHTML = '<div class="alert alert-danger m-4">Network error loading history.</div>';
            }
        }

        // ADDED LOAD REPORTS FUNCTION
        async function loadReports() {
            const container = document.getElementById('reportsContent');
            if (container.dataset.loaded) return; 
            try {
                // Reusing nurse_history.php as it displays the report history list
                const res = await fetch('nurse_history.php');
                if (res.ok) {
                    container.innerHTML = await res.text();
                    container.dataset.loaded = true;
                } else {
                    container.innerHTML = '<div class="alert alert-danger m-4">Error loading reports content.</div>';
                }
            } catch (e) {
                container.innerHTML = '<div class="alert alert-danger m-4">Network error loading reports.</div>';
            }
        }
        
        async function loadPatients() {
            const container = document.getElementById('patientsContent');
            if (container.dataset.loaded) return; 
            try {
                const res = await fetch('nurse_patients.php');
                if (res.ok) {
                    container.innerHTML = await res.text();
                    container.dataset.loaded = true;
                } else {
                    container.innerHTML = '<div class="alert alert-danger m-4">Error loading patients content.</div>';
                }
            } catch (e) {
                container.innerHTML = '<div class="alert alert-danger m-4">Network error loading patients.</div>';
            }
        }
    </script>
</body>
</html>