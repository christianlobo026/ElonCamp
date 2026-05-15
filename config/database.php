<?php
// =============================================
// config/database.php
// Koneksi database ElonCamp
// =============================================

// Mulai session di sini agar semua halaman bisa pakai $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_eloncamp');

// Buat koneksi
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database ElonCamp gagal: " . mysqli_connect_error());
}

// Set charset agar karakter Indonesia tampil benar
mysqli_set_charset($conn, 'utf8mb4');

// =============================================
// HELPER FUNCTIONS
// Fungsi-fungsi pembantu yang dipakai di seluruh project
// =============================================

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Cek apakah user yang login adalah admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Paksa redirect ke halaman login jika belum login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /auth/login.php");
        exit;
    }
}

/**
 * Paksa redirect jika bukan admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /index.php?error=akses_ditolak");
        exit;
    }
}

/**
 * Format angka ke format Rupiah
 * Contoh: formatRupiah(50000) → "Rp 50.000"
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Buat kode transaksi unik
 * Format: EC-YYYYMMDD-XXXXX (contoh: EC-20260514-A3F2K)
 */
function buatKodeTransaksi() {
    return 'EC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/**
 * Hitung denda keterlambatan (FITUR PROBLEM SOLVER)
 * Denda = 20% dari harga sewa per hari × jumlah hari telat
 */
function hitungDenda($hargaPerHari, $jumlahItem, $hariTelat) {
    if ($hariTelat <= 0) return 0;
    $dendaPerHari = $hargaPerHari * $jumlahItem * 0.2;
    return $dendaPerHari * $hariTelat;
}

/**
 * Sanitasi input untuk keamanan
 */
function bersihkan($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}
?>