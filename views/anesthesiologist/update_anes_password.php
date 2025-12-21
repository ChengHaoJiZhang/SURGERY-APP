```php
<?php
// grupd/views/nurse/update_anes_password.php
// Temporary script to change the anesthesiologist password (dr_sheldon) to 'anest123'.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';

$message = "";
$status_color = "danger";
$user_id_anes = 9; 
$new_password_plain = 'anest123';

try {
    $pdo = getDBConnection();
    
    // 1. Generate the new secure password hash
    $new_password_hash = password_hash($new_password_plain, PASSWORD_BCRYPT);

    // 2. Update the USERS table for dr_sheldon (User ID 9)
    $sql = "UPDATE USERS SET pass = :hash, updated_at = NOW() WHERE user_id = :uid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hash' => $new_password_hash, ':uid' => $user_id_anes]);
    
    if ($stmt->rowCount() > 0) {
        $message = "✅ **PASSWORD UPDATE SUCCESSFUL**";
        $message .= "<br>The password for user **dr\_sheldon** (User ID {$user_id_anes}) has been updated.";
        $message .= "<br><br>New Credentials:";
        $message .= "<ul><li>**Username:** dr\_sheldon</li><li>**Password:** `{$new_password_plain}`</li></ul>";
        $status_color = "success";
    } else {
        $message = "⚠️ **WARNING:** User ID {$user_id_anes} not found or password was already set.";
        $status_color = "warning";
    }

} catch (PDOException $e) {
    $message = "❌ **CRITICAL DB ERROR:** Operation failed. Details: " . $e->getMessage();
    $status_color = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Anesthesiologist Password Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Anesthesiologist Password Update Result</h3>
        
        <div class="alert alert-<?php echo $status_color; ?>">
            <p class="lead fw-bold mb-0"><?php echo $message; ?></p>
        </div>

        <a href="../../login.php" class="btn btn-primary">Go to Login Page</a>
    </div>
</div>
</body>
</html>

