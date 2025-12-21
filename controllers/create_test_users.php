<?php
// create_test_users.php
// LOCATION: /www/groupd/controllers/create_test_users.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. UPDATED PATH: Go up one level (..) to find the config folder
require_once __DIR__ . '/../config/config.php';
$pdo = getDBConnection();

echo "<h2>⚙️ Creating Test Users (from Controllers)...</h2><ul>";

// DEFINITION: The exact users you want to add
$usersToAdd = [
    [
        'username' => 'test_nurse',
        'pass'     => 'nurse123',
        'role_id'  => 1, // Nurse
        'email'    => 'test.nurse@hospital.com'
    ],
    [
        'username' => 'test_patient',
        'pass'     => 'patient123',
        'role_id'  => 2, // Patient
        'email'    => 'test.patient@hospital.com'
    ],
    [
        'username' => 'test_doctor',
        'pass'     => 'doctor123',
        'role_id'  => 3, // Doctor
        'email'    => 'test.doctor@hospital.com'
    ],
    [
        'username' => 'test_admin',
        'pass'     => 'admin123',
        'role_id'  => 4, // Admin
        'email'    => 'test.admin@hospital.com'
    ],
    [
        'username' => 'test_anest',
        'pass'     => 'anest123', // New password
        'role_id'  => 9, // Assuming 9 is the Anesthesiologist Role ID
        'email'    => 'test.anest@hospital.com'
    ]
];

foreach ($usersToAdd as $u) {
    $user = $u['username'];
    $plainPass = $u['pass'];
    
    // Delete old user to avoid duplicates
    $pdo->prepare("DELETE FROM USERS WHERE username = ?")->execute([$user]);

    // Hash the password
    $hashedPass = password_hash($plainPass, PASSWORD_BCRYPT);

    try {
        $sql = "INSERT INTO USERS (username, pass, email, role_id, created_at) 
                VALUES (:username, :pass, :email, :role_id, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $user,
            ':pass'     => $hashedPass,
            ':email'    => $u['email'],
            ':role_id'  => $u['role_id']
        ]);

        echo "<li>✅ Created <strong>$user</strong> with password: <code>$plainPass</code> (Role ID: {$u['role_id']})</li>";
    } catch (PDOException $e) {
        echo "<li>❌ Error creating $user: " . $e->getMessage() . "</li>";
    }
}

echo "</ul><hr>";
// 2. UPDATED LINK: Go up one level to find login.php in the root
echo "<h3><a href='../login.php'>Go to Login Page >></a></h3>";
?>