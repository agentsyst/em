<?php
session_start();
require 'env_helper.php';

if (!isset($_SESSION['client_logged_in'])) {
    header("Location: login.php");
    exit;
}

$env = getEnvData();
$username = $env['USERNAME'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjang Layanan - AgentSys</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md text-center">
        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class='bx bx-time-five text-3xl text-amber-600'></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Masa Aktif Habis</h2>
        <p class="text-gray-600 text-sm mb-6">Akun Anda telah berakhir. Silakan perpanjang untuk melanjutkan akses.</p>
        
        <div id="errorBox" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded text-sm text-left"></div>
        <div id="successBox" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded text-sm text-left"></div>

        <button onclick="requestRenew()" id="btnRenew" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg flex justify-center items-center gap-2">
            <i class='bx bx-refresh'></i> Proses Perpanjangan
        </button>

        <div id="paymentBox" class="hidden mt-6">
            <h3 class="text-lg font-bold text-gray-800 mb-2">Aktivasi Ulang</h3>
            <p id="payMessage" class="text-sm text-gray-600 mb-4"></p>
            <img id="qrCode" src="" alt="QRIS" class="mx-auto w-48 h-48 mb-4 border p-2 rounded-lg shadow-sm">
            <p class="font-bold text-xl text-blue-600 mb-4" id="payAmount"></p>
            <div class="flex flex-col gap-2">
                <button onclick="checkPayment()" id="btnCheck" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-lg flex justify-center items-center gap-2">
                    <i class='bx bx-check-circle'></i> Konfirmasi Aktivasi
                </button>
                <button onclick="cancelPayment()" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2.5 px-4 rounded-lg">
                    Batalkan
                </button>
            </div>
        </div>
        <div class="mt-4">
            <a href="logout.php" class="text-sm text-red-500 hover:underline">Keluar</a>
        </div>
    </div>
    <script>
        const currentUsername = '<?= htmlspecialchars($username) ?>';
        let currentTrx = '';

        function showError(msg) {
            const eb = document.getElementById('errorBox');
            eb.innerText = msg;
            eb.classList.remove('hidden');
            setTimeout(() => eb.classList.add('hidden'), 5000);
        }
        function showSuccess(msg) {
            const sb = document.getElementById('successBox');
            sb.innerText = msg;
            sb.classList.remove('hidden');
        }

        async function requestRenew() {
            const btn = document.getElementById('btnRenew');
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Memproses...';

            const fd = new URLSearchParams();
            fd.append('action', 'renew_request');
            fd.append('username', currentUsername);

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: fd.toString()
                });
                const data = await res.json();
                
                if (data.error) {
                    showError(data.error);
                } else if (data.status === 'success') {
                    btn.classList.add('hidden');
                    document.getElementById('paymentBox').classList.remove('hidden');
                    document.getElementById('payMessage').innerText = data.message;
                    document.getElementById('qrCode').src = data.qr_code_url;
                    document.getElementById('payAmount').innerText = 'Rp ' + parseInt(data.amount).toLocaleString('id-ID');
                    currentTrx = data.trx_id;
                }
            } catch (err) {
                showError('Tidak dapat terhubung. Periksa koneksi internet Anda.');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-refresh"></i> Proses Perpanjangan';
        }

        async function checkPayment() {
            const btn = document.getElementById('btnCheck');
            btn.disabled = true;
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Memverifikasi...";

            const fd = new URLSearchParams();
            fd.append('action', 'check_renew_payment');
            fd.append('trx_id', currentTrx);

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: fd.toString()
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    showSuccess(data.message);
                    setTimeout(() => { window.location.href = 'index.php'; }, 2000);
                } else {
                    showError(data.message || data.error);
                }
            } catch (err) {
                showError('Tidak dapat terhubung. Periksa koneksi internet Anda.');
            }
            btn.disabled = false;
            btn.innerHTML = "<i class='bx bx-check-circle'></i> Konfirmasi Aktivasi";
        }

        async function cancelPayment() {
            if(!confirm('Yakin ingin membatalkan proses ini?')) return;
            const fd = new URLSearchParams();
            fd.append('action', 'cancel_deposit');
            fd.append('trx_id', currentTrx);
            await fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: fd.toString()
            });
            window.location.reload();
        }
    </script>
</body>
</html>
