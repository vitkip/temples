<?php
$page_title = 'ໜ້າຫຼັກ';
require_once 'config/db.php';
require_once 'config/base_url.php';
require_once 'includes/header.php';

// Count temples
$stmt = $pdo->query("SELECT COUNT(*) FROM temples WHERE status = 'active'");
$templeCount = $stmt->fetchColumn();

// Count monks
$stmt = $pdo->query("SELECT COUNT(*) FROM monks WHERE status = 'active'");
$monkCount = $stmt->fetchColumn();

// Count upcoming events
$stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
$eventCount = $stmt->fetchColumn();

// Get recent events
$stmt = $pdo->query("SELECT e.*, t.name as temple_name 
                     FROM events e 
                     LEFT JOIN temples t ON e.temple_id = t.id 
                     WHERE e.event_date >= CURDATE() 
                     ORDER BY e.event_date ASC 
                     LIMIT 5");
$recentEvents = $stmt->fetchAll();

// Get user's temple if they're associated with one
$userTempleId = $_SESSION['user']['temple_id'] ?? null;
$userTemple = null;
if ($userTempleId) {
    $stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
    $stmt->execute([$userTempleId]);
    $userTemple = $stmt->fetch();
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
                        <?= date('l, j F Y') ?> | ຜູ້ໃຊ້: <?= ucfirst($_SESSION['user']['role']) ?>
                    </p>
                </div>
                
                <?php if ($userTemple): ?>
                <div class="mt-4 md:mt-0">
                    <span class="status-badge status-active">
                        <i class="fas fa-place-of-worship"></i>
                        <?= $userTemple['name'] ?>
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
                        <h3 class="text-lg font-semibold text-gray-700">ວັດທັງໝົດ</h3>
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
                        <h3 class="text-lg font-semibold text-gray-700">ພະສົງທັງໝົດ</h3>
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
                        <h3 class="text-lg font-semibold text-gray-700">ກິດຈະກໍາທີ່ຈະມາເຖິງ</h3>
                        <p class="text-3xl font-bold text-amber-800"><?= $eventCount ?></p>
                    </div>
                </div>
            </div>
        </div>

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
                                    <div class="flex justify-between">
                                        <h4 class="font-medium text-gray-900"><?= $event['title'] ?></h4>
                                        <span class="text-sm text-amber-700">
                                            <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1"><?= $event['temple_name'] ?></p>
                                    <p class="text-sm text-gray-700 mt-2 truncate">
                                        <?= substr($event['description'], 0, 100) . (strlen($event['description']) > 100 ? '...' : '') ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= $base_url ?>events/" class="mt-4 inline-block text-amber-700 hover:text-amber-800 font-medium">
                            ເບິ່ງກິດຈະກໍາທັງໝົດ <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    <?php else: ?>
                        <div class="py-4 text-center text-gray-500">
                            ບໍ່ມີກິດຈະກໍາທີ່ຈະມາເຖິງ
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chart -->
            <div class="card">
                <div class="p-6 border-b border-amber-100">
                    <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                        <div class="category-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        ສະຖິຕິວັດແຍກຕາມແຂວງ
                    </h3>
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
                <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
                <a href="<?= $base_url ?>temples/add.php" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-plus-circle text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ເພີ່ມວັດໃໝ່</h4>
                </a>
                <?php endif; ?>
                
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
                
                <a href="<?= $base_url ?>reports/" class="card p-6 text-center hover:bg-amber-50 transition">
                    <div class="flex justify-center mb-3">
                        <div class="icon-circle">
                            <i class="fas fa-chart-bar text-xl"></i>
                        </div>
                    </div>
                    <h4 class="font-medium text-gray-900">ລາຍງານ</h4>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Dashboard specific chart initialization
window.initCharts = function() {
    // Chart for temple statistics by province
    fetch('<?= $base_url ?>api/temple_stats.php')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('templeChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.province),
                    datasets: [{
                        label: 'ຈໍານວນວັດ',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(212, 167, 98, 0.7)',
                        borderColor: 'rgba(176, 133, 66, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
        });
};
</script>

<?php require_once 'includes/footer.php'; ?>
