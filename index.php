<?php include 'config/database.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElonCamp | Rental Equipment</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav>
    <a href="index.php" class="logo">ELONCAMP.</a>
    
    <ul>
        <li><a href="index.php">BERANDA</a></li>
        <li><a href="#katalog">SEWA</a></li>
        <li><a href="#about">TENTANG</a></li>
        
        <?php if(isLoggedIn()): ?>
            <li><a href="riwayat.php">RIWAYAT</a></li>
        <?php endif; ?>
    </ul>

    <div class="nav-right">
        <?php if(isLoggedIn()): ?>
            <span style="color: white; font-size: 0.8rem; margin-right: 10px;">Halo, User!</span>
            <a href="auth/logout.php" class="btn-auth" style="background: #ef4444; color: white;">LOGOUT</a>
        <?php else: ?>
            <a href="auth/login.php" class="btn-auth">LOGIN</a>
        <?php endif; ?>
    </div>
</nav>

<header class="hero">
    <div class="hero-content">
        <h1>ADVENTURE AWAITS</h1>
        <p>Sewa perlengkapan camping terbaik untuk pendakianmu.</p>
        <a href="#katalog" class="btn-auth" style="padding: 1rem 2.5rem; font-size: 0.85rem; text-decoration: none;">LIHAT KATALOG</a>
    </div >
</header>

<main class="container" id="katalog">
    <div class="section-title">
        <h2>PERLENGKAPAN TERSEDIA</h2>
    </div>

    <div class="grid">
        <?php
        // Mengambil seluruh data termasuk kolom foto dari tabel produk
        $query = mysqli_query($conn, "SELECT * FROM produk LIMIT 6");
        while($data = mysqli_fetch_array($query)):
        ?>
        <div class="card">
            
            <div class="card-img" style="padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
                <?php if(!empty($data['foto']) && file_exists("assets/images/".$data['foto'])): ?>
                    <img src="assets/images/<?= $data['foto']; ?>" alt="<?= $data['nama_alat']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 4rem; padding: 20px;">⛺</span>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <span class="cat"><?= $data['kategori']; ?></span>
                <h3><?= $data['nama_alat']; ?></h3>
                
                <p style="font-size: 0.8rem; margin-top: 5px; color: #64748b;">
                    Sisa Stok: <strong style="color: <?= $data['stok'] > 0 ? '#10b981' : '#ef4444'; ?>;"><?= $data['stok']; ?> unit</strong>
                </p>

                <div class="card-footer" style="margin-top: 15px;">
                    <span class="price"><?= formatRupiah($data['harga_sewa']); ?></span>
                    
                    <?php if($data['stok'] > 0): ?>
                        <a href="sewa.php?id=<?= $data['id_produk']; ?>" class="btn-action">DETAIL</a>
                    <?php else: ?>
                        <button disabled class="btn-action" style="background: #cbd5e1; color: #94a3b8; border: none; cursor: not-allowed;">STOK HABIS</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</main>

<footer id="about">
    <div style="text-align: center;">
        <h3 style="margin-bottom: 15px; letter-spacing: 2px;">ELONCAMP.</h3>
        <p style="font-size: 0.9rem; opacity: 0.7;">Penyedia perlengkapan outdoor terpercaya.</p>
    </div>
    <div class="footer-text">
        &copy; 2026 ElonCamp Project. Dibuat dengan semangat petualangan.
    </div>
</footer>

</body>
</html>