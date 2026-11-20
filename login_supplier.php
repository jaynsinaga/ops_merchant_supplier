<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');
require_once '../../config/database.php';

// Konfigurasi Target POS & Geofencing Radius 100 Meter
define('POS_LAT', -7.753053);
define('POS_LNG', 110.495028);
define('MAX_DISTANCE_METERS', 1000);
define('INPUT_COOLDOWN_SECONDS', 60);

function getDistanceHaversine($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

$pesan_error  = '';
$pesan_sukses = '';

if (isset($_SESSION['flash_sukses'])) {
    $pesan_sukses = $_SESSION['flash_sukses'];
    unset($_SESSION['flash_sukses']);
}
if (isset($_SESSION['flash_error'])) {
    $pesan_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// ==========================================
// 1. PROSES LOGOUT MANUAL
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset(
        $_SESSION['supplier_logged_in'],
        $_SESSION['supplier_id'],
        $_SESSION['supplier_nama'],
        $_SESSION['supplier_alamat'],
        $_SESSION['supplier_no_hp'],
        $_SESSION['supplier_kategori']
    );
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ==========================================
// 2. TANGKAP REQUEST POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- AKSI A: PROSES LOGIN ---
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $user_lat = filter_var($_POST['user_lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $user_lng = filter_var($_POST['user_lng'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($user_lat === false || $user_lng === false) {
            $pesan_error = "GPS HP Anda tidak terdeteksi. Aktifkan akses lokasi untuk login.";
        } else {
            $distance = getDistanceHaversine(POS_LAT, POS_LNG, $user_lat, $user_lng);
            if ($distance > MAX_DISTANCE_METERS) {
                $pesan_error = "Akses ditolak: Jarak Anda (" . round($distance) . " m) berada di luar batas area POS (Max 100m).";
            }
        }

        if (empty($pesan_error)) {
            $nama_supplier = trim($_POST['nama_supplier'] ?? '');
            $password      = trim($_POST['password'] ?? '');

            if (!empty($nama_supplier) && !empty($password)) {
                try {
                    $sql = "SELECT ms.*, kb.nama_kategori 
                            FROM merchant_supplier ms
                            LEFT JOIN merchant_kategori_barang kb ON ms.kategori_id = kb.kategori_id
                            WHERE ms.nama_supplier = ? LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nama_supplier]);
                    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($supplier) {
                        $isPasswordValid = false;
                        if (password_verify($password, $supplier['password'])) {
                            $isPasswordValid = true;
                        } else if ($password === $supplier['password']) {
                            $isPasswordValid = true;
                        }

                        if ($isPasswordValid) {
                            $supplier_id = $supplier['supplier_id'] ?? null;

                            if (!$supplier_id) {
                                $pesan_error = "Sistem error: ID Supplier tidak valid.";
                            } else {
                                $_SESSION['supplier_logged_in'] = true;
                                $_SESSION['supplier_id']        = $supplier_id;
                                $_SESSION['supplier_nama']      = $supplier['nama_supplier'];
                                $_SESSION['supplier_alamat']    = $supplier['alamat'] ?? '-';
                                $_SESSION['supplier_no_hp']     = $supplier['no_hp'] ?? '-';
                                $_SESSION['supplier_kategori']  = !empty($supplier['nama_kategori']) ? $supplier['nama_kategori'] : '-';

                                header('Location: ' . $_SERVER['PHP_SELF']);
                                exit;
                            }
                        } else {
                            $pesan_error = "Password salah!";
                        }
                    } else {
                        $pesan_error = "Nama Supplier tidak ditemukan.";
                    }
                } catch (PDOException $e) {
                    $pesan_error = "Error database: " . $e->getMessage();
                }
            } else {
                $pesan_error = "Nama dan Password wajib diisi!";
            }
        }
    }

    // --- AKSI B: TAMBAH JENIS BARANG BARU KE PROFIL SUPPLIER ---
    if (isset($_POST['action']) && $_POST['action'] === 'tambah_barang' && isset($_SESSION['supplier_logged_in'])) {
        $nama_barang_baru = trim($_POST['nama_barang_baru'] ?? '');
        $supplier_id      = $_SESSION['supplier_id'];

        if (!empty($nama_barang_baru)) {
            try {
                // Cek duplikasi nama barang pada supplier yang sama
                $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM merchant_supplier_barang WHERE supplier_id = ? AND LOWER(nama_barang) = LOWER(?)");
                $stmtCek->execute([$supplier_id, $nama_barang_baru]);

                if ($stmtCek->fetchColumn() > 0) {
                    $_SESSION['flash_error'] = "Barang '{$nama_barang_baru}' sudah ada di daftar Anda.";
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO merchant_supplier_barang (supplier_id, nama_barang) VALUES (?, ?)");
                    $stmtIns->execute([$supplier_id, $nama_barang_baru]);
                    $_SESSION['flash_sukses'] = "Jenis barang '{$nama_barang_baru}' berhasil disimpan!";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Gagal menambah barang: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = "Nama jenis barang tidak boleh kosong.";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // --- AKSI C: HAPUS JENIS BARANG DARI PROFIL SUPPLIER ---
    if (isset($_POST['action']) && $_POST['action'] === 'hapus_barang' && isset($_SESSION['supplier_logged_in'])) {
        $barang_id   = filter_var($_POST['barang_id'] ?? null, FILTER_VALIDATE_INT);
        $supplier_id = $_SESSION['supplier_id'];

        if ($barang_id) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM merchant_supplier_barang WHERE barang_id = ? AND supplier_id = ?");
                $stmtDel->execute([$barang_id, $supplier_id]);
                $_SESSION['flash_sukses'] = "Jenis barang berhasil dihapus dari daftar.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Gagal menghapus barang: " . $e->getMessage();
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // --- AKSI D: PROSES SUBMIT KEDATANGAN & JUMLAH BARANG ---
    if (isset($_POST['action']) && $_POST['action'] === 'simpan_akses' && isset($_SESSION['supplier_logged_in'])) {
        $last_submit = $_SESSION['last_supplier_entry'] ?? 0;
        $time_diff   = time() - $last_submit;

        if ($time_diff < INPUT_COOLDOWN_SECONDS) {
            $sisa_waktu = INPUT_COOLDOWN_SECONDS - $time_diff;
            $pesan_error = "Sistem sibuk. Tunggu {$sisa_waktu} detik.";
        } else {
            $supplier_id = $_SESSION['supplier_id'];
            $waktu_masuk = date('Y-m-d H:i:s');
            $items_input = $_POST['items'] ?? []; // Array: [barang_id => ['jumlah' => ..., 'satuan_id' => ...]]

            try {
                $pdo->beginTransaction();

                $stmtLog = $pdo->prepare("INSERT INTO merchant_log_akses_supplier (supplier_id, barang_id, jumlah, satuan_id, waktu_masuk) VALUES (?, ?, ?, ?, ?)");
                $ada_barang_diinput = false;
                $ringkasan_barang   = [];

                // Ambil map nama satuan untuk pesan notifikasi
                $satuan_map = $pdo->query("SELECT satuan_id, nama_satuan FROM merchant_satuan_barang")->fetchAll(PDO::FETCH_KEY_PAIR);
                // Ambil map nama barang milik supplier untuk validasi & notifikasi
                $stmtBrgMap = $pdo->prepare("SELECT barang_id, nama_barang FROM merchant_supplier_barang WHERE supplier_id = ?");
                $stmtBrgMap->execute([$supplier_id]);
                $barang_map = $stmtBrgMap->fetchAll(PDO::FETCH_KEY_PAIR);

                foreach ($items_input as $barang_id => $detail) {
                    $barang_id = (int)$barang_id;
                    if (!isset($barang_map[$barang_id])) continue;

                    $raw_jumlah = trim($detail['jumlah'] ?? '');
                    $jumlah     = ($raw_jumlah !== '' && is_numeric($raw_jumlah)) ? (int)$raw_jumlah : 0;
                    $satuan_id  = filter_var($detail['satuan_id'] ?? null, FILTER_VALIDATE_INT);

                    if ($jumlah > 0 && $satuan_id) {
                        $stmtLog->execute([$supplier_id, $barang_id, $jumlah, $satuan_id, $waktu_masuk]);
                        $ada_barang_diinput = true;

                        $nama_brg = $barang_map[$barang_id];
                        $nama_sat = $satuan_map[$satuan_id] ?? '';
                        $ringkasan_barang[] = "{$nama_brg} ({$jumlah} {$nama_sat})";
                    }
                }

                // Jika tidak ada barang yang diisi jumlahnya, tetap catat lapor masuk tanpa barang
                if (!$ada_barang_diinput) {
                    $stmtLog->execute([$supplier_id, null, 0, null, $waktu_masuk]);
                    $notif_sukses = "Berhasil lapor masuk kios (tanpa barang)! Anda otomatis Logout.";
                } else {
                    $teks_ringkasan = implode(', ', $ringkasan_barang);
                    $notif_sukses   = "Berhasil mencatat: {$teks_ringkasan}. Anda otomatis Logout.";
                }

                $pdo->commit();
                $_SESSION['last_supplier_entry'] = time();

                // LOGOUT OTOMATIS
                unset(
                    $_SESSION['supplier_logged_in'],
                    $_SESSION['supplier_id'],
                    $_SESSION['supplier_nama'],
                    $_SESSION['supplier_alamat'],
                    $_SESSION['supplier_no_hp'],
                    $_SESSION['supplier_kategori']
                );

                $_SESSION['flash_sukses'] = $notif_sukses;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pesan_error = "Gagal menyimpan: " . $e->getMessage();
            }
        }
    }
}

// Ambil daftar satuan & daftar barang milik supplier yang sedang login
$list_satuan = [];
$list_barang_supplier = [];

if (isset($_SESSION['supplier_logged_in'])) {
    try {
        $stmt_satuan = $pdo->query("SELECT * FROM merchant_satuan_barang ORDER BY satuan_id ASC");
        $list_satuan = $stmt_satuan->fetchAll(PDO::FETCH_ASSOC);

        $stmt_barang = $pdo->prepare("SELECT * FROM merchant_supplier_barang WHERE supplier_id = ? ORDER BY barang_id ASC");
        $stmt_barang->execute([$_SESSION['supplier_id']]);
        $list_barang_supplier = $stmt_barang->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pesan_error = "Gagal memuat data: " . $e->getMessage();
    }
}

$last_submit_time = $_SESSION['last_supplier_entry'] ?? 0;
$time_since_last  = time() - $last_submit_time;
$initial_cooldown = ($time_since_last < INPUT_COOLDOWN_SECONDS) ? (INPUT_COOLDOWN_SECONDS - $time_since_last) : 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Portal Supplier Kios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            padding-top: 15px;
            padding-bottom: 40px;
        }

        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
        }

        .card-custom {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            border: none;
            margin-bottom: 16px;
        }

        .pos-badge {
            font-size: 0.75rem;
            border-radius: 30px;
            padding: 5px 12px;
        }

        .btn-mobile-lg {
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
        }

        .header-logo {
            max-height: 42px;
            width: auto;
            object-fit: contain;
        }

        .info-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .info-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-list .label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 2px;
            font-weight: 500;
        }

        .info-list .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #212529;
        }

        .item-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
        }
    </style>
</head>

<body>
    <div class="container px-3 mobile-container">

        <!-- HEADER LOGO -->
        <div class="d-flex justify-content-between align-items-center mb-3 pt-2">
            <img src="img/injourney.png" alt="Logo Kiri" class="header-logo" onerror="this.style.display='none'">
            <img src="img/Prambanan.png" alt="Logo Kanan" class="header-logo" style="max-height: 80px;" onerror="this.style.display='none'">
        </div>

        <!-- Alert Status Pesan -->
        <?php if (!empty($pesan_sukses)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 text-sm" role="alert">
                <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($pesan_sukses) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($pesan_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 text-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($pesan_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>


        <?php if (!isset($_SESSION['supplier_logged_in'])): ?>
            <!-- ========================================== -->
            <!-- TAMPILAN 1: FORM LOGIN (MOBILE VIEW)       -->
            <!-- ========================================== -->
            <div class="card card-custom p-3 border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-1 fs-6"><i class="fa-solid fa-location-crosshairs text-primary me-2"></i>Status GPS</h6>
                        <small id="login-location-text" class="text-muted" style="font-size: 0.75rem;">Mencari sinyal...</small>
                    </div>
                    <span id="login-location-badge" class="badge bg-secondary pos-badge">
                        <i class="fa-solid fa-spinner fa-spin me-1"></i> Wait
                    </span>
                </div>
            </div>

            <div class="card card-custom p-4 border-top border-4 border-primary mt-3">
                <div class="text-center mb-4">
                    <i class="fa-solid fa-store fa-2x text-primary mb-2"></i>
                    <h5 class="fw-bold text-dark mb-1">Akses Area Kios</h5>
                    <p class="fw-bold text-dark mx-4 mb-1">PT Taman Wisata Candi Borobudur, Prambanan & Ratu Boko</p>
                    <p class="text-muted" style="font-size: 0.8rem;">Login harus sesuai lokasi POS</p>
                </div>

                <form id="formLoginSupplier" method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="user_lat" class="user_lat_input">
                    <input type="hidden" name="user_lng" class="user_lng_input">

                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold" style="font-size: 0.8rem;">Username Supplier</label>
                        <input type="text" name="nama_supplier" class="form-control form-control-lg fs-6" placeholder="Masukkan nama..." required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted fw-semibold" style="font-size: 0.8rem;">Password</label>
                        <input type="password" name="password" class="form-control form-control-lg fs-6" placeholder="Masukkan sandi..." required>
                    </div>

                    <button type="submit" id="btnLoginForm" class="btn btn-primary w-100 btn-mobile-lg" disabled>
                        <i class="fa-solid fa-spinner fa-spin me-1"></i> Mendeteksi Lokasi...
                    </button>
                </form>
            </div>

            <script>
                const TARGET_LAT = <?= POS_LAT ?>;
                const TARGET_LNG = <?= POS_LNG ?>;
                const MAX_RADIUS = <?= MAX_DISTANCE_METERS ?>;

                function calculateDistance(lat1, lon1, lat2, lon2) {
                    const R = 6371000;
                    const dLat = (lat2 - lat1) * Math.PI / 180;
                    const dLon = (lon2 - lon1) * Math.PI / 180;
                    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                        Math.sin(dLon / 2) * Math.sin(dLon / 2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                    return R * c;
                }

                function checkLoginGeolocation() {
                    const statusText = document.getElementById('login-location-text');
                    const statusBadge = document.getElementById('login-location-badge');
                    const btnLogin = document.getElementById('btnLoginForm');

                    if (!navigator.geolocation) {
                        statusText.innerText = "GPS HP tidak didukung.";
                        statusBadge.className = "badge bg-danger pos-badge";
                        statusBadge.innerHTML = '<i class="fa-solid fa-xmark me-1"></i> Error';
                        btnLogin.innerHTML = '<i class="fa-solid fa-ban me-1"></i> GPS Error';
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const userLat = position.coords.latitude;
                            const userLng = position.coords.longitude;

                            document.querySelectorAll('.user_lat_input').forEach(el => el.value = userLat);
                            document.querySelectorAll('.user_lng_input').forEach(el => el.value = userLng);

                            const distance = calculateDistance(TARGET_LAT, TARGET_LNG, userLat, userLng);

                            if (distance <= MAX_RADIUS) {
                                statusText.innerText = `Jarak sesuai (${Math.round(distance)}m)`;
                                statusBadge.className = "badge bg-success pos-badge";
                                statusBadge.innerHTML = '<i class="fa-solid fa-check me-1"></i> Valid';

                                btnLogin.disabled = false;
                                btnLogin.innerHTML = '<i class="fa-solid fa-right-to-bracket me-1"></i> Masuk Sekarang';
                            } else {
                                statusText.innerText = `Terlalu jauh (${Math.round(distance)}m)`;
                                statusBadge.className = "badge bg-danger pos-badge";
                                statusBadge.innerHTML = '<i class="fa-solid fa-xmark me-1"></i> Di Luar';

                                btnLogin.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Di Luar Area (Max 100m)';
                            }
                        },
                        (error) => {
                            statusText.innerText = "Akses lokasi ditolak HP.";
                            statusBadge.className = "badge bg-danger pos-badge";
                            statusBadge.innerHTML = '<i class="fa-solid fa-location-off me-1"></i> Off';
                            btnLogin.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Aktifkan GPS';
                        }, {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                }
                document.addEventListener('DOMContentLoaded', checkLoginGeolocation);
            </script>


        <?php else: ?>
            <!-- ========================================== -->
            <!-- TAMPILAN 2: INPUT KEDATANGAN (LOGGED IN)   -->
            <!-- ========================================== -->

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-user me-1 text-primary"></i> Data Supplier</h5>
                <a href="?action=logout" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1">Keluar</a>
            </div>

            <!-- Identitas Card -->
            <div class="card card-custom p-3 border-start border-4 border-info">
                <ul class="info-list">
                    <li>
                        <span class="label">Nama Supplier & Kategori</span>
                        <span class="value d-flex align-items-center justify-content-between flex-wrap gap-1">
                            <span class="text-uppercase"><?= htmlspecialchars($_SESSION['supplier_nama'] ?? '-') ?></span>
                            <span class="badge bg-info text-dark rounded-pill px-3 py-1" style="font-size:0.75rem;">
                                <?= htmlspecialchars($_SESSION['supplier_kategori'] ?? '-') ?>
                            </span>
                        </span>
                    </li>
                    <li>
                        <span class="label">Info Kontak (HP & Alamat)</span>
                        <span class="value" style="font-size:0.85rem;">
                            <i class="fa-solid fa-phone fa-fw text-muted me-1"></i> <?= htmlspecialchars($_SESSION['supplier_no_hp'] ?? '-') ?><br>
                            <i class="fa-solid fa-map-location-dot fa-fw text-muted me-1"></i> <?= htmlspecialchars($_SESSION['supplier_alamat'] ?? '-') ?>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Card 1: Tambah Jenis Barang Baru (Tersimpan Otomatis untuk Login Berikutnya) -->
            <div class="card card-custom p-3 border-start border-4 border-primary">
                <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-plus-circle text-primary me-1"></i> Daftarkan Jenis Barang Baru
                </h6>
                <p class="text-muted mb-2" style="font-size: 0.75rem;">
                    Barang yang ditambahkan akan tersimpan otomatis untuk kedatangan berikutnya.
                </p>
                <form method="POST" action="" class="d-flex gap-2">
                    <input type="hidden" name="action" value="tambah_barang">
                    <input type="text" name="nama_barang_baru" class="form-control" placeholder="Contoh: Keripik Tempe / Kaos..." required>
                    <button type="submit" class="btn btn-primary fw-semibold px-3">
                        <i class="fa-solid fa-plus"></i> Tambah
                    </button>
                </form>
            </div>

            <!-- Card 2: Form Input Jumlah (Pcs / Box) dari Barang yang Sudah Tersimpan -->
            <div class="card card-custom p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-box-open text-success me-2"></i>Input Jumlah Bawaan</h6>
                    <small id="cooldown-badge" class="badge bg-warning text-dark d-none">Jeda</small>
                </div>

                <?php if (empty($list_barang_supplier)): ?>
                    <div class="alert alert-warning text-center py-3 mb-3" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-circle-info mb-1 fs-5 d-block"></i>
                        Anda belum mendaftarkan jenis barang.<br>
                        Silakan tambahkan jenis barang di kolom atas terlebih dahulu, atau langsung klik tombol di bawah jika melapor tanpa barang.
                    </div>
                <?php endif; ?>

                <form id="formInputBarang" method="POST" action="">
                    <input type="hidden" name="action" value="simpan_akses">

                    <?php foreach ($list_barang_supplier as $index => $brg): ?>
                        <div class="item-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark" style="font-size: 0.95rem;">
                                    <?= ($index + 1) . '. ' . htmlspecialchars($brg['nama_barang']) ?>
                                </span>
                                <!-- Tombol Hapus Barang jika salah input jenis barang -->
                                <button type="button" class="btn btn-sm text-danger p-0 border-0"
                                    onclick="hapusBarang(<?= $brg['barang_id'] ?>, '<?= htmlspecialchars(addslashes($brg['nama_barang'])) ?>')"
                                    title="Hapus barang ini dari daftar">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>

                            <div class="input-group">
                                <input type="number"
                                    name="items[<?= $brg['barang_id'] ?>][jumlah]"
                                    class="form-control fw-bold"
                                    placeholder="0 (Kosongkan jika tidak bawa)"
                                    min="0"
                                    inputmode="numeric">
                                <select name="items[<?= $brg['barang_id'] ?>][satuan_id]"
                                    class="form-select bg-white fw-semibold"
                                    style="max-width: 110px;">
                                    <?php foreach ($list_satuan as $sat): ?>
                                        <option value="<?= $sat['satuan_id'] ?>" <?= (strtolower($sat['nama_satuan']) === 'pcs') ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sat['nama_satuan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" id="btnSubmitForm" class="btn btn-success btn-mobile-lg w-100 shadow-sm mt-2" disabled>
                        <i class="fa-solid fa-paper-plane me-1"></i> Lapor Masuk Kios
                    </button>
                    <p class="text-center text-muted mt-3 mb-0" style="font-size:0.75rem;">
                        Sistem akan mencatat waktu & memproses Logout otomatis setelah tombol diklik.
                    </p>
                </form>

                <!-- Form Tersembunyi untuk Hapus Barang -->
                <form id="formHapusBarang" method="POST" action="" class="d-none">
                    <input type="hidden" name="action" value="hapus_barang">
                    <input type="hidden" name="barang_id" id="hapus_barang_id">
                </form>
            </div>

            <!-- JS Timer & Konfirmasi Hapus -->
            <script>
                function hapusBarang(id, nama) {
                    if (confirm(`Hapus "${nama}" dari daftar barang tetap Anda?`)) {
                        document.getElementById('hapus_barang_id').value = id;
                        document.getElementById('formHapusBarang').submit();
                    }
                }

                let cooldownTimer = <?= $initial_cooldown ?>;

                function updateSubmitButtonState() {
                    const submitBtn = document.getElementById('btnSubmitForm');
                    const cooldownBadge = document.getElementById('cooldown-badge');

                    if (cooldownTimer > 0) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = `<i class="fa-solid fa-clock fa-spin me-1"></i> Tunggu ${cooldownTimer} detik`;
                        cooldownBadge.classList.remove('d-none');
                        cooldownBadge.innerText = `Jeda ${cooldownTimer}s`;
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = `<i class="fa-solid fa-paper-plane me-1"></i> Lapor Masuk Kios`;
                        cooldownBadge.classList.add('d-none');
                    }
                }

                if (cooldownTimer > 0) {
                    const interval = setInterval(() => {
                        cooldownTimer--;
                        updateSubmitButtonState();
                        if (cooldownTimer <= 0) clearInterval(interval);
                    }, 1000);
                } else {
                    updateSubmitButtonState();
                }
            </script>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>