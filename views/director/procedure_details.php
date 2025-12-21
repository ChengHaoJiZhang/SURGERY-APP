<?php
// views/director/procedure_details.php
session_start();
require_once __DIR__ . '/../../config/config.php';

// 1. Security & Setup
if (!isset($_SESSION['user_id'])) { /* header("Location: /login.php"); exit(); */ }
$proc_id = $_GET['id'] ?? null;
$pdo = getDBConnection();

$procedure = [];
$surgeons = [];
$others = [];

if ($proc_id) {
    // A. Get Procedure Basic Info
    $stmt = $pdo->prepare("SELECT * FROM PROCEDURES WHERE procedure_id = ?");
    $stmt->execute([$proc_id]);
    $procedure = $stmt->fetch(PDO::FETCH_ASSOC);

    // B. Get Surgeons + Detailed Case History (Using JSON Aggregation)
    $sql_s = "SELECT 
                s.surgeon_id, 
                CONCAT(s.first_name, ' ', s.surnames) as name, 
                s.license_number, 
                COUNT(sp.surgical_procedure_id) as count,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', sp.surgical_procedure_id, 
                        'date_display', DATE_FORMAT(sp.date_time, '%d %b %Y %H:%i'), 
                        'date_filter', DATE_FORMAT(sp.date_time, '%Y-%m-%d'),
                        'status', sp.status
                    )
                ) as cases_json
              FROM SURGICAL_PROCEDURE sp
              JOIN SURGICAL_PROCEDURE_SURGEONS sps ON sp.surgical_procedure_id = sps.surgical_procedure_id
              JOIN SURGEONS s ON sps.surgeon_id = s.surgeon_id
              WHERE sp.procedure_id = ? 
              GROUP BY s.surgeon_id 
              ORDER BY count DESC";
              
    $stmt_s = $pdo->prepare($sql_s);
    $stmt_s->execute([$proc_id]);
    $surgeons = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

    // C. Get Other Staff (Nurse/Anesthesiologist) - Simple Counts
    $sql_o = "SELECT 'Nurse' as role, CONCAT(n.first_name, ' ', n.surnames) as name, COUNT(*) as count
              FROM SURGICAL_PROCEDURE sp 
              JOIN SURGICAL_PROCEDURE_NURSES spn ON sp.surgical_procedure_id = spn.surgical_procedure_id 
              JOIN NURSES n ON spn.nurse_id = n.nurse_id 
              WHERE sp.procedure_id = ? GROUP BY n.nurse_id
              UNION
              SELECT 'Anesthesiologist', CONCAT(a.first_name, ' ', a.surnames), COUNT(*)
              FROM SURGICAL_PROCEDURE sp 
              JOIN SURGICAL_PROCEDURE_ANESTHESIOLOGISTS spa ON sp.surgical_procedure_id = spa.surgical_procedure_id 
              JOIN ANESTHESIOLOGISTS a ON spa.anesthesiologist_id = a.anesthesiologist_id 
              WHERE sp.procedure_id = ? GROUP BY a.anesthesiologist_id";
              
    $stmt_o = $pdo->prepare($sql_o);
    $stmt_o->execute([$proc_id, $proc_id]);
    $others = $stmt_o->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procedure Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bg-body: #121212; --card-bg: #1e1e1e; --text-main: #e0e0e0; --primary: #0d6efd; --border: #333; }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        
        /* FULL WIDTH CONTAINER FIX */
        .main-container { width: 100%; padding: 0 20px; }
        
        .card-clean { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 30px; margin-bottom: 20px; }
        
        /* Badges */
        .badge-icd { background: rgba(13, 110, 253, 0.2); color: #6ea8fe; border: 1px solid rgba(13, 110, 253, 0.3); font-family: monospace; font-size: 0.9rem; padding: 8px 12px; }
        .badge-snomed { background: rgba(220, 53, 69, 0.2); color: #ea868f; border: 1px solid rgba(220, 53, 69, 0.3); font-family: monospace; font-size: 0.9rem; padding: 8px 12px; }
        
        /* Surgeon Rows (Accordion) */
        .surgeon-row { cursor: pointer; border-bottom: 1px solid var(--border); transition: background 0.2s; }
        .surgeon-row:hover { background: #2a2a2a; }
        
        /* Expanded Case List */
        .case-container { padding: 0; background: #181818; border-bottom: 1px solid var(--border); }
        .case-list-header { padding: 10px 25px; font-size: 0.75rem; text-transform: uppercase; font-weight: bold; color: #666; background: #121212; }
        
        .case-item { 
            padding: 15px 25px; border-bottom: 1px solid #2a2a2a; cursor: pointer; 
            display: flex; justify-content: space-between; align-items: center;
            transition: all 0.2s; border-left: 4px solid transparent;
        }
        .case-item:hover { background: #2a2a2a; border-left: 4px solid var(--primary); color: white; }
        .case-item:last-child { border-bottom: none; }
        
        .case-date { font-weight: 500; color: #ddd; font-size: 1rem; }
        .case-status { font-size: 0.8rem; padding: 5px 10px; border-radius: 6px; background: #333; color: #aaa; }
    </style>
</head>
<body>

<div class="main-container">
    <a href="specialties.php" class="btn btn-outline-secondary mb-4 px-4"><i class="bi bi-arrow-left me-2"></i>Back to Specialties</a>

    <?php if ($procedure): ?>
        <!-- PROCEDURE HEADER -->
        <div class="card-clean">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="fw-bold text-white mb-2"><?php echo htmlspecialchars($procedure['procedure_name']); ?></h1>
                    <p class="text-muted lead fs-5 mb-4" style="max-width: 800px;"><?php echo htmlspecialchars($procedure['description']); ?></p>
                </div>
                <div class="d-flex gap-2">
                    <?php if($procedure['icd_code']): ?>
                        <span class="badge badge-icd">ICD: <?php echo $procedure['icd_code']; ?></span>
                    <?php endif; ?>
                    <?php if($procedure['snomed_id']): ?>
                        <span class="badge badge-snomed">SNOMED: <?php echo $procedure['snomed_id']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h4 class="fw-bold text-white mb-3 ps-1">Assigned Professionals (History)</h4>

        <!-- SURGEONS TABLE (ACCORDION) -->
        <div class="card-clean p-0 overflow-hidden mb-5">
            <table class="table table-dark table-hover mb-0 align-middle" style="font-size: 1.05rem;">
                <tbody>
                    <?php if(empty($surgeons)): ?>
                        <tr><td class="p-5 text-center text-muted fs-5">No surgical history found for this procedure type.</td></tr>
                    <?php else: ?>
                        <?php foreach($surgeons as $s): 
                            // Decode JSON to get list of surgeries
                            $cases = json_decode($s['cases_json'], true);
                            $rowId = 'surgeon-' . $s['surgeon_id'];
                        ?>
                        <!-- 1. Main Surgeon Row (Click to Expand) -->
                        <tr class="surgeon-row" onclick="toggleCases('<?php echo $rowId; ?>')">
                            <td class="ps-4 py-4">
                                <div class="fw-bold text-primary fs-4"><?php echo htmlspecialchars($s['name']); ?></div>
                                <div class="text-muted">License: <?php echo htmlspecialchars($s['license_number']); ?></div>
                            </td>
                            <td><span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-3 py-2">Lead Surgeon</span></td>
                            <td class="text-end pe-5 fw-bold text-white fs-5">
                                <?php echo $s['count']; ?> Cases <i class="bi bi-chevron-down ms-3 small text-muted"></i>
                            </td>
                        </tr>
                        
                        <!-- 2. Hidden Case List Row -->
                        <tr id="<?php echo $rowId; ?>" style="display:none;">
                            <td colspan="3" class="case-container">
                                <div class="case-list-header">Specific Cases Performed</div>
                                <?php if($cases): foreach($cases as $c): ?>
                                    <!-- Click redirects to Personnel History Tab -->
                                    <div class="case-item" onclick="goToHistory('<?php echo $c['date_filter']; ?>', '<?php echo $c['id']; ?>')">
                                        <div class="case-date">
                                            <i class="bi bi-calendar-event me-3 text-secondary"></i> <?php echo $c['date_display']; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="case-status"><?php echo $c['status']; ?></span>
                                            <i class="bi bi-arrow-right-circle text-primary fs-5"></i>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- SUPPORT TEAM -->
        <?php if(!empty($others)): ?>
            <h5 class="text-muted text-uppercase small mb-3 ps-1">Support Team (Nurses & Anesthesiologists)</h5>
            <div class="card-clean p-0 overflow-hidden mb-5">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <tbody>
                        <?php foreach($others as $o): ?>
                        <tr>
                            <td class="ps-4 py-3 text-white fw-bold"><?php echo htmlspecialchars($o['name']); ?></td>
                            <td>
                                <?php 
                                    $color = ($o['role'] == 'Nurse') ? 'bg-success text-success' : 'bg-info text-info';
                                    $border = ($o['role'] == 'Nurse') ? 'border-success' : 'border-info';
                                ?>
                                <span class="badge <?php echo $color; ?> bg-opacity-25 border <?php echo $border; ?>"><?php echo $o['role']; ?></span>
                            </td>
                            <td class="text-end pe-5 text-muted"><?php echo $o['count']; ?> Cases</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-danger">Procedure not found.</div>
    <?php endif; ?>
</div>

<script>
    // Toggle visibility of the child row
    function toggleCases(id) {
        const el = document.getElementById(id);
        // Simple display toggle
        el.style.display = (el.style.display === 'none') ? 'table-row' : 'none';
    }

    // Redirect to History
    function goToHistory(date, id) {
        window.location.href = `personnel.php?tab=history&date=${date}&id=${id}`;
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>