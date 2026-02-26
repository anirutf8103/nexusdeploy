<?php
// ฟังก์ชันเช็คและสร้างไฟล์ JSON เริ่มต้น (ถ้ายังไม่มี)
$requiredFiles = [
    'data/projects.json' => '[]',      // ค่าเริ่มต้นเป็น Array เปล่า
    'data/servers.json' => '[]',
    'data/logs.json' => '[]',
    'data/state.json' => '{}'    // ค่าเริ่มต้นเป็น Object เปล่า
];

// ถ้าไม่มีโฟลเดอร์ data ให้สร้างโฟลเดอร์ก่อน (เผื่อหลุด)
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}

// วนลูปเช็คไฟล์ ถ้าไม่มีให้สร้างขึ้นมาพร้อมค่าเริ่มต้น
foreach ($requiredFiles as $file => $defaultContent) {
    $filePath = __DIR__ . '/' . $file;
    if (!file_exists($filePath)) {
        file_put_contents($filePath, $defaultContent);
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition-all"></div>
        <div class="flex items-center justify-between z-10 relative">
            <div>
                <p class="text-sm text-gray-400 font-medium mb-1">Total Servers</p>
                <h3 class="text-3xl font-bold text-white tracking-wider glow-text" id="stat-servers">0</h3>
            </div>
            <div class="w-12 h-12 bg-dark-bg border border-dark-border rounded-lg flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                <i class="ph ph-hard-drives text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-accent/10 rounded-full blur-xl group-hover:bg-accent/20 transition-all"></div>
        <div class="flex items-center justify-between z-10 relative">
            <div>
                <p class="text-sm text-gray-400 font-medium mb-1">Active Projects</p>
                <h3 class="text-3xl font-bold text-white tracking-wider" id="stat-projects">0</h3>
            </div>
            <div class="w-12 h-12 bg-dark-bg border border-dark-border rounded-lg flex items-center justify-center text-accent group-hover:scale-110 transition-transform">
                <i class="ph ph-folder-open text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-xl group-hover:bg-purple-500/20 transition-all"></div>
        <div class="flex items-center justify-between z-10 relative">
            <div>
                <p class="text-sm text-gray-400 font-medium mb-1">Files Uploaded</p>
                <h3 class="text-3xl font-bold text-white tracking-wider" id="stat-files">0</h3>
            </div>
            <div class="w-12 h-12 bg-dark-bg border border-dark-border rounded-lg flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform">
                <i class="ph ph-files text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-yellow-500/10 rounded-full blur-xl group-hover:bg-yellow-500/20 transition-all"></div>
        <div class="flex items-center justify-between z-10 relative">
            <div>
                <p class="text-sm text-gray-400 font-medium mb-1">Deploy Time (Avg)</p>
                <h3 class="text-3xl font-bold text-white tracking-wider" id="stat-time">0s</h3>
            </div>
            <div class="w-12 h-12 bg-dark-bg border border-dark-border rounded-lg flex items-center justify-center text-yellow-400 group-hover:scale-110 transition-transform">
                <i class="ph ph-timer text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg relative h-96">
        <h2 class="text-white font-semibold mb-4 flex items-center gap-2"><i class="ph ph-chart-bar text-primary"></i> Deployments Overview (Last 7 Days)</h2>
        <div class="h-72 w-full relative">
            <canvas id="deployBarChart"></canvas>
        </div>
    </div>

    <div class="bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg relative h-96">
        <h2 class="text-white font-semibold mb-4 flex items-center gap-2"><i class="ph ph-target text-accent"></i> Success vs Failed Rate</h2>
        <div class="h-72 w-full relative flex items-center justify-center">
            <canvas id="statusDoughnutChart"></canvas>
        </div>
    </div>
</div>

<div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden mb-8">
    <div class="px-6 pt-6 mb-2">
        <h2 class="text-white font-semibold flex items-center gap-2">
            <i class="ph ph-trend-up text-purple-400"></i> Data Volume Transferred (Files per Day)
        </h2>
    </div>
    <div class="p-6 h-72 w-full relative">
        <canvas id="dataVolumeChart"></canvas>
    </div>
</div>

<div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg mb-8 relative" style="background-color: #111; background-image: radial-gradient(circle, #333 1px, transparent 1px); background-size: 20px 20px; overflow: hidden;">
    <div class="px-6 pt-6 mb-2 relative z-10">
        <h2 class="text-white font-semibold flex items-center gap-2">
            <i class="ph ph-share-network text-accent"></i> Infrastructure & Deployment Pipeline Topology
        </h2>
    </div>
    <div class="p-6 h-[500px] w-full relative z-10" id="topology-canvas">
        <div id="topology-loading" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
            <i class="ph ph-spinner-gap animate-spin text-3xl mb-2 text-primary"></i>
            <p>Loading topology map...</p>
        </div>
    </div>
</div>

<div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden">
    <div class="p-5 border-b border-dark-border flex justify-between items-center bg-gray-900/40">
        <h2 class="text-white font-semibold flex items-center gap-2"><i class="ph ph-activity text-primary animate-pulse"></i> Recent Activity</h2>
        <a href="logs.php" class="text-xs text-primary hover:text-white transition-colors">View All &rarr;</a>
    </div>
    <div class="p-0 max-h-96 overflow-y-auto custom-scrollbar">
        <table class="w-full text-left text-sm text-gray-400">
            <thead class="text-xs uppercase bg-dark-bg text-gray-500 sticky top-0 z-10 shadow-md">
                <tr>
                    <th scope="col" class="px-6 py-4 font-semibold tracking-wider">Date & Time</th>
                    <th scope="col" class="px-6 py-4 font-semibold tracking-wider">Project ID</th>
                    <th scope="col" class="px-6 py-4 font-semibold tracking-wider">Files Updated</th>
                    <th scope="col" class="px-6 py-4 font-semibold tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody id="recent-logs-body" class="divide-y divide-dark-border/50">
                <!-- Logs injected here -->
            </tbody>
        </table>
        <div id="logs-loading" class="p-8 text-center text-gray-500 hidden">
            <i class="ph ph-spinner-gap animate-spin text-3xl mb-2 text-primary"></i>
            <p>Loading activity radar...</p>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.9/dist/vis-network.min.js"></script>
<script src="assets/js/dashboard.js"></script>

<?php include 'includes/footer.php'; ?>