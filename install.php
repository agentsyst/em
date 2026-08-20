<?php
require 'env_helper.php';

if (isInstalled()) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun - AgentSys</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md" id="mainCard">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class='bx bx-user-plus text-3xl text-blue-600'></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Buat Akun Baru</h2>
            <p class="text-gray-500 text-sm mt-1">Daftarkan diri Anda untuk mulai menggunakan aplikasi</p>
        </div>

        <div id="errorBox" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded text-sm"></div>
        <div id="successBox" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded text-sm"></div>

        <form id="regForm" onsubmit="doRegister(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Username</label>
                    <input type="text" id="reg_username" required class="w-full px-4 py-2 rounded-lg border focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Nama Lengkap</label>
                    <input type="text" id="reg_nama" required class="w-full px-4 py-2 rounded-lg border focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Email</label>
                    <input type="email" id="reg_email" required class="w-full px-4 py-2 rounded-lg border focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Password</label>
                    <input type="password" id="reg_password" required class="w-full px-4 py-2 rounded-lg border focus:border-blue-500 outline-none">
                </div>
            </div>
            <button type="submit" id="btnReg" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg mt-6 flex justify-center items-center gap-2">
                <i class='bx bx-right-arrow-alt'></i> Daftar Sekarang
            </button>
        </form>

        <div id="paymentBox" class="hidden text-center">
            <h3 class="text-lg font-bold text-gray-800 mb-2">Aktivasi Akun</h3>
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
    </div>

    <script>
        let currentTrx = '';
        let currentPass = '';
        let currentUsername = '';

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

        async function doRegister(e) {
            e.preventDefault();
            const btn = document.getElementById('btnReg');
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Memproses...';

            currentUsername = document.getElementById('reg_username').value;
            currentPass = document.getElementById('reg_password').value;
            const nama = document.getElementById('reg_nama').value;
            const email = document.getElementById('reg_email').value;

            const fd = new URLSearchParams();
            fd.append('action', 'register_request');
            fd.append('username', currentUsername);
            fd.append('password', currentPass);
            fd.append('nama', nama);
            fd.append('email', email);

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
                    document.getElementById('regForm').classList.add('hidden');
                    document.getElementById('paymentBox').classList.remove('hidden');
                    document.getElementById('payMessage').innerText = data.message;
                    document.getElementById('qrCode').src = data.qr_code_url;
                    document.getElementById('payAmount').innerText = 'Rp ' + parseInt(data.amount).toLocaleString('id-ID');
                    currentTrx = data.trx_id;
                }
            } catch (err) {
                showError('Tidak dapat terhubung ke layanan. Periksa koneksi internet Anda. Periksa console browser.');
                console.error(err);
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-right-arrow-alt"></i> Daftar Sekarang';
        }

        async function checkPayment() {
            const btn = document.getElementById('btnCheck');
            btn.disabled = true;
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Memverifikasi...";

            const fd = new URLSearchParams();
            fd.append('action', 'check_payment');
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
                    
                    const saveFd = new URLSearchParams();
                    saveFd.append('api_token', data.api_token);
                    saveFd.append('username', currentUsername);
                    saveFd.append('password', currentPass);

                    await fetch('install_save.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: saveFd.toString()
                    });

                    setTimeout(() => { window.location.href = 'index.php'; }, 2000);
                } else {
                    showError(data.message || data.error);
                }
            } catch (err) {
                showError('Tidak dapat terhubung ke layanan. Periksa koneksi internet Anda.');
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