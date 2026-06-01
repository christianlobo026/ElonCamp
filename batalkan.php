<?php
include 'config/database.php';

if (!isLoggedIn()) {
    header("Location: auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: riwayat.php");
    exit;
}

$id_user = $_SESSION['user_id'];
$id_trx  = (int)$_GET['id'];
$aksi    = isset($_GET['aksi']) ? $_GET['aksi'] : 'batal';

$q = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat
                           FROM transaksi
                           JOIN produk ON transaksi.id_produk = produk.id_produk
                           WHERE transaksi.id_transaksi = '$id_trx'
                           AND transaksi.id_user = '$id_user'");
$row = mysqli_fetch_assoc($q);

if (!$row) { header("Location: riwayat.php"); exit; }

$ada_metode = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'metode_ambil'"))   > 0;
$ada_bukti  = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'bukti_transfer'")) > 0;
$ada_refund = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'refund_status'"))  > 0;

$metode    = $ada_metode ? ($row['metode_ambil']   ?? 'ambil_di_tempat') : 'ambil_di_tempat';
$bukti_file= $ada_bukti  ? ($row['bukti_transfer'] ?? '')                : '';
$refund_st = $ada_refund ? ($row['refund_status']  ?? '')                : '';
$sudah_bayar = !empty($bukti_file);

$error = '';

// AKSI POST: BATALKAN
if ($aksi === 'batal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($row['status'] !== 'pending') { header("Location: riwayat.php"); exit; }
    mysqli_query($conn, "UPDATE produk SET stok = stok + " . (int)$row['jumlah'] . " WHERE id_produk = '" . (int)$row['id_produk'] . "'");
    if ($ada_refund) {
        mysqli_query($conn, "UPDATE transaksi SET status='dibatalkan', refund_status=NULL WHERE id_transaksi='$id_trx'");
    } else {
        mysqli_query($conn, "UPDATE transaksi SET status='dibatalkan' WHERE id_transaksi='$id_trx'");
    }
    header("Location: riwayat.php?pesan=dibatalkan"); exit;
}

// AKSI POST: REFUND
if ($aksi === 'refund' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($row['status'] !== 'dibatalkan') { header("Location: riwayat.php"); exit; }
    $nama_bank = bersihkan($conn, $_POST['nama_bank'] ?? '');
    $no_rek    = bersihkan($conn, $_POST['no_rek']    ?? '');
    $nama_rek  = bersihkan($conn, $_POST['nama_rek']  ?? '');
    if (empty($nama_bank) || empty($no_rek) || empty($nama_rek)) {
        $error = "Semua data rekening wajib diisi.";
    } else {
        $info = bersihkan($conn, "$nama_bank | $no_rek | a.n. $nama_rek");
        if (!$ada_refund) {
            mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN refund_status VARCHAR(20) NULL, ADD COLUMN refund_info VARCHAR(255) NULL");
        }
        mysqli_query($conn, "UPDATE transaksi SET refund_status='diajukan', refund_info='$info' WHERE id_transaksi='$id_trx'");
        header("Location: riwayat.php?pesan=refund_diajukan"); exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $aksi === 'refund' ? 'Ajukan Refund' : 'Batalkan Pesanan'; ?> - ElonCamp</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        :root{--bg:#0d1117;--surf:#161b22;--surf2:#1c2128;--border:#30363d;--text:#e6edf3;--muted:#8b949e;--accent:#f97316;--red:#ef4444;--green:#10b981;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        nav{background:var(--surf);border-bottom:1px solid var(--border);padding:16px 6%;display:flex;justify-content:space-between;align-items:center;}
        .logo{font-family:'Syne',sans-serif;font-weight:800;color:var(--accent);text-decoration:none;font-size:1.35rem;}
        .back{color:var(--muted);text-decoration:none;font-size:.88rem;}
        .wrap{max-width:540px;margin:60px auto;padding:0 20px;}
        .card{background:var(--surf);border:1px solid var(--border);border-radius:18px;padding:36px;}
        .icon-wrap{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 22px;}
        .icon-red{background:rgba(239,68,68,.15);border:2px solid rgba(239,68,68,.3);}
        .icon-orange{background:rgba(249,115,22,.15);border:2px solid rgba(249,115,22,.3);}
        h2{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;text-align:center;margin-bottom:6px;}
        .subtitle{color:var(--muted);text-align:center;font-size:.88rem;line-height:1.6;margin-bottom:28px;}
        .detail-box{background:var(--surf2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:22px;}
        .detail-box h4{font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:12px;}
        .d-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:9px;}
        .d-row:last-child{margin-bottom:0;}
        .d-row .lbl{color:var(--muted);}
        .d-row .val{font-weight:600;}
        .warn{border-radius:10px;padding:13px 15px;margin-bottom:20px;font-size:.85rem;line-height:1.6;}
        .warn-red{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
        .warn-orange{background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);color:#fdba74;}
        .warn-green{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;}
        .form-group input,.form-group select{width:100%;padding:11px 14px;background:var(--surf2);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.92rem;outline:none;transition:border-color .2s;}
        .form-group input:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,.15);}
        .form-group select option{background:#1c2128;}
        .alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.4);color:#fca5a5;padding:12px 15px;border-radius:9px;font-size:.88rem;margin-bottom:16px;}
        .btn-row{display:flex;gap:12px;margin-top:8px;}
        .btn{flex:1;padding:13px;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;cursor:pointer;text-align:center;text-decoration:none;display:block;transition:opacity .2s;}
        .btn:hover{opacity:.88;}
        .btn-cancel{background:var(--surf2);color:var(--muted);border:1px solid var(--border);}
        .btn-red{background:var(--red);color:white;}
        .btn-orange{background:var(--accent);color:white;}
        .btn-full{flex:none;width:100%;}
    </style>
</head>
<body>
<nav>
    <a href="index.php" class="logo">ELONCAMP.</a>
    <a href="riwayat.php" class="back">&larr; Kembali ke Riwayat</a>
</nav>
<div class="wrap"><div class="card">

<?php if ($aksi === 'refund'): ?>

    <div class="icon-wrap icon-orange">&#x1F4B8;</div>
    <h2>Ajukan Pengembalian Dana</h2>
    <p class="subtitle">Isi data rekening atau e-wallet tujuan refund.<br>Dana dikembalikan dalam <strong style="color:var(--accent)">1&ndash;3 hari kerja</strong>.</p>

    <?php if ($row['status'] !== 'dibatalkan'): ?>
        <div class="warn warn-red">&#x274C; Refund hanya bisa diajukan untuk pesanan yang sudah dibatalkan.</div>
        <a href="riwayat.php" class="btn btn-cancel btn-full">&larr; Kembali</a>

    <?php elseif ($refund_st === 'diajukan'): ?>
        <div class="warn warn-orange">&#x23F3; Refund sudah diajukan dan sedang diproses admin. Tidak perlu mengajukan ulang.</div>
        <a href="riwayat.php" class="btn btn-cancel btn-full">&larr; Kembali</a>

    <?php elseif ($refund_st === 'disetujui'): ?>
        <div class="warn warn-green">&#x2705; Refund sudah disetujui. Dana sedang atau sudah ditransfer ke rekeningmu.</div>
        <a href="riwayat.php" class="btn btn-cancel btn-full">&larr; Kembali</a>

    <?php elseif ($refund_st === 'ditolak'): ?>
        <div class="warn warn-red">&#x274C; Pengajuan refund ditolak oleh admin. Hubungi ElonCamp untuk info lebih lanjut.</div>
        <a href="riwayat.php" class="btn btn-cancel btn-full">&larr; Kembali</a>

    <?php else: ?>
        <div class="detail-box">
            <h4>&#x1F4CB; Pesanan yang Dibatalkan</h4>
            <div class="d-row"><span class="lbl">Kode</span><span class="val" style="color:var(--accent);font-family:monospace;"><?= htmlspecialchars($row['kode_transaksi']); ?></span></div>
            <div class="d-row"><span class="lbl">Alat</span><span class="val"><?= htmlspecialchars($row['nama_alat']); ?></span></div>
            <div class="d-row"><span class="lbl">Total Dibayar</span><span class="val" style="color:var(--green);"><?= formatRupiah($row['total_harga']); ?></span></div>
        </div>

        <?php if ($error): ?><div class="alert-err">&#x26A0;&#xFE0F; <?= $error; ?></div><?php endif; ?>

        <form method="POST" action="batalkan.php?id=<?= $id_trx; ?>&aksi=refund">
            <div class="form-group">
                <label>Bank / E-Wallet Tujuan <span style="color:var(--red)">*</span></label>
                <select name="nama_bank" required>
                    <option value="">-- Pilih --</option>
                    <option value="BCA">BCA</option>
                    <option value="BNI">BNI</option>
                    <option value="BRI">BRI</option>
                    <option value="Mandiri">Mandiri</option>
                    <option value="GoPay">GoPay</option>
                    <option value="OVO">OVO</option>
                    <option value="DANA">DANA</option>
                    <option value="ShopeePay">ShopeePay</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nomor Rekening / HP E-Wallet <span style="color:var(--red)">*</span></label>
                <input type="text" name="no_rek" placeholder="Contoh: 1234567890 atau 081234567890"
                       value="<?= htmlspecialchars($_POST['no_rek'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Nama Pemilik Rekening <span style="color:var(--red)">*</span></label>
                <input type="text" name="nama_rek" placeholder="Sesuai nama di rekening"
                       value="<?= htmlspecialchars($_POST['nama_rek'] ?? ''); ?>" required>
            </div>
            <div class="warn warn-orange" style="margin-top:18px;">
                &#x26A0;&#xFE0F; Pastikan data rekening sudah benar. ElonCamp tidak bertanggung jawab atas kesalahan transfer akibat data yang salah.
            </div>
            <div class="btn-row" style="margin-top:20px;">
                <a href="riwayat.php" class="btn btn-cancel">Batal</a>
                <button type="submit" class="btn btn-orange">&#x1F4B8; Ajukan Refund</button>
            </div>
        </form>
    <?php endif; ?>

<?php else: ?>

    <div class="icon-wrap icon-red">&#x26A0;&#xFE0F;</div>
    <h2>Batalkan Pesanan?</h2>
    <p class="subtitle">Tindakan ini tidak bisa dibatalkan.<br>Pastikan kamu sudah yakin sebelum melanjutkan.</p>

    <div class="detail-box">
        <h4>&#x1F4CB; Detail Pesanan</h4>
        <div class="d-row"><span class="lbl">Kode</span><span class="val" style="color:var(--accent);font-family:monospace;"><?= htmlspecialchars($row['kode_transaksi']); ?></span></div>
        <div class="d-row"><span class="lbl">Alat</span><span class="val"><?= htmlspecialchars($row['nama_alat']); ?></span></div>
        <div class="d-row"><span class="lbl">Jumlah</span><span class="val"><?= $row['jumlah']; ?> unit</span></div>
        <div class="d-row"><span class="lbl">Tgl Sewa</span><span class="val"><?= date('d M Y', strtotime($row['tgl_sewa'])); ?></span></div>
        <div class="d-row"><span class="lbl">Total</span><span class="val"><?= formatRupiah($row['total_harga']); ?></span></div>
        <div class="d-row"><span class="lbl">Metode</span>
            <span class="val"><?= $metode === 'kirim_ke_alamat' ? '&#x1F69A; Kirim ke Alamat' : '&#x1F3EA; Ambil di Toko'; ?></span>
        </div>
    </div>

    <?php if ($row['status'] !== 'pending'): ?>
        <div class="warn warn-red">
            &#x274C; Pesanan ini tidak bisa dibatalkan karena statusnya sudah
            <strong><?= strtoupper($row['status']); ?></strong>.
            Pembatalan hanya bisa dilakukan saat status masih <strong>PENDING</strong>.
        </div>
        <a href="riwayat.php" class="btn btn-cancel btn-full">&larr; Kembali</a>

    <?php else: ?>
        <?php if ($metode === 'kirim_ke_alamat' && $sudah_bayar): ?>
            <div class="warn warn-orange">
                &#x1F69A; Pesanan ini menggunakan metode <strong>Kirim ke Alamat</strong> dan kamu sudah mengirim bukti transfer.<br><br>
                Setelah dibatalkan, kamu bisa mengajukan <strong>pengembalian dana (refund)</strong> langsung dari halaman Riwayat.
            </div>
        <?php elseif ($metode === 'kirim_ke_alamat'): ?>
            <div class="warn warn-orange">
                &#x1F69A; Pesanan ini menggunakan metode <strong>Kirim ke Alamat</strong>.<br>
                Karena belum ada pembayaran, tidak ada dana yang perlu dikembalikan.
            </div>
        <?php else: ?>
            <div class="warn warn-red">Stok alat akan langsung dikembalikan ke sistem setelah pesanan dibatalkan.</div>
        <?php endif; ?>

        <form method="POST" action="batalkan.php?id=<?= $id_trx; ?>&aksi=batal">
            <div class="btn-row" style="margin-top:20px;">
                <a href="riwayat.php" class="btn btn-cancel">Tidak, Kembali</a>
                <button type="submit" class="btn btn-red">&#x2715; Ya, Batalkan</button>
            </div>
        </form>
    <?php endif; ?>

<?php endif; ?>

</div></div>
</body>
</html>