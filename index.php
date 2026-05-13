<?php 
// Memanggil file koneksi yang sudah kita buat sebelumnya
include 'config/database.php'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Katalog ElonCamp</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; background-color: #f0f2f5; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .item-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        .item-card h3 { margin: 10px 0; color: #333; }
        .price { color: #27ae60; font-weight: bold; font-size: 1.1em; }
        .btn-sewa { background-color: #e67e22; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; margin-top: 10px; }
    </style>
</head>
<body>

    <h1 style="text-align: center;">⛺ Katalog Alat Camping</h1>
    <hr style="margin-bottom: 30px;">

    <div class="grid">
        <?php
        // Ambil data dari tabel produk
        $query = mysqli_query($conn, "SELECT * FROM produk");
        
        // Loop datanya
        while($data = mysqli_fetch_array($query)) {
        ?>
            <div class="item-card">
                <h3><?php echo $data['nama_alat']; ?></h3>
                <p>Kategori: <?php echo $data['kategori']; ?></p>
                <p class="price">Rp <?php echo number_format($data['harga_sewa'], 0, ',', '.'); ?></p>
                <button class="btn-sewa">Cek Stok: <?php echo $data['stok']; ?></button>
            </div>
        <?php } ?>
    </div>

</body>
</html>