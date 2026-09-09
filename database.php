<?php
// =====================================================
// DATABASE.PHP - DENGAN PASSWORD HASHING
// =====================================================

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'peminjaman_barang');

// =====================================================
// KONEKSI DATABASE
// =====================================================

function getConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Koneksi gagal: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Error Database: " . $e->getMessage());
    }
}

// =====================================================
// FUNGSI PASSWORD HASHING
// =====================================================

/**
 * Hash password menggunakan PASSWORD_DEFAULT (bcrypt)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifikasi password dengan hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Cek apakah password perlu rehash (upgrade ke algoritma baru)
 */
function needsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

// =====================================================
// FUNGSI USER / AUTHENTIKASI
// =====================================================

/**
 * Registrasi user baru dengan password hash
 */
function registerUser($username, $password, $role = 'siswa') {
    $conn = getConnection();
    
    // Cek username sudah ada atau belum
    $check = $conn->query("SELECT id_user FROM users WHERE username = '" . 
                          $conn->real_escape_string($username) . "'");
    
    if ($check && $check->num_rows > 0) {
        $conn->close();
        return ['success' => false, 'message' => 'Username sudah digunakan'];
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Simpan ke database
    $sql = "INSERT INTO users (username, password, role) VALUES (
        '" . $conn->real_escape_string($username) . "',
        '" . $conn->real_escape_string($hashedPassword) . "',
        '" . $conn->real_escape_string($role) . "'
    )";
    
    $result = $conn->query($sql);
    $id = $conn->insert_id;
    $conn->close();
    
    if ($result) {
        return ['success' => true, 'id' => $id, 'message' => 'Registrasi berhasil'];
    } else {
        return ['success' => false, 'message' => 'Registrasi gagal'];
    }
}

/**
 * Login user dengan verifikasi password hash
 */
function loginUser($username, $password) {
    $conn = getConnection();
    
    // Cari user berdasarkan username
    $sql = "SELECT id_user, username, password, role FROM users 
            WHERE username = '" . $conn->real_escape_string($username) . "' 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $conn->close();
        
        // Verifikasi password dengan hash
        if (verifyPassword($password, $user['password'])) {
            // Cek apakah perlu rehash
            if (needsRehash($user['password'])) {
                // Update password dengan hash baru
                updateUserPassword($user['id_user'], $password);
            }
            
            return [
                'success' => true,
                'user' => [
                    'id_user' => $user['id_user'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ];
        } else {
            return ['success' => false, 'message' => 'Password salah'];
        }
    }
    
    $conn->close();
    return ['success' => false, 'message' => 'Username tidak ditemukan'];
}

/**
 * Update password user dengan hash baru
 */
function updateUserPassword($userId, $newPassword) {
    $conn = getConnection();
    $hashedPassword = hashPassword($newPassword);
    
    $sql = "UPDATE users SET password = '" . 
           $conn->real_escape_string($hashedPassword) . 
           "' WHERE id_user = " . (int)$userId;
    
    $result = $conn->query($sql);
    $affected = $conn->affected_rows;
    $conn->close();
    
    return $result && $affected > 0;
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $conn = getConnection();
    $sql = "SELECT id_user, username, role FROM users WHERE id_user = " . (int)$userId;
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $conn->close();
        return $user;
    }
    
    $conn->close();
    return null;
}

/**
 * Get all users
 */
function getAllUsers() {
    $conn = getConnection();
    $sql = "SELECT id_user, username, role, created_at FROM users ORDER BY id_user DESC";
    $result = $conn->query($sql);
    
    $users = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    $conn->close();
    return $users;
}

/**
 * Delete user by ID
 */
function deleteUser($userId) {
    $conn = getConnection();
    $sql = "DELETE FROM users WHERE id_user = " . (int)$userId;
    $result = $conn->query($sql);
    $affected = $conn->affected_rows;
    $conn->close();
    
    return $result && $affected > 0;
}

/**
 * Change user role
 */
function changeUserRole($userId, $newRole) {
    $validRoles = ['admin', 'kepala_sekolah', 'siswa'];
    if (!in_array($newRole, $validRoles)) {
        return false;
    }
    
    $conn = getConnection();
    $sql = "UPDATE users SET role = '" . 
           $conn->real_escape_string($newRole) . 
           "' WHERE id_user = " . (int)$userId;
    
    $result = $conn->query($sql);
    $affected = $conn->affected_rows;
    $conn->close();
    
    return $result && $affected > 0;
}

// =====================================================
// QUERY HELPER LAINNYA (untuk tabel lain)
// =====================================================

function query($sql) {
    $conn = getConnection();
    $result = $conn->query($sql);
    
    if (!$result) {
        $error = $conn->error;
        $conn->close();
        die("Error Query: " . $error);
    }
    
    $conn->close();
    return $result;
}

function getOne($sql) {
    $result = query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function getAll($sql) {
    $result = query($sql);
    $data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    return $data;
}

function insert($table, $data) {
    $conn = getConnection();
    
    $fields = array_keys($data);
    $values = array_values($data);
    
    $fieldsStr = implode('`, `', $fields);
    $valuesStr = "'" . implode("', '", array_map(function($v) use ($conn) {
        return $conn->real_escape_string($v);
    }, $values)) . "'";
    
    $sql = "INSERT INTO `$table` (`$fieldsStr`) VALUES ($valuesStr)";
    
    $result = $conn->query($sql);
    $id = $conn->insert_id;
    
    $conn->close();
    
    return $result ? $id : false;
}

function update($table, $data, $where) {
    $conn = getConnection();
    
    $set = [];
    foreach ($data as $key => $value) {
        $set[] = "`$key` = '" . $conn->real_escape_string($value) . "'";
    }
    $setStr = implode(', ', $set);
    
    $whereClause = [];
    foreach ($where as $key => $value) {
        $whereClause[] = "`$key` = '" . $conn->real_escape_string($value) . "'";
    }
    $whereStr = implode(' AND ', $whereClause);
    
    $sql = "UPDATE `$table` SET $setStr WHERE $whereStr";
    
    $result = $conn->query($sql);
    $affected = $conn->affected_rows;
    
    $conn->close();
    
    return $result ? $affected : false;
}

function delete($table, $where) {
    $conn = getConnection();
    
    $whereClause = [];
    foreach ($where as $key => $value) {
        $whereClause[] = "`$key` = '" . $conn->real_escape_string($value) . "'";
    }
    $whereStr = implode(' AND ', $whereClause);
    
    $sql = "DELETE FROM `$table` WHERE $whereStr";
    
    $result = $conn->query($sql);
    $affected = $conn->affected_rows;
    
    $conn->close();
    
    return $result ? $affected : false;
}

function escape($string) {
    $conn = getConnection();
    $escaped = $conn->real_escape_string($string);
    $conn->close();
    return $escaped;
}
?>