<?php
// groupd/views/anesthesiologist/anesthesiologist_patients.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../../config/config.php';

$controller = new LoginController();
$controller->checkAuth();

// FIX: Ensure role check is robust enough to include 'Anesthesiologist' role name.
$role = strtolower($_SESSION['role_name']);
if (strpos($role, 'anesthesiology') === false && strpos($role, 'anesthesiologist') === false) {
    // Also allow admin access, which should be standard for backend pages
    if (strpos($role, 'admin') === false) {
        die("Access Denied.");
    }
}


$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// 1. Get Anesthesiologist ID
$stmtAnes = $pdo->prepare("SELECT anesthesiologist_id, surnames FROM ANESTHESIOLOGISTS WHERE user_id = ?");
$stmtAnes->execute([$userId]);
$anes = $stmtAnes->fetch();
$anesId = $anes['anesthesiologist_id'] ?? 0;

if ($anesId === 0) {
    die("<div class='alert alert-danger m-4'>Anesthesiologist ID not found for this user.</div>");
}


// 2. Fetch Patients associated with this anesthesiologist through surgical procedures
$sql = "SELECT 
            pat.patient_id, pat.first_name, pat.surnames, pat.date_of_birth, pat.gender,
            
            -- Get the date of the most recent surgery involvement
            MAX(sp.date_time) as last_surgery_date,
            COUNT(sp.surgical_procedure_id) as total_surgeries,
            
            -- Get the surgical_procedure_id of the most recent surgery for the chart link
            (SELECT sp_inner.surgical_procedure_id 
             FROM SURGICAL_PROCEDURE sp_inner
             JOIN EPISODE ep_inner ON sp_inner.episode_id = ep_inner.episode_id
             WHERE ep_inner.patient_id = pat.patient_id
             AND sp_inner.surgical_procedure_id IN (
                 SELECT surgical_procedure_id FROM SURGICAL_PROCEDURE_ANESTHESIOLOGISTS WHERE anesthesiologist_id = :aid_sub
             )
             ORDER BY sp_inner.date_time DESC
             LIMIT 1) as last_spid
            
        FROM PATIENT pat
        JOIN EPISODE ep ON pat.patient_id = ep.patient_id
        JOIN SURGICAL_PROCEDURE sp ON ep.episode_id = sp.episode_id
        JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id
        
        WHERE spa.anesthesiologist_id = :aid_main
        GROUP BY pat.patient_id
        ORDER BY last_surgery_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':aid_sub' => $anesId,
    ':aid_main' => $anesId
]);
$patients = $stmt->fetchAll();
?>

<div class="p-0">
    <div class="card-header-custom fw-bold text-secondary d-flex justify-content-between align-items-center bg-white p-4 border-bottom">
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill me-2"></i> My Patient Directory (Anesthesia)</h5>
        <input type="text" id="searchPat" class="form-control w-25" placeholder="Filter name...">
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle table-hover">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Patient Name</th>
                    <th>Gender/Age</th>
                    <th>Last Case Date</th>
                    <th>Total Cases</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody id="patTable">
                <?php if (empty($patients)): ?>
                    <tr><td colspan="5" class="p-4 text-center text-muted fst-italic">No patients assigned to your active surgical procedures.</td></tr>
                <?php else: ?>
                    <?php foreach($patients as $p): 
                        $dob = new DateTime($p['date_of_birth']);
                        $now = new DateTime();
                        $age = $now->diff($dob)->y;
                        $initials = strtoupper(mb_substr($p['first_name'],0,1) . mb_substr($p['surnames'],0,1));
                        
                        $linkSPID = $p['last_spid'] ?? 0;
                    ?>
                    <tr class="patient-row">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="initials bg-light text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #fce4ec !important; color: #d81b60 !important;">
                                    <?php echo $initials; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'].' '.$p['surnames']); ?></div>
                                    <div class="small text-muted"><?php echo $p['patient_id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $p['gender']; ?> / <?php echo $age; ?> yrs</td>
                        <td><?php echo date('d M Y', strtotime($p['last_surgery_date'])); ?></td>
                        <td><span class="badge bg-secondary rounded-pill"><?php echo $p['total_surgeries']; ?></span></td>
                        <td class="text-end pe-4">
                            <a href="patient_chart.php?spid=<?php echo $linkSPID; ?>" 
                                class="btn btn-sm btn-outline-primary fw-bold" 
                                <?php echo $linkSPID == 0 ? 'disabled' : ''; ?>
                            >
                                <i class="bi bi-person-lines-fill me-1"></i> View Chart
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.getElementById('searchPat').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('.patient-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
</script>