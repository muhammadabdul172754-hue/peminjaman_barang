<?php
$koneksi = mysqli_connect("localhost", "root", "", "peminjaman_barang");

if (!$koneksi) {
    die("Koneksi database gagal");
}

define('BASE_PATH', __DIR__);
define('BASE_URL', '/peminjaman_barang');
