<?php 
include 'config/database.php'; 

if (!isLoggedIn()) {
    header("Location: /ElonCamp/auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: /ElonCamp/index.php");
    exit;
}

$id_produk = mysqli_real_escape_string($conn, $_GET['id']);
$result    = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk = '$id_produk'");
$produk    = mysqli_fetch_assoc($result);

if (!$produk) {
    header("Location: /ElonCamp/index.php");
    exit;
}

if ($produk['stok'] <= 0) {
    echo "<script>alert('Maaf, stok alat ini sedang habis!'); window.location.href='/ElonCamp/index.php';</script>";
    exit;
}

// ─────────────────────────────────────────────
// Deteksi apakah kolom baru sudah ada di tabel
// ─────────────────────────────────────────────
$cek_kolom   = mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'metode_ambil'");
$kolom_baru  = (mysqli_num_rows($cek_kolom) > 0);

$error             = '';
$step = 'form';

// Deteksi redirect sukses dari header() di atas
if (isset($_GET['sukses']) && $_GET['sukses'] === 'ambil') {
    $step = 'sukses_ambil';
}
$id_trx            = null;
$total_bayar_final = 0;
$kode_trx_tampil   = '';

// ══════════════════════════════════════════════════════════════
// AKSI A: Upload Bukti Transfer
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'upload_bukti'
) {
    $id_trx_post = intval($_POST['id_transaksi'] ?? 0);
    $total_bayar_final = isset($_SESSION['ec_total_tmp'])  ? $_SESSION['ec_total_tmp']  : 0;
    $kode_trx_tampil   = isset($_SESSION['ec_kode_tmp'])   ? $_SESSION['ec_kode_tmp']   : '';
    $id_trx = $id_trx_post;

    $file_ok  = isset($_FILES['bukti_tf']) && $_FILES['bukti_tf']['error'] === UPLOAD_ERR_OK;

    if (!$file_ok) {
        $error = "Harap pilih file bukti transfer sebelum mengirim.";
        $step  = 'payment';
    } else {
        $allowed  = ['jpg','jpeg','png','webp'];
        $ext      = strtolower(pathinfo($_FILES['bukti_tf']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Format file harus JPG, JPEG, PNG, atau WEBP.";
            $step  = 'payment';
        } elseif ($_FILES['bukti_tf']['size'] > 5 * 1024 * 1024) {
            $error = "Ukuran file maksimal 5MB.";
            $step  = 'payment';
        } else {
            $dir = __DIR__ . '/uploads/bukti_tf/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $fname = 'bukti_' . $id_trx_post . '_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['bukti_tf']['tmp_name'], $dir . $fname)) {
                if ($kolom_baru) {
                    mysqli_query($conn, "UPDATE transaksi SET bukti_transfer='$fname' WHERE id_transaksi='$id_trx_post'");
                }
                unset($_SESSION['ec_total_tmp'], $_SESSION['ec_kode_tmp']);
                $step = 'sukses_transfer';
            } else {
                $error = "Gagal menyimpan file. Pastikan folder <code>uploads/bukti_tf/</code> sudah ada dan bisa ditulisi (chmod 755).";
                $step  = 'payment';
            }
        }
    }

// ══════════════════════════════════════════════════════════════
// AKSI B: Submit Form Sewa
// ══════════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'sewa'
) {
    $id_user     = $_SESSION['user_id'];
    $tgl_sewa    = trim($_POST['tgl_sewa']    ?? '');
    $tgl_kembali = trim($_POST['tgl_kembali'] ?? '');
    $jumlah      = (int)($_POST['jumlah']     ?? 0);
    $metode      = $_POST['metode_ambil']     ?? 'ambil_di_tempat';
    $kode_trx    = buatKodeTransaksi();

    // ── Validasi dasar ──────────────────────────────────────
    $tgl1 = null;
    $tgl2 = null;

    if (empty($tgl_sewa) || empty($tgl_kembali)) {
        $error = "Tanggal sewa dan tanggal kembali wajib diisi.";
    } else {
        $tgl1 = new DateTime($tgl_sewa);
        $tgl2 = new DateTime($tgl_kembali);

        if ($tgl2 <= $tgl1) {
            $error = "Tanggal kembali harus setelah tanggal sewa.";
        } elseif ($jumlah <= 0) {
            $error = "Jumlah alat minimal 1 unit.";
        } elseif ($jumlah > $produk['stok']) {
            $error = "Jumlah melebihi stok tersedia (" . $produk['stok'] . " unit).";
        }
    }

    // ── Variabel pengiriman (default kosong / 0) ────────────
    $nama_penerima   = '';
    $no_hp_penerima  = '';
    $alamat_kirim    = '';
    $foto_ktp_nama   = '';
    $selfie_ktp_nama = '';
    $catatan         = '';
    $ongkos_kirim    = 0;

    // ── Validasi & upload foto identitas (khusus kirim) ─────
    if (empty($error) && $metode === 'kirim_ke_alamat') {

        $nama_penerima  = trim($_POST['nama_penerima']  ?? '');
        $no_hp_penerima = trim($_POST['no_hp_penerima'] ?? '');
        $alamat_kirim   = trim($_POST['alamat_kirim']   ?? '');
        $catatan        = trim($_POST['catatan']        ?? '');
        $ongkos_kirim   = 0; // Ongkir ditanggung penyewa (Gojek/Grab sendiri)

        if (empty($nama_penerima)) {
            $error = "Nama penerima wajib diisi.";
        } elseif (empty($no_hp_penerima)) {
            $error = "Nomor HP penerima wajib diisi.";
        } elseif (empty($alamat_kirim)) {
            $error = "Alamat pengiriman wajib diisi.";
        } elseif (!isset($_FILES['foto_ktp']) || $_FILES['foto_ktp']['error'] !== UPLOAD_ERR_OK) {
            $ktp_err = $_FILES['foto_ktp']['error'] ?? -1;
            $error = "Foto KTP wajib diupload. (PHP error code: $ktp_err — pastikan ukuran file tidak melebihi batas upload_max_filesize di php.ini)";
        } elseif (!isset($_FILES['selfie_ktp']) || $_FILES['selfie_ktp']['error'] !== UPLOAD_ERR_OK) {
            $selfie_err = $_FILES['selfie_ktp']['error'] ?? -1;
            $error = "Foto selfie memegang KTP wajib diupload. (PHP error code: $selfie_err)";
        } else {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext_k   = strtolower(pathinfo($_FILES['foto_ktp']['name'],   PATHINFO_EXTENSION));
            $ext_s   = strtolower(pathinfo($_FILES['selfie_ktp']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext_k, $allowed) || !in_array($ext_s, $allowed)) {
                $error = "Format foto KTP & selfie harus JPG atau PNG.";
            } elseif ($_FILES['foto_ktp']['size'] > 5*1024*1024 || $_FILES['selfie_ktp']['size'] > 5*1024*1024) {
                $error = "Ukuran setiap foto maksimal 5MB.";
            } else {
                $dir_id = __DIR__ . '/uploads/identitas/';
                if (!is_dir($dir_id)) {
                    @mkdir($dir_id, 0755, true);
                }

                // Jika folder masih tidak ada / tidak bisa ditulis, simpan nama saja (file tidak di-save)
                $foto_ktp_nama   = 'ktp_'    . $id_user . '_' . time() . '.' . $ext_k;
                $selfie_ktp_nama = 'selfie_' . $id_user . '_' . time() . '.' . $ext_s;

                if (is_dir($dir_id) && is_writable($dir_id)) {
                    $ok1 = move_uploaded_file($_FILES['foto_ktp']['tmp_name'],   $dir_id . $foto_ktp_nama);
                    $ok2 = move_uploaded_file($_FILES['selfie_ktp']['tmp_name'], $dir_id . $selfie_ktp_nama);
                    if (!$ok1 || !$ok2) {
                        $error = "Gagal menyimpan foto identitas. Cek permission folder <code>uploads/identitas/</code>.";
                    }
                }
                // Jika folder tidak ada, tetap lanjut INSERT (nama file tetap tersimpan, file fisik tidak ada)
            }
        }
    }

    // ── INSERT ke database ──────────────────────────────────
    if (empty($error) && $tgl1 !== null && $tgl2 !== null) {
        $durasi      = $tgl1->diff($tgl2)->days;
        $total_sewa  = $durasi * $produk['harga_sewa'] * $jumlah;
        $total_bayar = $total_sewa; // Ongkir tidak termasuk dalam sistem

        // Sanitasi
        $np_s  = mysqli_real_escape_string($conn, $nama_penerima);
        $nhp_s = mysqli_real_escape_string($conn, $no_hp_penerima);
        $al_s  = mysqli_real_escape_string($conn, $alamat_kirim);
        $ct_s  = mysqli_real_escape_string($conn, $catatan);
        $fk_s  = mysqli_real_escape_string($conn, $foto_ktp_nama);
        $sk_s  = mysqli_real_escape_string($conn, $selfie_ktp_nama);

        // Selalu INSERT dengan kolom dasar yang pasti ada
        $sql = "INSERT INTO transaksi
                    (kode_transaksi, id_user, id_produk, jumlah,
                     tgl_sewa, tgl_kembali, total_harga, status,
                     denda, kondisi)
                VALUES
                    ('$kode_trx', '$id_user', '$id_produk', '$jumlah',
                     '$tgl_sewa', '$tgl_kembali', '$total_bayar', 'pending',
                     0, 'normal')";

        $insert = mysqli_query($conn, $sql);

        if ($insert) {
            $id_trx = mysqli_insert_id($conn);

            // Kurangi stok
            mysqli_query($conn, "UPDATE produk SET stok = stok - $jumlah WHERE id_produk = '$id_produk'");

            // Update kolom tambahan kalau migration sudah dijalankan
            if ($kolom_baru) {
                mysqli_query($conn, "UPDATE transaksi SET
                    metode_ambil   = '$metode',
                    nama_penerima  = '$np_s',
                    no_hp_penerima = '$nhp_s',
                    alamat_kirim   = '$al_s',
                    foto_ktp       = '$fk_s',
                    selfie_ktp     = '$sk_s',
                    catatan        = '$ct_s',
                    ongkos_kirim   = '$ongkos_kirim'
                    WHERE id_transaksi = '$id_trx'");
            }

            if ($metode === 'ambil_di_tempat') {
                // Hard redirect — tidak ada ambiguitas render
                header("Location: sewa.php?id=$id_produk&sukses=ambil&trx=" . urlencode($kode_trx));
                exit;
            } else {
                // Simpan data di SESSION lalu redirect ke halaman pembayaran terpisah
                $_SESSION['ec_bayar'] = [
                    'id_trx'  => $id_trx,
                    'kode'    => $kode_trx,
                    'total'   => $total_bayar,
                    'produk'  => $produk['nama_alat'],
                ];
                header("Location: pembayaran.php");
                exit;
            }
        } else {
            $error = "Gagal menyimpan transaksi: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sewa <?= htmlspecialchars($produk['nama_alat']); ?> – ElonCamp</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --bg:#0d1117; --surf:#161b22; --surf2:#1c2128; --border:#30363d;
            --text:#e6edf3; --muted:#8b949e;
            --accent:#f97316; --accent2:#ea580c;
            --green:#10b981; --red:#ef4444; --blue:#3b82f6;
        }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

        /* NAV */
        nav { background:var(--surf); border-bottom:1px solid var(--border); padding:16px 6%; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:99; }
        .logo { font-family:'Syne',sans-serif; font-weight:800; font-size:1.35rem; color:var(--accent); text-decoration:none; }
        .back-link { color:var(--muted); text-decoration:none; font-size:.9rem; transition:color .2s; }
        .back-link:hover { color:var(--text); }

        /* WRAP */
        .wrap { max-width:1080px; margin:0 auto; padding:50px 6%; }

        /* TWO-COL */
        .two-col { display:grid; grid-template-columns:1fr 1.45fr; gap:40px; align-items:start; }
        @media(max-width:800px){ .two-col { grid-template-columns:1fr; } }

        /* PRODUCT CARD */
        .prod-card { background:var(--surf); border:1px solid var(--border); border-radius:20px; padding:32px; position:sticky; top:80px; }
        .prod-emoji { font-size:4.5rem; text-align:center; background:var(--surf2); border-radius:14px; padding:28px; margin-bottom:22px; border:1px dashed var(--border); }
        .prod-card h2 { font-family:'Syne',sans-serif; font-size:1.5rem; margin-bottom:8px; }
        .prod-card .desc { color:var(--muted); font-size:.88rem; line-height:1.65; margin-bottom:18px; }
        .price-badge { display:inline-block; background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:var(--green); padding:7px 14px; border-radius:8px; font-weight:700; font-family:'Syne',sans-serif; }
        .stok-note { margin-top:10px; font-size:.82rem; color:var(--muted); }
        .stok-note strong { color:var(--blue); }

        /* FORM CARD */
        .form-card { background:var(--surf); border:1px solid var(--border); border-radius:20px; padding:38px; }
        .form-card h3 { font-family:'Syne',sans-serif; font-size:1.35rem; margin-bottom:5px; }
        .form-card .subtitle { color:var(--muted); font-size:.88rem; margin-bottom:28px; }
        hr.div { border:none; border-top:1px solid var(--border); margin:22px 0; }

        /* FORM ELEMENTS */
        .fg { margin-bottom:20px; }
        .fg label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:7px; }
        .fg label .req { color:var(--red); }
        .fg input[type=text],
        .fg input[type=number],
        .fg input[type=date],
        .fg textarea {
            width:100%; padding:11px 14px;
            background:var(--surf2); border:1px solid var(--border);
            border-radius:9px; color:var(--text);
            font-family:'DM Sans',sans-serif; font-size:.93rem;
            transition:border-color .2s,box-shadow .2s; outline:none;
        }
        .fg input:focus, .fg textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(249,115,22,.15); }
        .fg textarea { resize:vertical; min-height:88px; }
        .row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media(max-width:560px){ .row2 { grid-template-columns:1fr; } }

        /* METODE TOGGLE */
        .metode-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:22px; }
        .m-opt { position:relative; }
        .m-opt input[type=radio] { position:absolute; opacity:0; width:0; }
        .m-lbl { display:flex; flex-direction:column; align-items:center; gap:7px; padding:18px 12px; border:2px solid var(--border); border-radius:13px; cursor:pointer; text-align:center; background:var(--surf2); transition:all .2s; }
        .m-lbl .mi { font-size:1.9rem; }
        .m-lbl .mn { font-weight:700; font-size:.88rem; font-family:'Syne',sans-serif; }
        .m-lbl .ms { font-size:.72rem; color:var(--muted); }
        .m-opt input:checked + .m-lbl { border-color:var(--accent); background:rgba(249,115,22,.1); }
        .m-lbl:hover { border-color:rgba(249,115,22,.45); }

        /* SECTION KIRIM */
        #sec-kirim { background:var(--surf2); border:1px solid var(--border); border-radius:14px; padding:24px; margin-bottom:22px; display:none; }
        #sec-kirim.show { display:block; animation:slideDown .3s ease; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
        .sec-head { font-family:'Syne',sans-serif; font-size:.95rem; font-weight:700; color:var(--accent); margin-bottom:16px; }

        /* UPLOAD BOX */
        .up-wrap { position:relative; border:2px dashed var(--border); border-radius:10px; background:var(--bg); overflow:hidden; cursor:pointer; transition:border-color .2s; }
        .up-wrap:hover { border-color:var(--accent); }
        .up-wrap input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .up-inner { padding:18px; text-align:center; pointer-events:none; }
        .up-inner .ui { font-size:1.8rem; margin-bottom:5px; }
        .up-inner .ut { font-size:.78rem; color:var(--muted); line-height:1.5; }
        .up-inner .ut strong { color:var(--accent); }
        .up-prev { display:none; padding:8px; }
        .up-prev img { width:100%; max-height:150px; object-fit:cover; border-radius:7px; }
        .up-prev .fn { font-size:.75rem; color:var(--green); text-align:center; margin-top:5px; word-break:break-all; }

        /* TOTAL BOX */
        .total-box { background:var(--surf2); border:1px solid var(--border); border-radius:12px; padding:18px; margin-bottom:20px; }
        .tr-item { display:flex; justify-content:space-between; font-size:.88rem; color:var(--muted); margin-bottom:7px; }
        .tr-item.grand { font-family:'Syne',sans-serif; font-size:1.05rem; font-weight:700; color:var(--accent); border-top:1px solid var(--border); padding-top:11px; margin-top:8px; margin-bottom:0; }

        /* BTN */
        .btn-submit { width:100%; padding:15px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:white; border:none; border-radius:11px; font-family:'Syne',sans-serif; font-size:.98rem; font-weight:700; cursor:pointer; transition:opacity .2s,transform .15s; }
        .btn-submit:hover { opacity:.9; transform:translateY(-1px); }

        /* ALERT */
        .alert-err { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.35); color:#fca5a5; padding:13px 16px; border-radius:9px; margin-bottom:20px; font-size:.88rem; line-height:1.7; }

        /* ───── PAYMENT ───── */
        .pay-wrap { max-width:600px; margin:50px auto; padding:0 20px; }
        .pay-wrap h2 { font-family:'Syne',sans-serif; font-size:1.75rem; font-weight:800; margin-bottom:6px; }
        .pay-sub { color:var(--muted); font-size:.9rem; margin-bottom:25px; line-height:1.6; }
        .pay-kode { color:var(--accent); font-weight:700; }
        .total-tf { background:rgba(249,115,22,.1); border:1px solid rgba(249,115,22,.3); border-radius:12px; padding:18px; text-align:center; margin-bottom:22px; }
        .total-tf small { font-size:.78rem; color:var(--muted); display:block; margin-bottom:4px; }
        .total-tf .amount { font-family:'Syne',sans-serif; font-size:1.9rem; font-weight:800; color:var(--accent); }
        .bank-grid { display:grid; grid-template-columns:1fr 1fr; gap:13px; margin-bottom:25px; }
        @media(max-width:520px){ .bank-grid { grid-template-columns:1fr; } }
        .bank-card { background:var(--surf); border:1px solid var(--border); border-radius:13px; padding:18px; }
        .bank-card .bn { font-family:'Syne',sans-serif; font-weight:700; font-size:.95rem; margin-bottom:3px; }
        .bank-card .bno { font-family:'Courier New',monospace; font-size:1.05rem; color:var(--accent); letter-spacing:.8px; margin-bottom:3px; }
        .bank-card .bo { font-size:.78rem; color:var(--muted); }
        .up-tf-card { background:var(--surf); border:1px solid var(--border); border-radius:14px; padding:25px; }
        .up-tf-card h4 { font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin-bottom:5px; }
        .up-tf-card p { font-size:.83rem; color:var(--muted); margin-bottom:16px; line-height:1.5; }

        /* ───── SUKSES ───── */
        .sukses-wrap { max-width:520px; margin:70px auto; text-align:center; padding:0 20px; }
        .s-icon { width:96px; height:96px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2.8rem; margin:0 auto 28px; animation:popIn .5s cubic-bezier(.175,.885,.32,1.275); }
        .s-icon.green  { background:linear-gradient(135deg,var(--green),#059669); }
        .s-icon.orange { background:linear-gradient(135deg,var(--accent),var(--accent2)); }
        @keyframes popIn { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
        .sukses-wrap h2 { font-family:'Syne',sans-serif; font-size:1.9rem; font-weight:800; margin-bottom:10px; }
        .sukses-wrap h2.green  { color:var(--green); }
        .sukses-wrap h2.orange { color:var(--accent); }
        .sukses-wrap p { color:var(--muted); line-height:1.7; margin-bottom:10px; font-size:.93rem; }
        .info-steps { background:var(--surf); border:1px solid var(--border); border-radius:13px; padding:22px; margin:25px 0; text-align:left; }
        .info-steps h4 { font-family:'Syne',sans-serif; font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); margin-bottom:14px; }
        .stp { display:flex; gap:13px; align-items:flex-start; margin-bottom:13px; font-size:.88rem; color:#c9d1d9; }
        .stp:last-child { margin-bottom:0; }
        .stp-dot { width:24px; height:24px; border-radius:50%; background:var(--accent); display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:bold; flex-shrink:0; margin-top:1px; }
        .stp-dot.done { background:var(--green); }
        .btn-riwayat { display:inline-block; padding:14px 38px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:white; text-decoration:none; border-radius:11px; font-family:'Syne',sans-serif; font-weight:700; font-size:.93rem; transition:opacity .2s; }
        .btn-riwayat:hover { opacity:.9; }
    </style>
</head>
<body>

<nav>
    <a href="/ElonCamp/index.php" class="logo">ELONCAMP.</a>
    <a href="/ElonCamp/index.php" class="back-link">← Kembali ke Katalog</a>
</nav>

<?php /* ══ SUKSES AMBIL DI TEMPAT ══ */ if ($step === 'sukses_ambil'): ?>

<div class="wrap"><div class="sukses-wrap">
    <div class="s-icon green">🏕️</div>
    <h2 class="green">Pesanan Berhasil!</h2>
    <p>Permintaan sewa <strong><?= htmlspecialchars($produk['nama_alat']); ?></strong> sudah diterima dan menunggu konfirmasi admin.</p>
    <div class="info-steps">
        <h4>📋 Langkah Selanjutnya</h4>
        <div class="stp"><span class="stp-dot done">✓</span><span>Pesanan berhasil dibuat di sistem.</span></div>
        <div class="stp"><span class="stp-dot">1</span><span>Datang ke toko <strong>ElonCamp</strong> sesuai tanggal sewa yang dipilih.</span></div>
        <div class="stp"><span class="stp-dot">2</span><span>Bawa <strong>KTP / Kartu Identitas asli</strong> untuk verifikasi di kasir.</span></div>
        <div class="stp"><span class="stp-dot">3</span><span>Bayar sesuai total di kasir & ambil perlengkapan campingmu.</span></div>
        <div class="stp"><span class="stp-dot">4</span><span>Kembalikan alat tepat waktu agar tidak kena denda keterlambatan.</span></div>
    </div>
    <p style="font-size:.83rem;">📍 <strong>Alamat Toko:</strong> Jl. Petualang Sejati No. 17, Manado</p>
    <p style="font-size:.83rem; margin-top:5px;">📞 <strong>WA Admin:</strong> 0812-XXXX-XXXX</p>
    <br>
    <a href="riwayat.php?pesan=sukses_sewa" class="btn-riwayat">Lihat Riwayat Sewa →</a>
</div></div>

<?php /* ══ HALAMAN PEMBAYARAN ══ */ elseif ($step === 'payment' && $id_trx): ?>

<div class="wrap"><div class="pay-wrap">
    <h2>💳 Selesaikan Pembayaran</h2>
    <p class="pay-sub">
        Kode Pesanan: <span class="pay-kode"><?= htmlspecialchars($kode_trx_tampil); ?></span><br>
        Transfer ke salah satu rekening di bawah, lalu upload bukti transfernya.
    </p>

    <div class="total-tf">
        <small>Total yang harus ditransfer</small>
        <div class="amount"><?= formatRupiah($total_bayar_final); ?></div>
        <small style="margin-top:4px;"></small>
    </div>

    <div class="bank-grid">
        <div class="bank-card">
            <div class="bn">🏦 BCA</div>
            <div class="bno">1234 5678 90</div>
            <div class="bo">a.n. ElonCamp Official</div>
        </div>
        <div class="bank-card">
            <div class="bn">🏦 BNI</div>
            <div class="bno">9876 5432 10</div>
            <div class="bo">a.n. ElonCamp Official</div>
        </div>
        <div class="bank-card">
            <div class="bn">💚 GoPay</div>
            <div class="bno">0812-XXXX-XXXX</div>
            <div class="bo">a.n. ElonCamp</div>
        </div>
        <div class="bank-card">
            <div class="bn">💙 OVO / DANA</div>
            <div class="bno">0813-XXXX-XXXX</div>
            <div class="bo">a.n. ElonCamp</div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-err">⚠️ <?= $error; ?></div>
    <?php endif; ?>

    <div class="up-tf-card">
        <h4>📎 Upload Bukti Transfer</h4>
        <p>Foto atau screenshot struk/riwayat transfer. Format JPG/PNG/WEBP, maks. 5MB.</p>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"       value="upload_bukti">
            <input type="hidden" name="id_transaksi" value="<?= $id_trx; ?>">
            <!-- Pertahankan id_produk di URL supaya PHP tidak redirect ke index -->
            <input type="hidden" name="_dummy_produk" value="">

            <div class="up-wrap">
                <input type="file" name="bukti_tf" accept="image/*" required
                       onchange="prevUp(this,'pTf','iTf','imgTf','nTf')">
                <div class="up-inner" id="iTf">
                    <div class="ui">🖼️</div>
                    <div class="ut">Klik atau seret foto bukti transfer<br><strong>JPG / PNG / WEBP</strong> · Maks 5MB</div>
                </div>
                <div class="up-prev" id="pTf">
                    <img id="imgTf" src="" alt="">
                    <div class="fn" id="nTf"></div>
                </div>
            </div>

            <button type="submit" class="btn-submit" style="margin-top:18px;">
                ✅ Kirim Bukti Transfer
            </button>
        </form>
    </div>
</div></div>

<?php /* ══ SUKSES TRANSFER ══ */ elseif ($step === 'sukses_transfer'): ?>

<div class="wrap"><div class="sukses-wrap">
    <div class="s-icon orange">⏳</div>
    <h2 class="orange">Menunggu Verifikasi</h2>
    <p>Bukti transfer sudah berhasil dikirim. Tim <strong>ElonCamp</strong> akan segera memverifikasi pembayaranmu.</p>
    <div class="info-steps">
        <h4>📋 Status Pesanan</h4>
        <div class="stp"><span class="stp-dot done">✓</span><span>Pesanan berhasil dibuat.</span></div>
        <div class="stp"><span class="stp-dot done">✓</span><span>Bukti transfer telah dikirim.</span></div>
        <div class="stp"><span class="stp-dot">2</span><span>Admin memverifikasi pembayaran (maks. 1×24 jam kerja).</span></div>
        <div class="stp"><span class="stp-dot">3</span><span>Alat camping disiapkan & dikirimkan ke alamatmu.</span></div>
    </div>
    <p style="font-size:.83rem;">Pantau status di halaman <strong style="color:var(--accent);">Riwayat Sewa</strong>.</p>
    <br>
    <a href="riwayat.php" class="btn-riwayat">Lihat Riwayat Sewa →</a>
</div></div>

<?php /* ══ FORM SEWA UTAMA ══ */ else: ?>

<div class="wrap">
<div class="two-col">

    <div class="prod-card">
        <div class="prod-emoji">⛺</div>
        <h2><?= htmlspecialchars($produk['nama_alat']); ?></h2>
        <p class="desc"><?= htmlspecialchars($produk['deskripsi']); ?></p>
        <span class="price-badge"><?= formatRupiah($produk['harga_sewa']); ?> <span style="font-weight:400;font-size:.82rem;">/ hari</span></span>
        <p class="stok-note">Stok tersedia: <strong><?= $produk['stok']; ?> unit</strong></p>
    </div>

    <div class="form-card">
        <h3>Form Penyewaan</h3>
        <p class="subtitle">Lengkapi semua data di bawah untuk memproses pesananmu.</p>

        <?php if ($error): ?>
            <div class="alert-err">⚠️ <?= $error; ?></div>
            <?php if (!empty($_POST)): ?>
            <details style="margin-bottom:16px; font-size:.78rem; color:var(--muted); background:var(--surf2); padding:12px; border-radius:8px; border:1px solid var(--border);">
                <summary style="cursor:pointer; font-weight:600; color:var(--muted);">🔍 Debug Info (klik untuk lihat)</summary>
                <br>
                <strong>POST:</strong> metode_ambil = <?= htmlspecialchars($_POST['metode_ambil'] ?? '-'); ?>,
                nama_penerima = <?= htmlspecialchars($_POST['nama_penerima'] ?? '-'); ?>,
                tgl_sewa = <?= htmlspecialchars($_POST['tgl_sewa'] ?? '-'); ?>
                <br><br>
                <strong>FILES foto_ktp:</strong>
                error = <?= $_FILES['foto_ktp']['error'] ?? 'tidak ada'; ?>,
                size = <?= $_FILES['foto_ktp']['size'] ?? 0; ?> bytes
                <br>
                <strong>FILES selfie_ktp:</strong>
                error = <?= $_FILES['selfie_ktp']['error'] ?? 'tidak ada'; ?>,
                size = <?= $_FILES['selfie_ktp']['size'] ?? 0; ?> bytes
                <br><br>
                <strong>Kolom baru (migration):</strong> <?= $kolom_baru ? 'Sudah ada ✅' : 'BELUM — jalankan migration_sewa_update.sql ❌'; ?>
            </details>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="formSewa">
            <input type="hidden" name="action" value="sewa">

            <div class="row2">
                <div class="fg">
                    <label>Tanggal Sewa <span class="req">*</span></label>
                    <input type="date" name="tgl_sewa" id="tgl_sewa" required min="<?= date('Y-m-d'); ?>"
                           value="<?= htmlspecialchars($_POST['tgl_sewa'] ?? ''); ?>">
                </div>
                <div class="fg">
                    <label>Tanggal Kembali <span class="req">*</span></label>
                    <input type="date" name="tgl_kembali" id="tgl_kembali" required
                           min="<?= date('Y-m-d', strtotime('+1 day')); ?>"
                           value="<?= htmlspecialchars($_POST['tgl_kembali'] ?? ''); ?>">
                </div>
            </div>

            <div class="fg">
                <label>Jumlah Unit <span class="req">*</span></label>
                <input type="number" name="jumlah" id="jumlah"
                       value="<?= (int)($_POST['jumlah'] ?? 1); ?>"
                       min="1" max="<?= $produk['stok']; ?>" required>
            </div>

            <hr class="div">

            <div class="fg">
                <label>Metode Pengambilan <span class="req">*</span></label>
                <div class="metode-grid">
                    <div class="m-opt">
                        <input type="radio" name="metode_ambil" id="opt-ambil" value="ambil_di_tempat"
                               <?= (($_POST['metode_ambil'] ?? 'ambil_di_tempat') === 'ambil_di_tempat') ? 'checked' : ''; ?>
                               onchange="toggleMetode()">
                        <label class="m-lbl" for="opt-ambil">
                            <span class="mi">🏪</span>
                            <span class="mn">Ambil di Toko</span>
                            <span class="ms">Bayar langsung di kasir</span>
                        </label>
                    </div>
                    <div class="m-opt">
                        <input type="radio" name="metode_ambil" id="opt-kirim" value="kirim_ke_alamat"
                               <?= (($_POST['metode_ambil'] ?? '') === 'kirim_ke_alamat') ? 'checked' : ''; ?>
                               onchange="toggleMetode()">
                        <label class="m-lbl" for="opt-kirim">
                            <span class="mi">🚚</span>
                            <span class="mn">Kirim ke Alamat</span>
                            <span class="ms">Ongkir ditanggung penyewa</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- SECTION DATA PENGIRIMAN -->
            <div id="sec-kirim" class="<?= (($_POST['metode_ambil'] ?? '') === 'kirim_ke_alamat') ? 'show' : ''; ?>">
                <div class="sec-head">🚚 Data Identitas & Pengiriman</div>
                <div style="background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:9px;padding:11px 14px;margin-bottom:16px;font-size:.82rem;color:#fdba74;line-height:1.55;">
                    📍 <strong>Hanya melayani pengiriman di sekitar Kota Manado.</strong><br>
                    Ongkos kirim <strong>ditanggung penyewa</strong> melalui Gojek / Grab / ojek online — tidak termasuk dalam total sewa.
                </div>

                <div class="row2">
                    <div class="fg">
                        <label>Nama Penerima <span class="req">*</span></label>
                        <input type="text" name="nama_penerima" placeholder="Sesuai KTP"
                               value="<?= htmlspecialchars($_POST['nama_penerima'] ?? ''); ?>">
                    </div>
                    <div class="fg">
                        <label>No. HP / WhatsApp <span class="req">*</span></label>
                        <input type="text" name="no_hp_penerima" placeholder="08xxxxxxxxxx"
                               value="<?= htmlspecialchars($_POST['no_hp_penerima'] ?? ''); ?>">
                    </div>
                </div>

                <div class="fg">
                    <label>Alamat Lengkap Pengiriman <span class="req">*</span></label>
                    <textarea name="alamat_kirim"
                              placeholder="Jl. ... RT/RW ... Kelurahan ... Kecamatan ... Kota ..."><?= htmlspecialchars($_POST['alamat_kirim'] ?? ''); ?></textarea>
                </div>

                <div class="row2">
                    <div class="fg">
                        <label>📇 Foto KTP <span class="req">*</span></label>
                        <div class="up-wrap">
                            <input type="file" name="foto_ktp" accept="image/*"
                                   onchange="prevUp(this,'pKtp','iKtp','imgKtp','nKtp')">
                            <div class="up-inner" id="iKtp">
                                <div class="ui">🪪</div>
                                <div class="ut">Upload foto KTP<br><strong>JPG / PNG</strong></div>
                            </div>
                            <div class="up-prev" id="pKtp">
                                <img id="imgKtp" src="" alt="">
                                <div class="fn" id="nKtp"></div>
                            </div>
                        </div>
                    </div>
                    <div class="fg">
                        <label>🤳 Selfie + KTP <span class="req">*</span></label>
                        <div class="up-wrap">
                            <input type="file" name="selfie_ktp" accept="image/*"
                                   onchange="prevUp(this,'pSel','iSel','imgSel','nSel')">
                            <div class="up-inner" id="iSel">
                                <div class="ui">📸</div>
                                <div class="ut">Selfie sambil pegang KTP<br><strong>JPG / PNG</strong></div>
                            </div>
                            <div class="up-prev" id="pSel">
                                <img id="imgSel" src="" alt="">
                                <div class="fn" id="nSel"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fg">
                    <label>📝 Catatan Pengiriman <small style="text-transform:none;font-weight:400;color:var(--muted);">(opsional)</small></label>
                    <textarea name="catatan"
                              placeholder="Contoh: titip ke satpam, masuk gang kedua kiri, dll."><?= htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- ESTIMASI TOTAL -->
            <div class="total-box">
                <div class="tr-item"><span>Biaya Sewa</span><span id="rowSewa">Rp 0</span></div>
                <div class="tr-item" id="rowOngkir" style="display:none;"><span>Ongkos Kirim</span><span>Rp 25.000</span></div>
                <div class="tr-item grand"><span>Estimasi Total</span><span id="grandTotal">Rp 0</span></div>
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit">
                🚀 KONFIRMASI SEWA SEKARANG
            </button>
        </form>
    </div>

</div>
</div>

<?php endif; ?>

<script>
    const HARGA  = <?= (int)$produk['harga_sewa']; ?>;
    // Ongkir tidak dihitung sistem — ditanggung penyewa via Gojek/Grab

    const elTgl1 = document.getElementById('tgl_sewa');
    const elTgl2 = document.getElementById('tgl_kembali');
    const elQty  = document.getElementById('jumlah');

    function fmtRp(n){ return 'Rp ' + n.toLocaleString('id-ID'); }

    function hitungTotal() {
        if (!elTgl1 || !elTgl2) return;
        const d1  = new Date(elTgl1.value);
        const d2  = new Date(elTgl2.value);
        const qty = parseInt(elQty?.value) || 1;
        const isK = document.getElementById('opt-kirim')?.checked;
        if (elTgl1.value && elTgl2.value && d2 > d1) {
            const days = Math.round((d2 - d1) / 86400000);
            const sewa = days * HARGA * qty;
            document.getElementById('rowSewa').textContent   = fmtRp(sewa);
            document.getElementById('grandTotal').textContent = fmtRp(sewa); // Ongkir tidak termasuk
        }
    }

    function toggleMetode() {
        const isK   = document.getElementById('opt-kirim')?.checked;
        const sec   = document.getElementById('sec-kirim');
        const ro    = document.getElementById('rowOngkir');
        const btn   = document.getElementById('btnSubmit');
        if (isK) {
            sec?.classList.add('show');
            if (ro)  ro.style.display  = 'flex';
            if (btn) btn.textContent = '🚚 KONFIRMASI & LANJUT KE PEMBAYARAN';
        } else {
            sec?.classList.remove('show');
            if (ro)  ro.style.display  = 'none';
            if (btn) btn.textContent = '🚀 KONFIRMASI SEWA SEKARANG';
        }
        hitungTotal();
    }

    function prevUp(input, prevId, innerId, imgId, nameId) {
        if (input.files && input.files[0]) {
            const r = new FileReader();
            r.onload = e => {
                document.getElementById(innerId).style.display = 'none';
                document.getElementById(prevId).style.display  = 'block';
                document.getElementById(imgId).src             = e.target.result;
                document.getElementById(nameId).textContent    = '✅ ' + input.files[0].name;
            };
            r.readAsDataURL(input.files[0]);
        }
    }

    elTgl1?.addEventListener('change', hitungTotal);
    elTgl2?.addEventListener('change', hitungTotal);
    elQty?.addEventListener('input', hitungTotal);

    // Init on load
    hitungTotal();
    toggleMetode();
</script>

</body>
</html>