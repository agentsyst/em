# AgentSys

Sistem manajemen jarak jauh terintegrasi untuk perangkat Mobile dan Desktop. AgentSys memungkinkan administrator untuk melakukan monitoring, remote control, dan berbagi sumber daya (resource sharing) dari satu panel web terpusat dengan latensi rendah menggunakan teknologi WebRTC dan WebSocket.

## ?? Fitur Utama

- **Remote Lock Screen:** Kunci layar perangkat secara instan dari jarak jauh disertai pesan kustom (mendukung Device Policy Manager di Android).
- **Live Camera Streaming:** Akses kamera depan maupun belakang perangkat secara real-time. Dilengkapi dengan mekanisme khusus untuk menembus batasan background camera pada OS Android modern (seperti Android 14/15 dan Xiaomi HyperOS/MIUI).
- **Real-time Screen Share:** Pantau layar perangkat pengguna secara langsung (Screen Mirroring) dengan persetujuan pengguna (MediaProjection).
- **??? Secure Privacy Protection:** Sistem dirancang dengan mempertimbangkan keamanan privasi pengguna tingkat tinggi. Saat pengguna mengetikkan password atau data sensitif di perangkat Mobile, layar tersebut otomatis diamankan oleh OS (FLAG_SECURE) sehingga karakter password terlindungi dan tidak akan terlihat/bocor pada tayangan Screen Share.
- **Desktop Resource Sharing:** Agen desktop mendukung pembagian (sharing) fungsionalitas perangkat keras seperti Printer dan integrasi jaringan tingkat lanjut lainnya.

## ?? Arsitektur Proyek

Proyek ini dipisahkan menjadi beberapa komponen berdasarkan fungsinya:

### 1. client (Web Admin Dashboard)
Antarmuka pengguna (UI) bagi Administrator (dibangun menggunakan PHP & Tailwind CSS). Dari dashboard ini, Admin dapat mendaftarkan perangkat, memantau perangkat online, dan mengirimkan perintah jarak jauh (Screen/Camera/Lock).

### 2. mobile (Android Agent)
Aplikasi agen (APK) untuk OS Android (berbasis Kotlin). Agen ini berjalan mulus di latar belakang dan merespons perintah server secara real-time. Komponen ini mengatur izin sistem secara cerdas, dan menjamin perlindungan input sensitif (seperti password) agar tetap tersembunyi selama transmisi layar berlangsung.

### 3. desktop (PC Agent)
Aplikasi klien untuk PC/Desktop. Selain fungsi pantauan dan manajemen dasar, agen desktop ini berperan memperluas fungsionalitas perangkat di dalam jaringan, seperti berbagi (sharing) printer lokal agar dapat digunakan oleh ekosistem AgentSys lainnya.

### 4. Backend Server
Sistem relay/signaling umum yang bertugas mengatur lalu lintas (traffic) ringan berbasis WebSocket (Socket.IO). Server ini hanya menjembatani koneksi antara Client dan Agen agar komunikasi video/audio peer-to-peer (WebRTC) dapat terjalin dengan stabil.
