document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('projectModal');
    const modalContent = document.getElementById('projectModalContent');
    const btnNew = document.getElementById('btnNewProject');
    const btnCancel = document.getElementById('btnCancel');
    const btnClose = document.getElementById('closeModal');
    const form = document.getElementById('projectForm');
    const tbody = document.getElementById('projectsTableBody');
    const title = document.getElementById('modalTitle');
    const serverSelect = document.getElementById('projectServerId');

    let projects = [];
    let servers = [];

    const loadData = async () => {
        try {
            const [pRes, sRes] = await Promise.all([
                fetch('api/projects.php'),
                fetch('api/servers.php')
            ]);
            projects = await pRes.json();
            servers = await sRes.json();

            // Populate server dropdown
            serverSelect.innerHTML = '<option value="">-- Select a Server --</option>';
            servers.forEach(s => {
                serverSelect.innerHTML += `<option value="${s.id}">${s.name} (${s.host})</option>`;
            });

            renderTable();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-red-400">Error loading data. Check console.</td></tr>';
            console.error(e);
        }
    };

    const renderTable = () => {
        tbody.innerHTML = '';
        if (projects.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-500">No projects configured. Click "Add Project".</td></tr>';
            return;
        }

        projects.forEach(p => {
            const serverName = p.server_id ? (servers.find(s => s.id === p.server_id)?.name || 'Unknown') : '<span class="text-red-400">Not Mapped</span>';
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-dark-bg transition-colors group';
            tr.innerHTML = `
                <td class="px-6 py-4 font-semibold text-white whitespace-nowrap"><i class="ph ph-folder text-primary mr-2"></i> ${p.name}</td>
                <td class="px-6 py-4 font-mono text-xs text-gray-300 truncate max-w-xs" title="${p.local_path}">${p.local_path}</td>
                <td class="px-6 py-4 text-xs font-medium"><span class="bg-gray-800 border border-gray-600 px-2 py-1 rounded">${serverName}</span></td>
                <td class="px-6 py-4 text-right">
                    <button onclick="editProject('${p.id}')" class="text-blue-400 hover:text-blue-300 mx-1 transition-colors"><i class="ph ph-pencil-simple text-lg"></i></button>
                    <button onclick="deleteProject('${p.id}')" class="text-red-400 hover:text-red-300 mx-1 transition-colors"><i class="ph ph-trash text-lg"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    };

    const openModal = (isEdit = false, p = null) => {
        title.innerHTML = isEdit ? '<i class="ph ph-folder-notch-open text-primary"></i> Edit Project' : '<i class="ph ph-plus-circle text-primary"></i> Add Project';
        if (isEdit) {
            document.getElementById('projectId').value = p.id;
            document.getElementById('projectName').value = p.name;
            document.getElementById('projectLocalPath').value = p.local_path;
            document.getElementById('projectServerId').value = p.server_id || '';
            document.getElementById('projectRemotePath').value = p.remote_path || '/';
            document.getElementById('projectWebhookUrl').value = p.webhook_url || '';
            document.getElementById('projectIgnore').value = (p.ignore_list || []).join('\n');
        } else {
            document.getElementById('projectId').value = '';
            form.reset();
            document.getElementById('projectIgnore').value = ".git\nnode_modules\nvendor\n.env";
            document.getElementById('projectRemotePath').value = '/';
            document.getElementById('projectWebhookUrl').value = '';
        }

        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    };

    const closeModal = () => {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    };

    window.editProject = (id) => {
        const p = projects.find(x => x.id === id);
        if (p) openModal(true, p);
    };

    window.deleteProject = async (id) => {
        const p = projects.find(x => x.id === id);
        if (!p) return;

        const { value: confirmName } = await Swal.fire({
            title: 'Delete Project?',
            html: `Are you sure you want to delete <b>${p.name}</b>?<br>Type <strong>${p.name}</strong> to confirm.`,
            input: 'text',
            inputPlaceholder: 'Enter project name',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Delete',
            background: '#1e293b',
            color: '#f3f4f6',
            inputValidator: (value) => {
                if (!value) {
                    return 'You need to type the project name!';
                }
                if (value !== p.name) {
                    return 'Project name does not match!';
                }
            }
        });

        if (confirmName) {
            try {
                await fetch('api/projects.php', {
                    method: 'DELETE',
                    body: JSON.stringify({ id }),
                    headers: { 'Content-Type': 'application/json' }
                });
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Project deleted successfully.',
                    icon: 'success',
                    background: '#1e293b',
                    color: '#f3f4f6',
                    confirmButtonColor: '#3b82f6'
                });
                loadData();
            } catch (e) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete project.',
                    icon: 'error',
                    background: '#1e293b',
                    color: '#f3f4f6',
                    confirmButtonColor: '#3b82f6'
                });
            }
        }
    };

    btnNew.addEventListener('click', () => openModal(false));
    btnCancel.addEventListener('click', closeModal);
    btnClose.addEventListener('click', closeModal);

    document.getElementById('btnSave').addEventListener('click', async (e) => {
        e.preventDefault();
        const id = document.getElementById('projectId').value;
        const name = document.getElementById('projectName').value;
        const localPath = document.getElementById('projectLocalPath').value;
        const serverId = document.getElementById('projectServerId').value;
        const remotePath = document.getElementById('projectRemotePath').value;
        const webhookUrl = document.getElementById('projectWebhookUrl').value;
        const ignoreListRaw = document.getElementById('projectIgnore').value;

        if (!name || !localPath) {
            alert('Name and Local Path are required');
            return;
        }

        const ignore_list = ignoreListRaw.split('\n').map(l => l.trim()).filter(l => l.length > 0);

        const payload = {
            name, local_path: localPath, server_id: serverId, remote_path: remotePath, webhook_url: webhookUrl, ignore_list
        };
        if (id) payload.id = id;

        try {
            await fetch('api/projects.php', {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
                headers: { 'Content-Type': 'application/json' }
            });
            closeModal();
            loadData();
        } catch (error) {
            alert('Failed to save project');
        }
    });

    loadData();
});
