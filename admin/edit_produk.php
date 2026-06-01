<?php
include '../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: produk.php");
    exit;
}

$id_produk = mysqli_real_escape_string($conn, $_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk = '$id_produk'");
$produk = mysqli_fetch_assoc($query);

if (!$produk) {
    header("Location: produk.php");
    exit;
}

$error = '';

if (isset($_POST['update_produk'])) {
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
            $error = "Format foto harus JPG, JPEG, PNG, atau WEBP!";
        } elseif ($foto_size > 2000000) {
            $error = "Ukuran foto maksimal 2MB!";
        } else {
            $foto_baru = uniqid() . "." . $ekstensi_file;
            $target_dir = "../assets/images/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            if (move_uploaded_file($foto_tmp, $target_dir . $foto_baru)) {
                if (!empty($produk['foto']) && file_exists($target_dir . $produk['foto'])) {
                    unlink($target_dir . $produk['foto']);
                }
                $sql = "UPDATE produk SET nama_alat='$nama_alat', deskripsi='$deskripsi', harga_sewa='$harga_sewa', stok='$stok', foto='$foto_baru' WHERE id_produk='$id_produk'";
            } else {
                $error = "Gagal mengunggah foto baru ke server.";
            }
        }
    } else {
        $sql = "UPDATE produk SET nama_alat='$nama_alat', deskripsi='$deskripsi', harga_sewa='$harga_sewa', stok='$stok' WHERE id_produk='$id_produk'";
    }
    
    if (empty($error)) {
        if (mysqli_query($conn, $sql)) {
            echo "<script>
                alert('Data alat camping berhasil diperbarui!');
                window.location.href = 'produk.php';
            </script>";
            exit;
        } else {
            $error = "Gagal memperbarui database: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Produk - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .edit-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.08); font-family: sans-serif; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; color: #1e293b; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; }
        .btn { padding: 12px 20px; border-radius: 8px; font-weight: bold; text-decoration: none; display: inline-block; border: none; cursor: pointer; text-align: center; }
        .btn-success { background: #10b981; color: white; width: 100%; margin-top: 10px; }
        .btn-secondary { background: #64748b; color: white; margin-top: 10px; display: block; }
        .alert { background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body style="background: #f8fafc;">

<div class="edit-container">
    <h2>✏️ EDIT DATA ALAT CAMPING</h2>
    <p style="color: #64748b; margin-bottom: 25px;">Ubah data informasi spesifikasi, harga sewa, stok, atau gambar produk.</p>

    <?php if($error): ?> <div class="alert">⚠️ <?= $error; ?></div> <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Nama Alat</label>
            <input type="text" name="nama_alat" value="<?= htmlspecialchars($produk['nama_alat']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Deskripsi Alat</label>
            <textarea name="deskripsi" rows="4" required><?= htmlspecialchars($produk['deskripsi']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Harga Sewa / Hari (Rp)</label>
            <input type="number" name="harga_sewa" value="<?= $produk['harga_sewa']; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Stok Alat (Unit)</label>
            <input type="number" name="stok" value="<?= $produk['stok']; ?>" required>
        </div>

        <div class="form-group">
            <label>Foto Saat Ini</label>
            <div style="margin-bottom: 12px;">
                <?php if(!empty($produk['foto']) && file_exists("../assets/images/".$produk['foto'])): ?>
                    <img src="../assets/images/<?= $produk['foto']; ?>" width="130" style="border-radius: 8px; border: 1px solid #cbd5e1; object-fit: cover;">
                <?php else: ?>
                    <span style="color:#94a3b8; font-style:italic; font-size:0.9rem;">Belum ada foto yang diunggah</span>
                <?php endif; ?>
            </div>
            <label>Ganti Foto Baru <span style="font-weight:normal; color:#64748b;">(Kosongkan jika tidak ingin diganti)</span></label>
            <input type="file" name="foto" accept="image/*">
        </div>

        <button type="submit" name="update_produk" class="btn btn-success">💾 Simpan Perubahan</button>
        <a href="produk.php" class="btn btn-secondary">❌ Batalkan & Kembali</a>
    </form>
</div>

</body>
</html>