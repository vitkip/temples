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


// พระบวชใหม่แต่ละปี
$stmt = $pdo->query("SELECT YEAR(ordination_date) AS year, COUNT(*) AS monks_ordination FROM monks WHERE ordination_date IS NOT NULL GROUP BY YEAR(ordination_date) ORDER BY year DESC");
$ordination_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// พระสึกแต่ละปี
$stmt = $pdo->query("SELECT YEAR(resignation_date) AS year, COUNT(*) AS monks_resign FROM monks WHERE resignation_date IS NOT NULL GROUP BY YEAR(resignation_date) ORDER BY year DESC");
$resign_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// พระบวชใหม่ปีนี้
$monks_ordination = 0;
$current_year = date('Y');
foreach ($ordination_stats as $row) {
    if ($row['year'] == $current_year) {
        $monks_ordination = $row['monks_ordination'];
        break;
    }
}

// พระสึกปีนี้
$monks_resign = 0;
foreach ($resign_stats as $row) {
    if ($row['year'] == $current_year) {
        $monks_resign = $row['monks_resign'];
        break;
    }
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

        <!-- Visitor Statistics Section (Only for Superadmin) -->
        <?php if ($user_role === 'superadmin'): ?>
        <div class="card p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                ສະຖິຕິຜູ້ເຂົ້າເບິງເວັບໄຊ
            </h3>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-600 text-sm font-medium">ມື້ນີ້</p>
                            <p class="text-2xl font-bold text-blue-900" id="visitors-today">-</p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-eye text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-600 text-sm font-medium">ອາທິດນີ້</p>
                            <p class="text-2xl font-bold text-green-900" id="visitors-week">-</p>
                        </div>
                        <div class="text-green-500">
                            <i class="fas fa-calendar-week text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-600 text-sm font-medium">ເດືອນນີ້</p>
                            <p class="text-2xl font-bold text-purple-900" id="visitors-month">-</p>
                        </div>
                        <div class="text-purple-500">
                            <i class="fas fa-calendar-alt text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-600 text-sm font-medium">ທັງໝົດ</p>
                            <p class="text-2xl font-bold text-orange-900" id="visitors-total">-</p>
                        </div>
                        <div class="text-orange-500">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Visitor Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-4 border border-gray-200 rounded-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-medium text-gray-800">ສະຖິຕິ 30 ວັນທີ່ຜ່ານມາ</h4>
                        <button onclick="refreshVisitorChart()" class="text-blue-600 hover:text-blue-800 p-1 rounded transition" title="ໂຫຼດຂໍ້ມູນໃໝ່">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="h-64">
                        <canvas id="visitorChart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white p-4 border border-gray-200 rounded-lg">
                    <h4 class="text-lg font-medium text-gray-800 mb-4">ຂໍ້ມູນເພີ່ມເຕີມ</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">ໜ້າທີ່ເບິງວັນນີ້:</span>
                            <span class="font-medium text-gray-900" id="pageviews-today">-</span>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-sm text-gray-600 mb-2">ອຸປະກອນທີ່ໃຊ້ (ມື້ນີ້):</div>
                            <div class="grid grid-cols-3 gap-2 text-sm">
                                <div class="text-center">
                                    <div class="text-blue-600"><i class="fas fa-mobile-alt"></i></div>
                                    <div class="font-medium" id="device-mobile">-</div>
                                    <div class="text-gray-500">Mobile</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-green-600"><i class="fas fa-desktop"></i></div>
                                    <div class="font-medium" id="device-desktop">-</div>
                                    <div class="text-gray-500">Desktop</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-purple-600"><i class="fas fa-tablet-alt"></i></div>
                                    <div class="font-medium" id="device-tablet">-</div>
                                    <div class="text-gray-500">Tablet</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-sm text-gray-600 mb-2">ປະເທດທີ່ເຂົ້າເບິງຫຼາຍສຸດ:</div>
                            <div id="top-countries" class="space-y-1 text-sm">
                                <div class="text-gray-500">ກຳລັງໂຫຼດ...</div>
                            </div>
                        </div>
                    </div>
                </div>
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
                        ກິດຈະກໍາໃນວັນນີ້
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
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center" style="font-family: 'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', sans-serif;">
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
            <!-- กราฟแท่งแยกตาม แขวง เมือง และ วัด -->
            <canvas id="ordinationBarChart" height="220"></canvas>
            
            <script>
                let templeChartInstance = null;
                let ordinationBarChartInstance = null;

                document.addEventListener('DOMContentLoaded', function() {
                    initCharts();
                    initOrdinationChart();
                });

                function initCharts() {
                    const canvas = document.getElementById('templeChart');
                    if (!canvas) {
                        console.error('Chart canvas not found');
                        return;
                    }
                    
                    // Destroy existing chart if it exists
                    if (templeChartInstance) {
                        templeChartInstance.destroy();
                        templeChartInstance = null;
                    }
                    
                    const ctx = canvas.getContext('2d');
                    
                    // Show loading
                    ctx.fillStyle = '#9CA3AF';
                    ctx.font = "16px 'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif";
                    ctx.textAlign = 'center';
                    ctx.fillText('ກຳລັງໂຫຼດຂໍ້ມູນ...', canvas.width/2, canvas.height/2);
                    
                    // Get user role and ID from PHP
                    const userRole = '<?= $user_role ?>';
                    const userId = '<?= $user_id ?>';
                    
                    // Call API
                    const apiUrl = `<?= $base_url ?>api/temple_stats.php?user_role=${encodeURIComponent(userRole)}&user_id=${userId}`;
                    console.log('Fetching data from:', apiUrl);
                    
                    fetch(apiUrl)
                        .then(response => {
                            console.log('Response status:', response.status);
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Parsed data:', data);
                            
                            if (data.error) {
                                throw new Error(data.message || 'API Error');
                            }
                            
                            createChart(ctx, data);
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            showError(ctx, 'ບໍ່ສາມາດໂຫຼດຂໍ້ມູນໄດ້: ' + error.message);
                        });
                }

                function createChart(ctx, data) {
                    // Clear canvas first
                    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                    
                    // Check data
                    if (!Array.isArray(data) || data.length === 0) {
                        showError(ctx, 'ບໍ່ມີຂໍ້ມູນສະແດງ');
                        return;
                    }
                    
                    // Create Chart
                    templeChartInstance = new Chart(ctx, {
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
                                        usePointStyle: true,
                                        font: {
                                            family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                        }
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
                                    },
                                    bodyFont: {
                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                    },
                                    titleFont: {
                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                    }
                                }
                            }
                        }
                    });
                }

                function initOrdinationChart() {
                    const userRole = '<?= $user_role ?>';
                    const userId = '<?= $user_id ?>';
                    
                    fetch(`<?= $base_url ?>api/ordination_stats.php?user_role=${encodeURIComponent(userRole)}&user_id=${userId}`)
                        .then(res => res.json())
                        .then(data => {
                            // Prepare labels
                            const provinces = [...new Set(data.map(item => item.province))];
                            const districts = [...new Set(data.map(item => item.district))];
                            const temples = [...new Set(data.map(item => item.temple))];

                            // Prepare dataset for each level
                            const provinceData = provinces.map(prov => {
                                const filtered = data.filter(item => item.province === prov);
                                return {
                                    label: prov,
                                    monks_ordination: filtered.reduce((sum, i) => sum + (+i.monks_ordination_this_year || 0), 0),
                                    monks_resign: filtered.reduce((sum, i) => sum + (+i.monks_resign_this_year || 0), 0)
                                };
                            });
                            const districtData = districts.map(dist => {
                                const filtered = data.filter(item => item.district === dist);
                                return {
                                    label: dist,
                                    monks_ordination: filtered.reduce((sum, i) => sum + (+i.monks_ordination_this_year || 0), 0),
                                    monks_resign: filtered.reduce((sum, i) => sum + (+i.monks_resign_this_year || 0), 0)
                                };
                            });
                            const templeData = temples.map(temp => {
                                const filtered = data.filter(item => item.temple === temp);
                                return {
                                    label: temp,
                                    monks_ordination: filtered.reduce((sum, i) => sum + (+i.monks_ordination_this_year || 0), 0),
                                    monks_resign: filtered.reduce((sum, i) => sum + (+i.monks_resign_this_year || 0), 0)
                                };
                            });

                            // Default: show by province
                            renderOrdinationBarChart(provinceData, 'ຂໍ້ມູນພຣະບວດໃໝ່/ສຶກ (ແຍກຕາມແຂວງ)');

                            // Add dropdown for switching level
                            const container = document.getElementById('ordinationBarChart').parentNode;
                            let select = document.getElementById('ordinationLevelSelect');
                            if (!select) {
                                select = document.createElement('select');
                                select.id = 'ordinationLevelSelect';
                                select.className = 'mb-4 border rounded px-2 py-1';
                                select.style.fontFamily = "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif";
                                select.innerHTML = `
                                    <option value="province">ແຂວງ</option>
                                    <option value="district">ເມືອງ</option>
                                    <option value="temple">ວັດ</option>
                                `;
                                container.insertBefore(select, container.firstChild);
                            }
                            select.onchange = function() {
                                if (this.value === 'province') {
                                    renderOrdinationBarChart(provinceData, 'ຂໍ້ມູນພຣະບວດໃໝ່/ສຶກ (ແຍກຕາມແຂວງ)');
                                } else if (this.value === 'district') {
                                    renderOrdinationBarChart(districtData, 'ຂໍ້ມູນພຣະບວດໃໝ່/ສຶກ (ແຍກຕາມເມືອງ)');
                                } else {
                                    renderOrdinationBarChart(templeData, 'ຂໍ້ມູນພຣະບວດໃໝ່/ສຶກ (ແຍກຕາມວັດ)');
                                }
                            };

                            function renderOrdinationBarChart(dataset, title) {
                                const ctx = document.getElementById('ordinationBarChart').getContext('2d');
                                if (ordinationBarChartInstance) {
                                    ordinationBarChartInstance.destroy();
                                }
                                ordinationBarChartInstance = new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: dataset.map(d => d.label),
                                        datasets: [
                                            {
                                                label: 'ບວດໃໝ່ (ປີນີ້)',
                                                data: dataset.map(d => d.monks_ordination),
                                                backgroundColor: 'rgba(34,197,94,0.7)'
                                            },
                                            {
                                                label: 'ສຶກ (ປີນີ້)',
                                                data: dataset.map(d => d.monks_resign),
                                                backgroundColor: 'rgba(239,68,68,0.7)'
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: {
                                            title: {
                                                display: true,
                                                text: title,
                                                font: { size: 16, family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif" }
                                            },
                                            legend: { 
                                                position: 'top',
                                                labels: {
                                                    font: {
                                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            x: { 
                                                title: { display: false },
                                                ticks: {
                                                    font: {
                                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                                    }
                                                }
                                            },
                                            y: { 
                                                beginAtZero: true,
                                                ticks: {
                                                    font: {
                                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Ordination chart error:', error);
                        });
                }

                function showError(ctx, message) {
                    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                    ctx.fillStyle = '#EF4444';
                    ctx.font = "14px 'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif";
                    ctx.textAlign = 'center';
                    ctx.fillText(message, ctx.canvas.width/2, ctx.canvas.height/2);
                }

                function refreshChart() {
                    initCharts();
                }

                // Visitor Statistics Functions
                let visitorChartInstance = null;

                // Initialize visitor statistics
                <?php if ($user_role === 'superadmin'): ?>
                // Load visitor statistics on page load
                document.addEventListener('DOMContentLoaded', function() {
                    loadVisitorSummary();
                    initVisitorChart();
                });

                function loadVisitorSummary() {
                    fetch('<?= $base_url ?>api/visitor_summary.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update summary cards
                                document.getElementById('visitors-today').textContent = data.today || 0;
                                document.getElementById('visitors-week').textContent = data.week || 0;
                                document.getElementById('visitors-month').textContent = data.month || 0;
                                document.getElementById('visitors-total').textContent = data.total || 0;
                                
                                // Update additional info
                                if (data.today_pageviews) {
                                    document.getElementById('pageviews-today').textContent = data.today_pageviews;
                                }
                                
                                // Update device breakdown
                                if (data.device_breakdown) {
                                    document.getElementById('device-mobile').textContent = data.device_breakdown.mobile || 0;
                                    document.getElementById('device-desktop').textContent = data.device_breakdown.desktop || 0;
                                    document.getElementById('device-tablet').textContent = data.device_breakdown.tablet || 0;
                                }
                                
                                // Update top countries
                                if (data.top_countries && data.top_countries.length > 0) {
                                    const countriesDiv = document.getElementById('top-countries');
                                    countriesDiv.innerHTML = '';
                                    data.top_countries.forEach(country => {
                                        const div = document.createElement('div');
                                        div.className = 'flex justify-between items-center';
                                        div.innerHTML = `
                                            <span>${country.country}</span>
                                            <span class="font-medium">${country.count}</span>
                                        `;
                                        countriesDiv.appendChild(div);
                                    });
                                } else {
                                    document.getElementById('top-countries').innerHTML = '<div class="text-gray-500">ບໍ່ມີຂໍ້ມູນ</div>';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error loading visitor summary:', error);
                        });
                }

                function initVisitorChart() {
                    const canvas = document.getElementById('visitorChart');
                    if (!canvas) {
                        console.error('Visitor chart canvas not found');
                        return;
                    }

                    // Destroy existing chart if it exists
                    if (visitorChartInstance) {
                        visitorChartInstance.destroy();
                        visitorChartInstance = null;
                    }

                    const ctx = canvas.getContext('2d');
                    
                    // Show loading
                    ctx.fillStyle = '#9CA3AF';
                    ctx.font = "14px 'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif";
                    ctx.textAlign = 'center';
                    ctx.fillText('ກຳລັງໂຫຼດຂໍ້ມູນ...', canvas.width/2, canvas.height/2);

                    // Fetch visitor statistics
                    fetch('<?= $base_url ?>api/visitor_stats.php?period=30')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.daily_stats) {
                                createVisitorChart(ctx, data.daily_stats);
                            } else {
                                showError(ctx, 'ບໍ່ສາມາດໂຫຼດຂໍ້ມູນໄດ້');
                            }
                        })
                        .catch(error => {
                            console.error('Error loading visitor stats:', error);
                            showError(ctx, 'ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນ');
                        });
                }

                function createVisitorChart(ctx, data) {
                    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

                    if (!Array.isArray(data) || data.length === 0) {
                        showError(ctx, 'ບໍ່ມີຂໍ້ມູນສະແດງ');
                        return;
                    }

                    visitorChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.map(item => {
                                const date = new Date(item.date);
                                return date.toLocaleDateString('lo-LA', { 
                                    month: 'short', 
                                    day: 'numeric' 
                                });
                            }),
                            datasets: [{
                                label: 'ຜູ້ເຂົ້າເບິງປະຈໍາວັນ',
                                data: data.map(item => item.visitors),
                                borderColor: 'rgba(59, 130, 246, 1)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    },
                                    ticks: {
                                        font: {
                                            family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif",
                                            size: 11
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    },
                                    ticks: {
                                        font: {
                                            family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif",
                                            size: 11
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        font: {
                                            family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif",
                                            size: 12
                                        },
                                        usePointStyle: true,
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#ffffff',
                                    bodyColor: '#ffffff',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 1,
                                    titleFont: {
                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                    },
                                    bodyFont: {
                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return `ຜູ້ເຂົ້າເບິງ: ${context.parsed.y} ຄົນ`;
                                        }
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                }

                function refreshVisitorChart() {
                    loadVisitorSummary();
                    initVisitorChart();
                }
                <?php endif; ?>
            </script>
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
