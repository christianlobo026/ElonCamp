<?php 
include '../config/database.php'; 

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Cek kolom tambahan
$ada_metode = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'metode_ambil'"))   > 0;
$ada_bt     = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'bukti_transfer'")) > 0;
$ada_resi   = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'no_resi'"))        > 0;
$ada_kirim  = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'member_sudah_kirim'")) > 0;
$ada_refund = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'refund_status'"))  > 0;

// Proses Aksi
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id   = (int)$_GET['id'];
    $aksi = $_GET['aksi'];

    if ($aksi === 'disewa') {
        mysqli_query($conn, "UPDATE transaksi SET status='disewa' WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=disewa"); exit;

    } elseif ($aksi === 'konfirmasi_bayar') {
        mysqli_query($conn, "UPDATE transaksi SET status='dikirim' WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=bayar_dikonfirmasi"); exit;

    } elseif ($aksi === 'input_resi') {
        $resi = mysqli_real_escape_string($conn, trim($_POST['no_resi'] ?? ''));
        $eksp = mysqli_real_escape_string($conn, trim($_POST['ekspedisi'] ?? ''));
        if (!empty($resi) && $ada_resi) {
            mysqli_query($conn, "UPDATE transaksi SET no_resi='$resi', ekspedisi='$eksp' WHERE id_transaksi='$id'");
        }
        header("Location: transaksi.php?msg=resi_disimpan"); exit;

    } elseif ($aksi === 'barang_sampai') {
        header("Location: proses_refund_komplain.php?id=$id"); exit;

    } elseif ($aksi === 'komplain_disetujui') {
        mysqli_query($conn, "UPDATE transaksi SET status='mengembalikan', kondisi='rusak', denda=0 WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=komplain_disetujui"); exit;

    } elseif ($aksi === 'komplain_ditolak') {
        mysqli_query($conn, "UPDATE transaksi SET status='disewa' WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=komplain_ditolak"); exit;

    } elseif ($aksi === 'tolak_bayar') {
        if ($ada_bt) mysqli_query($conn, "UPDATE transaksi SET bukti_transfer=NULL WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=ditolak"); exit;

    } elseif ($aksi === 'proses_refund') {
        if ($ada_refund) mysqli_query($conn, "UPDATE transaksi SET refund_status='disetujui' WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=refund_disetujui"); exit;

    } elseif ($aksi === 'tolak_refund') {
        if ($ada_refund) mysqli_query($conn, "UPDATE transaksi SET refund_status='ditolak' WHERE id_transaksi='$id'");
        header("Location: transaksi.php?msg=refund_ditolak"); exit;

    } elseif ($aksi === 'kembali') {
        $tgl_skrg = date('Y-m-d');
        $qd = mysqli_query($conn, "SELECT transaksi.id_produk, transaksi.jumlah, transaksi.tgl_kembali, produk.harga_sewa 
                                    FROM transaksi JOIN produk ON transaksi.id_produk=produk.id_produk 
                                    WHERE transaksi.id_transaksi='$id'");
        $data = mysqli_fetch_assoc($qd);
        $denda = 0;
        if ($data) {
            $target = new DateTime($data['tgl_kembali']);
            $today  = new DateTime($tgl_skrg);
            if ($today > $target) {
                $hari = $today->diff($target)->days;
                if ($hari > 0) $denda = $hari * $data['harga_sewa'];
            }
            mysqli_query($conn, "UPDATE transaksi SET status='kembali', denda='$denda', tgl_realisasi_kembali='$tgl_skrg' WHERE id_transaksi='$id'");
            mysqli_query($conn, "UPDATE produk SET stok=stok+" . (int)$data['jumlah'] . " WHERE id_produk='" . (int)$data['id_produk'] . "'");
        }
        header("Location: transaksi.php?msg=selesai&denda=$denda"); exit;
    }
}

$query = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat, produk.harga_sewa, users.nama 
                               FROM transaksi 
                               JOIN produk ON transaksi.id_produk = produk.id_produk 
                               JOIN users  ON transaksi.id_user   = users.id_user 
                               ORDER BY transaksi.id_transaksi DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Transaksi | ElonCamp</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        *{box-sizing:border-box;}
        .admin-container{display:flex;min-height:100vh;}
        .sidebar{width:250px;background:#1e293b;color:white;padding:20px;flex-shrink:0;}
        .sidebar h2{margin-bottom:10px;font-size:1.2rem;letter-spacing:1px;}
        .sidebar hr{margin:15px 0;opacity:.1;}
        .sidebar a{display:block;color:#cbd5e1;text-decoration:none;padding:10px 0;border-bottom:1px solid #334155;font-size:.9rem;}
        .sidebar a:hover,.sidebar a.active{color:white;font-weight:bold;}
        .main-content{flex:1;padding:30px 35px;background:#f1f5f9;overflow-x:auto;}
        h1{font-size:1.5rem;color:#1e293b;margin-bottom:5px;}
        .sub{color:#64748b;font-size:.85rem;margin-bottom:20px;}

        /* Notif */
        .notif{padding:13px 18px;border-radius:8px;margin-bottom:20px;font-size:.88rem;font-weight:500;}
        .notif-blue {background:#dbeafe;color:#1e40af;border-left:4px solid #3b82f6;}
        .notif-green{background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
        .notif-red  {background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444;}

        /* Filter bar */
        .filter-bar{background:white;padding:14px 18px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
        .filter-bar form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .filter-bar label{font-size:.85rem;font-weight:bold;color:#475569;}
        .filter-bar input[type="date"]{padding:6px 10px;border:1px solid #cbd5e1;border-radius:4px;font-size:.85rem;}
        .btn-cetak{background:#2563eb;color:white;padding:7px 15px;border:none;border-radius:4px;font-weight:bold;font-size:.85rem;cursor:pointer;}
        .btn-cetak-all{background:#64748b;color:white;padding:8px 15px;border-radius:4px;text-decoration:none;font-weight:bold;font-size:.85rem;}

        /* Tabel */
        table{width:100%;border-collapse:collapse;background:white;margin-top:0;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.05);}
        th,td{padding:11px 13px;text-align:left;border-bottom:1px solid #f1f5f9;}
        th{background:#334155;color:white;font-size:.82rem;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:#f8fafc;}

        /* Badge status */
        .badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;}
        .badge-pending     {background:#fef9c3;color:#854d0e;}
        .badge-dikirim     {background:#ede9fe;color:#5b21b6;}
        .badge-disewa      {background:#dcfce7;color:#166534;}
        .badge-komplain    {background:#fee2e2;color:#991b1b;}
        .badge-mengembalikan{background:#fff7ed;color:#c2410c;}
        .badge-kembali     {background:#f1f5f9;color:#475569;}
        .badge-dibatalkan  {background:#fee2e2;color:#991b1b;}
        .badge-kirim       {background:#dbeafe;color:#1e40af;}
        .badge-ambil       {background:#f0fdf4;color:#166534;}

        /* Tombol aksi */
        .btn-aksi{display:inline-block;padding:5px 11px;border-radius:5px;font-size:.78rem;font-weight:600;text-decoration:none;color:white;margin-bottom:3px;white-space:nowrap;cursor:pointer;border:none;}
        .btn-orange{background:#f97316;}
        .btn-blue  {background:#2563eb;}
        .btn-green {background:#10b981;}
        .btn-red   {background:#dc2626;}
        .btn-purple{background:#7c3aed;}

        /* Modal resi */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;}
        .modal-overlay.show{display:flex;}
        .modal-box{background:white;border-radius:12px;padding:28px;width:400px;max-width:95%;box-shadow:0 20px 50px rgba(0,0,0,.25);}
        .modal-box h3{margin-bottom:16px;color:#1e293b;font-size:1rem;}
        .modal-box label{display:block;font-size:.83rem;font-weight:600;color:#475569;margin-bottom:5px;margin-top:12px;}
        .modal-box input,.modal-box select{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:.88rem;outline:none;}
        .modal-actions{display:flex;gap:10px;margin-top:18px;}
        .modal-actions button{flex:1;padding:10px;border:none;border-radius:7px;font-weight:600;cursor:pointer;font-size:.88rem;}
        .btn-modal-ok{background:#7c3aed;color:white;}
        .btn-modal-cancel{background:#e2e8f0;color:#475569;}
    </style>
</head>
<body>
<div class="admin-container">
    <div class="sidebar">
        <h2>ELONADMIN</h2>
        <hr>
        <a href="index.php">Dashboard</a>
        <a href="transaksi.php" class="active">Kelola Transaksi</a>
        <a href="produk.php">Kelola Produk</a>
        <a href="../auth/logout.php" style="color:#fb7185;">Logout</a>
    </div>

    <div class="main-content">
        <h1>Kelola Transaksi Penyewaan</h1>
        <p class="sub">Kelola semua pesanan masuk — ambil di toko maupun pengiriman.</p>

        <?php
        $notif_text = ''; $notif_class = 'notif-blue';
        if (isset($_GET['msg'])) {
            $msg = $_GET['msg'];
            if ($msg==='disewa')             { $notif_class='notif-blue';  $notif_text='📦 Pesanan dikonfirmasi. Pelanggan sudah mengambil alat.'; }
            if ($msg==='bayar_dikonfirmasi') { $notif_class='notif-green'; $notif_text='✅ Pembayaran dikonfirmasi. Status berubah ke DIKIRIM. Segera input resi pengiriman.'; }
            if ($msg==='resi_disimpan')      { $notif_class='notif-blue';  $notif_text='🚚 Nomor resi berhasil disimpan.'; }
            if ($msg==='selesai')            { $notif_class='notif-green'; $notif_text='✔️ Transaksi selesai.' . (isset($_GET['denda'])&&$_GET['denda']>0 ? ' Denda: '.formatRupiah($_GET['denda']) : ''); }
            if ($msg==='ditolak')            { $notif_class='notif-red';   $notif_text='✗ Bukti transfer ditolak. Pelanggan perlu kirim ulang.'; }
            if ($msg==='refund_disetujui')   { $notif_class='notif-green'; $notif_text='✅ Refund disetujui.'; }
            if ($msg==='refund_ditolak')     { $notif_class='notif-red';   $notif_text='✗ Refund ditolak.'; }
            if ($msg==='refund_selesai')     { $notif_class='notif-green'; $notif_text='💸 Refund berhasil dicatat. Transaksi selesai.'; }
            if ($msg==='komplain_disetujui') { $notif_class='notif-green'; $notif_text='✅ Komplain disetujui. Tunggu member mengirimkan barang kembali ke toko.'; }
            if ($msg==='komplain_ditolak')   { $notif_class='notif-red';   $notif_text='❌ Komplain ditolak. Status member kembali ke DISEWA.'; }
            if (isset($_GET['denda']) && $_GET['denda']>0 && $msg==='selesai') {
                $notif_text = '✔️ Transaksi selesai dengan denda ' . formatRupiah($_GET['denda']);
            }
        }
        if ($notif_text): ?>
        <div class="notif <?= $notif_class; ?>"><?= $notif_text; ?></div>
        <?php endif; ?>

        <!-- Filter & Cetak -->
        <div class="filter-bar">
            <form action="cetak_laporan.php" method="GET" target="_blank">
                <label>Dari:</label>
                <input type="date" name="tgl_mulai" required>
                <label>Sampai:</label>
                <input type="date" name="tgl_selesai" required>
                <button type="submit" class="btn-cetak">🖨️ Cetak Periode</button>
            </form>
            <a href="cetak_laporan.php" target="_blank" class="btn-cetak-all">🌐 Cetak Semua Riwayat</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Pelanggan</th>
                    <th>Alat</th>
                    <th>Metode</th>
                    <th>Bukti TF</th>
                    <th>Tgl Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Tgl Dikembalikan</th>
                    <th>Total Bayar</th>
                    <th>Denda</th>
                    <th>Refund</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($query)):
                $metode    = $ada_metode ? ($row['metode_ambil']        ?? 'ambil_di_tempat') : 'ambil_di_tempat';
                $bukti_tf  = $ada_bt     ? ($row['bukti_transfer']      ?? '')                : '';
                $no_resi   = $ada_resi   ? ($row['no_resi']             ?? '')                : '';
                $ekspedisi = $ada_resi   ? ($row['ekspedisi']           ?? '')                : '';
                $sdh_kirim = $ada_kirim  ? !empty($row['member_sudah_kirim'])                 : false;
                $ref_s     = $ada_refund ? ($row['refund_status']       ?? '')                : '';
                $ref_i     = $ada_refund ? ($row['refund_info']         ?? '')                : '';

                $denda_display = 0;
                $tgl_skrg = date('Y-m-d');
                if ($row['status']==='disewa' && $tgl_skrg > $row['tgl_kembali']) {
                    $denda_display = (new DateTime($tgl_skrg))->diff(new DateTime($row['tgl_kembali']))->days * $row['harga_sewa'];
                } elseif (in_array($row['status'], ['kembali','komplain','mengembalikan'])) {
                    $denda_display = $row['denda'];
                }
            ?>
            <tr>
                <!-- Pelanggan -->
                <td><strong><?= htmlspecialchars($row['nama']); ?></strong></td>

                <!-- Alat -->
                <td><?= htmlspecialchars($row['nama_alat']); ?></td>

                <!-- Metode -->
                <td>
                    <?php if ($metode === 'kirim_ke_alamat'): ?>
                        <span class="badge badge-kirim">🚚 Kirim</span>
                        <?php if (!empty($no_resi)): ?>
                            <br><small style="color:#7c3aed;font-size:.72rem;margin-top:3px;display:block;">
                                <?= htmlspecialchars($ekspedisi); ?> · <?= htmlspecialchars($no_resi); ?>
                            </small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-ambil">🏪 Ambil</span>
                    <?php endif; ?>
                </td>

                <!-- Bukti TF -->
                <td>
                    <?php if ($metode === 'kirim_ke_alamat'): ?>
                        <?php if (!empty($bukti_tf)): ?>
                            <a href="lihat_bukti.php?id=<?= $row['id_transaksi']; ?>"
                               style="color:#10b981;font-weight:600;font-size:.82rem;text-decoration:none;">
                               ✅ Ada — Lihat
                            </a>
                        <?php else: ?>
                            <span style="color:#f97316;font-weight:600;font-size:.8rem;">⏳ Belum</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:.78rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Tgl Pinjam & Jatuh Tempo -->
                <td><?= date('d/m/y', strtotime($row['tgl_sewa'])); ?></td>
                <td><?= date('d/m/y', strtotime($row['tgl_kembali'])); ?></td>

                <!-- Tgl Dikembalikan -->
                <td>
                    <?php if (!empty($row['tgl_realisasi_kembali']) && $row['tgl_realisasi_kembali'] !== '0000-00-00'): ?>
                        <span style="color:#10b981;font-weight:bold;">
                            ↩️ <?= date('d/m/y', strtotime($row['tgl_realisasi_kembali'])); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-style:italic;font-size:.85rem;">Belum Kembali</span>
                    <?php endif; ?>
                </td>

                <!-- Total -->
                <td><?= formatRupiah($row['total_harga']); ?></td>

                <!-- Denda -->
                <td style="color:<?= $denda_display > 0 ? '#dc2626' : '#10b981'; ?>;font-weight:600;">
                    <?= formatRupiah($denda_display); ?>
                    <?php if ($row['status']==='disewa' && $denda_display > 0): ?>
                        <br><small style="color:#dc2626;font-size:.7rem;">(Terlambat)</small>
                    <?php endif; ?>
                </td>

                <!-- Refund -->
                <td>
                    <?php if (in_array($row['status'], ['dibatalkan','mengembalikan','kembali']) && $metode==='kirim_ke_alamat'): ?>
                        <?php if ($ref_s === 'diajukan'): ?>
                            <span style="display:inline-block;font-size:.75rem;color:#854d0e;font-weight:700;background:#fef9c3;padding:3px 7px;border-radius:5px;margin-bottom:4px;">⏳ Diajukan</span>
                            <?php if (!empty($ref_i)): ?>
                                <br><small style="color:#64748b;font-size:.72rem;"><?= htmlspecialchars($ref_i); ?></small>
                            <?php endif; ?>
                            <br>
                            <a href="transaksi.php?aksi=proses_refund&id=<?= $row['id_transaksi']; ?>"
                               class="btn-aksi btn-green" style="font-size:.72rem;padding:3px 8px;"
                               onclick="return confirm('Setujui refund ini?')">✓ Setujui</a>
                            <a href="transaksi.php?aksi=tolak_refund&id=<?= $row['id_transaksi']; ?>"
                               class="btn-aksi btn-red" style="font-size:.72px;padding:3px 8px;"
                               onclick="return confirm('Tolak refund ini?')">✗ Tolak</a>
                        <?php elseif ($ref_s === 'disetujui'): ?>
                            <span style="color:#10b981;font-size:.78rem;font-weight:700;">✅ Selesai</span>
                        <?php elseif ($ref_s === 'ditolak'): ?>
                            <span style="color:#ef4444;font-size:.78rem;font-weight:700;">✗ Ditolak</span>
                        <?php elseif ($row['status'] === 'mengembalikan'): ?>
                            <span style="color:#f97316;font-size:.75rem;font-weight:600;">⏳ Menunggu</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:.78rem;">Belum diajukan</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:.78rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Status -->
                <td>
                    <span class="badge badge-<?= $row['status']; ?>"><?= strtoupper($row['status']); ?></span>
                </td>

                <!-- AKSI -->
                <td>
                    <?php if ($row['status'] === 'pending'): ?>

                        <?php if ($metode === 'kirim_ke_alamat'): ?>
                            <?php if (!empty($bukti_tf)): ?>
                                <!-- Ada bukti TF: tombol verifikasi -->
                                <a href="lihat_bukti.php?id=<?= $row['id_transaksi']; ?>"
                                   class="btn-aksi btn-orange">🔍 Verifikasi Bayar</a>
                            <?php else: ?>
                                <!-- Belum ada bukti TF -->
                                <span style="color:#f97316;font-size:.78rem;font-weight:600;">⏳ Tunggu Bukti TF</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Ambil di tempat -->
                            <a href="transaksi.php?aksi=disewa&id=<?= $row['id_transaksi']; ?>"
                               class="btn-aksi btn-blue"
                               onclick="return confirm('Konfirmasi pelanggan sudah mengambil alat?')">
                               ✅ Konfirmasi Ambil
                            </a>
                        <?php endif; ?>

                    <?php elseif ($row['status'] === 'dikirim'): ?>

                        <?php if (empty($no_resi)): ?>
                            <!-- Belum ada resi -->
                            <button class="btn-aksi btn-purple"
                                    onclick="bukaModalResi(<?= $row['id_transaksi']; ?>)">
                                    📦 Input Resi
                            </button>
                        <?php else: ?>
                            <span style="color:#7c3aed;font-size:.75rem;font-weight:600;display:block;margin-bottom:3px;">
                                🚚 <?= htmlspecialchars($ekspedisi); ?><br><?= htmlspecialchars($no_resi); ?>
                            </span>
                        <?php endif; ?>
                        <span style="color:#94a3b8;font-size:.73rem;display:block;margin-top:3px;">
                            Menunggu konfirmasi member
                        </span>

                    <?php elseif ($row['status'] === 'disewa'): ?>

                        <a href="konfirmasi_kembali.php?id=<?= $row['id_transaksi']; ?>"
                           class="btn-aksi btn-green">↩️ Selesai/Kembali</a>

                    <?php elseif ($row['status'] === 'komplain'): ?>

                        <!-- Member lapor barang rusak saat diterima -->
                        <a href="konfirmasi_kembali.php?id=<?= $row['id_transaksi']; ?>"
                           class="btn-aksi btn-red">⚠️ Proses Komplain</a>

                    <?php elseif ($row['status'] === 'mengembalikan'): ?>

                        <!-- Komplain disetujui, tunggu barang balik ke toko -->
                        <?php if ($sdh_kirim): ?>
                            <span style="color:#10b981;font-size:.75rem;font-weight:700;display:block;margin-bottom:4px;">
                                🚚 Member Sudah Kirim
                            </span>
                            <a href="transaksi.php?aksi=barang_sampai&id=<?= $row['id_transaksi']; ?>"
                               class="btn-aksi btn-green"
                               onclick="return confirm('Konfirmasi barang sudah diterima di toko? Lanjut proses refund.')">
                               ✅ Barang Sampai di Toko
                            </a>
                        <?php else: ?>
                            <span style="color:#c2410c;font-size:.75rem;font-weight:700;display:block;margin-bottom:4px;">
                                ⏳ Menunggu Barang Kembali
                            </span>
                            <a href="transaksi.php?aksi=barang_sampai&id=<?= $row['id_transaksi']; ?>"
                               class="btn-aksi btn-orange"
                               onclick="return confirm('Konfirmasi barang komplain sudah diterima di toko?')">
                               📦 Konfirmasi Barang Sampai
                            </a>
                        <?php endif; ?>

                    <?php elseif ($row['status'] === 'dibatalkan'): ?>

                        <span style="color:#ef4444;font-size:.78rem;font-weight:700;">✕ Dibatalkan</span>

                    <?php elseif ($row['status'] === 'kembali'): ?>

                        <span style="color:#94a3b8;font-size:.78rem;font-weight:600;">✔️ Transaksi Selesai</span>

                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:.78rem;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Input Resi -->
<div class="modal-overlay" id="modalResi">
    <div class="modal-box">
        <h3>📦 Input Nomor Resi Pengiriman</h3>
        <form method="POST" action="transaksi.php?aksi=input_resi&id=0" id="formResi">
            <label>Ekspedisi</label>
            <select name="ekspedisi">
                <option value="JNE">JNE</option>
                <option value="J&T">J&T Express</option>
                <option value="SiCepat">SiCepat</option>
                <option value="AnterAja">AnterAja</option>
                <option value="Pos Indonesia">Pos Indonesia</option>
                <option value="Grab/Gojek">Grab / Gojek</option>
                <option value="Lainnya">Lainnya</option>
            </select>
            <label>Nomor Resi</label>
            <input type="text" name="no_resi" placeholder="Contoh: JNE123456789" required>
            <div class="modal-actions">
                <button type="submit" class="btn-modal-ok">Simpan Resi</button>
                <button type="button" class="btn-modal-cancel" onclick="tutupModal()">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalResi(id) {
    document.getElementById('formResi').action = 'transaksi.php?aksi=input_resi&id=' + id;
    document.getElementById('modalResi').classList.add('show');
}
function tutupModal() {
    document.getElementById('modalResi').classList.remove('show');
}
document.getElementById('modalResi').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>
</body>
</html>