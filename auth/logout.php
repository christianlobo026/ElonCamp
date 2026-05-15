<?php
// auth/logout.php
include '../config/database.php';

// Hapus semua data session
$_SESSION = [];
session_destroy();

// Redirect ke halaman login
header("Location: ../auth/login.php?pesan=logout");
exit;
?>