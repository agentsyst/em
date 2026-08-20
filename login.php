<?php
session_start();
require 'env_helper.php';

if (!isInstalled()) {
    header("Location: install.php");
    exit;
}

if (isset($_SESSION['client_logged_in'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $env = getEnvData();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $env['USERNAME'] && password_verify($password, $env['PASSWORD_HASH'])) {
        $_SESSION['client_logged_in'] = true;
        $_SESSION['force_refresh'] = true;
        
        $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['action' => 'client_get_dashboard_data']
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        
        if (isset($data['error']) && $data['error'] === 'EXPIRED_OR_UNPAID') {
            header("Location: renew.php");
            exit;
        }

        header("Location: index.php");
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - AgentSys</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class='bx bx-user-circle text-4xl text-blue-600'></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Selamat Datang</h2>
            <p class="text-gray-500 text-sm mt-1">Silakan masuk ke akun Anda</p>
        </div>

        <?php if($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-2 rounded-lg border focus:border-blue-500 outline-none focus:ring-2 focus:ring-blue-100 transition-all">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2 rounded-lg border focus:border-blue-500 outline-none focus:ring-2 focus:ring-blue-100 transition-all">
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg mt-6 flex justify-center items-center gap-2 transition-colors">
                <i class='bx bx-log-in'></i> Masuk Aplikasi
            </button>
        </form>
    </div>
</body>
</html>