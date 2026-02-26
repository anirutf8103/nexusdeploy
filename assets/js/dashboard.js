document.addEventListener('DOMContentLoaded', async () => {
    const fetchStat = async (url) => {
        try {
            const res = await fetch(url);
            return await res.json();
        } catch {
            return [];
        }
    };

    const projects = await fetchStat('api/projects.php');
    const servers = await fetchStat('api/servers.php');
    const logs = await fetchStat('api/logs.php');

    // Widgets Support
    document.getElementById('stat-projects').innerText = projects.length || 0;
    document.getElementById('stat-servers').innerText = servers.length || 0;

    let totalFiles = 0;
    let totalTime = 0;
    let successCount = 0;
    let failedCount = 0;

    const last7Days = {};
    const last7DaysFiles = {};
    for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        const isoDate = d.toISOString().split('T')[0];
        last7Days[isoDate] = 0;
        last7DaysFiles[isoDate] = 0;
    }

    logs.forEach(l => {
        const numFiles = l.files_uploaded ? l.files_uploaded.length : 0;
        totalFiles += numFiles;
        totalTime += parseInt(l.time_taken) || 0;
        if (l.status === 'Success') successCount++;
        else failedCount++;

        const dStr = new Date(l.created_at).toISOString().split('T')[0];
        if (last7Days[dStr] !== undefined) {
            last7Days[dStr]++;
            last7DaysFiles[dStr] += numFiles;
        }
    });

    document.getElementById('stat-files').innerText = totalFiles;
    document.getElementById('stat-time').innerText = logs.length > 0 ? Math.round(totalTime / logs.length) + 's' : '0s';

    // Chart.js Default Config for Dark Theme
    Chart.defaults.color = '#9ca3af';
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.scale.grid.color = '#334155';

    // Deploy Bar Chart
    const ctxBar = document.getElementById('deployBarChart');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: Object.keys(last7Days).map(date => {
                    const d = new Date(date);
                    return `${d.getMonth() + 1}/${d.getDate()}`;
                }),
                datasets: [{
                    label: 'Deployments',
                    data: Object.values(last7Days),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // Status Doughnut Chart
    const ctxDoughnut = document.getElementById('statusDoughnutChart');
    if (ctxDoughnut && (successCount > 0 || failedCount > 0)) {
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Failed'],
                datasets: [{
                    data: [successCount, failedCount],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderColor: '#1e293b',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    } else if (ctxDoughnut) {
        ctxDoughnut.parentElement.innerHTML = '<div class="text-gray-500 font-mono text-sm">No deployment data available for chart.</div>';
    }

    // Data Volume Line Chart
    const ctxDataVolume = document.getElementById('dataVolumeChart');
    if (ctxDataVolume) {
        new Chart(ctxDataVolume, {
            type: 'line',
            data: {
                labels: Object.keys(last7DaysFiles).map(date => {
                    const d = new Date(date);
                    return `${d.getMonth() + 1}/${d.getDate()}`;
                }),
                datasets: [{
                    label: 'Files Processed',
                    data: Object.values(last7DaysFiles),
                    backgroundColor: 'rgba(168, 85, 247, 0.2)',
                    borderColor: '#a855f7',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#a855f7',
                    pointBorderColor: '#1e293b',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }

    const tbody = document.getElementById('recent-logs-body');
    const loading = document.getElementById('logs-loading');

    if (tbody && loading) {
        loading.classList.add('hidden');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-gray-500">No activity yet.</td></tr>';
        } else {
            // Sort to get newest first since backend just appends
            logs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            logs.slice(0, 5).forEach(log => {
                const date = new Date(log.created_at).toLocaleString();
                const badge = log.status === 'Success'
                    ? '<span class="bg-green-500/20 text-green-400 border border-green-500 text-xs px-2 py-1 rounded shadow-[0_0_10px_rgba(34,197,94,0.1)]">Success</span>'
                    : '<span class="bg-red-500/20 text-red-400 border border-red-500 text-xs px-2 py-1 rounded">Failed</span>';

                const prj = projects.find(p => p.id === log.project_id);
                const prjName = prj ? prj.name : log.project_id;

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-dark-bg transition-colors border-b border-dark-border/50 group';
                tr.innerHTML = `
                    <td class="px-6 py-3 whitespace-nowrap"><span class="flex items-center gap-1.5"><i class="ph ph-clock text-gray-500"></i> ${date}</span></td>
                    <td class="px-6 py-3 font-mono text-xs text-gray-400 truncate max-w-[200px] group-hover:text-primary transition-colors">${prjName}</td>
                    <td class="px-6 py-3 font-mono text-xs"><span class="bg-gray-800 text-gray-300 px-2 py-0.5 rounded border border-gray-700">${log.files_uploaded ? log.files_uploaded.length : 0}</span></td>
                    <td class="px-6 py-3 text-left">${badge}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    }
});
