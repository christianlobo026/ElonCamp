<?php
// auth/register.php
include '../config/database.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = bersihkan($conn, $_POST['nama']);
    $email    = bersihkan($conn, $_POST['email']);
    $password = $_POST['password'];
    $konfirm  = $_POST['konfirmasi_password'];
    $no_hp    = bersihkan($conn, $_POST['no_hp']);

    // Validasi
    if (empty($nama) || empty($email) || empty($password) || empty($no_hp)) {
        $error = 'Semua field wajib diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $konfirm) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } else {
        // Cek email sudah terdaftar
        $cek = mysqli_query($conn, "SELECT id_user FROM users WHERE email = '$email'");        if (mysqli_num_rows($cek) > 0) {
            $error = 'Email sudah terdaftar! Gunakan email lain.';
        } else {
            // Simpan ke database
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = mysqli_query($conn,
                "INSERT INTO users (nama, email, password, no_hp, role)
                 VALUES ('$nama', '$email', '$hash', '$no_hp', 'member')"
            );

            if ($insert) {
                $success = 'Akun berhasil dibuat! Silakan login.';
            } else {
                $error = 'Gagal membuat akun. Coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - ElonCamp</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 0;
        }

        .register-box {
            background: white;
            width: 480px;
            border-radius: 20px;
            padding: 45px 40px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }

        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-area .icon { font-size: 3rem; }

        .logo-area h2 {
            font-size: 1.8rem;
            color: #1a1a2e;
            font-weight: 800;
            margin-top: 8px;
        }

        .logo-area p { color: #888; font-size: 0.9rem; }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            color: #555;
            margin-bottom: 6px;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 11px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
            outline: none;
        }

        .form-group input:focus { border-color: #e67e22; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .btn-daftar {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.3s, transform 0.2s;
            margin-top: 5px;
        }

        .btn-daftar:hover { opacity: 0.9; transform: translateY(-1px); }

        .alert-error {
            background: #ffeaea;
            border-left: 4px solid #e74c3c;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.88rem;
        }

        .alert-success {
            background: #eafaf1;
            border-left: 4px solid #27ae60;
            color: #1e8449;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.88rem;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 0.9rem;
        }

        .login-link a { color: #e67e22; font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        @media (max-width: 520px) {
            .register-box { width: 95%; padding: 30px 20px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="register-box">
    <div class="logo-area">
        <div class="icon">⛺</div>
        <h2>Buat Akun ElonCamp</h2>
        <p>Daftar gratis dan mulai petualanganmu</p>
    </div>

    <?php if ($error): ?>
        <div class="alert-error">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success">
            ✅ <?php echo $success; ?>
            <br><a href="login.php" style="color:#1e8449; font-weight:bold;">→ Klik di sini untuk login</a>
        </div>
    <?php else: ?>

    <form method="POST">
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" placeholder="Nama kamu"
                   value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="contoh@email.com"
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label>No. HP / WhatsApp</label>
            <input type="text" name="no_hp" placeholder="08xxxxxxxxxx"
                   value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min. 6 karakter" required>
            </div>
            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input type="password" name="konfirmasi_password" placeholder="Ulangi password" required>
            </div>
        </div>

        <button type="submit" class="btn-daftar">🏕️ Buat Akun</button>
    </form>

    <?php endif; ?>

    <div class="login-link">
        Sudah punya akun? <a href="login.php">Masuk di sini</a>
    </div>
</div>

</body>
</html>