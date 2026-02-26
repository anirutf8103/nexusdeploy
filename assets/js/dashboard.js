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

    // --- Topology Visualizer ---
    const topoContainer = document.getElementById('topology-canvas');
    if (topoContainer && window.vis) {
        try {
            const topoRes = await fetch('api/get_topology.php');
            const topoData = await topoRes.json();

            document.getElementById('topology-loading')?.remove();

            const nodes = new vis.DataSet(topoData.nodes.map(n => {
                let color = n.group === 'project' ? '#3b82f6' : '#6366f1';
                let border = '#ffffff'; // Thicker white border by default

                // Status badge representation (Green/Red border)
                if (n.group === 'server') {
                    if (n.status === 'Success') border = '#10b981';
                    else if (n.status === 'Failed') border = '#ef4444';
                }

                // SVG Icons with background shapes
                let svgIcon;
                // Encode colors manually for SVG injection: %23 is the hash symbol '#'
                if (n.group === 'project') {
                    // ph-globe SVG inside a Green Circle
                    svgIcon = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 256 256">
                        <circle cx="128" cy="128" r="128" fill="#10b981"/> <!-- Green Circle -->
                        <path fill="#ffffff" d="M128,40A88,88,0,1,0,216,128,88.1,88.1,0,0,0,128,40ZM95.27,192.42A72.1,72.1,0,0,1,128,56a71.74,71.74,0,0,1,9.81.68,145.45,145.45,0,0,1,28.27,34H89.92A145.45,145.45,0,0,1,118.19,56.68a71.69,71.69,0,0,1,36.54,135.74A145.45,145.45,0,0,1,126.46,158.3A145.45,145.45,0,0,1,95.27,192.42ZM56.33,112h31a153.29,153.29,0,0,0-1.28,32H56.33A72.3,72.3,0,0,1,56.33,112ZM73.7,160H88a129.5,129.5,0,0,0,23.11,39A72.18,72.18,0,0,1,73.7,160Zm14.3-64H73.7A72.18,72.18,0,0,1,111.11,57,129.5,129.5,0,0,0,88,96Zm15.21,48h50.18a135.5,135.5,0,0,0,.91-32H102.4A135.5,135.5,0,0,0,103.21,144Zm24.79,31.7A129.5,129.5,0,0,0,150.84,160H105.16A129.5,129.5,0,0,0,128,175.7ZM144.89,57a72.18,72.18,0,0,1,37.41,39H168A129.5,129.5,0,0,0,144.89,57ZM168,160h14.3a72.18,72.18,0,0,1-37.41,39A129.5,129.5,0,0,0,168,160Zm18.3,10.6a88.16,88.16,0,0,0,13.37-26.6H169.93A153.29,153.29,0,0,1,171.21,112h28.47a88.16,88.16,0,0,0-13.37-26.6Z"/>
                    </svg>
                    `.trim().replace(/\n/g, '').replace(/\s+/g, ' ');
                } else {
                    // ph-hard-drives SVG inside a Blue Square with rounded corners
                    svgIcon = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 256 256">
                        <rect width="256" height="256" rx="48" fill="#3b82f6"/> <!-- Blue Rounded Square -->
                        <path fill="#ffffff" d="M192,56H64A16,16,0,0,0,48,72v32a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V72A16,16,0,0,0,192,56Zm0,48H64V72H192v32ZM192,136H64a16,16,0,0,0-16,16v32a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V152A16,16,0,0,0,192,136Zm0,48H64V152H192v32ZM88,88a8,8,0,1,1,8,8A8,8,0,0,1,88,88Zm0,80a8,8,0,1,1,8,8A8,8,0,0,1,88,168Z"/>
                    </svg>
                    `.trim().replace(/\n/g, '').replace(/\s+/g, ' ');
                }

                let url = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svgIcon);

                return {
                    id: n.id,
                    label: n.label, // Remove bold emoji from label
                    group: n.group,
                    shape: 'image',
                    image: url,
                    margin: { top: 12, bottom: 12, left: 16, right: 16 },
                    color: {
                        background: '#1e293b',
                        border: border,
                        highlight: {
                            background: '#334155',
                            border: border
                        },
                        hover: {
                            background: '#334155',
                            border: border
                        }
                    },
                    font: {
                        color: '#f8fafc',
                        face: 'Inter, sans-serif',
                        size: 13,
                        multi: 'html'
                    },
                    borderWidth: 2,
                    borderWidthSelected: 4,
                    shadow: {
                        enabled: true,
                        color: 'rgba(0,0,0,0.6)',
                        size: 8,
                        x: 0,
                        y: 4
                    }
                };
            }));

            const edges = new vis.DataSet(topoData.edges.map(e => {
                let label = e.has_webhook ? '⚡ Webhook' : '';
                return {
                    from: e.from,
                    to: e.to,
                    arrows: 'to',
                    color: {
                        color: '#475569',
                        highlight: '#3b82f6',
                        hover: '#3b82f6'
                    },
                    smooth: {
                        type: 'cubicBezier', // Let visjs figure out best curve automatically without hierarchy limitations
                        forceDirection: 'none',
                        roundness: 0.5
                    },
                    label: label,
                    font: {
                        color: '#ffffff',
                        size: 11,
                        background: 'none',
                        strokeWidth: 3,
                        strokeColor: '#1e293b' // Dark background stroke for high visibility
                    }
                };
            }));

            const data = { nodes, edges };
            const options = {
                physics: {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -150, // Push harder
                        centralGravity: 0.005, // Looser center focus
                        springConstant: 0.05,
                        springLength: 250, // Longer edges
                        damping: 0.4,
                        avoidOverlap: 1 // Crucial for no overlapping
                    }
                },
                layout: {
                    randomSeed: 8 // Give a new seed to try spreading better
                },
                interaction: {
                    hover: true,
                    tooltipDelay: 200,
                    zoomView: true,
                    dragView: true
                }
            };

            const network = new vis.Network(topoContainer, data, options);

            // Interactive Highlighting on Hover
            network.on("hoverNode", function (params) {
                network.canvas.body.container.style.cursor = 'pointer';
                const selectedNode = params.node;
                const connectedNodesNum = network.getConnectedNodes(selectedNode);

                // Blur other nodes
                let allNodes = nodes.get({ returnType: 'Object' });
                let updateArray = [];
                for (let nodeId in allNodes) {
                    if (nodeId == selectedNode || connectedNodesNum.includes(nodeId)) {
                        updateArray.push({ id: nodeId, hidden: false, opacity: 1 });
                    } else {
                        updateArray.push({ id: nodeId, opacity: 0.2 });
                    }
                }

                // Change colors due to opacity override bug in some vis versions
                nodes.update(updateArray.map(n => {
                    let ogNode = allNodes[n.id];
                    if (n.opacity === 0.2) {
                        return { id: n.id, color: { background: 'rgba(30,41,59,0.2)', border: 'rgba(255,255,255,0.2)' }, font: { color: 'rgba(248,250,252,0.2)' } };
                    } else {
                        // Restore colors
                        let border = '#ffffff';
                        if (ogNode.group === 'server') {
                            if (ogNode.status === 'Success') border = '#10b981';
                            else if (ogNode.status === 'Failed') border = '#ef4444';
                        }
                        return { id: n.id, color: { background: '#1e293b', border: border }, font: { color: '#f8fafc' } };
                    }
                }));

                // Edges fade
                let allEdges = edges.get({ returnType: 'Object' });
                let connectedEdges = network.getConnectedEdges(selectedNode);
                let updateEdges = [];
                for (let edgeId in allEdges) {
                    if (connectedEdges.includes(edgeId)) {
                        updateEdges.push({ id: edgeId, color: { color: '#3b82f6' } });
                    } else {
                        updateEdges.push({ id: edgeId, color: { color: 'rgba(71,85,105,0.2)' } });
                    }
                }
                edges.update(updateEdges);
            });

            network.on("blurNode", function (params) {
                network.canvas.body.container.style.cursor = 'default';

                // Restore all nodes and edges
                let allNodes = nodes.get({ returnType: 'Object' });
                let updateArray = [];
                for (let nodeId in allNodes) {
                    let ogNode = allNodes[nodeId];
                    let border = '#ffffff';
                    if (ogNode.group === 'server') {
                        if (ogNode.status === 'Success') border = '#10b981';
                        else if (ogNode.status === 'Failed') border = '#ef4444';
                    }
                    updateArray.push({ id: nodeId, color: { background: '#1e293b', border: border }, font: { color: '#f8fafc' } });
                }
                nodes.update(updateArray);

                let allEdges = edges.get({ returnType: 'Object' });
                let updateEdges = [];
                for (let edgeId in allEdges) {
                    updateEdges.push({ id: edgeId, color: { color: '#475569' } });
                }
                edges.update(updateEdges);
            });

        } catch (e) {
            console.error('Topology generation failed:', e);
            document.getElementById('topology-loading').innerHTML = '<p class="text-red-400">Failed to load topology map.</p>';
        }
    }
});
