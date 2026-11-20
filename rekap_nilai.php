<?php
include 'header.php';

$is_admin   = ($_SESSION['role'] === 'admin');
$id_saya    = (int)$_SESSION['id_user'];
$admin_flag = $is_admin ? 1 : 0;

// Hapus satu nilai (POST + CSRF). Guru hanya boleh menghapus nilai mapel yang ia ampu.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $id_nilai = (int)($_POST['id_nilai'] ?? 0);
        $ok = db_query($koneksi,
            "DELETE n FROM nilai n JOIN mapel m ON n.id_mapel = m.id_mapel WHERE n.id_nilai = ? AND (? = 1 OR m.id_guru = ?)",
            "iii", $id_nilai, $admin_flag, $id_saya);
        set_flash($ok ? 'success' : 'danger', $ok ? 'Data nilai dihapus.' : 'Gagal menghapus nilai.');
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('rekap_nilai.php' . ($qs !== '' ? '?' . $qs : ''));
}

// Filter
$f_kelas = trim($_GET['kelas'] ?? '');
$f_mapel = (int)($_GET['mapel'] ?? 0);
$cari    = trim($_GET['cari'] ?? '');

$sql = "SELECT n.*, u.nama_lengkap, u.nis_nip, u.kelas, m.nama_mapel
        FROM nilai n
        JOIN users u ON n.id_siswa = u.id_user
        JOIN mapel m ON n.id_mapel = m.id_mapel
        WHERE 1 = 1";
$tipe = '';
$par  = [];
if (!$is_admin)        { $sql .= " AND m.id_guru = ?"; $tipe .= 'i'; $par[] = $id_saya; }
if ($f_kelas !== '')   { $sql .= " AND u.kelas = ?";   $tipe .= 's'; $par[] = $f_kelas; }
if ($f_mapel > 0)      { $sql .= " AND n.id_mapel = ?"; $tipe .= 'i'; $par[] = $f_mapel; }
if ($cari !== '')      { $sql .= " AND (u.nama_lengkap LIKE ? OR u.nis_nip LIKE ?)"; $tipe .= 'ss'; $like = '%' . $cari . '%'; $par[] = $like; $par[] = $like; }
$sql .= " ORDER BY u.kelas, u.nama_lengkap, m.nama_mapel";

$hasil = db_query($koneksi, $sql, $tipe, ...$par);
$baris = [];
$jumlah = 0; $total = 0;
while ($r = mysqli_fetch_assoc($hasil)) { $baris[] = $r; $jumlah++; $total += (float)$r['nilai_akhir']; }
$rata = $jumlah > 0 ? $total / $jumlah : 0;

// Dropdown filter
$q_kelas = db_query($koneksi, "SELECT DISTINCT kelas FROM users WHERE role = 'siswa' AND kelas IS NOT NULL ORDER BY kelas");
if ($is_admin) {
    $q_mapel = db_query($koneksi, "SELECT id_mapel, nama_mapel FROM mapel ORDER BY nama_mapel");
} else {
    $q_mapel = db_query($koneksi, "SELECT id_mapel, nama_mapel FROM mapel WHERE id_guru = ? ORDER BY nama_mapel", "i", $id_saya);
}
$qs_sekarang = $_SERVER['QUERY_STRING'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Rekapitulasi Nilai Siswa</h3>
    <button onclick="window.print()" class="btn btn-outline-primary no-print"><i class="bi bi-printer me-1"></i> Cetak / Print</button>
</div>

<!-- Kop laporan (hanya tampil saat dicetak) -->
<div class="d-none d-print-block mb-3">
    <p class="mb-0">Kelas: <strong><?= $f_kelas !== '' ? e($f_kelas) : 'Semua Kelas'; ?></strong> &nbsp;|&nbsp; Dicetak: <?= date('d-m-Y'); ?> oleh <?= e($_SESSION['nama_lengkap']); ?></p>
</div>

<!-- Form Filter -->
<div class="card border-0 shadow-sm p-3 mb-3 bg-white no-print">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Kelas</label>
            <select name="kelas" class="form-select">
                <option value="">-- Semua Kelas --</option>
                <?php while ($k = mysqli_fetch_assoc($q_kelas)): ?>
                    <option value="<?= e($k['kelas']); ?>" <?= $f_kelas === $k['kelas'] ? 'selected' : ''; ?>><?= e($k['kelas']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Mata Pelajaran</label>
            <select name="mapel" class="form-select">
                <option value="0">-- Semua Mapel --</option>
                <?php while ($m = mysqli_fetch_assoc($q_mapel)): ?>
                    <option value="<?= (int)$m['id_mapel']; ?>" <?= $f_mapel === (int)$m['id_mapel'] ? 'selected' : ''; ?>><?= e($m['nama_mapel']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Cari nama / NIS</label>
            <input type="text" name="cari" class="form-control" value="<?= e($cari); ?>" placeholder="Ketik nama atau NIS">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($f_kelas !== '' || $f_mapel > 0 || $cari !== ''): ?>
                <a href="rekap_nilai.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Tugas</th>
                <th>Praktik</th>
                <th>Ujian</th>
                <th>Nilai Akhir</th>
                <th>Predikat</th>
                <th class="no-print" width="15%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($jumlah > 0): $no = 1; foreach ($baris as $row): $na = (float)$row['nilai_akhir']; ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= e($row['nis_nip']); ?></td>
                <td><?= e($row['nama_lengkap']); ?></td>
                <td><?= e($row['kelas']); ?></td>
                <td><?= e($row['nama_mapel']); ?></td>
                <td><?= e($row['nilai_tugas']); ?></td>
                <td><?= e($row['nilai_praktik']); ?></td>
                <td><?= e($row['nilai_ujian']); ?></td>
                <td class="fw-bold" style="color: var(--color-secondary);"><?= number_format($na, 2); ?></td>
                <td><span class="badge bg-info text-dark"><?= predikat($na); ?></span></td>
                <td class="no-print">
                    <a href="nilai.php?siswa=<?= (int)$row['id_siswa']; ?>&mapel=<?= (int)$row['id_mapel']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="rekap_nilai.php<?= $qs_sekarang !== '' ? '?' . e($qs_sekarang) : ''; ?>" class="d-inline" onsubmit="return confirm('Hapus nilai ini?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id_nilai" value="<?= (int)$row['id_nilai']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light fw-bold">
                <td colspan="8" class="text-end">Rata-rata nilai akhir (<?= $jumlah; ?> data)</td>
                <td><?= number_format($rata, 2); ?></td>
                <td><?= predikat($rata); ?></td>
                <td class="no-print"></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="11" class="text-center text-muted py-3">Tidak ada data nilai yang sesuai filter.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
