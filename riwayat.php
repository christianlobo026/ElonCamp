<?php 
include 'config/database.php'; 

// 🔴 AKTIFKAN PELACAK EROR: Memaksa PHP menampilkan masalah aslinya ke layar jika kueri/database macet
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek apakah sudah login, kalau belum tendang ke login
if (!isLoggedIn()) {
    header("Location: auth/login.php");
    exit;
}

$id_user = $_SESSION['user_id'];

// ── Aksi: member simpan rekening refund komplain ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_rekening') {
    $id_trx    = (int)($_POST['id_trx'] ?? 0);
    $nama_bank = bersihkan($conn, $_POST['nama_bank'] ?? '');
    $no_rek    = bersihkan($conn, $_POST['no_rek']    ?? '');
    $nama_rek  = bersihkan($conn, $_POST['nama_rek']  ?? '');

    if ($id_trx > 0 && !empty($nama_bank) && !empty($no_rek) && !empty($nama_rek)) {
        $info = bersihkan($conn, "$nama_bank | $no_rek | a.n. $nama_rek");

        // Pastikan kolom ada
        $cek_ref = mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'refund_status'");
        if (mysqli_num_rows($cek_ref) === 0) {
            mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN refund_status VARCHAR(20) NULL, ADD COLUMN refund_info VARCHAR(255) NULL");
        }
        mysqli_query($conn, "UPDATE transaksi SET refund_info='$info'
                              WHERE id_transaksi='$id_trx'
                              AND id_user='$id_user'
                              AND status='mengembalikan'");
    }
    header("Location: riwayat.php?pesan=rekening_disimpan");
    exit;
}

// ── Aksi: member konfirmasi sudah kirim barang ──
if (isset($_GET['aksi']) && $_GET['aksi'] === 'sudah_kirim' && isset($_GET['id'])) {
    $id_trx = (int)$_GET['id'];
    // Cek kolom ada
    $cek = mysqli_query($conn, "SHOW COLUMNS FROM transaksi LIKE 'member_sudah_kirim'");
    if (mysqli_num_rows($cek) > 0) {
        mysqli_query($conn, "UPDATE transaksi SET member_sudah_kirim=1 WHERE id_transaksi='$id_trx' AND id_user='$id_user' AND status='mengembalikan'");
    } else {
        // Kolom belum ada, buat dulu
        mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN member_sudah_kirim TINYINT(1) DEFAULT 0");
        mysqli_query($conn, "UPDATE transaksi SET member_sudah_kirim=1 WHERE id_transaksi='$id_trx' AND id_user='$id_user' AND status='mengembalikan'");
    }
    header("Location: riwayat.php?pesan=sudah_kirim");
    exit;
}

// Ambil data transaksi khusus user ini, digabung dengan tabel produk untuk ambil nama alatnya
$sql = "SELECT transaksi.*, produk.nama_alat 
        FROM transaksi 
        JOIN produk ON transaksi.id_produk = produk.id_produk 
        WHERE transaksi.id_user = '$id_user' 
        ORDER BY transaksi.id_transaksi DESC";

$query = mysqli_query($conn, $sql);

// PENGAMAN: Jika kueri gabungan JOIN di atas gagal, langsung bongkar kerusakannya di sini
if (!$query) {
    die("<div style='background:#fef2f2; color:#dc2626; padding:20px; margin:30px; border-left:5px solid #dc2626; font-family:sans-serif;'>
            <strong>⚠️ Kueri Riwayat Gagal Dieksekusi!</strong><br>
            Pesan Masalah dari MySQL: " . mysqli_error($conn) . "<br>
            <br><em>Saran: Pastikan nama kolom di tabel transaksi sudah sesuai dengan database kamu.</em>
         </div>");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Sewa - ElonCamp</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .container { padding: 40px 8%; }
        .table-container { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        th { background: #f8fafc; color: #64748b; font-size: 0.85rem; text-transform: uppercase; }
        .status { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; display: inline-block; }
        .pending { background: #fef3c7; color: #92400e; }
        .disewa { background: #dcfce7; color: #166534; }
        .kembali { background: #f1f5f9; color: #475569; }
        .dibatalkan  { background: #fee2e2; color: #991b1b; }
        .dikirim     { background: #ede9fe; color: #5b21b6; }
        .komplain        { background: #fef2f2; color: #991b1b; }
        .mengembalikan   { background: #fff7ed; color: #c2410c; }
        .dikirim    { background: #ede9fe; color: #5b21b6; }
        .btn-aksi { display: inline-block; padding: 5px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; text-decoration: none; margin-bottom: 4px; }
        .btn-batal { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .btn-batal:hover { background: #fecaca; }
        .btn-refund { background: #fff7ed; color: #c2410c; border: 1px solid #fdba74; }

        /* Modal Rekening */
        .modal-rek-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:999; align-items:center; justify-content:center; }
        .modal-rek-overlay.show { display:flex; }
        .modal-rek-box { background:#161b22; border:1px solid #30363d; border-radius:16px; padding:30px; width:440px; max-width:94%; color:#e6edf3; }
        .modal-rek-box h3 { font-size:1.05rem; font-weight:700; margin-bottom:6px; color:#f97316; }
        .modal-rek-box p  { font-size:.83rem; color:#8b949e; margin-bottom:20px; line-height:1.5; }
        .modal-rek-box label { display:block; font-size:.8rem; font-weight:600; color:#8b949e; text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; margin-top:14px; }
        .modal-rek-box select, .modal-rek-box input { width:100%; padding:10px 12px; background:#1c2128; border:1px solid #30363d; border-radius:8px; color:#e6edf3; font-size:.9rem; outline:none; }
        .modal-rek-box select:focus, .modal-rek-box input:focus { border-color:#f97316; }
        .modal-rek-box select option { background:#1c2128; }
        .modal-rek-btns { display:flex; gap:10px; margin-top:22px; }
        .modal-rek-btns button { flex:1; padding:12px; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:.88rem; }
        .btn-rek-ok     { background:#10b981; color:white; }
        .btn-rek-cancel { background:#21262d; color:#8b949e; border:1px solid #30363d; }
        .btn-refund:hover { background: #ffedd5; }
        .badge-refund { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .badge-refund.diajukan  { background: #fef9c3; color: #854d0e; }
        .badge-refund.disetujui { background: #dcfce7; color: #166534; }
        .badge-refund.ditolak   { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <nav style="background: #1e293b; padding: 20px 8%; display: flex; justify-content: space-between; align-items: center;">
        <a href="index.php" style="color: white; font-weight: bold; text-decoration: none; font-size: 1.5rem;">ELONCAMP.</a>
        <a href="index.php" style="color: white; text-decoration: none; font-weight: 600;">Kembali ke Beranda</a>
    </nav>

    <div class="container">
        
        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'sukses_sewa'): ?>
            <div style="background: #d1e7dd; color: #0f5132; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 5px solid #10b981; font-family: sans-serif; line-height: 1.5;">
                <strong style="font-size: 1.1rem;">🎉 Penyewaan Berhasil Diajukan!</strong><br>
                Pesanan Anda saat ini berstatus <span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: bold;">PENDING</span>. Silakan datang langsung ke store <strong>ElonCamp</strong> dengan membawa kartu identitas (KTP) fisik untuk melakukan konfirmasi pembayaran kasir dan pengambilan alat camping.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'rekening_disimpan'): ?>
            <div style="background:#d1fae5;color:#065f46;padding:18px 20px;border-radius:8px;margin-bottom:25px;border-left:5px solid #10b981;font-family:sans-serif;line-height:1.5;">
                <strong>🏦 Data Rekening Berhasil Disimpan!</strong><br>
                Admin akan mentransfer dana refund ke rekening kamu setelah barang diterima di toko.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'sudah_kirim'): ?>
            <div style="background:#f0fdf4;color:#166534;padding:20px;border-radius:8px;margin-bottom:25px;border-left:5px solid #10b981;font-family:sans-serif;line-height:1.5;">
                <strong style="font-size:1.1rem;">🚚 Konfirmasi Pengiriman Tercatat!</strong><br>
                Admin akan mengkonfirmasi saat barang sudah sampai di toko. Setelah itu, dana kamu akan segera dikembalikan.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'mengembalikan'): ?>
            <div style="background:#fff7ed;color:#92400e;padding:20px;border-radius:8px;margin-bottom:25px;border-left:5px solid #f97316;font-family:sans-serif;line-height:1.6;">
                <strong style="font-size:1.1rem;">📦 Komplain Disetujui — Kirim Barang Kembali ke Toko</strong><br>
                Admin telah menyetujui komplain kamu. Silakan kirimkan barang sewaan kembali ke alamat toko kami:<br><br>
                <strong>📍 ElonCamp Store</strong><br>
                Jl. Petualang Sejati No. 17, Manado, Sulawesi Utara<br>
                📞 WA Admin: <strong>0812-XXXX-XXXX</strong><br><br>
                Setelah barang sampai dan diterima admin, dana yang kamu bayarkan akan dikembalikan.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'terima_baik'): ?>
            <div style="background:#d1fae5;color:#065f46;padding:20px;border-radius:8px;margin-bottom:25px;border-left:5px solid #10b981;font-family:sans-serif;line-height:1.5;">
                <strong style="font-size:1.1rem;">✅ Barang Diterima dengan Baik!</strong><br>
                Status sewa kamu sekarang <strong>DISEWA</strong>. Jaga alat dengan baik dan kembalikan sesuai tanggal yang disepakati.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'komplain'): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:20px;border-radius:8px;margin-bottom:25px;border-left:5px solid #ef4444;font-family:sans-serif;line-height:1.5;">
                <strong style="font-size:1.1rem;">⚠️ Komplain Berhasil Dilaporkan!</strong><br>
                Tim ElonCamp akan menghubungi kamu dalam <strong>1×24 jam</strong> untuk menyelesaikan masalah ini. Pantau status di tabel di bawah.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'dibatalkan'): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 5px solid #ef4444; font-family: sans-serif; line-height: 1.5;">
                <strong style="font-size: 1.1rem;">✕ Pesanan Berhasil Dibatalkan.</strong><br>
                Stok alat sudah dikembalikan ke sistem. Jika kamu telah melakukan pembayaran transfer, kamu bisa mengajukan <strong>Refund</strong> melalui tombol di bawah.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'refund_diajukan'): ?>
            <div style="background: #fff7ed; color: #9a3412; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 5px solid #f97316; font-family: sans-serif; line-height: 1.5;">
                <strong style="font-size: 1.1rem;">💸 Pengajuan Refund Terkirim!</strong><br>
                Admin akan memproses pengembalian dana ke rekening/e-wallet kamu dalam <strong>1–3 hari kerja</strong>. Pantau statusnya di kolom Aksi.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'bukti_terkirim'): ?>
            <div style="background: #fff7ed; color: #9a3412; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 5px solid #f97316; font-family: sans-serif; line-height: 1.5;">
                <strong style="font-size: 1.1rem;">📤 Bukti Transfer Berhasil Dikirim!</strong><br>
                Pembayaran kamu sedang dalam proses verifikasi oleh admin ElonCamp. Pesanan akan diproses dan dikirim setelah pembayaran dikonfirmasi <strong>(maks. 1×24 jam kerja)</strong>.
            </div>
        <?php endif; ?>
        <h2>Riwayat Penyewaan</h2>
        <p style="color: #64748b;">Daftar dan status alat camping yang kamu pesan.</p>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Alat Camping</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Durasi Sewa</th>
                        <th>Tgl Dikembalikan</th> <th>Total Bayar</th>
                        <th>Denda/Ganti Rugi</th> <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($query)): 
                            // Hitung akumulasi subtotal biaya sewa + denda yang tersimpan di database
                            $subtotal_bayar = $row['total_harga'] + (isset($row['denda']) ? $row['denda'] : 0);
                        ?>
                        <tr>
                            <td><strong>#<?= $row['kode_transaksi']; ?></strong></td>
                            <td><strong><?= $row['nama_alat']; ?></strong></td>
                            <td><?= $row['jumlah']; ?> Unit</td>
                            <td>
                                <?php 
                                $met = isset($row['metode_ambil']) ? $row['metode_ambil'] : 'ambil_di_tempat';
                                $resi = isset($row['no_resi']) ? $row['no_resi'] : '';
                                $eksp = isset($row['ekspedisi']) ? $row['ekspedisi'] : '';
                                if ($met === 'kirim_ke_alamat'): ?>
                                    <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:700;">🚚 Kirim</span>
                                    <?php if (!empty($resi)): ?>
                                        <br><small style="color:#7c3aed;font-size:0.75rem;"><?= htmlspecialchars($eksp); ?> · <?= htmlspecialchars($resi); ?></small>
                                    <?php else: ?>
                                        <br><small style="color:#f97316;font-size:0.75rem;">Resi belum ada</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:700;">🏪 Ambil</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="display: block; color: #475569;">
                                    📅 Pinjam: <?= date('d M Y', strtotime($row['tgl_sewa'])); ?>
                                </small>
                                <small style="display: block; color: #dc2626; margin-top: 2px;">
                                    ⌛ Tempo: <?= date('d M Y', strtotime($row['tgl_kembali'])); ?>
                                </small>
                            </td>
                            
                            <td>
                                <?php if(!empty($row['tgl_realisasi_kembali']) && $row['tgl_realisasi_kembali'] != '0000-00-00'): ?>
                                    <span style="color: #10b981; font-weight: bold;">
                                        ↩️ <?= date('d M Y', strtotime($row['tgl_realisasi_kembali'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">Belum Dikembalikan</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span style="font-weight: bold; color: #0f172a;"><?= formatRupiah($subtotal_bayar); ?></span><br>
                                <small style="color: #64748b; font-size: 0.75rem;">Sewa: <?= formatRupiah($row['total_harga']); ?></small>
                            </td>

                            <td>
                                <?php if(isset($row['denda']) && $row['denda'] > 0): ?>
                                    <span style="color: #dc2626; font-weight: bold;">+<?= formatRupiah($row['denda']); ?></span>
                                    <?php if(!empty($row['kondisi'])): ?>
                                        <br><small style="background: #fee2e2; color: #991b1b; padding: 1px 5px; border-radius: 4px; font-size: 0.7rem; text-transform: uppercase; font-weight: bold;"><?= $row['kondisi']; ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #10b981;">Rp 0</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="status <?= $row['status']; ?>">
                                    <?= strtoupper($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $met_r        = isset($row['metode_ambil'])  ? $row['metode_ambil']  : '';
                                $refund_status= isset($row['refund_status']) ? $row['refund_status'] : '';
                                $bukti_ada_r  = !empty($row['bukti_transfer'] ?? '');
                                // Pesanan kirim = ada kolom metode kirim ATAU ada bukti transfer
                                $is_kirim_r   = ($met_r === 'kirim_ke_alamat') || $bukti_ada_r;
                                ?>

                                <?php if ($row['status'] === 'pending'): ?>
                                    <!-- Status pending: bisa batalkan -->
                                    <a href="batalkan.php?id=<?= $row['id_transaksi']; ?>"
                                       class="btn-aksi btn-batal"
                                       onclick="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                       ✕ Batalkan Pesanan
                                    </a>

                                <?php elseif ($row['status'] === 'dikirim'): ?>
                                    <!-- Barang dikirim: tombol konfirmasi terima -->
                                    <a href="konfirmasi_terima.php?id=<?= $row['id_transaksi']; ?>"
                                       class="btn-aksi"
                                       style="background:#7c3aed;color:white;display:inline-block;padding:5px 12px;border-radius:6px;text-decoration:none;font-size:0.78rem;font-weight:700;">
                                       📦 Konfirmasi Terima
                                    </a>

                                <?php elseif ($row['status'] === 'komplain'): ?>
                                    <!-- Komplain: menunggu admin -->
                                    <span style="color:#dc2626;font-size:0.78rem;font-weight:700;">⚠️ Komplain Diproses</span>

                                <?php elseif ($row['status'] === 'mengembalikan'): ?>
                                    <?php
                                    $sdh_kirim   = !empty($row['member_sudah_kirim']);
                                    $ref_s_meng  = $row['refund_status'] ?? '';
                                    $ref_i_meng  = $row['refund_info']   ?? '';
                                    // Parse info rekening jika sudah diisi
                                    $sudah_isi_rek = !empty($ref_i_meng) && strpos($ref_i_meng, '|') !== false;
                                    ?>

                                    <!-- Baris 1: Rekening refund -->
                                    <?php if (!$sudah_isi_rek): ?>
                                        <a href="#" onclick="bukaModalRekening(<?= $row['id_transaksi']; ?>);return false;"
                                           style="display:inline-block;background:#dcfce7;border:1px solid #86efac;color:#166534;padding:5px 11px;border-radius:6px;font-size:0.78rem;font-weight:700;text-decoration:none;margin-bottom:5px;">
                                           🏦 Isi Rekening Refund
                                        </a>
                                        <br>
                                    <?php else: ?>
                                        <?php $parts = explode(' | ', $ref_i_meng); ?>
                                        <span style="display:block;font-size:0.75rem;color:#10b981;font-weight:700;margin-bottom:4px;">
                                            ✅ Rekening: <?= htmlspecialchars($parts[0]??''); ?> · <?= htmlspecialchars($parts[1]??''); ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Baris 2: Alamat toko + kirim barang -->
                                    <?php if (!$sdh_kirim): ?>
                                        <a href="#" onclick="document.getElementById('modal-toko').style.display='flex';return false;"
                                           style="display:inline-block;background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;padding:5px 11px;border-radius:6px;font-size:0.78rem;font-weight:700;text-decoration:none;margin-bottom:5px;">
                                           📍 Lihat Alamat Toko
                                        </a>
                                        <br>
                                        <a href="riwayat.php?aksi=sudah_kirim&id=<?= $row['id_transaksi']; ?>"
                                           style="display:inline-block;background:#f97316;color:white;padding:6px 12px;border-radius:6px;font-size:0.78rem;font-weight:700;text-decoration:none;"
                                           onclick="return confirm('Konfirmasi bahwa kamu sudah mengirimkan barang ke alamat toko ElonCamp?')">
                                           🚚 Sudah Kirim Barang
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#10b981;font-size:0.78rem;font-weight:700;display:block;">✅ Barang Sudah Dikirim</span>
                                        <span style="color:#94a3b8;font-size:0.75rem;">Menunggu konfirmasi toko & proses refund</span>
                                    <?php endif; ?>

                                <?php elseif ($row['status'] === 'dibatalkan'): ?>
                                    <!-- Pesanan dibatalkan -->
                                    <?php if ($is_kirim_r): ?>
                                        <!-- Metode kirim: tampilkan opsi refund -->
                                        <?php if (empty($refund_status)): ?>
                                            <a href="batalkan.php?id=<?= $row['id_transaksi']; ?>&aksi=refund"
                                               class="btn-aksi btn-refund">
                                               💸 Ajukan Refund
                                            </a>
                                        <?php elseif ($refund_status === 'diajukan'): ?>
                                            <span class="badge-refund diajukan">⏳ Refund Diproses</span>
                                        <?php elseif ($refund_status === 'disetujui'): ?>
                                            <span class="badge-refund disetujui">✅ Refund Disetujui</span>
                                        <?php elseif ($refund_status === 'ditolak'): ?>
                                            <span class="badge-refund ditolak">✗ Refund Ditolak</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:0.78rem;">Pesanan Dibatalkan</span>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <span style="color:#94a3b8;font-size:0.78rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; color: #94a3b8; padding: 40px;">Belum ada riwayat transaksi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<!-- Modal Isi Rekening Refund Komplain -->
<div class="modal-rek-overlay" id="modalRekening">
    <div class="modal-rek-box">
        <h3>🏦 Isi Data Rekening Refund</h3>
        <p>Dana refund akan ditransfer ke rekening ini setelah barang sampai di toko dan diverifikasi admin.</p>

        <form method="POST" action="riwayat.php" id="formRekening">
            <input type="hidden" name="aksi" value="simpan_rekening">
            <input type="hidden" name="id_trx" id="inputIdTrx" value="">

            <label>Bank / E-Wallet Tujuan <span style="color:#ef4444">*</span></label>
            <select name="nama_bank" required>
                <option value="">— Pilih —</option>
                <option value="BCA">BCA</option>
                <option value="BNI">BNI</option>
                <option value="BRI">BRI</option>
                <option value="Mandiri">Mandiri</option>
                <option value="GoPay">GoPay</option>
                <option value="OVO">OVO</option>
                <option value="DANA">DANA</option>
                <option value="ShopeePay">ShopeePay</option>
            </select>

            <label>Nomor Rekening / HP E-Wallet <span style="color:#ef4444">*</span></label>
            <input type="text" name="no_rek"
                   placeholder="Contoh: 1234567890 atau 081234567890" required>

            <label>Nama Pemilik Rekening <span style="color:#ef4444">*</span></label>
            <input type="text" name="nama_rek"
                   placeholder="Sesuai nama di rekening / akun" required>

            <div style="background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:8px;padding:10px 12px;margin-top:16px;font-size:.8rem;color:#fdba74;line-height:1.5;">
                ⚠️ Pastikan data rekening sudah benar. ElonCamp tidak bertanggung jawab atas kesalahan transfer akibat data yang salah.
            </div>

            <div class="modal-rek-btns">
                <button type="button" class="btn-rek-cancel" onclick="tutupModalRekening()">Batal</button>
                <button type="submit" class="btn-rek-ok">💾 Simpan Rekening</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Alamat Toko -->
<div id="modal-toko" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#161b22;border:1px solid #30363d;border-radius:16px;padding:32px;max-width:420px;width:90%;color:#e6edf3;">
        <h3 style="font-family:sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:16px;color:#f97316;">📍 Alamat Pengiriman Barang</h3>
        <p style="font-size:.9rem;line-height:1.7;color:#c9d1d9;">
            <strong style="color:#e6edf3;">ElonCamp Store</strong><br>
            Jl. Petualang Sejati No. 17<br>
            Manado, Sulawesi Utara<br><br>
            📞 <strong>WA Admin:</strong> 0812-XXXX-XXXX<br><br>
            <span style="background:rgba(249,115,22,.15);border:1px solid rgba(249,115,22,.3);padding:10px 12px;border-radius:8px;display:block;font-size:.82rem;color:#fdba74;line-height:1.6;">
                ⚠️ Sertakan <strong>kode transaksi</strong> di dalam paket atau di keterangan pengiriman agar admin mudah mengidentifikasi.
            </span>
        </p>
        <button onclick="document.getElementById('modal-toko').style.display='none'"
                style="margin-top:20px;width:100%;padding:12px;background:#f97316;color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Tutup</button>
    </div>
</div>

<script>
function bukaModalRekening(id) {
    document.getElementById('inputIdTrx').value = id;
    document.getElementById('modalRekening').classList.add('show');
}
function tutupModalRekening() {
    document.getElementById('modalRekening').classList.remove('show');
}
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('modalRekening');
    if (el) el.addEventListener('click', function(e){ if(e.target===this) tutupModalRekening(); });
});
</script>
</body>
</html>