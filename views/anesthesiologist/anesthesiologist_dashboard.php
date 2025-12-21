<?php
// groupd/views/anesthesiologist/anesthesiologist_dashboard.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../../config/config.php';

// 1. Seguridad
$controller = new LoginController();
$controller->checkAuth();

$role = strtolower($_SESSION['role_name'] ?? '');
if (strpos($role, 'anest') === false && strpos($role, 'admin') === false) { 
    die("Access Denied."); 
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// 2. Obtener Datos del Anestesiólogo
$stmt = $pdo->prepare("SELECT anesthesiologist_id, first_name, surnames FROM ANESTHESIOLOGISTS WHERE user_id = ?");
$stmt->execute([$userId]);
$anes = $stmt->fetch();
$anesId = $anes['anesthesiologist_id'] ?? 0;

// 3. KPI: Casos Completados
$kpiCompleted = 0;
if ($anesId > 0) {
    $sqlKpi = "SELECT COUNT(*) FROM SURGICAL_PROCEDURE sp 
               JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id 
               WHERE spa.anesthesiologist_id = $anesId AND sp.status = 'Completed' 
               AND MONTH(sp.date_time) = MONTH(CURDATE())";
    $kpiCompleted = $pdo->query($sqlKpi)->fetchColumn();
}

// 4. CONSULTA PRINCIPAL
$allSchedule = [];
if ($anesId > 0) {
    $sqlSchedule = "SELECT sp.surgical_procedure_id, ep.episode_id, p.procedure_name, sp.date_time, 
                       pat.first_name, pat.surnames, pat.patient_id, op.room_number, sp.status, p.default_duration,
                       (SELECT GROUP_CONCAT(a.allergen_name SEPARATOR ', ') FROM PATIENT_ALLERGIES pa JOIN ALLERGIES a ON pa.allergen_ID = a.allergen_ID WHERE pa.patient_id = pat.patient_id AND pa.severity IN ('Severe', 'Life-threatening')) as critical_allergies
                FROM SURGICAL_PROCEDURE sp
                JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id
                JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
                WHERE spa.anesthesiologist_id = :aid AND sp.status NOT IN ('Cancelled', 'Completed')
                ORDER BY sp.date_time ASC";
    $stmtSchedule = $pdo->prepare($sqlSchedule);
    $stmtSchedule->execute([':aid' => $anesId]);
    $allSchedule = $stmtSchedule->fetchAll();
}

// Clasificación
$live = []; $today = []; $upcoming = [];
$todayDate = date('Y-m-d');

foreach($allSchedule as $s) {
    $sDate = date('Y-m-d', strtotime($s['date_time']));
    if($s['status'] == 'In Progress') { $live[] = $s; }
    elseif($sDate == $todayDate) { $today[] = $s; }
    elseif($sDate > $todayDate) { $upcoming[] = $s; }
}

// 5. HISTORIAL / REPORTES
$reportsPending = []; $historyList = [];
if ($anesId > 0) {
    $sqlHist = "SELECT sp.surgical_procedure_id, p.procedure_name, sp.date_time, sp.anesthesiology_report, sp.status, pat.first_name, pat.surnames
                FROM SURGICAL_PROCEDURE sp
                JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id
                JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                WHERE spa.anesthesiologist_id = :aid AND (sp.status = 'Completed' OR sp.date_time < NOW())
                ORDER BY sp.date_time DESC LIMIT 50";
    $stmtHist = $pdo->prepare($sqlHist); 
    $stmtHist->execute([':aid' => $anesId]);
    $historyList = $stmtHist->fetchAll();
    
    $reportsPending = array_filter($historyList, fn($h) => empty($h['anesthesiology_report']));
}

// 6. MIS PACIENTES
$myPatients = [];
if ($anesId > 0) {
    $sqlPat = "SELECT pat.patient_id, pat.first_name, pat.surnames, pat.date_of_birth, pat.gender, MAX(sp.date_time) as last_surgery_date 
               FROM PATIENT pat 
               JOIN EPISODE ep ON pat.patient_id = ep.patient_id 
               JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id 
               JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id 
               WHERE spa.anesthesiologist_id = :aid 
               GROUP BY pat.patient_id
               ORDER BY last_surgery_date DESC";
    $stmtPat = $pdo->prepare($sqlPat);
    $stmtPat->execute([':aid' => $anesId]);
    $myPatients = $stmtPat->fetchAll();
}

// 7. CALENDARIO
$calendarEvents = [];
if ($anesId > 0) {
    $sqlCal = "SELECT sp.surgical_procedure_id as id, p.procedure_name as title, sp.date_time as start, sp.status, sp.episode_id, CONCAT(pat.first_name,' ',pat.surnames) as pname
               FROM SURGICAL_PROCEDURE sp
               JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id
               JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
               JOIN EPISODE ep ON sp.episode_id = ep.episode_id
               JOIN PATIENT pat ON ep.patient_id = pat.patient_id
               WHERE spa.anesthesiologist_id = :aid AND sp.status != 'Cancelled'";
    $stmtCal = $pdo->prepare($sqlCal); $stmtCal->execute([':aid' => $anesId]);
    foreach($stmtCal->fetchAll() as $row) {
        $color = ($row['status']=='Completed') ? '#10b981' : (($row['status']=='In Progress')?'#ef4444':'#3b82f6');
        $calendarEvents[] = [
            'id' => $row['id'], 'title' => $row['title'], 'start' => $row['start'], 'color' => $color,
            'extendedProps' => ['episode_id' => $row['episode_id'], 'patient' => $row['pname']]
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Anesthesiology Portal | Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <style>
        /* ESTILOS PREMIUM (DEEP & HARMONIOUS) - ORIGINALES */
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
        .brand-icon { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }

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

        .strip-live { background: linear-gradient(to bottom, var(--danger), #ef4444); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }
        .strip-today { background: linear-gradient(to bottom, var(--warning), #f59e0b); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }
        .strip-pending { background: linear-gradient(to bottom, #3b82f6, #2563eb); width: 6px; position: absolute; left: 0; top: 0; bottom: 0; }

        .time-box { background: #f8fafc; padding: 8px 12px; border-radius: 8px; font-family: 'Monaco', monospace; font-weight: 700; color: var(--text-dark); font-size: 0.9rem; border: 1px solid #e2e8f0; }

        .btn-premium { background: var(--text-dark); color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(30, 41, 59, 0.2); transition: 0.2s; }
        .btn-premium:hover { background: #0f172a; transform: translateY(-1px); color: white; box-shadow: 0 6px 16px rgba(30, 41, 59, 0.3); }
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 10px; padding: 8px 16px; font-weight: 600; transition: 0.2s; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; }

        .alert-modern { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-top: 12px; }
        
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
            <div class="brand-icon"><i class="bi bi-lungs"></i></div>
            <span class="d-none d-md-inline">AnesPortal</span>
        </div>
        
        <ul class="nav nav-pills d-none d-lg-flex flex-nowrap" id="mainTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview">Dashboard</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#calendar-view" onclick="renderCal()">Calendar</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports">Reports</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#patients">My Patients</button></li>
        </ul>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <button class="btn btn-glass btn-sm position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRounds">
                <i class="bi bi-clipboard-pulse me-2 text-primary"></i> Rounds
                <?php if(count($live)>0): ?>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                <?php endif; ?>
            </button>
            
            <div class="border-start ps-3 ms-2">
                <a href="../../controllers/logout.php" class="btn btn-glass btn-sm text-danger border-0"><i class="bi bi-power"></i></a>
            </div>
        </div>
    </header>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRounds" style="width: 380px;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold text-dark">Active Procedures</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 bg-light">
            <?php if(empty($live) && empty($today)): ?>
                <div class="p-5 text-center text-muted">No active cases.</div>
            <?php else: ?>
                <?php foreach($live as $p): ?>
                <div class="round-item bg-white border-start border-4 border-danger" onclick="window.location.href='live_cockpit.php?id=<?php echo $p['surgical_procedure_id']; ?>'">
                    <div class="d-flex align-items-center gap-3">
                        <div class="initials bg-danger text-white"><i class="bi bi-broadcast"></i></div>
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></div>
                            <small class="text-danger fw-bold">IN PROGRESS</small>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </div>
                <?php endforeach; ?>
                <?php foreach($today as $p): ?>
                <div class="round-item bg-white" onclick="window.location.href='../surgeon/clinical_episode.php?id=<?php echo $p['episode_id']; ?>'">
                    <div class="d-flex align-items-center gap-3">
                        <div class="initials"><?php echo substr($p['first_name'],0,1).substr($p['surnames'],0,1); ?></div>
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($p['procedure_name']); ?></small>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        <div class="tab-content">
            
            <div class="tab-pane fade show active" id="overview">
                
                <div class="kpi-container">
                    <div class="kpi-card" style="cursor: pointer;" onclick="document.querySelector('[data-bs-target=\'#reports\']').click()">
                        <div class="kpi-value text-success"><?php echo $kpiCompleted; ?></div>
                        <div class="kpi-title">Completed (Mo)</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value text-warning"><?php echo count($reportsPending); ?></div>
                        <div class="kpi-title">Pending Reports</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value text-danger"><?php echo count($live); ?></div>
                        <div class="kpi-title">Live Now</div>
                    </div>
                    <div class="kpi-card" style="background: #fffbeb; border-color: #fef3c7;">
                        <small class="fw-bold text-warning-emphasis mb-1">QUICK NOTE</small>
                        <p class="small text-muted mb-0">Review allergies before induction.</p>
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
                                    <span class="time-box text-danger border-danger"><?php echo date('H:i', strtotime($s['date_time'])); ?></span>
                                </div>
                                <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($s['procedure_name']); ?></h3>
                                <p class="text-muted fs-5 mb-4"><?php echo htmlspecialchars($s['first_name'].' '.$s['surnames']); ?></p>
                                <a href="live_cockpit.php?id=<?php echo $s['surgical_procedure_id']; ?>" class="btn btn-danger w-100 py-3 fw-bold shadow-sm">ENTER COCKPIT</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="section-title text-warning"><i class="bi bi-calendar-day me-2"></i> Today's Schedule</div>
                
                <?php if(empty($today)): ?>
                    <div class="card-depth p-5 text-center text-muted fst-italic">No more cases today.</div>
                <?php else: ?>
                <div class="row g-4 mb-5">
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
                                        <i class="bi bi-shield-exclamation text-danger"></i> 
                                        <span class="ms-2">Allergy Alert</span>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-auto d-flex gap-2 pt-3">
                                    <button onclick="confirmAnesthesiaStart(<?php echo $s['surgical_procedure_id']; ?>, '<?php echo htmlspecialchars($s['first_name'].' '.$s['surnames'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['procedure_name'], ENT_QUOTES); ?>')" 
                                            class="btn btn-premium flex-grow-1 bg-warning text-dark border-warning">
                                        <i class="bi bi-play-circle me-2"></i> Start Induction
                                    </button>
                                    <a href="../surgeon/clinical_episode.php?id=<?php echo $s['episode_id']; ?>" class="btn btn-glass px-3"><i class="bi bi-folder2-open"></i></a>
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
                    <?php if(empty($historyList)): ?><div class="col-12 text-center text-muted p-5">No history found.</div><?php endif; ?>
                    <?php foreach($historyList as $h): 
                        $hasReport = !empty($h['anesthesiology_report']);
                        $borderCol = $hasReport ? '#10b981' : '#f59e0b';
                        $btnTxt = $hasReport ? 'View' : 'Write Report';
                        $btnClass = $hasReport ? 'btn-glass text-success' : 'btn-premium bg-warning border-warning text-dark';
                    ?>
                    <div class="col-md-6">
                        <div class="card-depth p-4 d-flex justify-content-between align-items-center" style="border-left: 4px solid <?php echo $borderCol; ?>">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($h['procedure_name']); ?></h6>
                                <small class="text-muted"><?php echo date('d M', strtotime($h['date_time'])); ?> • <?php echo htmlspecialchars($h['first_name']); ?></small>
                            </div>
                            <a href="anesthesia_report.php?id=<?php echo $h['surgical_procedure_id']; ?>" class="btn btn-sm <?php echo $btnClass; ?>"><?php echo $btnTxt; ?></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="patients">
                <div class="card-depth p-0 overflow-hidden">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light text-secondary small fw-bold"><tr><th class="ps-4 py-3">Patient</th><th>Age/Gender</th><th>Last Visit</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach($myPatients as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark py-3"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></td>
                                <td class="text-muted"><?php echo $p['gender']; ?> / <?php echo (date('Y') - date('Y', strtotime($p['date_of_birth']))); ?> yrs</td>
                                <td class="text-muted"><?php echo date('d M Y', strtotime($p['last_surgery_date'])); ?></td>
                                <td class="text-end pe-4"><button onclick="viewHistory('<?php echo $p['patient_id']; ?>','<?php echo htmlspecialchars($p['first_name']); ?>')" class="btn btn-sm btn-glass text-primary">Record</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="anesStartModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>Surgical Safety Check (WHO)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <strong class="d-block text-dark mb-1" id="modalPatient">Patient Name</strong>
                        <span class="text-muted" id="modalProcedure">Procedure Name</span>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold small mb-3">Time-Out Verification</h6>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="chkIdentity"><label class="form-check-label" for="chkIdentity">Patient Identity Confirmed</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="chkSite"><label class="form-check-label" for="chkSite">Surgical Site Marked</label></div>
                    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="chkConsent"><label class="form-check-label" for="chkConsent">Consent Signed & Available</label></div>

                    <div class="d-grid">
                        <button type="button" onclick="finalizeAnesthesiaStart()" class="btn btn-success fw-bold py-2 shadow-sm">
                            CONFIRM & GO LIVE
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="eventOptionsModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-body text-center p-4"><h5 id="calProcName" class="fw-bold mb-1"></h5><p id="calPatName" class="text-muted small mb-4"></p><div class="d-grid gap-2"><a id="btnGoEpisode" href="#" class="btn btn-premium btn-sm">Open File</a><a id="btnGoCockpit" href="#" class="btn btn-glass btn-sm text-danger">Go to Cockpit</a></div></div></div></div></div>

    <div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden"><div class="modal-header bg-primary text-white border-0 pb-4"><div><h5 class="modal-title fw-bold mb-1" id="histTitle">Record</h5><p class="mb-0 small opacity-75" id="histSubtitle">Loading...</p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light p-0"><div id="patientInfoSection" class="bg-white p-4 border-bottom row g-3"></div><div id="allergiesSection" class="px-4 py-3 bg-white border-bottom"></div><div class="p-0"><div class="bg-light px-4 py-2 border-bottom"><h6 class="fw-bold text-muted small mb-0 text-uppercase">Surgical History</h6></div><div id="histContent" class="table-responsive"></div></div></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- LÓGICA DE CHECKLIST ---
    let startModal;
    let currentSpid = 0;

    document.addEventListener('DOMContentLoaded', () => {
         // Inicializar modal al cargar
         const modalEl = document.getElementById('anesStartModal');
         if(modalEl) {
             startModal = new bootstrap.Modal(modalEl);
         }
         
         // Setup calendar tab listener
         const tabEl = document.querySelector('button[data-bs-target="#calendar-view"]');
         if (tabEl) {
             tabEl.addEventListener('shown.bs.tab', event => {
                 if (!calInit) {
                     renderCal(); // First init
                 } else {
                     calendar.render(); // Re-render to fix sizing issues
                 }
             });
         }
    });

    function confirmAnesthesiaStart(spid, patName, procName) {
        currentSpid = spid;
        document.getElementById('modalPatient').textContent = patName;
        document.getElementById('modalProcedure').textContent = procName;
        
        // Resetear checkboxes
        document.getElementById('chkIdentity').checked = false;
        document.getElementById('chkSite').checked = false;
        document.getElementById('chkConsent').checked = false;
        
        if(startModal) startModal.show();
        else alert("Error: Modal not initialized. Reload page.");
    }

    async function finalizeAnesthesiaStart() {
        // Validar Checklist
        if (!document.getElementById('chkIdentity').checked || 
            !document.getElementById('chkSite').checked || 
            !document.getElementById('chkConsent').checked) {
            alert("Safety Check Failed: You must confirm all safety items.");
            return;
        }

        try {
            // Llamada a la API
            const res = await fetch('api_anesthesia.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    action: 'save_timestamp', 
                    spid: currentSpid,
                    type: 'in', 
                    checklist_verified: true 
                })
            });

            // Manejo de respuesta
            const text = await res.text(); // Leemos texto primero por si hay error PHP HTML
            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                console.error("Server Response:", text);
                alert("Server Error: Check console for details.");
                return;
            }
            
            if(data.success) {
                // Éxito: Redirigir
                window.location.href = `live_cockpit.php?id=${currentSpid}`;
            } else {
                alert('Error starting case: ' + data.error);
            }
        } catch(e) {
            console.error(e);
            alert("Network connection error.");
        }
    }

        // --- CALENDARIO ---
        let calInit = false;
        let calendar = null;
        const optModal = new bootstrap.Modal(document.getElementById('eventOptionsModal'));
        
        function renderCal() {
            if(calInit) {
                setTimeout(() => { calendar.render(); }, 1);
                return;
            }
            
            var calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                events: <?php echo json_encode($calendarEvents); ?>,
                height: 650,
                eventClick: function(info) {
                    document.getElementById('calProcName').textContent = info.event.title;
                    document.getElementById('calPatName').textContent = info.event.extendedProps.patient;
                    document.getElementById('btnGoEpisode').href = '../surgeon/clinical_episode.php?id=' + info.event.extendedProps.episode_id;
                    document.getElementById('btnGoCockpit').href = 'live_cockpit.php?id=' + info.event.id;
                    optModal.show();
                }
            });
            calendar.render();
            calInit = true;
        }

        // --- HISTORIAL ---
        const histModal = new bootstrap.Modal(document.getElementById('historyModal'));
        window.viewHistory = async (pid, name) => {
            document.getElementById('histTitle').textContent = name;
            document.getElementById('histSubtitle').textContent = "ID: " + pid;
            document.getElementById('histContent').innerHTML = '<div class="p-5 text-center"><div class="spinner-border text-primary"></div></div>';
            document.getElementById('patientInfoSection').innerHTML = '';
            document.getElementById('allergiesSection').innerHTML = '';
            histModal.show();

            try {
                const res = await fetch(`../surgeon/api_patient_history.php?pid=${pid}`);
                const data = await res.json();
                
                if (data.patient) {
                    const p = data.patient;
                    const dob = new Date(p.date_of_birth);
                    const age = new Date().getFullYear() - dob.getFullYear();
                    document.getElementById('patientInfoSection').innerHTML = `<div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">DOB</div><div class="fw-bold">${dob.toLocaleDateString()} (${age} yrs)</div></div><div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Gender</div><div class="fw-bold">${p.gender}</div></div><div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Phone</div><div>${p.phone||'-'}</div></div><div class="col-md-3"><div class="small text-uppercase text-muted fw-bold" style="font-size:0.7rem">Email</div><div class="text-truncate">${p.email||'-'}</div></div>`;
                }
                const allSec = document.getElementById('allergiesSection');
                if (data.allergies && data.allergies.length > 0) {
                    let badges = data.allergies.map(a => {
                        let c = a.severity.includes('vere')||a.severity.includes('Life') ? 'bg-danger' : 'bg-warning text-dark';
                        return `<span class="badge ${c} me-1">${a.allergen_name} (${a.severity})</span>`;
                    }).join('');
                    allSec.innerHTML = `<div class="small text-uppercase text-danger fw-bold mb-1" style="font-size:0.7rem">Allergies</div>${badges}`;
                } else { allSec.innerHTML = `<div class="small text-uppercase text-success fw-bold mb-0" style="font-size:0.7rem"><i class="bi bi-check-circle me-1"></i> No Known Allergies</div>`; }

                let html = '<table class="table table-hover mb-0"><thead><tr><th class="ps-4">Date</th><th>Procedure</th><th>Status</th><th></th></tr></thead><tbody>';
                if(data.history && data.history.length > 0) {
                    data.history.forEach(h => {
                        let badge = h.status=='Completed'?'bg-success':(h.status=='In Progress'?'bg-danger':'bg-primary');
                        html += `<tr><td class="ps-4">${new Date(h.date_time).toLocaleDateString()}</td><td class="fw-bold">${h.procedure_name}</td><td><span class="badge ${badge} text-white" style="font-size:0.7rem">${h.status}</span></td><td class="text-end pe-4"><a href="../surgeon/clinical_episode.php?id=${h.episode_id}" class="btn btn-sm btn-light border"><i class="bi bi-folder2-open"></i></a></td></tr>`; 
                    });
                } else { html += '<tr><td colspan="4" class="text-center p-4 text-muted">No records found.</td></tr>'; }
                html += '</tbody></table>';
                document.getElementById('histContent').innerHTML = html;
            } catch(e) { console.error(e); }
        }
    </script>
</body>
</html>