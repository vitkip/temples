<?php
$page_title = 'ໜ້າຫຼັກ';
require_once 'config/db.php';
require_once 'config/base_url.php';
require_once 'includes/header.php';

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// Initialize counters
$templeCount = 0;
$monkCount = 0;
$eventCount = 0;
$recentEvents = [];

// Get data based on user role
if ($user_role === 'superadmin') {
    // Superadmin can see all data
    
    // Count all active temples
    $stmt = $pdo->query("SELECT COUNT(*) FROM temples WHERE status = 'active'");
    $templeCount = $stmt->fetchColumn();
    
    // Count all active monks
    $stmt = $pdo->query("SELECT COUNT(*) FROM monks WHERE status = 'active'");
    $monkCount = $stmt->fetchColumn();
    
    // Count all upcoming events
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
    $eventCount = $stmt->fetchColumn();
    
    // Get recent events with temple and province info
    $stmt = $pdo->query("
        SELECT e.*, t.name as temple_name, p.province_name 
        FROM events e 
        LEFT JOIN temples t ON e.temple_id = t.id 
        LEFT JOIN provinces p ON t.province_id = p.province_id
        WHERE e.event_date >= CURDATE() 
        ORDER BY e.event_date ASC 
        LIMIT 5
    ");
    $recentEvents = $stmt->fetchAll();

} elseif ($user_role === 'admin') {
    // Admin can only see data from their temple
    
    if ($user_temple_id) {
        // Count only their temple (1 if active, 0 if inactive)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM temples WHERE id = ? AND status = 'active'");
        $stmt->execute([$user_temple_id]);
        $templeCount = $stmt->fetchColumn();
        
        // Count monks from their temple only
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE temple_id = ? AND status = 'active'");
        $stmt->execute([$user_temple_id]);
        $monkCount = $stmt->fetchColumn();
        
        // Count events from their temple only
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE temple_id = ? AND event_date >= CURDATE()");
        $stmt->execute([$user_temple_id]);
        $eventCount = $stmt->fetchColumn();
        
        // Get recent events from their temple only
        $stmt = $pdo->prepare("
            SELECT e.*, t.name as temple_name, p.province_name 
            FROM events e 
            LEFT JOIN temples t ON e.temple_id = t.id 
            LEFT JOIN provinces p ON t.province_id = p.province_id
            WHERE e.temple_id = ? AND e.event_date >= CURDATE() 
            ORDER BY e.event_date ASC 
            LIMIT 5
        ");
        $stmt->execute([$user_temple_id]);
        $recentEvents = $stmt->fetchAll();
    }

} elseif ($user_role === 'province_admin') {
    // Province admin can see data from temples in their assigned provinces
    
    // Count temples in assigned provinces
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM temples t
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE upa.user_id = ? AND t.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $templeCount = $stmt->fetchColumn();
    
    // Count monks from temples in assigned provinces
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM monks m
        JOIN temples t ON m.temple_id = t.id
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE upa.user_id = ? AND m.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $monkCount = $stmt->fetchColumn();
    
    // Count events from temples in assigned provinces
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM events e
        JOIN temples t ON e.temple_id = t.id
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE upa.user_id = ? AND e.event_date >= CURDATE()
    ");
    $stmt->execute([$user_id]);
    $eventCount = $stmt->fetchColumn();
    
    // Get recent events from temples in assigned provinces
    $stmt = $pdo->prepare("
        SELECT e.*, t.name as temple_name, p.province_name 
        FROM events e 
        JOIN temples t ON e.temple_id = t.id 
        JOIN provinces p ON t.province_id = p.province_id
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE upa.user_id = ? AND e.event_date >= CURDATE() 
        ORDER BY e.event_date ASC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recentEvents = $stmt->fetchAll();
}

// Get user's temple info if they're associated with one
$userTemple = null;
if ($user_temple_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, d.district_name, p.province_name 
        FROM temples t 
        LEFT JOIN districts d ON t.district_id = d.district_id
        LEFT JOIN provinces p ON t.province_id = p.province_id
        WHERE t.id = ?
    ");
    $stmt->execute([$user_temple_id]);
    $userTemple = $stmt->fetch();
}

// Get province stats for province_admin
$provinceStats = [];
if ($user_role === 'province_admin') {
    $stmt = $pdo->prepare("
        SELECT 
            p.province_name,
            COUNT(DISTINCT t.id) as temple_count,
            COUNT(DISTINCT m.id) as monk_count
        FROM provinces p
        JOIN user_province_access upa ON p.province_id = upa.province_id
        LEFT JOIN temples t ON p.province_id = t.province_id AND t.status = 'active'
        LEFT JOIN monks m ON t.id = m.temple_id AND m.status = 'active'
        WHERE upa.user_id = ?
        GROUP BY p.province_id, p.province_name
        ORDER BY p.province_name
    ");
    $stmt->execute([$user_id]);
    $provinceStats = $stmt->fetchAll();
}
?>

<!-- เพิ่ม link เพื่อนำเข้า monk-style.css -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<div class="page-container min-h-screen py-6">
    <!-- Welcome Panel -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="header-section p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                        <div class="category-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        ສະບາຍດີ, <?= $_SESSION['user']['name'] ?>!
                    </h2>
                    <p class="text-amber-700 mt-1">
                        <?= date('l, j F Y') ?> | 
                        ຜູ້ໃຊ້: <?php 
                            $role_names = [
                                'superadmin' => 'ຜູ້ດູແລລະບົບ',
                                'admin' => 'ຜູ້ດູແລວັດ',
                                'province_admin' => 'ຜູ້ດູແລແຂວງ',
                                'user' => 'ຜູ້ໃຊ້ທົ່ວໄປ'
                            ];
                            echo $role_names[$user_role] ?? ucfirst($user_role);
                        ?>
                    </p>
                </div>
                
                <?php if ($userTemple): ?>
                <div class="mt-4 md:mt-0">
                    <span class="status-badge status-active">
                        <i class="fas fa-place-of-worship"></i>
                        <?= htmlspecialchars($userTemple['name']) ?>
                        <?php if (!empty($userTemple['province_name'])): ?>
                            <small class="block text-xs"><?= htmlspecialchars($userTemple['province_name']) ?></small>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Temple Stats -->
            <div class="card p-6">
                <div class="flex items-center">
                    <div class="icon-circle">
                        <i class="fas fa-landmark text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">
                            <?php if ($user_role === 'admin'): ?>
                                ວັດຂອງທ່ານ
                            <?php elseif ($user_role === 'province_admin'): ?>
                                ວັດໃນແຂວງທີ່ຮັບຜິດຊອບ
                            <?php else: ?>
                                ວັດທັງໝົດ
                            <?php endif; ?>
                        </h3>
                        <p class="text-3xl font-bold text-amber-800"><?= $templeCount ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Monk Stats -->
            <div class="card p-6">
                <div class="flex items-center">
                    <div class="icon-circle">
                        <i class="fas fa-pray text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">
                            <?php if ($user_role === 'admin'): ?>
                                ພະສົງໃນວັດ
                            <?php elseif ($user_role === 'province_admin'): ?>
                                ພະສົງໃນແຂວງທີຮັບຜິດຊອບ
                            <?php else: ?>
                                ພະສົງທັງໝົດ
                            <?php endif; ?>
                        </h3>
                        <p class="text-3xl font-bold text-amber-800"><?= $monkCount ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Event Stats -->
            <div class="card p-6">
                <div class="flex items-center">
                    <div class="icon-circle">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">
                            <?php if ($user_role === 'admin'): ?>
                                ກິດຈະກໍາວັດ
                            <?php elseif ($user_role === 'province_admin'): ?>
                                ກິດຈະກໍາໃນແຂວງ
                            <?php else: ?>
                                ກິດຈະກໍາທີ່ຈະມາເຖິງ
                            <?php endif; ?>
                        </h3>
                        <p class="text-3xl font-bold text-amber-800"><?= $eventCount ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Province Stats for Province Admin -->
        <?php if ($user_role === 'province_admin' && !empty($provinceStats)): ?>
        <div class="card p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                ສະຖິຕິແຂວງທີ່ຮັບຜິດຊອບ
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($provinceStats as $stat): ?>
                <div class="bg-amber-50 p-4 rounded-lg border border-amber-200">
                    <h4 class="font-medium text-amber-900 mb-2">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <?= htmlspecialchars($stat['province_name']) ?>
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="text-gray-600">ວັດ:</span>
                            <span class="font-medium text-amber-800"><?= $stat['temple_count'] ?></span>
                        </div>
                        <div>
                            <span class="text-gray-600">ພະສົງ:</span>
                            <span class="font-medium text-amber-800"><?= $stat['monk_count'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Events & Chart Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Recent Events -->
            <div class="card">
                <div class="p-6 border-b border-amber-100">
                    <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                        <div class="category-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        ກິດຈະກໍາທີ່ຈະມາເຖິງ
                    </h3>
                </div>
                
                <div class="p-6">
                    <?php if (count($recentEvents) > 0): ?>
                        <div class="divide-y divide-amber-100">
                            <?php foreach ($recentEvents as $event): ?>
                                <div class="py-4">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></h4>
                                            <p class="text-sm text-gray-500 mt-1">
                                                <i class="fas fa-place-of-worship mr-1"></i>
                                                <?= htmlspecialchars($event['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                                                <?php if (!empty($event['province_name'])): ?>
                                                    <span class="text-amber-600 ml-2">
                                                        (<?= htmlspecialchars($event['province_name']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!empty($event['description'])): ?>
                                            <p class="text-sm text-gray-700 mt-2 truncate">
                                                <?= substr(htmlspecialchars($event['description']), 0, 100) . (strlen($event['description']) > 100 ? '...' : '') ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-sm text-amber-700 font-medium ml-4 flex-shrink-0">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= $base_url ?>events/" class="mt-4 inline-block text-amber-700 hover:text-amber-800 font-medium transition">
                            ເບິ່ງກິດຈະກໍາທັງໝົດ <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    <?php else: ?>
                        <div class="py-8 text-center text-gray-500">
                            <i class="fas fa-calendar-times text-4xl mb-3 text-gray-300"></i>
                            <p>ບໍ່ມີກິດຈະກໍາທີ່ຈະມາເຖິງ</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chart -->
            <div class="card">
                <div class="p-6 border-b border-amber-100">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                            <div class="category-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <?php if ($user_role === 'province_admin'): ?>
                                ສະຖິຕິວັດໃນແຂວງທີຮັບຜິດຊອບ
                            <?php else: ?>
                                ສະຖິຕິວັດແຍກຕາມແຂວງ
                            <?php endif; ?>
                        </h3>
                        <button onclick="refreshChart()" class="text-amber-600 hover:text-amber-800 p-2 rounded-lg hover:bg-amber-50 transition" title="ໂຫຼດຂໍ້ມູນໃໝ່">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="h-64">
                        <canvas id="templeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="card p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                ເຂົ້າເຖິງດ່ວນ
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php if ($user_role === 'superadmin'): ?>
                <a href="<?= $base_url ?>temples/add.php" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-plus-circle text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ເພີ່ມວັດໃໝ່</h4>
                </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['superadmin', 'admin', 'province_admin'])): ?>
                <a href="<?= $base_url ?>monks/add.php" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-user-plus text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ເພີ່ມພະສົງໃໝ່</h4>
                </a>
                
                <a href="<?= $base_url ?>events/add.php" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-calendar-plus text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ສ້າງກິດຈະກໍາໃໝ່</h4>
                </a>
                <?php endif; ?>
                
                <a href="<?= $base_url ?>reports/" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-chart-bar text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ລາຍງານ</h4>
                </a>
                
                <!-- Browse Links -->
                <a href="<?= $base_url ?>temples/" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-gopuram text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ເບິ່ງວັດ</h4>
                </a>
                
                <a href="<?= $base_url ?>monks/" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ເບິ່ງພະສົງ</h4>
                </a>
                
                <a href="<?= $base_url ?>events/" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-calendar text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ເບິ່ງກິດຈະກໍາ</h4>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Dashboard specific chart initialization
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
});

function initCharts() {
    const canvas = document.getElementById('templeChart');
    if (!canvas) {
        console.error('Chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    
    // แสดง loading
    ctx.fillStyle = '#9CA3AF';
    ctx.font = '16px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('ກຳລັງໂຫຼດຂໍ້ມູນ...', canvas.width/2, canvas.height/2);
    
    // เรียก API
    const apiUrl = '<?= $base_url ?>api/temple_stats.php?user_role=<?= urlencode($user_role) ?>&user_id=<?= $user_id ?>';
    console.log('Fetching data from:', apiUrl);
    
    fetch(apiUrl)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text(); // ใช้ text() ก่อนเพื่อดู response
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.error) {
                    throw new Error(data.message || 'API Error');
                }
                
                createChart(ctx, data);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showError(ctx, 'ຂໍ້ມູນບໍ່ຖືກຕ້ອງ: ' + e.message);
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            showError(ctx, 'ບໍ່ສາມາດໂຫຼດຂໍ້ມູນໄດ້: ' + error.message);
        });
}

function createChart(ctx, data) {
    // ล้าง canvas ก่อน
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    
    // ตรวจสอบข้อมูล
    if (!Array.isArray(data) || data.length === 0) {
        showError(ctx, 'ບໍ່ມີຂໍ້ມູນສະແດງ');
        return;
    }
    
    // สร้าง Chart
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.province || 'ບໍ່ຊີ້ແຈງ'),
            datasets: [{
                label: 'ຈໍານວນວັດ',
                data: data.map(item => parseInt(item.count) || 0),
                backgroundColor: [
                    'rgba(212, 167, 98, 0.8)',
                    'rgba(176, 133, 66, 0.8)',
                    'rgba(200, 169, 126, 0.8)',
                    'rgba(164, 113, 88, 0.8)',
                    'rgba(145, 111, 73, 0.8)',
                    'rgba(191, 154, 108, 0.8)',
                    'rgba(158, 132, 97, 0.8)',
                    'rgba(183, 147, 112, 0.8)',
                    'rgba(167, 124, 89, 0.8)',
                    'rgba(198, 162, 125, 0.8)'
                ],
                borderColor: 'rgba(176, 133, 66, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} ວັດ (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function showError(ctx, message) {
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    ctx.fillStyle = '#EF4444';
    ctx.font = '14px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(message, ctx.canvas.width/2, ctx.canvas.height/2);
}

function refreshChart() {
    const existingChart = Chart.getChart('templeChart');
    if (existingChart) {
        existingChart.destroy();
    }
    initCharts();
}
</script>

<?php require_once 'includes/footer.php'; ?>
