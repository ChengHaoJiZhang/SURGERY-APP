<?php
// groupd/views/nurse/clinical_episode.php
// DESIGN: Clean & Depth (Nurse Theme)
// UPDATED: Removed Lab Results, Imaging, and Vitals button

session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../../config/config.php';

// 1. Auth Check
$controller = new LoginController();
$controller->checkAuth();
// Allow Nurse, Admin, Anesthesiologist
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false && strpos(strtolower($_SESSION['role_name']), 'admin') === false && strpos(strtolower($_SESSION['role_name']), 'anest') === false) {
    die("Access Denied.");
}

$pdo = getDBConnection();

// 2. Validate Episode ID
if (!isset($_GET['id']) || empty($_GET['id'])) die("Error: No episode ID provided.");
$episodeId = $_GET['id'];

// 3. Data Queries

// A. Episode & Patient Data
$episode = $pdo->prepare("SELECT ep.*, p.first_name, p.surnames, p.date_of_birth, p.gender, p.patient_id as p_code 
                          FROM EPISODE ep JOIN PATIENT p ON ep.patient_id = p.patient_id WHERE ep.episode_id = ?");
$episode->execute([$episodeId]);
$epData = $episode->fetch();

if (!$epData) die("Episode not found.");

// B. Surgeries
$surgeries = $pdo->prepare("SELECT sp.*, p.procedure_name, op.room_number, sp.status
                            FROM SURGICAL_PROCEDURE sp 
                            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id 
                            LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id 
                            WHERE sp.episode_id = ? ORDER BY sp.date_time DESC");
$surgeries->execute([$episodeId]);
$surgList = $surgeries->fetchAll();

// C. Medication
$meds = $pdo->prepare("SELECT m.comercial_name, ep.dosage, ep.prescribed_date 
                       FROM EPISODE_PRESCRIPTIONS ep 
                       JOIN MEDICATIONS m ON ep.drug_ID = m.drug_ID 
                       WHERE ep.episode_id = ? ORDER BY ep.prescribed_date DESC");
$meds->execute([$episodeId]);
$medList = $meds->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Episode #<?php echo $episodeId; ?> | Nurse View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root { 
            --bg-body: #eaeff2; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #0ea5e9; 
            --primary-dark: #0284c7;
            --surface: #ffffff;
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
        
        /* PATIENT BANNER */
        .patient-banner { background: var(--surface); border-radius: 16px; padding: 24px; box-shadow: var(--shadow-soft); display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; border: 1px solid #f1f5f9; }
        .patient-name { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; }
        .meta-pill { background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-right: 8px; }
        
        /* CARDS */
        .card-clean { background: var(--surface); border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 24px; }
        .card-header-clean { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 10px; background: #fff; }
        
        /* LISTS */
        .list-item-clean { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
        .list-item-clean:hover { background-color: #f8fafc; }
        .list-item-clean:last-child { border-bottom: none; }

        /* BUTTONS */
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 10px; padding: 8px 16px; font-weight: 600; transition: 0.2s; text-decoration: none; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
        
        /* BADGES */
        .badge-status { font-size: 0.75rem; padding: 5px 12px; border-radius: 50px; text-transform: uppercase; font-weight: 700; }
        .bg-active { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        
        .timeline-date { font-family: 'Monaco', monospace; font-size: 0.8rem; color: var(--text-muted); background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>

    <header class="top-header">
        <div class="d-flex align-items-center gap-4">
            <a href="nurse_dashboard.php" class="btn-glass border-0 text-muted ps-0">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <div class="border-start ps-4">
                <h1 class="h6 mb-0 fw-bold text-uppercase ls-1 text-primary">Clinical Episode View</h1>
            </div>
        </div>
        <div class="text-muted small fw-bold">ID: #<?php echo $episodeId; ?></div>
    </header>

    <div class="container py-5" style="max-width: 1200px;">
        
        <div class="patient-banner">
            <div>
                <h2 class="patient-name"><?php echo htmlspecialchars($epData['first_name'] . ' ' . $epData['surnames']); ?></h2>
                <div class="mt-2">
                    <span class="meta-pill"><i class="bi bi-person-badge me-1"></i> <?php echo $epData['p_code']; ?></span>
                    <span class="meta-pill"><i class="bi bi-calendar me-1"></i> <?php echo date('d M Y', strtotime($epData['date_of_birth'])); ?></span>
                    <span class="meta-pill"><i class="bi bi-gender-ambiguous me-1"></i> <?php echo $epData['gender']; ?></span>
                </div>
            </div>
            <div class="text-end">
                <span class="badge-status bg-active">Active Admission</span>
                <div class="small text-muted mt-1 fw-bold">Since: <?php echo date('d M Y', strtotime($epData['date_time_start'])); ?></div>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-lg-8">
                
                <div class="card-clean">
                    <div class="card-header-clean">
                        <i class="bi bi-bandaid text-primary"></i> Surgical Procedures
                    </div>
                    <div>
                        <?php if (empty($surgList)): ?>
                            <div class="p-5 text-center text-muted fst-italic">No surgeries recorded for this episode.</div>
                        <?php else: ?>
                            <?php foreach ($surgList as $s): 
                                $isLive = $s['status'] === 'In Progress';
                                $isScheduled = $s['status'] === 'Scheduled';
                            ?>
                                <div class="list-item-clean">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($s['procedure_name']); ?></h6>
                                            <span class="timeline-date"><i class="bi bi-clock me-1"></i> <?php echo date('d M H:i', strtotime($s['date_time'])); ?></span>
                                            <span class="small text-muted ms-2"><i class="bi bi-geo-alt me-1"></i> Room <?php echo htmlspecialchars($s['room_number'] ?? 'TBD'); ?></span>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?php echo $s['status']; ?></span>
                                    </div>
                                    
                                    <div class="mt-3 d-flex gap-2">
                                        <?php if ($isLive || $isScheduled): ?>
                                            <a href="pre_op_check.php?spid=<?php echo $s['surgical_procedure_id']; ?>" class="btn-glass btn-sm text-primary">
                                                <i class="bi bi-shield-check"></i> Pre-Op
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($isLive): ?>
                                            <a href="surgery_live.php?id=<?php echo $s['surgical_procedure_id']; ?>" class="btn-glass btn-sm text-danger border-danger">
                                                <i class="bi bi-activity"></i> Monitor
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="nursery_report.php?spid=<?php echo $s['surgical_procedure_id']; ?>" class="btn-glass btn-sm">
                                            <i class="bi bi-file-text"></i> Report
                                        </a>
                                        
                                        </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-clean mt-4">
                    <div class="card-header-clean">
                        <i class="bi bi-info-circle text-info"></i> Admission Reason
                    </div>
                    <div class="p-4 text-secondary fst-italic">
                        "<?php echo nl2br(htmlspecialchars($epData['reason'])); ?>"
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                
                <div class="card-clean mb-4">
                    <div class="card-header-clean">
                        <i class="bi bi-capsule text-warning"></i> Active Medication
                    </div>
                    <div>
                        <?php if (empty($medList)): ?>
                            <div class="p-4 text-center text-muted small">No active prescriptions.</div>
                        <?php else: ?>
                            <?php foreach ($medList as $m): ?>
                                <div class="list-item-clean d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['comercial_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($m['dosage']); ?></div>
                                    </div>
                                    <div class="timeline-date"><?php echo date('d/m', strtotime($m['prescribed_date'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>