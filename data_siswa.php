<?php
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id_user'] ?? 0);

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nis   = trim($_POST['nis_nip'] ?? '');
        $nama  = trim($_POST['nama_lengkap'] ?? '');
        $kelas = rapikan_kelas($_POST['kelas'] ?? '');
        $jk    = $_POST['jenis_kelamin'] ?? '';
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $galat = '';

        if ($nis === '' || $nama === '' || $kelas === '') {
            $galat = 'NIS, nama, dan kelas wajib diisi.';
        } elseif (!in_array($jk, ['L', 'P'], true)) {
            $galat = 'Jenis kelamin tidak valid.';
        } elseif ($aksi === 'tambah' && ($uname === '' || strlen($pass) < 6)) {
            $galat = 'Username wajib diisi dan password minimal 6 karakter.';
        } elseif ($aksi === 'edit' && $pass !== '' && strlen($pass) < 6) {
            $galat = 'Password baru minimal 6 karakter.';
        } elseif (db_row($koneksi, "SELECT id_user FROM users WHERE nis_nip = ? AND role = 'siswa' AND id_user <> ?", "si", $nis, $id)) {
            $galat = 'NIS sudah dipakai siswa lain.';
        }

        if ($galat !== '') {
            set_flash('danger', $galat);
        } elseif ($aksi === 'tambah') {
            $ok = db_query($koneksi,
                "INSERT INTO users (nis_nip, nama_lengkap, kelas, jenis_kelamin, username, password, role) VALUES (?, ?, ?, ?, ?, ?, 'siswa')",
                "ssssss", $nis, $nama, $kelas, $jk, $uname, password_hash($pass, PASSWORD_DEFAULT));
            if ($ok) set_flash('success', 'Siswa berhasil ditambahkan!');
            elseif ($db_errno == 1062) set_flash('danger', 'Username sudah dipakai, gunakan username lain.');
            else set_flash('danger', 'Gagal menambahkan siswa.');
        } else {
            if ($pass !== '') {
                $ok = db_query($koneksi,
                    "UPDATE users SET nis_nip = ?, nama_lengkap = ?, kelas = ?, jenis_kelamin = ?, password = ? WHERE id_user = ? AND role = 'siswa'",
                    "sssssi", $nis, $nama, $kelas, $jk, password_hash($pass, PASSWORD_DEFAULT), $id);
            } else {
                $ok = db_query($koneksi,
                    "UPDATE users SET nis_nip = ?, nama_lengkap = ?, kelas = ?, jenis_kelamin = ? WHERE id_user = ? AND role = 'siswa'",
                    "ssssi", $nis, $nama, $kelas, $jk, $id);
            }
            set_flash($ok ? 'success' : 'danger', $ok ? 'Data siswa berhasil diperbarui!' : 'Gagal memperbarui data siswa.');
        }
    } elseif ($aksi === 'hapus') {
        $ok = db_query($koneksi, "DELETE FROM users WHERE id_user = ? AND role = 'siswa'", "i", $id);
        set_flash($ok ? 'success' : 'danger', $ok ? 'Siswa dan seluruh nilainya berhasil dihapus!' : 'Gagal menghapus siswa.');
    }
    redirect('data_siswa.php');
}

$siswa  = db_query($koneksi, "SELECT * FROM users WHERE role = 'siswa' ORDER BY kelas, nama_lengkap ASC");
$daftar_kelas = db_query($koneksi, "SELECT DISTINCT kelas FROM users WHERE role = 'siswa' AND kelas IS NOT NULL ORDER BY kelas");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Manajemen Data Siswa</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
        <i class="bi bi-person-plus me-1"></i> Tambah Siswa
    </button>
</div>

<datalist id="daftarKelas">
    <?php while ($k = mysqli_fetch_assoc($daftar_kelas)): ?>
        <option value="<?= e($k['kelas']); ?>">
    <?php endwhile; ?>
</datalist>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama Lengkap</th>
                <th>L/P</th>
                <th>Kelas</th>
                <th>Username</th>
                <th width="18%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($siswa)): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= e($row['nis_nip']); ?></td>
                <td><?= e($row['nama_lengkap']); ?></td>
                <td><?= e($row['jenis_kelamin'] ?? '-'); ?></td>
                <td><?= e($row['kelas']); ?></td>
                <td><?= e($row['username']); ?></td>
                <td>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditSiswa"
                        data-id="<?= (int)$row['id_user']; ?>"
                        data-nis="<?= e($row['nis_nip']); ?>"
                        data-nama="<?= e($row['nama_lengkap']); ?>"
                        data-kelas="<?= e($row['kelas']); ?>"
                        data-jk="<?= e($row['jenis_kelamin']); ?>"
                        data-username="<?= e($row['username']); ?>">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus siswa ini? SEMUA nilai milik siswa ini juga akan terhapus.');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id_user" value="<?= (int)$row['id_user']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Modal Tambah Siswa -->
<div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header text-white" style="background-color: var(--color-primary);">
                    <h5 class="modal-title">Tambah Siswa Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NIS (Nomor Induk Siswa)</label>
                        <input type="text" name="nis_nip" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kelas</label>
                        <input type="text" name="kelas" list="daftarKelas" class="form-control" placeholder="Contoh: XI RPL" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username Login</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <small class="text-muted">(minimal 6 karakter)</small></label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Siswa -->
<div class="modal fade" id="modalEditSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_user" id="edit_id">
                <div class="modal-header text-white" style="background-color: var(--color-primary);">
                    <h5 class="modal-title">Edit Data Siswa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username (tidak dapat diubah)</label>
                        <input type="text" id="edit_username" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIS</label>
                        <input type="text" name="nis_nip" id="edit_nis" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="edit_jk" class="form-select" required>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kelas</label>
                        <input type="text" name="kelas" id="edit_kelas" list="daftarKelas" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reset Password <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                        <input type="password" name="password" class="form-control" minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalEditSiswa').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('edit_id').value       = b.dataset.id;
    document.getElementById('edit_nis').value      = b.dataset.nis;
    document.getElementById('edit_nama').value     = b.dataset.nama;
    document.getElementById('edit_kelas').value    = b.dataset.kelas;
    document.getElementById('edit_jk').value       = b.dataset.jk || 'L';
    document.getElementById('edit_username').value = b.dataset.username;
});
</script>

<?php include 'footer.php'; ?>
