<?php
// groupd/views/admin/admin_requests.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) die("Access Denied.");

$pdo = getDBConnection();

$sql = "
    SELECT r.request_id, r.request_type, r.details, r.created_at, r.status,
        r.patient_id as req_pid, r.original_surgery_id,
        u.username, s.surnames as surgeon_surname, p.first_name, p.surnames
    FROM SCHEDULE_REQUESTS r
    JOIN USERS u ON r.user_id = u.user_id
    LEFT JOIN SURGEONS s ON u.user_id = s.user_id
    LEFT JOIN PATIENT p ON r.patient_id = p.patient_id
    ORDER BY FIELD(r.status, 'Pending', 'In Review', 'Resolved', 'Rejected'), r.created_at DESC
    LIMIT 50
";
$requests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requests Inbox | Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏥</text></svg>?v=999">
    
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
        .req-card { background: white; border: none; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s; border-left: 5px solid transparent; }
        .req-card:hover { transform: translateY(-3px); }
        .type-new { border-left-color: #198754; } .type-cancel { border-left-color: #dc3545; } .type-change { border-left-color: #ffc107; } .type-default { border-left-color: #6c757d; }
        .badge-status { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .bg-Pending { background: #fff3cd; color: #856404; } .bg-Resolved { background: #d1e7dd; color: #0f5132; } .bg-Rejected { background: #f8d7da; color: #842029; }
        .btn-action { border-radius: 8px; font-weight: 600; padding: 8px 16px; font-size: 0.85rem; }
        .manual-action-hint { font-size: 0.8rem; background: #fff8e1; color: #b7791f; padding: 5px 10px; border-radius: 6px; border: 1px solid #fceebf; display: inline-block; margin-right: 10px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="mb-4 px-2 text-white fw-bold fs-5"><i class="bi bi-hospital-fill me-2"></i> Les Corts Admin</div>
        <a href="admin_dashboard.php"><span class="icon-box"><i class="bi bi-grid-fill"></i></span> Dashboard</a>
        <a href="admin_scheduler_advanced.php"><span class="icon-box"><i class="bi bi-calendar-check-fill"></i></span> Smart Scheduler</a>
        <a href="#" class="active"><span class="icon-box"><i class="bi bi-envelope-fill"></i></span> Requests</a>
        <a href="admin_patients.php"><span class="icon-box"><i class="bi bi-people-fill"></i></span> Patients</a>
        <div class="mt-auto"><a href="../../controllers/logout.php" class="text-danger ps-2 text-decoration-none fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></div>
    </div>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h3 class="fw-bold mb-1">Requests Inbox</h3><p class="text-muted mb-0">Manage scheduling requests.</p></div>
            <div class="bg-white px-3 py-2 rounded-pill shadow-sm text-muted small fw-bold">Pending: <span class="text-primary ms-1"><?php echo count(array_filter($requests, fn($r) => $r['status'] == 'Pending')); ?></span></div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="text-center py-5 text-muted opacity-50"><i class="bi bi-inbox fs-1"></i><p class="mt-2">No requests found.</p></div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($requests as $r): ?>
                    <?php 
                        $typeClass = 'type-default';
                        if(strpos($r['request_type'], 'New') !== false) $typeClass = 'type-new';
                        elseif(strpos($r['request_type'], 'Cancellation') !== false) $typeClass = 'type-cancel';
                        elseif(strpos($r['request_type'], 'Reschedule') !== false) $typeClass = 'type-change';
                        
                        $isPending = $r['status'] === 'Pending';
                        $surgeonName = $r['surgeon_surname'] ?? $r['username'];
                        $patientName = ($r['first_name'] ?? 'Unknown') . ' ' . ($r['surnames'] ?? 'Patient');
                        
                        // FIX: URL correcta sin target_blank
                        $calendarLink = "admin_schedule.php";
                        if($r['req_pid']) {
                            $calendarLink .= "?pid=" . urlencode($r['req_pid']) . "&pname=" . urlencode($patientName);
                            // Añadimos req_id a la URL
                            if($r['original_surgery_id']) {
                                $calendarLink .= "&cancel_id=" . $r['original_surgery_id'] . "&req_id=" . $r['request_id'];
                            }
                        }
                    ?>
                    <div class="col-12 col-xl-6">
                        <div class="req-card <?php echo $typeClass; ?>" id="req-<?php echo $r['request_id']; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($r['request_type']); ?></h5>
                                        <span class="badge-status bg-<?php echo $r['status']; ?>"><?php echo $r['status']; ?></span>
                                    </div>
                                    <div class="text-muted small">Dr. <?php echo htmlspecialchars($surgeonName); ?> • <?php echo date('d M, H:i', strtotime($r['created_at'])); ?></div>
                                </div>
                            </div>

                            <div class="bg-light p-3 rounded-3 mb-3">
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="bg-white p-2 rounded-circle shadow-sm text-secondary"><i class="bi bi-person-fill fs-5"></i></div>
                                    <div><div class="fw-bold text-dark"><?php echo htmlspecialchars($patientName); ?></div><div class="small text-muted">ID: <?php echo htmlspecialchars($r['req_pid'] ?? 'N/A'); ?></div></div>
                                </div>
                                <hr class="my-2 opacity-10">
                                <p class="mb-0 small text-secondary fst-italic">"<?php echo htmlspecialchars($r['details']); ?>"</p>
                            </div>

                            <?php if($isPending): ?>
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <?php if(strpos($r['request_type'], 'New') !== false): ?>
                                        <button class="btn btn-success btn-action shadow-sm" onclick="handleRequest(<?php echo $r['request_id']; ?>, 'resolve')"><i class="bi bi-check-lg me-1"></i> Approve & Waitlist</button>
                                    <?php elseif(strpos($r['request_type'], 'Cancellation') !== false): ?>
                                        <button class="btn btn-danger btn-action shadow-sm" onclick="handleRequest(<?php echo $r['request_id']; ?>, 'resolve')"><i class="bi bi-trash me-1"></i> Confirm Cancel</button>
                                    <?php else: ?>
                                        <span class="manual-action-hint">Manual Action</span>
                                        <a href="<?php echo $calendarLink; ?>" class="btn btn-dark btn-action"><i class="bi bi-calendar-event me-1"></i> Go to Schedule</a>
                                        <button class="btn btn-secondary btn-action" onclick="handleRequest(<?php echo $r['request_id']; ?>, 'resolve')"><i class="bi bi-archive me-1"></i> Archive</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function handleRequest(reqId, action) {
            let msg = action === 'reject' ? "Reject?" : "Confirm action?";
            if(!confirm(msg)) return;
            try {
                const res = await fetch('api_handle_request.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ request_id: reqId, action: action })
                });
                if((await res.json()).success) {
                    document.getElementById('req-' + reqId).style.opacity = "0";
                    setTimeout(() => location.reload(), 300);
                }
            } catch(e) { alert("System Error"); }
        }
    </script>
</body>
</html>