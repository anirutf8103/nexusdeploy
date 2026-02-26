<?php include 'includes/header.php'; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-wide">Group Deployment Engine</h2>
        <p class="text-gray-400 text-sm mt-1">Deploy projects sharing the same local path to multiple servers simultaneously.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Groups List -->
    <div class="bg-dark-panel p-6 rounded-xl border border-dark-border shadow-lg space-y-4 flex flex-col max-h-[700px]">
        <div class="flex-1 overflow-y-auto custom-scrollbar" id="groupsList">
            <div class="text-center py-10 text-gray-500 font-mono text-sm">
                <i class="ph ph-spinner animate-spin text-2xl mb-2 text-primary"></i><br>Loading groups...
            </div>
        </div>
        <div id="webhookContainer" class="hidden shrink-0 mt-4 p-3 bg-dark-bg border border-dark-border rounded-lg flex items-center justify-between transition-all">
            <div>
                <h4 class="text-sm font-semibold text-white flex items-center gap-1"><i class="ph ph-link text-accent"></i> Post-Deploy Webhooks</h4>
                <p class="text-[10px] text-gray-500 mt-1">Triggers for configured projects</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="webhookToggle" class="sr-only peer" checked>
                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent shadow-[0_0_10px_rgba(16,185,129,0.2)]"></div>
            </label>
        </div>
    </div>

    <!-- Right Column: Files & Terminal -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Changes Preview -->
        <div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden flex flex-col h-72">
            <div class="p-4 border-b border-dark-border flex items-center justify-between bg-gray-900/40 shrink-0">
                <h3 class="text-white font-semibold flex items-center gap-2" id="changesTitle"><i class="ph ph-files text-primary"></i> Files to Upload <span class="bg-dark-border text-xs px-2 py-0.5 rounded text-gray-300 inline-block font-mono" id="changesCount">0</span></h3>
            </div>
            <div class="flex-1 overflow-y-auto custom-scrollbar bg-dark-bg p-0">
                <table class="w-full text-left text-sm text-gray-400">
                    <tbody id="changesList" class="divide-y divide-dark-border/30">
                        <tr>
                            <td class="text-center py-10 text-gray-500 font-mono text-xs">Select a group and click "Analyze Changes" to preview files.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Terminal Progress -->
        <div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden flex flex-col h-72 border-primary/30">
            <div class="px-4 py-2 border-b border-dark-border flex items-center justify-between bg-gray-900/80 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-red-500 border border-red-600"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500 border border-yellow-600"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500 border border-green-600"></div>
                </div>
                <span class="text-gray-400 font-mono text-xs flex items-center gap-2"><i class="ph ph-terminal text-primary"></i> Localhost Terminal Engine</span>
            </div>

            <div class="flex-1 bg-[#0b1120] p-4 text-xs font-mono overflow-y-auto custom-scrollbar relative" id="terminalWindow">
                <div class="text-gray-500 mb-2">NexusDeploy CLI v1.0.0 initializing...</div>
                <div class="text-gray-500">Awaiting group deployment instructions.</div>
                <!-- Dynamic logs -->
                <div id="terminalContent" class="mt-2 space-y-1 pb-10"></div>
            </div>

            <div class="bg-gray-900/60 border-t border-dark-border p-3 shrink-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-mono text-gray-400" id="progressText">0 / 0 Files Uploaded</span>
                    <span class="text-xs font-mono text-primary font-bold" id="progressPercent">0%</span>
                </div>
                <!-- Progress bar -->
                <div class="w-full bg-dark-bg rounded-full h-1.5 mb-1 relative overflow-hidden">
                    <div id="progressBar" class="bg-gradient-to-r from-primary to-accent h-1.5 rounded-full" style="width: 0%"></div>
                    <!-- animated shine block inside progress bar -->
                    <div class="absolute inset-0 bg-white/20 w-full h-full animate-[ping_1.5s_cubic-bezier(0,0,0.2,1)_infinite] hidden" id="progressShine"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/group_deploy.js"></script>

<?php include 'includes/footer.php'; ?>