<?php
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
$message = '';
$error = '';

$stmt = $db->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}
$app_title = $settings['app_title'] ?? 'Pemilihan Ketua & Wakil Ketua OSIS';
$app_period = $settings['app_period'] ?? date('Y') . '/' . (date('Y') + 1);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_candidate'])) {
        $chairman = $_POST['chairman_name'];
        $vice = $_POST['vice_chairman_name'];
        $vision = $_POST['vision'];
        $mission = $_POST['mission'];
        $photo = '';
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $photo);
        }
        
        $stmt = $db->prepare("INSERT INTO candidates (chairman_name, vice_chairman_name, vision, mission, photo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$chairman, $vice, $vision, $mission, $photo]);
        $message = "Kandidat berhasil ditambahkan.";
    } elseif (isset($_POST['delete_candidate'])) {
        $id = $_POST['candidate_id'];
        $stmt = $db->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $db->prepare("DELETE FROM votes WHERE candidate_id = ?");
        $stmt->execute([$id]);
        $message = "Kandidat berhasil dihapus.";
    } elseif (isset($_POST['generate_tokens'])) {
        $amount = (int)$_POST['amount'];
        if ($amount > 0 && $amount <= 2000) {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO tokens (code) VALUES (?)");
                for ($i = 0; $i < $amount; $i++) {
                    $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                    $stmt->execute([$code]);
                }
                $db->commit();
                $message = "$amount Kode unik berhasil dibuat.";
            } catch (PDOException $e) {
                $db->rollBack();
                $error = "Terjadi kesalahan saat membuat kode unik.";
            }
        } else {
            $error = "Jumlah harus antara 1 sampai 2000.";
        }
    } elseif (isset($_POST['delete_token'])) {
        $id = $_POST['token_id'];
        $stmt = $db->prepare("DELETE FROM tokens WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $db->prepare("DELETE FROM votes WHERE token_id = ?");
        $stmt->execute([$id]);
        $message = "Kode unik berhasil dihapus.";
    } elseif (isset($_POST['delete_all_tokens'])) {
        $db->exec("DELETE FROM tokens");
        $db->exec("DELETE FROM votes");
        $message = "Semua kode unik dan data suara berhasil dihapus.";
    } elseif (isset($_POST['save_settings'])) {
        $title = $_POST['app_title'];
        $period = $_POST['app_period'];
        $stmt = $db->prepare("REPLACE INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute(['app_title', $title]);
        $stmt->execute(['app_period', $period]);
        $message = "Pengaturan berhasil disimpan.";
        $app_title = $title;
        $app_period = $period;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pemilihan OSIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .token-print-card { 
                display: inline-block; width: 30%; margin: 1%; 
                border: 2px dashed #000; padding: 1rem; text-align: center; 
                box-sizing: border-box; font-family: monospace; 
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
    <nav class="bg-gray-900 text-white shadow-md no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center py-4 md:h-16">
                <div class="flex-shrink-0 mb-4 md:mb-0 flex items-center">
                    <img src="logo.png" alt="Logo" class="h-12 w-12 object-cover rounded-full border-2 border-gray-500 shadow mr-3" onerror="this.style.display='none'">
                    <span class="font-bold text-xl tracking-wider uppercase">Admin Panel</span>
                </div>
                <div class="flex flex-wrap justify-center gap-2 md:gap-4">
                    <a href="admin.php?action=dashboard" class="px-3 py-2 rounded-md text-sm font-medium <?= $action === 'dashboard' ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> transition">Dashboard</a>
                    <a href="admin.php?action=candidates" class="px-3 py-2 rounded-md text-sm font-medium <?= $action === 'candidates' ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> transition">Kandidat</a>
                    <a href="admin.php?action=tokens" class="px-3 py-2 rounded-md text-sm font-medium <?= $action === 'tokens' ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> transition">Kode Unik (Token)</a>
                    <a href="admin.php?action=settings" class="px-3 py-2 rounded-md text-sm font-medium <?= $action === 'settings' ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> transition">Pengaturan</a>
                    <a href="logout.php" class="px-3 py-2 rounded-md text-sm font-medium text-red-400 hover:bg-red-900 hover:text-white transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full no-print">
        
        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'dashboard'): ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Dashboard Hasil Pemungutan Suara</h2>
                <p class="text-gray-500 font-medium mt-1"><?= htmlspecialchars($app_title) ?> - Periode <?= htmlspecialchars($app_period) ?></p>
            </div>
            <?php
                $stmt = $db->query("SELECT COUNT(*) FROM tokens");
                $total_voters = $stmt->fetchColumn();
                
                $stmt = $db->query("SELECT COUNT(*) FROM tokens WHERE is_used = 1");
                $voted = $stmt->fetchColumn();
                
                $stmt = $db->query("
                    SELECT c.id, c.chairman_name, c.vice_chairman_name, c.photo, COUNT(v.id) as total_votes
                    FROM candidates c
                    LEFT JOIN votes v ON c.id = v.candidate_id
                    GROUP BY c.id
                    ORDER BY c.id ASC
                ");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Prepare data for Chart.js
                $chartLabels = [];
                $chartData = [];
                $chartColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                
                foreach ($results as $index => $res) {
                    $chartLabels[] = "Paslon " . ($index + 1);
                    $chartData[] = $res['total_votes'];
                }
            ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Kode Unik</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= $total_voters ?></p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Sudah Memilih</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?= $voted ?></p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Belum Memilih</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?= $total_voters - $voted ?></p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Grafik Perolehan Suara</h3>
                    <div class="relative h-64 w-full">
                        <canvas id="votesChart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Detail Kandidat</h3>
                    <div class="space-y-4">
                        <?php foreach ($results as $index => $result): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
                                <div class="flex items-center space-x-4">
                                    <?php if ($result['photo']): ?>
                                        <img src="uploads/<?= htmlspecialchars($result['photo']) ?>" class="w-12 h-12 rounded-full object-cover border">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                            <?= ($index + 1) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-bold text-gray-900">Paslon <?= ($index + 1) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($result['chairman_name']) ?> & <?= htmlspecialchars($result['vice_chairman_name']) ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-bold text-blue-600"><?= $result['total_votes'] ?></span>
                                    <span class="text-sm text-gray-500">suara</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($results)): ?>
                            <p class="text-gray-500 text-center py-4">Belum ada kandidat.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('votesChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($chartLabels) ?>,
                            datasets: [{
                                label: 'Jumlah Suara',
                                data: <?= json_encode($chartData) ?>,
                                backgroundColor: <?= json_encode(array_slice($chartColors, 0, count($chartData))) ?>,
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                });
            </script>
            
        <?php elseif ($action === 'candidates'): ?>
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Manajemen Kandidat</h2>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-bold border-b pb-3 mb-4">Tambah Kandidat Baru</h3>
                <form method="POST" action="admin.php?action=candidates" enctype="multipart/form-data">
                    <input type="hidden" name="add_candidate" value="1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Calon Ketua</label>
                            <input type="text" name="chairman_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Calon Wakil Ketua</label>
                            <input type="text" name="vice_chairman_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Visi</label>
                        <textarea name="vision" rows="2" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Misi</label>
                        <textarea name="mission" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Foto Pasangan (Opsional)</label>
                        <input type="file" name="photo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 focus:outline-none">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition">Simpan Kandidat</button>
                </form>
            </div>
            
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Foto</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ketua & Wakil</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visi Singkat</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                                $stmt = $db->query("SELECT * FROM candidates");
                                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($candidates as $index => $candidate):
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $index + 1 ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($candidate['photo']): ?>
                                        <img src="uploads/<?= htmlspecialchars($candidate['photo']) ?>" class="h-10 w-10 rounded-full object-cover border">
                                    <?php else: ?>
                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xs">No Pic</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium"><?= htmlspecialchars($candidate['chairman_name']) ?></div>
                                    <div class="text-gray-500">& <?= htmlspecialchars($candidate['vice_chairman_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div class="truncate w-48" title="<?= htmlspecialchars($candidate['vision']) ?>"><?= htmlspecialchars($candidate['vision']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" action="admin.php?action=candidates" onsubmit="return confirm('Hapus kandidat ini?');" class="inline">
                                        <input type="hidden" name="delete_candidate" value="1">
                                        <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded transition">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($candidates)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Belum ada data kandidat.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($action === 'tokens'): ?>
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 md:mb-0">Manajemen Kode Unik (Token)</h2>
                <div class="flex space-x-2">
                    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2-2v4h10z"></path></svg>
                        Cetak Token
                    </button>
                    <form method="POST" action="admin.php?action=tokens" onsubmit="return confirm('PERINGATAN: Ini akan menghapus SEMUA token dan data suara yang sudah masuk. Anda yakin?');" class="inline">
                        <input type="hidden" name="delete_all_tokens" value="1">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition">Hapus Semua</button>
                    </form>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-bold border-b pb-3 mb-4">Buat Kode Unik Baru</h3>
                <form method="POST" action="admin.php?action=tokens" class="flex flex-col sm:flex-row items-end gap-4">
                    <input type="hidden" name="generate_tokens" value="1">
                    <div class="flex-1 w-full sm:w-auto">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Kode (1 - 2000)</label>
                        <input type="number" name="amount" min="1" max="2000" value="50" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition w-full sm:w-auto mt-2 sm:mt-0">Buat Kode</button>
                </form>
            </div>
            
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Unik</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                                $stmt = $db->query("SELECT * FROM tokens ORDER BY id DESC");
                                $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($tokens as $index => $token):
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $index + 1 ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 bg-gray-100 text-gray-800 font-mono text-lg tracking-wider rounded border border-gray-300"><?= htmlspecialchars($token['code']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($token['is_used']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Terpakai</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Belum Terpakai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" action="admin.php?action=tokens" onsubmit="return confirm('Hapus kode ini?');" class="inline">
                                        <input type="hidden" name="delete_token" value="1">
                                        <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($tokens)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">Belum ada kode unik yang digenerate.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($action === 'settings'): ?>
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 md:mb-0">Pengaturan Aplikasi</h2>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6 mb-8 max-w-2xl">
                <form method="POST" action="admin.php?action=settings">
                    <input type="hidden" name="save_settings" value="1">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Judul Aplikasi</label>
                        <input type="text" name="app_title" value="<?= htmlspecialchars($app_title) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Periode Jabatan</label>
                        <input type="text" name="app_period" value="<?= htmlspecialchars($app_period) ?>" placeholder="Misal: 2026/2027" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md transition w-full md:w-auto">Simpan Pengaturan</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Print Area for Tokens -->
    <?php if ($action === 'tokens'): ?>
        <div id="print-area" style="display: none;">
            <h2 style="text-align: center; margin-bottom: 20px; font-family: sans-serif;">Kupon Pemilihan OSIS</h2>
            <?php foreach ($tokens as $token): ?>
                <?php if (!$token['is_used']): ?>
                    <div class="token-print-card">
                        <div style="font-size: 12px; margin-bottom: 5px; font-family: sans-serif;">KODE UNIK PEMILIHAN</div>
                        <div style="font-size: 24px; font-weight: bold;"><?= htmlspecialchars($token['code']) ?></div>
                        <div style="font-size: 10px; margin-top: 5px; font-family: sans-serif;">Gunakan kode ini di website pemilihan.</div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <script>
            window.addEventListener("beforeprint", function() {
                document.getElementById('print-area').style.display = 'block';
            });
            window.addEventListener("afterprint", function() {
                document.getElementById('print-area').style.display = 'none';
            });
        </script>
    <?php endif; ?>
</body>
</html>
