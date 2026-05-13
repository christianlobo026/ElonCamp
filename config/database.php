<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_eloncamp";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi ke database ElonCamp gagal: " . mysqli_connect_error());
}
?>