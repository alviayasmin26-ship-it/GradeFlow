<?php
include 'header.php';

$id_saya = (int)$_SESSION['id_user'];

$hasil = db_query($koneksi,
    "SELECT n.nilai_tugas, n.nilai_praktik, n.nilai_ujian, n.nilai_akhir, m.nama_mapel, g.nama_lengkap AS nama_guru
     FROM nilai n
     JOIN mapel m ON n.id_mapel = m.id_mapel
     LEFT JOIN users g ON m.id_guru = g.id_user
     WHERE n.id_siswa = ?
     ORDER BY m.nama_mapel", "i", $id_saya);

$baris = []; $total = 0;
while ($r = mysqli_fetch_assoc($hasil)) { $baris[] = $r; $total += (float)$r['nilai_akhir']; }
$rata = count($baris) > 0 ? $total / count($baris) : 0;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Nilai Saya</h3>
    <button onclick="window.print()" class="btn btn-outline-primary no-print"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th>No</th>
                <th>Mata Pelajaran</th>
                <th>Guru</th>
                <th>Tugas</th>
                <th>Praktik</th>
                <th>Ujian</th>
                <th>Nilai Akhir</th>
                <th>Predikat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($baris) > 0): $no = 1; foreach ($baris as $row): $na = (float)$row['nilai_akhir']; ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= e($row['nama_mapel']); ?></td>
                <td><?= e($row['nama_guru'] ?? '-'); ?></td>
                <td><?= e($row['nilai_tugas']); ?></td>
                <td><?= e($row['nilai_praktik']); ?></td>
                <td><?= e($row['nilai_ujian']); ?></td>
                <td class="fw-bold" style="color: var(--color-secondary);"><?= number_format($na, 2); ?></td>
                <td><span class="badge bg-info text-dark"><?= predikat($na); ?></span></td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light fw-bold">
                <td colspan="6" class="text-end">Rata-rata</td>
                <td><?= number_format($rata, 2); ?></td>
                <td><?= predikat($rata); ?></td>
            </tr>
            <?php else: ?>
            <tr><td colspan="8" class="text-center text-muted py-3">Belum ada nilai yang diinput untuk Anda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
