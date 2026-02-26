<?php
$page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-dark-panel border-r border-dark-border h-full flex flex-col shrink-0">
    <div class="h-16 flex items-center px-6 border-b border-dark-border shrink-0">
        <div class="flex items-center space-x-2 text-primary">
            <i class="ph-fill ph-rocket-launch text-2xl drop-shadow-[0_0_8px_rgba(59,130,246,0.6)]"></i>
            <span class="text-xl font-bold tracking-wider text-white">NEXUS<span class="text-primary">DEPLOY</span></span>
        </div>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">
        <a href="index.php" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-dark-border hover:text-white transition-colors <?= $page == 'index.php' ? 'bg-dark-border text-white border-l-4 border-primary' : '' ?>">
            <i class="ph ph-squares-four text-xl <?= $page == 'index.php' ? 'text-primary drop-shadow-[0_0_5px_rgba(59,130,246,0.8)]' : '' ?>"></i>
            <span class="font-medium">Overview</span>
        </a>
        <a href="projects.php" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-dark-border hover:text-white transition-colors <?= $page == 'projects.php' ? 'bg-dark-border text-white border-l-4 border-primary' : '' ?>">
            <i class="ph ph-folder text-xl <?= $page == 'projects.php' ? 'text-primary drop-shadow-[0_0_5px_rgba(59,130,246,0.8)]' : '' ?>"></i>
            <span class="font-medium">Projects</span>
        </a>
        <a href="servers.php" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-dark-border hover:text-white transition-colors <?= $page == 'servers.php' ? 'bg-dark-border text-white border-l-4 border-primary' : '' ?>">
            <i class="ph ph-hard-drives text-xl <?= $page == 'servers.php' ? 'text-primary drop-shadow-[0_0_5px_rgba(59,130,246,0.8)]' : '' ?>"></i>
            <span class="font-medium">Servers</span>
        </a>
        <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</p>
        </div>
        <a href="deploy.php" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-dark-border hover:text-white transition-colors <?= $page == 'deploy.php' ? 'bg-dark-border text-white border-l-4 border-accent' : '' ?>">
            <i class="ph ph-cloud-arrow-up text-xl text-accent <?= $page == 'deploy.php' ? 'drop-shadow-[0_0_5px_rgba(16,185,129,0.8)]' : '' ?>"></i>
            <span class="font-medium <?= $page == 'deploy.php' ? 'text-white' : '' ?>">Deploy Now</span>
        </a>
        <a href="group_deploy.php" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-dark-border hover:text-white transition-colors <?= $page == 'group_deploy.php' ? 'bg-dark-border text-white border-l-4 border-purple-500' : '' ?>">
            <i class="ph ph-rocket text-xl text-purple-400 <?= $page == 'group_deploy.php' ? 'drop-shadow-[0_0_5px_rgba(168,85,247,0.8)]' : '' ?>"></i>
            <span class="font-medium <?= $page == 'group_deploy.php' ? 'text-white' : '' ?>">Group Deploy</span>
        </a>
        <a href="logs.php" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-dark-border hover:text-white transition-colors <?= $page == 'logs.php' ? 'bg-dark-border text-white border-l-4 border-primary' : '' ?>">
            <i class="ph ph-terminal text-xl <?= $page == 'logs.php' ? 'text-primary drop-shadow-[0_0_5px_rgba(59,130,246,0.8)]' : '' ?>"></i>
            <span class="font-medium">Activity Logs</span>
        </a>
    </nav>
    <div class="p-4 border-t border-dark-border mt-auto shrink-0 bg-dark-panel">
        <div class="flex items-center gap-3 bg-dark-bg p-3 rounded-md border border-dark-border">
            <i class="ph ph-cpu text-gray-400 text-lg"></i>
            <div>
                <p class="text-xs text-gray-400">Localhost Engine</p>
                <p class="text-[10px] text-accent font-mono flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-accent inline-block"></span> STATUS: ONLINE</p>
            </div>
        </div>
    </div>
</aside>