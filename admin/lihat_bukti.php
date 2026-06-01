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

$query = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat, users.nama, users.no_hp
                               FROM transaksi
                               JOIN produk ON transaksi.id_produk = produk.id_produk
                               JOIN users  ON transaksi.id_user   = users.id_user
                               WHERE transaksi.id_transaksi = '$id'");
$row = mysqli_fetch_assoc($query);

if (!$row) {
    header("Location: transaksi.php");
    exit;
}

// Cek kolom ada
$cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'metode_ambil'");
$kolom_baru = (mysqli_num_rows($cek_kolom) > 0);

$bukti_file  = $kolom_baru ? ($row['bukti_transfer'] ?? '') : '';
$foto_ktp    = $kolom_baru ? ($row['foto_ktp']       ?? '') : '';
$selfie_ktp  = $kolom_baru ? ($row['selfie_ktp']     ?? '') : '';
$nama_penerima  = $kolom_baru ? ($row['nama_penerima']  ?? '') : '';
$no_hp_penerima = $kolom_baru ? ($row['no_hp_penerima'] ?? '') : '';
$alamat_kirim   = $kolom_baru ? ($row['alamat_kirim']   ?? '') : '';
$ongkos_kirim   = $kolom_baru ? ($row['ongkos_kirim']   ?? 0)  : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran | ElonCamp</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

        nav { background: #1e293b; padding: 16px 30px; display: flex; justify-content: space-between; align-items: center; }
        nav .logo { color: #f97316; font-weight: 800; font-size: 1.2rem; letter-spacing: 1px; text-decoration: none; }
        nav a.back { color: #94a3b8; text-decoration: none; font-size: 0.85rem; }
        nav a.back:hover { color: white; }

        .wrap { max-width: 1050px; margin: 0 auto; padding: 35px 25px; }

        .page-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
        .page-sub   { color: #64748b; font-size: 0.85rem; margin-bottom: 25px; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
        @media (max-width: 750px) { .grid { grid-template-columns: 1fr; } }

        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 3px 10px rgba(0,0,0,0.06); }
        .card h3 { font-size: 0.88rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }

        .info-row { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 11px; font-size: 0.88rem; }
        .info-row .lbl { color: #64748b; flex-shrink: 0; }
        .info-row .val { font-weight: 600; text-align: right; }

        /* Foto viewer */
        .foto-box { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #f8fafc; }
        .foto-box img { width: 100%; display: block; max-height: 340px; object-fit: contain; background: #0f172a; cursor: zoom-in; }
        .foto-box .foto-label { padding: 8px 12px; font-size: 0.8rem; font-weight: 600; color: #475569; background: #f1f5f9; }
        .foto-empty { padding: 40px; text-align: center; color: #94a3b8; font-size: 0.88rem; }

        /* Tombol aksi verifikasi */
        .aksi-bar { background: white; border-radius: 12px; padding: 22px 24px; margin-top: 22px; box-shadow: 0 3px 10px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .aksi-bar .kode { font-family: 'Courier New', monospace; font-size: 0.95rem; color: #f97316; font-weight: 700; }
        .aksi-bar .status { font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; }
        .s-pending  { background: #fef9c3; color: #854d0e; }
        .s-disewa   { background: #dcfce7; color: #166534; }
        .s-kembali  { background: #e2e8f0; color: #475569; }

        .btn { padding: 11px 26px; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; transition: opacity .2s; }
        .btn:hover { opacity: .88; }
        .btn-konfirm { background: #10b981; color: white; }
        .btn-tolak   { background: #ef4444; color: white; }
        .btn-back    { background: #e2e8f0; color: #475569; }

        /* Modal konfirmasi */
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 999; align-items: center; justify-content: center; }
        .overlay.show { display: flex; }
        .modal { background: white; border-radius: 14px; padding: 30px; width: 420px; max-width: 94%; box-shadow: 0 20px 50px rgba(0,0,0,0.25); }
        .modal h3 { margin-bottom: 10px; }
        .modal p  { color: #64748b; font-size: 0.9rem; line-height: 1.6; margin-bottom: 20px; }
        .modal-btns { display: flex; gap: 10px; }
        .modal-btns a, .modal-btns button { flex: 1; padding: 12px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; text-align: center; text-decoration: none; font-size: 0.9rem; }

        /* Lightbox */
        .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 1000; align-items: center; justify-content: center; cursor: zoom-out; }
        .lightbox.show { display: flex; }
        .lightbox img { max-width: 92vw; max-height: 90vh; border-radius: 8px; }
    </style>
</head>
<body>

<nav>
    <a href="../admin/index.php" class="logo">ELONADMIN.</a>
    <a href="transaksi.php" class="back">← Kembali ke Transaksi</a>
</nav>

<div class="wrap">
    <h1 class="page-title">Verifikasi Pembayaran</h1>
    <p class="page-sub">Periksa bukti transfer dan data identitas penyewa sebelum mengkonfirmasi.</p>

    <div class="grid">

        <!-- Kolom kiri: Info transaksi & alamat -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <h3>📋 Detail Pesanan</h3>
                <div class="info-row"><span class="lbl">Kode Transaksi</span><span class="val" style="color:#f97316;font-family:monospace;"><?= htmlspecialchars($row['kode_transaksi']); ?></span></div>
                <div class="info-row"><span class="lbl">Nama Pelanggan</span><span class="val"><?= htmlspecialchars($row['nama']); ?></span></div>
                <div class="info-row"><span class="lbl">No. HP Akun</span><span class="val"><?= htmlspecialchars($row['no_hp'] ?? '-'); ?></span></div>
                <div class="info-row"><span class="lbl">Alat Disewa</span><span class="val"><?= htmlspecialchars($row['nama_alat']); ?></span></div>
                <div class="info-row"><span class="lbl">Jumlah</span><span class="val"><?= $row['jumlah']; ?> unit</span></div>
                <div class="info-row"><span class="lbl">Tgl Sewa</span><span class="val"><?= date('d M Y', strtotime($row['tgl_sewa'])); ?></span></div>
                <div class="info-row"><span class="lbl">Tgl Kembali</span><span class="val"><?= date('d M Y', strtotime($row['tgl_kembali'])); ?></span></div>
                <div class="info-row"><span class="lbl">Biaya Sewa</span><span class="val"><?= formatRupiah($row['total_harga'] - $ongkos_kirim); ?></span></div>
                <div class="info-row"><span class="lbl">Ongkos Kirim</span><span class="val"><?= formatRupiah($ongkos_kirim); ?></span></div>
                <div class="info-row" style="border-top:1px solid #f1f5f9;padding-top:10px;margin-top:5px;">
                    <span class="lbl" style="font-weight:700;">Total Dibayar</span>
                    <span class="val" style="color:#f97316;font-size:1rem;"><?= formatRupiah($row['total_harga']); ?></span>
                </div>
            </div>

            <div class="card">
                <h3>🚚 Data Pengiriman</h3>
                <div class="info-row"><span class="lbl">Nama Penerima</span><span class="val"><?= htmlspecialchars($nama_penerima ?: '-'); ?></span></div>
                <div class="info-row"><span class="lbl">No. HP Penerima</span><span class="val"><?= htmlspecialchars($no_hp_penerima ?: '-'); ?></span></div>
                <div class="info-row"><span class="lbl">Alamat Kirim</span><span class="val" style="max-width:55%;line-height:1.5;"><?= nl2br(htmlspecialchars($alamat_kirim ?: '-')); ?></span></div>
                <?php if (!empty($row['catatan'] ?? '')): ?>
                <div class="info-row"><span class="lbl">Catatan</span><span class="val" style="max-width:55%;"><?= htmlspecialchars($row['catatan']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kolom kanan: Foto-foto -->
        <div style="display:flex;flex-direction:column;gap:18px;">

            <!-- Bukti Transfer -->
            <div class="card">
                <h3>💳 Bukti Transfer</h3>
                <?php if (!empty($bukti_file) && file_exists('../uploads/bukti_tf/' . $bukti_file)): ?>
                    <div class="foto-box">
                        <div class="foto-label">📎 <?= htmlspecialchars($bukti_file); ?></div>
                        <img src="../uploads/bukti_tf/<?= htmlspecialchars($bukti_file); ?>"
                             alt="Bukti Transfer"
                             onclick="bukaLightbox(this.src)">
                    </div>
                    <p style="font-size:0.78rem;color:#94a3b8;margin-top:8px;">Klik foto untuk zoom.</p>
                <?php elseif (!empty($bukti_file)): ?>
                    <div class="foto-empty">⚠️ File bukti transfer tidak ditemukan di server.<br><small style="font-size:0.75rem;">Nama file: <?= htmlspecialchars($bukti_file); ?></small></div>
                <?php else: ?>
                    <div class="foto-empty">⏳ Pelanggan belum mengirim bukti transfer.</div>
                <?php endif; ?>
            </div>

            <!-- Foto KTP -->
            <div class="card">
                <h3>🪪 Foto KTP & Selfie</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <?php if (!empty($foto_ktp) && file_exists('../uploads/identitas/' . $foto_ktp)): ?>
                        <div class="foto-box">
                            <div class="foto-label">KTP</div>
                            <img src="../uploads/identitas/<?= htmlspecialchars($foto_ktp); ?>"
                                 alt="Foto KTP" onclick="bukaLightbox(this.src)">
                        </div>
                    <?php else: ?>
                        <div class="foto-box"><div class="foto-empty" style="padding:20px;">Foto KTP<br>tidak ada</div></div>
                    <?php endif; ?>

                    <?php if (!empty($selfie_ktp) && file_exists('../uploads/identitas/' . $selfie_ktp)): ?>
                        <div class="foto-box">
                            <div class="foto-label">Selfie + KTP</div>
                            <img src="../uploads/identitas/<?= htmlspecialchars($selfie_ktp); ?>"
                                 alt="Selfie KTP" onclick="bukaLightbox(this.src)">
                        </div>
                    <?php else: ?>
                        <div class="foto-box"><div class="foto-empty" style="padding:20px;">Selfie KTP<br>tidak ada</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bar Aksi Konfirmasi -->
    <div class="aksi-bar">
        <div>
            <div class="kode"><?= htmlspecialchars($row['kode_transaksi']); ?></div>
            <div style="margin-top:5px;">
                <span class="status s-<?= $row['status']; ?>"><?= strtoupper($row['status']); ?></span>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="transaksi.php" class="btn btn-back">← Kembali</a>

            <?php if ($row['status'] === 'pending'): ?>
                <?php if (!empty($bukti_file)): ?>
                    <button class="btn btn-tolak"
                            onclick="document.getElementById('modalTolak').classList.add('show')">
                        ✗ Tolak
                    </button>
                    <button class="btn btn-konfirm"
                            onclick="document.getElementById('modalKonfirm').classList.add('show')">
                        ✓ Konfirmasi Pembayaran
                    </button>
                <?php else: ?>
                    <span style="color:#f97316;font-weight:600;font-size:0.88rem;">⏳ Menunggu bukti transfer dari pelanggan.</span>
                <?php endif; ?>
            <?php else: ?>
                <span style="color:#10b981;font-weight:600;">✔️ Sudah diproses.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Konfirmasi Bayar -->
<div class="overlay" id="modalKonfirm">
    <div class="modal">
        <h3>✅ Konfirmasi Pembayaran & Kirim</h3>
        <p>Pastikan nominal transfer sudah sesuai dengan total tagihan <strong><?= formatRupiah($row['total_harga']); ?></strong>.<br><br>Setelah dikonfirmasi, status berubah menjadi <strong>DIKIRIM</strong>. Segera input nomor resi di halaman transaksi agar member bisa memantau pengiriman.</p>
        <div class="modal-btns">
            <button onclick="document.getElementById('modalKonfirm').classList.remove('show')"
                    style="background:#e2e8f0;color:#475569;">Batal</button>
            <a href="transaksi.php?aksi=konfirmasi_bayar&id=<?= $row['id_transaksi']; ?>"
               style="background:#10b981;color:white;">Ya, Konfirmasi</a>
        </div>
    </div>
</div>

<!-- Modal: Tolak Pembayaran -->
<div class="overlay" id="modalTolak">
    <div class="modal">
        <h3>✗ Tolak Pembayaran</h3>
        <p>Fitur tolak akan mereset status kembali ke <strong>PENDING</strong> agar pelanggan bisa kirim ulang bukti transfer yang benar.</p>
        <div class="modal-btns">
            <button onclick="document.getElementById('modalTolak').classList.remove('show')"
                    style="background:#e2e8f0;color:#475569;">Batal</button>
            <a href="transaksi.php?aksi=tolak_bayar&id=<?= $row['id_transaksi']; ?>"
               style="background:#ef4444;color:white;">Ya, Tolak</a>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="" alt="">
</div>

<script>
function bukaLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
</script>

</body>
</html>