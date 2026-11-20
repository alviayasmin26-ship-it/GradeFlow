<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start(); // supaya header("Location") tetap bisa dipakai setelah ada output HTML

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}
include 'koneksi.php';

/* ================= Fungsi bantu ================= */
function e($teks) { return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8'); }

function redirect($url) { header("Location: $url"); exit(); }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_cek() {
    $kirim = $_POST['csrf'] ?? '';
    if (!is_string($kirim) || !hash_equals($_SESSION['csrf'] ?? '', $kirim)) {
        http_response_code(403);
        die('Permintaan tidak valid (token keamanan salah). Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

function set_flash($tipe, $pesan) { $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan]; }
function ambil_flash() { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// "xi  rpl " -> "XI RPL" (supaya penulisan kelas seragam)
function rapikan_kelas($k) { return strtoupper(trim(preg_replace('/\s+/', ' ', (string)$k))); }

// Bobot nilai akhir
const BOBOT_TUGAS   = 0.30;
const BOBOT_PRAKTIK = 0.30;
const BOBOT_UJIAN   = 0.40;

function predikat($na) {
    if ($na >= 85) return 'A';
    if ($na >= 75) return 'B';
    if ($na >= 60) return 'C';
    return 'D';
}

/* ====== Validasi user pada setiap halaman (role selalu diambil dari database) ====== */
$user_sesi = db_row($koneksi, "SELECT id_user, username, nama_lengkap, role FROM users WHERE id_user = ?", "i", (int)$_SESSION['id_user']);
if (!$user_sesi) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['role']         = $user_sesi['role'];
$_SESSION['nama_lengkap'] = $user_sesi['nama_lengkap'];

/* ====== Tabel izin akses (dibuat otomatis sekali per sesi) ====== */
if (empty($_SESSION['tabel_akses_ok'])) {
    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS akses_halaman (
        halaman   VARCHAR(50) NOT NULL,
        role      VARCHAR(20) NOT NULL,
        diizinkan TINYINT(1)  NOT NULL DEFAULT 0,
        PRIMARY KEY (halaman, role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $_SESSION['tabel_akses_ok'] = 1;
}

/* ====== Daftar halaman yang aksesnya bisa diatur admin (pemilik) ======
   peran   : role yang bisa diberi izin untuk halaman itu (admin selalu boleh)
   default : izin bawaan bila admin belum pernah mengaturnya */
function daftar_halaman() {
    return [
        'data_siswa.php'    => ['label' => 'Data Siswa',       'icon' => 'bi-people',            'ket' => 'Tambah, ubah, dan hapus akun siswa',        'peran' => ['guru'],  'default' => []],
        'data_mapel.php'    => ['label' => 'Mata Pelajaran',   'icon' => 'bi-journal-text',      'ket' => 'Kelola mapel dan guru pengampu',            'peran' => ['guru'],  'default' => []],
        'data_guru.php'     => ['label' => 'Data Guru',        'icon' => 'bi-person-badge',      'ket' => 'Kelola akun guru',                          'peran' => ['guru'],  'default' => []],
        'data_pengguna.php' => ['label' => 'Semua Pengguna',   'icon' => 'bi-person-lines-fill', 'ket' => 'Daftar seluruh akun dan hapus akun',        'peran' => ['guru'],  'default' => []],
        'nilai.php'         => ['label' => 'Input Nilai',      'icon' => 'bi-pencil-square',     'ket' => 'Input dan ubah nilai siswa',                'peran' => ['guru'],  'default' => ['guru']],
        'rekap_nilai.php'   => ['label' => 'Rekap Nilai',      'icon' => 'bi-journal-check',     'ket' => 'Rekap, filter, dan cetak nilai',            'peran' => ['guru'],  'default' => ['guru']],
        'nilai_saya.php'    => ['label' => 'Nilai Saya',       'icon' => 'bi-award',             'ket' => 'Siswa melihat nilainya sendiri',            'peran' => ['siswa'], 'default' => ['siswa'], 'admin_menu' => false],
    ];
}

$AKSES_MAP = [];
$q_akses = db_query($koneksi, "SELECT halaman, role, diizinkan FROM akses_halaman");
if ($q_akses instanceof mysqli_result) {
    while ($baris_akses = mysqli_fetch_assoc($q_akses)) {
        $AKSES_MAP[$baris_akses['halaman']][$baris_akses['role']] = (int)$baris_akses['diizinkan'];
    }
}

function boleh_akses($file, $role) {
    global $AKSES_MAP;
    if ($role === 'admin') return true;                       // pemilik selalu boleh
    $info = daftar_halaman();
    if (!isset($info[$file])) return true;                    // halaman tidak diatur lewat tabel
    if (!in_array($role, $info[$file]['peran'], true)) return false;
    if (isset($AKSES_MAP[$file][$role])) return $AKSES_MAP[$file][$role] === 1;
    return in_array($role, $info[$file]['default'], true);
}

/* ====== Penjagaan halaman ====== */
$file_ini     = basename($_SERVER['PHP_SELF']);
$info_halaman = daftar_halaman();
if (isset($info_halaman[$file_ini])) {
    $diizinkan = boleh_akses($file_ini, $_SESSION['role']);
} elseif (isset($wajib_role)) {
    $diizinkan = in_array($_SESSION['role'], $wajib_role, true);
} else {
    $diizinkan = true;
}
if (!$diizinkan) {
    set_flash('danger', 'Akses ditolak! Anda tidak memiliki hak untuk membuka halaman itu.');
    header("Location: dashboard.php");
    exit();
}

$halaman_aktif = basename($_SERVER['PHP_SELF']);
function aktif($file) { global $halaman_aktif; return $halaman_aktif === $file ? 'active' : ''; }
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeFlow - Sistem Manajemen Nilai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #1B365D;
            --color-secondary: #005A9C;
            --color-accent: #60C1E5;
            --color-light: #A0E0EF;
        }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background-color: var(--color-primary); min-height: 100vh; }
        .sidebar .nav-link { color: #ffffff; margin-bottom: 5px; border-radius: 5px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: var(--color-secondary); color: var(--color-light); }
        .card-custom { border-left: 5px solid var(--color-secondary); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        @media print {
            .sidebar, .no-print, .mobile-bar { display: none !important; }
            main { margin: 0 !important; width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
<!-- Tombol menu untuk layar kecil -->
<div class="mobile-bar d-md-none p-2" style="background-color: var(--color-primary);">
    <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false">
        <i class="bi bi-list"></i> Menu
    </button>
    <span class="text-white fw-bold ms-2">GradeFlow</span>
</div>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <h4 class="text-white text-center py-3 fw-bold border-bottom" style="letter-spacing: 1px;">Grade<span style="color: var(--color-accent);">Flow</span></h4>

            <ul class="nav flex-column mt-3">
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?= aktif('dashboard.php'); ?>"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>

                <?php foreach (daftar_halaman() as $file_menu => $h_menu): ?>
                    <?php if (boleh_akses($file_menu, $role) && ($role !== 'admin' || ($h_menu['admin_menu'] ?? true))): ?>
                    <li class="nav-item"><a href="<?= e($file_menu); ?>" class="nav-link <?= aktif($file_menu); ?>"><i class="bi <?= e($h_menu['icon']); ?> me-2"></i> <?= e($h_menu['label']); ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a href="kontrol_akses.php" class="nav-link <?= aktif('kontrol_akses.php'); ?>"><i class="bi bi-shield-lock me-2"></i> Kontrol Akses</a></li>
                <?php endif; ?>

                <li class="nav-item"><a href="profil.php" class="nav-link <?= aktif('profil.php'); ?>"><i class="bi bi-person-circle me-2"></i> Profil Saya</a></li>
                <li class="nav-item"><a href="pemilik.php" class="nav-link <?= aktif('pemilik.php'); ?>"><i class="bi bi-info-circle me-2"></i> Tentang Aplikasi</a></li>

                <li class="nav-item mt-4"><a href="logout.php" class="nav-link text-danger bg-light fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </nav>
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
<?php if ($flash = ambil_flash()): ?>
            <div class="alert alert-<?= e($flash['tipe']); ?> alert-dismissible fade show no-print" role="alert">
                <?= e($flash['pesan']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
<?php endif; ?>
