<?php
// groupd/views/surgeon/surgeon_dashboard.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) { die("Access Denied."); }

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// 1. DATOS DEL CIRUJANO
$stmt = $pdo->prepare("SELECT surgeon_id, first_name, surnames, specialization, personal_notes FROM SURGEONS WHERE user_id = ?");
$procList = $pdo->query("SELECT procedure_id, procedure_name FROM PROCEDURES ORDER BY procedure_name ASC")->fetchAll();
$stmt->execute([$userId]);
$surgeon = $stmt->fetch();
$surgId = $surgeon['surgeon_id'];

// 2. KPIS
$kpiCompleted = $pdo->query("SELECT COUNT(*) FROM SURGICAL_PROCEDURE sp JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id WHERE sps.surgeon_id = $surgId AND sp.status = 'Completed' AND MONTH(sp.date_time) = MONTH(CURDATE())")->fetchColumn();

// 3. NOTIFICACIONES
$stmtReq = $pdo->prepare("SELECT request_type, status, details, created_at FROM SCHEDULE_REQUESTS WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtReq->execute([$userId]);
$notifs = $stmtReq->fetchAll();
$pendingReqs = count(array_filter($notifs, fn($n) => $n['status'] === 'Pending'));

// 4. CONSULTA UNIFICADA (FUTURAS + ACTIVAS) -- CORREGIDA CON LEFT JOIN
$sqlFuture = "SELECT ep.episode_id, sp.surgical_procedure_id, p.procedure_name, sp.date_time, 
               pat.first_name, pat.surnames, pat.patient_id, op.room_number, sp.status, p.default_duration,
               (SELECT COUNT(*) FROM LAB_TESTS_EPISODES lte WHERE lte.episode_id = ep.episode_id AND lte.test_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)) as new_labs,
               (SELECT GROUP_CONCAT(a.allergen_name SEPARATOR ', ') FROM PATIENT_ALLERGIES pa JOIN ALLERGIES a ON pa.allergen_ID = a.allergen_ID WHERE pa.patient_id = pat.patient_id AND pa.severity IN ('Severe', 'Life-threatening')) as critical_allergies,
               (SELECT GROUP_CONCAT(CONCAT(n.first_name,' ',n.surnames) SEPARATOR '<br>') FROM SURGICAL_PROCEDURE_NURSES spn JOIN NURSES n ON spn.nurse_id = n.nurse_id WHERE spn.surgical_procedure_id = sp.surgical_procedure_id) as team_nurses,
               (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.surnames) SEPARATOR '<br>') FROM SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa JOIN ANESTHESIOLOGISTS a ON spa.anesthesiologist_id = a.anesthesiologist_id WHERE spa.surgical_procedure_id = sp.surgical_procedure_id) as team_anes
        FROM SURGICAL_PROCEDURE sp
        JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        JOIN EPISODE ep ON sp.episode_id = ep.episode_id
        JOIN PATIENT pat ON ep.patient_id = pat.patient_id
        LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id -- <--- CORRECCIÓN AQUÍ
        WHERE sps.surgeon_id = :sid AND sp.status NOT IN ('Cancelled', 'Completed')
        ORDER BY sp.date_time ASC";
$stmtFuture = $pdo->prepare($sqlFuture);
$stmtFuture->execute([':sid' => $surgId]);
$allActive = $stmtFuture->fetchAll();

// Clasificación
$live = []; $today = []; $upcoming = [];
$todayDate = date('Y-m-d');

foreach($allActive as $s) {
    $sDate = date('Y-m-d', strtotime($s['date_time']));
    if($s['status'] == 'In Progress') { $live[] = $s; }
    elseif($sDate == $todayDate) { $today[] = $s; }
    elseif($sDate > $todayDate) { $upcoming[] = $s; }
}

// 5. REPORTES PENDIENTES
$sqlPendingRep = "SELECT sp.surgical_procedure_id, p.procedure_name, sp.date_time, pat.first_name, pat.surnames
                  FROM SURGICAL_PROCEDURE sp
                  JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
                  JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                  JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                  JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                  WHERE sps.surgeon_id = :sid AND sp.status = 'Completed' AND (sp.surgical_report IS NULL OR sp.surgical_report = '')
                  ORDER BY sp.date_time DESC";
$stmtPR = $pdo->prepare($sqlPendingRep);
$stmtPR->execute([':sid' => $surgId]);
$pendingReports = $stmtPR->fetchAll();

// 6. HISTORIAL
$sqlPast = "SELECT sp.surgical_procedure_id, p.procedure_name, sp.date_time, sp.surgical_report, sp.status, pat.first_name, pat.surnames
            FROM SURGICAL_PROCEDURE sp
            JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
            JOIN EPISODE ep ON sp.episode_id = ep.episode_id
            JOIN PATIENT pat ON ep.patient_id = pat.patient_id
            WHERE sps.surgeon_id = :sid AND (sp.date_time < CURDATE() OR sp.status = 'Completed')
            ORDER BY sp.date_time DESC LIMIT 50";
$stmtPast = $pdo->prepare($sqlPast); $stmtPast->execute([':sid' => $surgId]); $pastSurgeries = $stmtPast->fetchAll();

// 7. ROUNDING LIST
$sqlRounds = "SELECT pat.first_name, pat.surnames, pat.patient_id, ep.episode_id, ep.date_time_start, w.ward_name 
              FROM EPISODE ep JOIN PATIENT pat ON ep.patient_id = pat.patient_id
              JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id
              JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
              LEFT JOIN WARD w ON sp.ward_id = w.ward_id
              WHERE sps.surgeon_id = :sid AND ep.status = 'Active' GROUP BY ep.episode_id ORDER BY sp.date_time DESC";
$stmtRounds = $pdo->prepare($sqlRounds); $stmtRounds->execute([':sid' => $surgId]); $inpatients = $stmtRounds->fetchAll();

// 8. PREFS & PATIENTS
$prefCards = $pdo->query("SELECT p.procedure_id, p.procedure_name, pref.glove_size, pref.specific_instruments, pref.notes FROM PROCEDURES p LEFT JOIN SURGEON_PREFERENCES pref ON p.procedure_id = pref.procedure_id AND pref.surgeon_id = $surgId ORDER BY p.procedure_name")->fetchAll();
$myPatients = $pdo->query("SELECT pat.patient_id, pat.first_name, pat.surnames, pat.date_of_birth, pat.gender, MAX(sp.date_time) as last_surgery_date FROM PATIENT pat JOIN EPISODE ep ON pat.patient_id = ep.patient_id JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id WHERE sps.surgeon_id = $surgId GROUP BY pat.patient_id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Surgeon Portal | Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <style>
        /* COLOR PALETTE - DEEP & HARMONIOUS */
        :root { 
            --bg-body: #eaeff2; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #2563eb; 
            --primary-dark: #1e40af;
            --surface: #ffffff;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            
            --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-depth: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            --glass-bg: rgba(255, 255, 255, 0.95);
        }
        
        body { background-color: var(--bg-body); font-family: 'Inter', system-ui, sans-serif; color: var(--text-dark); padding-bottom: 80px; }
        
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
        .brand-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); }

        .search-wrapper { position: relative; width: 300px; }
        .search-input { border-radius: 20px; border: 1px solid #e2e8f0; background: #f8fafc; padding-left: 35px; font-size: 0.9rem; transition: 0.2s; }
        .search-input:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 12px; box-shadow: var(--shadow-depth); margin-top: 5px; display: none; z-index: 1050; overflow: hidden; border: 1px solid #f1f5f9; }
        .search-item { padding: 10px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.1s; }
        .search-item:hover { background: #f8fafc; color: var(--primary); }

        .nav-pills { gap: 8px; background: #f1f5f9; padding: 4px; border-radius: 12px; display: inline-flex; flex-wrap: nowrap; }
        .nav-pills .nav-link { color: var(--text-muted); font-weight: 600; border-radius: 8px; padding: 8px 20px; font-size: 0.9rem; transition: all 0.2s; white-space: nowrap; }
        .nav-pills .nav-link.active { background-color: var(--surface); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .card-depth { background: var(--surface); border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: var(--shadow-soft); transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; height: 100%; position: relative; }
        .card-depth:hover { transform: translateY(-4px); box-shadow: var(--shadow-depth); border-color: #cbd5e1; }
        
        .kpi-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 30px; }
        .kpi-card { background: var(--surface); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid #f1f5f9; display: flex; flex-direction: column; justify-content: center; text-align: center; transition: transform 0.2s; }
        .kpi-card:hover { transform: scale(1.02); }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 8px; }
        .kpi-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; color: var(--text-muted); }
        
        .notes-area { border: none; resize: none; background: #fefce8; color: #854d0e; font-size: 0.9rem; border-radius: 8px; padding: 10px; width: 100%; height: 80px; font-family: 'Courier New', monospace; outline: none; }
        .notes-card { background: #fffbeb; border-color: #fef3c7; }

        .strip-live { background: linear-gradient(to bottom, var(--danger), #ef4444); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }
        .strip-today { background: linear-gradient(to bottom, var(--primary), #3b82f6); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }
        .strip-pending { background: linear-gradient(to bottom, var(--warning), #f59e0b); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }
        .time-box { background: #f8fafc; padding: 8px 12px; border-radius: 8px; font-family: 'Monaco', monospace; font-weight: 700; color: var(--text-dark); font-size: 0.9rem; border: 1px solid #e2e8f0; }

        .btn-premium { background: var(--text-dark); color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(30, 41, 59, 0.2); transition: 0.2s; }
        .btn-premium:hover { background: #0f172a; transform: translateY(-1px); color: white; box-shadow: 0 6px 16px rgba(30, 41, 59, 0.3); }
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 10px; padding: 8px 16px; font-weight: 600; transition: 0.2s; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; }

        .alert-modern { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-top: 12px; }
        
        .round-item { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.2s; }
        .round-item:hover { background: #f8fafc; padding-left: 24px; }
        .initials { width: 42px; height: 42px; background: #e0e7ff; color: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        .section-title { font-size: 0.9rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ''; flex-grow: 1; height: 1px; background: #e2e8f0; }
        
        .animate-pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>
</head>
<body>

    <header class="top-header">
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-activity"></i></div>
            <span class="d-none d-md-inline">SurgicalOS</span>
        </div>
        
        <div class="search-wrapper mx-4 d-none d-md-block">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control search-input" id="globalSearch" placeholder="Search patient...">
            <div id="searchResults" class="search-results"></div>
        </div>
        
        <ul class="nav nav-pills d-none d-lg-flex flex-nowrap" id="mainTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview">Dashboard</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar-view">Calendar</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports">Reports</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#patients">My Patients</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#preferences">Preferences</button></li>
        </ul>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <button class="btn btn-glass btn-sm position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRounds">
                <i class="bi bi-clipboard-pulse me-2 text-primary"></i> Rounds
                <?php if(count($inpatients)>0): ?>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                <?php endif; ?>
            </button>
            
            <div class="dropdown">
                <button class="btn btn-glass btn-sm border-0" data-bs-toggle="dropdown"><i class="bi bi-bell-fill text-muted"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3" style="width: 320px; border-radius: 12px;">
                    <li class="fw-bold text-secondary small mb-2">NOTIFICATIONS</li>
                    <?php if(empty($notifs)): ?><li class="text-center text-muted small py-2">All caught up</li><?php else: foreach($notifs as $n): 
                        $col = $n['status']=='Resolved'?'text-success':($n['status']=='Rejected'?'text-danger':'text-warning'); ?>
                        <li class="py-2 border-bottom d-flex justify-content-between align-items-center">
                            <div><div class="fw-bold fs-7"><?php echo $n['request_type']; ?></div><div class="text-muted small"><?php echo $n['details']; ?></div></div>
                            <span class="<?php echo $col; ?> fw-bold small"><?php echo $n['status']; ?></span>
                        </li>
                    <?php endforeach; endif; ?>
                    <li class="mt-3"><button onclick="openRequestModal('New Surgery Request')" class="btn btn-primary w-100 btn-sm">New Request</button></li>
                </ul>
            </div>
            
            <div class="border-start ps-3 ms-2">
                <a href="../../controllers/logout.php" class="btn btn-glass btn-sm text-danger border-0"><i class="bi bi-power"></i></a>
            </div>
        </div>
    </header>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRounds" style="width: 380px;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold text-dark">Inpatient Rounds</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 bg-light">
            <?php if(empty($inpatients)): ?>
                <div class="p-5 text-center text-muted">No active inpatients.</div>
            <?php else: foreach($inpatients as $p): ?>
                <div class="round-item bg-white" onclick="window.location.href='clinical_episode.php?id=<?php echo $p['episode_id']; ?>'">
                    <div class="d-flex align-items-center gap-3">
                        <div class="initials"><?php echo substr($p['first_name'],0,1).substr($p['surnames'],0,1); ?></div>
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></div>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($p['ward_name']??'Unassigned'); ?></small>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        <div class="tab-content" id="myTabContent">
            
            <div class="tab-pane fade show active" id="overview">
                
                <div class="kpi-container">
                    <div class="kpi-card" style="cursor: pointer;" onclick="document.querySelector('[data-bs-target=\'#reports\']').click()">
                        <div class="kpi-value text-success"><?php echo $kpiCompleted; ?></div>
                        <div class="kpi-title">Completed (Mo)</div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-value text-warning"><?php echo count($pendingReports); ?></div>
                        <div class="kpi-title">Missing Reports</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value text-primary"><?php echo count($today) + count($live); ?></div>
                        <div class="kpi-title">Total Today</div>
                    </div>
                    
                    <div class="kpi-card notes-card p-2 text-start">
                        <small class="fw-bold text-warning mb-1 ps-1"><i class="bi bi-sticky-fill"></i> QUICK NOTES</small>
                        <textarea class="notes-area" id="quickNotes" placeholder="Personal reminders..."><?php echo htmlspecialchars($surgeon['personal_notes'] ?? ''); ?></textarea>
                        <small id="saveStatus" class="text-muted d-block text-end pe-2" style="font-size: 0.7rem; height: 15px;"></small>
                    </div>
                </div>

                <?php if(!empty($live)): ?>
                <div class="section-title text-danger"><i class="bi bi-broadcast me-2"></i> Live Operations</div>
                <div class="row g-4 mb-5">
                    <?php foreach($live as $s): ?>
                    <div class="col-lg-6">
                        <div class="card-depth ps-4">
                            <div class="strip-live"></div>
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-danger animate-pulse px-3 py-2">LIVE IN <?php echo $s['room_number'] ?? 'TBD'; ?></span>
                                    <span class="time-box border-danger text-danger"><?php echo date('H:i', strtotime($s['date_time'])); ?></span>
                                </div>
                                <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($s['procedure_name']); ?></h3>
                                <p class="text-muted fs-5 mb-4"><?php echo htmlspecialchars($s['first_name'].' '.$s['surnames']); ?></p>
                                <a href="surgery_monitor.php?id=<?php echo $s['surgical_procedure_id']; ?>" class="btn btn-danger w-100 py-3 fw-bold shadow-sm">ENTER COCKPIT</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if(!empty($pendingReports)): ?>
                <div class="section-title text-warning"><i class="bi bi-file-earmark-text me-2"></i> Pending Documentation</div>
                <div class="row g-4 mb-5">
                    <?php foreach($pendingReports as $pr): ?>
                    <div class="col-md-4">
                        <div class="card-depth ps-4">
                            <div class="strip-pending"></div>
                            <div class="p-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($pr['procedure_name']); ?></div>
                                    <small class="text-muted d-block mt-1"><?php echo date('d M', strtotime($pr['date_time'])); ?> • <?php echo htmlspecialchars($pr['first_name']); ?></small>
                                </div>
                                <a href="operative_report.php?id=<?php echo $pr['surgical_procedure_id']; ?>" class="btn btn-glass text-warning border-warning"><i class="bi bi-pencil-fill"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="section-title text-primary"><i class="bi bi-calendar-day me-2"></i> Today's Schedule</div>
                
                <?php if(empty($today)): ?>
                    <div class="card-depth p-5 text-center text-muted fst-italic">No remaining procedures for today.</div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach($today as $s): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-depth ps-4">
                            <div class="strip-today"></div>
                            <div class="p-4 d-flex flex-column h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="time-box"><?php echo date('H:i', strtotime($s['date_time'])); ?></span>
                                    <span class="text-muted small fw-bold"><i class="bi bi-door-open-fill"></i> <?php echo $s['room_number'] ?? 'TBD'; ?></span>
                                </div>
                                
                                <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($s['procedure_name']); ?></h5>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($s['first_name'].' '.$s['surnames']); ?></p>
                                
                                <?php if(!empty($s['critical_allergies'])): ?>
                                    <div class="alert-modern mb-3" data-allergies="<?php echo htmlspecialchars($s['critical_allergies']); ?>">
                                        <i class="bi bi-shield-exclamation"></i> 
                                        <span>Allergy Alert</span>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-auto d-flex gap-2 pt-3">
                                    <button onclick="confirmStart(<?php echo $s['surgical_procedure_id']; ?>, '<?php echo htmlspecialchars($s['first_name'].' '.$s['surnames']); ?>', '<?php echo htmlspecialchars($s['procedure_name']); ?>', '<?php echo htmlspecialchars($s['critical_allergies']??''); ?>')" class="btn btn-premium flex-grow-1">Start Case</button>
                                    <a href="clinical_episode.php?id=<?php echo $s['episode_id']; ?>" class="btn btn-glass px-3"><i class="bi bi-folder2-open"></i></a>
                                    
                                    <span class="btn btn-glass px-3 border-0" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true" 
                                          title="Surgical Team" data-bs-content="<div class='small'><strong>Nurses:</strong><br><?php echo $s['team_nurses']?:'-'; ?><br><br><strong>Anesthesia:</strong><br><?php echo $s['team_anes']?:'-'; ?></div>">
                                        <i class="bi bi-people"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($upcoming)): ?>
                <div class="mt-5 pt-4 border-top">
                    <h6 class="fw-bold text-muted text-uppercase small mb-4">Upcoming Schedule</h6>
                    <div class="row g-3">
                        <?php foreach($upcoming as $s): ?>
                        <div class="col-md-3">
                            <div class="card-depth p-3 opacity-75">
                                <div class="small fw-bold text-primary mb-1"><?php echo date('d M - H:i', strtotime($s['date_time'])); ?></div>
                                <div class="text-dark fw-bold text-truncate"><?php echo htmlspecialchars($s['procedure_name']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="tab-pane fade" id="calendar-view">
                <div class="card-depth p-4"><div id='calendar'></div></div>
            </div>

            <div class="tab-pane fade" id="reports">
                <div class="row g-4">
                    <?php if(empty($pastSurgeries)): ?><div class="col-12 text-center text-muted p-5">No history found.</div><?php endif; ?>
                    <?php foreach($pastSurgeries as $h): $hasReport=!empty($h['surgical_report']); ?>
                    <div class="col-md-6">
                        <div class="card-depth p-4 d-flex justify-content-between align-items-center" style="border-left: 4px solid <?php echo $hasReport?'#10b981':'#f59e0b'; ?>">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($h['procedure_name']); ?></h6>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($h['date_time'])); ?> • <?php echo htmlspecialchars($h['first_name']); ?></small>
                            </div>
                            <a href="operative_report.php?id=<?php echo $h['surgical_procedure_id']; ?>" class="btn btn-sm <?php echo $hasReport?'btn-glass text-success':'btn-premium bg-warning border-warning text-dark'; ?>">
                                <?php echo $hasReport?'View Record':'Write Report'; ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="patients">
                <div class="card-depth p-0 overflow-hidden">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light text-label"><tr><th class="ps-4 py-3">Patient</th><th>Age/Gender</th><th>Last Visit</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach($myPatients as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark py-3"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></td>
                                <td class="text-muted"><?php echo $p['gender']; ?> / <?php echo (date('Y') - date('Y', strtotime($p['date_of_birth']))); ?> yrs</td>
                                <td class="text-muted"><?php echo date('d M Y', strtotime($p['last_surgery_date'])); ?></td>
                                <td class="text-end pe-4"><button onclick="viewHistory('<?php echo $p['patient_id']; ?>','<?php echo htmlspecialchars($p['first_name']); ?>')" class="btn btn-sm btn-glass">Record</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="preferences">
                <div class="card-depth p-0 overflow-hidden">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light">
                        <h6 class="fw-bold text-muted mb-0 text-uppercase">Material & Instrument Preferences</h6>
                        <button onclick="openPrefModal()" class="btn btn-primary btn-sm btn-premium">
                            <i class="bi bi-plus-lg me-1"></i> Add Custom Card
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-white text-secondary small">
                                <tr>
                                    <th class="ps-4 py-3">Procedure</th>
                                    <th>Glove Size</th>
                                    <th>Specific Instruments</th>
                                    <th>Notes</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($prefCards)): ?>
                                    <tr><td colspan="5" class="text-center p-5 text-muted">No preference cards set up yet.</td></tr>
                                <?php else: foreach($prefCards as $pc): 
                                    // Preparamos datos seguros para JS
                                    $jsId = $pc['procedure_id'];
                                    $jsName = htmlspecialchars($pc['procedure_name'], ENT_QUOTES);
                                    $jsGlove = htmlspecialchars($pc['glove_size'] ?? '', ENT_QUOTES);
                                    $jsInstr = htmlspecialchars($pc['specific_instruments'] ?? '', ENT_QUOTES);
                                    $jsNotes = htmlspecialchars($pc['notes'] ?? '', ENT_QUOTES);
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo $pc['procedure_name']; ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $pc['glove_size'] ?? 'Default'; ?></span></td>
                                    <td class="small text-muted" style="max-width: 250px;"><div class="text-truncate"><?php echo $pc['specific_instruments'] ?? '-'; ?></div></td>
                                    <td class="small text-muted" style="max-width: 250px;"><div class="text-truncate"><?php echo $pc['notes'] ?? '-'; ?></div></td>
                                    <td class="text-end pe-4">
                                        <button onclick="openPrefModal('<?php echo $jsId; ?>', '<?php echo $jsName; ?>', '<?php echo $jsGlove; ?>', '<?php echo $jsInstr; ?>', '<?php echo $jsNotes; ?>')" class="btn btn-sm btn-glass text-primary">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="preOpModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>Surgical Safety Check (WHO)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <strong class="d-block text-dark mb-1" id="poPatient">Patient Name</strong>
                        <span class="text-muted" id="poProcedure">Procedure Name</span>
                    </div>
                    <div id="poAllergyBox" class="alert alert-danger d-none mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>CRITICAL ALLERGY:</strong> <span id="poAllergyText"></span>
                    </div>
                    <h6 class="text-uppercase text-muted fw-bold small mb-3">Time-Out Verification</h6>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="chkIdentity"><label class="form-check-label" for="chkIdentity">Patient Identity Confirmed</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="chkSite"><label class="form-check-label" for="chkSite">Surgical Site Marked</label></div>
                    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="chkConsent"><label class="form-check-label" for="chkConsent">Consent Signed & Available</label></div>
                    <button type="button" onclick="finalizeStart()" class="btn btn-success w-100 py-2 fw-bold shadow-sm">CONFIRM & GO LIVE</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">New Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="requestForm">
                        <input type="hidden" id="reqSurgeryId">
                        <input type="hidden" id="reqPatientId"> 
                        <div class="mb-3 position-relative">
                            <label class="small fw-bold text-muted mb-1">Patient</label>
                            <input type="text" id="reqPatientName" class="form-control" placeholder="Search name or ID..." autocomplete="off">
                            <div id="patientSearchResults" class="list-group position-absolute w-100 shadow" style="display:none; z-index:1050; max-height: 200px; overflow-y:auto;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Type</label>
                            <select id="reqType" class="form-select" onchange="toggleProcSelect(this.value)">
                                <option value="New Surgery Request">New Surgery Request</option>
                                <option value="Reschedule">Reschedule</option>
                                <option value="Cancellation">Cancellation</option>
                            </select>
                        </div>

                        <div class="mb-3" id="divReqProc">
                            <label class="small fw-bold text-muted mb-1">Procedure</label>
                            <select id="reqProcedure" class="form-select">
                                <option value="">Select Procedure...</option>
                                <?php foreach($procList as $proc): ?>
                                    <option value="<?php echo $proc['procedure_id']; ?>">
                                        <?php echo htmlspecialchars($proc['procedure_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Details</label>
                            <textarea id="reqDetails" class="form-control" rows="3" placeholder="Describe procedure, priority, or reason..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-premium w-100 py-2">Send Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 pb-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="histTitle">Patient Record</h5>
                        <p class="mb-0 small opacity-75" id="histSubtitle">ID: ...</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-0">
                    <div class="bg-white p-4 border-bottom"><div class="row g-3" id="patientInfoSection"></div></div>
                    <div class="px-4 py-3 bg-white border-bottom" id="allergiesSection"></div>
                    <div class="p-0">
                        <div class="bg-light px-4 py-2 border-bottom"><h6 class="fw-bold text-muted small mb-0 text-uppercase">Surgical History</h6></div>
                        <table class="table table-hover mb-0 align-middle"><tbody id="histTableBody"></tbody></table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="eventOptionsModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-body text-center p-4"><h5 id="calProcName" class="fw-bold mb-1"></h5><p id="calPatName" class="text-muted small mb-4"></p><div class="d-grid gap-2"><a id="btnGoEpisode" href="#" class="btn btn-premium btn-sm">Open File</a><button id="btnReqChange" class="btn btn-glass btn-sm text-danger">Reschedule</button></div></div></div></div></div>

    <div class="modal fade" id="prefModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" id="prefModalTitle">Edit Preference Card</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="prefForm">
                        <input type="hidden" id="prefProcId">
                        
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Procedure</label>
                            <input type="text" id="prefProcName" class="form-control" placeholder="e.g., Laparoscopic Cholecystectomy">
                            <small class="text-muted" style="font-size:0.75rem">For "Add", type the exact procedure name.</small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Glove Size</label>
                                <select id="prefGlove" class="form-select">
                                    <option value="">Select...</option>
                                    <option>6.0</option><option>6.5</option><option>7.0</option><option>7.5</option><option>8.0</option><option>8.5</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Specific Instruments</label>
                            <textarea id="prefInstr" class="form-control" rows="3" placeholder="List special trays or tools..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Setup Notes</label>
                            <textarea id="prefNotes" class="form-control" rows="2" placeholder="Positioning, prep, etc..."></textarea>
                        </div>

                        <button type="button" onclick="savePreferences()" class="btn btn-premium w-100 py-2">Save Preference Card</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // INIT TOOLTIPS
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) { return new bootstrap.Popover(popoverTriggerEl) })
        
        // --- PREFERENCE CARD LOGIC (NUEVO) ---
        const prefModal = new bootstrap.Modal(document.getElementById('prefModal'));

        function openPrefModal(id = '', name = '', glove = '', instr = '', notes = '') {
            document.getElementById('prefProcId').value = id;
            document.getElementById('prefProcName').value = name;
            document.getElementById('prefGlove').value = glove;
            document.getElementById('prefInstr').value = instr;
            document.getElementById('prefNotes').value = notes;
            
            // Si hay ID, es editar (Nombre readonly), si no, es añadir
            if(id) {
                document.getElementById('prefModalTitle').textContent = "Edit Preference Card";
                document.getElementById('prefProcName').readOnly = true;
                document.getElementById('prefProcName').classList.add('bg-light');
            } else {
                document.getElementById('prefModalTitle').textContent = "Add New Card";
                document.getElementById('prefProcName').readOnly = false;
                document.getElementById('prefProcName').classList.remove('bg-light');
            }
            
            prefModal.show();
        }

        async function savePreferences() {
            const data = {
                proc_id: document.getElementById('prefProcId').value,
                proc_name: document.getElementById('prefProcName').value,
                glove: document.getElementById('prefGlove').value,
                instr: document.getElementById('prefInstr').value,
                notes: document.getElementById('prefNotes').value
            };

            // Simulamos guardado ya que falta el backend PHP
            // await sendData('api_save_prefs.php', data, () => { location.reload(); });
            
            console.log("Saving preferences:", data);
            alert("Preference Card Saved! (Simulation)");
            prefModal.hide();
            // location.reload(); // Descomentar cuando el backend exista
        }

        // --- QUICK NOTES LOGIC ---
        const noteArea = document.getElementById('quickNotes');
        const statusLabel = document.getElementById('saveStatus');
        let timeoutId;

        noteArea.addEventListener('input', () => {
            statusLabel.textContent = "Typing...";
            clearTimeout(timeoutId);
            timeoutId = setTimeout(async () => {
                statusLabel.textContent = "Saving...";
                try {
                    const res = await fetch('api_save_notes.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ notes: noteArea.value })
                    });
                    const data = await res.json();
                    if(data.success) {
                        statusLabel.textContent = "Saved";
                        setTimeout(() => statusLabel.textContent = "", 2000);
                    } else {
                        statusLabel.textContent = "Error saving";
                        console.error(data.error);
                    }
                } catch(e) { console.error(e); statusLabel.textContent = "Connection error"; }
            }, 1000);
        });

        // GLOBAL SEARCH
        const searchInput = document.getElementById('globalSearch');
        const resultsBox = document.getElementById('searchResults');
        searchInput.addEventListener('input', async (e) => {
            const q = e.target.value;
            if(q.length < 2) { resultsBox.style.display='none'; return; }
            const res = await fetch(`api_patients.php?search=${q}`);
            const data = await res.json();
            resultsBox.innerHTML = '';
            if(data.patients.length > 0) {
                resultsBox.style.display = 'block';
                data.patients.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerHTML = `<div class="fw-bold">${p.first_name} ${p.surnames}</div><div class="small text-muted">${p.patient_id}</div>`;
                    div.onclick = () => window.location.href = `api_admin_patient_details.php?id=${p.patient_id}`; 
                    resultsBox.appendChild(div);
                });
            } else resultsBox.style.display='none';
        });

        // CALENDAR
        document.addEventListener('DOMContentLoaded', function() {
            var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'dayGridMonth', 
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                events: 'api_or_calendar.php', 
                height: 650,
                eventClick: function(info) {
                    let cleanTitle = info.event.title.replace(/^\d{2}:\d{2} /, ''); 
                    document.getElementById('calProcName').textContent = cleanTitle;
                    document.getElementById('calPatName').textContent = info.event.extendedProps.patient; 
                    document.getElementById('btnGoEpisode').href = 'clinical_episode.php?id=' + info.event.extendedProps.episode_id;
                    document.getElementById('btnReqChange').onclick = function() {
                        bootstrap.Modal.getInstance(document.getElementById('eventOptionsModal')).hide();
                        openRequestModal('Reschedule', `Change: ${cleanTitle}`, info.event.extendedProps.patient_id, info.event.id);
                    };
                    new bootstrap.Modal(document.getElementById('eventOptionsModal')).show();
                },
                dateClick: function(info) { openRequestModal('New Surgery Request'); }
            });
            document.querySelector('button[data-bs-target="#calendar-view"]').addEventListener('shown.bs.tab', function () { calendar.render(); });
            calendar.render();
        });

        // START SURGERY LOGIC
        let currentStartId = 0;
        const preOpModal = new bootstrap.Modal(document.getElementById('preOpModal'));
        
        function confirmStart(id, patName, procName, allergies) {
            currentStartId = id;
            document.getElementById('poPatient').textContent = patName;
            document.getElementById('poProcedure').textContent = procName;
            
            const abox = document.getElementById('poAllergyBox');
            if(allergies) {
                abox.classList.remove('d-none');
                document.getElementById('poAllergyText').textContent = allergies;
            } else {
                abox.classList.add('d-none');
            }
            
            document.getElementById('chkIdentity').checked = false;
            document.getElementById('chkSite').checked = false;
            document.getElementById('chkConsent').checked = false;
            
            preOpModal.show();
        }

        async function finalizeStart() {
            if(!document.getElementById('chkIdentity').checked || !document.getElementById('chkSite').checked || !document.getElementById('chkConsent').checked) {
                alert("Safety Check Failed: You must confirm all items.");
                return;
            }
            
            // MODIFICADO: Enviamos 'checklist_verified: true' en el payload
            await sendData('api_surgery_update.php', { 
                action: 'start_surgery', 
                sp_id: currentStartId,
                checklist_verified: true // <--- Nuevo parámetro
            }, () => window.location.href = `surgery_monitor.php?id=${currentStartId}`);
        }

        // REQUESTS Logic
        const reqModal = new bootstrap.Modal(document.getElementById('requestModal'));
        const pInput = document.getElementById('reqPatientName');
        const pResults = document.getElementById('patientSearchResults');
        const pIdHidden = document.getElementById('reqPatientId');

        
        pInput.addEventListener('input', async function() {
            const q = this.value;
            if (q.length < 2) { pResults.style.display = 'none'; return; }
            try {
                const res = await fetch(`api_patients.php?search=${q}`);
                const data = await res.json();
                pResults.innerHTML = '';
                if (data.patients && data.patients.length > 0) {
                    pResults.style.display = 'block';
                    data.patients.forEach(pat => {
                        const item = document.createElement('button');
                        item.type = 'button'; 
                        item.className = 'list-group-item list-group-item-action text-start';
                        item.innerHTML = `<strong>${pat.first_name} ${pat.surnames}</strong> <small class='text-muted'>(${pat.patient_id})</small>`;
                        item.onclick = () => {
                            pInput.value = `${pat.first_name} ${pat.surnames}`; 
                            pIdHidden.value = pat.patient_id; 
                            pResults.style.display = 'none';
                        };
                        pResults.appendChild(item);
                    });
                } else { pResults.style.display = 'none'; }
            } catch(e) { console.error(e); }
        });

        document.addEventListener('click', (e) => {
            if (!pInput.contains(e.target) && !pResults.contains(e.target)) {
                pResults.style.display = 'none';
            }
        });

        // --- INICIO DE LA NUEVA LÓGICA DE SOLICITUDES (Paso 2 C) ---

        // 1. Función para mostrar/ocultar el desplegable de procedimientos
        // Coloca esto justo antes de 'window.openRequestModal'
        function toggleProcSelect(type) {
            const div = document.getElementById('divReqProc'); 
            // Solo intentamos ocultar/mostrar si el elemento existe en el HTML (Paso 2B)
            if (div) {
                if (type === 'New Surgery Request') {
                    div.style.display = 'block';
                } else {
                    div.style.display = 'none';
                }
            }
        }

        // 2. Listener para detectar cambios manuales en el desplegable "Type"
        // Esto hace que si el usuario cambia de "Reschedule" a "New Request", aparezca el campo.
        const typeSelectEl = document.getElementById('reqType');
        if(typeSelectEl) {
            typeSelectEl.addEventListener('change', function() {
                toggleProcSelect(this.value);
            });
        }

        // 3. Función actualizada para abrir el Modal
        // REEMPLAZA tu antigua función 'window.openRequestModal' por esta:
        window.openRequestModal = function(type, details='', pid='', surgId='') { 
            document.getElementById('requestForm').reset();
            
            const typeInput = document.getElementById('reqType');
            typeInput.value = type; 
            
            // Llamamos a la función para configurar la visibilidad inicial
            toggleProcSelect(type);

            document.getElementById('reqSurgeryId').value = surgId;
            
            // Lógica de paciente (se mantiene igual)
            if (pid) {
                pIdHidden.value = pid;
                pInput.value = "Patient ID: " + pid; 
                pInput.disabled = true; 
            } else {
                pIdHidden.value = '';
                pInput.value = '';
                pInput.disabled = false;
            }
            
            if(details) document.getElementById('reqDetails').value = details; 
            
            reqModal.show(); 
        };

        // 4. Envío del Formulario (Submit) Actualizado
        // REEMPLAZA tu antiguo 'document.getElementById('requestForm').addEventListener...' por este:
        document.getElementById('requestForm').addEventListener('submit', async (e) => { 
            e.preventDefault(); 
            
            const type = document.getElementById('reqType').value;
            // Intentamos obtener el valor del procedimiento, si existe
            const procSelect = document.getElementById('reqProcedure');
            const procId = procSelect ? procSelect.value : null;
            
            // VALIDACIÓN: Si es "New Surgery Request", es OBLIGATORIO seleccionar procedimiento
            if (type === 'New Surgery Request' && (!procId || procId === "")) {
                alert("⚠️ Please select a procedure from the list.");
                return; // Detenemos el envío aquí
            }

            const payload = { 
                type: type, 
                details: document.getElementById('reqDetails').value, 
                surgery_id: document.getElementById('reqSurgeryId').value,
                patient_id: document.getElementById('reqPatientId').value,
                procedure_id: procId // <--- ENVIAMOS EL NUEVO DATO
            }; 
            
            await sendData('api_send_request.php', payload, () => { 
                alert("Request Sent Successfully"); 
                reqModal.hide(); 
                location.reload(); 
            }); 
        });
        
        // --- FIN DE LA NUEVA LÓGICA ---

        // HISTORY MODAL
        const histModal = new bootstrap.Modal(document.getElementById('historyModal'));
        window.viewHistory = async (pid, name) => {
            document.getElementById('histTitle').textContent = name;
            document.getElementById('histSubtitle').textContent = "ID: " + pid;
            document.getElementById('histTableBody').innerHTML = '<tr><td class="p-5 text-center text-muted"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><br>Fetching full profile...</td></tr>';
            document.getElementById('patientInfoSection').innerHTML = '';
            document.getElementById('allergiesSection').innerHTML = '';
            histModal.show();

            try {
                const res = await fetch(`api_patient_history.php?pid=${pid}`);
                const data = await res.json();
                if (data.patient) {
                    const p = data.patient;
                    const dob = new Date(p.date_of_birth);
                    const age = new Date().getFullYear() - dob.getFullYear();
                    document.getElementById('patientInfoSection').innerHTML = `
                        <div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Date of Birth</div><div class="fw-bold">${dob.toLocaleDateString()} (${age} yrs)</div></div>
                        <div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Gender</div><div class="fw-bold">${p.gender}</div></div>
                        <div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Phone</div><div>${p.phone || '-'}</div></div>
                        <div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Email</div><div class="text-truncate">${p.email || '-'}</div></div>`;
                }
                const allSection = document.getElementById('allergiesSection');
                if (data.allergies && data.allergies.length > 0) {
                    let badges = data.allergies.map(a => {
                        let color = a.severity === 'Life-threatening' ? 'bg-danger' : (a.severity === 'Severe' ? 'bg-warning text-dark' : 'bg-secondary');
                        return `<span class="badge ${color} me-1 mb-1">${a.allergen_name} (${a.severity})</span>`;
                    }).join('');
                    allSection.innerHTML = `<div class="small text-uppercase text-danger fw-bold mb-1" style="font-size:0.7rem">Allergies</div>${badges}`;
                } else { allSection.innerHTML = `<div class="small text-uppercase text-success fw-bold mb-0" style="font-size:0.7rem"><i class="bi bi-check-circle me-1"></i> No Known Allergies</div>`; }

                const tbody = document.getElementById('histTableBody');
                tbody.innerHTML = '';
                if (data.history && data.history.length > 0) {
                    data.history.forEach(s => {
                        let badgeClass = 'bg-secondary';
                        if(s.status === 'Completed') badgeClass = 'bg-success';
                        if(s.status === 'Scheduled') badgeClass = 'bg-primary';
                        if(s.status === 'In Progress') badgeClass = 'bg-danger';
                        tbody.innerHTML += `<tr><td class="ps-4 py-3"><div class="fw-bold text-dark">${s.procedure_name}</div><small class="text-muted">${new Date(s.date_time).toLocaleDateString()}</small></td><td><span class="badge ${badgeClass} text-white" style="font-size:0.7rem">${s.status}</span></td><td class="text-end pe-4"><a href="clinical_episode.php?id=${s.episode_id}" class="btn btn-sm btn-light border"><i class="bi bi-folder2-open"></i></a></td></tr>`;
                    });
                } else { tbody.innerHTML = '<tr><td class="p-4 text-center text-muted">No surgical history records found.</td></tr>'; }
            } catch (e) { console.error(e); document.getElementById('histTableBody').innerHTML = '<tr><td class="p-4 text-center text-danger">Error loading patient record.</td></tr>'; }
        };

        async function sendData(url, data, cb) { try { const r = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) }); const d = await r.json(); if(d.success) cb(); else alert(d.error); } catch(e) { console.error(e); } }
    </script>
</body>
</html>