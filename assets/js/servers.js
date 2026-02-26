document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('serversTableBody');
    const modal = document.getElementById('serverModal');
    const modalContent = document.getElementById('serverModalContent');
    const btnNew = document.getElementById('btnNewServer');
    const btnCancel = document.getElementById('btnCancel');
    const btnClose = document.getElementById('closeModal');
    const title = document.getElementById('modalTitle');
    const pForm = document.getElementById('serverForm');
    const btnTest = document.getElementById('btnTestConn');
    const testResult = document.getElementById('testResult');

    let servers = [];

    const loadData = async () => {
        try {
            const res = await fetch('api/servers.php');
            servers = await res.json();
            renderTable();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">Error loading data.</td></tr>';
        }
    };

    const renderTable = () => {
        tbody.innerHTML = '';
        if (servers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">No servers configured.</td></tr>';
            return;
        }

        servers.forEach(s => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-dark-bg transition-colors';
            tr.innerHTML = `
                <td class="px-6 py-4 font-semibold text-white whitespace-nowrap"><i class="ph ph-hard-drives text-accent mr-2"></i> ${s.name}</td>
                <td class="px-6 py-4 font-mono text-xs text-gray-300">${s.host}</td>
                <td class="px-6 py-4 font-mono text-xs text-gray-400">${s.port}</td>
                <td class="px-6 py-4 text-xs font-medium text-gray-300">${s.username}</td>
                <td class="px-6 py-4 text-right">
                    <button onclick="editServer('${s.id}')" class="text-blue-400 hover:text-blue-300 mx-1"><i class="ph ph-pencil-simple text-lg"></i></button>
                    <button onclick="deleteServer('${s.id}')" class="text-red-400 hover:text-red-300 mx-1"><i class="ph ph-trash text-lg"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    };

    const openModal = (isEdit = false, s = null) => {
        title.innerHTML = isEdit ? '<i class="ph ph-hard-drives text-accent"></i> Edit Server' : '<i class="ph ph-plus-circle text-accent"></i> Add Server';
        testResult.classList.add('hidden');
        if (isEdit) {
            document.getElementById('serverId').value = s.id;
            document.getElementById('serverName').value = s.name;
            document.getElementById('serverHost').value = s.host;
            document.getElementById('serverPort').value = s.port || 21;
            document.getElementById('serverUsername').value = s.username;
            document.getElementById('serverPassword').value = s.password || '';
        } else {
            pForm.reset();
            document.getElementById('serverId').value = '';
            document.getElementById('serverPort').value = 21;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
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
            modal.classList.remove('flex');
        }, 300);
    };

    window.editServer = (id) => {
        const s = servers.find(x => x.id === id);
        if (s) openModal(true, s);
    };

    window.deleteServer = async (id) => {
        const s = servers.find(x => x.id === id);
        if (!s) return;

        const { value: confirmName } = await Swal.fire({
            title: 'Delete Server?',
            html: `Are you sure you want to delete <b>${s.name}</b>?<br>Type <strong>${s.name}</strong> to confirm.`,
            input: 'text',
            inputPlaceholder: 'Enter server name',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Delete',
            background: '#1e293b',
            color: '#f3f4f6',
            inputValidator: (value) => {
                if (!value) {
                    return 'You need to type the server name!';
                }
                if (value !== s.name) {
                    return 'Server name does not match!';
                }
            }
        });

        if (confirmName) {
            try {
                await fetch('api/servers.php', {
                    method: 'DELETE',
                    body: JSON.stringify({ id }),
                    headers: { 'Content-Type': 'application/json' }
                });
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Server deleted successfully.',
                    icon: 'success',
                    background: '#1e293b',
                    color: '#f3f4f6',
                    confirmButtonColor: '#3b82f6'
                });
                loadData();
            } catch (e) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete server.',
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
        const id = document.getElementById('serverId').value;
        const name = document.getElementById('serverName').value;
        const host = document.getElementById('serverHost').value;
        const port = document.getElementById('serverPort').value;
        const username = document.getElementById('serverUsername').value;
        const password = document.getElementById('serverPassword').value;

        if (!name || !host || !username) {
            alert('Name, Host and Username are required');
            return;
        }

        const payload = { name, host, port, username, password };
        if (id) payload.id = id;

        try {
            await fetch('api/servers.php', {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
                headers: { 'Content-Type': 'application/json' }
            });
            closeModal();
            loadData();
        } catch (error) {
            alert('Failed to save server');
        }
    });

    btnTest.addEventListener('click', async () => {
        const host = document.getElementById('serverHost').value;
        const port = document.getElementById('serverPort').value;
        const username = document.getElementById('serverUsername').value;
        const password = document.getElementById('serverPassword').value;

        if (!host || !username) {
            testResult.innerHTML = '<i class="ph ph-warning-circle"></i> Host and username required for testing';
            testResult.className = 'text-sm font-medium px-3 py-1 rounded bg-red-500/20 text-red-400 border border-red-500 flex items-center gap-2';
            testResult.classList.remove('hidden');
            return;
        }

        btnTest.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Testing...';
        btnTest.disabled = true;

        try {
            const res = await fetch('api/test_connection.php', {
                method: 'POST',
                body: JSON.stringify({ host, port, username, password }),
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await res.json();

            if (data.success) {
                testResult.innerHTML = `<i class="ph ph-check-circle"></i> ${data.message}`;
                testResult.className = 'text-sm font-medium px-3 py-1 rounded bg-green-500/20 text-green-400 border border-green-500 flex items-center gap-2';
            } else {
                testResult.innerHTML = `<i class="ph ph-x-circle"></i> ${data.message || data.error}`;
                testResult.className = 'text-sm font-medium px-3 py-1 rounded bg-red-500/20 text-red-400 border border-red-500 flex items-center gap-2';
            }
        } catch (error) {
            testResult.innerHTML = `<i class="ph ph-warning-circle"></i> Cannot connect to localhost API`;
            testResult.className = 'text-sm font-medium px-3 py-1 rounded bg-red-500/20 text-red-400 border border-red-500 flex items-center gap-2';
        }
        testResult.classList.remove('hidden');
        btnTest.innerHTML = '<i class="ph ph-plugs"></i> Test Connection';
        btnTest.disabled = false;
    });

    loadData();
});
