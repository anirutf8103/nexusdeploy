<?php include 'includes/header.php'; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-wide">Server Management</h2>
        <p class="text-gray-400 text-sm mt-1">Configure your FTP connections</p>
    </div>
    <button id="btnNewServer" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-5 rounded-lg shadow-[0_0_15px_rgba(59,130,246,0.5)] transition-all transform hover:scale-105 flex items-center gap-2">
        <i class="ph ph-plus-circle text-xl"></i> Add Server
    </button>
</div>

<div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden">
    <table class="w-full text-left text-sm text-gray-400">
        <thead class="text-xs uppercase bg-dark-bg text-gray-500 border-b border-dark-border">
            <tr>
                <th scope="col" class="px-6 py-4 font-semibold">Server Name</th>
                <th scope="col" class="px-6 py-4 font-semibold">Host / IP</th>
                <th scope="col" class="px-6 py-4 font-semibold">Port</th>
                <th scope="col" class="px-6 py-4 font-semibold">Username</th>
                <th scope="col" class="px-6 py-4 font-semibold text-right">Actions</th>
            </tr>
        </thead>
        <tbody id="serversTableBody" class="divide-y divide-dark-border/50">
            <tr>
                <td colspan="5" class="text-center py-8 text-gray-500"><i class="ph ph-spinner animate-spin text-2xl mb-2 text-primary"></i><br>Loading servers...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="serverModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50 items-center justify-center">
    <div class="bg-dark-panel border border-dark-border rounded-xl shadow-2xl w-full max-w-2xl transform scale-95 opacity-0 transition-all duration-300 shadow-[0_0_50px_rgba(0,0,0,0.5)]" id="serverModalContent">
        <div class="flex justify-between items-center p-6 border-b border-dark-border bg-gray-900/40">
            <h3 class="text-xl font-bold text-white flex items-center gap-2" id="modalTitle"><i class="ph ph-hard-drives text-accent drop-shadow-[0_0_8px_rgba(16,185,129,0.8)]"></i> Edit Server</h3>
            <button id="closeModal" class="text-gray-400 hover:text-white transition-colors bg-dark-bg p-1 rounded hover:bg-red-500/20 hover:text-red-400"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <form id="serverForm" class="space-y-5">
                <input type="hidden" id="serverId">

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-300 flex items-center gap-1"><i class="ph ph-tag text-primary"></i> Server Name / Nickname</label>
                    <input type="text" id="serverName" required class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-accent focus:border-accent block p-2.5 transition-all outline-none" placeholder="Production Server">
                </div>

                <div class="grid grid-cols-12 gap-5">
                    <div class="col-span-9">
                        <label class="block mb-2 text-sm font-medium text-gray-300 flex items-center gap-1"><i class="ph ph-globe text-primary"></i> Host / IP Address</label>
                        <input type="text" id="serverHost" required class="w-full bg-dark-bg border border-dark-border text-white font-mono text-sm rounded-lg focus:ring-accent focus:border-accent block p-2.5 transition-all outline-none placeholder:font-sans placeholder:text-gray-500" placeholder="ftp.example.com or 192.168.1.10">
                    </div>
                    <div class="col-span-3">
                        <label class="block mb-2 text-sm font-medium text-gray-300 flex items-center gap-1"><i class="ph ph-door text-primary"></i> Port</label>
                        <input type="number" id="serverPort" required value="21" class="w-full bg-dark-bg border border-dark-border text-center font-mono text-white text-sm rounded-lg focus:ring-accent focus:border-accent block p-2.5 transition-all outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-5 pt-3 border-t border-dark-border/50">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300 flex items-center gap-1"><i class="ph ph-user text-primary"></i> Username</label>
                        <input type="text" id="serverUsername" required class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-accent focus:border-accent block p-2.5 transition-all outline-none">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300 flex items-center gap-1"><i class="ph ph-key text-primary"></i> Password <span class="text-xs text-gray-500">(Plaintext locally)</span></label>
                        <input type="password" id="serverPassword" class="w-full bg-dark-bg border border-dark-border text-white text-sm rounded-lg focus:ring-accent focus:border-accent block p-2.5 transition-all outline-none" placeholder="••••••••">
                    </div>
                </div>
            </form>
        </div>

        <!-- Connection Test Area -->
        <div class="px-6 py-4 bg-gray-900/60 border-t border-dark-border flex justify-between items-center">
            <div id="testResult" class="text-sm font-medium px-3 py-1 rounded hidden flex items-center gap-2"></div>
            <button type="button" id="btnTestConn" class="text-xs bg-dark-border hover:bg-gray-600 text-white py-1.5 px-3 rounded flex items-center gap-2 transition-colors border border-gray-600">
                <i class="ph ph-plugs"></i> Test Connection
            </button>
        </div>

        <div class="flex items-center justify-end p-6 border-t border-dark-border bg-gray-900 gap-3">
            <button id="btnCancel" class="text-gray-300 bg-transparent hover:bg-gray-800 border border-gray-600 focus:ring-4 focus:outline-none focus:ring-gray-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors">Cancel</button>
            <button id="btnSave" class="text-white bg-accent hover:bg-green-600 shadow-[0_0_15px_rgba(16,185,129,0.3)] hover:shadow-[0_0_20px_rgba(16,185,129,0.5)] focus:ring-4 focus:outline-none focus:ring-green-900 font-medium rounded-lg text-sm px-5 py-2.5 flex items-center gap-2 transition-all">
                <i class="ph ph-check-circle"></i> Save Server Details
            </button>
        </div>
    </div>
</div>

<script src="assets/js/servers.js"></script>

<?php include 'includes/footer.php'; ?>