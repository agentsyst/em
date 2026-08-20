<?php
session_start();
require 'env_helper.php';
require 'config.php';

if (!isInstalled()) {
    header("Location: install.php");
    exit;
}

if (!isset($_SESSION['client_logged_in'])) {
    header("Location: login.php");
    exit;
}

$env = getEnvData();
$username = $env['USERNAME'];

$data_file = __DIR__ . '/data_' . md5($username) . '.json';
$dashboard_data = null;
if (file_exists($data_file)) {
    $dashboard_data = json_decode(file_get_contents($data_file), true);
}

$devices = $dashboard_data['devices'] ?? [];
$activities = $dashboard_data['activities'] ?? [];
$stats = $dashboard_data['stats'] ?? ['activeCount' => 0, 'lockedCount' => 0, 'totalDevices' => 0];
$activeCount = $stats['activeCount'];
$lockedCount = $stats['lockedCount'];

function clientApiCall($action, $postData = []) {
    $postData['action'] = $action;
    $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    if (isset($data['error']) && $data['error'] === 'EXPIRED_OR_UNPAID') {
        header("Location: renew.php");
        exit;
    }
    return $data;
}

function autoSyncData() {
    global $data_file;
    $sync = clientApiCall('client_get_dashboard_data');
    if (isset($sync['status']) && $sync['status'] === 'success') {
        file_put_contents($data_file, json_encode($sync));
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['refresh_data'])) {
        $json_resp = clientApiCall('client_get_dashboard_data');
        if (isset($json_resp['status']) && $json_resp['status'] === 'success') {
            file_put_contents($data_file, json_encode($json_resp));
            $message = "Data berhasil disinkronisasi!";
        } else {
            $error = "Gagal mensinkronisasi data: " . ($json_resp['error'] ?? 'Unknown error');
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (isset($_POST['add_device'])) {
        $res = clientApiCall('client_add_device', [
            'device_id' => $_POST['device_id'],
            'device_name' => $_POST['device_name'],
            'api_key' => $_POST['api_key'],
            'status' => $_POST['status']
        ]);
        if (isset($res['status']) && $res['status'] === 'success') {
            $message = "Perangkat berhasil ditambahkan!";
            autoSyncData();
        } else {
            $error = $res['error'] ?? 'Gagal menambahkan perangkat.';
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (isset($_POST['edit_device'])) {
        $postData = [
            'id' => $_POST['id'],
            'device_id' => $_POST['device_id'] ?? '',
            'device_name' => $_POST['device_name'],
            'status' => $_POST['status'],
            'sync_interval' => (int)$_POST['sync_interval'],
            'log_activity_enabled' => isset($_POST['log_activity_enabled']) ? (int)$_POST['log_activity_enabled'] : 0
        ];
        if (!empty($_POST['api_key'])) {
            $postData['new_api_key'] = $_POST['api_key'];
        }
        $res = clientApiCall('client_update_device', $postData);
        if (isset($res['status']) && $res['status'] === 'success') {
            $message = "Perangkat berhasil diperbarui!";
            autoSyncData();
        } else {
            $error = $res['error'] ?? 'Gagal memperbarui perangkat.';
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (isset($_POST['delete_device'])) {
        $res = clientApiCall('client_delete_device', ['id' => $_POST['id']]);
        if (isset($res['status']) && $res['status'] === 'success') {
            $message = "Perangkat berhasil dihapus!";
            autoSyncData();
        } else {
            $error = $res['error'] ?? 'Gagal menghapus perangkat.';
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (isset($_POST['toggle_status'])) {
        $postData = [
            'id' => $_POST['id'],
            'new_status' => $_POST['new_status']
        ];
        if ($_POST['new_status'] === 'locked') {
            $postData['lock_message'] = $_POST['lock_message'] ?? '';
            $postData['lock_duration'] = $_POST['lock_duration'] ?? 0;
        }
        $res = clientApiCall('client_toggle_status', $postData);
        if (isset($res['status']) && $res['status'] === 'success') {
            $message = "Status perangkat berhasil diubah!";
            autoSyncData();
        } else {
            $error = $res['error'] ?? 'Gagal mengubah status.';
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (isset($_POST['reset_binding'])) {
        $res = clientApiCall('client_reset_binding', ['id' => $_POST['id']]);
        if (isset($res['status']) && $res['status'] === 'success') {
            $message = "Binding perangkat berhasil direset!";
            autoSyncData();
        } else {
            $error = $res['error'] ?? 'Gagal mereset binding.';
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

if (isset($_SESSION['force_refresh'])) {
    unset($_SESSION['force_refresh']);
    $json_resp = clientApiCall('client_get_dashboard_data');
    if (isset($json_resp['status']) && $json_resp['status'] === 'success') {
        file_put_contents($data_file, json_encode($json_resp));
        $dashboard_data = $json_resp;
        $devices = $dashboard_data['devices'] ?? [];
        $activities = $dashboard_data['activities'] ?? [];
        $stats = $dashboard_data['stats'] ?? ['activeCount' => 0, 'lockedCount' => 0, 'totalDevices' => 0];
        $activeCount = $stats['activeCount'];
        $lockedCount = $stats['lockedCount'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>AgentSys - Device Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <style>
        .tab-content { display: none; }
        .tab-content.block { display: block; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .modal-inactive { pointer-events: none; opacity: 0; }
        .modal-content-inactive { transform: scale(0.95); opacity: 0; }
        .btn-action { @apply p-2 rounded-lg transition-all duration-200 hover:scale-110 active:scale-95; }
        
        .dark body { background-color: #0f172a; color: #f1f5f9; }
        .dark .bg-white { background-color: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        .dark .bg-slate-50 { background-color: #0f172a !important; border-color: #1e293b !important; }
        .dark .bg-slate-200 { background-color: #334155 !important; }
        .dark .hover\:bg-slate-100:hover { background-color: #334155 !important; }
        .dark .hover\:bg-slate-200:hover { background-color: #475569 !important; }
        .dark .text-slate-800, .dark .text-slate-900 { color: #f8fafc !important; }
        .dark .text-slate-700, .dark .text-slate-600 { color: #e2e8f0 !important; }
        .dark .text-slate-500 { color: #94a3b8 !important; }
        .dark .border-slate-200, .dark .border-slate-300, .dark .border-slate-100 { border-color: #334155 !important; }
        .dark input, .dark select, .dark textarea { background-color: #0f172a !important; color: #f1f5f9 !important; border-color: #334155 !important; }
        .dark table th { background-color: #1e293b !important; color: #cbd5e1 !important; border-bottom-color: #334155 !important; }
        .dark table td { border-bottom-color: #334155 !important; }
        .dark tr:hover { background-color: #334155 !important; }
        .dark tr.hover\:bg-slate-50:hover { background-color: #334155 !important; }
        .dark .bg-emerald-50 { background-color: rgba(16, 185, 129, 0.1) !important; color: #34d399 !important; }
        .dark .bg-rose-50 { background-color: rgba(244, 63, 94, 0.1) !important; color: #fb7185 !important; }
        .dark .bg-blue-50, .dark .bg-indigo-50 { background-color: rgba(59, 130, 246, 0.1) !important; color: #60a5fa !important; }
        .dark .bg-amber-50 { background-color: rgba(245, 158, 11, 0.1) !important; color: #fbbf24 !important; }
        .dark .bg-slate-100 { background-color: #334155 !important; color: #cbd5e1 !important; }
        .dark .border-blue-500 { border-color: #3b82f6 !important; }
        .dark .text-blue-600 { color: #60a5fa !important; }
        .dark .bg-gradient-to-r.from-blue-600.to-indigo-700 { background-image: linear-gradient(to right, #1e3a8a, #312e81) !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 transition-colors duration-300">

    <nav class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-2 font-bold text-xl">
                <i class='bx bx-cloud-lightning'></i> AgentSys
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" class="flex h-10 m-0">
                    <button type="submit" name="refresh_data" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm transition-colors flex items-center justify-center gap-2 backdrop-blur-sm h-full">
                        <i class='bx bx-refresh text-lg'></i> <span class="hidden sm:inline">Sync Data</span>
                    </button>
                </form>
                <button id="theme-toggle" class="bg-white/20 hover:bg-white/30 px-3 py-2 rounded-lg text-sm transition-colors flex items-center justify-center backdrop-blur-sm h-10">
                    <i id="theme-icon" class='bx bx-moon text-lg'></i>
                </button>
                <span class="text-sm font-medium hidden sm:flex items-center h-10"><?= htmlspecialchars($username) ?></span>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm transition-colors flex items-center justify-center gap-2 h-10 backdrop-blur-sm"><i class='bx bx-log-out text-lg'></i> <span class="hidden sm:inline">Logout</span></a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <?php if ($message): ?>
            <div class="bg-emerald-50 text-emerald-700 p-4 rounded-lg mb-6 border border-emerald-200 shadow-sm flex items-center gap-3">
                <i class='bx bx-check-circle text-xl'></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-700 p-4 rounded-lg mb-6 border border-rose-200 shadow-sm flex items-center gap-3">
                <i class='bx bx-error-circle text-xl'></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$dashboard_data): ?>
            <div class="bg-white p-8 rounded-xl border border-slate-200 text-center shadow-sm">
                <i class='bx bx-cloud-download text-5xl text-slate-300 mb-3 block'></i>
                <h3 class="text-lg font-bold text-slate-700 mb-2">Belum ada data lokal</h3>
                <p class="text-slate-500 mb-4">Silakan klik tombol <b>Sync Data</b> di atas untuk mengambil data terbaru.</p>
            </div>
        <?php else: ?>

        <div class="border-b border-slate-200 mb-6 flex overflow-x-auto no-scrollbar">
            <nav class="-mb-px flex space-x-1 sm:space-x-4" aria-label="Tabs">
                <button id="btn-tab-dashboard" onclick="switchTab('dashboard')" class="tab-btn whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm transition-all duration-200 border-blue-500 text-blue-600">
                    <i class='bx bx-grid-alt mr-1'></i><span class="hidden sm:inline">Dashboard</span><span class="sm:hidden">Home</span>
                </button>
                <button id="btn-tab-device" onclick="switchTab('device')" class="tab-btn whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm transition-all duration-200 border-transparent text-slate-500 hover:text-slate-700">
                    <i class='bx bx-desktop mr-1'></i><span class="hidden sm:inline">Devices</span><span class="sm:hidden">Dev</span>
                </button>
                <button id="btn-tab-activity" onclick="switchTab('activity')" class="tab-btn whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm transition-all duration-200 border-transparent text-slate-500 hover:text-slate-700">
                    <i class='bx bx-list-ul mr-1'></i><span class="hidden sm:inline">Device Activity</span><span class="sm:hidden">Log</span>
                </button>
            </nav>
        </div>

        <div id="tab-dashboard" class="tab-content block">
            <h1 class="text-2xl font-bold text-slate-900 mb-6">Dashboard Analytics</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-xl flex items-center justify-center text-2xl shadow-lg shadow-blue-500/30"><i class='bx bx-devices'></i></div>
                    <div><p class="text-sm text-slate-500 font-medium">Total Perangkat</p><h3 class="text-2xl font-bold text-slate-800"><?= count($devices) ?></h3></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 text-white rounded-xl flex items-center justify-center text-2xl shadow-lg shadow-emerald-500/30"><i class='bx bx-check-circle'></i></div>
                    <div><p class="text-sm text-slate-500 font-medium">Aktif</p><h3 class="text-2xl font-bold text-slate-800"><?= $activeCount ?></h3></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center gap-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 text-white rounded-xl flex items-center justify-center text-2xl shadow-lg shadow-rose-500/30"><i class='bx bx-lock-alt'></i></div>
                    <div><p class="text-sm text-slate-500 font-medium">Terkunci</p><h3 class="text-2xl font-bold text-slate-800"><?= $lockedCount ?></h3></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i class='bx bx-cloud text-blue-500'></i> Kuota Jaringan</h2>
                    <button onclick="loadMeteredQuota()" class="text-xs text-blue-600 hover:underline flex items-center gap-1"><i class='bx bx-refresh'></i> Refresh</button>
                </div>
                <div id="quotaContent">
                    <div class="flex items-center justify-center py-8 text-slate-400">
                        <i class='bx bx-loader-alt bx-spin text-2xl mr-2'></i> Memuat data...
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-device" class="tab-content hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Manajemen Perangkat</h1>
                    <p class="text-slate-500 mt-1 text-sm">Kontrol dan pantau agen PC maupun Mobile Anda.</p>
                </div>
                <button onclick="openModal('addModal')" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-5 py-2.5 rounded-lg shadow-lg shadow-blue-500/30 transition-all active:scale-95 flex items-center gap-2 font-medium">
                    <i class='bx bx-plus'></i> Tambah Perangkat
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 px-6 pt-6 gap-4">
                    <h3 class="text-lg font-bold text-slate-800 hidden sm:block">Daftar Perangkat</h3>
                    <div class="relative w-full sm:w-auto">
                        <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400'></i>
                        <input type="text" id="search-devices" onkeyup="filterTable('table-devices', this.value)" class="pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none w-full sm:w-64" placeholder="Cari perangkat...">
                    </div>
                </div>
                <div class="overflow-x-auto border-t border-slate-200">
                    <table id="table-devices" class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">ID / Device ID</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama Perangkat</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Terakhir Sync</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php foreach ($devices as $row): ?>
                            <?php
                            $isOwner = ($row['role'] ?? '') !== 'shared';
                            $perms = $row['permissions'] ?? [];
                            if (is_string($perms)) $perms = json_decode($perms, true) ?: [];
                            $can_lock = $isOwner || !empty($perms['can_lock']) || !empty($row['can_lock']);
                            $can_front = $isOwner || !empty($perms['can_front_cam']) || !empty($row['can_front_cam']);
                            $can_back = $isOwner || !empty($perms['can_back_cam']) || !empty($row['can_back_cam']);
                            $can_screen = $isOwner || !empty($perms['can_screen_share']) || !empty($row['can_screen_share']);
                            $can_live = $can_front || $can_back || $can_screen;
                            $perm_json = json_encode(['can_front_cam' => $can_front, 'can_back_cam' => $can_back, 'can_screen_share' => $can_screen]);
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="text-sm text-slate-500 block">ID: #<?= $row['id'] ?></span>
                                    <span class="font-medium text-slate-900 block"><?= htmlspecialchars($row['device_id']) ?></span>
                                    <?php if (!empty($row['hardware_id'])): ?>
                                        <span class="text-xs text-emerald-600 flex items-center gap-1 mt-1"><i class='bx bx-link'></i> Terikat</span>
                                    <?php else: ?>
                                        <span class="text-xs text-amber-500 flex items-center gap-1 mt-1"><i class='bx bx-unlink'></i> Belum Terikat</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-slate-800"><?= htmlspecialchars($row['device_name']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500">
                                    <?= $row['last_sync'] ? date('d M Y, H:i', strtotime($row['last_sync'])) : 'Belum Pernah' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($row['status'] == 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200"><i class='bx bx-check-circle'></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200"><i class='bx bx-lock-alt'></i> Terkunci</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right space-x-1">
                                    <?php if($can_lock): ?>
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <?php if ($row['status'] == 'locked'): ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button type="submit" name="toggle_status" class="btn-action text-emerald-600 bg-emerald-50" title="Buka Kunci"><i class='bx bx-lock-open-alt text-lg'></i></button>
                                        <?php else: ?>
                                            <button type="button" onclick="openLockModal(<?= $row['id'] ?>)" class="btn-action text-rose-600 bg-rose-50" title="Kunci"><i class='bx bx-lock-alt text-lg'></i></button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>
                                    <?php if($can_live): ?>
                                    <?php 
                                    $active_user = $row['live_active_user'] ?? '';
                                    if (!empty($active_user)): 
                                    ?>
                                    <button type="button" disabled class="btn-action text-slate-400 bg-slate-100 cursor-not-allowed" title="Sedang digunakan oleh <?= htmlspecialchars($active_user) ?>"><i class='bx bx-video-off text-lg'></i></button>
                                    <?php else: ?>
                                    <button type="button" onclick="openLiveModal('<?= htmlspecialchars($row['device_id']) ?>', <?= $isOwner ? 'true' : 'false' ?>, '<?= htmlspecialchars($perm_json) ?>')" class="btn-action text-indigo-600 bg-indigo-50" title="Live Stream"><i class='bx bx-video text-lg'></i></button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if($isOwner): ?>
                                    <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['device_id']) ?>', '<?= htmlspecialchars($row['device_name']) ?>', '<?= $row['status'] ?>', <?= (int)($row['sync_interval'] ?? 5) ?>, <?= (int)($row['log_activity_enabled'] ?? 1) ?>)" class="btn-action text-blue-600 bg-blue-50" title="Edit"><i class='bx bx-edit text-lg'></i></button>
                                    <form method="POST" class="inline-block" onsubmit="return swalConfirm(event, this, 'Reset Binding?', 'Ikatan perangkat ini akan direset.', 'warning');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="reset_binding" value="1">
                                        <button type="submit" class="btn-action text-amber-600 bg-amber-50" title="Reset Binding"><i class='bx bx-unlink text-lg'></i></button>
                                    </form>
                                    <form method="POST" class="inline-block" onsubmit="return swalConfirm(event, this, 'Hapus Perangkat?', 'Data perangkat ini akan dihapus secara permanen.', 'error');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="delete_device" value="1">
                                        <button type="submit" class="btn-action text-slate-500 bg-slate-100 hover:text-rose-600" title="Hapus"><i class='bx bx-trash text-lg'></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($devices) === 0): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500"><i class='bx bx-folder-open text-4xl mb-3 block'></i>Belum ada perangkat yang ditambahkan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-activity" class="tab-content hidden">
            <h1 class="text-2xl font-bold text-slate-900 mb-6">Device Activity Log</h1>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 px-6 pt-6 gap-4">
                    <h3 class="text-lg font-bold text-slate-800 hidden sm:block">Log Aktivitas</h3>
                    <div class="relative w-full sm:w-auto">
                        <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400'></i>
                        <input type="text" id="search-activities" onkeyup="filterTable('table-activities', this.value)" class="pl-9 pr-4 py-2 text-sm rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none w-full sm:w-64" placeholder="Cari aktivitas...">
                    </div>
                </div>
                <div class="overflow-x-auto border-t border-slate-200" style="max-height: 600px; overflow-y: auto;">
                    <table id="table-activities" class="w-full text-left">
                        <thead class="bg-slate-50 sticky top-0 border-b border-slate-200 z-10">
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider w-44">Waktu</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider w-40">Device</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider w-56">Aktivitas</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm">
                            <?php if(count($activities) === 0): ?>
                            <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">Belum ada aktivitas tercatat.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($activities as $act): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-3 whitespace-nowrap text-slate-500"><?= date('d M Y, H:i', strtotime($act['created_at'])) ?></td>
                                <td class="px-6 py-3 font-medium text-slate-800"><?= htmlspecialchars($act['device_name'] ?? '-') ?></td>
                                <td class="px-6 py-3 text-slate-800"><?= htmlspecialchars($act['activity_name'] ?? '-') ?></td>
                                <td class="px-6 py-3 text-slate-500"><?= htmlspecialchars($act['details'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center modal-inactive transition-all duration-300">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('addModal')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 relative z-10 modal-content-inactive transition-all duration-300">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Tambah Perangkat</h3>
                <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i class='bx bx-x text-2xl'></i></button>
            </div>
            <form method="POST" class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Device ID (Unik)</label>
                        <div class="flex gap-2">
                            <input type="text" name="device_id" id="add_device_id" required placeholder="Contoh: DEV-001" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <button type="button" onclick="generateRandomId('add_device_id')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg" title="Generate Acak"><i class='bx bx-refresh text-lg'></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Perangkat</label>
                        <input type="text" name="device_name" required placeholder="Contoh: PC Lobi" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status Awal</label>
                        <select name="status" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="active">Aktif</option>
                            <option value="locked">Terkunci</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">API Key Rahasia</label>
                        <div class="flex gap-2">
                            <input type="text" name="api_key" id="add_api_key" required placeholder="Buat kode rahasia" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <button type="button" onclick="generateRandomApiKey('add_api_key')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg" title="Generate Acak"><i class='bx bx-refresh text-lg'></i></button>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('addModal')" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Batal</button>
                    <button type="submit" name="add_device" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-md shadow-blue-500/30">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center modal-inactive transition-all duration-300">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('editModal')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 relative z-10 modal-content-inactive transition-all duration-300">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Edit Perangkat</h3>
                <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class='bx bx-x text-2xl'></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="id" id="edit_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Device ID</label>
                        <input type="text" name="device_id" id="edit_device_id" readonly required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Perangkat</label>
                        <input type="text" name="device_name" id="edit_device_name" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" id="edit_status" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">
                            <option value="active">Aktif</option>
                            <option value="locked">Terkunci</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Interval Sync (detik)</label>
                        <input type="number" name="sync_interval" id="edit_sync_interval" value="5" min="1" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Kirim Log Aktivitas</label>
                        <select name="log_activity_enabled" id="edit_log_activity_enabled" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">API Key Baru (Opsional)</label>
                        <div class="flex gap-2">
                            <input type="text" name="api_key" id="edit_api_key" placeholder="Kosongkan jika tidak ingin diubah" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">
                            <button type="button" onclick="generateRandomApiKey('edit_api_key')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg"><i class='bx bx-refresh text-lg'></i></button>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('editModal')" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium">Batal</button>
                    <button type="submit" name="edit_device" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-md shadow-blue-500/30">Perbarui</button>
                </div>
            </form>
        </div>
    </div>

    <div id="lockModal" class="fixed inset-0 z-50 flex items-center justify-center modal-inactive transition-all duration-300">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('lockModal')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 relative z-10 modal-content-inactive transition-all duration-300">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-rose-600 flex items-center gap-2"><i class='bx bx-lock-alt'></i> Kunci Perangkat</h3>
                <button onclick="closeModal('lockModal')" class="text-slate-400 hover:text-slate-600"><i class='bx bx-x text-2xl'></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="id" id="lock_device_id">
                <input type="hidden" name="new_status" value="locked">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pesan Kunci</label>
                        <textarea name="lock_message" rows="3" placeholder="Pesan yang ditampilkan di perangkat" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">Perangkat ini dikunci oleh pemilik.</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Durasi Kunci (menit, 0 = permanen)</label>
                        <input type="number" name="lock_duration" value="0" min="0" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('lockModal')" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium">Batal</button>
                    <button type="submit" name="toggle_status" class="px-5 py-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-medium shadow-md shadow-rose-500/30">Kunci Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <div id="liveModal" class="fixed inset-0 z-50 flex items-center justify-center hidden transition-all duration-300">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" onclick="closeLiveModal()"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl mx-4 relative z-10 transition-all duration-300">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Live Streaming & Screen Share</h3>
                <button onclick="closeLiveModal()" class="text-slate-400 hover:text-slate-600"><i class='bx bx-x text-2xl'></i></button>
            </div>
            <div class="p-6">
                <div class="flex flex-col gap-4">
                    <div class="w-full">
                        <div class="flex gap-2 mb-4 justify-center flex-wrap">
                            <button id="btn_front_cam" onclick="startStream('front_cam')" class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 disabled:opacity-50 transition-colors" disabled><i class='bx bx-camera mr-1'></i> Kamera Depan</button>
                            <button id="btn_back_cam" onclick="startStream('back_cam')" class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 disabled:opacity-50 transition-colors" disabled><i class='bx bx-camera-movie mr-1'></i> Kamera Belakang</button>
                            <button id="btn_screen_share" onclick="confirmScreenShare()" class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors"><i class='bx bx-desktop mr-1'></i> Screen Share</button>
                            <button onclick="stopStream()" class="px-4 py-2 bg-rose-100 text-rose-700 rounded-lg hover:bg-rose-200 transition-colors"><i class='bx bx-stop-circle mr-1'></i> Stop</button>
                        </div>
                        <div id="video-container" class="bg-black w-full rounded-lg overflow-hidden aspect-video relative flex items-center justify-center">
                            <video id="remoteVideo" autoplay playsinline class="w-full h-full object-contain"></video>
                            <button id="btn-fullscreen" onclick="toggleFullscreen()" class="absolute top-4 right-4 bg-black/60 hover:bg-black/80 text-white w-10 h-10 flex items-center justify-center rounded-lg backdrop-blur-sm transition-all z-20">
                                <i class='bx bx-fullscreen text-2xl'></i>
                            </button>
                            <button id="btn-exit-fullscreen" onclick="toggleFullscreen()" class="absolute top-6 right-6 bg-rose-600/90 hover:bg-rose-600 text-white w-12 h-12 flex items-center justify-center rounded-full shadow-lg backdrop-blur-sm transition-all z-20 hidden">
                                <i class='bx bx-x text-3xl'></i>
                            </button>
                            <div id="liveLoading" class="absolute inset-0 flex items-center justify-center bg-black/50 text-white hidden z-10">
                                <i class='bx bx-loader-alt bx-spin text-4xl'></i>
                            </div>
                        </div>
                    </div>
                    <div class="w-full flex flex-col bg-slate-900 rounded-lg overflow-hidden border border-slate-700" style="max-height: 200px;">
                        <div class="bg-slate-800 px-4 py-2 border-b border-slate-700 flex justify-between items-center shrink-0">
                            <span class="text-xs font-mono text-slate-300"><i class='bx bx-terminal mr-1'></i>Connection Log</span>
                            <div class="flex gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                            </div>
                        </div>
                        <div id="live-terminal" class="p-4 font-mono text-xs overflow-y-auto grow leading-relaxed scroll-smooth bg-slate-900 text-slate-300 break-words">
                            <div class="text-slate-500 mb-2">System initialized. Menunggu koneksi...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let serverUrl = '';
        let pc = null;
        let currentLiveDevice = null;
        let socket = null;
        let pendingIceCandidates = [];
        let isRemoteDescriptionSet = false;

        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_server_config'
        }).then(r => r.json()).then(d => {
            if (d.master_url) serverUrl = d.master_url;
        });

        function openModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('modal-inactive');
            modal.querySelector('.modal-content-inactive')?.classList.remove('modal-content-inactive');
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            modal.classList.add('modal-inactive');
            const content = modal.querySelector('.bg-white');
            if (content) content.classList.add('modal-content-inactive');
        }

        function openEditModal(id, deviceId, name, status, syncInterval, logEnabled) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_device_id').value = deviceId;
            document.getElementById('edit_device_name').value = name;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_sync_interval').value = syncInterval;
            document.getElementById('edit_log_activity_enabled').value = logEnabled;
            document.getElementById('edit_api_key').value = '';
            openModal('editModal');
        }

        function openLockModal(id) {
            document.getElementById('lock_device_id').value = id;
            openModal('lockModal');
        }

        function generateRandomId(inputId) {
            const hex = Array.from(crypto.getRandomValues(new Uint8Array(4))).map(b => b.toString(16).padStart(2,'0')).join('').toUpperCase();
            document.getElementById(inputId).value = 'DEV-' + hex;
        }
        function generateRandomApiKey(inputId) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let key = '';
            for (let i = 0; i < 32; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
            document.getElementById(inputId).value = key;
        }

        function filterTable(tableId, query) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            const q = query.toLowerCase();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        }

        function swalConfirm(event, form, title, text, icon) {
            event.preventDefault();
            Swal.fire({
                title: title, text: text, icon: icon,
                showCancelButton: true, confirmButtonText: 'Ya, Lanjutkan', cancelButtonText: 'Batal',
                confirmButtonColor: icon === 'error' ? '#ef4444' : '#f59e0b'
            }).then((result) => { if (result.isConfirmed) form.submit(); });
            return false;
        }

        function switchTab(tabId) {
            localStorage.setItem('activeTab', tabId);
            document.querySelectorAll('.tab-content').forEach(el => { el.classList.remove('block'); el.classList.add('hidden'); });
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            document.getElementById('tab-' + tabId).classList.add('block');

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-slate-500');
            });
            const activeBtn = document.getElementById('btn-tab-' + tabId);
            if (activeBtn) {
                activeBtn.classList.remove('border-transparent', 'text-slate-500');
                activeBtn.classList.add('border-blue-500', 'text-blue-600');
            }
        }

        function logTerminal(msg) {
            const term = document.getElementById('live-terminal');
            if (term) {
                const time = new Date().toLocaleTimeString();
                let colorClass = "text-blue-400";
                if (msg.includes("[Error]") || msg.includes("gagal") || msg.includes("disconnect")) colorClass = "text-rose-400";
                if (msg.includes("[Socket]") || msg.includes("ICE")) colorClass = "text-amber-400";
                if (msg.includes("siap") || msg.includes("Connected") || msg.includes("berhasil")) colorClass = "text-emerald-400";
                msg = msg.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                term.innerHTML += `<div class="mb-1 ${colorClass}"><span class="text-slate-500 mr-2">[${time}]</span>${msg}</div>`;
                term.scrollTop = term.scrollHeight;
            }
        }

        function openLiveModal(deviceId, isOwner, permsStr) {
            currentLiveDevice = deviceId;
            document.getElementById('remoteVideo').srcObject = null;

            let permissions = {};
            try { permissions = JSON.parse(permsStr || '{}'); } catch(e){}

            let btnFront = document.getElementById('btn_front_cam');
            let btnBack = document.getElementById('btn_back_cam');
            let btnScreen = document.getElementById('btn_screen_share');

            if (!isOwner && !permissions.can_front_cam) btnFront.style.display = 'none'; else btnFront.style.display = '';
            if (!isOwner && !permissions.can_back_cam) btnBack.style.display = 'none'; else btnBack.style.display = '';
            if (!isOwner && !permissions.can_screen_share) btnScreen.style.display = 'none'; else btnScreen.style.display = '';

            btnFront.setAttribute('disabled', 'true');
            btnBack.setAttribute('disabled', 'true');

            document.getElementById('live-terminal').innerHTML = '<div class="text-slate-500 mb-2">System initialized. Menunggu koneksi...</div>';
            connectSocket(deviceId);
            document.getElementById('liveModal').classList.remove('hidden');
        }

        function closeLiveModal() {
            stopStream();
            if (socket) { socket.disconnect(); socket = null; }
            document.getElementById('liveModal').classList.add('hidden');
        }

        function confirmScreenShare() {
            Swal.fire({
                title: 'Buka Screen Share?', text: 'Apakah Anda yakin ingin melihat layar perangkat ini?',
                icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Buka', cancelButtonText: 'Batal', confirmButtonColor: '#3b82f6',
            }).then((result) => { if (result.isConfirmed) startStream('screen'); });
        }

        function connectSocket(deviceId) {
            if (!socket) {
                socket = io(serverUrl, { path: '/socket/socket.io' });
                socket.on('connect', () => {
                    logTerminal('[Socket] Connected to signaling service');
                    socket.emit('join_room', { device_id: deviceId, role: 'admin' });
                    socket.emit('get_device_info', deviceId);
                });
                socket.on('device_info', (info) => {
                    logTerminal('[Socket] Device Info received');
                    if (info.hasFrontCamera) document.getElementById('btn_front_cam').removeAttribute('disabled');
                    if (info.hasBackCamera) document.getElementById('btn_back_cam').removeAttribute('disabled');
                });
                socket.on('signal', async (data) => {
                    if (data.sender === 'device') {
                        try {
                            if (data.type === 'log') { logTerminal('[Device Log] ' + data.signal_data); return; }
                            if (data.type === 'error') { logTerminal('[Device Error] ' + data.signal_data); return; }
                            if (data.type === 'ready') {
                                logTerminal("Device siap! Membuat koneksi...");
                                const offer = await pc.createOffer();
                                await pc.setLocalDescription(offer);
                                socket.emit('signal', { device_id: currentLiveDevice, sender: 'admin', type: 'offer', signal_data: JSON.stringify(pc.localDescription) });
                            } else {
                                let sigData;
                                try { sigData = JSON.parse(data.signal_data); } catch(e) { return; }
                                if (data.type === 'answer') {
                                    logTerminal("Menerima koneksi balasan (Answer)");
                                    await pc.setRemoteDescription(new RTCSessionDescription(sigData));
                                    isRemoteDescriptionSet = true;
                                    for (let pendingIce of pendingIceCandidates) await pc.addIceCandidate(pendingIce);
                                    pendingIceCandidates = [];
                                } else if (data.type === 'ice') {
                                    logTerminal("Menerima jalur koneksi (ICE)");
                                    const candidate = new RTCIceCandidate(sigData);
                                    if (isRemoteDescriptionSet) await pc.addIceCandidate(candidate);
                                    else pendingIceCandidates.push(candidate);
                                }
                            }
                        } catch (err) { logTerminal('[Error] ' + err.message); }
                    }
                });
                socket.on('device_disconnected', () => { logTerminal('[Socket] Device disconnected'); stopStream(); });
            } else {
                socket.emit('join_room', { device_id: deviceId, role: 'admin' });
                socket.emit('get_device_info', deviceId);
            }
        }

        async function startStream(mode) {
            stopStream(false);
            logTerminal("Memulai permintaan stream: " + mode);
            document.getElementById('liveLoading').classList.remove('hidden');

            async function sendApiRequest(paramsObj) {
                const params = new URLSearchParams();
                for (const key in paramsObj) params.append(key, paramsObj[key]);
                const res = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() });
                const text = await res.text();
                try { return { status: res.status, data: JSON.parse(text) }; }
                catch(e) { return { status: res.status, raw: text }; }
            }

            const reqRes = await sendApiRequest({ device_id: currentLiveDevice, action: 'request_live', mode: mode });
            if (reqRes.status !== 200) {
                logTerminal("[Error] Gagal memulai sesi (API: " + reqRes.status + "). " + (reqRes.data?.error || ""));
                document.getElementById('liveLoading').classList.add('hidden');
                return;
            }

            let configuration = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
            const iceRes = await sendApiRequest({ action: 'get_ice_servers', device_id: currentLiveDevice });
            if (iceRes.status === 200 && iceRes.data && iceRes.data.ice_servers) {
                configuration = { iceServers: iceRes.data.ice_servers };
                logTerminal("Jalur koneksi relay berhasil didapatkan.");
            }

            configuration.iceCandidatePoolSize = 10;
            configuration.iceTransportPolicy = 'all';
            pc = new RTCPeerConnection(configuration);
            pc.addTransceiver('audio', { direction: 'recvonly' });
            pc.addTransceiver('video', { direction: 'recvonly' });

            pc.oniceconnectionstatechange = () => {
                logTerminal("Status koneksi: " + pc.iceConnectionState);
                if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                    document.getElementById('liveLoading').classList.add('hidden');
                }
            };
            pc.onicecandidate = async (e) => {
                if (e.candidate && socket) {
                    socket.emit('signal', { device_id: currentLiveDevice, sender: 'admin', type: 'ice', signal_data: JSON.stringify(e.candidate) });
                }
            };
            pc.ontrack = e => {
                const video = document.getElementById('remoteVideo');
                if (e.streams && e.streams[0]) {
                    video.srcObject = e.streams[0];
                    video.onloadedmetadata = () => { video.play().catch(err => console.error("Play error:", err)); };
                    document.getElementById('liveLoading').classList.add('hidden');
                }
            };

            isRemoteDescriptionSet = false;
            pendingIceCandidates = [];
            if (socket) { socket.emit('signal', { device_id: currentLiveDevice, sender: 'admin', type: 'request_start', signal_data: mode }); }
        }

        function stopStream(notifyDevice = true) {
            if (pc) { pc.close(); pc = null; }
            document.getElementById('remoteVideo').srcObject = null;
            document.getElementById('liveLoading').classList.add('hidden');
            if (notifyDevice && currentLiveDevice) {
                const params = new URLSearchParams();
                params.append('device_id', currentLiveDevice);
                params.append('action', 'request_live');
                params.append('mode', 'stop');
                fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() });
            }
        }

        function toggleFullscreen() {
            const container = document.getElementById('video-container');
            const btnEnter = document.getElementById('btn-fullscreen');
            const btnExit = document.getElementById('btn-exit-fullscreen');
            if (!document.fullscreenElement) {
                if (container.requestFullscreen) container.requestFullscreen();
                btnEnter.classList.add('hidden');
                btnExit.classList.remove('hidden');
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
                btnEnter.classList.remove('hidden');
                btnExit.classList.add('hidden');
            }
        }
        document.addEventListener('fullscreenchange', () => {
            const btnEnter = document.getElementById('btn-fullscreen');
            const btnExit = document.getElementById('btn-exit-fullscreen');
            if (!document.fullscreenElement) { btnEnter.classList.remove('hidden'); btnExit.classList.add('hidden'); }
            else { btnEnter.classList.add('hidden'); btnExit.classList.remove('hidden'); }
        });

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'], i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function loadMeteredQuota() {
            const container = document.getElementById('quotaContent');
            if (!container) return;
            container.innerHTML = '<div class="flex items-center justify-center py-8 text-slate-400"><i class="bx bx-loader-alt bx-spin text-2xl mr-2"></i> Memuat data quota...</div>';

            const anyDeviceId = '<?= !empty($devices) ? htmlspecialchars($devices[0]['device_id']) : '' ?>';
            if (!anyDeviceId) {
                container.innerHTML = `
                    <div class="text-center py-6">
                        <i class='bx bx-info-circle text-3xl text-slate-400 mb-2'></i>
                        <p class="text-sm text-slate-500">Belum ada perangkat. Tambahkan perangkat untuk melihat data.</p>
                    </div>`;
                return;
            }

            const params = new URLSearchParams();
            params.append('action', 'get_metered_quota');
            params.append('device_id', anyDeviceId);

            fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<p class="text-sm text-red-500 py-4"><i class='bx bx-error-circle'></i> ${data.error}</p>`;
                        return;
                    }

                    if (data.total_accounts === 0) {
                        container.innerHTML = `
                            <div class="text-center py-6">
                                <i class='bx bx-info-circle text-3xl text-slate-400 mb-2'></i>
                                <p class="text-sm text-slate-500 mb-4">Anda belum memiliki akun layanan jaringan.</p>
                            </div>`;
                        return;
                    }

                    let html = `<div class="space-y-4">`;
                    data.accounts.forEach(acc => {
                        const total = acc.bandwidth_limit || 0;
                        const used = acc.bandwidth_used || 0;
                        const usedText = formatBytes(used);
                        const totalText = total === 0 ? 'Unlimited' : formatBytes(total);
                        const pct = total === 0 ? 0 : Math.min(100, Math.round((used / total) * 100));
                        let barColor = 'bg-blue-500';
                        if (pct > 75) barColor = 'bg-amber-500';
                        if (pct > 90) barColor = 'bg-rose-500';
                        const isOk = acc.status === 'ok';
                        const errorBadge = !isOk ? `<span class="text-xs px-2 py-0.5 bg-rose-100 text-rose-700 rounded-full font-medium ml-2"><i class='bx bx-error-circle'></i> Gagal memuat</span>` : '';
                        
                        html += `
                            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-semibold text-slate-800">Akun Relay: ${acc.domain || 'N/A'} ${errorBadge}</span>
                                    <span class="text-xs px-2 py-0.5 bg-slate-200 text-slate-700 rounded-full font-medium">Batas: ${totalText}</span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-2.5 mb-2 overflow-hidden opacity-${isOk ? '100' : '50'}">
                                    <div class="${barColor} h-2.5 rounded-full" style="width: ${pct}%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-slate-500">
                                    <span>Digunakan: ${usedText}</span>
                                    <span>Tersisa: ${total === 0 ? 'Unlimited' : formatBytes(Math.max(0, total - used))}</span>
                                </div>
                            </div>
                        `;
                    });
                    html += `</div>`;
                    container.innerHTML = html;
                })
                .catch(() => {
                    container.innerHTML = `<p class="text-sm text-red-500 py-4"><i class='bx bx-error-circle'></i> Gagal memuat data kuota jaringan.</p>`;
                });
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            loadMeteredQuota();
            const savedTab = localStorage.getItem('activeTab');
            if (savedTab) {
                switchTab(savedTab);
            }
            const themeToggleBtn = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            
            if (document.documentElement.classList.contains('dark')) {
                themeIcon.classList.remove('bx-moon');
                themeIcon.classList.add('bx-sun');
            } else {
                themeIcon.classList.remove('bx-sun');
                themeIcon.classList.add('bx-moon');
            }

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                        themeIcon.classList.remove('bx-sun');
                        themeIcon.classList.add('bx-moon');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                        themeIcon.classList.remove('bx-moon');
                        themeIcon.classList.add('bx-sun');
                    }
                });
            }
        });
    </script>
</body>
</html>