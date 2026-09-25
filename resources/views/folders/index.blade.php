<x-explorer-layout>
    <div class="h-screen w-screen flex flex-col bg-gray-50 overflow-hidden"
         @click="closeContextMenu()"
         @contextmenu="closeContextMenu()"
         @keydown.escape="closeContextMenu(); closeShareModal();"
         x-data="{
        viewMode: 'grid',
        createFolderModal: false,
        renameFolderModal: false,
        moveDocModal: false,
        previewModal: false,
        inspectorOpen: false,
        activeDoc: null,

        editFolderId: null,
        editFolderName: '',
        moveDocId: null,
        moveDocName: '',

        // Context Menu (Right-click) State
        contextMenu: {
            show: false,
            x: 0,
            y: 0,
            type: '',
            item: null
        },

        // Edit Display Date Modal State
        editDateModal: {
            open: false,
            id: null,
            uuid: null,
            name: '',
            currentDate: '',
            newDate: '',
            isSaving: false
        },

        // Share Modal State
        shareModal: {
            open: false,
            id: null,
            uuid: null,
            type: '',
            title: '',
            url: '',
            department: '',
            visibility: '',
            selectedBiros: [],
            isSaving: false,
            saveStatus: '',
            copied: false
        },
        permissionsCache: {
            document: {},
            folder: {}
        },
        saveTimeout: null,
        allUnits: {{ Js::from(\App\Models\User::UNITS) }},

        isDraggingFileOver: false,
        draggedDocId: null,
        hoveredFolderId: null,
        isUploading: false,
        uploadMessage: '',
        toastMessage: '',
        showToast: false,

        triggerToast(msg) {
            this.toastMessage = msg;
            this.showToast = true;
            setTimeout(() => { this.showToast = false; }, 3500);
        },

        openContextMenu(event, type, item) {
            event.preventDefault();
            event.stopPropagation();
            this.contextMenu.show = true;
            this.contextMenu.type = type;
            this.contextMenu.item = item;

            const menuWidth = 220;
            const menuHeight = 250;
            const x = event.clientX + menuWidth > window.innerWidth ? window.innerWidth - menuWidth - 10 : event.clientX;
            const y = event.clientY + menuHeight > window.innerHeight ? window.innerHeight - menuHeight - 10 : event.clientY;

            this.contextMenu.x = Math.max(10, x);
            this.contextMenu.y = Math.max(10, y);
        },

        closeContextMenu() {
            this.contextMenu.show = false;
        },

        openEditDateModal(doc) {
            this.closeContextMenu();
            this.editDateModal.id = doc.id;
            this.editDateModal.uuid = doc.uuid || doc.id;
            this.editDateModal.name = doc.name;
            this.editDateModal.currentDate = doc.raw_date || '';
            this.editDateModal.newDate = doc.raw_date || '';
            this.editDateModal.isSaving = false;
            this.editDateModal.open = true;
        },

        async saveDisplayDate() {
            if (!this.editDateModal.newDate) return;
            this.editDateModal.isSaving = true;

            try {
                const response = await fetch(`/documents/${this.editDateModal.uuid || this.editDateModal.id}/display-date`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        display_date: this.editDateModal.newDate
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.triggerToast(data.message || 'Tanggal tampil berhasil diubah.');

                    if (this.activeDoc && (this.activeDoc.id === this.editDateModal.id || this.activeDoc.uuid === this.editDateModal.uuid)) {
                        this.activeDoc.date = data.display_date_formatted;
                        this.activeDoc.raw_date = data.display_date;
                    }

                    this.editDateModal.open = false;
                } else {
                    alert(data.message || 'Gagal mengubah tanggal tampil.');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat mengubah tanggal tampil.');
            } finally {
                this.editDateModal.isSaving = false;
            }
        },

        async quickApproveDoc(doc) {
            if (!confirm(`Setujui (ACC) dokumen "${doc.name}"? Dokumen ini akan langsung terbit dan dapat diakses pengguna.`)) return;
            try {
                const response = await fetch(`/approvals/${doc.uuid || doc.id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        notes: 'Disetujui via File Explorer'
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.triggerToast(data.message || 'Dokumen berhasil di-ACC.');
                    if (this.activeDoc && (this.activeDoc.id === doc.id || this.activeDoc.uuid === doc.uuid)) {
                        this.activeDoc.status = 'approved';
                        this.activeDoc.status_label = 'Disetujui (ACC)';
                        this.activeDoc.status_color = 'green';
                        this.activeDoc.needs_acc = false;
                    }
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    alert(data.message || 'Gagal menyetujui dokumen.');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat memproses approval.');
            }
        },

        async quickRejectDoc(doc) {
            const notes = prompt(`Masukkan catatan revisi untuk "${doc.name}":`);
            if (notes === null) return;
            if (!notes.trim()) {
                alert('Catatan revisi wajib diisi.');
                return;
            }
            try {
                const response = await fetch(`/approvals/${doc.uuid || doc.id}/revision`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ notes })
                });
                const data = await response.json();
                if (data.success) {
                    this.triggerToast(data.message || 'Permintaan revisi berhasil dikirim.');
                    if (this.activeDoc && (this.activeDoc.id === doc.id || this.activeDoc.uuid === doc.uuid)) {
                        this.activeDoc.status = 'revision';
                        this.activeDoc.status_label = 'Perlu Revisi';
                        this.activeDoc.status_color = 'red';
                        this.activeDoc.needs_acc = false;
                    }
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    alert(data.message || 'Gagal mengirim revisi.');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat memproses revisi.');
            }
        },

        openShare(type, item) {
            this.closeContextMenu();
            this.shareModal.type = type;
            this.shareModal.id = item.id;
            this.shareModal.uuid = item.uuid || null;
            this.shareModal.title = item.name;
            this.shareModal.department = item.department || 'Umum';
            this.shareModal.visibility = item.visibility || 'Tampil ke Viewer';
            this.shareModal.copied = false;
            this.shareModal.isSaving = false;
            this.shareModal.saveStatus = '';

            const key = (type === 'folder') ? item.id : (item.uuid || item.id);
            if (this.permissionsCache[type] && this.permissionsCache[type][key] !== undefined) {
                this.shareModal.selectedBiros = [...this.permissionsCache[type][key]];
            } else if (Array.isArray(item.shared_departments)) {
                this.shareModal.selectedBiros = [...item.shared_departments];
                if (!this.permissionsCache[type]) this.permissionsCache[type] = {};
                this.permissionsCache[type][key] = [...item.shared_departments];
            } else {
                this.shareModal.selectedBiros = [];
            }

            if (type === 'folder') {
                this.shareModal.url = `${window.location.origin}/folders?folder_id=${item.id}`;
            } else {
                this.shareModal.url = item.preview_url || `${window.location.origin}/documents/${item.uuid}/preview`;
            }
            this.shareModal.open = true;
        },

        selectAllBiros() {
            this.shareModal.selectedBiros = [...this.allUnits];
            this.autoSavePermissions();
        },

        deselectAllBiros() {
            this.shareModal.selectedBiros = [];
            this.autoSavePermissions();
        },

        toggleBiro(biro) {
            const index = this.shareModal.selectedBiros.indexOf(biro);
            if (index > -1) {
                this.shareModal.selectedBiros.splice(index, 1);
            } else {
                this.shareModal.selectedBiros.push(biro);
            }
            this.autoSavePermissions();
        },

        autoSavePermissions() {
            this.shareModal.saveStatus = 'saving';
            if (this.saveTimeout) {
                clearTimeout(this.saveTimeout);
            }
            this.saveTimeout = setTimeout(() => {
                this.savePermissions(true);
            }, 300);
        },

        async savePermissions(isAuto = false) {
            if (this.saveTimeout) {
                clearTimeout(this.saveTimeout);
                this.saveTimeout = null;
            }
            this.shareModal.isSaving = true;
            this.shareModal.saveStatus = 'saving';

            const type = this.shareModal.type;
            const key = (type === 'folder') ? this.shareModal.id : (this.shareModal.uuid || this.shareModal.id);
            const currentSelected = [...this.shareModal.selectedBiros];

            // Update in-memory cache immediately so UI is always responsive and fresh
            if (!this.permissionsCache[type]) {
                this.permissionsCache[type] = {};
            }
            this.permissionsCache[type][key] = currentSelected;

            if (this.contextMenu.item && (this.contextMenu.item.id === this.shareModal.id || this.contextMenu.item.uuid === this.shareModal.uuid)) {
                this.contextMenu.item.shared_departments = currentSelected;
            }
            if (this.activeDoc && (this.activeDoc.id === this.shareModal.id || this.activeDoc.uuid === this.shareModal.uuid)) {
                this.activeDoc.shared_departments = currentSelected;
            }

            const endpoint = type === 'folder'
                ? `/folders/${this.shareModal.id}/share`
                : `/documents/${this.shareModal.uuid || this.shareModal.id}/share`;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        shared_departments: currentSelected
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.shareModal.saveStatus = 'saved';
                    const savedDepts = Array.isArray(data.shared_departments) ? data.shared_departments : currentSelected;
                    this.permissionsCache[type][key] = savedDepts;
                    this.shareModal.selectedBiros = [...savedDepts];

                    if (!isAuto) {
                        this.triggerToast(data.message || 'Izin akses biro berhasil disimpan!');
                    }
                    setTimeout(() => {
                        if (this.shareModal.saveStatus === 'saved') {
                            this.shareModal.saveStatus = '';
                        }
                    }, 2500);
                } else {
                    this.shareModal.saveStatus = 'error';
                    if (!isAuto) {
                        alert(data.message || 'Gagal menyimpan izin akses.');
                    }
                }
            } catch (err) {
                console.error(err);
                this.shareModal.saveStatus = 'error';
                if (!isAuto) {
                    alert('Terjadi kesalahan saat menyimpan izin akses biro.');
                }
            } finally {
                this.shareModal.isSaving = false;
            }
        },

        async closeShareModal() {
            if (this.saveTimeout) {
                clearTimeout(this.saveTimeout);
                this.saveTimeout = null;
                await this.savePermissions(true);
            }
            this.shareModal.open = false;
        },

        async copyShareLink() {
            try {
                await navigator.clipboard.writeText(this.shareModal.url);
                this.shareModal.copied = true;
                this.triggerToast('Tautan berhasil disalin ke clipboard!');
                setTimeout(() => { this.shareModal.copied = false; }, 2500);
            } catch (err) {
                const tempInput = document.createElement('input');
                tempInput.value = this.shareModal.url;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                this.shareModal.copied = true;
                this.triggerToast('Tautan berhasil disalin!');
                setTimeout(() => { this.shareModal.copied = false; }, 2500);
            }
        },

        shareViaWhatsApp() {
            const text = encodeURIComponent(`Berikut tautan ${this.shareModal.type === 'folder' ? 'folder' : 'dokumen'} '${this.shareModal.title}': ${this.shareModal.url}`);
            window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
        },

        shareViaEmail() {
            const subject = encodeURIComponent(`${this.shareModal.type === 'folder' ? 'Folder' : 'Dokumen'}: ${this.shareModal.title}`);
            const body = encodeURIComponent(`Halo,\n\nBerikut tautan untuk mengakses ${this.shareModal.type === 'folder' ? 'folder' : 'dokumen'} '${this.shareModal.title}':\n${this.shareModal.url}\n\nTerima kasih.`);
            window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
        },

        openRenameFolder(id, name) {
            this.editFolderId = id;
            this.editFolderName = name;
            this.renameFolderModal = true;
        },

        openMoveDoc(id, name) {
            this.moveDocId = id;
            this.moveDocName = name;
            this.moveDocModal = true;
        },

        selectDoc(doc) {
            this.activeDoc = doc;
            this.inspectorOpen = true;
        },

        onDocDragStart(event, docId) {
            this.draggedDocId = docId;
            event.dataTransfer.setData('text/plain', docId);
            event.dataTransfer.effectAllowed = 'move';
        },

        onFolderDragOver(event, folderId) {
            event.preventDefault();
            this.hoveredFolderId = folderId;
            event.dataTransfer.dropEffect = 'move';
        },

        onFolderDragLeave(event, folderId) {
            if (this.hoveredFolderId === folderId) {
                this.hoveredFolderId = null;
            }
        },

        async onFolderDrop(event, targetFolderId) {
            event.preventDefault();
            this.hoveredFolderId = null;

            if (event.dataTransfer.types && event.dataTransfer.types.includes('Files')) {
                await this.handleDropItems(event.dataTransfer, targetFolderId);
                return;
            }

            const docId = this.draggedDocId || event.dataTransfer.getData('text/plain');
            if (!docId) return;

            try {
                const response = await fetch('/documents/' + docId + '/move', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ folder_id: targetFolderId })
                });

                const data = await response.json();
                if (data.success) {
                    this.triggerToast(data.message);
                    setTimeout(() => { window.location.reload(); }, 600);
                } else {
                    alert(data.message || 'Gagal memindahkan dokumen.');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan saat memindahkan dokumen.');
            }
        },

        onWindowDragOver(event) {
            event.preventDefault();
            if (event.dataTransfer.types && event.dataTransfer.types.includes('Files')) {
                this.isDraggingFileOver = true;
            }
        },

        onWindowDragLeave(event) {
            if (event.clientX <= 0 || event.clientY <= 0 || event.clientX >= window.innerWidth || event.clientY >= window.innerHeight) {
                this.isDraggingFileOver = false;
            }
        },

        async onWindowDrop(event) {
            event.preventDefault();
            this.isDraggingFileOver = false;
            const currentFolderId = '{{ $currentFolder ? $currentFolder->id : '' }}';
            await this.handleDropItems(event.dataTransfer, currentFolderId || null);
        },

        async handleDropItems(dataTransfer, targetFolderId) {
            const items = dataTransfer.items;
            this.isUploading = true;
            this.uploadMessage = 'Memeriksa berkas dan folder...';

            const queue = [];

            if (items && items.length > 0) {
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    if (item.kind !== 'file') continue;

                    const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                    if (entry) {
                        const traversed = await this.traverseFileSystemEntry(entry, targetFolderId);
                        queue.push(...traversed);
                    } else {
                        const file = item.getAsFile();
                        if (file) queue.push({ file: file, folderId: targetFolderId });
                    }
                }
            } else if (dataTransfer.files && dataTransfer.files.length > 0) {
                for (let i = 0; i < dataTransfer.files.length; i++) {
                    queue.push({ file: dataTransfer.files[i], folderId: targetFolderId });
                }
            }

            if (queue.length === 0) {
                this.isUploading = false;
                this.triggerToast('Folder berhasil dibuat!');
                setTimeout(() => { window.location.reload(); }, 600);
                return;
            }

            await this.processUploadQueue(queue);
        },

        async traverseFileSystemEntry(entry, parentFolderId) {
            if (entry.isFile) {
                return new Promise(resolve => {
                    entry.file(file => {
                        resolve([{ file: file, folderId: parentFolderId }]);
                    }, () => resolve([]));
                });
            } else if (entry.isDirectory) {
                this.uploadMessage = 'Membuat folder ' + entry.name + '...';
                let createdFolderId = parentFolderId;

                try {
                    const res = await fetch('{{ route("folders.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            name: entry.name,
                            parent_id: parentFolderId || null
                        })
                    });
                    const resData = await res.json();
                    if (resData.success && resData.folder) {
                        createdFolderId = resData.folder.id;
                    }
                } catch (err) {
                    console.error('Gagal membuat folder:', entry.name, err);
                }

                const dirReader = entry.createReader();
                const entries = await new Promise(resolve => {
                    const all = [];
                    function readNext() {
                        dirReader.readEntries(results => {
                            if (results.length > 0) {
                                all.push(...results);
                                readNext();
                            } else {
                                resolve(all);
                            }
                        }, () => resolve(all));
                    }
                    readNext();
                });

                const nested = [];
                for (const child of entries) {
                    const childItems = await this.traverseFileSystemEntry(child, createdFolderId);
                    nested.push(...childItems);
                }
                return nested;
            }
            return [];
        },

        async uploadFolderPicker(files, baseFolderId) {
            if (!files || files.length === 0) return;
            this.isUploading = true;
            this.uploadMessage = 'Mempersiapkan struktur folder...';

            const folderMap = {};
            const queue = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const relPath = file.webkitRelativePath || file.name;
                const parts = relPath.split('/');

                let parentId = baseFolderId || null;
                let currentPath = '';

                for (let p = 0; p < parts.length - 1; p++) {
                    const folderName = parts[p];
                    currentPath = currentPath ? (currentPath + '/' + folderName) : folderName;

                    if (folderMap[currentPath]) {
                        parentId = folderMap[currentPath];
                    } else {
                        try {
                            const res = await fetch('{{ route("folders.store") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    name: folderName,
                                    parent_id: parentId
                                })
                            });
                            const data = await res.json();
                            if (data.success && data.folder) {
                                folderMap[currentPath] = data.folder.id;
                                parentId = data.folder.id;
                            }
                        } catch (e) {
                            console.error('Gagal membuat folder:', folderName, e);
                        }
                    }
                }

                queue.push({ file: file, folderId: parentId });
            }

            await this.processUploadQueue(queue);
        },

        async processUploadQueue(queue) {
            this.isUploading = true;
            this.uploadMessage = 'Mengunggah ' + queue.length + ' berkas...';

            for (let i = 0; i < queue.length; i++) {
                const item = queue[i];
                const formData = new FormData();
                formData.append('file', item.file);
                if (item.folderId) {
                    formData.append('folder_id', item.folderId);
                }

                try {
                    this.uploadMessage = 'Mengunggah (' + (i + 1) + '/' + queue.length + '): ' + item.file.name;
                    const res = await fetch('{{ route("folders.quick-upload") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                    const resData = await res.json();
                    if (!resData.success) {
                        console.error('Gagal mengunggah ' + item.file.name, resData);
                    }
                } catch (err) {
                    console.error('Gagal mengunggah berkas ' + item.file.name, err);
                }
            }

            this.isUploading = false;
            this.triggerToast('Semua berkas dan folder berhasil diunggah!');
            setTimeout(() => { window.location.reload(); }, 600);
        },

        async uploadFiles(files, folderId) {
            const queue = [];
            for (let i = 0; i < files.length; i++) {
                queue.push({ file: files[i], folderId: folderId });
            }
            await this.processUploadQueue(queue);
        }
    }"
    @dragover="onWindowDragOver($event)"
    @dragleave="onWindowDragLeave($event)"
    @drop="onWindowDrop($event)">

        <!-- Fullscreen Drop Overlay -->
        <div x-show="isDraggingFileOver" class="fixed inset-0 z-50 bg-blue-600/20 backdrop-blur-sm border-4 border-dashed border-blue-500 flex flex-col items-center justify-center pointer-events-none transition-all" style="display: none;" x-cloak>
            <div class="bg-white p-8 rounded-2xl shadow-2xl flex flex-col items-center max-w-sm text-center">
                <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-4 animate-bounce">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Lepaskan Berkas di Sini</h3>
                <p class="text-xs text-gray-500 mt-1">Berkas akan langsung diunggah otomatis ke dalam folder aktif.</p>
            </div>
        </div>

        <!-- Uploading Progress Indicator Overlay -->
        <div x-show="isUploading" class="fixed inset-0 z-50 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center" style="display: none;" x-cloak>
            <div class="bg-white p-6 rounded-2xl shadow-2xl flex items-center gap-4 max-w-md">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <div>
                    <h4 class="text-sm font-bold text-gray-900">Sedang Mengunggah...</h4>
                    <p class="text-xs text-gray-500 mt-0.5" x-text="uploadMessage"></p>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div x-show="showToast" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2" class="fixed bottom-5 right-5 z-50 bg-gray-900 text-white text-xs px-4 py-3 rounded-xl shadow-xl flex items-center gap-2" style="display: none;" x-cloak>
            <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span x-text="toastMessage"></span>
        </div>

        <!-- Top Header Navigation Bar (PSTI UNISA Theme) -->
        <header class="h-16 bg-[#002147] border-b-2 border-[#f1b500] px-6 shrink-0 flex items-center justify-between gap-4 z-20 shadow-md">
            <!-- Left: Logo & Brand -->
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img src="https://psti.unisayogya.ac.id/wp-content/uploads/2023/11/logo-ti-unisa-putih.png"
                         alt="Program Studi Teknologi Informasi UNISA"
                         class="h-9 sm:h-10 w-auto object-contain"
                         onerror="this.style.display='none'">
                    <div class="border-l border-white/20 pl-3">
                        <h1 class="text-sm sm:text-base font-black text-white leading-tight tracking-tight">SMART</h1>
                        <p class="text-[10px] text-[#f1b500] font-bold uppercase tracking-wider">File Explorer &bull; PSTI</p>
                    </div>
                </a>

                <nav class="hidden md:flex items-center gap-1.5 pl-4 border-l border-white/10 text-xs font-semibold">
                    <a href="{{ route('folders.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('folders.*') ? 'bg-[#f1b500] text-[#002147] font-bold' : 'text-white/80 hover:text-white hover:bg-white/10' }} transition-colors">
                        File Explorer
                    </a>
                    <a href="{{ route('google-drive.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('google-drive.*') ? 'bg-[#f1b500] text-[#002147] font-bold' : 'text-white/80 hover:text-white hover:bg-white/10' }} transition-colors flex items-center gap-1.5">
                        <x-google-drive-icon class="w-3.5 h-3.5 shrink-0" />
                        Google Drive
                    </a>
                    @if(auth()->check() && auth()->user()->canApproveDocuments())
                    <a href="{{ route('approvals.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('approvals.*') ? 'bg-[#f1b500] text-[#002147] font-bold' : 'text-white/80 hover:text-white hover:bg-white/10' }} transition-colors flex items-center gap-1.5" title="Panel Verifikasi &amp; Approval Dokumen">
                        <svg class="w-3.5 h-3.5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Panel Approval</span>
                        @if(($pendingApprovalsCount ?? 0) > 0)
                        <span class="bg-amber-400 text-[#002147] font-black text-[10px] px-1.5 py-0.2 rounded-full shadow-xs">{{ $pendingApprovalsCount }}</span>
                        @endif
                    </a>
                    @endif
                </nav>
            </div>

            <!-- Right Controls & User Info -->
            <div class="flex items-center gap-3 shrink-0">
                <!-- View Toggle: Grid / List -->
                <div class="flex items-center bg-black/25 p-0.5 rounded-lg border border-white/10 text-white/70">
                    <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-[#f1b500] text-[#002147] font-bold shadow-sm' : 'hover:text-white'" class="p-1.5 rounded-md transition-all" title="Tampilan Grid (Drive)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    </button>
                    <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-[#f1b500] text-[#002147] font-bold shadow-sm' : 'hover:text-white'" class="p-1.5 rounded-md transition-all" title="Tampilan List (Explorer)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                    </button>
                </div>

                @if(!$currentFolder || $currentFolder->canManage(auth()->user()))
                <!-- Create Folder Button -->
                <button @click="createFolderModal = true" class="inline-flex items-center px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-xs font-semibold text-white shadow-sm transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                    + Folder
                </button>
                @endif

                @if(!$currentFolder || $currentFolder->canUploadTo(auth()->user()))
                <!-- Hidden File Input for Instant Upload -->
                <input type="file" x-ref="quickFileInput" @change="uploadFiles($event.target.files, '{{ $currentFolder ? $currentFolder->id : '' }}')" multiple class="hidden">

                <!-- Hidden Folder Input for Folder Picker -->
                <input type="file" x-ref="folderPickerInput" webkitdirectory directory @change="uploadFolderPicker($event.target.files, '{{ $currentFolder ? $currentFolder->id : '' }}')" class="hidden">

                <!-- Upload Document Button Group (Gold PSTI Theme) -->
                <div class="relative inline-flex rounded-lg shadow-sm" x-data="{ uploadMenu: false }">
                    <button type="button" @click="$refs.quickFileInput.click()" class="inline-flex items-center px-3.5 py-1.5 bg-[#f1b500] hover:bg-[#e5a800] text-[#002147] rounded-l-lg text-xs font-bold shadow-sm transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Upload Berkas
                    </button>
                    <button type="button" @click="uploadMenu = !uploadMenu" class="px-2 py-1.5 bg-[#dfa200] hover:bg-[#cf9400] text-[#002147] rounded-r-lg text-xs font-bold transition-colors border-l border-black/10 cursor-pointer">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="uploadMenu" @click.outside="uploadMenu = false" class="absolute right-0 mt-8 w-52 bg-white rounded-xl shadow-xl border border-gray-200 py-1.5 z-30 text-xs" style="display: none;">
                        <button type="button" @click="$refs.quickFileInput.click(); uploadMenu = false;" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-[#002147]/5 hover:text-[#002147] flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#002147]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <span>Upload Berkas (File Picker)</span>
                        </button>
                        <button type="button" @click="$refs.folderPickerInput.click(); uploadMenu = false;" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-[#002147]/5 hover:text-[#002147] flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                            <span>Upload Folder Lengkap</span>
                        </button>
                        <a href="{{ route('documents.create', ['folder_id' => $currentFolder ? $currentFolder->id : null]) }}" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-[#002147]/5 hover:text-[#002147] flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Form Lengkap &amp; Metadata</span>
                        </a>
                        <a href="{{ route('google-drive.index') }}" class="w-full text-left px-3.5 py-2 text-blue-700 hover:bg-[#002147]/5 hover:text-[#002147] flex items-center gap-2 border-t border-gray-100 font-semibold">
                            <x-google-drive-icon class="w-4 h-4 shrink-0" />
                            <span>Impor dari Google Drive</span>
                        </a>
                    </div>
                </div>
                @endif

                <!-- User Dropdown (Alpine.js) -->
                <div class="relative ml-2" x-data="{ userMenu: false }">
                    <button @click="userMenu = !userMenu" class="flex items-center gap-2 p-1 rounded-lg hover:bg-white/10 transition-colors focus:outline-none">
                        <div class="w-8 h-8 rounded-full bg-[#f1b500] text-[#002147] font-black flex items-center justify-center text-xs shadow-sm ring-2 ring-white/20">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="text-left hidden lg:block">
                            <div class="text-xs font-bold text-white leading-tight">{{ auth()->user()->name }}</div>
                            <div class="text-[10px] text-gray-300 leading-tight">{{ auth()->user()->role_label }}</div>
                        </div>
                        <svg class="w-3.5 h-3.5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="userMenu" @click.outside="userMenu = false" class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-200 py-1.5 z-30" style="display: none;">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-xs font-bold text-gray-900">{{ auth()->user()->name }}</p>
                            <p class="text-[11px] text-gray-500 truncate">{{ auth()->user()->department ?? 'Superadmin Pusat' }}</p>
                        </div>

                        <a href="{{ route('google-drive.index') }}" class="flex items-center px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 font-medium">
                            <x-google-drive-icon class="w-4 h-4 mr-2 shrink-0" />
                            Google Drive (Cloud)
                        </a>

                        @if(auth()->user()->canApproveDocuments())
                        <a href="{{ route('approvals.index') }}" class="flex items-center justify-between px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 font-medium">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Panel Approval
                            </span>
                            @if(($pendingApprovalsCount ?? 0) > 0)
                            <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingApprovalsCount }}</span>
                            @endif
                        </a>
                        @endif

                        @if(auth()->user()->canManageUsers())
                        <a href="{{ route('users.index') }}" class="flex items-center px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 font-medium">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            Kelola Pengguna
                        </a>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="flex items-center px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 font-medium">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Pengaturan Profil
                        </a>

                        <div class="border-t border-gray-100 my-1"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center px-4 py-2 text-xs text-red-600 hover:bg-red-50 font-semibold">
                                <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                Keluar (Logout)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Subheader: Breadcrumb Bar -->
        <div class="bg-white border-b border-gray-200 px-6 py-2.5 shrink-0 flex items-center justify-between gap-4 shadow-xs">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                @if($currentFolder && $currentFolder->parent_id)
                    <a href="{{ route('folders.index', ['folder_id' => $currentFolder->parent_id]) }}" class="p-1 rounded text-gray-500 hover:text-[#002147] hover:bg-gray-100 transition-colors" title="Naik Satu Tingkat">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </a>
                @endif

                <nav class="flex items-center text-xs font-medium text-gray-600 truncate">
                    <a href="{{ route('folders.index') }}" class="hover:text-[#002147] flex items-center shrink-0 transition-colors {{ empty($currentFolder) ? 'text-[#002147] font-bold' : '' }}">
                        <svg class="w-3.5 h-3.5 mr-1 text-[#f1b500]" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                        DOKUMEN
                    </a>

                    @foreach($breadcrumbs as $bc)
                        <svg class="w-3.5 h-3.5 text-gray-400 mx-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        @if($loop->last)
                            <span class="text-[#002147] font-bold truncate">{{ $bc['name'] }}</span>
                        @else
                            <a href="{{ route('folders.index', ['folder_id' => $bc['id']]) }}" class="hover:text-[#002147] truncate transition-colors">
                                {{ $bc['name'] }}
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>

            @if($currentFolder && $currentFolder->getEffectiveDepartment())
                <div class="shrink-0 text-[11px] text-gray-600 bg-[#002147]/5 px-2.5 py-1 rounded-full border border-[#002147]/10 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#f1b500]"></span>
                    <span>Unit:</span> <span class="font-bold text-[#002147]">{{ $currentFolder->getEffectiveDepartment() }}</span>
                    @if($currentFolder->isSharedFromOtherDepartment(auth()->user()))
                        <span class="ms-1 text-[9px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded">Dibagikan</span>
                    @endif
                </div>
            @endif
        </div>

        <!-- Main Workspace Area: Tree Left Pane + Explorer Content + Right Inspector -->
        <div class="flex-1 flex overflow-hidden">

            <!-- Left Pane: Folder Tree Directory (Explorer Style) -->
            <aside class="w-64 bg-white border-r border-gray-200 flex flex-col shrink-0 overflow-y-auto hidden md:flex">
                <div class="p-3 border-b border-gray-100 flex items-center justify-between shrink-0 bg-slate-50/60">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#002147]">Direktori Unit</span>
                    <span class="text-[10px] bg-[#002147]/10 text-[#002147] font-bold px-2 py-0.5 rounded-full">{{ $folderTree->count() }} Unit</span>
                </div>

                <div class="p-2 space-y-0.5 text-sm flex-1 overflow-y-auto">
                    <!-- Root Item -->
                    <a href="{{ route('folders.index') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ empty($currentFolder) ? 'bg-[#002147] text-white font-bold shadow-sm' : 'text-gray-700 hover:bg-slate-100 hover:text-[#002147]' }} transition-colors">
                        <svg class="w-4 h-4 mr-2 {{ empty($currentFolder) ? 'text-[#f1b500]' : 'text-blue-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                        Semua Dokumen
                    </a>

                    <!-- Google Drive Cloud Shortcut -->
                    <a href="{{ route('google-drive.index') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                        <x-google-drive-icon class="w-4 h-4 mr-2 shrink-0" />
                        <span>Google Drive (Cloud)</span>
                    </a>

                    <div class="pt-2 pb-1 px-1">
                        <div class="border-t border-gray-100"></div>
                    </div>

                    <!-- Tree Items -->
                    @foreach($folderTree as $rf)
                    @php
                        $rfAccessibleChildren = $rf->getAccessibleChildren(auth()->user());
                        $isRfShared = $rf->isSharedFromOtherDepartment(auth()->user());
                    @endphp
                    <div class="space-y-0.5" x-data="{ expanded: {{ ($currentFolder && ($currentFolder->id == $rf->id || $currentFolder->getEffectiveDepartment() == $rf->department)) ? 'true' : 'false' }} }">
                        <div class="flex items-center justify-between px-2 py-1.5 rounded-lg text-xs {{ ($currentFolder && $currentFolder->id == $rf->id) ? 'bg-[#002147] text-white font-bold shadow-sm' : 'text-gray-700 hover:bg-slate-100 hover:text-[#002147]' }} transition-colors group cursor-pointer"
                             @dragover="onFolderDragOver($event, {{ $rf->id }})"
                             @dragleave="onFolderDragLeave($event, {{ $rf->id }})"
                             @drop="onFolderDrop($event, {{ $rf->id }})"
                             @contextmenu.prevent="openContextMenu($event, 'folder', { id: {{ $rf->id }}, name: '{{ addslashes($rf->name) }}', department: '{{ addslashes($rf->department ?? 'Umum') }}', shared_departments: {{ json_encode($rf->shared_departments ?? []) }}, can_manage: {{ $rf->canManage(auth()->user()) ? 'true' : 'false' }}, url: '{{ route('folders.index', ['folder_id' => $rf->id]) }}' })"
                             :class="hoveredFolderId == {{ $rf->id }} ? 'ring-2 ring-[#f1b500] bg-amber-50' : ''">

                            <a href="{{ route('folders.index', ['folder_id' => $rf->id]) }}" class="flex items-center min-w-0 flex-1 truncate">
                                <svg class="w-3.5 h-3.5 mr-2 text-[#f1b500] shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                <span class="truncate">{{ $rf->name }}</span>
                            </a>

                            @if($isRfShared)
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/20 text-[#f1b500] font-bold mr-1 shrink-0">Shared</span>
                            @endif

                            @if($rfAccessibleChildren->isNotEmpty())
                                <button @click="expanded = !expanded" class="p-0.5 text-gray-400 hover:text-gray-700">
                                    <svg class="w-3.5 h-3.5 transform transition-transform" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            @endif
                        </div>

                        <!-- Subfolder Tree level 1 -->
                        @if($rfAccessibleChildren->isNotEmpty())
                        <div x-show="expanded" x-collapse class="pl-4 space-y-0.5">
                            @foreach($rfAccessibleChildren as $cf)
                            <div class="flex items-center px-2 py-1 rounded-md text-xs {{ ($currentFolder && $currentFolder->id == $cf->id) ? 'bg-[#002147] text-white font-bold' : 'text-gray-600 hover:bg-slate-100 hover:text-[#002147]' }} transition-colors cursor-pointer"
                                 @dragover="onFolderDragOver($event, {{ $cf->id }})"
                                 @dragleave="onFolderDragLeave($event, {{ $cf->id }})"
                                 @drop="onFolderDrop($event, {{ $cf->id }})"
                                 @contextmenu.prevent="openContextMenu($event, 'folder', { id: {{ $cf->id }}, name: '{{ addslashes($cf->name) }}', department: '{{ addslashes($cf->department ?? 'Umum') }}', shared_departments: {{ json_encode($cf->shared_departments ?? []) }}, can_manage: {{ $cf->canManage(auth()->user()) ? 'true' : 'false' }}, url: '{{ route('folders.index', ['folder_id' => $cf->id]) }}' })"
                                 :class="hoveredFolderId == {{ $cf->id }} ? 'ring-2 ring-[#f1b500] bg-amber-50' : ''">
                                <a href="{{ route('folders.index', ['folder_id' => $cf->id]) }}" class="flex items-center min-w-0 flex-1 truncate">
                                    <svg class="w-3 h-3 mr-1.5 text-[#f1b500] shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                    <span class="truncate">{{ $cf->name }}</span>
                                </a>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </aside>

            <!-- Center Pane: Folder Explorer (Google Drive Grid / List) -->
            <main class="flex-1 overflow-y-auto p-6 flex flex-col justify-between">
                <div>
                    <!-- Drop Zone Hint Banner (Clickable & Drag-Drop) -->
                    <div @click="$refs.quickFileInput.click()" class="mb-6 p-4 rounded-xl border-2 border-dashed border-gray-300 bg-white hover:border-[#002147] hover:bg-[#002147]/5 transition-all text-center flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xs cursor-pointer group">
                        <div class="flex items-center gap-3 text-left">
                            <div class="w-10 h-10 rounded-xl bg-[#002147]/10 text-[#002147] flex items-center justify-center shrink-0 group-hover:bg-[#002147] group-hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 group-hover:text-[#002147] transition-colors">Klik untuk Pilih Berkas/Folder atau Drag &amp; Drop ke Sini</h4>
                                <p class="text-[11px] text-gray-500">Pilih berkas atau folder dari komputermu, atau tarik langsung ke area ini untuk upload instan.</p>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-[#002147] bg-[#f1b500] hover:bg-[#e5a800] px-3 py-1.5 rounded-lg uppercase tracking-wider shrink-0 transition-colors shadow-xs">
                            Pilih Berkas / Folder
                        </span>
                    </div>

                    <!-- 1. Folders Section -->
                    @if($subfolders->isNotEmpty())
                    <section class="mb-8">
                        <h3 class="text-xs font-bold text-[#002147] uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-3.5 bg-[#f1b500] rounded-full"></span>
                            Folder ({{ $subfolders->count() }})
                        </h3>

                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                            @foreach($subfolders as $sf)
                            <div class="bg-white rounded-xl border border-gray-200 hover:border-[#002147] hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-3.5 flex flex-col justify-between group relative cursor-pointer"
                                 @dragover="onFolderDragOver($event, {{ $sf->id }})"
                                 @dragleave="onFolderDragLeave($event, {{ $sf->id }})"
                                 @drop="onFolderDrop($event, {{ $sf->id }})"
                                 @contextmenu.prevent="openContextMenu($event, 'folder', { id: {{ $sf->id }}, name: '{{ addslashes($sf->name) }}', department: '{{ addslashes($sf->department ?? 'Umum') }}', shared_departments: {{ json_encode($sf->shared_departments ?? []) }}, can_manage: {{ $sf->canManage(auth()->user()) ? 'true' : 'false' }}, url: '{{ route('folders.index', ['folder_id' => $sf->id]) }}' })"
                                 :class="hoveredFolderId == {{ $sf->id }} ? 'ring-2 ring-[#f1b500] bg-amber-50/40 scale-105 transition-transform' : ''">

                                <div class="flex items-start justify-between">
                                    <a href="{{ route('folders.index', ['folder_id' => $sf->id]) }}" class="flex items-center min-w-0 flex-1 pr-2">
                                        <div class="w-9 h-9 rounded-lg bg-amber-50 text-[#f1b500] flex items-center justify-center mr-2.5 shrink-0 group-hover:scale-105 transition-transform">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <h4 class="text-xs font-bold text-gray-800 truncate group-hover:text-[#002147] transition-colors">{{ $sf->name }}</h4>
                                                @if($sf->isSharedFromOtherDepartment(auth()->user()))
                                                    <span class="text-[9px] bg-amber-100 text-amber-800 font-bold px-1.5 py-0.2 rounded shrink-0">Dibagikan</span>
                                                @endif
                                            </div>
                                            <p class="text-[10px] text-gray-400 truncate">{{ $sf->department ?? 'Umum' }}</p>
                                        </div>
                                    </a>

                                    <!-- Action Menu -->
                                    <div class="relative shrink-0" x-data="{ open: false }">
                                        <button @click="open = !open" class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                                        </button>
                                        <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-xl py-1 z-20 text-xs" style="display: none;">
                                            @if($sf->canManage(auth()->user()))
                                            <button @click="openShare('folder', { id: {{ $sf->id }}, name: '{{ addslashes($sf->name) }}', department: '{{ addslashes($sf->department ?? 'Umum') }}', shared_departments: {{ json_encode($sf->shared_departments ?? []) }}, can_manage: true, url: '{{ route('folders.index', ['folder_id' => $sf->id]) }}' }); open = false" class="w-full text-left px-3 py-1.5 text-gray-700 hover:bg-gray-50 flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                                Bagikan Folder
                                            </button>
                                            <button @click="openRenameFolder({{ $sf->id }}, '{{ addslashes($sf->name) }}'); open = false" class="w-full text-left px-3 py-1.5 text-gray-700 hover:bg-gray-50 flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                Ubah Nama
                                            </button>
                                            <form action="{{ route('folders.destroy', $sf) }}" method="POST" onsubmit="return confirm('Hapus folder ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left px-3 py-1.5 text-red-600 hover:bg-red-50 flex items-center">
                                                    <svg class="w-3.5 h-3.5 mr-1.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Hapus
                                                </button>
                                            </form>
                                            @else
                                            <div class="px-3 py-1.5 text-gray-400 italic text-[11px] flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                <span>Read Only</span>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 pt-2 border-t border-gray-100 flex items-center justify-between text-[10px] text-gray-400">
                                    <span>{{ $sf->children()->count() }} subfolder</span>
                                    <span>{{ $sf->documents()->count() }} berkas</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </section>
                    @endif

                    <!-- 2. Documents Section -->
                    <section>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-[#002147] uppercase tracking-wider flex items-center gap-2">
                                <span class="w-1.5 h-3.5 bg-[#f1b500] rounded-full"></span>
                                Berkas Dokumen ({{ $documents->total() }})
                            </h3>
                            <span class="text-[11px] text-gray-400">Tarik dokumen dan lepaskan ke folder di atas untuk memindahkan.</span>
                        </div>

                        <!-- 2A. GRID VIEW (Google Drive Card Style) -->
                        <div x-show="viewMode === 'grid'">
                            @if($documents->isNotEmpty())
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                                @foreach($documents as $doc)
                                @php
                                    $ext = $doc->latestVersion ? pathinfo($doc->latestVersion->file_path, PATHINFO_EXTENSION) : 'file';
                                    $iconColor = match(strtolower($ext)) {
                                        'pdf' => 'text-red-500 bg-red-50',
                                        'doc', 'docx' => 'text-blue-500 bg-blue-50',
                                        'xls', 'xlsx' => 'text-emerald-500 bg-emerald-50',
                                        'ppt', 'pptx' => 'text-orange-500 bg-orange-50',
                                        'jpg', 'jpeg', 'png' => 'text-purple-500 bg-purple-50',
                                        default => 'text-gray-500 bg-gray-100'
                                    };
                                    $canManageDoc = auth()->check() && (
                                        auth()->user()->isSuperAdmin() ||
                                        $doc->created_by === auth()->id() ||
                                        ($doc->department && auth()->user()->matchesDepartment($doc->department))
                                    );
                                    $isDocShared = $doc->isSharedFromOtherDepartment(auth()->user());
                                    $displayStatus = $doc->getDisplayStatusForUser(auth()->user());
                                    $displayStatusLabel = $doc->getDisplayStatusLabelForUser(auth()->user());
                                    $displayStatusColor = $doc->getDisplayStatusColorForUser(auth()->user());
                                    $needsProdiAcc = auth()->check() && (auth()->user()->isAdminProdi() || auth()->user()->isSuperAdmin()) && $doc->needsProdiApproval(auth()->user());

                                    $docPayload = [
                                        'id' => $doc->id,
                                        'uuid' => $doc->uuid,
                                        'name' => $doc->name,
                                        'document_number' => $doc->document_number,
                                        'department' => $doc->department ?? 'Umum',
                                        'category' => $doc->category->name ?? '-',
                                        'date' => $doc->effective_display_date ? $doc->effective_display_date->format('d F Y') : '-',
                                        'raw_date' => $doc->effective_display_date ? $doc->effective_display_date->format('Y-m-d') : date('Y-m-d'),
                                        'visibility' => $doc->visibility_label,
                                        'status' => $displayStatus,
                                        'status_label' => $displayStatusLabel,
                                        'status_color' => $displayStatusColor,
                                        'needs_acc' => $needsProdiAcc,
                                        'is_downloadable' => (bool)$doc->is_downloadable,
                                        'shared_departments' => $doc->shared_departments ?? [],
                                        'file_size' => $doc->latestVersion ? $doc->latestVersion->file_size_formatted : '-',
                                        'description' => $doc->description ?? 'Tidak ada deskripsi.',
                                        'preview_url' => route('documents.preview', $doc),
                                        'download_url' => route('documents.download', $doc),
                                        'edit_url' => $canManageDoc ? route('documents.edit', $doc) : null,
                                        'can_manage' => $canManageDoc,
                                        'is_shared' => $isDocShared,
                                    ];
                                @endphp
                                <div draggable="{{ auth()->check() ? 'true' : 'false' }}"
                                     @dragstart="onDocDragStart($event, '{{ $doc->uuid }}')"
                                     @click="selectDoc({{ json_encode($docPayload) }})"
                                     @contextmenu.prevent="openContextMenu($event, 'document', {{ json_encode($docPayload) }})"
                                     class="bg-white rounded-2xl border border-gray-200 hover:border-[#002147] hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between cursor-pointer group select-none relative overflow-hidden"
                                     :class="activeDoc && activeDoc.id == {{ $doc->id }} ? 'ring-2 ring-[#002147] bg-slate-50' : ''">

                                    <!-- 1. Thumbnail Preview Box (Google Drive Style) -->
                                    <div class="h-32 w-full bg-gray-50 border-b border-gray-100 relative overflow-hidden flex items-center justify-center">
                                        @if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                            <!-- Image Thumbnail Preview -->
                                            <img src="{{ route('documents.preview', $doc) }}" alt="{{ $doc->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" loading="lazy">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        @elseif(strtolower($ext) === 'pdf')
                                            <!-- PDF Mockup Thumbnail -->
                                            <div class="w-16 h-20 bg-white rounded-md shadow-md border border-red-200 p-2 flex flex-col justify-between group-hover:scale-105 transition-transform duration-200">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] font-extrabold text-red-600">PDF</span>
                                                    <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="h-1 bg-red-100 rounded w-full"></div>
                                                    <div class="h-1 bg-red-100 rounded w-3/4"></div>
                                                    <div class="h-1 bg-red-100 rounded w-1/2"></div>
                                                </div>
                                            </div>
                                        @elseif(in_array(strtolower($ext), ['doc', 'docx']))
                                            <!-- Word Mockup Thumbnail -->
                                            <div class="w-16 h-20 bg-white rounded-md shadow-md border border-blue-200 p-2 flex flex-col justify-between group-hover:scale-105 transition-transform duration-200">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] font-extrabold text-blue-600">DOC</span>
                                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="h-1 bg-blue-100 rounded w-full"></div>
                                                    <div class="h-1 bg-blue-100 rounded w-4/5"></div>
                                                    <div class="h-1 bg-blue-100 rounded w-2/3"></div>
                                                </div>
                                            </div>
                                        @elseif(in_array(strtolower($ext), ['xls', 'xlsx']))
                                            <!-- Excel Mockup Thumbnail -->
                                            <div class="w-16 h-20 bg-white rounded-md shadow-md border border-emerald-200 p-2 flex flex-col justify-between group-hover:scale-105 transition-transform duration-200">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] font-extrabold text-emerald-600">XLS</span>
                                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                </div>
                                                <div class="grid grid-cols-2 gap-0.5">
                                                    <div class="h-2 bg-emerald-100 rounded-xs"></div>
                                                    <div class="h-2 bg-emerald-50 rounded-xs"></div>
                                                    <div class="h-2 bg-emerald-50 rounded-xs"></div>
                                                    <div class="h-2 bg-emerald-100 rounded-xs"></div>
                                                </div>
                                            </div>
                                        @elseif(in_array(strtolower($ext), ['ppt', 'pptx']))
                                            <!-- Presentation Mockup Thumbnail -->
                                            <div class="w-20 h-14 bg-white rounded-md shadow-md border border-orange-200 p-2 flex flex-col justify-between group-hover:scale-105 transition-transform duration-200">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] font-extrabold text-orange-600">PPT</span>
                                                    <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                                                </div>
                                                <div class="h-1.5 bg-orange-100 rounded w-2/3"></div>
                                            </div>
                                        @else
                                            <!-- General Document Mockup -->
                                            <div class="w-14 h-16 bg-white rounded-md shadow-md border border-gray-200 p-2 flex flex-col items-center justify-center text-gray-500 group-hover:scale-105 transition-transform duration-200">
                                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                            </div>
                                        @endif

                                        <!-- Status Badge Top Left -->
                                        @if(auth()->check() && auth()->user()->isAdmin())
                                            @if($displayStatusColor === 'green')
                                                <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded shadow-xs bg-emerald-600 text-white flex items-center gap-1 backdrop-blur-xs">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                    ACC
                                                </span>
                                            @elseif($displayStatusColor === 'yellow')
                                                <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded shadow-xs bg-amber-500 text-white flex items-center gap-1 backdrop-blur-xs animate-pulse">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    Menunggu ACC
                                                </span>
                                            @elseif($displayStatusColor === 'red')
                                                <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded shadow-xs bg-red-600 text-white flex items-center gap-1 backdrop-blur-xs">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Revisi
                                                </span>
                                            @else
                                                <span class="absolute top-2 left-2 text-[9px] font-bold px-2 py-0.5 rounded shadow-xs bg-gray-600 text-white backdrop-blur-xs">
                                                    Draft
                                                </span>
                                            @endif
                                        @elseif(auth()->check() && auth()->user()->isUser() && $doc->created_by === auth()->id() && $displayStatusColor !== 'green')
                                            <span class="absolute top-2 left-2 text-[9px] font-medium px-2 py-0.5 rounded shadow-xs bg-amber-100 text-amber-900 border border-amber-300">
                                                Menunggu Verifikasi
                                            </span>
                                        @endif

                                        <!-- Visibility Tag Top Right -->
                                        <span class="absolute top-2 right-2 text-[9px] font-bold px-1.5 py-0.5 rounded shadow-xs backdrop-blur-xs {{ $doc->visibility === 'viewer' ? 'bg-green-600/90 text-white' : ($doc->visibility === 'internal' ? 'bg-blue-600/90 text-white' : 'bg-gray-800/90 text-white') }}">
                                            {{ $doc->visibility_label }}
                                        </span>
                                    </div>

                                    <!-- 2. Bottom Content Info -->
                                    <div class="p-3">
                                        <div class="flex items-start gap-1.5 mb-1">
                                            @if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                <svg class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012-2h5.586a1 1 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            @elseif(strtolower($ext) === 'pdf')
                                                <svg class="w-3.5 h-3.5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                            @elseif(in_array(strtolower($ext), ['doc', 'docx']))
                                                <svg class="w-3.5 h-3.5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            @elseif(in_array(strtolower($ext), ['xls', 'xlsx']))
                                                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            @else
                                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                            @endif
                                            <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                                <h4 class="text-xs font-semibold text-gray-900 truncate group-hover:text-[#002147] transition-colors" title="{{ $doc->name }}">
                                                    {{ $doc->name }}
                                                </h4>
                                                @if($isDocShared)
                                                    <span class="text-[9px] bg-amber-100 text-amber-800 font-bold px-1.5 py-0.2 rounded shrink-0">Dibagikan</span>
                                                @endif
                                            </div>
                                        </div>

                                        <p class="text-[10px] text-gray-400 truncate">
                                            {{ $doc->effective_display_date ? $doc->effective_display_date->format('d M Y') : '-' }} &bull; {{ $doc->category->name ?? '-' }}
                                        </p>

                                        <!-- Footer card: Version and Share button -->
                                        <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-[10px] text-gray-400">
                                            <span>v{{ $doc->current_version }} &bull; {{ $doc->latestVersion ? $doc->latestVersion->file_size_formatted : '' }}</span>
                                            <div class="flex items-center gap-1.5">
                                                @if($needsProdiAcc)
                                                <button type="button" @click.stop="quickApproveDoc({{ json_encode($docPayload) }})" class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold rounded shadow-xs flex items-center gap-0.5 transition-colors cursor-pointer" title="ACC Dokumen">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                    <span>ACC</span>
                                                </button>
                                                @endif
                                                @if($canManageDoc)
                                                <button @click.stop="openShare('document', {{ json_encode($docPayload) }})" class="text-gray-400 hover:text-emerald-600 p-1 rounded-md hover:bg-emerald-50 transition-colors" title="Bagikan Dokumen">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                                </button>
                                                @else
                                                <span class="text-[9px] text-gray-400 italic bg-gray-50 px-1 py-0.2 rounded border border-gray-100">Read Only</span>
                                                @endif
                                                <button @click.stop="openContextMenu($event, 'document', {{ json_encode($docPayload) }})" class="text-gray-400 hover:text-gray-700 p-1 rounded-md hover:bg-gray-100 transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <div class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-200">
                                <p class="text-sm font-semibold text-gray-700">Folder ini masih kosong</p>
                                <p class="text-xs text-gray-400 mt-1">Tarik &amp; jatuhkan berkas dari komputermu ke sini untuk upload instan.</p>
                            </div>
                            @endif
                        </div>

                        <!-- 2B. LIST VIEW (Windows Explorer Table Style) -->
                        <div x-show="viewMode === 'list'" style="display: none;">
                            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50/75">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama Berkas</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Kategori</th>
                                            @if(auth()->check() && auth()->user()->isAdmin())
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                            @endif
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Visibilitas</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tanggal Tampil</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        @forelse($documents as $doc)
                                        @php
                                            $canManageDoc = auth()->check() && (
                                                auth()->user()->isSuperAdmin() ||
                                                $doc->created_by === auth()->id() ||
                                                ($doc->department && auth()->user()->matchesDepartment($doc->department))
                                            );
                                            $isDocShared = $doc->isSharedFromOtherDepartment(auth()->user());
                                            $displayStatus = $doc->getDisplayStatusForUser(auth()->user());
                                            $displayStatusLabel = $doc->getDisplayStatusLabelForUser(auth()->user());
                                            $displayStatusColor = $doc->getDisplayStatusColorForUser(auth()->user());
                                            $needsProdiAcc = auth()->check() && (auth()->user()->isAdminProdi() || auth()->user()->isSuperAdmin()) && $doc->needsProdiApproval(auth()->user());

                                            $docPayload = [
                                                'id' => $doc->id,
                                                'uuid' => $doc->uuid,
                                                'name' => $doc->name,
                                                'document_number' => $doc->document_number,
                                                'department' => $doc->department ?? 'Umum',
                                                'category' => $doc->category->name ?? '-',
                                                'date' => $doc->effective_display_date ? $doc->effective_display_date->format('d F Y') : '-',
                                                'raw_date' => $doc->effective_display_date ? $doc->effective_display_date->format('Y-m-d') : date('Y-m-d'),
                                                'visibility' => $doc->visibility_label,
                                                'status' => $displayStatus,
                                                'status_label' => $displayStatusLabel,
                                                'status_color' => $displayStatusColor,
                                                'needs_acc' => $needsProdiAcc,
                                                'is_downloadable' => (bool)$doc->is_downloadable,
                                                'shared_departments' => $doc->shared_departments ?? [],
                                                'file_size' => $doc->latestVersion ? $doc->latestVersion->file_size_formatted : '-',
                                                'description' => $doc->description ?? 'Tidak ada deskripsi.',
                                                'preview_url' => route('documents.preview', $doc),
                                                'download_url' => route('documents.download', $doc),
                                                'edit_url' => $canManageDoc ? route('documents.edit', $doc) : null,
                                                'can_manage' => $canManageDoc,
                                                'is_shared' => $isDocShared,
                                            ];
                                        @endphp
                                        <tr draggable="{{ auth()->check() ? 'true' : 'false' }}"
                                            @dragstart="onDocDragStart($event, '{{ $doc->uuid }}')"
                                            @click="selectDoc({{ json_encode($docPayload) }})"
                                            @contextmenu.prevent="openContextMenu($event, 'document', {{ json_encode($docPayload) }})"
                                            class="hover:bg-slate-50 cursor-pointer transition-colors"
                                            :class="activeDoc && activeDoc.id == {{ $doc->id }} ? 'bg-[#002147]/5 font-semibold text-[#002147]' : ''">
                                            <td class="px-5 py-3 text-xs font-semibold text-gray-900 flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-[#002147] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                <span class="truncate">{{ $doc->name }}</span>
                                                @if($isDocShared)
                                                    <span class="ms-2 text-[9px] bg-amber-100 text-amber-800 font-bold px-1.5 py-0.5 rounded shrink-0">Dibagikan</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-xs text-gray-600">{{ $doc->category->name ?? '-' }}</td>
                                            @if(auth()->check() && auth()->user()->isAdmin())
                                            <td class="px-5 py-3 text-xs whitespace-nowrap">
                                                @if($displayStatusColor === 'green')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                        <svg class="w-2.5 h-2.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                        ACC
                                                    </span>
                                                @elseif($displayStatusColor === 'yellow')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                                                        <svg class="w-2.5 h-2.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        Menunggu ACC
                                                    </span>
                                                @elseif($displayStatusColor === 'red')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 border border-red-300">
                                                        <svg class="w-2.5 h-2.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        Revisi
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700">
                                                        Draft
                                                    </span>
                                                @endif
                                            </td>
                                            @endif
                                            <td class="px-5 py-3 text-xs">
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $doc->visibility === 'viewer' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                                    {{ $doc->visibility_label }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-xs text-gray-600">{{ $doc->effective_display_date ? $doc->effective_display_date->format('d M Y') : '-' }}</td>
                                            <td class="px-5 py-3 text-xs text-right whitespace-nowrap">
                                                <div class="flex items-center justify-end gap-1">
                                                    @if($needsProdiAcc)
                                                    <button type="button" @click.stop="quickApproveDoc({{ json_encode($docPayload) }})" class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[11px] font-bold transition-colors shadow-xs" title="ACC Dokumen Ini">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                        <span>ACC</span>
                                                    </button>
                                                    @endif
                                                    @if($canManageDoc)
                                                    <button @click.stop="openShare('document', {{ json_encode($docPayload) }})" class="text-gray-400 hover:text-emerald-600 p-1 rounded hover:bg-gray-100" title="Bagikan">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                                    </button>
                                                    @auth
                                                    <button @click.stop="openMoveDoc('{{ $doc->uuid }}', '{{ addslashes($doc->name) }}')" class="text-gray-400 hover:text-blue-600 p-1 rounded hover:bg-gray-100" title="Pindah Folder">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                    </button>
                                                    @endauth
                                                    @else
                                                    <span class="text-[10px] text-gray-400 italic px-2 py-0.5 rounded bg-gray-50 border border-gray-100">Read Only</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="{{ auth()->check() && auth()->user()->isAdmin() ? '6' : '5' }}" class="px-5 py-8 text-center text-xs text-gray-400">Tidak ada berkas.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                @if($documents->hasPages())
                <div class="pt-4 border-t border-gray-200 mt-6">
                    {{ $documents->links() }}
                </div>
                @endif
            </main>

            <!-- Right Pane: File Inspector Drawer (Google Drive Info Panel) -->
            <aside x-show="inspectorOpen"
                   x-transition:enter="transition ease-out duration-200 transform"
                   x-transition:enter-start="translate-x-full opacity-0"
                   x-transition:enter-end="translate-x-0 opacity-100"
                   x-transition:leave="transition ease-in duration-150 transform"
                   x-transition:leave-start="translate-x-0 opacity-100"
                   x-transition:leave-end="translate-x-full opacity-0"
                   class="w-72 bg-white border-l border-gray-200 p-5 flex flex-col shrink-0 overflow-y-auto shadow-lg"
                   style="display: none;"
                   x-cloak>
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Detail Berkas</h4>
                    <button @click="inspectorOpen = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <template x-if="activeDoc">
                    <div class="space-y-4 text-xs">
                        <div class="text-center p-3 bg-gray-50 rounded-2xl border border-gray-100 overflow-hidden">
                            <template x-if="activeDoc.is_image">
                                <div class="w-full h-40 rounded-xl overflow-hidden bg-gray-200 border border-gray-200 mb-3 shadow-inner">
                                    <img :src="activeDoc.preview_url" :alt="activeDoc.name" class="w-full h-full object-cover">
                                </div>
                            </template>
                            <template x-if="!activeDoc.is_image">
                                <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mb-2.5 shadow-sm">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </div>
                            </template>
                            <h5 class="font-bold text-gray-900 text-sm truncate" x-text="activeDoc.name"></h5>
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-800 mt-1" x-text="activeDoc.visibility"></span>
                        </div>

                        <div class="space-y-2.5 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                            @if(auth()->check() && auth()->user()->isAdmin())
                            <div>
                                <span class="text-gray-400 text-[10px] block uppercase font-semibold">Status Persetujuan</span>
                                <div class="mt-1">
                                    <template x-if="activeDoc.status_color === 'green'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-xs">
                                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            <span x-text="activeDoc.status_label"></span>
                                        </span>
                                    </template>
                                    <template x-if="activeDoc.status_color === 'yellow'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 shadow-xs animate-pulse">
                                            <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span x-text="activeDoc.status_label"></span>
                                        </span>
                                    </template>
                                    <template x-if="activeDoc.status_color === 'red'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-red-100 text-red-800 border border-red-300 shadow-xs">
                                            <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            <span x-text="activeDoc.status_label"></span>
                                        </span>
                                    </template>
                                    <template x-if="activeDoc.status_color === 'gray'">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                            <span x-text="activeDoc.status_label"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                            @endif
                            <div>
                                <span class="text-gray-400 text-[10px] block uppercase font-semibold">Unit / Biro</span>
                                <span class="font-semibold text-gray-800" x-text="activeDoc.department"></span>
                            </div>
                            <div>
                                <span class="text-gray-400 text-[10px] block uppercase font-semibold">Kategori</span>
                                <span class="font-semibold text-gray-800" x-text="activeDoc.category"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-gray-400 text-[10px] block uppercase font-semibold">Tanggal Tampil (display_date)</span>
                                    <span class="font-semibold text-gray-800" x-text="activeDoc.date"></span>
                                </div>
                                @if(auth()->check() && auth()->user()->isAdmin())
                                <button type="button" @click="openEditDateModal(activeDoc)" class="text-[11px] text-[#002147] hover:underline font-bold px-2 py-0.5 rounded bg-[#002147]/5 hover:bg-[#002147]/10 transition-colors flex items-center gap-1 shrink-0" title="Ubah tanggal tampil dokumen">
                                    <svg class="w-3 h-3 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    <span>Ubah</span>
                                </button>
                                @endif
                            </div>
                            <div>
                                <span class="text-gray-400 text-[10px] block uppercase font-semibold">Ukuran Berkas</span>
                                <span class="font-semibold text-gray-800" x-text="activeDoc.file_size"></span>
                            </div>
                        </div>

                        <div>
                            <span class="text-gray-400 text-[10px] block uppercase font-semibold mb-1">Deskripsi</span>
                            <p class="text-gray-600 bg-gray-50 p-2.5 rounded-lg border border-gray-100" x-text="activeDoc.description"></p>
                        </div>

                        <!-- Action Buttons in Inspector -->
                        <div class="pt-2 space-y-2">
                            <template x-if="activeDoc.needs_acc">
                                <div class="space-y-1.5 p-2.5 bg-amber-50 rounded-xl border border-amber-200">
                                    <div class="text-[11px] font-bold text-amber-900 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Dokumen Menunggu Verifikasi
                                    </div>
                                    <button type="button" @click="quickApproveDoc(activeDoc)" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold transition-colors shadow-xs cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        ACC Dokumen (Setujui)
                                    </button>
                                    <button type="button" @click="quickRejectDoc(activeDoc)" class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white border border-red-300 hover:bg-red-50 text-red-700 rounded-lg font-semibold transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Minta Revisi / Tolak
                                    </button>
                                </div>
                            </template>
                            <template x-if="activeDoc.can_manage">
                                <button @click="openShare('document', activeDoc)" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-[#f1b500] hover:bg-[#e5a800] text-[#002147] rounded-lg font-bold transition-colors shadow-xs">
                                    <svg class="w-3.5 h-3.5 text-[#002147]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                    Bagikan Dokumen...
                                </button>
                            </template>
                            <template x-if="!activeDoc.can_manage">
                                <div class="p-2 text-center text-xs bg-amber-50 text-amber-800 rounded-lg border border-amber-200 font-medium">
                                    Dokumen milik <span class="font-bold" x-text="activeDoc.department"></span> (Hanya Baca / Read Only)
                                </div>
                            </template>
                            <a :href="activeDoc.preview_url" target="_blank" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-[#002147] hover:bg-[#001733] text-white rounded-lg font-semibold transition-colors shadow-xs">
                                <svg class="w-3.5 h-3.5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                Pratinjau Dokumen
                            </a>
                            <a :href="activeDoc.download_url" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-semibold transition-colors">
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Unduh Berkas
                            </a>
                            <template x-if="activeDoc.can_manage && activeDoc.edit_url">
                                <a :href="activeDoc.edit_url" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 text-[#002147] hover:bg-[#002147]/5 rounded-lg font-semibold transition-colors">
                                    Edit Metadata
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </aside>
        </div>

        <!-- Modal 1: Buat Folder Baru -->
        <div x-show="createFolderModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 text-center">
                <div @click="createFolderModal = false" class="fixed inset-0 bg-gray-900 bg-opacity-50"></div>
                <div class="inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full z-10 p-6 border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                        Buat Folder Baru
                    </h3>
                    <form action="{{ route('folders.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $currentFolder ? $currentFolder->id : '' }}">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Folder <span class="text-red-500">*</span></label>
                                <input type="text" name="name" required class="w-full text-xs rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Misal: Surat, Pedoman, RPS">
                            </div>
                            @if(auth()->check() && auth()->user()->isSuperAdmin() && empty($currentFolder))
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Unit / Biro Pemilik</label>
                                <select name="department" class="w-full text-xs rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Umum / Tanpa Unit</option>
                                    @foreach(\App\Models\User::UNITS as $unit)
                                        <option value="{{ $unit }}">{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Keterangan (Opsional)</label>
                                <textarea name="description" rows="2" class="w-full text-xs rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Catatan isi folder..."></textarea>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="createFolderModal = false" class="px-3.5 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-4 py-1.5 bg-[#002147] text-white rounded-lg text-xs font-semibold hover:bg-[#001733] shadow-sm">Simpan Folder</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal 2: Rename Folder -->
        <div x-show="renameFolderModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 text-center">
                <div @click="renameFolderModal = false" class="fixed inset-0 bg-gray-900 bg-opacity-50"></div>
                <div class="inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full z-10 p-6 border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-4">Ubah Nama Folder</h3>
                    <form :action="'/folders/' + editFolderId" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Folder Baru <span class="text-red-500">*</span></label>
                                <input type="text" name="name" x-model="editFolderName" required class="w-full text-xs rounded-lg border-gray-300 shadow-sm focus:border-[#002147] focus:ring-[#002147]">
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="renameFolderModal = false" class="px-3.5 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-4 py-1.5 bg-[#002147] text-white rounded-lg text-xs font-semibold hover:bg-[#001733] shadow-sm">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal 3: Pindah Dokumen ke Folder Lain -->
        <div x-show="moveDocModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 text-center">
                <div @click="moveDocModal = false" class="fixed inset-0 bg-gray-900 bg-opacity-50"></div>
                <div class="inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full z-10 p-6 border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">Pindahkan Dokumen</h3>
                    <p class="text-xs text-gray-500 mb-4 truncate" x-text="moveDocName"></p>
                    <form :action="'/documents/' + moveDocId + '/move'" method="POST">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Pilih Folder Tujuan</label>
                                <select name="folder_id" class="w-full text-xs rounded-lg border-gray-300 shadow-sm focus:border-[#002147] focus:ring-[#002147]">
                                    <option value="">Root / Tanpa Folder</option>
                                    @foreach($allAccessibleFolders as $f)
                                        <option value="{{ $f->id }}">{{ $f->department ? "[{$f->department}] " : "" }}{{ $f->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="moveDocModal = false" class="px-3.5 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-4 py-1.5 bg-[#002147] text-white rounded-lg text-xs font-semibold hover:bg-[#001733] shadow-sm">Pindahkan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Custom Right-Click Context Menu (Google Drive / Windows 11 style) -->
        <div x-show="contextMenu.show"
             @click.outside="closeContextMenu()"
             :style="`position: fixed; left: ${contextMenu.x}px; top: ${contextMenu.y}px; z-index: 9999;`"
             class="bg-white/95 backdrop-blur-md rounded-xl shadow-2xl border border-gray-200 py-1.5 min-w-[210px] text-xs font-medium divide-y divide-gray-100 select-none animate-in fade-in zoom-in-95 duration-75"
             style="display: none;"
             x-cloak>

            <!-- Folder Context Menu -->
            <template x-if="contextMenu.type === 'folder' && contextMenu.item">
                <div>
                    <div class="px-3.5 py-1.5 text-[11px] font-bold text-gray-400 uppercase tracking-wider truncate flex items-center gap-1.5 bg-gray-50/50">
                        <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                        <span class="truncate" x-text="contextMenu.item.name"></span>
                    </div>

                    <div class="py-1">
                        <a :href="contextMenu.item.url" @click="closeContextMenu()" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                            <span>Buka Folder</span>
                        </a>

                        <template x-if="contextMenu.item.can_manage">
                            <button @click="openShare('folder', contextMenu.item)" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                <span>Bagikan Folder...</span>
                            </button>
                        </template>

                        <template x-if="!contextMenu.item.can_manage">
                            <div class="px-3.5 py-1.5 text-amber-800 bg-amber-50 text-[11px] italic flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span>Folder Dibagikan (Read Only)</span>
                            </div>
                        </template>
                    </div>

                    @auth
                    <template x-if="contextMenu.item.can_manage">
                    <div class="py-1">
                        <button @click="openRenameFolder(contextMenu.item.id, contextMenu.item.name); closeContextMenu();" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            <span>Ganti Nama</span>
                        </button>

                        <form :action="'/folders/' + contextMenu.item.id" method="POST" onsubmit="return confirm('Anda yakin ingin menghapus folder ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full text-left px-3.5 py-2 text-red-600 hover:bg-red-50 flex items-center gap-2.5 transition-colors">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                <span>Hapus Folder</span>
                            </button>
                        </form>
                    </div>
                    </template>
                    @endauth
                </div>
            </template>

            <!-- Document Context Menu -->
            <template x-if="contextMenu.type === 'document' && contextMenu.item">
                <div>
                    <div class="px-3.5 py-1.5 text-[11px] font-bold text-gray-400 uppercase tracking-wider truncate flex items-center gap-1.5 bg-gray-50/50">
                        <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span class="truncate" x-text="contextMenu.item.name"></span>
                    </div>

                    @if(auth()->check() && (auth()->user()->isAdminProdi() || auth()->user()->isSuperAdmin()))
                    <template x-if="contextMenu.item.needs_acc">
                        <div class="py-1 bg-amber-50/70 border-b border-amber-200">
                            <button type="button" @click="quickApproveDoc(contextMenu.item); closeContextMenu();" class="w-full text-left px-3.5 py-2 text-emerald-800 hover:bg-emerald-100 flex items-center gap-2.5 font-bold transition-colors cursor-pointer">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <span>ACC Dokumen (Setujui)</span>
                            </button>
                            <button type="button" @click="quickRejectDoc(contextMenu.item); closeContextMenu();" class="w-full text-left px-3.5 py-2 text-red-700 hover:bg-red-100 flex items-center gap-2.5 font-medium transition-colors cursor-pointer">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Minta Revisi / Tolak...</span>
                            </button>
                        </div>
                    </template>
                    @endif

                    <div class="py-1">
                        <a :href="contextMenu.item.preview_url" target="_blank" @click="closeContextMenu()" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span>Pratinjau (Preview)</span>
                        </a>

                        <template x-if="contextMenu.item.can_manage">
                            <button @click="openShare('document', contextMenu.item)" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                <span>Bagikan Dokumen...</span>
                            </button>
                        </template>

                        <template x-if="!contextMenu.item.can_manage">
                            <div class="px-3.5 py-1.5 text-amber-800 bg-amber-50 text-[11px] italic flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span>Dokumen Dibagikan (Read Only)</span>
                            </div>
                        </template>

                        <template x-if="contextMenu.item.is_downloadable">
                            <a :href="contextMenu.item.download_url" @click="closeContextMenu()" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                <span>Unduh Berkas</span>
                            </a>
                        </template>
                    </div>

                    @auth
                    <template x-if="contextMenu.item.can_manage">
                    <div class="py-1">
                        @if(auth()->check() && auth()->user()->isAdmin())
                        <button @click="openEditDateModal(contextMenu.item)" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-[#002147]/5 hover:text-[#002147] flex items-center gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span>Ubah Tanggal Tampil...</span>
                        </button>
                        @endif

                        <button @click="openMoveDoc(contextMenu.item.uuid || contextMenu.item.id, contextMenu.item.name); closeContextMenu();" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            <span>Pindah ke Folder...</span>
                        </button>

                        <template x-if="contextMenu.item.edit_url">
                            <a :href="contextMenu.item.edit_url" @click="closeContextMenu()" class="w-full text-left px-3.5 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2.5 transition-colors">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                <span>Edit Metadata</span>
                            </a>
                        </template>

                        <form :action="'/documents/' + (contextMenu.item.uuid || contextMenu.item.id)" method="POST" onsubmit="return confirm('Anda yakin ingin menghapus dokumen ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full text-left px-3.5 py-2 text-red-600 hover:bg-red-50 flex items-center gap-2.5 transition-colors">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                <span>Hapus Dokumen</span>
                            </button>
                        </form>
                    </div>
                    </template>
                    @endauth
                </div>
            </template>
        </div>

        <!-- Modal Bagikan & Hak Akses Biro (Share Modal) -->
        <div x-show="shareModal.open" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 text-center">
                <div @click="closeShareModal()" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"></div>

                <div class="inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-lg sm:w-full z-10 p-6 border border-gray-100">
                    <!-- Header Modal -->
                    <div class="flex items-start justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-gray-900">Bagikan &amp; Atur Akses Biro</h3>
                                <p class="text-xs text-gray-500 truncate" x-text="shareModal.type === 'folder' ? 'Folder: ' + shareModal.title : 'Dokumen: ' + shareModal.title"></p>
                            </div>
                        </div>
                        <button @click="closeShareModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Body Modal -->
                    <div class="py-4 space-y-4 text-xs">

                        <!-- 1. Section: Pilih Biro / Unit yang Diberikan Izin Akses -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="font-bold text-gray-800 text-xs flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    Biro / Unit yang Diberikan Izin Akses
                                </label>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <button type="button" @click="selectAllBiros()" class="text-blue-600 hover:underline font-semibold">Pilih Semua</button>
                                    <span class="text-gray-300">&bull;</span>
                                    <button type="button" @click="deselectAllBiros()" class="text-gray-500 hover:underline">Hapus Semua</button>
                                </div>
                            </div>

                            <!-- List Checklist Semua Biro / Unit -->
                            <div class="max-h-52 overflow-y-auto border border-gray-200 rounded-xl p-2.5 bg-gray-50/50 space-y-1 divide-y divide-gray-100">
                                <template x-for="unit in allUnits" :key="unit">
                                    <label class="flex items-center justify-between p-2 rounded-lg hover:bg-white transition-colors cursor-pointer"
                                           :class="shareModal.selectedBiros.includes(unit) ? 'bg-blue-50/60 font-semibold text-blue-900' : 'text-gray-700'">
                                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                            <input type="checkbox"
                                                   :value="unit"
                                                   x-model="shareModal.selectedBiros"
                                                   @change="autoSavePermissions()"
                                                   class="rounded text-blue-600 focus:ring-blue-500 border-gray-300 h-4 w-4">
                                            <span class="truncate text-xs" x-text="unit"></span>
                                        </div>
                                        <template x-if="shareModal.department === unit || (shareModal.department === 'PSTI' && (unit === 'Program Studi Teknologi Informasi' || unit === 'Program Studi PSTI')) || (shareModal.department === 'Program Studi Teknologi Informasi' && (unit === 'Program Studi Teknologi Informasi' || unit === 'PSTI'))">
                                            <span class="shrink-0 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">Pemilik</span>
                                        </template>
                                    </label>
                                </template>
                            </div>

                            <!-- Save Permissions Button & Auto-save Status Feedback -->
                            <div class="mt-2.5 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap min-w-0">
                                    <p class="text-[11px] text-gray-500">
                                        <span x-text="shareModal.selectedBiros.length"></span> dari <span x-text="allUnits.length"></span> unit dipilih.
                                    </p>
                                    <template x-if="shareModal.saveStatus === 'saving'">
                                        <span class="inline-flex items-center gap-1 text-[10px] text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                            <svg class="animate-spin h-2.5 w-2.5 text-amber-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Menyimpan...
                                        </span>
                                    </template>
                                    <template x-if="shareModal.saveStatus === 'saved'">
                                        <span class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Tersimpan otomatis
                                        </span>
                                    </template>
                                </div>
                                <button type="button" @click="savePermissions(false)" :disabled="shareModal.isSaving" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-semibold text-xs transition-colors flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span x-text="shareModal.isSaving ? 'Menyimpan...' : 'Simpan Izin Akses'"></span>
                                </button>
                            </div>
                        </div>

                        <!-- 2. Warning Note -->
                        <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-800 flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <span>Unit yang <strong>tidak dicentang</strong> akan otomatis ditolak (403 Forbidden) saat membuka tautan atau berkas ini.</span>
                        </div>

                        <!-- 3. Section: Tautan Langsung -->
                        <div class="pt-2 border-t border-gray-100">
                            <label class="block font-semibold text-gray-700 mb-1.5">Tautan Langsung</label>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly :value="shareModal.url" class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-700 select-all font-mono">
                                <button @click="copyShareLink()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shrink-0 transition-colors flex items-center gap-1.5 shadow-sm">
                                    <template x-if="!shareModal.copied">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            <span>Salin</span>
                                        </div>
                                    </template>
                                    <template x-if="shareModal.copied">
                                        <div class="flex items-center gap-1 text-green-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Tersalin!</span>
                                        </div>
                                    </template>
                                </button>
                            </div>
                        </div>

                        <!-- 4. Section: Bagikan Langsung Ke WhatsApp & Email -->
                        <div>
                            <div class="grid grid-cols-2 gap-2.5">
                                <button @click="shareViaWhatsApp()" class="flex items-center justify-center gap-2 p-2.5 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-semibold transition-colors shadow-sm">
                                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824z"/></svg>
                                    WhatsApp
                                </button>

                                <button @click="shareViaEmail()" class="flex items-center justify-center gap-2 p-2.5 rounded-xl border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition-colors shadow-sm">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    Email
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Footer Modal -->
                    <div class="pt-3 border-t border-gray-100 flex justify-end">
                        <button type="button" @click="closeShareModal()" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Ubah Tanggal Tampil (display_date) -->
        <div x-show="editDateModal.open" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 text-center">
                <div @click="editDateModal.open = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"></div>
                <div class="inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full z-10 p-6 border border-gray-100">
                    <div class="flex items-center gap-3 pb-3 border-b border-gray-100 mb-4">
                        <div class="w-9 h-9 rounded-xl bg-[#002147]/10 text-[#002147] flex items-center justify-center font-bold">
                            <svg class="w-5 h-5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-[#002147]">Ubah Tanggal Tampil (display_date)</h3>
                            <p class="text-xs text-gray-500 truncate" x-text="editDateModal.name"></p>
                        </div>
                    </div>

                    <form @submit.prevent="saveDisplayDate()">
                        <div class="space-y-3 text-xs">
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Tanggal Tampil untuk Pengguna / Viewer <span class="text-red-500">*</span></label>
                                <input type="date" x-model="editDateModal.newDate" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
                                <p class="font-semibold text-slate-800">Aturan Logika Tanggal:</p>
                                <p>&bull; <strong>Viewer/Pengguna</strong> hanya melihat tanggal tampil (<code class="text-[#002147] font-semibold">display_date</code>) ini.</p>
                                <p>&bull; Tidak ada label "diperbarui" di halaman viewer.</p>
                                <p>&bull; Tanggal asli upload sistem (<code class="text-slate-500">actual_uploaded_at</code>) tetap aman tersimpan untuk audit administrasi.</p>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="editDateModal.open = false" class="px-3.5 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="editDateModal.isSaving" class="px-4 py-2 bg-[#002147] text-white rounded-lg text-xs font-bold hover:bg-[#001733] shadow-xs flex items-center gap-1.5 disabled:opacity-50">
                                <span x-text="editDateModal.isSaving ? 'Menyimpan...' : 'Simpan Tanggal'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-explorer-layout>
