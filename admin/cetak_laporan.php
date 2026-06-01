<?php 
include '../config/database.php'; 

// Set zona waktu ke Indonesia (WITA)
date_default_timezone_set('Asia/Makassar'); 

// Proteksi Admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Mengambil filter tanggal jika dikirimkan oleh admin
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : '';
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : '';

// Jika admin mengisi kedua tanggal filter
if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    $sub_judul = "Periode Pengembalian: " . date('d/m/Y', strtotime($tgl_mulai)) . " s/d " . date('d/m/Y', strtotime($tgl_selesai));
    
    // UPDATE QUERY: Menyaring berdasarkan tanggal barang benar-benar KEMBALI di dunia nyata
    $query = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat, users.nama 
                                  FROM transaksi 
                                  JOIN produk ON transaksi.id_produk = produk.id_produk 
                                  JOIN users ON transaksi.id_user = users.id_user 
                                  WHERE transaksi.tgl_realisasi_kembali BETWEEN '$tgl_mulai' AND '$tgl_selesai'
                                  ORDER BY transaksi.tgl_realisasi_kembali DESC");
} else {
    $sub_judul = "Semua Riwayat Transaksi";
    
    // Query DEFAULT (Ambil semua tanpa filter)
    $query = mysqli_query($conn, "SELECT transaksi.*, produk.nama_alat, users.nama 
                                  FROM transaksi 
                                  JOIN produk ON transaksi.id_produk = produk.id_produk 
                                  JOIN users ON transaksi.id_user = users.id_user 
                                  ORDER BY transaksi.id_transaksi DESC");
}

// Hitung Ringkasan Total
$total_pendapatan_sewa = 0;
$total_pendapatan_denda = 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendapatan ElonCamp</title>
    <style>
        body { font-family: 'Arial', sans-serif; color: #333; padding: 20px; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px double #334155; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 1.8rem; letter-spacing: 1px; }
        .header p { margin: 5px 0 0 0; color: #64748b; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.85rem; }
        th, td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background: #f8fafc; }
        .btn-print-box { text-align: right; margin-bottom: 20px; }
        .btn-print { background: #2563eb; color: white; padding: 8px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        
        /* Menyembunyikan tombol cetak saat kertas diprint */
        @media print {
            .btn-print-box { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="btn-print-box">
        <button onclick="window.print()" class="btn-print">🖨️ Cetak / Simpan ke PDF</button>
        <a href="transaksi.php" class="btn-print" style="background: #64748b;">← Kembali</a>
    </div>

    <div class="header">
        <h1>ELONCAMP OUTDOOR RENTAL</h1>
        <p>Laporan Rekapitulasi Pendapatan & Pengembalian Alat Camping</p>
        <p style="font-weight: bold; margin-top: 5px; color: #2563eb;"><?= $sub_judul; ?></p> 
        <p style="font-size: 0.8rem; margin-top: 5px;">Dicetak pada: <?= date('d M Y, H:i'); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Trx</th>
                <th>Pelanggan</th>
                <th>Nama Alat</th>
                <th>Jatuh Tempo</th>
                <th>Tgl Realisasi</th> <th>Status</th>
                <th>Kondisi Alat</th>
                <th class="text-right">Biaya Sewa</th>
                <th class="text-right">Denda/Ganti Rugi</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = mysqli_fetch_assoc($query)): 
                $subtotal = $row['total_harga'] + $row['denda'];
                $total_pendapatan_sewa += $row['total_harga'];
                $total_pendapatan_denda += $row['denda'];
                
                // Atur warna status agar laporan lebih informatif
                $color_status = '#94a3b8';
                if($row['status'] == 'disewa') $color_status = '#3b82f6';
                if($row['status'] == 'kembali') $color_status = '#10b981';
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><code><?= $row['kode_transaksi']; ?></code></td>
                <td><?= $row['nama']; ?></td>
                <td><?= $row['nama_alat']; ?></td>
                <td><?= date('d/m/Y', strtotime($row['tgl_kembali'])); ?></td>
                
                <td>
                    <?php if(!empty($row['tgl_realisasi_kembali']) && $row['tgl_realisasi_kembali'] != '0000-00-00'): ?>
                        <?= date('d/m/Y', strtotime($row['tgl_realisasi_kembali'])); ?>
                    <?php else: ?>
                        <span style="color: #94a3b8; font-style: italic;">Belum Kembali</span>
                    <?php endif; ?>
                </td>

                <td>
                    <span class="status-badge" style="background: <?= $color_status; ?>; color: white;">
                        <?= $row['status']; ?>
                    </span>
                </td>
                <td style="text-transform: uppercase; font-weight: bold; color: <?= $row['kondisi'] == 'normal' ? '#10b981' : '#dc2626'; ?>;">
                    <?= !empty($row['kondisi']) ? $row['kondisi'] : '-'; ?>
                </td>
                <td class="text-right"><?= formatRupiah($row['total_harga']); ?></td>
                <td class="text-right"><?= formatRupiah($row['denda']); ?></td>
                <td class="text-right" style="font-weight: bold;"><?= formatRupiah($subtotal); ?></td>
            </tr>
            <?php endwhile; ?>
            
            <tr class="total-row">
                <td colspan="8" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-right" style="color: #2563eb;"><?= formatRupiah($total_pendapatan_sewa); ?></td>
                <td class="text-right" style="color: #dc2626;"><?= formatRupiah($total_pendapatan_denda); ?></td>
                <td class="text-right" style="background: #e2e8f0; font-size: 0.95rem;">
                    <?= formatRupiah($total_pendapatan_sewa + $total_pendapatan_denda); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 50px; float: right; text-align: center; width: 200px; font-size: 0.9rem;">
        <p>Manado, <?= date('d M Y'); ?></p>
        <p style="margin-top: 60px; font-weight: bold; border-bottom: 1px solid #333; padding-bottom: 5px;">Admin ElonCamp</p>
        <p style="font-size: 0.8rem; color: #64748b; margin-top: -5px;">Management System</p>
    </div>

</body>
</html>