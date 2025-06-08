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

<!-- Welcome Panel -->
<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">
                ສະບາຍດີ, <?= $_SESSION['user']['name'] ?>!
            </h2>
            <p class="text-gray-600 mt-1">
                <?= date('l, j F Y') ?> | ຜູ້ໃຊ້: <?= ucfirst($_SESSION['user']['role']) ?>
            </p>
        </div>
        
        <?php if ($userTemple): ?>
        <div class="mt-4 md:mt-0">
            <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-3 py-1 rounded-full">
                <?= $userTemple['name'] ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Temple Stats -->
    <div class="bg-white rounded-lg shadow-sm p-6 card">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                <i class="fas fa-landmark text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-700">ວັດທັງໝົດ</h3>
                <p class="text-3xl font-bold text-gray-900"><?= $templeCount ?></p>
            </div>
        </div>
    </div>
    
    <!-- Monk Stats -->
    <div class="bg-white rounded-lg shadow-sm p-6 card">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-pray text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-700">ພະສົງທັງໝົດ</h3>
                <p class="text-3xl font-bold text-gray-900"><?= $monkCount ?></p>
            </div>
        </div>
    </div>
    
    <!-- Event Stats -->
    <div class="bg-white rounded-lg shadow-sm p-6 card">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-700">ກິດຈະກໍາທີ່ຈະມາເຖິງ</h3>
                <p class="text-3xl font-bold text-gray-900"><?= $eventCount ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Events & Chart Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Events -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">ກິດຈະກໍາທີ່ຈະມາເຖິງ</h3>
        
        <?php if (count($recentEvents) > 0): ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($recentEvents as $event): ?>
                    <div class="py-4">
                        <div class="flex justify-between">
                            <h4 class="font-medium text-gray-900"><?= $event['title'] ?></h4>
                            <span class="text-sm text-indigo-600">
                                <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1"><?= $event['temple_name'] ?></p>
                        <p class="text-sm text-gray-700 mt-1 truncate">
                            <?= substr($event['description'], 0, 100) . (strlen($event['description']) > 100 ? '...' : '') ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= $base_url ?>events/" class="mt-4 inline-block text-indigo-600 hover:text-indigo-800">
                ເບິ່ງກິດຈະກໍາທັງໝົດ <i class="fas fa-arrow-right ml-1"></i>
            </a>
        <?php else: ?>
            <div class="py-4 text-center text-gray-500">
                ບໍ່ມີກິດຈະກໍາທີ່ຈະມາເຖິງ
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Chart -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">ສະຖິຕິວັດແຍກຕາມແຂວງ</h3>
        <div class="h-64">
            <canvas id="templeChart"></canvas>
        </div>
    </div>
</div>

<!-- Quick Access -->
<div class="mt-6">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">ເຂົ້າເຖິງດ່ວນ</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="<?= $base_url ?>temples/add.php" class="bg-white rounded-lg shadow-sm p-6 text-center hover:bg-indigo-50 transition card">
            <i class="fas fa-plus-circle text-3xl text-indigo-600 mb-3"></i>
            <h4 class="font-medium">ເພີ່ມວັດໃໝ່</h4>
        </a>
        
        <a href="<?= $base_url ?>monks/add.php" class="bg-white rounded-lg shadow-sm p-6 text-center hover:bg-indigo-50 transition card">
            <i class="fas fa-user-plus text-3xl text-indigo-600 mb-3"></i>
            <h4 class="font-medium">ເພີ່ມພະສົງໃໝ່</h4>
        </a>
        
        <a href="<?= $base_url ?>events/add.php" class="bg-white rounded-lg shadow-sm p-6 text-center hover:bg-indigo-50 transition card">
            <i class="fas fa-calendar-plus text-3xl text-indigo-600 mb-3"></i>
            <h4 class="font-medium">ສ້າງກິດຈະກໍາໃໝ່</h4>
        </a>
        
        <a href="<?= $base_url ?>reports/" class="bg-white rounded-lg shadow-sm p-6 text-center hover:bg-indigo-50 transition card">
            <i class="fas fa-chart-bar text-3xl text-indigo-600 mb-3"></i>
            <h4 class="font-medium">ລາຍງານ</h4>
        </a>
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
                        backgroundColor: 'rgba(79, 70, 229, 0.7)',
                        borderColor: 'rgba(79, 70, 229, 1)',
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
