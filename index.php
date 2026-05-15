<?php 
// Memanggil file koneksi dan helper
include 'config/database.php'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElonCamp - Sewa & Jual Alat Camping</title>
    <style>
        :root {
            --primary: #e67e22;
            --dark: #1a1a2e;
            --light: #f4f4f4;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }

        body { background-color: #f9f9f9; color: #333; }

        /* Navigation */
        nav {
            background: var(--dark);
            padding: 15px 10%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        nav .logo { color: white; font-size: 1.5rem; font-weight: bold; text-decoration: none; }
        nav ul { display: flex; list-style: none; gap: 20px; }
        nav ul li a { color: white; text-decoration: none; font-size: 0.9rem; }
        nav .auth-btn {
            background: var(--primary);
            color: white;
            padding: 8px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 60vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            padding: 0 20px;
        }
        .hero h1 { font-size: 3rem; margin-bottom: 10px; }
        .hero p { font-size: 1.2rem; margin-bottom: 25px; opacity: 0.9; }

        /* Catalog Section */
        .container { padding: 50px 10%; }
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        .section-title h2 { font-size: 2rem; color: var(--dark); }
        .section-title .line {
            width: 80px;
            height: 4px;
            background: var(--primary);
            margin: 10px auto;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
            border: 1px solid #eee;
        }
        .card:hover { transform: translateY(-5px); }
        .card-img {
            height: 180px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        .card-content { padding: 20px; }
        .card-content h3 { font-size: 1.1rem; margin-bottom: 10px; color: var(--dark); }
        .card-content .category {
            font-size: 0.8rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        .price { color: var(--primary); font-weight: bold; font-size: 1.1rem; }
        .btn-detail {
            padding: 8px 15px;
            border: 1px solid var(--primary);
            color: var(--primary);
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.85rem;
            transition: 0.3s;
        }
        .btn-detail:hover { background: var(--primary); color: white; }

        footer {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="logo">⛺ ElonCamp</a>
        <ul>
            <li><a href="index.php">Beranda</a></li>
            <li><a href="#katalog">Katalog</a></li>
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/index.php">Dashboard Admin</a></li>
                <?php endif; ?>
                <li><a href="auth/logout.php" style="color: #ff7675;">Logout (<?php echo $_SESSION['nama']; ?>)</a></li>
            <?php else: ?>
                <li><a href="auth/login.php" class="auth-btn">Masuk</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <section class="hero">
        <h1>Petualangan Menantimu</h1>
        <p>Sewa perlengkapan camping terbaik dengan harga terjangkau.</p>
        <a href="#katalog" class="auth-btn" style="padding: 12px 30px; font-size: 1rem;">Lihat Produk</a>
    </section>

    <div class="container" id="katalog">
        <div class="section-title">
            <h2>Katalog Perlengkapan</h2>
            <div class="line"></div>
        </div>

        <div class="grid">
            <?php
            // Ambil data dari tabel produk
            $query = mysqli_query($conn, "SELECT * FROM produk ORDER BY id_produk DESC");
            
            if (mysqli_num_rows($query) > 0) {
                while($data = mysqli_fetch_array($query)) {
                    // Emoji sederhana sebagai pengganti gambar jika belum ada file upload
                    $emoji = ($data['kategori'] == 'Tenda') ? '⛺' : (($data['kategori'] == 'Tas') ? '🎒' : '🔦');
            ?>
                <div class="card">
                    <div class="card-img"><?php echo $emoji; ?></div>
                    <div class="card-content">
                        <span class="category"><?php echo $data['kategori']; ?></span>
                        <h3><?php echo $data['nama_alat']; ?></h3>
                        <p style="font-size: 0.85rem; color: #666;">Stok tersedia: <?php echo $data['stok']; ?></p>
                        <div class="card-footer">
                            <span class="price"><?php echo formatRupiah($data['harga_sewa']); ?><small>/hari</small></span>
                            <a href="detail.php?id=<?php echo $data['id_produk']; ?>" class="btn-detail">Detail</a>
                        </div>
                    </div>
                </div>
            <?php 
                } 
            } else {
                echo "<p style='text-align:center; grid-column: 1/-1;'>Belum ada produk tersedia.</p>";
            }
            ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 ElonCamp Kelompok 4. Dibuat dengan ❤️ untuk UAS Pemrograman Web.</p>
    </footer>

</body>
</html>