<?php 
include '../config/database.php'; 

// Proteksi Admin: Jika bukan admin, tendang ke login
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil statistik sederhana
$total_trx = mysqli_num_rows(mysqli_query($conn, "SELECT id_transaksi FROM transaksi"));
$total_user = mysqli_num_rows(mysqli_query($conn, "SELECT id_user FROM users WHERE role = 'member'"));
$total_alat = mysqli_num_rows(mysqli_query($conn, "SELECT id_produk FROM produk"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | ElonCamp</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e293b; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 40px; background: #f1f5f9; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h3 { color: #64748b; font-size: 0.9rem; }
        .stat-card p { font-size: 2rem; font-weight: bold; color: #1e293b; }
        .sidebar a { display: block; color: #cbd5e1; text-decoration: none; padding: 10px 0; border-bottom: 1px solid #334155; }
        .sidebar a:hover { color: white; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>ELONADMIN</h2>
            <hr style="margin: 20px 0; opacity: 0.1;">
            <a href="index.php">Dashboard</a>
            <a href="transaksi.php">Kelola Transaksi</a>
            <a href="produk.php">Kelola Produk</a>
            <a href="../auth/logout.php" style="color: #fb7185;">Logout</a>
        </div>

        <div class="main-content">
            <h1>Selamat Datang, Admin!</h1>
            <p>Berikut adalah ringkasan data ElonCamp hari ini.</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Transaksi</h3>
                    <p><?= $total_trx; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Member Aktif</h3>
                    <p><?= $total_user; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Jumlah Alat</h3>
                    <p><?= $total_alat; ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>