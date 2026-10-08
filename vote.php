<?php
session_start();
require 'db.php';

if (!isset($_SESSION['token_id']) || $_SESSION['role'] !== 'voter') {
    header("Location: index.php");
    exit;
}

$token_id = $_SESSION['token_id'];
$message = '';
$error = '';

// Check if token is already used
$stmt = $db->prepare("SELECT is_used, code FROM tokens WHERE id = ?");
$stmt->execute([$token_id]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);
$has_voted = $token['is_used'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_voted) {
    $candidate_id = $_POST['candidate_id'] ?? 0;
    
    $stmt = $db->prepare("SELECT id FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    
    if ($stmt->fetch()) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO votes (token_id, candidate_id) VALUES (?, ?)");
            $stmt->execute([$token_id, $candidate_id]);
            
            $stmt = $db->prepare("UPDATE tokens SET is_used = 1 WHERE id = ?");
            $stmt->execute([$token_id]);
            
            $db->commit();
            $has_voted = true;
            $message = "Terima kasih, suara Anda telah berhasil disimpan.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Terjadi kesalahan saat menyimpan suara.";
        }
    } else {
        $error = "Kandidat tidak valid.";
    }
}

$stmt = $db->query("SELECT * FROM candidates");
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Suara - Pemilihan OSIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    <?php if ($has_voted): ?>
    <meta http-equiv="refresh" content="10;url=logout.php">
    <?php endif; ?>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center">
                    <img src="logo.png" alt="Logo" class="h-12 w-12 object-cover rounded-full border-2 border-blue-200 shadow mr-3" onerror="this.style.display='none'">
                    <span class="font-bold text-xl text-blue-600">Pemilihan OSIS</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm font-medium text-gray-600 bg-gray-100 px-3 py-1 rounded-full">Token: <span class="font-mono text-gray-800"><?= htmlspecialchars($token['code']) ?></span></span>
                    <a href="logout.php" class="text-sm font-medium text-red-600 hover:text-red-800 transition">Selesai / Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full">
        <div class="text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">Kandidat Ketua & Wakil Ketua OSIS</h2>
            <p class="mt-4 text-lg text-gray-500">Pilih salah satu kandidat terbaik menurut Anda. Pilihan tidak dapat diubah setelah dikonfirmasi.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8 rounded shadow-sm" role="alert">
                <p class="font-bold">Berhasil</p>
                <p><?= htmlspecialchars($message) ?></p>
                <p class="mt-2 text-sm">Halaman akan kembali secara otomatis dalam <span id="countdown1" class="font-bold">10</span> detik...</p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-8 rounded shadow-sm" role="alert">
                <p class="font-bold">Error</p>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($has_voted && !$message): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-8 rounded shadow-sm" role="alert">
                <p class="font-bold">Informasi</p>
                <p>Kode unik ini sudah digunakan untuk memberikan suara. Terima kasih atas partisipasi Anda!</p>
                <p class="mt-2 text-sm">Halaman akan kembali secara otomatis dalam <span id="countdown2" class="font-bold">10</span> detik...</p>
            </div>
        <?php endif; ?>

        <?php if ($has_voted): ?>
        <script>
            let timeLeft = 10;
            const el1 = document.getElementById('countdown1');
            const el2 = document.getElementById('countdown2');
            setInterval(() => {
                timeLeft--;
                if(el1) el1.innerText = timeLeft;
                if(el2) el2.innerText = timeLeft;
            }, 1000);
        </script>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($candidates as $index => $candidate): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden transition duration-300 transform hover:-translate-y-2 hover:shadow-2xl flex flex-col">
                    <?php if ($candidate['photo']): ?>
                        <div class="h-64 w-full bg-gray-200">
                            <img src="uploads/<?= htmlspecialchars($candidate['photo']) ?>" alt="Foto Kandidat" class="w-full h-full object-cover object-top">
                        </div>
                    <?php else: ?>
                        <div class="h-64 w-full bg-gradient-to-br from-blue-100 to-indigo-200 flex items-center justify-center">
                            <span class="text-6xl font-bold text-blue-500 opacity-50"><?= ($index + 1) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-6 flex-1 flex flex-col">
                        <div class="text-center mb-4 border-b pb-4">
                            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-bold uppercase tracking-wide mb-2">Nomor Urut <?= ($index + 1) ?></span>
                            <h3 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($candidate['chairman_name']) ?></h3>
                            <span class="text-gray-500 text-sm font-medium">&</span>
                            <h3 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($candidate['vice_chairman_name']) ?></h3>
                        </div>
                        
                        <div class="flex-1 space-y-4 mb-6">
                            <div>
                                <h4 class="font-semibold text-gray-800 flex items-center"><svg class="w-4 h-4 mr-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Visi</h4>
                                <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($candidate['vision'])) ?></p>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 flex items-center"><svg class="w-4 h-4 mr-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Misi</h4>
                                <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($candidate['mission'])) ?></p>
                            </div>
                        </div>
                        
                        <?php if (!$has_voted): ?>
                            <div class="mt-auto pt-4">
                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin memilih paslon Nomor Urut <?= ($index + 1) ?>? Pilihan ini bersifat final dan tidak dapat diubah.');">
                                    <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 shadow-md hover:shadow-lg flex justify-center items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Pilih Paslon Ini
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($candidates)): ?>
                <div class="col-span-full bg-white rounded-2xl shadow p-10 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada data kandidat</h3>
                    <p class="mt-1 text-sm text-gray-500">Silakan hubungi administrator.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
