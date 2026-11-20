<?php 
include 'header.php'; 

// Proses Tambah Siswa
if (isset($_POST['tambah_siswa'])) {
    $nis_nip      = mysqli_real_escape_string($koneksi, $_POST['nis_nip']);
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $kelas        = mysqli_real_escape_string($koneksi, $_POST['kelas']);
    $username     = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password     = password_hash($_POST['password'], PASSWORD_DEFAULT); // Aman dengan hashing
    $role         = 'siswa';

    $query = "INSERT INTO users (nis_nip, nama_lengkap, kelas, username, password, role) 
              VALUES ('$nis_nip', '$nama_lengkap', '$kelas', '$username', '$password', '$role')";
    
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Siswa berhasil ditambahkan!'); window.location='data_siswa.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan siswa!');</script>";
    }
}

// Proses Hapus Siswa
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($koneksi, "DELETE FROM users WHERE id_user='$id' AND role='siswa'");
    echo "<script>alert('Siswa berhasil dihapus!'); window.location='data_siswa.php';</script>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Manajemen Data Siswa</h3>
    <!-- Tombol Trigger Modal Tambah Siswa -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
        <i class="bi bi-person-plus me-1"></i> Tambah Siswa
    </button>
</div>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama Lengkap</th>
                <th>Kelas</th>
                <th>Username</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $siswa = mysqli_query($koneksi, "SELECT * FROM users WHERE role='siswa' ORDER BY kelas, nama_lengkap ASC");
            while($row = mysqli_fetch_assoc($siswa)): 
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['nis_nip']); ?></td>
                <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                <td><?= htmlspecialchars($row['kelas']); ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td>
                    <a href="data_siswa.php?hapus=<?= $row['id_user']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus siswa ini?');">
                        <i class="bi bi-trash"></i> Hapus
                    </a>
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
                        <label class="form-label">Kelas</label>
                        <input type="text" name="kelas" class="form-control" placeholder="Contoh: XI RPL" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username Login</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_siswa" class="btn btn-primary">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>