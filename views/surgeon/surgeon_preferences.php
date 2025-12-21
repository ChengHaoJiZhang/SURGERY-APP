<?php
// groupd/views/surgeon/surgeon_preferences.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) die("Access Denied.");

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get Surgeon
$stmt = $pdo->prepare("SELECT surgeon_id, surnames FROM SURGEONS WHERE user_id = ?");
$stmt->execute([$userId]);
$surgeon = $stmt->fetch();
$surgId = $surgeon['surgeon_id'];

// Handle POST (Save Preference)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $procId = $_POST['procedure_id'];
    $glove = $_POST['glove_size'];
    $instruments = $_POST['instruments'];
    $notes = $_POST['notes'];

    $sql = "INSERT INTO SURGEON_PREFERENCES (surgeon_id, procedure_id, glove_size, specific_instruments, notes) 
            VALUES (:sid, :pid, :gl, :ins, :nts)
            ON DUPLICATE KEY UPDATE glove_size=:gl, specific_instruments=:ins, notes=:nts";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':sid'=>$surgId, ':pid'=>$procId, ':gl'=>$glove, ':ins'=>$instruments, ':nts'=>$notes]);
    header("Location: surgeon_preferences.php?success=1"); exit;
}

// Fetch Procedures & Existing Preferences
$sql = "SELECT p.procedure_id, p.procedure_name, pref.glove_size, pref.specific_instruments, pref.notes 
        FROM PROCEDURES p
        LEFT JOIN SURGEON_PREFERENCES pref ON p.procedure_id = pref.procedure_id AND pref.surgeon_id = ?
        ORDER BY p.procedure_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$surgId]);
$cards = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Preference Cards | Dr. <?php echo htmlspecialchars($surgeon['surnames']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .brand-header { background: #0d6efd; color: white; padding: 1rem 0; margin-bottom: 2rem; }
        .nav-link { color: rgba(255,255,255,0.8); font-weight: 500; }
        .nav-link:hover, .nav-link.active { color: white; border-bottom: 2px solid white; }
        .card-pref { transition: 0.2s; border: 1px solid #e9ecef; }
        .card-pref:hover { border-color: #0d6efd; box-shadow: 0 4px 12px rgba(13,110,253,0.1); }
    </style>
</head>
<body>

    <header class="brand-header shadow-sm">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-hospital fs-4"></i>
                    <span class="fw-bold fs-5">Surgical Portal</span>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <span>Dr. <?php echo htmlspecialchars($surgeon['surnames']); ?></span>
                    <a href="../../controllers/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
            <ul class="nav nav-tabs border-0">
                <li class="nav-item"><a class="nav-link" href="surgeon_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="surgeon_patients.php">My Patients</a></li>
                <li class="nav-item"><a class="nav-link active" href="#">Preference Cards</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-tools me-2 text-secondary"></i> My Preference Cards</h4>
            <div class="text-muted small">Define your standard setup for each procedure.</div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Preferences saved successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach($cards as $c): 
                $hasPref = !empty($c['glove_size']);
                $borderClass = $hasPref ? 'border-primary' : '';
                $bgHeader = $hasPref ? 'bg-primary text-white' : 'bg-light text-dark';
            ?>
            <div class="col-md-6 col-lg-4">
                <form method="POST">
                    <input type="hidden" name="procedure_id" value="<?php echo $c['procedure_id']; ?>">
                    <div class="card h-100 card-pref <?php echo $borderClass; ?>">
                        <div class="card-header fw-bold d-flex justify-content-between align-items-center <?php echo $bgHeader; ?>">
                            <span class="text-truncate" title="<?php echo htmlspecialchars($c['procedure_name']); ?>">
                                <?php echo htmlspecialchars($c['procedure_name']); ?>
                            </span>
                            <?php if($hasPref): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">GLOVE SIZE</label>
                                <select name="glove_size" class="form-select form-select-sm">
                                    <option value="">Select...</option>
                                    <?php $sizes=['6.0','6.5','7.0','7.5','8.0','8.5']; foreach($sizes as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo ($c['glove_size']==$s)?'selected':''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">SPECIAL INSTRUMENTS</label>
                                <input type="text" name="instruments" class="form-control form-control-sm" placeholder="e.g. Harmonic Scalpel" value="<?php echo htmlspecialchars($c['specific_instruments']??''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted fw-bold">SETUP NOTES</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Patient lateral position..."><?php echo htmlspecialchars($c['notes']??''); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-dark w-100">Save Card</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>