<?php
session_start();
require 'db.php';

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: vote.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    $stmt = $db->prepare("SELECT * FROM tokens WHERE code = ?");
    $stmt->execute([$code]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token) {
        if ($token['is_used'] == 1) {
            $error = 'Kode unik ini sudah digunakan untuk memilih!';
        } else {
            $_SESSION['token_id'] = $token['id'];
            $_SESSION['role'] = 'voter';
            header("Location: vote.php");
            exit;
        }
    } else {
        $error = 'Kode unik tidak valid!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pemilihan OSIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-6 shadow-md text-center flex flex-col items-center">
        <img src="logo.png" alt="Logo OSIS" class="h-32 w-32 object-cover rounded-full border-4 border-white shadow-xl mb-3" onerror="this.style.display='none'">
        <h1 class="text-2xl md:text-3xl font-bold px-4">Pemilihan Ketua & Wakil Ketua OSIS</h1>
    </header>
    
    <div class="flex-1 flex justify-center items-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 transform transition duration-300 hover:-translate-y-1 hover:shadow-2xl">
            <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Masuk dengan Kode Unik</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="code" class="block text-sm font-semibold text-gray-700 mb-2">Kode Unik (Token)</label>
                    <input type="text" id="code" name="code" required autocomplete="off" placeholder="Masukkan 6 digit kode" 
                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-center text-lg font-mono tracking-widest uppercase">
                </div>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 shadow-md hover:shadow-lg transform hover:scale-[1.02]">
                    Masuk & Pilih
                </button>
            </form>
            
            <div class="text-center mt-6">
                <a href="admin_login.php" class="text-sm text-gray-500 hover:text-blue-600 transition">Login sebagai Admin</a>
            </div>
        </div>
    </div>
</body>
</html>
