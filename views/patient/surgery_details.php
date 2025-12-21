<?php
// File: groupd/views/patient/surgery_details.php
// REDESIGNED: Clean Aesthetic matching Patient Dashboard

session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['logged_in']) || strtolower($_SESSION['role_name']) !== 'patient') {
    header('Location: ../../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$sp_id = $_GET['id'] ?? null;
$error_message = null;

try {
    $pdo = getDBConnection();
    // 1. Get ALL Patient IDs (Fix for duplicate records)
    $stmt = $pdo->prepare("SELECT P.patient_id, P.first_name, P.surnames FROM PATIENT P JOIN USERS U ON P.email=U.email WHERE U.user_id = ?");
    $stmt->execute([$user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $patient_names = $pdo->query("SELECT first_name, surnames FROM PATIENT WHERE patient_id = '{$patient_ids[0]}' ")->fetch(PDO::FETCH_ASSOC);
    $display_name = htmlspecialchars($patient_names['first_name'] . ' ' . $patient_names['surnames']);

    if (empty($patient_ids)) {
        $error_message = "Patient not found.";
    } else {
        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

        // 2. Get Surgery (Check if it belongs to ANY of the patient's IDs)
        $sql = "SELECT SP.*, P.procedure_name, ORR.room_number, W.ward_name, E.episode_id 
                FROM SURGICAL_PROCEDURE SP 
                JOIN PROCEDURES P ON SP.procedure_id=P.procedure_id 
                JOIN EPISODE E ON SP.episode_id=E.episode_id
                LEFT JOIN OPERATING_ROOM ORR ON SP.operating_room_id=ORR.room_id
                LEFT JOIN WARD W ON SP.ward_id=W.ward_id
                WHERE SP.surgical_procedure_id = ? AND SP.patient_id IN ($placeholders)";

        $params = array_merge([$sp_id], $patient_ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $surgery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$surgery)
            $error_message = "Surgery not found.";
        else {
            // 3. Get Consent
            $stmtC = $pdo->prepare("SELECT consent_id, consent_date, signed FROM PATIENT_CONSENTS WHERE episode_id = ? AND procedure_id = ?");
            $stmtC->execute([$surgery['episode_id'], $surgery['procedure_id']]);
            $consent = $stmtC->fetch(PDO::FETCH_ASSOC);

            // 4. Staff
            $stmtS = $pdo->prepare("SELECT CONCAT(first_name,' ',surnames) as name, role_in_surgery FROM SURGICAL_PROCEDURE_SURGEONS SPS JOIN SURGEONS S ON SPS.surgeon_id=S.surgeon_id WHERE surgical_procedure_id=?");
            $stmtS->execute([$sp_id]);
            $surgeons = $stmtS->fetchAll(PDO::FETCH_ASSOC);

            // 5. Allergies (Check for ALL patient IDs)
            $sqlA = "SELECT allergen_name FROM PATIENT_ALLERGIES pa JOIN ALLERGIES a ON pa.allergen_ID = a.allergen_ID WHERE patient_id IN ($placeholders)";
            $stmtA = $pdo->prepare($sqlA);
            $stmtA->execute($patient_ids);
            $allergies = $stmtA->fetchAll(PDO::FETCH_COLUMN);
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Details | <?php echo htmlspecialchars($surgery['procedure_name'] ?? 'Surgery'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* DESIGN SYSTEM: Matching Dashboard */
        :root { 
            --bg-body: #f0f4f8; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #0891b2; 
            --primary-dark: #155e75;
            --surface: #ffffff;
            --radius-lg: 12px;
            --shadow-soft: 0 2px 4px -1px rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.03);
        }
        
        body { background-color: var(--bg-body); font-family: 'Inter', system-ui, sans-serif; color: var(--text-dark); padding-bottom: 60px; font-size: 0.95rem; }
        
        /* HEADER */
        .top-header { 
            background: var(--surface); 
            height: 60px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 30px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: var(--shadow-soft);
        }
        .brand { font-weight: 800; color: var(--primary-dark); font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        .brand-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(8, 145, 178, 0.3); font-size: 0.9rem; }

        /* CARDS */
        .card-clean { 
            background: var(--surface); 
            border: 1px solid #e2e8f0; 
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-soft); 
            overflow: hidden; 
            height: auto;
            transition: transform 0.2s;
        }
        .card-header-simple { 
            padding: 1rem 1.5rem; 
            border-bottom: 1px solid #f1f5f9; 
            font-weight: 700; 
            font-size: 0.95rem;
            color: var(--text-dark);
            background: #fff;
            display: flex; justify-content: space-between; align-items: center;
        }
        
        /* CONSENT CARD STYLES */
        .card-consent { border-left: 4px solid #f59e0b; }
        .card-consent.signed { border-left: 4px solid #10b981; }
        
        /* BUTTONS */
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 8px; padding: 6px 16px; font-weight: 600; transition: 0.2s; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; color: var(--primary); }
        
        .btn-action { background: var(--primary); color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600; width: 100%; transition: 0.2s; }
        .btn-action:hover { background: var(--primary-dark); transform: translateY(-1px); }

        /* ALERTS */
        .alert-danger-modern { background: #fef2f2; color: #b91c1c; border-radius: 10px; padding: 1rem; border: 1px solid #fecaca; }
        
        /* STATUS BADGES */
        .badge-status { font-size: 0.75rem; padding: 5px 12px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-scheduled { background: #e0f2fe; color: #0284c7; }
        .bg-completed { background: #dcfce7; color: #166534; }
        
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { color: var(--text-dark); font-weight: 600; }
    </style>
</head>

<body>

    <header class="top-header">
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-heart-pulse-fill"></i></div>
            <span>Patient Portal</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small d-none d-md-block fw-bold"><?php echo htmlspecialchars($display_name ?? 'User'); ?></span>
            <a href="../../controllers/logout.php" class="btn btn-glass btn-sm text-danger border-0 px-2"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>

    <div class="container py-4" style="max-width: 1000px;">
        
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="patient_dashboard.php" class="btn-glass"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger-modern shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($surgery['procedure_name']); ?></h2>
                    <span class="text-muted small">Procedure ID: #<?php echo $sp_id; ?></span>
                </div>
                <span class="badge badge-status <?php echo ($surgery['status']=='Completed')?'bg-completed':'bg-scheduled'; ?>">
                    <?php echo $surgery['status']; ?>
                </span>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card-clean mb-4">
                        <div class="card-header-simple">
                            <span><i class="bi bi-info-circle me-2 text-primary"></i> Surgical Details</span>
                        </div>
                        <div class="p-4">
                            <div class="info-row">
                                <span class="info-label">Date & Time</span>
                                <span class="info-value"><?php echo date('l, d M Y - H:i', strtotime($surgery['date_time'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Location</span>
                                <span class="info-value">
                                    <i class="bi bi-door-open me-1 text-muted"></i> 
                                    <?php echo $surgery['room_number'] ? $surgery['room_number'] : '<span class="text-muted fst-italic">Pending Assignment</span>'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Ward</span>
                                <span class="info-value"><?php echo $surgery['ward_name'] ?? '-'; ?></span>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="text-uppercase text-muted small fw-bold mb-3">Medical Team</h6>
                                <?php if(empty($surgeons)): ?>
                                    <div class="text-muted small fst-italic">Team assignment pending.</div>
                                <?php else: foreach ($surgeons as $s): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-person-fill"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark text-sm"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?php echo htmlspecialchars($s['role_in_surgery']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    
                    <?php 
                        $isSigned = ($consent && $consent['signed']);
                        $cardClass = $isSigned ? 'signed' : '';
                        $icon = $isSigned ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-warning';
                        $title = $isSigned ? 'Consent Signed' : 'Action Required';
                    ?>
                    <div class="card-clean card-consent <?php echo $cardClass; ?> mb-4">
                        <div class="card-header-simple">
                            <span><i class="bi bi-file-earmark-check me-2"></i> Documentation</span>
                        </div>
                        <div class="p-4 text-center">
                            <div class="mb-3">
                                <i class="bi <?php echo $icon; ?>" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="fw-bold mb-1"><?php echo $title; ?></h5>
                            
                            <?php if ($isSigned): ?>
                                <p class="text-muted small">Signed on <?php echo date('d M Y', strtotime($consent['consent_date'])); ?></p>
                                <button class="btn btn-glass btn-sm w-100 justify-content-center" disabled>
                                    <i class="bi bi-check2-all"></i> Verified
                                </button>
                            <?php elseif ($consent): ?>
                                <p class="text-muted small mb-3">Please sign the consent form before surgery.</p>
                                <button onclick="openSignModal(<?php echo $consent['consent_id']; ?>)" class="btn-action shadow-sm">
                                    Sign Consent Now <i class="bi bi-pen-fill ms-2"></i>
                                </button>
                            <?php else: ?>
                                <p class="text-muted small fst-italic">No consent document generated yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($allergies)): ?>
                        <div class="alert-danger-modern shadow-sm">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi bi-shield-exclamation fs-4 mt-1"></i>
                                <div>
                                    <div class="fw-bold text-uppercase small mb-1" style="letter-spacing: 0.5px;">Medical Alerts</div>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($allergies as $a) echo "<li>$a</li>"; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-clean p-3 text-center">
                            <span class="text-success small fw-bold"><i class="bi bi-check-circle me-1"></i> No Known Allergies</span>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="signModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold ms-2 mt-2">Digital Signature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <input type="hidden" id="signConsentId">
                    <div class="text-center mb-4 mt-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-3">
                            <i class="bi bi-pen-fill fs-3"></i>
                        </div>
                        <p class="text-muted small mb-0">You are signing consent for:</p>
                        <h5 class="fw-bold text-dark"><?php echo htmlspecialchars($surgery['procedure_name'] ?? ''); ?></h5>
                    </div>
                    
                    <div class="alert alert-light border small text-secondary mb-4 p-3 rounded-3">
                        <i class="bi bi-info-circle me-2"></i> I hereby authorize the performance of this procedure and acknowledge that I have been informed of the risks and benefits.
                    </div>
                    
                    <div class="form-check p-3 bg-light rounded-3 mb-3 border">
                        <input class="form-check-input mt-1" type="checkbox" id="agreeCheck" onchange="document.getElementById('btnConfirmSign').disabled = !this.checked">
                        <label class="form-check-label fw-bold ms-2 small" for="agreeCheck">I agree to sign electronically.</label>
                    </div>
                    
                    <button type="button" class="btn-action w-100 py-2" id="btnConfirmSign" onclick="submitConsent()" disabled>
                        Confirm & Sign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const signModal = new bootstrap.Modal(document.getElementById('signModal'));
        
        function openSignModal(id) {
            document.getElementById('signConsentId').value = id;
            document.getElementById('agreeCheck').checked = false;
            document.getElementById('btnConfirmSign').disabled = true;
            signModal.show();
        }
        
        async function submitConsent() {
            const cid = document.getElementById('signConsentId').value;
            try {
                const res = await fetch('./api/sign_consent.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ consent_id: cid })
                });
                const data = await res.json();
                if (data.success) location.reload();
                else alert("Error: " + data.error);
            } catch (e) { console.error(e); }
        }
    </script>
</body>
</html>