<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/config/config.php';
$pdo = getDBConnection();

$demoUsers = [];

// DEFINITION: Configuration for Demo Users (English)
$roleConfig = [
    'nurse'    => ['pass' => 'nurse123',      'label' => 'Nurse',           'style' => 'text-bg-info',      'icon' => 'bi-clipboard-pulse'],
    'surgeon'  => ['pass' => 'surgeon123',  'label' => 'Surgeon',       'style' => 'text-bg-primary',   'icon' => 'bi-heart-pulse'],
    'anesthesiologist' => ['pass' => 'anest123',  'label' => 'Anesthesiologist','style' => 'text-bg-warning',   'icon' => 'bi-lungs'],
    'doctor'   => ['pass' => 'surgeon123',  'label' => 'Doctor',          'style' => 'text-bg-primary',   'icon' => 'bi-heart-pulse'], 
    'patient'  => ['pass' => 'password123', 'label' => 'Patient',         'style' => 'text-bg-success',   'icon' => 'bi-plus-square-fill'],
    'director' => ['pass' => 'director123', 'label' => 'Director',        'style' => 'text-bg-dark',      'icon' => 'bi-building'],
    'admin'    => ['pass' => 'admin123',    'label' => 'Administrator',   'style' => 'text-bg-secondary', 'icon' => 'bi-shield-lock']
];

try {
    // SQL: Fetch usernames based on role
    $sql = "SELECT u.username, r.role_name
            FROM USERS u
            INNER JOIN ROLES r ON u.role_id = r.role_id
            WHERE LOWER(r.role_name) = :role_name
              AND u.deleted_at IS NULL
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);

    // Loop through config to populate the demo list
    foreach ($roleConfig as $roleKey => $config) {
        $stmt->execute([':role_name' => $roleKey]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $demoUsers[] = [
                'role_label' => $config['label'],
                'style'      => $config['style'],
                'icon'       => $config['icon'],
                'username'   => $user['username'],
                'password'   => $config['pass']
            ];
        }
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger m-0 rounded-0'>System Error: " . $e->getMessage() . "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal - Hospital Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --hospital-blue: #0056b3;
            --hospital-teal: #17a2b8;
            --bg-light: #f4f6f9;
        }
        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-light);
            height: 100vh;
            overflow: hidden;
        }
        .main-wrapper {
            height: 100vh;
            width: 100%;
        }
        
        /* LEFT SIDE: Branding */
        .brand-panel {
            background-image: url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?q=80&w=2000&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .brand-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(180deg, rgba(0, 86, 179, 0.8) 0%, rgba(2, 48, 97, 0.9) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        /* RIGHT SIDE: Login & Demo */
        .login-panel {
            background: white;
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow-y: auto;
        }
        .login-content {
            max-width: 450px;
            width: 100%;
            margin: auto;
            padding: 3rem 1.5rem;
        }

        /* Demo Card Styling */
        .demo-section {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }
        .scroll-row {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 5px;
            scrollbar-width: thin;
        }
        .user-chip {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 50px;
            padding: 5px 15px 5px 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            min-width: max-content;
        }
        .user-chip:hover {
            border-color: var(--hospital-blue);
            background-color: #eef5fc;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .chip-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        /* Form Inputs */
        .form-floating > .form-control:focus {
            border-color: var(--hospital-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 86, 179, 0.15);
        }
        .btn-hospital {
            background-color: var(--hospital-blue);
            border-color: var(--hospital-blue);
            color: white;
            font-weight: 500;
            padding: 0.8rem;
            letter-spacing: 0.5px;
        }
        .btn-hospital:hover {
            background-color: #004494;
            color: white;
        }
        
        /* Password Toggle Style */
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        .password-toggle:hover {
            color: var(--hospital-blue);
        }
        /* Ensure text doesn't go under the icon */
        #password {
            padding-right: 40px; 
        }
    </style>
</head>
<body>

<div class="row g-0 main-wrapper">
    
    <div class="col-lg-5 d-none d-lg-block brand-panel">
        <div class="brand-overlay">
            <h1 class="display-4 fw-bold mb-3"><i class="bi bi-hospital"></i> Les Corts</h1>
            <p class="lead mb-4">Excellence in Healthcare Management</p>
            <hr class="w-25 border-top border-2 opacity-50 mb-4">
            <small class="opacity-75">Secure Staff Portal • Authorized Personnel Only</small>
        </div>
    </div>

    <div class="col-lg-7 login-panel">
        
        <div class="demo-section">
            <p class="small text-muted fw-bold mb-2 text-uppercase tracking-wider">
                <i class="bi bi-terminal-fill me-1"></i> Demo Environment Access
            </p>
            
            <?php if(empty($demoUsers)): ?>
                <div class="alert alert-warning py-1 small">
                    <i class="bi bi-exclamation-triangle"></i> No demo roles found in database.
                </div>
            <?php else: ?>
                <div class="scroll-row">
                    <?php foreach($demoUsers as $u): ?>
                        <div class="user-chip" onclick="fillForm('<?php echo $u['username']; ?>', '<?php echo $u['password']; ?>')">
                            <div class="chip-icon <?php echo $u['style']; ?>">
                                <i class="bi <?php echo $u['icon']; ?>"></i>
                            </div>
                            <div class="d-flex flex-column" style="line-height: 1;">
                                <span class="fw-bold small"><?php echo htmlspecialchars($u['role_label']); ?></span>
                                <span class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($u['username']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="login-content">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-dark">Welcome Back</h2>
                <p class="text-muted">Please enter your credentials to access the system.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div>Invalid username or password.</div>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['logout'])): ?>
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>Signed out successfully.</div>
                </div>
            <?php endif; ?>

            <form action="/controllers/LoginController.php" method="POST">
                <input type="hidden" name="login" value="1">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="name@example.com" required>
                    <label for="username">Username</label>
                </div>

                <div class="form-floating mb-4 password-container">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                    <span class="password-toggle" onclick="togglePasswordVisibility()">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </span>
                </div>

                <button type="submit" name="login" class="btn btn-hospital w-100 mb-4 rounded-3 shadow-sm">
                    Sign In
                </button>
            </form>
        </div>
        
        <div class="mt-auto p-3 text-center text-muted bg-light border-top">
            <small>&copy; <?php echo date('Y'); ?> Hospital Les Corts. All rights reserved.</small>
        </div>
    </div>
</div>

<script>
    function fillForm(user, pass) {
        const uInput = document.getElementById('username');
        const pInput = document.getElementById('password');
        
        uInput.value = user;
        pInput.value = pass;

        // Subtle flash effect to indicate success
        uInput.style.transition = "background-color 0.3s";
        pInput.style.transition = "background-color 0.3s";
        
        uInput.style.backgroundColor = "#e8f0fe";
        pInput.style.backgroundColor = "#e8f0fe";
        
        setTimeout(() => {
            uInput.style.backgroundColor = "";
            pInput.style.backgroundColor = "";
        }, 400);
    }

    // Function to toggle password visibility
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
