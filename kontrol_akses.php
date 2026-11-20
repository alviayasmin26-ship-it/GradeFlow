<?php
$wajib_role = ['admin']; // halaman ini selalu khusus admin (pemilik)
include 'header.php';

$daftar = daftar_halaman();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (($_POST['aksi'] ?? '') === 'reset') {
        $ok = db_query($koneksi, "DELETE FROM akses_halaman");
        set_flash($ok ? 'success' : 'danger', $ok ? 'Pengaturan akses dikembalikan ke bawaan.' : 'Gagal mengembalikan pengaturan.');
    } else {
        $ok = true;
        foreach ($daftar as $file => $h) {
            foreach ($h['peran'] as $r) {
                $nilai = isset($_POST['akses'][$file][$r]) ? 1 : 0;
                $sukses = db_query($koneksi,
                    "INSERT INTO akses_halaman (halaman, role, diizinkan) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE diizinkan = VALUES(diizinkan)",
                    "ssi", $file, $r, $nilai);
                if (!$sukses) $ok = false;
            }
        }
        set_flash($ok ? 'success' : 'danger', $ok ? 'Pengaturan akses berhasil disimpan. Langsung berlaku untuk semua pengguna.' : 'Sebagian pengaturan gagal disimpan.');
    }
    redirect('kontrol_akses.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-0" style="color: var(--color-primary);">Kontrol Akses Halaman</h3>
        <p class="text-muted mb-0">Tentukan role mana yang boleh membuka tiap halaman. Perubahan langsung berlaku, termasuk pada menu sidebar.</p>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Admin (pemilik) selalu punya akses penuh ke semua halaman. Dashboard, Profil Saya, dan Tentang selalu terbuka untuk semua pengguna yang sudah login.
    Halaman ini sendiri hanya bisa dibuka admin.
</div>

<form method="POST">
    <?= csrf_field(); ?>
    <div class="table-responsive bg-white p-3 rounded shadow-sm mb-3">
        <table class="table table-bordered align-middle mb-0">
            <thead class="text-white" style="background-color: var(--color-primary);">
                <tr>
                    <th>Halaman</th>
                    <th class="text-center" width="12%">Admin</th>
                    <th class="text-center" width="12%">Guru</th>
                    <th class="text-center" width="12%">Siswa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar as $file => $h): ?>
                <tr>
                    <td>
                        <strong><?= e($h['label']); ?></strong>
                        <div class="small text-muted"><?= e($h['ket']); ?> &middot; <code><?= e($file); ?></code></div>
                    </td>
                    <td class="text-center">
                        <input class="form-check-input" type="checkbox" style="width:1.4em;height:1.4em;" checked disabled title="Admin selalu punya akses">
                    </td>
                    <?php foreach (['guru', 'siswa'] as $r): ?>
                    <td class="text-center">
                        <?php if (in_array($r, $h['peran'], true)): ?>
                            <input class="form-check-input" type="checkbox" style="width:1.4em;height:1.4em;"
                                   name="akses[<?= e($file); ?>][<?= e($r); ?>]" value="1" <?= boleh_akses($file, $r) ? 'checked' : ''; ?>>
                        <?php else: ?>
                            <span class="text-muted" title="Halaman ini tidak dirancang untuk role tersebut">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Jika guru diberi akses ke <strong>Data Guru</strong> atau <strong>Semua Pengguna</strong>, guru hanya bisa mengelola akun guru dan siswa.
        Akun admin tetap hanya bisa diubah atau dihapus oleh admin. Pada <strong>Input Nilai</strong> dan <strong>Rekap Nilai</strong>, guru tetap hanya melihat mapel yang ia ampu.
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Pengaturan</button>
    </div>
</form>

<form method="POST" class="mt-2" onsubmit="return confirm('Kembalikan semua pengaturan akses ke bawaan?');">
    <?= csrf_field(); ?>
    <input type="hidden" name="aksi" value="reset">
    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Kembalikan ke bawaan</button>
</form>

<?php include 'footer.php'; ?>
