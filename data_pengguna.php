<?php
include 'header.php';

$id_saya = (int)$_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $id = (int)($_POST['id_user'] ?? 0);
        if ($id === $id_saya) {
            set_flash('danger', 'Anda tidak dapat menghapus akun yang sedang digunakan!');
        } else {
            $ok = db_query($koneksi, "DELETE FROM users WHERE id_user = ? AND (? = 1 OR role <> 'admin')", "ii", $id, ($_SESSION['role'] === 'admin' ? 1 : 0));
            set_flash($ok ? 'success' : 'danger', $ok ? 'Pengguna berhasil dihapus!' : 'Gagal menghapus pengguna.');
        }
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('data_pengguna.php' . ($qs !== '' ? '?' . $qs : ''));
}

$f_role = $_GET['role'] ?? '';
if (in_array($f_role, ['admin', 'guru', 'siswa'], true)) {
    $query = db_query($koneksi, "SELECT * FROM users WHERE role = ? ORDER BY role, nama_lengkap ASC", "s", $f_role);
} else {
    $f_role = '';
    $query = db_query($koneksi, "SELECT * FROM users ORDER BY role, nama_lengkap ASC");
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Daftar Seluruh Pengguna Sistem</h3>
    <form method="GET" class="d-flex gap-2">
        <select name="role" class="form-select" onchange="this.form.submit()">
            <option value="">Semua Role</option>
            <option value="admin" <?= $f_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="guru"  <?= $f_role === 'guru'  ? 'selected' : ''; ?>>Guru</option>
            <option value="siswa" <?= $f_role === 'siswa' ? 'selected' : ''; ?>>Siswa</option>
        </select>
    </form>
</div>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th>No</th>
                <th>Username</th>
                <th>Nama Lengkap</th>
                <th>NIS / NIP</th>
                <th>Kelas</th>
                <th>Role / Hak Akses</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($query)): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= e($row['username']); ?></td>
                <td><?= e($row['nama_lengkap']); ?></td>
                <td><?= e($row['nis_nip'] ?? '-'); ?></td>
                <td><?= e($row['kelas'] ?? '-'); ?></td>
                <td>
                    <span class="badge <?= $row['role'] === 'admin' ? 'bg-danger' : ($row['role'] === 'guru' ? 'bg-success' : 'bg-primary'); ?>">
                        <?= e(ucfirst($row['role'])); ?>
                    </span>
                </td>
                <td>
                    <?php if ((int)$row['id_user'] !== $id_saya && ($_SESSION['role'] === 'admin' || $row['role'] !== 'admin')): ?>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Hapus pengguna ini? Jika siswa, seluruh nilainya ikut terhapus.');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id_user" value="<?= (int)$row['id_user']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted small"><?= (int)$row['id_user'] === $id_saya ? 'Akun Anda' : 'Dilindungi'; ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
