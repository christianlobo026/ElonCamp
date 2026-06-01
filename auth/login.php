<?php
// auth/login.php
include '../config/database.php';

// Kalau sudah login, langsung redirect
if (isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = bersihkan($conn, $_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi!';
    } else {
        $query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' LIMIT 1");
        $user  = mysqli_fetch_assoc($query);

        if ($user && password_verify($password, $user['password'])) {
            // Simpan data ke session
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            // Arahkan sesuai role
            if ($user['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../index.php");
            }
            exit;
        } else {
            $error = 'Email atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ElonCamp</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            display: flex;
            width: 900px;
            min-height: 500px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }

        /* Panel kiri - ilustrasi */
        .panel-kiri {
            flex: 1;
            background: linear-gradient(160deg, #e67e22, #d35400);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            text-align: center;
        }

        .panel-kiri .logo { font-size: 5rem; margin-bottom: 20px; }
        .panel-kiri h2 { font-size: 2rem; font-weight: 800; margin-bottom: 10px; }
        .panel-kiri p { font-size: 1rem; opacity: 0.9; line-height: 1.6; }

        /* Panel kanan - form */
        .panel-kanan {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 50px 40px;
        }

        .panel-kanan h3 {
            font-size: 1.8rem;
            color: #1a1a2e;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .panel-kanan .sub { color: #888; margin-bottom: 30px; font-size: 0.95rem; }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            color: #555;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
            outline: none;
        }

        .form-group input:focus { border-color: #e67e22; }

        .btn-login {
            width: 100%;
            padding: 14px;
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

        .btn-login:hover { opacity: 0.9; transform: translateY(-1px); }

        .alert-error {
            background: #ffeaea;
            border-left: 4px solid #e74c3c;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .daftar-link {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 0.9rem;
        }

        .daftar-link a { color: #e67e22; font-weight: 600; text-decoration: none; }
        .daftar-link a:hover { text-decoration: underline; }

        @media (max-width: 700px) {
            .login-wrapper { flex-direction: column; width: 95%; }
            .panel-kiri { padding: 30px; }
            .panel-kiri .logo { font-size: 3rem; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- Panel Kiri -->
    <div class="panel-kiri">
        <div class="logo">⛺</div>
        <h2>ElonCamp</h2>
        <p>Sewa & Jual Perlengkapan<br>Camping Terlengkap.<br><br>Petualangan dimulai dari sini.</p>
    </div>

    <!-- Panel Kanan -->
    <div class="panel-kanan">
        <h3>Selamat Datang!</h3>
        <p class="sub">Masuk ke akun ElonCamp kamu</p>

        <?php if ($error): ?>
            <div class="alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="contoh@email.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">🔑 Masuk</button>
        </form>

        <div class="daftar-link">
            Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </div>
    </div>
</div>

</body>
</html>