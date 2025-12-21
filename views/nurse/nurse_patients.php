<?php
// groupd/views/nurse/nurse_patients.php
// REDESIGNED: Matches Surgeon's "Clean & Depth" Aesthetic + Links to Episode

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) die("Access Denied.");

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get Nurse ID
$stmtNurse = $pdo->prepare("SELECT nurse_id FROM NURSES WHERE user_id = ?");
$stmtNurse->execute([$userId]);
$nurse = $stmtNurse->fetch();
$nurseId = $nurse['nurse_id'];

// Fetch Patients associated with this nurse
$sql = "SELECT 
            pat.patient_id, pat.first_name, pat.surnames, pat.date_of_birth, pat.gender,
            MAX(sp.date_time) as last_surgery_date,
            COUNT(sp.surgical_procedure_id) as total_surgeries,
            
            -- Get SPID for specific surgery links
            (SELECT sp_inner.surgical_procedure_id 
             FROM SURGICAL_PROCEDURE sp_inner
             JOIN EPISODE ep_inner ON sp_inner.episode_id = ep_inner.episode_id
             WHERE ep_inner.patient_id = pat.patient_id
             AND sp_inner.surgical_procedure_id IN (
                 SELECT surgical_procedure_id FROM SURGICAL_PROCEDURE_NURSES WHERE nurse_id = :nid_sub_1
             )
             ORDER BY sp_inner.date_time DESC
             LIMIT 1) as last_spid,

            -- NEW: Get Episode ID for the main 'View Episode' link
            (SELECT sp_inner.episode_id 
             FROM SURGICAL_PROCEDURE sp_inner
             JOIN EPISODE ep_inner ON sp_inner.episode_id = ep_inner.episode_id
             WHERE ep_inner.patient_id = pat.patient_id
             AND sp_inner.surgical_procedure_id IN (
                 SELECT surgical_procedure_id FROM SURGICAL_PROCEDURE_NURSES WHERE nurse_id = :nid_sub_2
             )
             ORDER BY sp_inner.date_time DESC
             LIMIT 1) as last_epid

        FROM PATIENT pat
        JOIN EPISODE ep ON pat.patient_id = ep.patient_id
        JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id
        JOIN SURGICAL_PROCEDURE_NURSES spn ON sp.surgical_procedure_id = spn.surgical_procedure_id
        WHERE spn.nurse_id = :nid_main
        GROUP BY pat.patient_id
        ORDER BY last_surgery_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':nid_sub_1' => $nurseId, ':nid_sub_2' => $nurseId, ':nid_main' => $nurseId]);
$patients = $stmt->fetchAll();
?>

<div class="d-flex flex-column h-100">
    
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
        <div>
            <h6 class="fw-bold text-dark mb-1 text-uppercase ls-1"><i class="bi bi-people-fill me-2 text-primary"></i> Patient Directory</h6>
            <small class="text-muted">Manage assigned patient records</small>
        </div>
        <div class="position-relative" style="width: 300px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="searchPat" class="form-control ps-5 rounded-pill border-light bg-light text-dark" placeholder="Search by name..." style="font-size: 0.9rem;">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="bg-light text-secondary small text-uppercase">
                <tr>
                    <th class="ps-4 py-3 border-bottom-0">Patient Name</th>
                    <th class="border-bottom-0">Demographics</th>
                    <th class="border-bottom-0">Last Case</th>
                    <th class="border-bottom-0 text-center">Total Cases</th>
                    <th class="text-end pe-4 border-bottom-0">Actions</th>
                </tr>
            </thead>
            <tbody id="patTable">
                <?php if (empty($patients)): ?>
                    <tr><td colspan="5" class="p-5 text-center text-muted fst-italic">No patients assigned to your history.</td></tr>
                <?php else: ?>
                    <?php foreach($patients as $p): 
                        $dob = new DateTime($p['date_of_birth']);
                        $now = new DateTime();
                        $age = $now->diff($dob)->y;
                        $initials = strtoupper(mb_substr($p['first_name'],0,1) . mb_substr($p['surnames'],0,1));
                        $linkEPID = $p['last_epid'] ?? 0;
                    ?>
                    <tr class="patient-row transition-hover">
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                     style="width: 40px; height: 40px; background-color: #e0f2fe; color: #0284c7;">
                                    <?php echo $initials; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></div>
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">ID: <?php echo $p['patient_id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-dark fw-medium"><?php echo $age; ?> yrs</div>
                            <small class="text-muted"><?php echo $p['gender'] == 'F' ? 'Female' : 'Male'; ?></small>
                        </td>
                        <td>
                            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?php echo date('d M Y', strtotime($p['last_surgery_date'])); ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-3 rounded-pill"><?php echo $p['total_surgeries']; ?></span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if($linkEPID > 0): ?>
                                <a href="clinical_episode.php?id=<?php echo $linkEPID; ?>" class="btn btn-sm btn-glass text-primary fw-bold">
                                    View Episode <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-light text-muted border" disabled>No Active Case</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Search Filter Logic
    document.getElementById('searchPat').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('.patient-row').forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    
    // Mark as loaded for the dashboard controller
    if(document.getElementById('patientsContent')) {
        document.getElementById('patientsContent').dataset.loaded = true;
    }
</script>