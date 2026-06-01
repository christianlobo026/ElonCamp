<?php
include 'config/database.php';

// Proteksi: harus login
if (!isLoggedIn()) {
    header("Location: /ElonCamp/auth/login.php");
    exit;
}

// Proteksi: harus ada data SESSION dari sewa.php
if (!isset($_SESSION['ec_bayar']) || empty($_SESSION['ec_bayar'])) {
    header("Location: /ElonCamp/index.php");
    exit;
}

$bayar    = $_SESSION['ec_bayar'];
$id_trx   = (int)$bayar['id_trx'];
$kode     = $bayar['kode'];
$total    = (int)$bayar['total'];
$nm_alat  = $bayar['produk'];

$error   = '';
$sukses  = false;

// ── Proses upload bukti transfer ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['bukti_tf']) || $_FILES['bukti_tf']['error'] !== UPLOAD_ERR_OK) {
        $err_code = $_FILES['bukti_tf']['error'] ?? -1;
        $error = "Harap pilih file bukti transfer. (kode error PHP: $err_code)";
    } else {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['bukti_tf']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Format file harus JPG, JPEG, PNG, atau WEBP.";
        } elseif ($_FILES['bukti_tf']['size'] > 5 * 1024 * 1024) {
            $error = "Ukuran file maksimal 5MB.";
        } else {
            $dir = __DIR__ . '/uploads/bukti_tf/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $fname = 'bukti_' . $id_trx . '_' . time() . '.' . $ext;
            $dest  = $dir . $fname;

            // Cek apakah kolom bukti_transfer sudah ada
            $cek = mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'bukti_transfer'");
            $kolom_ada = (mysqli_num_rows($cek) > 0);

            $upload_ok = true;
            if (is_dir($dir) && is_writable($dir)) {
                $upload_ok = move_uploaded_file($_FILES['bukti_tf']['tmp_name'], $dest);
            }

            if (!$upload_ok) {
                $error = "Gagal upload file. Pastikan folder <code>uploads/bukti_tf/</code> ada dan bisa ditulisi.";
            } else {
                // Simpan nama file ke DB kalau kolom ada
                if ($kolom_ada) {
                    mysqli_query($conn, "UPDATE transaksi SET bukti_transfer='$fname' WHERE id_transaksi='$id_trx'");
                }
                // Bersihkan session
                unset($_SESSION['ec_bayar']);
                // Langsung redirect ke riwayat
                header("Location: riwayat.php?pesan=bukti_terkirim");
                exit;
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
    <title>Pembayaran – ElonCamp</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0d1117; --surf: #161b22; --surf2: #1c2128;
            --border: #30363d; --text: #e6edf3; --muted: #8b949e;
            --accent: #f97316; --accent2: #ea580c;
            --green: #10b981; --red: #ef4444;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        nav {
            background: var(--surf); border-bottom: 1px solid var(--border);
            padding: 16px 6%; display: flex; justify-content: space-between; align-items: center;
        }
        .logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.35rem; color: var(--accent); text-decoration: none; }
        .back  { color: var(--muted); text-decoration: none; font-size: .9rem; }

        .wrap  { max-width: 640px; margin: 0 auto; padding: 50px 20px; }

        /* ── SUKSES ── */
        .sukses-card {
            background: var(--surf); border: 1px solid var(--border); border-radius: 20px;
            padding: 50px 40px; text-align: center;
        }
        .s-icon {
            width: 88px; height: 88px; border-radius: 50%;
            background: linear-gradient(135deg, #f97316, #ea580c);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; margin: 0 auto 28px;
            animation: popIn .5s cubic-bezier(.175,.885,.32,1.275);
        }
        @keyframes popIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .sukses-card h2 { font-family: 'Syne', sans-serif; font-size: 1.7rem; color: var(--accent); margin-bottom: 12px; }
        .sukses-card p  { color: var(--muted); line-height: 1.7; font-size: .95rem; }
        .steps-box { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 22px; margin: 25px 0; text-align: left; }
        .steps-box h4 { font-family: 'Syne', sans-serif; font-size: .82rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin-bottom: 14px; }
        .stp { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 13px; font-size: .9rem; color: #c9d1d9; }
        .stp:last-child { margin-bottom: 0; }
        .stp-dot { width: 24px; height: 24px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; color: white; }
        .stp-dot.done { background: var(--green); }
        .btn-riwayat { display: inline-block; margin-top: 8px; padding: 14px 38px; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: white; text-decoration: none; border-radius: 12px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; }

        /* ── FORM PEMBAYARAN ── */
        .page-title { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; margin-bottom: 5px; }
        .page-sub   { color: var(--muted); font-size: .92rem; margin-bottom: 30px; }

        .kode-badge {
            display: inline-block; background: rgba(249,115,22,.12);
            border: 1px solid rgba(249,115,22,.3); color: var(--accent);
            padding: 5px 14px; border-radius: 6px;
            font-family: 'Courier New', monospace; font-size: .95rem; font-weight: 700;
            margin-bottom: 25px;
        }

        .total-box {
            background: rgba(249,115,22,.1); border: 1px solid rgba(249,115,22,.3);
            border-radius: 14px; padding: 20px 24px; text-align: center; margin-bottom: 25px;
        }
        .total-box small { font-size: .8rem; color: var(--muted); display: block; margin-bottom: 6px; }
        .total-box .amount { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--accent); }

        .bank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; }
        @media (max-width: 500px) { .bank-grid { grid-template-columns: 1fr; } }
        .bank-card {
            background: var(--surf); border: 1px solid var(--border); border-radius: 12px;
            padding: 18px; transition: border-color .2s;
        }
        .bank-card:hover { border-color: rgba(249,115,22,.4); }
        .bank-card .bname { font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; margin-bottom: 4px; }
        .bank-card .bno   { font-family: 'Courier New', monospace; font-size: 1.05rem; color: var(--accent); letter-spacing: .5px; margin-bottom: 3px; cursor: pointer; }
        .bank-card .bown  { font-size: .78rem; color: var(--muted); }
        .copied-hint { font-size: .72rem; color: var(--green); display: none; margin-top: 3px; }

        .upload-section { background: var(--surf); border: 1px solid var(--border); border-radius: 16px; padding: 28px; }
        .upload-section h4 { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 5px; }
        .upload-section p  { font-size: .85rem; color: var(--muted); margin-bottom: 18px; line-height: 1.55; }

        .upload-zone {
            border: 2px dashed var(--border); border-radius: 10px;
            background: var(--bg); position: relative; cursor: pointer;
            transition: border-color .2s, background .2s; overflow: hidden;
        }
        .upload-zone:hover { border-color: var(--accent); background: rgba(249,115,22,.04); }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-inner { padding: 28px 20px; text-align: center; pointer-events: none; }
        .upload-inner .u-ico  { font-size: 2.2rem; margin-bottom: 8px; }
        .upload-inner .u-text { font-size: .85rem; color: var(--muted); line-height: 1.5; }
        .upload-inner .u-text strong { color: var(--accent); }

        .preview-wrap { display: none; padding: 12px; }
        .preview-wrap img { width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; display: block; }
        .preview-name { font-size: .8rem; color: var(--green); text-align: center; margin-top: 6px; }

        .alert-err {
            background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.4);
            color: #fca5a5; padding: 13px 16px; border-radius: 10px;
            margin-bottom: 18px; font-size: .9rem;
        }

        .btn-submit {
            width: 100%; padding: 15px; margin-top: 18px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white; border: none; border-radius: 12px;
            font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: opacity .2s, transform .15s;
        }
        .btn-submit:hover { opacity: .9; transform: translateY(-1px); }
        .btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        .note { font-size: .82rem; color: var(--muted); text-align: center; margin-top: 14px; line-height: 1.5; }
    </style>
</head>
<body>

<nav>
    <a href="/ElonCamp/index.php" class="logo">ELONCAMP.</a>
    <a href="/ElonCamp/riwayat.php" class="back">Riwayat Sewa →</a>
</nav>

<div class="wrap">

<?php if ($sukses): ?>
<!-- ══ SUKSES TRANSFER ══ -->
<div class="sukses-card">
    <div class="s-icon">⏳</div>
    <h2>Bukti Transfer Terkirim!</h2>
    <p>Pembayaran untuk pesanan <strong><?= htmlspecialchars($nm_alat); ?></strong> sedang dalam proses verifikasi oleh admin ElonCamp.</p>
    <div class="steps-box">
        <h4>📋 Status Pesanan</h4>
        <div class="stp"><span class="stp-dot done">✓</span><span>Pesanan berhasil dibuat.</span></div>
        <div class="stp"><span class="stp-dot done">✓</span><span>Bukti transfer telah dikirim.</span></div>
        <div class="stp"><span class="stp-dot">2</span><span>Admin memverifikasi pembayaran <em>(maks. 1×24 jam kerja)</em>.</span></div>
        <div class="stp"><span class="stp-dot">3</span><span>Alat camping disiapkan &amp; dikirimkan ke alamatmu.</span></div>
    </div>
    <p style="font-size:.83rem;">Pantau status di halaman <strong style="color:var(--accent);">Riwayat Sewa</strong>.</p>
    <br>
    <a href="riwayat.php" class="btn-riwayat">Lihat Riwayat Sewa →</a>
</div>

<?php else: ?>
<!-- ══ FORM PEMBAYARAN ══ -->
<h2 class="page-title">💳 Selesaikan Pembayaran</h2>
<p class="page-sub">Transfer sesuai nominal di bawah, lalu upload bukti transfermu.</p>

<div class="kode-badge">Kode Pesanan: <?= htmlspecialchars($kode); ?></div>

<div class="total-box">
    <small>Total yang harus ditransfer</small>
    <div class="amount"><?= formatRupiah($total); ?></div>
</div>

<!-- Rekening -->
<div class="bank-grid">
    <div class="bank-card">
        <div class="bname">🏦 BCA</div>
        <div class="bno" onclick="salin(this, '1234567890')">1234 5678 90</div>
        <div class="copied-hint">✅ Disalin!</div>
        <div class="bown">a.n. ElonCamp Official</div>
    </div>
    <div class="bank-card">
        <div class="bname">🏦 BNI</div>
        <div class="bno" onclick="salin(this, '9876543210')">9876 5432 10</div>
        <div class="copied-hint">✅ Disalin!</div>
        <div class="bown">a.n. ElonCamp Official</div>
    </div>
    <div class="bank-card">
        <div class="bname">💚 GoPay</div>
        <div class="bno" onclick="salin(this, '081200000000')">0812-0000-0000</div>
        <div class="copied-hint">✅ Disalin!</div>
        <div class="bown">a.n. ElonCamp</div>
    </div>
    <div class="bank-card">
        <div class="bname">💙 OVO / DANA</div>
        <div class="bno" onclick="salin(this, '081300000000')">0813-0000-0000</div>
        <div class="copied-hint">✅ Disalin!</div>
        <div class="bown">a.n. ElonCamp</div>
    </div>
</div>

<!-- Upload Bukti -->
<?php if ($error): ?>
    <div class="alert-err">⚠️ <?= $error; ?></div>
<?php endif; ?>

<div class="upload-section">
    <h4>📎 Upload Bukti Transfer</h4>
    <p>Foto atau screenshot struk/notifikasi transfer. Format JPG/PNG/WEBP, maksimal 5MB.</p>

    <form method="POST" enctype="multipart/form-data" id="formBayar">

        <div class="upload-zone" id="uploadZone">
            <input type="file" name="bukti_tf" id="inputBukti" accept="image/*" required
                   onchange="previewFile(this)">
            <div class="upload-inner" id="uploadInner">
                <div class="u-ico">🖼️</div>
                <div class="u-text">Klik atau drag foto bukti transfer ke sini<br><strong>JPG / PNG / WEBP</strong> · maks 5MB</div>
            </div>
            <div class="preview-wrap" id="previewWrap">
                <img id="previewImg" src="" alt="preview">
                <div class="preview-name" id="previewName"></div>
            </div>
        </div>

        <button type="submit" class="btn-submit" id="btnKirim" disabled>
            📤 Kirim Bukti Transfer
        </button>
    </form>

    <p class="note">Klik nomor rekening untuk menyalinnya otomatis. Setelah bukti terkirim, tim kami akan memverifikasi dalam 1×24 jam.</p>
</div>

<?php endif; ?>

</div><!-- /wrap -->

<script>
function previewFile(input) {
    const inner   = document.getElementById('uploadInner');
    const wrap    = document.getElementById('previewWrap');
    const img     = document.getElementById('previewImg');
    const name    = document.getElementById('previewName');
    const btnKirim = document.getElementById('btnKirim');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            name.textContent = '✅ ' + input.files[0].name;
            inner.style.display = 'none';
            wrap.style.display  = 'block';
            btnKirim.disabled = false;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function salin(el, nomor) {
    navigator.clipboard.writeText(nomor).then(() => {
        const hint = el.nextElementSibling;
        hint.style.display = 'block';
        setTimeout(() => hint.style.display = 'none', 2000);
    }).catch(() => {
        // fallback untuk browser lama
        const tmp = document.createElement('input');
        tmp.value = nomor;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        const hint = el.nextElementSibling;
        hint.style.display = 'block';
        setTimeout(() => hint.style.display = 'none', 2000);
    });
}
</script>

</body>
</html>