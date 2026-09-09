<?php

session_start();

require 'koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

$page = $_GET['page'] ?? 'dashboard';

// BATAS AKSES SISWA
if ($_SESSION['role'] === 'siswa') {

    $allowed = ['dashboard', 'pinjam'];

    if (!in_array($page, $allowed)) {
        $page = 'dashboard';
    }
}

$msg = '';
$err = '';

function e($s){
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// =====================================================
// PROSES DATA BARANG
// =====================================================

if($page==='petugas'){

    // SIMPAN / UPDATE
    if(isset($_POST['save'])){

        $id = (int)$_POST['id'];

        $nama = $koneksi->real_escape_string($_POST['nama']);
        $telp = $koneksi->real_escape_string($_POST['telp']);
        $jabatan = $koneksi->real_escape_string($_POST['jabatan']);
        $alamat = $koneksi->real_escape_string($_POST['alamat']);

        if($id){

            $koneksi->query("
                UPDATE petugas SET
                nama_petugas='$nama',
                no_telp='$telp',
                jabatan='$jabatan',
                alamat='$alamat'
                WHERE id_petugas=$id
            ");

        }else{

            $koneksi->query("
                INSERT INTO petugas
                (nama_petugas,no_telp,jabatan,alamat)
                VALUES
                ('$nama','$telp','$jabatan','$alamat')
            ");
        }

        header('Location:?page=petugas');
        exit;
    }

    // HAPUS
    if(isset($_GET['del'])){

        $id = (int)$_GET['del'];

        $koneksi->query("
            DELETE FROM petugas
            WHERE id_petugas=$id
        ");

        header('Location:?page=petugas');
        exit;
    }
}

if ($page === 'barang') {

    // Simpan / Update Barang
    if (isset($_POST['save'])) {

        $id       = (int) $_POST['id'];
        $kode     = $koneksi->real_escape_string($_POST['kode']);
        $nama     = $koneksi->real_escape_string($_POST['nama']);
        $kat      = $koneksi->real_escape_string($_POST['kategori']);
        $jumlah   = (int) $_POST['jumlah'];
        $kondisi  = $koneksi->real_escape_string($_POST['kondisi']);
        $ket      = $koneksi->real_escape_string($_POST['keterangan']);

        if ($id) {

            $sql = "
                UPDATE barang SET
                    kode_barang = '$kode',
                    nama_barang = '$nama',
                    kategori = '$kat',
                    jumlah = $jumlah,
                    kondisi = '$kondisi',
                    keterangan = '$ket'
                WHERE id_barang = $id
            ";

        } else {

            $sql = "
                INSERT INTO barang (
                    kode_barang,
                    nama_barang,
                    kategori,
                    jumlah,
                    kondisi,
                    keterangan
                )
                VALUES (
                    '$kode',
                    '$nama',
                    '$kat',
                    $jumlah,
                    '$kondisi',
                    '$ket'
                )
            ";
        }

        $koneksi->query($sql);

        header('Location: ?page=barang');
        exit;
    }


    // Hapus Barang
    if (isset($_GET['del'])) {

        $id = (int) $_GET['del'];

        $koneksi->query("
            DELETE FROM barang
            WHERE id_barang = $id
        ");

        header('Location: ?page=barang');
        exit;
    }
}


// =====================================================
// PROSES DATA PEMINJAM
// =====================================================

if ($page === 'peminjam') {

    // Simpan / Update Peminjam
    if (isset($_POST['save'])) {

        $id     = (int) $_POST['id'];
        $nama   = $koneksi->real_escape_string($_POST['nama']);
        $kelas  = $koneksi->real_escape_string($_POST['kelas']);
        $telp   = $koneksi->real_escape_string($_POST['telp']);
        $alamat = $koneksi->real_escape_string($_POST['alamat']);

        if ($id) {

            $sql = "
                UPDATE peminjam SET
                    nama_peminjam = '$nama',
                    kelas = '$kelas',
                    no_telp = '$telp',
                    alamat = '$alamat'
                WHERE id_peminjam = $id
            ";

        } else {

            $sql = "
                INSERT INTO peminjam (
                    nama_peminjam,
                    kelas,
                    no_telp,
                    alamat
                )
                VALUES (
                    '$nama',
                    '$kelas',
                    '$telp',
                    '$alamat'
                )
            ";
        }

        $koneksi->query($sql);

        header('Location: ?page=peminjam');
        exit;
    }


    // Hapus Peminjam
    if (isset($_GET['del'])) {

        $id = (int) $_GET['del'];

        $koneksi->query("
            DELETE FROM peminjam
            WHERE id_peminjam = $id
        ");

        header('Location: ?page=peminjam');
        exit;
    }
}


// =====================================================
// PROSES PEMINJAMAN
// =====================================================

if ($page === 'pinjam' && isset($_POST['pinjam'])) {

    $pid   = (int) $_POST['peminjam'];
    $pet   = (int) $_POST['petugas'];

    $tgl   = $koneksi->real_escape_string($_POST['tgl']);
    $tempo = $koneksi->real_escape_string($_POST['tempo']);

    $ids = $_POST['barang'] ?? [];
    $qty = $_POST['qty'] ?? [];


    // Mulai transaksi database
    $koneksi->begin_transaction();

    try {

        // Validasi
        if (!$pid || !$pet || !count($ids)) {

            throw new Exception(
                'Lengkapi data peminjaman.'
            );
        }


        // Simpan transaksi peminjaman
        $koneksi->query("
            INSERT INTO peminjaman (
                id_peminjam,
                id_petugas,
                tanggal_pinjam,
                tanggal_kembali,
                status
            )
            VALUES (
                $pid,
                $pet,
                '$tgl',
                '$tempo',
                'Dipinjam'
            )
        ");


        // Ambil ID peminjaman yang baru
        $new = $koneksi->insert_id;


        // Simpan detail barang
        foreach ($ids as $i => $b) {

            $b = (int) $b;
            $q = (int) $qty[$i];


            // Cek stok barang
            $s = $koneksi->query("
                SELECT jumlah
                FROM barang
                WHERE id_barang = $b
                FOR UPDATE
            ")->fetch_assoc();


            // Validasi stok
            if (
                !$s ||
                $q < 1 ||
                $s['jumlah'] < $q
            ) {

                throw new Exception(
                    'Stok barang tidak cukup.'
                );
            }


            // Simpan detail peminjaman
            $koneksi->query("
                INSERT INTO detail_peminjaman (
                    id_peminjaman,
                    id_barang,
                    jumlah
                )
                VALUES (
                    $new,
                    $b,
                    $q
                )
            ");


            // Kurangi stok barang
            $koneksi->query("
                UPDATE barang
                SET jumlah = jumlah - $q
                WHERE id_barang = $b
            ");
        }


        // Jika berhasil
        $koneksi->commit();

        header(
            'Location: ?page=pinjam&ok=1'
        );

        exit;

    } catch (Exception $x) {

        // Jika gagal, batalkan transaksi
        $koneksi->rollback();

        $err = $x->getMessage();
    }
}


// =====================================================
// PROSES PENGEMBALIAN
// =====================================================

if ($page === 'kembali' && isset($_POST['kembali'])) {

    $id = (int) $_POST['id'];

    $koneksi->begin_transaction();

    try {

        // Ambil data peminjaman
        $p = $koneksi->query("
            SELECT status
            FROM peminjaman
            WHERE id_peminjaman = $id
            FOR UPDATE
        ")->fetch_assoc();


        // Validasi transaksi
        if (
            !$p ||
            $p['status'] !== 'Dipinjam'
        ) {

            throw new Exception(
                'Transaksi tidak valid.'
            );
        }


        // Ambil detail barang
        $ds = $koneksi->query("
            SELECT
                id_barang,
                jumlah
            FROM detail_peminjaman
            WHERE id_peminjaman = $id
        ");


        // Kembalikan stok
        while ($d = $ds->fetch_assoc()) {

            $id_barang = $d['id_barang'];
            $jumlah    = $d['jumlah'];

            $koneksi->query("
                UPDATE barang
                SET jumlah = jumlah + $jumlah
                WHERE id_barang = $id_barang
            ");
        }


        // Update status peminjaman
        $koneksi->query("
            UPDATE peminjaman
            SET status = 'Dikembalikan'
            WHERE id_peminjaman = $id
        ");


        // Selesaikan transaksi
        $koneksi->commit();

        $msg = '
            Pengembalian berhasil
            dan stok dikembalikan.
        ';

    } catch (Exception $x) {

        $koneksi->rollback();

        $err = $x->getMessage();
    }
}


// =====================================================
// MENU SIDEBAR
// =====================================================

$role = $_SESSION['role'];

if ($role === 'siswa') {

    $menus = [
        'dashboard' => '🏠 Dashboard',
        'pinjam' => '📝 Peminjaman'
    ];

} else {

    $menus = [
        'dashboard' => '🏠 Dashboard',
        'petugas' => '👨‍💼 Petugas',
        'barang' => '📦 Barang',
        'peminjam' => '👥 Peminjam',
        'pinjam' => '📝 Peminjaman',
        'kembali' => '🔄 Pengembalian',
        'laporan' => '📊 Laporan'
    ];

}

// =====================================================
// HEADER
// =====================================================

function head($title, $page, $menus){

    $nama = $_SESSION['username'];
    $role = $_SESSION['role'];

    $roleText = [
        'admin' => 'Admin',
        'kepala_sekolah' => 'Kepala Sekolah',
        'siswa' => 'Siswa'
    ];

    echo '
    <!doctype html>
    <html lang="id">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>'.e($title).'</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>

    <body>

    <aside class="side">

        <div class="brand">
            📦 PEMINJAMAN BARANG
        </div>
    ';

    foreach($menus as $k => $v){

        echo '
        <a class="'.($page === $k ? 'on' : '').'"
           href="?page='.$k.'">
            '.$v.'
        </a>
        ';
    }

    echo '
        <a href="logout.php">
            🚪 Logout
        </a>

    </aside>

    <main class="main">

        <div class="top">

            <h1>'.e($title).'</h1>

            <div>
                <b style="color:#176b40">
                    👤 '.e($nama).'
                </b>

                <small style="color:#89938c">
                    ('.e($roleText[$role]).')
                </small>
            </div>

        </div>
    ';
}


// =====================================================
// FOOTER
// =====================================================

function foot()
{
?>

    <div
        style="
            text-align:center;
            color:#89938c;
            font-size:12px;
            margin-top:30px;
        "
    >

        Aplikasi Peminjaman Barang • Udin

    </div>

</main>

</body>

</html>

<?php
}


// =====================================================
// TAMPILKAN HEADER
// =====================================================

head(
    ucfirst($page),
    $page,
    $menus
);


// =====================================================
// DASHBOARD
// =====================================================

if ($page === 'dashboard') {



    // Total barang
    $a = $koneksi->query("
        SELECT COUNT(*) AS c
        FROM barang
    ")->fetch_assoc()['c'];


    // Total peminjam
    $b = $koneksi->query("
        SELECT COUNT(*) AS c
        FROM peminjam
    ")->fetch_assoc()['c'];


    // Barang sedang dipinjam
    $c = $koneksi->query("
        SELECT COUNT(*) AS c
        FROM peminjaman
        WHERE status = 'Dipinjam'
    ")->fetch_assoc()['c'];


    // Barang sudah kembali
    $d = $koneksi->query("
        SELECT COUNT(*) AS c
        FROM peminjaman
        WHERE status = 'Dikembalikan'
    ")->fetch_assoc()['c'];

?>

    <div class="cards">


        <div class="card">

            <small>
                Total Barang
            </small>

            <div class="num">
                <?= $a ?>
            </div>

        </div>


        <div class="card">

            <small>
                Total Peminjam
            </small>

            <div class="num">
                <?= $b ?>
            </div>

        </div>


        <div class="card">

            <small>
                Sedang Dipinjam
            </small>

            <div class="num">
                <?= $c ?>
            </div>

        </div>


        <div class="card">

            <small>
                Sudah Kembali
            </small>

            <div class="num">
                <?= $d ?>
            </div>

        </div>

    </div>


    <!-- PEMINJAMAN TERBARU -->

    <div class="box">

        <div class="head">

            <h2>
                Peminjaman Terbaru
            </h2>

            <a
                class="btn"
                href="?page=pinjam"
            >
                + Peminjaman
            </a>

        </div>


        <table>

            <tr>

                <th>No</th>

                <th>Peminjam</th>

                <th>Barang</th>

                <th>Tanggal</th>

                <th>Status</th>

            </tr>

<?php

    $q = $koneksi->query("
        SELECT
            p.id_peminjaman,
            pm.nama_peminjam,
            p.tanggal_pinjam,
            p.status,

            GROUP_CONCAT(
                CONCAT(
                    b.nama_barang,
                    ' x',
                    d.jumlah
                )
                SEPARATOR ', '
            ) AS br

        FROM peminjaman p

        JOIN peminjam pm
            ON pm.id_peminjam = p.id_peminjam

        JOIN detail_peminjaman d
            ON d.id_peminjaman = p.id_peminjaman

        JOIN barang b
            ON b.id_barang = d.id_barang

        GROUP BY p.id_peminjaman

        ORDER BY p.id_peminjaman DESC

        LIMIT 5
    ");


    while ($r = $q->fetch_assoc()):

?>

            <tr>

                <td>
                    #<?= $r['id_peminjaman'] ?>
                </td>

                <td>
                    <?= e($r['nama_peminjam']) ?>
                </td>

                <td>
                    <?= e($r['br']) ?>
                </td>

                <td>
                    <?= $r['tanggal_pinjam'] ?>
                </td>

                <td>

                    <span class="badge">

                        <?= $r['status'] ?>

                    </span>

                </td>

            </tr>

<?php

    endwhile;

?>

        </table>

    </div>


<?php
}

// =====================================================
// DATA PETUGAS
// =====================================================

elseif($page==='petugas'){

    $edit = null;

    if(isset($_GET['edit'])){
        $edit = $koneksi->query(
            'SELECT * FROM petugas WHERE id_petugas='.(int)$_GET['edit']
        )->fetch_assoc();
    }

    echo '
    <div class="box">

        <div class="head">
            <h2>'.($edit ? 'Edit' : 'Tambah').' Petugas</h2>
        </div>

        <form method="post">

            <input
                type="hidden"
                name="id"
                value="'.($edit['id_petugas'] ?? 0).'">

            <div class="formgrid">

                <div class="field">
                    <label>Nama Petugas</label>
                    <input
                        type="text"
                        name="nama"
                        required
                        value="'.e($edit['nama_petugas'] ?? '').'">
                </div>

                <div class="field">
                    <label>No. Telepon</label>
                    <input
                        type="text"
                        name="telp"
                        value="'.e($edit['no_telp'] ?? '').'">
                </div>

                <div class="field">
                    <label>Jabatan</label>
                    <select name="jabatan">

                        <option value="Admin"
                        '.(($edit['jabatan'] ?? '')==='Admin'?'selected':'').'>
                            Admin
                        </option>

                        <option value="Petugas"
                        '.(($edit['jabatan'] ?? '')==='Petugas'?'selected':'').'>
                            Petugas
                        </option>

                    </select>
                </div>

                <div class="field">
                    <label>Alamat</label>
                    <input
                        type="text"
                        name="alamat"
                        value="'.e($edit['alamat'] ?? '').'">
                </div>

            </div>

            <button
                class="btn"
                name="save">
                Simpan
            </button>

            '.($edit ? '
            <a
                href="?page=petugas"
                class="btn gray">
                Batal
            </a>
            ' : '').'

        </form>

    </div>


    <div class="box">

        <div class="head">
            <h2>Data Petugas</h2>
        </div>

        <table>

            <tr>
                <th>No</th>
                <th>Nama Petugas</th>
                <th>No. Telepon</th>
                <th>Jabatan</th>
                <th>Alamat</th>
                <th>Aksi</th>
            </tr>
    ';

    $q = $koneksi->query(
        'SELECT * FROM petugas ORDER BY id_petugas DESC'
    );

    while($r = $q->fetch_assoc()){

        echo '
        <tr>

            <td>'.$r['id_petugas'].'</td>

            <td>'.e($r['nama_petugas']).'</td>

            <td>'.e($r['no_telp']).'</td>

            <td>'.e($r['jabatan']).'</td>

            <td>'.e($r['alamat']).'</td>

            <td class="actions">

                <a
                    class="btn"
                    href="?page=petugas&edit='.$r['id_petugas'].'">
                    Edit
                </a>

                <a
                    class="btn red"
                    onclick="return confirm(\'Hapus petugas?\')"
                    href="?page=petugas&del='.$r['id_petugas'].'">
                    Hapus
                </a>

            </td>

        </tr>
        ';
    }

    echo '
        </table>

    </div>
    ';
}
// =====================================================
// DATA BARANG
// =====================================================

elseif ($page === 'barang') {

    $edit = null;


    // Ambil data untuk edit
    if (isset($_GET['edit'])) {

        $id = (int) $_GET['edit'];

        $edit = $koneksi->query("
            SELECT *
            FROM barang
            WHERE id_barang = $id
        ")->fetch_assoc();
    }

?>

    <!-- FORM BARANG -->

    <div class="box">

        <div class="head">

            <h2>

                <?= $edit ? 'Edit' : 'Tambah' ?>

                Barang

            </h2>

        </div>


        <form method="post">

            <input
                type="hidden"
                name="id"
                value="<?= $edit['id_barang'] ?? 0 ?>"
            >


            <div class="formgrid">


                <div class="field">

                    <label>
                        Kode Barang
                    </label>

                    <input
                        name="kode"
                        required
                        value="<?= e($edit['kode_barang'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Nama Barang
                    </label>

                    <input
                        name="nama"
                        required
                        value="<?= e($edit['nama_barang'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Kategori
                    </label>

                    <input
                        name="kategori"
                        value="<?= e($edit['kategori'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Jumlah
                    </label>

                    <input
                        type="number"
                        min="0"
                        name="jumlah"
                        value="<?= $edit['jumlah'] ?? 0 ?>"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Kondisi
                    </label>

                    <select name="kondisi">

<?php

    foreach (
        ['Baik', 'Rusak Ringan', 'Rusak Berat']
        as $k
    ):

?>

                        <option
                            <?= (($edit['kondisi'] ?? 'Baik') === $k)
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= $k ?>

                        </option>

<?php

    endforeach;

?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Keterangan
                    </label>

                    <input
                        name="keterangan"
                        value="<?= e($edit['keterangan'] ?? '') ?>"
                    >

                </div>


            </div>


            <button
                class="btn"
                name="save"
            >

                Simpan

            </button>

        </form>

    </div>


    <!-- TABEL BARANG -->

    <div class="box">

        <div class="head">

            <h2>
                Daftar Barang
            </h2>

        </div>


        <table>

            <tr>

                <th>No</th>

                <th>Kode</th>

                <th>Nama</th>

                <th>Kategori</th>

                <th>Stok</th>

                <th>Kondisi</th>

                <th>Aksi</th>

            </tr>

<?php

    $q = $koneksi->query("
        SELECT *
        FROM barang
        ORDER BY id_barang DESC
    ");


    while ($r = $q->fetch_assoc()):

?>

            <tr>

                <td>
                    <?= $r['id_barang'] ?>
                </td>

                <td>
                    <?= e($r['kode_barang']) ?>
                </td>

                <td>
                    <?= e($r['nama_barang']) ?>
                </td>

                <td>
                    <?= e($r['kategori']) ?>
                </td>

                <td>
                    <?= $r['jumlah'] ?>
                </td>

                <td>
                    <?= e($r['kondisi']) ?>
                </td>

                <td class="actions">


                    <a
                        class="btn"
                        href="?page=barang&edit=<?= $r['id_barang'] ?>"
                    >

                        Edit

                    </a>


                    <a
                        class="btn red"
                        href="?page=barang&del=<?= $r['id_barang'] ?>"
                        onclick="return confirm('Hapus barang?')"
                    >

                        Hapus

                    </a>


                </td>

            </tr>

<?php

    endwhile;

?>

        </table>

    </div>


<?php
}


// =====================================================
// DATA PEMINJAM
// =====================================================

elseif ($page === 'peminjam') {

    $edit = null;


    // Ambil data edit
    if (isset($_GET['edit'])) {

        $id = (int) $_GET['edit'];

        $edit = $koneksi->query("
            SELECT *
            FROM peminjam
            WHERE id_peminjam = $id
        ")->fetch_assoc();
    }

?>

    <div class="box">

        <div class="head">

            <h2>

                <?= $edit ? 'Edit' : 'Tambah' ?>

                Peminjam

            </h2>

        </div>


        <form method="post">

            <input
                type="hidden"
                name="id"
                value="<?= $edit['id_peminjam'] ?? 0 ?>"
            >


            <div class="formgrid">


                <div class="field">

                    <label>
                        Nama
                    </label>

                    <input
                        name="nama"
                        required
                        value="<?= e($edit['nama_peminjam'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Kelas / Bagian
                    </label>

                    <input
                        name="kelas"
                        value="<?= e($edit['kelas'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        No. Telepon
                    </label>

                    <input
                        name="telp"
                        value="<?= e($edit['no_telp'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Alamat
                    </label>

                    <input
                        name="alamat"
                        value="<?= e($edit['alamat'] ?? '') ?>"
                    >

                </div>


            </div>


            <button
                class="btn"
                name="save"
            >

                Simpan

            </button>

        </form>

    </div>


    <!-- TABEL PEMINJAM -->

    <div class="box">

        <div class="head">

            <h2>
                Daftar Peminjam
            </h2>

        </div>


        <table>

            <tr>

                <th>No</th>

                <th>Nama</th>

                <th>Kelas</th>

                <th>Telepon</th>

                <th>Alamat</th>

                <th>Aksi</th>

            </tr>

<?php

    $q = $koneksi->query("
        SELECT *
        FROM peminjam
        ORDER BY id_peminjam DESC
    ");


    while ($r = $q->fetch_assoc()):

?>

            <tr>

                <td>
                    <?= $r['id_peminjam'] ?>
                </td>

                <td>
                    <?= e($r['nama_peminjam']) ?>
                </td>

                <td>
                    <?= e($r['kelas']) ?>
                </td>

                <td>
                    <?= e($r['no_telp']) ?>
                </td>

                <td>
                    <?= e($r['alamat']) ?>
                </td>

                <td class="actions">


                    <a
                        class="btn"
                        href="?page=peminjam&edit=<?= $r['id_peminjam'] ?>"
                    >

                        Edit

                    </a>


                    <a
                        class="btn red"
                        href="?page=peminjam&del=<?= $r['id_peminjam'] ?>"
                        onclick="return confirm('Hapus peminjam?')"
                    >

                        Hapus

                    </a>


                </td>

            </tr>

<?php

    endwhile;

?>

        </table>

    </div>


<?php
}


// =====================================================
// PEMINJAMAN
// =====================================================

elseif ($page === 'pinjam') {

    $pm = $koneksi->query("
        SELECT *
        FROM peminjam
        ORDER BY nama_peminjam
    ");


    $pt = $koneksi->query("
        SELECT *
        FROM petugas
        ORDER BY nama_petugas
    ");


    $br = $koneksi->query("
        SELECT *
        FROM barang
        WHERE jumlah > 0
        ORDER BY nama_barang
    ");

?>


    <?php if ($err): ?>

        <div class="alert warn">
            <?= e($err) ?>
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['ok'])): ?>

        <div class="alert">

            Peminjaman berhasil disimpan.

        </div>

    <?php endif; ?>


    <!-- FORM PEMINJAMAN -->

    <div class="box">

        <div class="head">

            <h2>
                Form Peminjaman
            </h2>

        </div>


        <form method="post">


            <div class="formgrid">


                <!-- PEMINJAM -->

                <div class="field">

                    <label>
                        Peminjam
                    </label>

                    <select
                        name="peminjam"
                        required
                    >

                        <option value="">
                            -- pilih --
                        </option>

<?php

    while ($r = $pm->fetch_assoc()):

?>

                        <option
                            value="<?= $r['id_peminjam'] ?>"
                        >

                            <?= e($r['nama_peminjam']) ?>

                        </option>

<?php

    endwhile;

?>

                    </select>

                </div>


                <!-- PETUGAS -->

                <div class="field">

                    <label>
                        Petugas
                    </label>

                    <select
                        name="petugas"
                        required
                    >

                        <option value="">
                            -- pilih --
                        </option>

<?php

    while ($r = $pt->fetch_assoc()):

?>

                        <option
                            value="<?= $r['id_petugas'] ?>"
                        >

                            <?= e($r['nama_petugas']) ?>

                        </option>

<?php

    endwhile;

?>

                    </select>

                </div>


                <!-- TANGGAL PINJAM -->

                <div class="field">

                    <label>
                        Tanggal Pinjam
                    </label>

                    <input
                        type="date"
                        name="tgl"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- TANGGAL KEMBALI -->

                <div class="field">

                    <label>
                        Tanggal Kembali
                    </label>

                    <input
                        type="date"
                        name="tempo"
                        required
                    >

                </div>


                <!-- BARANG -->

                <div class="field full">

                    <label>
                        Barang
                    </label>


                    <div id="items">


                        <div class="formgrid rowitem">


                            <select
                                name="barang[]"
                                required
                            >

                                <option value="">
                                    -- pilih barang --
                                </option>

<?php

    while ($r = $br->fetch_assoc()):

?>

                                <option
                                    value="<?= $r['id_barang'] ?>"
                                >

                                    <?= e($r['nama_barang']) ?>

                                    (stok <?= $r['jumlah'] ?>)

                                </option>

<?php

    endwhile;

?>

                            </select>


                            <input
                                type="number"
                                min="1"
                                name="qty[]"
                                value="1"
                                required
                            >


                        </div>


                    </div>


                    <br>


                    <button
                        type="button"
                        class="btn gray"
                        onclick="addItem()"
                    >

                        + Tambah Barang

                    </button>


                </div>


            </div>


            <button
                class="btn"
                name="pinjam"
            >

                Simpan Peminjaman

            </button>


        </form>

    </div>


    <!-- JAVASCRIPT TAMBAH BARANG -->

    <script>

        function addItem()
        {
            let item = document
                .querySelector('.rowitem')
                .cloneNode(true);

            item
                .querySelector('select')
                .value = '';

            item
                .querySelector('input')
                .value = 1;

            document
                .getElementById('items')
                .appendChild(item);
        }

    </script>


    <!-- RIWAYAT PEMINJAMAN -->

    <div class="box">

        <div class="head">

            <h2>
                Riwayat
            </h2>

        </div>


        <table>

            <tr>

                <th>No</th>

                <th>Peminjam</th>

                <th>Barang</th>

                <th>Pinjam</th>

                <th>Tempo</th>

                <th>Status</th>

            </tr>

<?php

    $q = $koneksi->query("
        SELECT
            p.*,
            pm.nama_peminjam,

            GROUP_CONCAT(
                CONCAT(
                    b.nama_barang,
                    ' x',
                    d.jumlah
                )
                SEPARATOR ', '
            ) AS br

        FROM peminjaman p

        JOIN peminjam pm
            ON pm.id_peminjam = p.id_peminjam

        JOIN detail_peminjaman d
            ON d.id_peminjaman = p.id_peminjaman

        JOIN barang b
            ON b.id_barang = d.id_barang

        GROUP BY p.id_peminjaman

        ORDER BY p.id_peminjaman DESC
    ");


    while ($r = $q->fetch_assoc()):

?>

            <tr>

                <td>
                    #<?= $r['id_peminjaman'] ?>
                </td>

                <td>
                    <?= e($r['nama_peminjam']) ?>
                </td>

                <td>
                    <?= e($r['br']) ?>
                </td>

                <td>
                    <?= $r['tanggal_pinjam'] ?>
                </td>

                <td>
                    <?= $r['tanggal_kembali'] ?>
                </td>

                <td>

                    <span class="badge">

                        <?= $r['status'] ?>

                    </span>

                </td>

            </tr>

<?php

    endwhile;

?>

        </table>

    </div>


<?php
}


// =====================================================
// PENGEMBALIAN
// =====================================================

elseif ($page === 'kembali') {

?>

    <?php if ($msg): ?>

        <div class="alert">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>


    <?php if ($err): ?>

        <div class="alert warn">

            <?= e($err) ?>

        </div>

    <?php endif; ?>


    <div class="box">

        <div class="head">

            <h2>
                Barang Sedang Dipinjam
            </h2>

        </div>


        <table>

            <tr>

                <th>No</th>

                <th>Peminjam</th>

                <th>Barang</th>

                <th>Pinjam</th>

                <th>Jatuh Tempo</th>

                <th>Aksi</th>

            </tr>

<?php

    $q = $koneksi->query("
        SELECT
            p.*,
            pm.nama_peminjam,

            GROUP_CONCAT(
                CONCAT(
                    b.nama_barang,
                    ' x',
                    d.jumlah
                )
                SEPARATOR ', '
            ) AS br

        FROM peminjaman p

        JOIN peminjam pm
            ON pm.id_peminjam = p.id_peminjam

        JOIN detail_peminjaman d
            ON d.id_peminjaman = p.id_peminjaman

        JOIN barang b
            ON b.id_barang = d.id_barang

        WHERE p.status = 'Dipinjam'

        GROUP BY p.id_peminjaman

        ORDER BY p.tanggal_kembali
    ");


    while ($r = $q->fetch_assoc()):

?>

            <tr>

                <td>
                    #<?= $r['id_peminjaman'] ?>
                </td>

                <td>
                    <?= e($r['nama_peminjam']) ?>
                </td>

                <td>
                    <?= e($r['br']) ?>
                </td>

                <td>
                    <?= $r['tanggal_pinjam'] ?>
                </td>

                <td>
                    <?= $r['tanggal_kembali'] ?>
                </td>

                <td>


                    <form method="post">

                        <input
                            type="hidden"
                            name="id"
                            value="<?= $r['id_peminjaman'] ?>"
                        >


                        <button
                            class="btn"
                            name="kembali"
                            onclick="return confirm('Konfirmasi pengembalian?')"
                        >

                            Kembalikan

                        </button>

                    </form>


                </td>

            </tr>

<?php

    endwhile;

?>

        </table>

    </div>


<?php
}


// =====================================================
// LAPORAN
// =====================================================

elseif ($page === 'laporan') {

?>

    <div class="box">

        <div class="head">

            <h2>
                Laporan Peminjaman
            </h2>


            <button
                class="btn"
                onclick="window.print()"
            >

                🖨 Cetak

            </button>

        </div>


        <table>

            <tr>

                <th>No</th>

                <th>Peminjam</th>

                <th>Barang</th>

                <th>Pinjam</th>

                <th>Tempo</th>

                <th>Status</th>

            </tr>

<?php

    $q = $koneksi->query("
        SELECT
            p.*,
            pm.nama_peminjam,

            GROUP_CONCAT(
                CONCAT(
                    b.nama_barang,
                    ' x',
                    d.jumlah
                )
                SEPARATOR ', '
            ) AS br

        FROM peminjaman p

        JOIN peminjam pm
            ON pm.id_peminjam = p.id_peminjam

        JOIN detail_peminjaman d
            ON d.id_peminjaman = p.id_peminjaman

        JOIN barang b
            ON b.id_barang = d.id_barang

        GROUP BY p.id_peminjaman

        ORDER BY p.id_peminjaman DESC
    ");


    while ($r = $q->fetch_assoc()):

?>

            <tr>

                <td>
                    #<?= $r['id_peminjaman'] ?>
                </td>

                <td>
                    <?= e($r['nama_peminjam']) ?>
                </td>

                <td>
                    <?= e($r['br']) ?>
                </td>

                <td>
                    <?= $r['tanggal_pinjam'] ?>
                </td>

                <td>
                    <?= $r['tanggal_kembali'] ?>
                </td>

                <td>

                    <?= e($r['status']) ?>

                </td>

            </tr>

<?php

    endwhile;

?>

        </table>

    </div>

<?php
}


// =====================================================
// FOOTER
// =====================================================

foot();

?>