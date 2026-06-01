<?php
include '../config/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: transaksi.php"); exit;
}

$id = (int)$_GET['id'];

$q = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat, produk.harga_sewa, users.nama, users.no_hp
                           FROM transaksi
                           JOIN produk ON transaksi.id_produk = produk.id_produk
                           JOIN users  ON transaksi.id_user   = users.id_user
                           WHERE transaksi.id_transaksi = '$id'
                           AND transaksi.status = 'mengembalikan'");
$row = mysqli_fetch_assoc($q);

if (!$row) {
    header("Location: transaksi.php"); exit;
}

// Ambil info rekening refund member (dari pengajuan refund sebelumnya atau saat komplain)
$refund_info = $row['refund_info'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal_refund = (int)$_POST['nominal_refund'];
    $catatan_refund = bersihkan($conn, $_POST['catatan_refund'] ?? '');
    $tgl_sekarang   = date('Y-m-d');

    if ($nominal_refund <= 0) {
        $error = "Nominal refund harus lebih dari 0.";
    } else {
        // Selesaikan transaksi + catat refund
        mysqli_query($conn, "UPDATE transaksi
                              SET status                = 'kembali',
                                  tgl_realisasi_kembali = '$tgl_sekarang',
                                  refund_status         = 'disetujui',
                                  refund_info           = CONCAT(IFNULL(refund_info,''), ' | Refund: Rp " . number_format($nominal_refund, 0, ',', '.') . " | Catatan: $catatan_refund')
                              WHERE id_transaksi = '$id'");

        // Kembalikan stok
        mysqli_query($conn, "UPDATE produk SET stok = stok + " . (int)$row['jumlah'] . "
                              WHERE id_produk = '" . (int)$row['id_produk'] . "'");

        header("Location: transaksi.php?msg=refund_selesai");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Refund Komplain | ElonCamp</title>
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh;}
        nav{background:#1e293b;padding:15px 30px;display:flex;justify-content:space-between;align-items:center;}
        .logo{color:#f97316;font-weight:800;font-size:1.1rem;text-decoration:none;}
        .back{color:#94a3b8;text-decoration:none;font-size:.85rem;}
        .wrap{max-width:680px;margin:40px auto;padding:0 20px;}
        h2{font-size:1.3rem;font-weight:700;margin-bottom:4px;}
        .sub{color:#64748b;font-size:.85rem;margin-bottom:25px;}

        .card{background:white;border-radius:12px;padding:24px;box-shadow:0 3px 10px rgba(0,0,0,.06);margin-bottom:20px;}
        .card h3{font-size:.82rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:14px;border-bottom:1px solid #f1f5f9;padding-bottom:8px;}
        .info-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:9px;}
        .info-row .lbl{color:#64748b;}
        .info-row .val{font-weight:600;}

        /* Rekening info */
        .rek-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;}
        .rek-box .rek-title{font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:10px;}
        .rek-item{display:flex;gap:10px;align-items:center;margin-bottom:8px;font-size:.9rem;}
        .rek-item .rek-lbl{color:#64748b;width:100px;flex-shrink:0;}
        .rek-item .rek-val{font-weight:700;color:#1e293b;}
        .rek-item .copy-btn{margin-left:auto;padding:3px 10px;background:#e2e8f0;border:none;border-radius:5px;font-size:.75rem;cursor:pointer;color:#475569;}
        .rek-item .copy-btn:hover{background:#cbd5e1;}
        .no-rek{color:#94a3b8;font-style:italic;font-size:.88rem;}

        .warn{border-radius:8px;padding:13px 15px;margin-bottom:18px;font-size:.85rem;line-height:1.6;}
        .warn-orange{background:#fff7ed;border:1px solid #fed7aa;color:#92400e;}
        .warn-green{background:#f0fdf4;border:1px solid #86efac;color:#166534;}

        /* Total box */
        .total-bayar-box{background:linear-gradient(135deg,#fff7ed,#ffedd5);border:2px solid #fed7aa;border-radius:12px;padding:18px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;}
        .total-bayar-box .lbl{font-size:.85rem;color:#92400e;}
        .total-bayar-box .amount{font-size:1.5rem;font-weight:800;color:#c2410c;font-family:'Courier New',monospace;}

        /* Form */
        .form-group{margin-bottom:18px;}
        .form-group label{display:block;font-weight:600;font-size:.88rem;color:#334155;margin-bottom:7px;}
        .form-group input,.form-group textarea{width:100%;padding:11px 13px;border:1px solid #cbd5e1;border-radius:8px;font-size:.92rem;outline:none;transition:border-color .2s;font-family:'Segoe UI',sans-serif;}
        .form-group input:focus,.form-group textarea:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12);}
        .form-group textarea{resize:vertical;min-height:70px;}
        .input-prefix{display:flex;align-items:center;border:1px solid #cbd5e1;border-radius:8px;overflow:hidden;transition:border-color .2s;}
        .input-prefix:focus-within{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12);}
        .input-prefix span{background:#f1f5f9;padding:11px 14px;font-size:.88rem;font-weight:600;color:#475569;border-right:1px solid #cbd5e1;white-space:nowrap;}
        .input-prefix input{border:none;outline:none;box-shadow:none;flex:1;padding:11px 13px;}
        .input-prefix input:focus{border:none;box-shadow:none;}

        .alert-err{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 14px;border-radius:8px;font-size:.88rem;margin-bottom:16px;}

        .btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,#f97316,#ea580c);color:white;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .2s;}
        .btn-submit:hover{opacity:.9;}
        .link-back{display:block;text-align:center;margin-top:12px;color:#64748b;text-decoration:none;font-size:.85rem;}

        /* Steps */
        .steps{display:flex;flex-direction:column;gap:12px;margin-bottom:22px;}
        .step{display:flex;gap:14px;align-items:flex-start;}
        .step-num{width:28px;height:28px;border-radius:50%;background:#f97316;color:white;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;margin-top:2px;}
        .step-num.done{background:#10b981;}
        .step-txt{font-size:.88rem;color:#334155;line-height:1.55;}
        .step-txt strong{color:#1e293b;}
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="logo">ELONADMIN.</a>
    <a href="transaksi.php" class="back">← Kembali ke Transaksi</a>
</nav>

<div class="wrap">
    <h2>💸 Proses Pengembalian Dana</h2>
    <p class="sub">Barang sudah diterima kembali di toko. Selesaikan transaksi dengan mentransfer dana ke member.</p>

    <!-- Alur ringkas -->
    <div class="card">
        <h3>📋 Alur Proses</h3>
        <div class="steps">
            <div class="step"><span class="step-num done">✓</span><div class="step-txt"><strong>Komplain disetujui</strong> — Admin memverifikasi kerusakan dari video bukti member.</div></div>
            <div class="step"><span class="step-num done">✓</span><div class="step-txt"><strong>Barang dikembalikan</strong> — Member mengirim barang kembali ke toko & sudah diterima.</div></div>
            <div class="step"><span class="step-num">3</span><div class="step-txt"><strong>Transfer dana</strong> — Admin mentransfer dana ke rekening member sesuai nominal, lalu isi form ini sebagai bukti.</div></div>
        </div>
    </div>

    <!-- Detail transaksi -->
    <div class="card">
        <h3>📦 Detail Transaksi</h3>
        <div class="info-row"><span class="lbl">Nama Member</span><span class="val"><?= htmlspecialchars($row['nama']); ?></span></div>
        <div class="info-row"><span class="lbl">No. HP</span><span class="val"><?= htmlspecialchars($row['no_hp'] ?? '-'); ?></span></div>
        <div class="info-row"><span class="lbl">Alat Disewa</span><span class="val"><?= htmlspecialchars($row['nama_alat']); ?></span></div>
        <div class="info-row"><span class="lbl">Jumlah</span><span class="val"><?= $row['jumlah']; ?> unit</span></div>
        <div class="info-row"><span class="lbl">Kode Transaksi</span><span class="val" style="color:#f97316;font-family:monospace;"><?= htmlspecialchars($row['kode_transaksi']); ?></span></div>

        <div class="total-bayar-box" style="margin-top:16px;">
            <div>
                <div class="lbl">Total yang Pernah Dibayar Member</div>
                <div style="font-size:.78rem;color:#92400e;margin-top:2px;">Ini adalah jumlah maksimal yang bisa direfund</div>
            </div>
            <div class="amount"><?= formatRupiah($row['total_harga']); ?></div>
        </div>
    </div>

    <!-- Rekening tujuan transfer -->
    <div class="card">
        <h3>🏦 Rekening Tujuan Transfer</h3>
        <?php if (!empty($refund_info)): ?>
            <?php
            // Parse format: "BCA | 1234567890 | a.n. Nama | ..."
            $parts = explode(' | ', $refund_info);
            $bank  = $parts[0] ?? '-';
            $norek = $parts[1] ?? '-';
            $atas  = $parts[2] ?? '-';
            // Bersihkan prefix "a.n. "
            $atas  = str_replace('a.n. ', '', $atas);
            ?>
            <div class="rek-box">
                <div class="rek-title">Info rekening dari member</div>
                <div class="rek-item">
                    <span class="rek-lbl">Bank / Ewallet</span>
                    <span class="rek-val"><?= htmlspecialchars($bank); ?></span>
                </div>
                <div class="rek-item">
                    <span class="rek-lbl">No. Rekening</span>
                    <span class="rek-val" id="norek"><?= htmlspecialchars($norek); ?></span>
                    <button class="copy-btn" onclick="salin('<?= htmlspecialchars($norek); ?>', this)">Salin</button>
                </div>
                <div class="rek-item">
                    <span class="rek-lbl">Atas Nama</span>
                    <span class="rek-val"><?= htmlspecialchars($atas); ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="warn warn-orange">
                ⚠️ Member belum mengisi data rekening refund. Hubungi member melalui WhatsApp untuk meminta nomor rekening sebelum mentransfer.
            </div>
            <div class="rek-box">
                <div class="rek-title">No. HP Member untuk dihubungi</div>
                <div class="rek-item">
                    <span class="rek-lbl">WhatsApp</span>
                    <span class="rek-val"><?= htmlspecialchars($row['no_hp'] ?? '-'); ?></span>
                    <?php if (!empty($row['no_hp'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $row['no_hp']); ?>" target="_blank"
                       class="copy-btn" style="text-decoration:none;background:#dcfce7;color:#166534;">Chat WA</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Form konfirmasi transfer -->
    <div class="card">
        <h3>✅ Konfirmasi Transfer Sudah Dilakukan</h3>
        <div class="warn warn-orange">
            ⚠️ Isi form ini <strong>setelah kamu benar-benar sudah mentransfer dana</strong> ke rekening member. Form ini hanya sebagai pencatatan — sistem tidak melakukan transfer otomatis.
        </div>

        <?php if ($error): ?>
            <div class="alert-err">⚠️ <?= $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nominal yang Ditransfer (Rp) <span style="color:#ef4444;">*</span></label>
                <div class="input-prefix">
                    <span>Rp</span>
                    <input type="number" name="nominal_refund"
                           value="<?= $row['total_harga']; ?>"
                           min="1" max="<?= $row['total_harga']; ?>" required
                           placeholder="<?= $row['total_harga']; ?>">
                </div>
                <small style="color:#64748b;display:block;margin-top:5px;">
                    Maksimal: <?= formatRupiah($row['total_harga']); ?> — sesuaikan jika ada potongan biaya administrasi.
                </small>
            </div>

            <div class="form-group">
                <label>Catatan (opsional)</label>
                <textarea name="catatan_refund"
                          placeholder="Contoh: Transfer via BCA, ref. 20260523-001"></textarea>
            </div>

            <div class="warn warn-green">
                ✅ Setelah submit: status transaksi berubah ke <strong>KEMBALI</strong>, stok alat dikembalikan ke sistem, dan refund_status member menjadi <strong>DISETUJUI</strong>.
            </div>

            <button type="submit" class="btn-submit">💸 Konfirmasi — Dana Sudah Ditransfer</button>
            <a href="transaksi.php" class="link-back">← Batalkan</a>
        </form>
    </div>
</div>

<script>
function salin(teks, btn) {
    navigator.clipboard.writeText(teks).then(() => {
        btn.textContent = '✅ Disalin';
        btn.style.background = '#dcfce7';
        btn.style.color = '#166534';
        setTimeout(() => {
            btn.textContent = 'Salin';
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}
</script>
</body>
</html>