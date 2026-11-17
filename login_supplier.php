<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');
require_once '../../config/database.php';

// Konfigurasi Target POS & Geofencing Radius 100 Meter
define('POS_LAT', -7.753053);
define('POS_LNG', 110.495028);
define('MAX_DISTANCE_METERS', 100);
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
        $_SESSION['supplier_jenis_barang'],
        $_SESSION['supplier_kategori']
    );
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ==========================================
// 2. TANGKAP REQUEST POST (LOGIN & SUBMIT)
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
                    // Ambil data supplier beserta nama_kategori dari merchant_kategori_barang
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
                                $_SESSION['supplier_logged_in']    = true;
                                $_SESSION['supplier_id']           = $supplier_id;
                                $_SESSION['supplier_nama']         = $supplier['nama_supplier'];
                                $_SESSION['supplier_alamat']       = $supplier['alamat'] ?? '-';
                                $_SESSION['supplier_no_hp']        = $supplier['no_hp'] ?? '-';
                                $_SESSION['supplier_jenis_barang'] = $supplier['jenis_barang'] ?? '-';
                                $_SESSION['supplier_kategori']     = !empty($supplier['nama_kategori']) ? $supplier['nama_kategori'] : '-';

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

    // --- AKSI B: PROSES SUBMIT BARANG (JUMLAH OPSIONAL) ---
    if (isset($_POST['action']) && $_POST['action'] === 'simpan_akses' && isset($_SESSION['supplier_logged_in'])) {
        $last_submit = $_SESSION['last_supplier_entry'] ?? 0;
        $time_diff   = time() - $last_submit;

        if ($time_diff < INPUT_COOLDOWN_SECONDS) {
            $sisa_waktu = INPUT_COOLDOWN_SECONDS - $time_diff;
            $pesan_error = "Sistem sibuk. Tunggu {$sisa_waktu} detik.";
        } else {
            $raw_jumlah = trim($_POST['jumlah'] ?? '');
            $jumlah     = ($raw_jumlah !== '' && is_numeric($raw_jumlah)) ? (int)$raw_jumlah : 0;
            $satuan_id  = filter_var($_POST['satuan_id'] ?? null, FILTER_VALIDATE_INT);

            $jenis_barang = $_SESSION['supplier_jenis_barang'] ?? '-';
            $kategori     = $_SESSION['supplier_kategori'] ?? '-';

            if ($jumlah > 0 && $satuan_id) {
                $stmtSat = $pdo->prepare("SELECT nama_satuan FROM merchant_satuan_barang WHERE satuan_id = ?");
                $stmtSat->execute([$satuan_id]);
                $nama_satuan = $stmtSat->fetchColumn() ?: '';

                $teks_kategori = ($kategori !== '-') ? " ({$kategori})" : "";
                $detail_barang = trim("{$jumlah} {$nama_satuan} {$jenis_barang}{$teks_kategori}");
                $notif_sukses  = "Berhasil mencatat {$jumlah} {$nama_satuan} {$jenis_barang}! Anda otomatis Logout.";
            } else {
                // Jika jumlah tidak diisi / 0
                $jumlah        = 0;
                $satuan_id     = null;
                $detail_barang = '-';
                $notif_sukses  = "Berhasil lapor masuk kios! Anda otomatis Logout.";
            }

            $waktu_masuk = date('Y-m-d H:i:s');
            $supplier_id = $_SESSION['supplier_id'];

            try {
                $stmt = $pdo->prepare("INSERT INTO merchant_log_akses_supplier (supplier_id, jumlah, satuan_id, waktu_masuk) VALUES (?, ?, ?, ?)");
                $stmt->execute([$supplier_id, $jumlah, $satuan_id, $waktu_masuk]);

                $_SESSION['last_supplier_entry'] = time();

                // LOGOUT OTOMATIS
                unset(
                    $_SESSION['supplier_logged_in'],
                    $_SESSION['supplier_id'],
                    $_SESSION['supplier_nama'],
                    $_SESSION['supplier_alamat'],
                    $_SESSION['supplier_no_hp'],
                    $_SESSION['supplier_jenis_barang'],
                    $_SESSION['supplier_kategori']
                );

                $_SESSION['flash_sukses'] = $notif_sukses;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $pesan_error = "Gagal menyimpan: " . $e->getMessage();
            }
        }
    }
}

// Ambil daftar satuan dari merchant_satuan_barang
$list_satuan = [];
if (isset($_SESSION['supplier_logged_in'])) {
    try {
        $stmt_satuan = $pdo->query("SELECT * FROM merchant_satuan_barang ORDER BY satuan_id ASC");
        $list_satuan = $stmt_satuan->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pesan_error = "Gagal memuat data satuan: " . $e->getMessage();
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
                        <span class="label">Nama Supplier</span>
                        <span class="value text-uppercase"><?= htmlspecialchars($_SESSION['supplier_nama'] ?? '-') ?></span>
                    </li>
                    <li>
                        <span class="label">Jenis Barang & Kategori</span>
                        <span class="value d-flex align-items-center gap-2 flex-wrap mt-1">
                            <span class="badge bg-primary rounded-pill px-3 py-1"><?= htmlspecialchars($_SESSION['supplier_jenis_barang'] ?? '-') ?></span>
                            <span class="badge bg-info text-dark rounded-pill px-3 py-1"><?= htmlspecialchars($_SESSION['supplier_kategori'] ?? '-') ?></span>
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

            <!-- Form Bawaan (Input Angka Opsional & Pilih Satuan) -->
            <div class="card card-custom p-3 mt-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-box-open text-primary me-2"></i>Jumlah Barang Bawaan</h6>
                    <small id="cooldown-badge" class="badge bg-warning text-dark d-none">Jeda</small>
                </div>

                <form id="formInputBarang" method="POST" action="">
                    <input type="hidden" name="action" value="simpan_akses">

                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold" style="font-size:0.8rem;">
                            Jumlah <?= htmlspecialchars($_SESSION['supplier_jenis_barang'] ?? '-') ?> <span class="fw-normal">(Opsional / Boleh dikosongkan)</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="jumlah" class="form-control fw-bold" placeholder="0" min="0" inputmode="numeric" autofocus>
                            <select name="satuan_id" class="form-select bg-light fw-semibold" style="max-width: 120px;">
                                <?php foreach ($list_satuan as $sat): ?>
                                    <option value="<?= $sat['satuan_id'] ?>" <?= (strtolower($sat['nama_satuan']) === 'pcs') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sat['nama_satuan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" id="btnSubmitForm" class="btn btn-success btn-mobile-lg w-100 shadow-sm mt-2" disabled>
                        <i class="fa-solid fa-paper-plane me-1"></i> Lapor Masuk Kios
                    </button>
                    <p class="text-center text-muted mt-3 mb-0" style="font-size:0.75rem;">Sistem akan mencatat waktu & memproses Logout otomatis setelah tombol diklik.</p>
                </form>
            </div>

            <!-- JS Timer -->
            <script>
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