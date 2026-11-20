<?php
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id_mapel'] ?? 0);

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nama    = trim($_POST['nama_mapel'] ?? '');
        $id_guru = (int)($_POST['id_guru'] ?? 0);
        $galat   = '';

        if ($nama === '') {
            $galat = 'Nama mata pelajaran wajib diisi.';
        } elseif ($id_guru > 0 && !db_row($koneksi, "SELECT id_user FROM users WHERE id_user = ? AND role = 'guru'", "i", $id_guru)) {
            $galat = 'Guru pengampu tidak valid.';
        }
        $id_guru = $id_guru > 0 ? $id_guru : null; // null = belum ada pengampu

        if ($galat !== '') {
            set_flash('danger', $galat);
        } elseif ($aksi === 'tambah') {
            $ok = db_query($koneksi, "INSERT INTO mapel (nama_mapel, id_guru) VALUES (?, ?)", "si", $nama, $id_guru);
            if ($ok) set_flash('success', 'Mata pelajaran berhasil ditambahkan!');
            elseif ($db_errno == 1062) set_flash('danger', 'Nama mata pelajaran sudah ada.');
            else set_flash('danger', 'Gagal menambahkan mapel.');
        } else {
            $ok = db_query($koneksi, "UPDATE mapel SET nama_mapel = ?, id_guru = ? WHERE id_mapel = ?", "sii", $nama, $id_guru, $id);
            if ($ok) set_flash('success', 'Mata pelajaran berhasil diperbarui!');
            elseif ($db_errno == 1062) set_flash('danger', 'Nama mata pelajaran sudah ada.');
            else set_flash('danger', 'Gagal memperbarui mapel.');
        }
    } elseif ($aksi === 'hapus') {
        $ok = db_query($koneksi, "DELETE FROM mapel WHERE id_mapel = ?", "i", $id);
        set_flash($ok ? 'success' : 'danger', $ok ? 'Mata pelajaran dan seluruh nilainya berhasil dihapus!' : 'Gagal menghapus mapel.');
    }
    redirect('data_mapel.php');
}

$mapel = db_query($koneksi,
    "SELECT m.id_mapel, m.nama_mapel, m.id_guru, u.nama_lengkap AS nama_guru, COUNT(n.id_nilai) AS jml_nilai
     FROM mapel m
     LEFT JOIN users u ON m.id_guru = u.id_user
     LEFT JOIN nilai n ON n.id_mapel = m.id_mapel
     GROUP BY m.id_mapel, m.nama_mapel, m.id_guru, u.nama_lengkap
     ORDER BY m.nama_mapel ASC");
$guru = [];
$q_guru = db_query($koneksi, "SELECT id_user, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap");
while ($g = mysqli_fetch_assoc($q_guru)) $guru[] = $g;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold" style="color: var(--color-primary);">Manajemen Mata Pelajaran</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahMapel">
        <i class="bi bi-journal-plus me-1"></i> Tambah Mapel
    </button>
</div>

<div class="table-responsive bg-white p-3 rounded shadow-sm">
    <table class="table table-bordered table-striped align-middle">
        <thead class="text-white" style="background-color: var(--color-primary);">
            <tr>
                <th width="6%">No</th>
                <th>Nama Mata Pelajaran</th>
                <th>Guru Pengampu</th>
                <th width="12%">Jumlah Nilai</th>
                <th width="18%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($mapel)): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= e($row['nama_mapel']); ?></td>
                <td><?= $row['nama_guru'] ? e($row['nama_guru']) : '<span class="text-muted">Belum ada pengampu</span>'; ?></td>
                <td><?= (int)$row['jml_nilai']; ?></td>
                <td>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditMapel"
                        data-id="<?= (int)$row['id_mapel']; ?>"
                        data-nama="<?= e($row['nama_mapel']); ?>"
                        data-guru="<?= (int)$row['id_guru']; ?>">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <form method="POST" class="d-inline"
                          onsubmit="return confirm('Hapus mapel ini? <?= (int)$row['jml_nilai']; ?> data nilai milik mapel ini juga akan ikut terhapus permanen.');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id_mapel" value="<?= (int)$row['id_mapel']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambahMapel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header text-white" style="background-color: var(--color-primary);">
                    <h5 class="modal-title">Tambah Mata Pelajaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran</label>
                        <input type="text" name="nama_mapel" class="form-control" placeholder="Contoh: Basis Data" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Guru Pengampu</label>
                        <select name="id_guru" class="form-select">
                            <option value="0">-- Belum ada pengampu --</option>
                            <?php foreach ($guru as $g): ?>
                                <option value="<?= (int)$g['id_user']; ?>"><?= e($g['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Mapel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEditMapel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_mapel" id="edit_id">
                <div class="modal-header text-white" style="background-color: var(--color-primary);">
                    <h5 class="modal-title">Edit Mata Pelajaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran</label>
                        <input type="text" name="nama_mapel" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Guru Pengampu</label>
                        <select name="id_guru" id="edit_guru" class="form-select">
                            <option value="0">-- Belum ada pengampu --</option>
                            <?php foreach ($guru as $g): ?>
                                <option value="<?= (int)$g['id_user']; ?>"><?= e($g['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
document.getElementById('modalEditMapel').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('edit_id').value   = b.dataset.id;
    document.getElementById('edit_nama').value = b.dataset.nama;
    document.getElementById('edit_guru').value = b.dataset.guru || '0';
});
</script>

<?php include 'footer.php'; ?>
