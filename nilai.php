<?php
include 'header.php';

$is_admin = ($_SESSION['role'] === 'admin');
$id_saya  = (int)$_SESSION['id_user'];
$admin_flag = $is_admin ? 1 : 0;

if (isset($_POST['simpan_nilai'])) {
    csrf_cek();

    $id_siswa = (int)($_POST['id_siswa'] ?? 0);
    $id_mapel = (int)($_POST['id_mapel'] ?? 0);
    $galat = '';

    // Validasi angka 0 - 100 di sisi server
    $skor = [];
    foreach (['nilai_tugas', 'nilai_praktik', 'nilai_ujian'] as $kolom) {
        $v = filter_var($_POST[$kolom] ?? '', FILTER_VALIDATE_FLOAT);
        if ($v === false || $v < 0 || $v > 100) {
            $galat = 'Semua nilai harus berupa angka antara 0 sampai 100.';
            break;
        }
        $skor[$kolom] = round($v, 2);
    }

    if ($galat === '' && !db_row($koneksi, "SELECT id_user FROM users WHERE id_user = ? AND role = 'siswa'", "i", $id_siswa)) {
        $galat = 'Siswa tidak ditemukan.';
    }
    // Guru hanya boleh mengisi mapel yang ia ampu (admin bebas)
    if ($galat === '' && !db_row($koneksi, "SELECT id_mapel FROM mapel WHERE id_mapel = ? AND (? = 1 OR id_guru = ?)", "iii", $id_mapel, $admin_flag, $id_saya)) {
        $galat = 'Anda tidak berhak mengisi nilai untuk mata pelajaran tersebut.';
    }

    if ($galat !== '') {
        set_flash('danger', $galat);
    } else {
        $nilai_akhir = round(
            $skor['nilai_tugas'] * BOBOT_TUGAS +
            $skor['nilai_praktik'] * BOBOT_PRAKTIK +
            $skor['nilai_ujian'] * BOBOT_UJIAN, 2);

        $ok = db_query($koneksi,
            "INSERT INTO nilai (id_siswa, id_mapel, nilai_tugas, nilai_praktik, nilai_ujian, nilai_akhir)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nilai_tugas = VALUES(nilai_tugas), nilai_praktik = VALUES(nilai_praktik),
                                     nilai_ujian = VALUES(nilai_ujian), nilai_akhir = VALUES(nilai_akhir)",
            "iidddd", $id_siswa, $id_mapel, $skor['nilai_tugas'], $skor['nilai_praktik'], $skor['nilai_ujian'], $nilai_akhir);

        set_flash($ok ? 'success' : 'danger',
                  $ok ? 'Data nilai berhasil disimpan! Nilai akhir: ' . number_format($nilai_akhir, 2) . ' (' . predikat($nilai_akhir) . ')'
                      : 'Gagal menyimpan nilai.');
    }
    redirect('nilai.php');
}

// Daftar siswa
$siswa = db_query($koneksi, "SELECT id_user, nama_lengkap, kelas FROM users WHERE role = 'siswa' ORDER BY kelas, nama_lengkap");

// Daftar mapel: admin semua, guru hanya yang diampu
if ($is_admin) {
    $mapel = db_query($koneksi, "SELECT id_mapel, nama_mapel FROM mapel ORDER BY nama_mapel");
} else {
    $mapel = db_query($koneksi, "SELECT id_mapel, nama_mapel FROM mapel WHERE id_guru = ? ORDER BY nama_mapel", "i", $id_saya);
}
$jumlah_mapel = mysqli_num_rows($mapel);

// Isi otomatis form bila dibuka dari tombol "Edit" di rekap (?siswa=..&mapel=..)
$pre_siswa = (int)($_GET['siswa'] ?? 0);
$pre_mapel = (int)($_GET['mapel'] ?? 0);
$pre = null;
if ($pre_siswa > 0 && $pre_mapel > 0) {
    $pre = db_row($koneksi, "SELECT * FROM nilai WHERE id_siswa = ? AND id_mapel = ?", "ii", $pre_siswa, $pre_mapel);
}
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header text-white" style="background-color: var(--color-primary);">
        <h5 class="mb-0">Form Input / Edit Nilai Siswa</h5>
    </div>
    <div class="card-body">
        <?php if (!$is_admin && $jumlah_mapel === 0): ?>
            <div class="alert alert-warning mb-0">Anda belum ditetapkan sebagai pengampu mata pelajaran apa pun. Hubungi admin untuk mengatur mapel Anda.</div>
        <?php else: ?>
        <form method="POST" class="row g-3">
            <?= csrf_field(); ?>
            <div class="col-md-6">
                <label class="form-label fw-bold">Pilih Siswa</label>
                <select name="id_siswa" class="form-select" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php while ($row = mysqli_fetch_assoc($siswa)): ?>
                        <option value="<?= (int)$row['id_user']; ?>" <?= $pre_siswa === (int)$row['id_user'] ? 'selected' : ''; ?>>
                            <?= e($row['nama_lengkap']); ?> (<?= e($row['kelas']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Mata Pelajaran</label>
                <select name="id_mapel" class="form-select" required>
                    <option value="">-- Pilih Mapel --</option>
                    <?php while ($row = mysqli_fetch_assoc($mapel)): ?>
                        <option value="<?= (int)$row['id_mapel']; ?>" <?= $pre_mapel === (int)$row['id_mapel'] ? 'selected' : ''; ?>>
                            <?= e($row['nama_mapel']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nilai Tugas (30%)</label>
                <input type="number" step="0.01" min="0" max="100" name="nilai_tugas" class="form-control" value="<?= $pre ? e($pre['nilai_tugas']) : ''; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nilai Praktik (30%)</label>
                <input type="number" step="0.01" min="0" max="100" name="nilai_praktik" class="form-control" value="<?= $pre ? e($pre['nilai_praktik']) : ''; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nilai Ujian (40%)</label>
                <input type="number" step="0.01" min="0" max="100" name="nilai_ujian" class="form-control" value="<?= $pre ? e($pre['nilai_ujian']) : ''; ?>" required>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center">
                <small class="text-muted">Jika siswa dan mapel sudah punya nilai, nilai lama akan diperbarui.</small>
                <button type="submit" name="simpan_nilai" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Nilai</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
