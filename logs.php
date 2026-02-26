<?php include 'includes/header.php'; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-wide">Deployment History</h2>
        <p class="text-gray-400 text-sm mt-1">Audit log of all synchronizations</p>
    </div>
    <div class="flex gap-2">
        <button id="btnRefresh" class="bg-dark-panel hover:bg-dark-border text-gray-300 py-2 px-4 border border-dark-border rounded-lg transition-all flex items-center gap-2">
            <i class="ph ph-arrows-clockwise text-xl"></i> Refresh
        </button>
    </div>
</div>

<div class="bg-dark-panel rounded-xl border border-dark-border shadow-lg overflow-hidden">
    <div class="p-4 border-b border-dark-border flex items-center justify-between bg-gray-900/40">
        <div class="flex items-center space-x-2 text-sm">
            <i class="ph ph-funnel text-primary"></i> <span class="text-gray-400">Filter:</span>
            <select id="filterStatus" class="bg-dark-bg border border-dark-border text-gray-300 text-sm rounded focus:ring-primary focus:border-primary px-2 py-1 outline-none">
                <option value="all">All Statuses</option>
                <option value="Success">Success Only</option>
                <option value="Failed">Failed Only</option>
            </select>
            <select id="filterProject" class="bg-dark-bg border border-dark-border text-gray-300 text-sm rounded focus:ring-primary focus:border-primary px-2 py-1 outline-none max-w-[200px] truncate">
                <option value="all">All Projects</option>
            </select>
            <select id="filterServer" class="bg-dark-bg border border-dark-border text-gray-300 text-sm rounded focus:ring-primary focus:border-primary px-2 py-1 outline-none max-w-[200px] truncate">
                <option value="all">All Servers</option>
            </select>
        </div>
        <div class="text-xs text-gray-500 font-mono" id="logCount">Loading records...</div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-400">
            <thead class="text-xs uppercase bg-dark-bg text-gray-500 border-b border-dark-border">
                <tr>
                    <th scope="col" class="px-6 py-4 font-semibold">Timestamp</th>
                    <th scope="col" class="px-6 py-4 font-semibold">Project & Server</th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">Duration</th>
                    <th scope="col" class="px-6 py-4 font-semibold">Details</th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">Webhook Status</th>
                    <th scope="col" class="px-6 py-4 font-semibold text-right">FTP Status</th>
                </tr>
            </thead>
            <tbody id="logsTableBody" class="divide-y divide-dark-border/50">
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500"><i class="ph ph-spinner animate-spin text-2xl mb-2 text-primary"></i><br>Loading logs...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center">
    <div class="bg-dark-panel border border-dark-border rounded-xl shadow-[0_0_30px_rgba(0,0,0,0.8)] w-full max-w-3xl transform scale-95 opacity-0 transition-all duration-300" id="logModalContent">
        <div class="flex justify-between items-center p-5 border-b border-dark-border bg-gray-900/70">
            <h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="ph ph-files text-primary"></i> Files Uploaded</h3>
            <button id="closeLogModal" class="text-gray-400 hover:text-white transition-colors bg-dark-bg p-1 rounded hover:bg-red-500/20 hover:text-red-400"><i class="ph ph-x"></i></button>
        </div>
        <div class="p-0 max-h-[60vh] overflow-y-auto custom-scrollbar bg-dark-bg">
            <table class="w-full text-left text-xs text-gray-400 font-mono line-height-tight">
                <tbody id="logDetailsList" class="divide-y divide-dark-border/30">
                    <!-- File info inserted here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Webhook Details Modal -->
<div id="webhookModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center">
    <div class="bg-dark-panel border border-dark-border rounded-xl shadow-[0_0_30px_rgba(0,0,0,0.8)] w-full max-w-3xl transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh]" id="webhookModalContent">
        <div class="flex justify-between items-center p-5 border-b border-dark-border bg-gray-900/70 shrink-0">
            <h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="ph ph-terminal-window text-primary"></i> Webhook Response JSON</h3>
            <button id="closeWebhookModal" class="text-gray-400 hover:text-white transition-colors bg-dark-bg p-1 rounded hover:bg-red-500/20 hover:text-red-400"><i class="ph ph-x"></i></button>
        </div>
        <div class="p-4 flex-1 overflow-y-auto custom-scrollbar bg-[#0b1120]">
            <pre id="webhookResponseContent" class="text-sm text-gray-300 font-mono whitespace-pre-wrap break-all"></pre>
        </div>
        <div class="p-4 border-t border-dark-border bg-gray-900/40 shrink-0 flex justify-end">
            <button id="closeWebhookModalBtn" class="bg-dark-bg hover:bg-gray-800 text-gray-300 border border-dark-border py-2 px-4 rounded transition-colors text-sm">Close</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        let allLogs = [];
        let projectList = [];
        let serverList = [];

        const fetchLogs = async () => {
            const tbody = document.getElementById('logsTableBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500"><i class="ph ph-spinner animate-spin text-2xl mb-2 text-primary"></i></td></tr>';

            try {
                const [logRes, prjRes, srvRes] = await Promise.all([
                    fetch('api/logs.php'),
                    fetch('api/projects.php'),
                    fetch('api/servers.php')
                ]);

                allLogs = await logRes.json();

                // Sort by newest first
                allLogs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                projectList = await prjRes.json();
                serverList = await srvRes.json();

                const pFilter = document.getElementById('filterProject');
                pFilter.innerHTML = '<option value="all">All Projects</option>';
                projectList.forEach(p => pFilter.innerHTML += `<option value="${p.id}">${p.name}</option>`);

                const sFilter = document.getElementById('filterServer');
                sFilter.innerHTML = '<option value="all">All Servers</option>';
                serverList.forEach(s => sFilter.innerHTML += `<option value="${s.id}">${s.name} (${s.host})</option>`);

                renderLogs();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-400">Error loading logs</td></tr>';
            }
        };

        const renderLogs = () => {
            const tbody = document.getElementById('logsTableBody');
            const filter = document.getElementById('filterStatus').value;
            const fProject = document.getElementById('filterProject').value;
            const fServer = document.getElementById('filterServer').value;

            tbody.innerHTML = '';

            let filteredLogs = allLogs;
            if (filter !== 'all') {
                filteredLogs = filteredLogs.filter(l => l.status === filter);
            }
            if (fProject !== 'all') {
                filteredLogs = filteredLogs.filter(l => l.project_id === fProject);
            }
            if (fServer !== 'all') {
                filteredLogs = filteredLogs.filter(l => l.server_id === fServer);
            }

            document.getElementById('logCount').innerText = `${filteredLogs.length} Records found`;

            if (filteredLogs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No deployment logs available.</td></tr>';
                return;
            }

            filteredLogs.forEach((log, index) => {
                const date = new Date(log.created_at).toLocaleString();
                const badgeClass = log.status === 'Success' ? 'bg-green-500/20 text-green-400 border border-green-500/30 shadow-[0_0_10px_rgba(34,197,94,0.1)]' : 'bg-red-500/20 text-red-400 border border-red-500/30';
                const icon = log.status === 'Success' ? 'ph-check-circle' : 'ph-warning-circle';

                const prj = projectList.find(p => p.id === log.project_id);
                const srv = serverList.find(s => s.id === log.server_id);

                const prjName = prj ? prj.name : log.project_id;
                const srvName = srv ? `${srv.name} (${srv.host})` : log.server_id;

                const count = log.files_uploaded ? log.files_uploaded.length : 0;

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-dark-bg transition-colors';

                // Storing log index to view files later
                const actionBtn = count > 0 ? `<button onclick="viewFiles(${index})" class="text-xs text-primary hover:text-white underline decoration-primary/50 underline-offset-4 flex items-center gap-1"><i class="ph ph-list-magnifying-glass text-sm"></i> View ${count} Files</button>` : `<span class="text-xs text-gray-600">No files</span>`;

                let webhookBadge = '';
                if (log.webhook_result) {
                    const wr = log.webhook_result;
                    const statusCode = wr.status_code !== undefined ? wr.status_code : wr.webhook_status;
                    const whClass = wr.success ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30';
                    const whIcon = wr.success ? 'ph-check-circle' : 'ph-warning-circle';
                    const statusText = statusCode ? `${statusCode} ` + (wr.success ? `OK` : `Error`) : (wr.success ? `OK` : `Error`);
                    webhookBadge = `
                        <div class="flex items-center justify-center gap-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium cursor-help ${whClass}"><i class="ph ${whIcon}"></i> ${statusText}</span>
                            <button onclick="viewWebhookDetails(${index})" class="text-xs text-primary hover:text-white flex items-center gap-1 transition-colors px-2 py-1 bg-dark-bg border border-dark-border rounded hover:bg-gray-800"><i class="ph ph-magnifying-glass text-sm"></i> Details</button>
                        </div>
                    `;
                } else {
                    webhookBadge = `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-gray-800 text-gray-500 border border-gray-700 shadow-inner"><i class="ph ph-minus"></i> Skipped</span>`;
                }

                tr.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap"><span class="flex items-center gap-2"><i class="ph ph-clock text-gray-500"></i> ${date}</span></td>
                <td class="px-6 py-4 font-mono text-xs text-gray-300"><span class="font-bold text-accent">${prjName}</span><br><span class="text-[10px] text-gray-500">Srv: ${srvName}</span></td>
                <td class="px-6 py-4 text-center text-yellow-500 font-mono">${log.time_taken ? log.time_taken + 's' : '< 1s'}</td>
                <td class="px-6 py-4">${actionBtn}</td>
                <td class="px-6 py-4 text-center">${webhookBadge}</td>
                <td class="px-6 py-4 text-right">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium ${badgeClass}"><i class="ph ${icon}"></i> ${log.status}</span>
                </td>
            `;
                tbody.appendChild(tr);
            });
        };

        document.getElementById('filterStatus').addEventListener('change', renderLogs);
        document.getElementById('filterProject').addEventListener('change', renderLogs);
        document.getElementById('filterServer').addEventListener('change', renderLogs);
        document.getElementById('btnRefresh').addEventListener('click', fetchLogs);

        // View files logic
        window.viewFiles = (index) => {
            const log = allLogs[index];
            const files = log.files_uploaded || [];
            const tbody = document.getElementById('logDetailsList');
            tbody.innerHTML = '';

            if (files.length === 0) {
                tbody.innerHTML = '<tr><td class="px-4 py-3 text-center text-gray-500">No files found or recorded.</td></tr>';
            } else {
                files.forEach(f => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td class="px-6 py-2 whitespace-nowrap w-4 text-accent"><i class="ph ph-file-code"></i></td>
                    <td class="px-6 py-2 text-gray-300 break-all">${f}</td>
                `;
                    tbody.appendChild(tr);
                });
            }

            const modal = document.getElementById('logModal');
            const content = document.getElementById('logModalContent');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        };

        document.getElementById('closeLogModal').addEventListener('click', () => {
            const modal = document.getElementById('logModal');
            const content = document.getElementById('logModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 300);
        });

        // Webhook Details Logic
        window.viewWebhookDetails = (index) => {
            const log = allLogs[index];
            const wr = log.webhook_result;
            const contentEl = document.getElementById('webhookResponseContent');

            if (!wr) {
                contentEl.innerText = 'No webhook data available.';
            } else {
                const statusCode = wr.status_code !== undefined ? wr.status_code : wr.webhook_status;
                const responseBody = wr.response_body !== undefined ? wr.response_body : wr.webhook_response;
                const errorMsg = wr.error !== undefined ? wr.error : null;

                let displayObj = {
                    status_code: statusCode,
                    success: wr.success,
                    error: errorMsg,
                    execution_time_ms: wr.execution_time_ms || null,
                    response_body: responseBody
                };

                // Try format JSON if possible
                try {
                    if (responseBody) {
                        const parsed = JSON.parse(responseBody);
                        displayObj.response_body = parsed;
                    }
                } catch (e) {
                    // String output remains if parse fails
                }

                contentEl.innerText = JSON.stringify(displayObj, null, 4);
            }

            const modal = document.getElementById('webhookModal');
            const content = document.getElementById('webhookModalContent');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        };

        const closeWhModal = () => {
            const modal = document.getElementById('webhookModal');
            const content = document.getElementById('webhookModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 300);
        };

        document.getElementById('closeWebhookModal').addEventListener('click', closeWhModal);
        document.getElementById('closeWebhookModalBtn').addEventListener('click', closeWhModal);

        fetchLogs();
    });
</script>

<?php include 'includes/footer.php'; ?>