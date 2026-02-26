<?php include 'includes/header.php'; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-wide">Project Management</h2>
        <p class="text-gray-400 text-sm mt-1">Configure your local project directories</p>
    </div>
    <button id="btnNewProject" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-5 rounded-lg shadow-[0_0_15px_rgba(59,130,246,0.5)] transition-all transform hover:scale-105 flex items-center gap-2">
        <i class="ph ph-plus-circle text-xl"></i> Add Project
    </button>
</div>

<div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden">
    <table class="w-full text-left text-sm text-gray-400">
        <thead class="text-xs uppercase bg-dark-bg text-gray-500 border-b border-dark-border">
            <tr>
                <th scope="col" class="px-6 py-4 font-semibold">Project Name</th>
                <th scope="col" class="px-6 py-4 font-semibold">Local Path</th>
                <th scope="col" class="px-6 py-4 font-semibold">Server ID</th>
                <th scope="col" class="px-6 py-4 font-semibold text-right">Actions</th>
            </tr>
        </thead>
        <tbody id="projectsTableBody" class="divide-y divide-dark-border/50">
            <!-- Loading and data injected via JS -->
            <tr>
                <td colspan="4" class="text-center py-8 text-gray-500"><i class="ph ph-spinner animate-spin text-2xl mb-2 text-primary"></i><br>Loading projects...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="projectModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-dark-panel border border-dark-border rounded-xl shadow-2xl w-full max-w-2xl transform scale-95 opacity-0 transition-all duration-300" id="projectModalContent">
        <div class="flex justify-between items-center p-6 border-b border-dark-border">
            <h3 class="text-xl font-bold text-white flex items-center gap-2" id="modalTitle"><i class="ph ph-folder-notch-open text-primary"></i> Edit Project</h3>
            <button id="closeModal" class="text-gray-400 hover:text-white transition-colors"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-6">
            <form id="projectForm" class="space-y-5">
                <input type="hidden" id="projectId">
                <div class="grid grid-cols-2 gap-5">
                    <div class="col-span-2">
                        <label class="block mb-2 text-sm font-medium text-gray-300">Project Name</label>
                        <input type="text" id="projectName" required class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5 glow-border transition-all" placeholder="e.g. My Awesome Startup">
                    </div>
                    <div class="col-span-2">
                        <label class="block mb-2 text-sm font-medium text-gray-300">Local Path (Absolute)</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-800 border border-r-0 border-dark-border rounded-l-md font-mono">
                                <i class="ph ph-terminal-window"></i>
                            </span>
                            <input type="text" id="projectLocalPath" required class="rounded-none rounded-r-lg bg-dark-bg border border-dark-border text-white flex-1 min-w-0 w-full text-sm block p-2.5 focus:ring-primary focus:border-primary glow-border transition-all font-mono placeholder:font-sans placeholder:text-gray-500" placeholder="/Users/username/Projects/startup">
                        </div>
                    </div>
                </div>

                <div class="border-t border-dark-border pt-5 grid grid-cols-2 gap-5">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300 flex justify-between">Server Mapping <a href="servers.php" class="text-xs text-primary hover:underline">Manage</a></label>
                        <select id="projectServerId" class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5 glow-border">
                            <option value="">-- Select a Server --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300">Remote Path</label>
                        <input type="text" id="projectRemotePath" class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5 glow-border font-mono placeholder:font-sans placeholder:text-gray-500" placeholder="/public_html">
                    </div>
                </div>

                <div class="border-t border-dark-border pt-5">
                    <label class="block mb-2 text-sm font-medium text-gray-300">Webhook URL (Optional)</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-400 bg-gray-800 border border-r-0 border-dark-border rounded-l-md font-mono">
                            <i class="ph ph-link"></i>
                        </span>
                        <input type="url" id="projectWebhookUrl" class="rounded-none rounded-r-lg bg-dark-bg border border-dark-border text-white flex-1 min-w-0 w-full text-sm block p-2.5 focus:ring-primary focus:border-primary glow-border transition-all placeholder:font-sans placeholder:text-gray-500" placeholder="https://demo.example.com/api/?key=your_secret">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Fired automatically after a successful deployment.</p>
                </div>

                <div class="border-t border-dark-border pt-5">
                    <label class="block mb-2 text-sm font-medium text-gray-300 flex items-center justify-between">
                        <span>Smart Ignore List</span>
                        <span class="text-xs text-gray-500 font-normal">One per line</span>
                    </label>
                    <textarea id="projectIgnore" rows="4" class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5 glow-border font-mono leading-relaxed" placeholder=".git&#10;node_modules&#10;vendor&#10;.env"></textarea>
                    <p class="text-xs text-gray-500 mt-2"><i class="ph ph-info"></i> Files matching these names or directories will be skipped during deployment.</p>
                </div>
            </form>
        </div>
        <div class="flex items-center justify-end p-6 border-t border-dark-border bg-gray-900/40 gap-3">
            <button id="btnCancel" class="text-gray-300 bg-transparent hover:bg-gray-800 border border-gray-600 focus:ring-4 focus:outline-none focus:ring-gray-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors">Cancel</button>
            <button id="btnSave" class="text-white bg-primary hover:bg-primary-hover shadow-[0_0_10px_rgba(59,130,246,0.3)] focus:ring-4 focus:outline-none focus:ring-blue-900 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center gap-2 transition-all">
                <i class="ph ph-floppy-disk"></i> Save Configuration
            </button>
        </div>
    </div>
</div>

<script src="assets/js/projects.js"></script>

<?php include 'includes/footer.php'; ?>