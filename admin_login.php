<?php
session_start();
require 'db.php';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['role'] = 'admin';
        header("Location: admin.php");
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
    <title>Admin Login - Pemilihan OSIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
    <header class="bg-gradient-to-r from-gray-800 to-gray-900 text-white py-6 shadow-md text-center flex flex-col items-center">
        <img src="logo.png" alt="Logo OSIS" class="h-32 w-32 object-cover rounded-full border-4 border-white shadow-xl mb-3" onerror="this.style.display='none'">
        <h1 class="text-2xl md:text-3xl font-bold px-4">Panel Administrator</h1>
    </header>
    
    <div class="flex-1 flex justify-center items-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 transform transition duration-300 hover:-translate-y-1 hover:shadow-2xl">
            <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Login Admin</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-5">
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="off" 
                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition">
                </div>
                
                <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-lg transition duration-200 shadow-md hover:shadow-lg transform hover:scale-[1.02]">
                    Masuk
                </button>
            </form>
            
            <div class="text-center mt-6">
                <a href="index.php" class="text-sm text-gray-500 hover:text-gray-800 transition">Kembali ke Halaman Pemilih</a>
            </div>
        </div>
    </div>
</body>
</html>
