<?php
require 'db.php';

$stmt = $db->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}
$app_title = $settings['app_title'] ?? 'Pemilihan Ketua & Wakil Ketua OSIS';
$app_period = $settings['app_period'] ?? date('Y') . '/' . (date('Y') + 1);

// Prepare API response for real-time polling
if (isset($_GET['api'])) {
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

    header('Content-Type: application/json');
    echo json_encode([
        'total_voters' => $total_voters,
        'voted' => $voted,
        'results' => $results
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Hasil Pemilihan OSIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-white h-screen flex flex-col overflow-hidden">
    <header class="bg-black/40 py-3 shadow-md flex-shrink-0">
        <div class="max-w-screen-2xl mx-auto px-6 flex justify-between items-center">
            <div class="flex items-center">
                <img src="logo.png" alt="Logo" class="h-12 w-12 object-cover rounded-full border-4 border-white shadow-xl mr-3" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-400">
                        Live Quick Count
                    </h1>
                    <p class="text-gray-400 text-xs mt-0.5 font-semibold"><?= htmlspecialchars($app_title) ?> - Periode <?= htmlspecialchars($app_period) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <!-- Compact Stats -->
                <div class="flex gap-3">
                    <div class="bg-gray-800 rounded-xl px-4 py-2 border-b-2 border-blue-500 text-center">
                        <p class="text-gray-400 text-xs font-bold uppercase tracking-wider">DPT</p>
                        <p id="stat-total" class="text-2xl font-extrabold text-white">0</p>
                    </div>
                    <div class="bg-gray-800 rounded-xl px-4 py-2 border-b-2 border-green-500 text-center">
                        <p class="text-gray-400 text-xs font-bold uppercase tracking-wider">Memilih</p>
                        <p id="stat-voted" class="text-2xl font-extrabold text-green-400">0</p>
                    </div>
                    <div class="bg-gray-800 rounded-xl px-4 py-2 border-b-2 border-red-500 text-center">
                        <p class="text-gray-400 text-xs font-bold uppercase tracking-wider">Belum</p>
                        <p id="stat-unvoted" class="text-2xl font-extrabold text-red-400">0</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="relative flex h-3 w-3 mr-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                    <span class="text-sm font-semibold uppercase tracking-wider text-red-400">Real Time</span>
                </div>
            </div>
        </div>
    </header>
    
    <main class="flex-1 max-w-screen-2xl mx-auto px-4 py-3 w-full grid grid-cols-3 gap-4 overflow-hidden">
        <!-- Candidate Positions (larger, left 2/3) -->
        <div class="col-span-2 bg-gray-800 rounded-2xl shadow-2xl p-5 flex flex-col overflow-hidden">
            <h2 class="text-lg font-bold text-gray-300 mb-3 border-b border-gray-700 pb-2 flex-shrink-0">🏆 Posisi Kandidat</h2>
            <div id="candidate-list" class="flex flex-col gap-3 flex-1 overflow-hidden" style="position: relative;">
                <!-- Loaded dynamically -->
            </div>
        </div>
        
        <!-- Chart Area (smaller, right 1/3) -->
        <div class="col-span-1 bg-gray-800 rounded-2xl shadow-2xl p-5 flex flex-col">
            <h2 class="text-lg font-bold text-gray-300 mb-3 text-center flex-shrink-0">Grafik Suara</h2>
            <div class="relative flex-1 w-full">
                <canvas id="liveChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        const chartColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
        let liveChart = null;
        let prevOrder = [];

        function buildCard(cand, originalIndex, rank, totalVoted) {
            const color = chartColors[originalIndex % chartColors.length];
            const picUrl = cand.photo ? 'uploads/' + cand.photo : '';
            const percentage = totalVoted > 0 ? ((cand.total_votes / totalVoted) * 100).toFixed(1) : 0;
            const medals = ['🥇', '🥈', '🥉'];
            const medal = medals[rank] || `#${rank + 1}`;
            return `
            <div class="bg-gray-700 rounded-xl flex items-center relative overflow-hidden transition-all duration-500 flex-1"
                 data-id="${cand.id}" style="border-left: 8px solid ${color}; padding: 6px 14px; min-height: 0;">
                <div class="mr-3 text-3xl w-9 text-center flex-shrink-0">${medal}</div>
                <div class="mr-4 flex-shrink-0">
                    ${cand.photo ?
                        `<div class="w-36 h-36 rounded-lg overflow-hidden border-4 bg-gray-600 shadow-lg" style="border-color:${color}">
                            <img src="${picUrl}" class="w-full h-full object-cover" alt="Foto Paslon ${originalIndex + 1}">
                        </div>` :
                        `<div class="w-36 h-36 rounded-lg bg-gray-600 flex items-center justify-center text-4xl font-bold text-gray-300 border-4" style="border-color:${color}">${originalIndex + 1}</div>`
                    }
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-extrabold text-2xl text-white truncate">${cand.chairman_name}</p>
                    <p class="text-lg text-gray-300 truncate">& ${cand.vice_chairman_name}</p>
                    <div class="mt-2 h-3 bg-gray-600 rounded-full overflow-hidden w-full">
                        <div class="h-3 rounded-full transition-all duration-700" style="width: ${percentage}%; background-color: ${color}"></div>
                    </div>
                </div>
                <div class="text-right ml-6 flex-shrink-0">
                    <p class="text-5xl font-extrabold leading-none" style="color: ${color}">${cand.total_votes}</p>
                    <p class="text-base text-gray-400 font-bold tracking-wider mt-1">${percentage}%</p>
                </div>
            </div>`;
        }

        function updateCandidateList(sortedResults, originalResults, totalVoted) {
            const container = document.getElementById('candidate-list');
            const existingIds = Array.from(container.children).map(el => el.dataset.id);
            const newOrder = sortedResults.map(c => String(c.id));
            const orderChanged = JSON.stringify(existingIds) !== JSON.stringify(newOrder);

            if (existingIds.length === 0) {
                // First render
                container.innerHTML = '';
                sortedResults.forEach((cand, rank) => {
                    const origIdx = originalResults.findIndex(c => c.id === cand.id);
                    const div = document.createElement('div');
                    div.innerHTML = buildCard(cand, origIdx, rank, totalVoted).trim();
                    const card = div.firstElementChild;
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    container.appendChild(card);
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, rank * 80);
                });
                return;
            }

            // Update vote counts & bars in place (no reorder animation needed if same order)
            if (!orderChanged) {
                container.querySelectorAll('[data-id]').forEach(card => {
                    const cand = sortedResults.find(c => String(c.id) === card.dataset.id);
                    if (!cand) return;
                    const origIdx = originalResults.findIndex(c => c.id === cand.id);
                    const rank = sortedResults.findIndex(c => c.id === cand.id);
                    card.outerHTML = buildCard(cand, origIdx, rank, totalVoted).trim();
                });
                return;
            }

            // FLIP animation: record old positions
            const oldPositions = {};
            container.querySelectorAll('[data-id]').forEach(card => {
                oldPositions[card.dataset.id] = card.getBoundingClientRect().top;
            });

            // Rebuild HTML in new sorted order
            container.innerHTML = '';
            sortedResults.forEach((cand, rank) => {
                const origIdx = originalResults.findIndex(c => c.id === cand.id);
                const div = document.createElement('div');
                div.innerHTML = buildCard(cand, origIdx, rank, totalVoted).trim();
                container.appendChild(div.firstElementChild);
            });

            // Play FLIP
            container.querySelectorAll('[data-id]').forEach(card => {
                const id = card.dataset.id;
                if (oldPositions[id] !== undefined) {
                    const newPos = card.getBoundingClientRect().top;
                    const delta = oldPositions[id] - newPos;
                    if (delta !== 0) {
                        card.style.transition = 'none';
                        card.style.transform = `translateY(${delta}px)`;
                        requestAnimationFrame(() => {
                            card.style.transition = 'transform 0.6s cubic-bezier(0.25,0.46,0.45,0.94)';
                            card.style.transform = 'translateY(0)';
                        });
                    }
                } else {
                    // New card fade in
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    requestAnimationFrame(() => {
                        card.style.transition = 'opacity 0.4s, transform 0.4s';
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    });
                }
            });
        }

        function updateUI(data) {
            // Update Stats
            document.getElementById('stat-total').innerText = data.total_voters;
            document.getElementById('stat-voted').innerText = data.voted;
            document.getElementById('stat-unvoted').innerText = data.total_voters - data.voted;
            
            // Prepare Chart Data (original order)
            const labels = [];
            const votes = [];
            const backgroundColors = [];

            data.results.forEach((cand, index) => {
                labels.push('Paslon ' + (index + 1));
                votes.push(cand.total_votes);
                backgroundColors.push(chartColors[index % chartColors.length]);
            });

            // Sort for sidebar (highest first)
            const sortedResults = [...data.results].sort((a, b) => b.total_votes - a.total_votes);

            updateCandidateList(sortedResults, data.results, data.voted);

            // Update or Initialize Chart
            if (!liveChart) {
                const ctx = document.getElementById('liveChart').getContext('2d');
                liveChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Jumlah Suara',
                            data: votes,
                            backgroundColor: backgroundColors,
                            borderRadius: 10,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 18 },
                                bodyFont: { size: 18, weight: 'bold' }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { 
                                    stepSize: 1,
                                    color: '#9ca3af',
                                    font: { size: 14 }
                                },
                                grid: { color: '#374151' }
                            },
                            x: {
                                ticks: { 
                                    color: '#e5e7eb',
                                    font: { size: 16, weight: 'bold' }
                                },
                                grid: { display: false }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            } else {
                liveChart.data.labels = labels;
                liveChart.data.datasets[0].data = votes;
                liveChart.update();
            }
        }

        function fetchLiveData() {
            fetch('live.php?api=1')
                .then(res => res.json())
                .then(data => {
                    updateUI(data);
                })
                .catch(err => console.error('Gagal mengambil data live:', err));
        }

        // Initial fetch
        fetchLiveData();

        // Refresh every 3 seconds
        setInterval(fetchLiveData, 3000);
    </script>
</body>
</html>
