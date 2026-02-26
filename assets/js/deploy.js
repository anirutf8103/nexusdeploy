document.addEventListener('DOMContentLoaded', () => {
    const projectSelect = document.getElementById('deployProject');
    const projectMeta = document.getElementById('projectMeta');
    const btnAnalyze = document.getElementById('btnAnalyze');
    const btnDeploy = document.getElementById('btnDeploy');
    const deployBadge = document.getElementById('deployStatusBadge');

    // UI Elements
    const changesTitle = document.getElementById('changesTitle');
    const changesCount = document.getElementById('changesCount');
    const changesList = document.getElementById('changesList');

    // Webhook Elements
    const webhookContainer = document.getElementById('webhookContainer');
    const webhookToggle = document.getElementById('webhookToggle');
    const webhookUrlDisplay = document.getElementById('webhookUrlDisplay');

    // Terminal UI
    const termWin = document.getElementById('terminalWindow');
    const termContent = document.getElementById('terminalContent');
    const pText = document.getElementById('progressText');
    const pPercent = document.getElementById('progressPercent');
    const pBar = document.getElementById('progressBar');
    const pShine = document.getElementById('progressShine');

    let projects = [];
    let servers = [];
    let filesToUpload = [];
    let isDeploying = false;
    let selectedProject = null;

    const printTerm = (msg, type = 'info') => {
        const div = document.createElement('div');
        const ts = new Date().toLocaleTimeString();
        let color = 'text-gray-400';
        if (type === 'success') color = 'text-green-400';
        if (type === 'error') color = 'text-red-400 text-bold';
        if (type === 'warn') color = 'text-yellow-400';
        if (type === 'cyan') color = 'text-cyan-400';

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

            projectSelect.innerHTML = '<option value="">-- Select Project --</option>';
            projects.forEach(p => {
                projectSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
            });
        } catch (e) {
            printTerm('FATAL ERROR: Could not load projects or servers from local database.', 'error');
        }
    };

    projectSelect.addEventListener('change', (e) => {
        const id = e.target.value;
        if (!id) {
            projectMeta.classList.add('hidden');
            btnAnalyze.disabled = true;
            btnDeploy.disabled = true;
            selectedProject = null;
            return;
        }

        selectedProject = projects.find(p => p.id === id);
        const srv = servers.find(s => s.id === selectedProject.server_id);

        document.getElementById('metaLocalPath').innerText = selectedProject.local_path;
        document.getElementById('metaRemotePath').innerText = selectedProject.remote_path || '/';
        document.getElementById('metaServer').innerText = srv ? `${srv.name} (${srv.host})` : 'UNMAPPED';

        projectMeta.classList.remove('hidden');
        btnAnalyze.disabled = false;

        if (selectedProject.webhook_url) {
            webhookUrlDisplay.innerText = selectedProject.webhook_url;
            webhookContainer.classList.remove('hidden');
            webhookToggle.checked = true;
        } else {
            webhookContainer.classList.add('hidden');
            webhookToggle.checked = false;
        }

        if (!srv) {
            printTerm('WARNING: Selected project has no server mapped. Deployment will fail.', 'warn');
            btnAnalyze.disabled = true;
        } else {
            printTerm(`Project mapped: ${selectedProject.name} -> ${srv.host}`);
        }

        filesToUpload = [];
        changesList.innerHTML = '<tr><td class="text-center py-10 text-gray-500 font-mono text-xs">Ready to analyze structure.</td></tr>';
        changesCount.innerText = '0';
        btnDeploy.disabled = true;
    });

    btnAnalyze.addEventListener('click', async () => {
        if (!selectedProject || isDeploying) return;

        btnAnalyze.disabled = true;
        btnAnalyze.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Analyzing...';
        printTerm(`Starting dry run for ${selectedProject.local_path}...`, 'cyan');

        try {
            const res = await fetch(`api/deploy.php?action=dry-run&project_id=${selectedProject.id}`);
            const data = await res.json();

            if (data.error) {
                printTerm(`Scan Error: ${data.error}`, 'error');
                btnAnalyze.innerHTML = '<i class="ph ph-magnifying-glass"></i> Analyze Changes (Dry Run)';
                btnAnalyze.disabled = false;
                return;
            }

            filesToUpload = data.files;
            changesCount.innerText = data.total;
            changesList.innerHTML = '';

            if (filesToUpload.length === 0) {
                printTerm('Dry run complete. No changes detected. System is up-to-date.', 'success');
                changesList.innerHTML = '<tr><td class="text-center py-10 text-gray-500 font-mono text-xs"><i class="ph ph-check-circle text-green-500 block text-2xl mb-2"></i> All files up to date</td></tr>';
                btnDeploy.disabled = true;
            } else {
                printTerm(`Dry run complete. ${filesToUpload.length} files modified or new.`, 'warn');
                filesToUpload.forEach(f => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-dark-bg group transition-colors border-l-2 border-transparent hover:border-accent';
                    tr.innerHTML = `
                        <td class="px-4 py-3 font-mono text-xs truncate w-full flex items-center gap-2">
                            <i class="ph ph-file text-gray-400 group-hover:text-accent"></i> 
                            <span class="text-gray-300 break-all" title="${f.path}">${f.path}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-xs text-gray-500 whitespace-nowrap">${(f.size / 1024).toFixed(1)} KB</td>
                    `;
                    changesList.appendChild(tr);
                });
                btnDeploy.disabled = false;
            }

        } catch (error) {
            printTerm(`Network/Engine Error during analysis`, 'error');
        }

        btnAnalyze.innerHTML = '<i class="ph ph-magnifying-glass"></i> Re-Analyze Changes';
        btnAnalyze.disabled = false;
    });

    btnDeploy.addEventListener('click', async () => {
        if (!selectedProject || filesToUpload.length === 0 || isDeploying) return;

        isDeploying = true;
        btnDeploy.disabled = true;
        btnAnalyze.disabled = true;
        projectSelect.disabled = true;

        btnDeploy.innerHTML = '<i class="ph ph-spinner-gap animate-spin text-xl"></i> DEPLOYING...';
        deployBadge.className = 'bg-blue-500/20 text-blue-400 text-xs font-medium px-2.5 py-0.5 rounded border border-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.3)] animate-pulse';
        deployBadge.innerText = 'UPLOADING';
        pShine.classList.remove('hidden');

        printTerm(`Starting deployment sequence. Target files: ${filesToUpload.length}`, 'cyan');
        const startTime = Date.now();
        let successCount = 0;
        let failCount = 0;

        const maxConcurrent = 10;
        for (let i = 0; i < filesToUpload.length; i += maxConcurrent) {
            const batch = filesToUpload.slice(i, i + maxConcurrent);

            // update UI progress
            const percent = Math.floor((i / filesToUpload.length) * 100);
            pBar.style.width = `${percent}%`;
            pPercent.innerText = `${percent}%`;
            pText.innerText = `${i} / ${filesToUpload.length} Files Processing...`;

            printTerm(`Initiating concurrent batch (${batch.length} files)...`, 'info');

            try {
                const res = await fetch(`api/deploy.php?action=upload_batch`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        project_id: selectedProject.id,
                        files: batch,
                        trigger_webhook: (webhookContainer && !webhookContainer.classList.contains('hidden')) ? webhookToggle.checked : false,
                        is_last_batch: (i + maxConcurrent >= filesToUpload.length)
                    })
                });

                const data = await res.json();

                if (data.error) {
                    failCount += batch.length;
                    printTerm(`BATCH ERROR: ${data.error}`, 'error');
                } else {
                    successCount += data.success || 0;
                    failCount += data.failed || 0;

                    if (data.success_files) {
                        data.success_files.forEach(f => printTerm(`UPLOADED: ${f}`));
                    }
                    if (data.failed_files) {
                        data.failed_files.forEach(f => printTerm(`FAILED: ${f.path} - ${f.error}`, 'error'));
                    }
                    if (data.webhook_result) {
                        window.lastWebhookResult = data.webhook_result; // Store for logs
                        if (data.webhook_result.success) {
                            printTerm(`WEBHOOK: Triggered successfully (${data.webhook_result.status_code})`, 'success');
                        } else {
                            printTerm(`WEBHOOK ERROR: ${data.webhook_result.error || 'Failed'} (${data.webhook_result.status_code})`, 'error');
                        }
                    }
                }
            } catch (e) {
                failCount += batch.length;
                printTerm(`FATAL: Batch upload connection crash`, 'error');
            }
        }

        const timeTaken = Math.round((Date.now() - startTime) / 1000);

        // Final UI updates
        pBar.style.width = `100%`;
        pPercent.innerText = `100%`;
        pText.innerText = `${filesToUpload.length} / ${filesToUpload.length} Files Processed`;
        pShine.classList.add('hidden');

        const isFullSuccess = failCount === 0;
        printTerm(`Deployment sequence finished in ${timeTaken}s.`, isFullSuccess ? 'success' : 'warn');
        printTerm(`Summary: ${successCount} Success, ${failCount} Failed.`);

        deployBadge.className = isFullSuccess
            ? 'bg-green-500/20 text-green-400 text-xs font-medium px-2.5 py-0.5 rounded border border-green-500'
            : 'bg-red-500/20 text-red-400 text-xs font-medium px-2.5 py-0.5 rounded border border-red-500';
        deployBadge.innerText = isFullSuccess ? 'COMPLETED' : 'HAS ERRORS';

        // Write to logs.json
        try {
            await fetch('api/logs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    project_id: selectedProject.id,
                    server_id: selectedProject.server_id,
                    files_uploaded: filesToUpload.map(f => f.path),
                    time_taken: timeTaken,
                    status: isFullSuccess ? 'Success' : 'Failed',
                    webhook_result: window.lastWebhookResult || null
                })
            });
            window.lastWebhookResult = null; // Clear it
            printTerm('Deployment log saved.');
        } catch (e) {
            printTerm('Failed to save log to local database.', 'warn');
        }

        filesToUpload = [];
        changesCount.innerText = '0';

        btnDeploy.innerHTML = '<i class="ph ph-check-circle text-xl"></i> DEPLOYMENT DONE';
        setTimeout(() => {
            btnDeploy.innerHTML = '<i class="ph ph-rocket-launch text-xl"></i> START DEPLOYMENT';
            isDeploying = false;
            projectSelect.disabled = false;
            btnAnalyze.disabled = false;
            btnDeploy.disabled = true; // wait for re-analysis
        }, 5000);
    });

    loadData();
});
