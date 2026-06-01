<?php 
include '../config/database.php'; 

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = $_POST['nama_alat'];
    $kategori  = $_POST['kategori'];
    $harga     = $_POST['harga_sewa'];
    $deskripsi = $_POST['deskripsi'];

    $insert = mysqli_query($conn, "INSERT INTO produk (nama_alat, kategori, harga_sewa, deskripsi) 
                                   VALUES ('$nama', '$kategori', '$harga', '$deskripsi')");

    if ($insert) {
        echo "<script>alert('Produk berhasil ditambahkan!'); window.location='produk.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk | ElonCamp</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Tambah Alat Baru</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nama Alat</label>
                <input type="text" name="nama_alat" required>
            </div>
            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori">
                    <option value="Tenda">Tenda</option>
                    <option value="Tas">Tas / Carrier</option>
                    <option value="Masak">Peralatan Masak</option>
                    <option value="Lampu">Penerangan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Harga Sewa per Hari</label>
                <input type="number" name="harga_sewa" required>
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="deskripsi" rows="4"></textarea>
            </div>
            <button type="submit" class="btn-auth" style="width: 100%;">SIMPAN PRODUK</button>
            <a href="produk.php" style="display: block; text-align: center; margin-top: 15px; color: #64748b;">Batal</a>
        </form>
    </div>
</body>
</html>