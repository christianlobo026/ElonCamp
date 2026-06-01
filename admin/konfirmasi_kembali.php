<?php 
include '../config/database.php'; 

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: transaksi.php");
    exit;
}

$id = (int)$_GET['id'];

$query = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat, produk.harga_sewa 
                               FROM transaksi 
                               JOIN produk ON transaksi.id_produk = produk.id_produk 
                               WHERE transaksi.id_transaksi = '$id' 
                               AND transaksi.status IN ('disewa','komplain')");
$row = mysqli_fetch_assoc($query);

if (!$row) {
    header("Location: transaksi.php");
    exit;
}

$is_komplain    = ($row['status'] === 'komplain');
$video_terima   = $row['video_terima']   ?? '';
$catatan_member = $row['catatan']        ?? '';

// Hitung denda keterlambatan
$denda_telat  = 0;
$tgl_sekarang = date('Y-m-d');
$target = new DateTime($row['tgl_kembali']);
$today  = new DateTime($tgl_sekarang);
if ($today > $target) {
    $hari_telat = $today->diff($target)->days;
    if ($hari_telat > 0) $denda_telat = $hari_telat * $row['harga_sewa'];
}

// ── PROSES FORM ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_admin  = $_POST['aksi_admin'] ?? '';
    $id_produk   = $row['id_produk'];

    if ($is_komplain) {
        // ── KOMPLAIN: Setujui atau Tolak ──
        if ($aksi_admin === 'setujui_komplain') {
            // Komplain disetujui → status 'mengembalikan': member harus kirim balik barang ke toko
            // Stok dan refund diproses SETELAH admin konfirmasi barang sampai
            mysqli_query($conn, "UPDATE transaksi 
                                  SET status='mengembalikan', kondisi='rusak', denda=0
                                  WHERE id_transaksi='$id'");
            header("Location: transaksi.php?msg=komplain_disetujui");
            exit;

        } elseif ($aksi_admin === 'tolak_komplain') {
            // Komplain ditolak → status kembali ke disewa, member tetap sewa normal
            mysqli_query($conn, "UPDATE transaksi SET status='disewa' WHERE id_transaksi='$id'");
            header("Location: transaksi.php?msg=komplain_ditolak");
            exit;
        }

    } else {
        // ── PENGEMBALIAN NORMAL ──
        $kondisi     = $_POST['kondisi'];
        $biaya_rusak = (int)$_POST['biaya_rusak'];
        $total_denda = $denda_telat + $biaya_rusak;

        mysqli_query($conn, "UPDATE transaksi 
                              SET status='kembali', denda='$total_denda', kondisi='$kondisi',
                                  tgl_realisasi_kembali='$tgl_sekarang'
                              WHERE id_transaksi='$id'");
        mysqli_query($conn, "UPDATE produk SET stok = stok + " . (int)$row['jumlah'] . " WHERE id_produk='$id_produk'");
        header("Location: transaksi.php?msg=selesai&denda=$total_denda");
        exit;
    }
}

// Path file video
$file_path = '';
$ext_file  = '';
if (!empty($video_terima)) {
    $full_path = __DIR__ . '/../uploads/bukti_terima/' . $video_terima;
    if (file_exists($full_path)) {
        $file_path = '../uploads/bukti_terima/' . $video_terima;
        $ext_file  = strtolower(pathinfo($video_terima, PATHINFO_EXTENSION));
    }
}
$is_video = in_array($ext_file, ['mp4','mov','avi','webm']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pengembalian | ElonCamp</title>
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh;}
        nav{background:#1e293b;padding:15px 30px;display:flex;justify-content:space-between;align-items:center;}
        nav .logo{color:#f97316;font-weight:800;font-size:1.1rem;text-decoration:none;}
        nav a.back{color:#94a3b8;text-decoration:none;font-size:.85rem;}
        .wrap{max-width:820px;margin:35px auto;padding:0 20px;}
        h2{font-size:1.3rem;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:10px;}
        .sub{color:#64748b;font-size:.85rem;margin-bottom:25px;}
        .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:700;}
        .badge-komplain{background:#fee2e2;color:#991b1b;}
        .badge-normal{background:#dcfce7;color:#166534;}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        @media(max-width:640px){.grid{grid-template-columns:1fr;}}
        .card{background:white;border-radius:12px;padding:22px;box-shadow:0 3px 10px rgba(0,0,0,0.06);}
        .card h3{font-size:.82rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:14px;border-bottom:1px solid #f1f5f9;padding-bottom:8px;}
        .info-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:9px;}
        .info-row .lbl{color:#64748b;}
        .info-row .val{font-weight:600;}

        /* Video bukti */
        .bukti-wrap{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#0f172a;}
        .bukti-label{padding:9px 14px;font-size:.78rem;font-weight:700;color:#e2e8f0;background:#1e293b;display:flex;align-items:center;gap:6px;}
        .bukti-wrap video{width:100%;max-height:280px;display:block;}
        .bukti-wrap img{width:100%;max-height:280px;object-fit:contain;display:block;cursor:zoom-in;}
        .bukti-empty{padding:40px;text-align:center;color:#94a3b8;font-size:.85rem;background:white;border-radius:10px;border:2px dashed #e2e8f0;}

        /* Keputusan admin — komplain */
        .keputusan-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;}
        .kep-option input[type="radio"]{position:absolute;opacity:0;width:0;}
        .kep-option{position:relative;}
        .kep-label{display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 14px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;background:#f8fafc;transition:all .2s;text-align:center;}
        .kep-label .ico{font-size:2.2rem;}
        .kep-label .lbl-txt{font-size:.9rem;font-weight:700;}
        .kep-label .sub-txt{font-size:.75rem;color:#64748b;line-height:1.4;}
        .kep-option input:checked + .kep-label.setujui{border-color:#10b981;background:#f0fdf4;}
        .kep-option input:checked + .kep-label.tolak{border-color:#ef4444;background:#fef2f2;}
        .kep-label:hover{border-color:#94a3b8;}

        /* Form normal */
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:.88rem;color:#334155;}
        .form-group select,.form-group input{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:7px;font-size:.9rem;outline:none;transition:border-color .2s;}
        .form-group select:focus,.form-group input:focus{border-color:#f97316;}
        .warn{border-radius:8px;padding:13px 15px;margin-bottom:16px;font-size:.85rem;line-height:1.6;}
        .warn-orange{background:#fff7ed;border:1px solid #fed7aa;color:#92400e;}
        .warn-green{background:#f0fdf4;border:1px solid #86efac;color:#166534;}
        .warn-red{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;}

        /* Tombol */
        .btn-row{display:flex;gap:12px;margin-top:6px;}
        .btn{flex:1;padding:13px;border:none;border-radius:9px;font-weight:700;font-size:.92rem;cursor:pointer;transition:opacity .2s;}
        .btn:hover{opacity:.88;}
        .btn-green{background:#10b981;color:white;}
        .btn-red{background:#ef4444;color:white;}
        .btn-normal{background:#2563eb;color:white;width:100%;padding:13px;border:none;border-radius:9px;font-weight:700;font-size:.92rem;cursor:pointer;}
        .link-back{display:block;text-align:center;margin-top:12px;color:#64748b;text-decoration:none;font-size:.85rem;}

        /* Lightbox */
        .lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:999;align-items:center;justify-content:center;cursor:zoom-out;}
        .lightbox.show{display:flex;}
        .lightbox img{max-width:92vw;max-height:90vh;border-radius:8px;}

        /* Konfirmasi overlay */
        .confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:998;align-items:center;justify-content:center;}
        .confirm-overlay.show{display:flex;}
        .confirm-box{background:white;border-radius:14px;padding:30px;width:420px;max-width:94%;box-shadow:0 20px 50px rgba(0,0,0,.25);}
        .confirm-box h3{margin-bottom:10px;}
        .confirm-box p{color:#64748b;font-size:.9rem;line-height:1.6;margin-bottom:20px;}
        .confirm-btns{display:flex;gap:10px;}
        .confirm-btns button{flex:1;padding:11px;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;}
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="logo">ELONADMIN.</a>
    <a href="transaksi.php" class="back">← Kembali ke Transaksi</a>
</nav>

<div class="wrap">
    <h2>
        <?= $is_komplain ? 'Proses Komplain Kerusakan' : 'Konfirmasi Pengembalian Alat'; ?>
        <span class="badge <?= $is_komplain ? 'badge-komplain' : 'badge-normal'; ?>">
            <?= $is_komplain ? '⚠️ KOMPLAIN' : '↩️ KEMBALI'; ?>
        </span>
    </h2>
    <p class="sub">
        <?= $is_komplain
            ? 'Member melaporkan kerusakan barang saat diterima. Tonton video bukti sebelum membuat keputusan.'
            : 'Periksa kondisi fisik alat yang dikembalikan sebelum menyelesaikan transaksi.'; ?>
    </p>

    <div class="grid">
        <!-- Kiri: Info -->
        <div class="card">
            <h3>📋 Detail Transaksi</h3>
            <div class="info-row"><span class="lbl">Nama Alat</span><span class="val"><?= htmlspecialchars($row['nama_alat']); ?></span></div>
            <div class="info-row"><span class="lbl">Jumlah</span><span class="val"><?= $row['jumlah']; ?> unit</span></div>
            <div class="info-row"><span class="lbl">Batas Kembali</span><span class="val"><?= date('d/m/Y', strtotime($row['tgl_kembali'])); ?></span></div>
            <div class="info-row">
                <span class="lbl">Denda Telat</span>
                <span class="val" style="color:<?= $denda_telat > 0 ? '#dc2626' : '#10b981'; ?>;"><?= formatRupiah($denda_telat); ?></span>
            </div>
            <?php if (!empty($catatan_member)): ?>
            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px;margin-top:14px;font-size:.85rem;color:#92400e;line-height:1.5;">
                <strong style="display:block;margin-bottom:4px;font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;">📝 Keterangan dari Member:</strong>
                <?= nl2br(htmlspecialchars($catatan_member)); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Kanan: Video bukti -->
        <div class="card">
            <h3>🎥 Video Bukti dari Member</h3>
            <?php if (!empty($file_path)): ?>
                <div class="bukti-wrap">
                    <div class="bukti-label">
                        🎥 <?= $is_video ? 'Video Unboxing' : 'Foto Bukti'; ?> — <?= htmlspecialchars($video_terima); ?>
                    </div>
                    <?php if ($is_video): ?>
                        <video controls>
                            <source src="<?= $file_path; ?>" type="video/<?= $ext_file === 'mov' ? 'mp4' : $ext_file; ?>">
                            Browser tidak mendukung video.
                        </video>
                    <?php else: ?>
                        <img src="<?= $file_path; ?>" alt="Bukti" onclick="bukaLightbox(this.src)">
                    <?php endif; ?>
                </div>
                <p style="font-size:.75rem;color:#94a3b8;margin-top:6px;">Tonton video sebelum membuat keputusan.</p>
            <?php elseif (!empty($video_terima)): ?>
                <div class="bukti-empty">⚠️ File tidak ditemukan di server.<br><small style="font-size:.75rem;"><?= htmlspecialchars($video_terima); ?></small></div>
            <?php else: ?>
                <div class="bukti-empty">📂 Member tidak mengupload video bukti.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ KOMPLAIN: Tombol Setujui / Tolak ══ -->
    <?php if ($is_komplain): ?>
    <div class="card" style="margin-top:20px;">
        <h3>⚖️ Keputusan Admin</h3>

        <div class="warn warn-orange" style="margin-bottom:20px;">
            ⚠️ Pastikan kamu sudah <strong>menonton video bukti</strong> sebelum membuat keputusan. Keputusan ini tidak bisa diubah.
        </div>

        <div class="keputusan-grid">
            <div style="background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:22px;text-align:center;cursor:pointer;" onclick="konfirmasi('setujui')">
                <div style="font-size:2.2rem;margin-bottom:8px;">✅</div>
                <div style="font-weight:800;font-size:1rem;color:#166534;font-family:sans-serif;margin-bottom:6px;">Setujui Komplain</div>
                <div style="font-size:.78rem;color:#166534;line-height:1.4;">Bukti valid — barang memang rusak saat dikirim. Transaksi diselesaikan, member tidak dikenakan biaya tambahan.</div>
            </div>
            <div style="background:#fef2f2;border:2px solid #fca5a5;border-radius:12px;padding:22px;text-align:center;cursor:pointer;" onclick="konfirmasi('tolak')">
                <div style="font-size:2.2rem;margin-bottom:8px;">❌</div>
                <div style="font-weight:800;font-size:1rem;color:#991b1b;font-family:sans-serif;margin-bottom:6px;">Tolak Komplain</div>
                <div style="font-size:.78rem;color:#991b1b;line-height:1.4;">Video tidak valid — barang sudah dibuka sebelum direkam atau tidak ada bukti kerusakan. Status kembali ke DISEWA.</div>
            </div>
        </div>

        <!-- Form hidden — disubmit via JS -->
        <form method="POST" id="formKeputusan">
            <input type="hidden" name="aksi_admin" id="inputAksi" value="">
        </form>
    </div>

    <!-- ══ NORMAL: Form Pengembalian Biasa ══ -->
    <?php else: ?>
    <div class="card" style="margin-top:20px;">
        <h3>⚖️ Keputusan Admin</h3>
        <form method="POST">
            <input type="hidden" name="aksi_admin" value="normal">
            <div class="form-group">
                <label>Kondisi Alat Saat Dikembalikan</label>
                <select name="kondisi" id="kondisi" onchange="cekKondisi()">
                    <option value="normal">Normal / Lengkap</option>
                    <option value="rusak">Rusak</option>
                    <option value="hilang">Hilang</option>
                </select>
            </div>
            <div class="form-group" id="box-biaya" style="display:none;">
                <label>Biaya Ganti Rugi Tambahan (Rp)</label>
                <input type="number" name="biaya_rusak" id="biaya_rusak" value="0" min="0">
                <small style="color:#64748b;display:block;margin-top:4px;">Masukkan nominal ganti rugi jika ada komponen rusak/hilang.</small>
            </div>
            <button type="submit" class="btn-normal">Proses &amp; Selesaikan Transaksi</button>
            <a href="transaksi.php" class="link-back">← Batalkan</a>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Confirm overlay: Setujui -->
<div class="confirm-overlay" id="overlaySetujui">
    <div class="confirm-box">
        <h3>✅ Setujui Komplain?</h3>
        <p>Kamu menyatakan bahwa bukti video <strong>valid</strong> — barang memang rusak saat dikirim.<br><br>Transaksi akan diselesaikan dengan kondisi <strong>RUSAK</strong>, stok dikembalikan, dan member tidak dikenakan denda.</p>
        <div class="confirm-btns">
            <button onclick="tutupOverlay()" style="background:#e2e8f0;color:#475569;">Batal</button>
            <button onclick="submitKeputusan('setujui_komplain')" style="background:#10b981;color:white;">Ya, Setujui</button>
        </div>
    </div>
</div>

<!-- Confirm overlay: Tolak -->
<div class="confirm-overlay" id="overlayTolak">
    <div class="confirm-box">
        <h3>❌ Tolak Komplain?</h3>
        <p>Kamu menyatakan bahwa bukti video <strong>tidak valid</strong> — video direkam setelah barang dibuka, atau tidak ada bukti kerusakan yang jelas.<br><br>Status transaksi akan kembali ke <strong>DISEWA</strong> dan member melanjutkan masa sewa normal.</p>
        <div class="confirm-btns">
            <button onclick="tutupOverlay()" style="background:#e2e8f0;color:#475569;">Batal</button>
            <button onclick="submitKeputusan('tolak_komplain')" style="background:#ef4444;color:white;">Ya, Tolak</button>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="" alt="">
</div>

<script>
function cekKondisi() {
    const val = document.getElementById('kondisi').value;
    const box = document.getElementById('box-biaya');
    box.style.display = (val === 'rusak' || val === 'hilang') ? 'block' : 'none';
    if (val === 'normal') document.getElementById('biaya_rusak').value = 0;
}
function konfirmasi(tipe) {
    document.getElementById('overlaySetujui').classList.remove('show');
    document.getElementById('overlayTolak').classList.remove('show');
    if (tipe === 'setujui') document.getElementById('overlaySetujui').classList.add('show');
    else document.getElementById('overlayTolak').classList.add('show');
}
function tutupOverlay() {
    document.getElementById('overlaySetujui').classList.remove('show');
    document.getElementById('overlayTolak').classList.remove('show');
}
function submitKeputusan(aksi) {
    document.getElementById('inputAksi').value = aksi;
    document.getElementById('formKeputusan').submit();
}
function bukaLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
// Tutup overlay klik luar
['overlaySetujui','overlayTolak'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){
        if (e.target === this) tutupOverlay();
    });
});
</script>
</body>
</html>