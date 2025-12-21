<?php
// views/data_agreement.php
session_start();

// Security check: Must be logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_name'])) {
    header("Location: /login.php");
    exit();
}

$role = strtolower($_SESSION['role_name']);
$title = "Data Usage Agreement";
$message = "";
$icon = "bi-shield-lock";
$color_class = "primary";

// --- LOGIC TO SELECT MESSAGE BASED ON ROLE ---

// 1. PATIENT
if (strpos($role, 'patient') !== false) {
    $title = "Patient Data Privacy Notice";
    $icon = "bi-person-heart";
    $color_class = "success";
    $message = "
        <h4 class='alert-heading mb-3'>Your Health Data Privacy</h4>
        <p>Dear Patient,</p>
        <p>To ensure you receive the most accurate diagnosis and effective treatment, your clinical data will be shared securely among your authorized care team (Doctors, Nurses, and Specialists) within Hospital Les Corts.</p>
        <p><strong>This allows us to:</strong></p>
        <ul>
            <li>Provide your treating clinicians with your full history to prevent errors.</li>
            <li>Share diagnostic results instantly with your surgeon.</li>
        </ul>
        <p class='mb-0 fw-bold mt-3'>By proceeding, you acknowledge that your data is used strictly for your medical care.</p>";
} 
// 2. ADMINISTRATOR (NEW SPECIFIC MESSAGE)
elseif (in_array($role, ['admin', 'administrator'])) {
    $title = "System Integrity Protocol";
    $icon = "bi-shield-shaded";
    $color_class = "dark"; // Dark theme for Admin warning
    $message = "
        <h4 class='alert-heading mb-3'>Privileged Access Agreement</h4>
        <div class='alert alert-warning d-flex align-items-center' role='alert'>
            <i class='bi bi-exclamation-triangle-fill flex-shrink-0 me-2'></i>
            <div>
                <strong>Warning:</strong> You are accessing the system with high-level privileges.
            </div>
        </div>
        <p>By accessing the Administrative Control Center, you explicitly compromise to:</p>
        <ul class='list-group list-group-flush mb-3 bg-transparent'>
            <li class='list-group-item bg-transparent'>
                <i class='bi bi-check2-square me-2 text-success'></i> 
                <strong>Not share sensitive data</strong> with unauthorized personnel.
            </li>
            <li class='list-group-item bg-transparent'>
                <i class='bi bi-check2-square me-2 text-success'></i> 
                <strong>Not corrupt data</strong> through improper database manipulation.
            </li>
            <li class='list-group-item bg-transparent'>
                <i class='bi bi-check2-square me-2 text-success'></i> 
                Maintain the confidentiality and integrity of the hospital system.
            </li>
        </ul>
        <p class='mb-0 small text-muted'>All administrative actions are recorded in the Audit Log.</p>";
}
// 3. CLINICAL STAFF (Surgeon, Nurse, Anesthesiologist)
elseif (in_array($role, ['nurse', 'surgeon', 'anesthesiologist', 'doctor', 'physician'])) {
    $title = "Clinical Data Usage Protocol";
    $icon = "bi-hospital";
    $color_class = "info";
    $message = "
        <h4 class='alert-heading mb-3'>Professional Data Access Agreement</h4>
        <p>As a clinical professional at Hospital Les Corts, you are granted access to sensitive patient information.</p>
        <p><strong>Terms of Access:</strong></p>
        <ul>
            <li>You agree to access only the <strong>minimum information necessary</strong> to deliver your specific services.</li>
            <li>Data usage is monitored for compliance with hospital privacy policies.</li>
            <li>Sharing credentials or unauthorized viewing of patient records is strictly prohibited.</li>
        </ul>
        <p class='mb-0 fw-bold mt-3'>By clicking 'Accept & Continue', you confirm your adherence to these data minimization principles.</p>";
} else {
    // Fallback
    $message = "<p>Please confirm you agree to the hospital's data usage policies.</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .agreement-card { max-width: 600px; width: 90%; border: none; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .card-header { background: white; border-bottom: 1px solid #eee; border-radius: 15px 15px 0 0 !important; padding: 25px; text-align: center; }
        .icon-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 15px; }
        .bg-soft-success { background-color: #d1e7dd; color: #198754; }
        .bg-soft-info { background-color: #cff4fc; color: #0dcaf0; }
        .bg-soft-primary { background-color: #cfe2ff; color: #0d6efd; }
        .bg-soft-dark { background-color: #e2e3e5; color: #212529; }
    </style>
</head>
<body>

<div class="card agreement-card">
    <div class="card-header">
        <div class="icon-circle bg-soft-<?php echo $color_class; ?>">
            <i class="bi <?php echo $icon; ?>"></i>
        </div>
        <h3 class="fw-bold mb-0"><?php echo $title; ?></h3>
    </div>
    <div class="card-body p-4">
        <div class="alert alert-light border-0">
            <?php echo $message; ?>
        </div>
        
        <form action="/controllers/LoginController.php" method="POST">
            <div class="d-grid gap-2 mt-4">
                <button type="submit" name="accept_agreement" class="btn btn-<?php echo $color_class; ?> btn-lg py-3 fw-bold shadow-sm">
                    <i class="bi bi-check-lg me-2"></i> Accept & Continue
                </button>
                <a href="/controllers/logout.php" class="btn btn-outline-secondary">Cancel & Logout</a>
            </div>
        </form>
    </div>
    <div class="card-footer text-center text-muted py-3 small bg-white border-0 rounded-bottom">
        Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (<?php echo htmlspecialchars($_SESSION['role_name']); ?>)
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>