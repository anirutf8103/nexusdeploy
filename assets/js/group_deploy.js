document.addEventListener('DOMContentLoaded', () => {
    const groupsList = document.getElementById('groupsList');
    const changesCount = document.getElementById('changesCount');
    const changesList = document.getElementById('changesList');

    // Terminal UI
    const termWin = document.getElementById('terminalWindow');
    const termContent = document.getElementById('terminalContent');
    const pText = document.getElementById('progressText');
    const pPercent = document.getElementById('progressPercent');
    const pBar = document.getElementById('progressBar');
    const pShine = document.getElementById('progressShine');

    // Webhook Elements
    const webhookContainer = document.getElementById('webhookContainer');
    const webhookToggle = document.getElementById('webhookToggle');

    let projects = [];
    let servers = [];
    let groupedData = {};
    let isDeploying = false;
    let masterQueue = []; // [{ project, file, server }, ...]

    const printTerm = (msg, type = 'info') => {
        const div = document.createElement('div');
        const ts = new Date().toLocaleTimeString();
        let color = 'text-gray-400';
        if (type === 'success') color = 'text-green-400';
        if (type === 'error') color = 'text-red-400 font-bold';
        if (type === 'warn') color = 'text-yellow-400';
        if (type === 'cyan') color = 'text-cyan-400';
        if (type === 'purple') color = 'text-purple-400';

        div.innerHTML = `<span class="text-gray-600">[${ts}]</span> <span class="${color}">${msg}</span>`;
        termContent.appendChild(div);
        termWin.scrollTop = termWin.scrollHeight;
    };

    const loadData = async () => {
        try {
            const [pRes, sRes] = await Promise.all([
                fetch('api/projects.php'),
                fetch('api/servers.php')
            ]);
            projects = await pRes.json();
            servers = await sRes.json();

            // Group by local_path
            groupedData = {};
            projects.forEach(p => {
                if (!p.local_path) return;
                if (!groupedData[p.local_path]) {
                    groupedData[p.local_path] = [];
                }
                groupedData[p.local_path].push(p);
            });

            renderGroups();
        } catch (e) {
            groupsList.innerHTML = '<div class="text-red-400 text-center py-4">FATAL ERROR: Could not load data.</div>';
        }
    };

    const renderGroups = () => {
        groupsList.innerHTML = '';
        const paths = Object.keys(groupedData);
        if (paths.length === 0) {
            groupsList.innerHTML = '<div class="text-gray-500 text-center py-4">No projects with defined local paths.</div>';
            return;
        }

        paths.forEach((path, index) => {
            const groupProjects = groupedData[path];
            const div = document.createElement('div');
            div.className = 'bg-gray-900/50 border border-dark-border rounded-lg p-4 transition-all hover:border-dark-border/80';

            let html = `
                <div class="flex justify-between items-start mb-3">
                    <div class="overflow-hidden">
                        <h4 class="text-sm font-semibold text-white mb-1 flex items-center gap-2"><i class="ph ph-folder text-purple-400 font-bold"></i> Group Path</h4>
                        <div class="text-xs text-gray-400 font-mono truncate" title="${path}">${path}</div>
                    </div>
                    <span class="bg-purple-500/20 text-purple-400 border border-purple-500/30 text-[10px] px-2 py-0.5 rounded font-bold whitespace-nowrap">${groupProjects.length} Projects</span>
                </div>
                <div class="space-y-2 mb-4">
            `;

            groupProjects.forEach(p => {
                const srv = servers.find(s => s.id === p.server_id);
                const srvName = srv ? `${srv.name} (${srv.host})` : '<span class="text-red-500">Unmapped</span>';
                html += `
                    <div class="text-xs flex flex-col bg-dark-bg p-2 rounded border border-gray-800">
                        <span class="text-gray-300 font-semibold truncate"><i class="ph ph-target text-gray-500"></i> ${p.name}</span>
                        <span class="text-gray-500 font-mono mt-0.5 pl-4 truncate">&rarr; ${srvName}</span>
                    </div>
                `;
            });

            html += `
                </div>
                <div class="flex gap-2.5">
                    <button class="btn-analyze flex-1 bg-dark-bg hover:bg-gray-800 text-gray-300 border border-dark-border py-2 px-3 rounded text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors" data-path="${btoa(path)}">
                        <i class="ph ph-magnifying-glass"></i> Analyze
                    </button>
                    <button class="btn-deploy flex-1 bg-purple-600 hover:bg-purple-700 text-white shadow-[0_0_15px_rgba(168,85,247,0.3)] disabled:opacity-50 disabled:cursor-not-allowed py-2 px-3 rounded text-xs font-semibold flex items-center justify-center gap-1.5 transition-all" data-path="${btoa(path)}" disabled>
                        <i class="ph ph-rocket-launch"></i> Deploy Group
                    </button>
                </div>
            `;
            div.innerHTML = html;
            groupsList.appendChild(div);
        });

        // Bind events
        document.querySelectorAll('.btn-analyze').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (isDeploying) return;
                const pathStr = atob(e.currentTarget.getAttribute('data-path'));
                await handleAnalyze(pathStr, e.currentTarget);
            });
        });

        document.querySelectorAll('.btn-deploy').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (isDeploying) return;
                const pathStr = atob(e.currentTarget.getAttribute('data-path'));
                await handleDeploy(pathStr, e.currentTarget);
            });
        });
    };

    const handleAnalyze = async (localPath, btnEl) => {
        const groupProjects = groupedData[localPath];
        const deployBtn = btnEl.nextElementSibling;

        btnEl.disabled = true;
        deployBtn.disabled = true;
        btnEl.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Analyzing...';
        printTerm(`Starting group dry run for path: ${localPath}...`, 'cyan');

        try {
            // Encode the path to send to API
            const b64Path = btoa(localPath);
            const res = await fetch(`api/group_deploy.php?action=dry-run&local_path=${encodeURIComponent(b64Path)}`);
            const data = await res.json();

            if (data.error) {
                printTerm(`Scan Error: ${data.error}`, 'error');
                btnEl.innerHTML = '<i class="ph ph-magnifying-glass"></i> Re-Analyze';
                btnEl.disabled = false;
                return;
            }

            masterQueue = data.master_queue || []; // [{ project_id, file: {path, size, ...}, server_id }]
            changesCount.innerText = masterQueue.length;
            changesList.innerHTML = '';

            if (masterQueue.length === 0) {
                printTerm('Dry run complete. No changes detected across the entire group.', 'success');
                changesList.innerHTML = '<tr><td class="text-center py-10 text-gray-500 font-mono text-xs"><i class="ph ph-check-circle text-green-500 block text-2xl mb-2"></i> All servers are up to date with this path</td></tr>';
                deployBtn.disabled = true;
                if (webhookContainer) webhookContainer.classList.add('hidden');
            } else {
                printTerm(`Group dry run complete. Master queue holds ${masterQueue.length} files to upload.`, 'warn');

                // Check for Webhook setup
                const hasWebhook = masterQueue.some(item => {
                    const p = projects.find(proj => proj.id === item.project_id);
                    return p && p.webhook_url;
                });
                if (webhookContainer) {
                    if (hasWebhook) {
                        webhookContainer.classList.remove('hidden');
                        webhookToggle.checked = true;
                    } else {
                        webhookContainer.classList.add('hidden');
                    }
                }

                // Group UI by project for Changes List
                let currentProj = null;
                masterQueue.forEach(item => {
                    if (currentProj !== item.project_id) {
                        currentProj = item.project_id;
                        const pName = projects.find(p => p.id === currentProj)?.name || currentProj;
                        const trHeader = document.createElement('tr');
                        trHeader.innerHTML = `<td colspan="2" class="px-3 py-1 bg-gray-900/60 font-semibold text-xs text-purple-400 border-y border-dark-border mt-2">${pName}</td>`;
                        changesList.appendChild(trHeader);
                    }

                    const f = item.file;
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-dark-bg group transition-colors';
                    tr.innerHTML = `
                        <td class="px-4 py-2 font-mono text-xs truncate w-full flex items-center gap-2">
                            <i class="ph ph-file text-gray-400 group-hover:text-purple-400"></i> 
                            <span class="text-gray-300 break-all" title="${f.path}">${f.path}</span>
                        </td>
                        <td class="px-4 py-2 text-right font-mono text-[10px] text-gray-500 whitespace-nowrap">${(f.size / 1024).toFixed(1)} KB</td>
                    `;
                    changesList.appendChild(tr);
                });

                deployBtn.disabled = false;
            }

        } catch (error) {
            printTerm(`Network/Engine Error during group analysis`, 'error');
        }

        btnEl.innerHTML = '<i class="ph ph-magnifying-glass"></i> Re-Analyze';
        btnEl.disabled = false;
    };

    const handleDeploy = async (localPath, btnEl) => {
        if (masterQueue.length === 0 || isDeploying) return;

        isDeploying = true;

        // Disable all buttons in UI
        document.querySelectorAll('button').forEach(b => b.disabled = true);

        btnEl.innerHTML = '<i class="ph ph-spinner-gap animate-spin text-xl"></i> DEPLOYING...';
        pShine.classList.remove('hidden');

        printTerm(`Starting GROUP deployment sequence. Master queue length: ${masterQueue.length}`, 'purple');
        const startTime = Date.now();

        let successCount = 0;
        let failCount = 0;

        // Results summary by server { server_id: { success: 0, fail: 0, time: 0 } }
        let serverSummary = {};

        // We cap the maximum concurrent cURL connections per batch to 15 (as requested)
        const maxConcurrent = 15;

        for (let i = 0; i < masterQueue.length; i += maxConcurrent) {
            const batch = masterQueue.slice(i, i + maxConcurrent);

            // update UI progress
            const percent = Math.floor((i / masterQueue.length) * 100);
            pBar.style.width = `${percent}%`;
            pPercent.innerText = `${percent}%`;
            pText.innerText = `${i} / ${masterQueue.length} Files Processing...`;

            printTerm(`Initiating Multi-Server concurrent batch (${batch.length} files in queue)...`, 'info');

            try {
                const res = await fetch(`api/group_deploy.php?action=upload_batch`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        local_path: localPath,
                        queue: batch
                    })
                });

                const data = await res.json();

                if (data.error) {
                    failCount += batch.length;
                    printTerm(`BATCH ERROR: ${data.error}`, 'error');
                } else {
                    successCount += data.success || 0;
                    failCount += data.failed || 0;

                    if (data.results) {
                        data.results.forEach(res => {
                            if (res.status === 'success') {
                                printTerm(`[${res.project_name}] UPLOADED: ${res.path}`);
                            } else {
                                printTerm(`[${res.project_name}] FAILED: ${res.path} - ${res.error}`, 'error');
                            }
                        });
                    }

                    // Accumulate server summary
                    if (data.server_stats) {
                        Object.keys(data.server_stats).forEach(sid => {
                            if (!serverSummary[sid]) serverSummary[sid] = { success: 0, failed: 0 };
                            serverSummary[sid].success += data.server_stats[sid].success || 0;
                            serverSummary[sid].failed += data.server_stats[sid].failed || 0;
                        });
                    }
                }
            } catch (e) {
                failCount += batch.length;
                printTerm(`FATAL: Group Batch upload connection crash`, 'error');
            }
        }

        const totalTimeTaken = Math.round((Date.now() - startTime) / 1000);

        // Final UI updates
        pBar.style.width = `100%`;
        pPercent.innerText = `100%`;
        pText.innerText = `${masterQueue.length} / ${masterQueue.length} Files Processed`;
        pShine.classList.add('hidden');

        const isFullSuccess = failCount === 0;
        printTerm(`Phase 1: FTP sequence finished in ${totalTimeTaken}s.`, isFullSuccess ? 'success' : 'warn');

        // --- PHASE 2: Webhooks --- //
        const triggerHooks = (webhookContainer && !webhookContainer.classList.contains('hidden')) ? webhookToggle.checked : false;
        let webhookResultsMap = {}; // Maps project_id to its webhook result object

        if (triggerHooks) {
            printTerm(`Phase 2: Executing Webhooks concurrently...`, 'purple');

            const hookPayload = [];
            const groupProjects = groupedData[localPath];

            groupProjects.forEach(p => {
                const pQueue = masterQueue.filter(q => q.project_id === p.id);
                if (pQueue.length > 0) {
                    const sId = p.server_id;
                    const stats = serverSummary[sId] || { failed: 0 };
                    const ftp_success = stats.failed === 0;
                    if (p.webhook_url) {
                        hookPayload.push({
                            project_id: p.id,
                            server_name: servers.find(s => s.id === sId)?.name || 'Unknown',
                            ftp_success: ftp_success,
                            webhook_url: p.webhook_url
                        });
                    }
                }
            });

            if (hookPayload.length > 0) {
                try {
                    const whRes = await fetch('api/group_deploy.php?action=run_webhooks', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ webhooks: hookPayload })
                    });
                    const whData = await whRes.json();

                    if (whData.results) {
                        webhookResultsMap = whData.results;
                        Object.values(webhookResultsMap).forEach(wr => {
                            if (wr.webhook_triggered) {
                                if (wr.success) {
                                    printTerm(`[${wr.server_name}] Webhook OK (${wr.webhook_status}) HTTP`, 'success');
                                } else {
                                    printTerm(`[${wr.server_name}] Webhook Failed (${wr.webhook_status})`, 'error');
                                }
                            } else {
                                printTerm(`[${wr.server_name}] Webhook Skipped (FTP Failed or Missing URL)`, 'warn');
                            }
                        });
                    }
                } catch (e) {
                    printTerm(`Failed to execute Phase 2 webhooks`, 'error');
                }
            } else {
                printTerm(`No valid webhooks to execute.`, 'warn');
            }
        }

        printTerm(`Global Summary: ${successCount} Success, ${failCount} Failed.`, isFullSuccess ? 'cyan' : 'yellow');
        printTerm('Writing audit logs to database...', 'info');

        const groupProjects = groupedData[localPath];

        for (const p of groupProjects) {
            const pQueue = masterQueue.filter(q => q.project_id === p.id);
            if (pQueue.length > 0) {
                const sId = p.server_id;
                const stats = serverSummary[sId] || { failed: 0 };
                const pSuccess = stats.failed === 0;

                const pWebhookResult = webhookResultsMap[p.id] || null;

                try {
                    await fetch('api/logs.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            project_id: p.id,
                            server_id: sId,
                            files_uploaded: pQueue.map(q => q.file.path),
                            time_taken: totalTimeTaken,
                            status: pSuccess ? 'Success' : 'Failed',
                            webhook_result: pWebhookResult
                        })
                    });
                } catch (e) { }
            }
        }
        printTerm('Group deployment logs saved.', 'success');

        masterQueue = [];
        changesCount.innerText = '0';

        btnEl.innerHTML = '<i class="ph ph-check-circle"></i> DONE';
        setTimeout(() => {
            btnEl.innerHTML = '<i class="ph ph-rocket-launch"></i> Deploy Group';
            isDeploying = false;
            document.querySelectorAll('button').forEach(b => b.disabled = false);
            document.querySelectorAll('.btn-deploy').forEach(b => b.disabled = true); // Need re-analysis
        }, 5000);
    };

    loadData();
});
