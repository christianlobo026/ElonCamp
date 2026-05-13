<?php
// Konfigurasi Database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_rental_outdoor";

$conn = mysqli_connect($host, $user, $pass, $db);

if ($conn) {
    echo "<h1>Koneksi Berhasil!</h1>";
    
    // Ambil data dari database
    $query = mysqli_query($conn, "SELECT * FROM produk");
    while($data = mysqli_fetch_array($query)) {
        echo "Produk: " . $data['nama_produk'] . " | Harga: Rp" . $data['harga'];
    }
} else {
    echo "Koneksi Gagal: " . mysqli_connect_error();
}
?>