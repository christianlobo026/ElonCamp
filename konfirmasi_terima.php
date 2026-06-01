<?php
include 'config/database.php';

if (!isLoggedIn()) {
    header("Location: auth/login.php"); exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: riwayat.php"); exit;
}

$id_user = $_SESSION['user_id'];
$id_trx  = (int)$_GET['id'];

$q = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat
                           FROM transaksi
                           JOIN produk ON transaksi.id_produk = produk.id_produk
                           WHERE transaksi.id_transaksi = '$id_trx'
                           AND transaksi.id_user = '$id_user'");
$row = mysqli_fetch_assoc($q);

if (!$row || $row['status'] !== 'dikirim') {
    header("Location: riwayat.php"); exit;
}

$error = '';

// Cek kolom video ada tidak
$ada_video = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'video_terima'")) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kondisi_terima = $_POST['kondisi_terima'] ?? '';

    if (empty($kondisi_terima)) {
        $error = "Pilih kondisi barang saat diterima.";
    } elseif ($kondisi_terima === 'rusak' && (!isset($_FILES['bukti_terima']) || $_FILES['bukti_terima']['error'] !== UPLOAD_ERR_OK)) {
        $error = "Video bukti WAJIB diupload untuk mengajukan komplain kerusakan.";
    } else {

        // Handle upload video bukti
        $nama_file = '';
        if (isset($_FILES['bukti_terima']) && $_FILES['bukti_terima']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['mp4','mov','avi','webm'];
            $ext     = strtolower(pathinfo($_FILES['bukti_terima']['name'], PATHINFO_EXTENSION));
            $size    = $_FILES['bukti_terima']['size'];

            if (!in_array($ext, $allowed)) {
                $error = "Format file harus JPG, PNG, MP4, atau MOV.";
            } elseif ($size > 50 * 1024 * 1024) {
                $error = "Ukuran file maksimal 50MB.";
            } else {
                $dir = __DIR__ . '/uploads/bukti_terima/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $nama_file = 'terima_' . $id_trx . '_' . time() . '.' . $ext;
                if (!move_uploaded_file($_FILES['bukti_terima']['tmp_name'], $dir . $nama_file)) {
                    $nama_file = ''; // upload gagal, tidak masalah, tetap lanjut
                }
            }
        }

        if (empty($error)) {
            if ($kondisi_terima === 'baik') {
                // Barang aman → status DISEWA, mulai masa sewa
                $set_video = $ada_video && !empty($nama_file) ? ", video_terima='$nama_file'" : '';
                mysqli_query($conn, "UPDATE transaksi SET status='disewa' $set_video
                                     WHERE id_transaksi='$id_trx'");
                header("Location: riwayat.php?pesan=terima_baik"); exit;

            } else {
                // Barang rusak/tidak sesuai → status KOMPLAIN
                $catatan_komplain = bersihkan($conn, $_POST['catatan_komplain'] ?? '');
                $set_video  = $ada_video && !empty($nama_file)       ? ", video_terima='$nama_file'"         : '';
                $set_cat    = !empty($catatan_komplain)               ? ", catatan='$catatan_komplain'"       : '';
                $cek_kondisi = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'kondisi_terima'")) > 0;
                $set_kondisi = $cek_kondisi                           ? ", kondisi_terima='$kondisi_terima'"  : '';

                mysqli_query($conn, "UPDATE transaksi SET status='komplain'
                                     $set_video $set_cat $set_kondisi
                                     WHERE id_transaksi='$id_trx'");

                // Kembalikan stok sementara untuk komplain
                mysqli_query($conn, "UPDATE produk SET stok = stok + " . (int)$row['jumlah'] . "
                                     WHERE id_produk = '" . (int)$row['id_produk'] . "'");

                header("Location: riwayat.php?pesan=komplain"); exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Penerimaan Barang – ElonCamp</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        :root{--bg:#0d1117;--surf:#161b22;--surf2:#1c2128;--border:#30363d;--text:#e6edf3;--muted:#8b949e;--accent:#f97316;--red:#ef4444;--green:#10b981;--purple:#7c3aed;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        nav{background:var(--surf);border-bottom:1px solid var(--border);padding:16px 6%;display:flex;justify-content:space-between;align-items:center;}
        .logo{font-family:'Syne',sans-serif;font-weight:800;color:var(--accent);text-decoration:none;font-size:1.35rem;}
        .back{color:var(--muted);text-decoration:none;font-size:.88rem;}
        .wrap{max-width:580px;margin:50px auto;padding:0 20px;}
        .card{background:var(--surf);border:1px solid var(--border);border-radius:18px;padding:36px;}
        h2{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;margin-bottom:6px;}
        .subtitle{color:var(--muted);font-size:.88rem;line-height:1.6;margin-bottom:28px;}
        .detail-box{background:var(--surf2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:24px;}
        .detail-box h4{font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:12px;}
        .d-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:8px;}
        .d-row:last-child{margin-bottom:0;}
        .d-row .lbl{color:var(--muted);}
        .d-row .val{font-weight:600;}
        hr.div{border:none;border-top:1px solid var(--border);margin:22px 0;}
        .kondisi-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px;}
        .kondisi-opt input[type="radio"]{position:absolute;opacity:0;width:0;}
        .kondisi-opt{position:relative;}
        .kondisi-label{display:flex;flex-direction:column;align-items:center;gap:8px;padding:18px 12px;border:2px solid var(--border);border-radius:12px;cursor:pointer;background:var(--surf2);transition:all .2s;text-align:center;}
        .kondisi-label .ico{font-size:2rem;}
        .kondisi-label .lbl-txt{font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;}
        .kondisi-label .sub-txt{font-size:.75rem;color:var(--muted);}
        .kondisi-opt input:checked + .kondisi-label.good{border-color:var(--green);background:rgba(16,185,129,.1);}
        .kondisi-opt input:checked + .kondisi-label.bad{border-color:var(--red);background:rgba(239,68,68,.1);}
        .kondisi-label:hover{border-color:rgba(249,115,22,.5);}
        .form-group{margin-bottom:18px;}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:7px;}
        .form-group textarea{width:100%;padding:11px 14px;background:var(--surf2);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.92rem;outline:none;resize:vertical;min-height:80px;transition:border-color .2s;}
        .form-group textarea:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(239,68,68,.15);}
        /* Upload bukti */
        .upload-zone{border:2px dashed var(--border);border-radius:10px;background:var(--bg);position:relative;cursor:pointer;transition:border-color .2s;overflow:hidden;}
        .upload-zone:hover{border-color:var(--accent);}
        .upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
        .upload-inner{padding:24px;text-align:center;pointer-events:none;}
        .upload-inner .u-ico{font-size:2rem;margin-bottom:6px;}
        .upload-inner .u-txt{font-size:.82rem;color:var(--muted);line-height:1.5;}
        .upload-inner .u-txt strong{color:var(--accent);}
        .preview-name{font-size:.8rem;color:var(--green);text-align:center;padding:8px;display:none;}
        .warn{border-radius:10px;padding:13px 15px;margin-bottom:20px;font-size:.85rem;line-height:1.6;}
        .warn-orange{background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);color:#fdba74;}
        .warn-red{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
        .alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.4);color:#fca5a5;padding:12px 15px;border-radius:9px;font-size:.88rem;margin-bottom:16px;}
        .btn-row{display:flex;gap:12px;margin-top:8px;}
        .btn{flex:1;padding:13px;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;cursor:pointer;text-align:center;text-decoration:none;display:block;transition:opacity .2s;}
        .btn:hover{opacity:.88;}
        .btn-cancel{background:var(--surf2);color:var(--muted);border:1px solid var(--border);}
        .btn-green{background:var(--green);color:white;}
        .btn-red{background:var(--red);color:white;}
        /* Section komplain — hidden by default */
        #section-komplain{display:none;animation:slideDown .3s ease;}
        @keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
        #section-komplain.show{display:block;}
    </style>
</head>
<body>
<nav>
    <a href="index.php" class="logo">ELONCAMP.</a>
    <a href="riwayat.php" class="back">← Riwayat Sewa</a>
</nav>

<div class="wrap">
<div class="card">

    <h2>📦 Konfirmasi Penerimaan Barang</h2>
    <p class="subtitle">
        Periksa kondisi alat yang baru kamu terima, lalu upload bukti video/foto.<br>
        Laporan kondisi ini penting untuk proses klaim jika ada kerusakan.
    </p>

    <!-- Detail pesanan -->
    <div class="detail-box">
        <h4>📋 Detail Pesanan</h4>
        <div class="d-row"><span class="lbl">Kode</span><span class="val" style="color:var(--accent);font-family:monospace;"><?= htmlspecialchars($row['kode_transaksi']); ?></span></div>
        <div class="d-row"><span class="lbl">Alat</span><span class="val"><?= htmlspecialchars($row['nama_alat']); ?></span></div>
        <div class="d-row"><span class="lbl">Jumlah</span><span class="val"><?= $row['jumlah']; ?> unit</span></div>
        <div class="d-row"><span class="lbl">Tgl Kembali</span><span class="val"><?= date('d M Y', strtotime($row['tgl_kembali'])); ?></span></div>
        <?php
        $resi = $row['no_resi'] ?? '';
        $eksp = $row['ekspedisi'] ?? '';
        if (!empty($resi)): ?>
        <div class="d-row"><span class="lbl">Resi</span><span class="val" style="color:var(--purple);"><?= htmlspecialchars($eksp); ?> · <?= htmlspecialchars($resi); ?></span></div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert-err">⚠️ <?= $error; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="formTerima">

        <!-- Pilih kondisi -->
        <p style="font-size:.82rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">Kondisi Barang Saat Diterima <span style="color:var(--red)">*</span></p>
        <div class="kondisi-grid">
            <div class="kondisi-opt">
                <input type="radio" name="kondisi_terima" id="kondisi_baik" value="baik" onchange="toggleKomplain(this)">
                <label class="kondisi-label good" for="kondisi_baik">
                    <span class="ico">✅</span>
                    <span class="lbl-txt">Baik / Lengkap</span>
                    <span class="sub-txt">Barang sesuai, siap dipakai</span>
                </label>
            </div>
            <div class="kondisi-opt">
                <input type="radio" name="kondisi_terima" id="kondisi_rusak" value="rusak" onchange="toggleKomplain(this)">
                <label class="kondisi-label bad" for="kondisi_rusak">
                    <span class="ico">⚠️</span>
                    <span class="lbl-txt">Rusak / Tidak Sesuai</span>
                    <span class="sub-txt">Ada kerusakan atau kekurangan</span>
                </label>
            </div>
        </div>

        <!-- Upload bukti video/foto -->
        <div class="form-group">
            <label>🎥 Video Unboxing Barang <span style="color:var(--red);font-weight:700;">* WAJIB</span></label>
            <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);border-radius:8px;padding:11px 14px;margin-bottom:10px;font-size:.83rem;color:#fca5a5;line-height:1.55;">
                ⚠️ <strong>WAJIB VIDEO DARI BARANG MASIH DALAM PACKING / BELUM DIBUKA.</strong><br>
                Video yang direkam setelah barang dibuka tidak akan diterima sebagai bukti komplain yang valid.
            </div>
            <div class="upload-zone" id="uploadZone">
                <input type="file" name="bukti_terima" id="inputBukti" accept="video/*,.mp4,.mov,.avi"
                       onchange="previewFile(this)">
                <div class="upload-inner" id="uploadInner">
                    <div class="u-ico">🎬</div>
                    <div class="u-txt">
                        Klik untuk upload video unboxing barang<br>
                        <strong>MP4 / MOV / AVI</strong> · maks 50MB
                    </div>
                </div>
                <div class="preview-name" id="previewName"></div>
            </div>
        </div>

        <!-- Section komplain — muncul kalau pilih rusak -->
        <div id="section-komplain">
            <hr class="div">
            <div class="warn warn-red">
                ⚠️ Kamu memilih kondisi <strong>Rusak / Tidak Sesuai</strong>. Pesanan akan masuk status <strong>KOMPLAIN</strong> dan admin akan menghubungi kamu untuk proses lebih lanjut.
            </div>
            <div class="form-group">
                <label>Deskripsi Kerusakan / Ketidaksesuaian <span style="color:var(--red)">*</span></label>
                <textarea name="catatan_komplain"
                          placeholder="Jelaskan kerusakan yang kamu temukan. Contoh: tenda berlubang di bagian atap, tiang frame patah, dll."><?= htmlspecialchars($_POST['catatan_komplain'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="warn warn-orange" style="margin-top:4px;">
            ⚠️ Video harus direkam <strong>sebelum membuka packing</strong>. Komplain yang diajukan tanpa video dari barang masih dipacking akan <strong>ditolak oleh admin</strong>.
        </div>

        <div class="btn-row" style="margin-top:20px;" id="btnWrap">
            <a href="riwayat.php" class="btn btn-cancel">Batal</a>
            <button type="submit" class="btn btn-green" id="btnSubmit">✅ Konfirmasi Diterima</button>
        </div>

    </form>
</div>
</div>

<script>
function toggleKomplain(radio) {
    const sec     = document.getElementById('section-komplain');
    const btnSubmit = document.getElementById('btnSubmit');
    if (radio.value === 'rusak') {
        sec.classList.add('show');
        btnSubmit.textContent = '⚠️ Laporkan Kerusakan';
        btnSubmit.style.background = 'var(--red)';
    } else {
        sec.classList.remove('show');
        btnSubmit.textContent = '✅ Konfirmasi Diterima';
        btnSubmit.style.background = 'var(--green)';
    }
}

function previewFile(input) {
    const inner = document.getElementById('uploadInner');
    const name  = document.getElementById('previewName');
    if (input.files && input.files[0]) {
        inner.style.display = 'none';
        name.style.display  = 'block';
        name.textContent    = '✅ ' + input.files[0].name + ' (' + (input.files[0].size / 1024 / 1024).toFixed(1) + ' MB)';
    }
}
</script>
</body>
</html>