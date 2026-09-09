<?php
session_start();
require_once 'database.php';

if (isset($_SESSION['login'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Login dengan verifikasi hash
    $result = loginUser($username, $password);
    
    if ($result['success']) {
        $user = $result['user'];
        
        $_SESSION['login'] = true;
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        header('Location: index.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Peminjaman Barang</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .logo-sekolah {
            display: block;
            width: 90px;
            height: 90px;
            object-fit: contain;
            margin: 0 auto 15px;
        }
        
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f4f7fb;
        }

        .login-box {
            width: 380px;
            background: white;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }

        .login-title {
            text-align: center;
            color: #176b40;
            margin-bottom: 8px;
        }

        .login-subtitle {
            text-align: center;
            color: #89938c;
            font-size: 13px;
            margin-bottom: 25px;
        }

        .login-box .field {
            margin-bottom: 18px;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 9px;
            background: #176b40;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #0f5431;
        }

        .login-error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="assets/logo-sekolah.jpg" alt="logo-sekolah" class="logo-sekolah">
        
        <h1 class="login-title">SIMBAR</h1>
        <h3 class="login-title">Sistem Operasi Peminjaman Barang</h3>
        <div class="login-subtitle">Silakan masuk ke sistem</div>
        
        <?php if ($error): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required>
            </div>
            
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            
            <button type="submit" name="login" class="login-btn">Login</button>
        </form>
    </div>
</body>
</html>