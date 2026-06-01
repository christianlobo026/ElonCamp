<?php
include '../config/database.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isAdmin()) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$sukses = '';

// ============================================================
// 1. PROSES TAMBAH PRODUK BARU (DENGAN UPLOAD FOTO)
// ============================================================
if (isset($_POST['tambah_produk'])) {
    $nama_alat  = mysqli_real_escape_string($conn, $_POST['nama_alat']);
    $deskripsi  = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $harga_sewa = (int)$_POST['harga_sewa'];
    $stok       = (int)$_POST['stok'];
    
    $foto_name = $_FILES['foto']['name'];
    $foto_tmp  = $_FILES['foto']['tmp_name'];
    $foto_size = $_FILES['foto']['size'];
    
    if (!empty($foto_name)) {
        $ekstensi_valid = ['jpg', 'jpeg', 'png', 'webp'];
        $ekstensi_file  = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        
        if (!in_array($ekstensi_file, $ekstensi_valid)) {
            $error = "Format gambar harus JPG, JPEG, PNG, atau WEBP!";
        } elseif ($foto_size > 5 * 1024 * 1024) { // Dinaikkan ke 5MB agar sejalan dengan sewa.php
            $error = "Ukuran gambar terlalu besar! Maksimal 5MB.";
        } else {
            $foto_baru = uniqid() . "." . $ekstensi_file;
            $target_dir = "../assets/images/";
            
            // KUNCI PERBAIKAN: Buat folder otomatis jika belum terbaca oleh PHP, set permission ke 0777
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            if (move_uploaded_file($foto_tmp, $target_dir . $foto_baru)) {
                $query = "INSERT INTO produk (nama_alat, deskripsi, harga_sewa, stok, foto) 
                          VALUES ('$nama_alat', '$deskripsi', '$harga_sewa', '$stok', '$foto_baru')";
                if (mysqli_query($conn, $query)) {
                    $sukses = "Produk baru berhasil ditambahkan!";
                } else {
                    $error = "Gagal menyimpan data ke database: " . mysqli_error($conn);
                }
            } else {
                $error = "Server gagal memindahkan file gambar. Periksa write permission folder assets/images/.";
            }
        }
    } else {
        // Jika tambah produk tanpa foto
        $query = "INSERT INTO produk (nama_alat, deskripsi, harga_sewa, stok, foto) 
                  VALUES ('$nama_alat', '$deskripsi', '$harga_sewa', '$stok', '')";
        if (mysqli_query($conn, $query)) {
            $sukses = "Produk berhasil ditambahkan tanpa foto!";
        } else {
            $error = "Gagal menyimpan data: " . mysqli_error($conn);
        }
    }
}

// ============================================================
// 2. PROSES HAPUS PRODUK
// ============================================================
if (isset($_GET['hapus'])) {
    $id_hapus = mysqli_real_escape_string($conn, $_GET['hapus']);
    
    // Ambil info nama file foto lama sebelum datanya dihapus
    $cari_foto = mysqli_query($conn, "SELECT foto FROM produk WHERE id_produk = '$id_hapus'");
    $data_foto = mysqli_fetch_assoc($cari_foto);
    
    if ($data_foto && !empty($data_foto['foto'])) {
        $file_path = "../assets/images/" . $data_foto['foto'];
        if (file_exists($file_path)) {
            unlink($file_path); // Hapus berkas gambar fisik dari server
        }
    }
    
    $delete_query = mysqli_query($conn, "DELETE FROM produk WHERE id_produk = '$id_hapus'");
    if ($delete_query) {
        header("Location: produk.php?pesan=terhapus");
        exit;
    } else {
        $error = "Gagal menghapus produk dari database.";
    }
}

// Tangkap pesan sukses dari redirect pembersihan URL
if (isset($_GET['pesan']) && $_GET['pesan'] === 'terhapus') {
    $sukses = "Produk berhasil dihapus secara permanen!";
}

// Ambil seluruh data katalog produk untuk tabel manajemen
$produk_query = mysqli_query($conn, "SELECT * FROM produk ORDER BY id_produk DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk - Admin ElonCamp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #f8fafc; color: #334155; }
        .admin-header { font-family: 'Syne', sans-serif; font-weight: 800; color: #0f172a; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); }
        .table th { background-color: #f1f5f9; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="admin-header mb-1">🏕️ KELOLA ALAT CAMPING</h2>
            <p class="text-muted small mb-0">Halaman khusus Administrator ElonCamp</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">← Kembali ke Dashboard</a>
    </div>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>⚠️ Eror:</strong> <?= $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if(!empty($sukses)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✅ Sukses:</strong> <?= $sukses; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4">
                <h5 class="fw-bold mb-3" style="font-family:'Syne',sans-serif;">Tambah Alat Baru</h5>
                <form method="POST" action="produk.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Perlengkapan</label>
                        <input type="text" name="nama_alat" class="form-control" placeholder="Misal: Tenda Dome 4P" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deskripsi Singkat</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Spesifikasi bahan, ukuran, dll." required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label small fw-bold">Harga Sewa / Hari</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="harga_sewa" class="form-control" placeholder="0" required>
                            </div>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label small fw-bold">Jumlah Stok</label>
                            <input type="number" name="stok" class="form-control" placeholder="0" min="1" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Foto Produk</label>
                        <input type="file" name="foto" class="form-control" accept="image/*">
                        <div class="form-text text-muted" style="font-size:0.75rem;">Format: JPG, PNG, WEBP (Maks. 5MB)</div>
                    </div>
                    <button type="submit" name="tambah_produk" class="btn btn-primary w-100 fw-bold">
                        ➕ Tambahkan Alat
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-4">
                <h5 class="fw-bold mb-3" style="font-family:'Syne',sans-serif;">Katalog Perlengkapan</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">Foto</th>
                                <th>Nama Alat</th>
                                <th>Harga Sewa</th>
                                <th>Stok</th>
                                <th width="140">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($produk_query) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada alat camping yang ditambahkan.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php while($row = mysqli_fetch_assoc($produk_query)): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($row['foto']) && file_exists("../assets/images/".$row['foto'])): ?>
                                        <img src="../assets/images/<?= $row['foto']; ?>" width="55" height="45" style="border-radius: 6px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width:55px; height:45px; background:#e2e8f0; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:1.1rem;">⛺</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['nama_alat']); ?></strong></td>
                                <td class="text-success fw-semibold">Rp <?= number_format($row['harga_sewa'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php if($row['stok'] <= 0): ?>
                                        <span class="badge bg-danger">Habis</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><?= $row['stok']; ?> Unit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_produk.php?id=<?= $row['id_produk']; ?>" class="btn btn-warning btn-sm fw-medium">Edit</a>
                                    <a href="produk.php?hapus=<?= $row['id_produk']; ?>" class="btn btn-danger btn-sm fw-medium" onclick="return confirm('Apakah Anda yakin ingin menghapus perlengkapan ini?')">Hapus</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div> 
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>