<?php
include 'header.php';

$id_saya = (int)$_SESSION['id_user'];
$adalah_admin  = ($_SESSION['role'] === 'admin');
$boleh_role    = $adalah_admin ? ['guru', 'admin'] : ['guru'];
$role_dikelola = $adalah_admin ? "role <> 'siswa'" : "role = 'guru'"; // guru tidak boleh menyentuh akun admin

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id_user'] ?? 0);

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nip   = trim($_POST['nis_nip'] ?? '');
        $nama  = trim($_POST['nama_lengkap'] ?? '');
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $rolebaru = $_POST['role'] ?? '';
        $galat = '';

        if ($nip === '' || $nama === '') {
            $galat = 'NIP dan nama wajib diisi.';
        } elseif (!in_array($rolebaru, $boleh_role, true)) {
            $galat = 'Role tidak valid.';
        } elseif ($aksi === 'tambah' && ($uname === '' || strlen($pass) < 6)) {
            $galat = 'Username wajib diisi dan password minimal 6 karakter.';
        } elseif ($aksi === 'edit' && $pass !== '' && strlen($pass) < 6) {
            $galat = 'Password baru minimal 6 karakter.';
        }

        if ($galat !== '') {
            set_flash('danger', $galat);
        } elseif ($aksi === 'tambah') {
            $ok = db_query($koneksi,
                "INSERT INTO users (nis_nip, nama_lengkap, username, password, role) VALUES (?, ?, ?, ?, ?)",
                "sssss", $nip, $nama, $uname, password_hash($pass, PASSWORD_DEFAULT), $rolebaru);
            if ($ok) set_flash('success', 'Pengguna berhasil ditambahkan!');
            elseif ($db_errno == 1062) set_flash('danger', 'Username sudah dipakai, gunakan username lain.');
            else set_flash('danger', 'Gagal menambahkan pengguna.');
        } else {
            // Admin tidak boleh menurunkan role dirinya sendiri
            $set  = "nis_nip = ?, nama_lengkap = ?";
            $tipe = "ss";
            $par  = [$nip, $nama];
            if ($id !== $id_saya) { $set .= ", role = ?"; $tipe .= "s"; $par[] = $rolebaru; }
            if ($pass !== '')     { $set .= ", password = ?"; $tipe .= "s"; $par[] = password_hash($pass, PASSWORD_DEFAULT); }
            $tipe .= "i"; $par[] = $id;
            $ok = db_query($koneksi, "UPDATE users SET $set WHERE id_user = ? AND $role_dikelola", $tipe, ...$par);
            set_flash($ok ? 'success' : 'danger', $ok ? 'Data pengguna berhasil diperbarui!' : 'Gagal memperbarui data.');
        }
    } elseif ($aksi === 'hapus') {
        if ($id === $id_saya) {
            set_flash('danger', 'Anda tidak dapat menghapus akun yang sedang digunakan!');
        } else {
            $ok = db_query($koneksi, "DELETE FROM users WHERE id_user = ? AND $role_dikelola", "i", $id);
            set_flash($ok ? 'success' : 'danger', $ok ? 'Pengguna berhasil dihapus! (mapel yang diampu menjadi tanpa pengampu)' : 'Gagal menghapus pengguna.');
        }
    }
    redirect('data_guru.php');
}

$users = db_query($koneksi, "SELECT * FROM users WHERE $role_dikelola ORDER BY role, nama_lengkap ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Manajemen Guru & Admin</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="bi bi-person-badge me-1"></i> Tambah Pengguna
    </button>
</div>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th>No</th>
                <th>NIP / ID</th>
                <th>Nama Lengkap</th>
                <th>Username</th>
                <th>Role (Hak Akses)</th>
                <th width="18%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($users)): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= e($row['nis_nip'] ?? '-'); ?></td>
                <td><?= e($row['nama_lengkap']); ?></td>
                <td><?= e($row['username']); ?></td>
                <td>
                    <span class="badge <?= $row['role'] === 'admin' ? 'bg-danger' : 'bg-success'; ?>"><?= e(ucfirst($row['role'])); ?></span>
                </td>
                <td>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditUser"
                        data-id="<?= (int)$row['id_user']; ?>"
                        data-nip="<?= e($row['nis_nip']); ?>"
                        data-nama="<?= e($row['nama_lengkap']); ?>"
                        data-username="<?= e($row['username']); ?>"
                        data-role="<?= e($row['role']); ?>"
                        data-self="<?= (int)$row['id_user'] === $id_saya ? '1' : '0'; ?>">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <?php if ((int)$row['id_user'] !== $id_saya): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pengguna ini? Mapel yang diampunya akan menjadi tanpa pengampu.');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id_user" value="<?= (int)$row['id_user']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                    </form>
                    <?php else: ?>
                        <span class="text-muted small">Akun Anda</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header text-white" style="background-color: var(--color-primary);">
                    <h5 class="modal-title">Tambah Guru / Admin Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NIP / Kode Pegawai</label>
                        <input type="text" name="nis_nip" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap & Gelar</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username Login</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <small class="text-muted">(minimal 6 karakter)</small></label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hak Akses (Role)</label>
                        <select name="role" class="form-select" required>
                            <option value="guru">Guru</option>
                            <?php if ($adalah_admin): ?><option value="admin">Admin</option><?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_user" id="edit_id">
                <div class="modal-header text-white" style="background-color: var(--color-primary);">
                    <h5 class="modal-title">Edit Guru / Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username (tidak dapat diubah)</label>
                        <input type="text" id="edit_username" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIP / Kode Pegawai</label>
                        <input type="text" name="nis_nip" id="edit_nip" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap & Gelar</label>
                        <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hak Akses (Role)</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="guru">Guru</option>
                            <?php if ($adalah_admin): ?><option value="admin">Admin</option><?php endif; ?>
                        </select>
                        <div class="form-text" id="edit_role_info" style="display:none;">Role akun Anda sendiri tidak dapat diubah.</div>
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
document.getElementById('modalEditUser').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('edit_id').value       = b.dataset.id;
    document.getElementById('edit_nip').value      = b.dataset.nip;
    document.getElementById('edit_nama').value     = b.dataset.nama;
    document.getElementById('edit_username').value = b.dataset.username;
    document.getElementById('edit_role').value     = b.dataset.role;
    var diriSendiri = b.dataset.self === '1';
    document.getElementById('edit_role').disabled = diriSendiri;
    document.getElementById('edit_role_info').style.display = diriSendiri ? 'block' : 'none';
});
</script>

<?php include 'footer.php'; ?>
