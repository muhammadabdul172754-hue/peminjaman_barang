<?php
// =====================================================
// GENERATE PASSWORD HASH UNTUK USER
// =====================================================

require_once 'database.php';

// Daftar password yang akan di-hash
$users = [
    ['username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
    ['username' => 'kepsek', 'password' => 'kepsek123', 'role' => 'kepala_sekolah'],
    ['username' => 'siswa', 'password' => 'siswa123', 'role' => 'siswa']
];

echo "<h2>Generate Password Hash</h2>";
echo "<hr>";
// apapun password yang di-hash, simpan hash tersebut ke database, bukan password asli.
foreach ($users as $user) {
    $hash = hashPassword($user['password']);
    
    echo "<div style='margin-bottom: 20px;'>";
    echo "<strong>Username:</strong> " . $user['username'] . "<br>";
    echo "<strong>Password:</strong> " . $user['password'] . "<br>";
    echo "<strong>Hash:</strong> <code style='background: #f0f0f0; padding: 5px;'>" . $hash . "</code><br>";
    echo "<strong>Role:</strong> " . $user['role'] . "<br>";
    
    // Cek apakah hash valid
    $valid = verifyPassword($user['password'], $hash);
    echo "<strong>Valid:</strong> " . ($valid ? '✅ Ya' : '❌ Tidak') . "<br>";
    echo "</div>";
    echo "<hr>";
}

echo "<h3>SQL untuk INSERT (dengan hash):</h3>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";

foreach ($users as $user) {
    $hash = hashPassword($user['password']);
    echo "INSERT INTO users (username, password, role) VALUES (\n";
    echo "    '" . $user['username'] . "',\n";
    echo "    '" . $hash . "',\n";
    echo "    '" . $user['role'] . "'\n";
    echo ");\n\n";
}

echo "</pre>";
?>