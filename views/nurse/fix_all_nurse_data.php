<?php
// grupd/views/nurse/fix_all_nurse_data.php
// Script de diagnóstico y corrección.

// FORCED DEBUGGING: Ensure all errors are displayed
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// --- CRITICAL PATH CHECK: Load config and LoginController ---
try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../controllers/LoginController.php';
} catch (Exception $e) {
    die("<h1>❌ ERROR FATAL: No se puede cargar el archivo de configuración o controlador.</h1><p>Verifique que /config/config.php y /controllers/LoginController.php existan.</p>");
}

$message = "";
$pdo = null;
$status_color = "danger";

try {
    // Attempt to establish database connection immediately
    $pdo = getDBConnection();

    // --- PARAMETERS ---
    $plain_password = 'nurse123';
    $user_id_carla = 8; 
    $nurse_id_carla = 1; 
    
    // 1. GENERATE COMPATIBLE PASSWORD HASH
    $new_password_hash = password_hash($plain_password, PASSWORD_BCRYPT);

    // 2. START TRANSACTION for safety
    $pdo->beginTransaction();

    // --- STEP 1: FIX PASSWORD HASH (USERS TABLE) ---
    $stmtPass = $pdo->prepare("UPDATE USERS SET pass = :hash, updated_at = NOW() WHERE user_id = :uid");
    $stmtPass->execute([':hash' => $new_password_hash, ':uid' => $user_id_carla]);
    
    // --- STEP 2: FIX ASSOCIATION (NURSES TABLE) ---
    $pdo->prepare("UPDATE NURSES SET user_id = NULL WHERE user_id = 2")->execute();
    $pdo->prepare("UPDATE NURSES SET user_id = NULL WHERE nurse_id = :nid AND user_id IS NOT NULL")->execute([':nid' => $nurse_id_carla]);
    $stmtAssoc = $pdo->prepare("UPDATE NURSES SET user_id = :uid, updated_at = NOW() WHERE nurse_id = :nid");
    $stmtAssoc->execute([':uid' => $user_id_carla, ':nid' => $nurse_id_carla]);

    // 4. COMMIT changes
    $pdo->commit();
    
    $message = "✅ **ÉXITO TOTAL:** El login de **nurse\_carla** ha sido reconfigurado.";
    $message .= "<br>Contraseña: '{$plain_password}' establecida y vínculo USERS(8) -> NURSES(1) establecido.";
    $status_color = "success";


} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $message = "❌ **ERROR CRÍTICO EN DB:** Operación fallida. Verifique que 'DB_TYPE' y las credenciales en 'config.php' sean correctas. Detalles: " . $e->getMessage();
    $status_color = "danger";
} catch (Exception $e) {
    $message = "❌ **ERROR GENERAL:** " . $e->getMessage();
    $status_color = "danger";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corrección de Acceso (Final)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Resultado de la Corrección Completa</h3>
        
        <div class="alert alert-<?php echo $status_color; ?>">
            <p class="lead fw-bold mb-0"><?php echo $message; ?></p>
        </div>

        <div class="alert alert-info text-center mt-3">
            <h5 class="fw-bold">Usuario: nurse_carla</h5>
            <h5 class="fw-bold">Contraseña: nurse123</h5>
        </div>
        
        <a href="../../login.php" class="btn btn-primary">Ir a Login</a>
    </div>
</div>
</body>
</html>