<?php
// TAMPILKAN ERROR UNTUK DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// WAJIB ADA DI BARIS PALING ATAS SEBELUM KODE APAPUN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set zona waktu Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 1. KONEKSI DATABASE & KONFIGURASI
// ==========================================
require_once __DIR__ . '/database.php';

$pesan_sukses = '';
$pesan_error = '';

// ==========================================
// 2. PROSES LOGOUT
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin_supplierr.php');
    exit;
}

// ==========================================
// 3. PROSES LOGIN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            // Mengambil data user dari tabel merchant_user
            $stmt = $pdo->prepare("SELECT * FROM merchant_user WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verifikasi password hash (password_hash & password_verify)
                $is_password_valid = false;
                if (password_verify($password, $user['password'])) {
                    $is_password_valid = true;
                } elseif ($user['password'] === md5($password)) { // Fallback jika masih ada data lama berformat MD5
                    $is_password_valid = true;
                }

                if ($is_password_valid) {
                    // Menyimpan data login ke session dari tabel merchant_user
                    // Mendukung primary key 'id' atau 'id_user'
                    $_SESSION['id_user']  = $user['id'] ?? $user['id_user'] ?? $user['username'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama']     = $user['nama'];

                    // Jika tabel merchant_user memiliki kolom role, gunakan role tersebut. Jika tidak, set default 'admin'.
                    $_SESSION['role']     = $user['role'] ?? 'admin';

                    // Refresh halaman ke dashboard
                    echo "<script>window.location.href = 'admin_supplierr.php';</script>";
                    exit;
                } else {
                    $pesan_error = 'Password yang Anda masukkan salah.';
                }
            } else {
                $pesan_error = 'Username tidak ditemukan dalam sistem.';
            }
        } catch (Exception $e) {
            $pesan_error = 'Terjadi kesalahan pada server: ' . $e->getMessage();
        }
    } else {
        $pesan_error = 'Username dan Password wajib diisi!';
    }
}

// ==========================================
// CEK STATUS LOGIN UNTUK TAMPILAN VIEW
// ==========================================
$is_logged_in = isset($_SESSION['id_user']) && in_array($_SESSION['role'] ?? '', ['admin', 'admin_dept']);

// JIKA SUDAH LOGIN, JALANKAN LOGIKA DASHBOARD
if ($is_logged_in) {

    // Deteksi nama kolom primary key pada tabel merchant_user ('id' atau 'id_user')
    $user_pk_col = 'id';
    try {
        $stmt_cols = $pdo->query("SHOW COLUMNS FROM merchant_user");
        $cols = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('id_user', $cols)) {
            $user_pk_col = 'id_user';
        }
    } catch (Exception $e) {
        $user_pk_col = 'id';
    }

    // ==========================================
    // 4. FITUR EXPORT EXCEL (TAB 2 LOG KIOS)
    // ==========================================
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        $log_bulan = $_GET['log_bulan'] ?? '';
        $log_tahun = $_GET['log_tahun'] ?? '';

        $sql_export = "SELECT l.detail_barang, l.waktu_masuk, m.nama_supplier, m.jenis_barang 
                       FROM log_akses_supplier l 
                       LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id 
                       WHERE 1=1";
        $params_export = [];

        if (!empty($log_bulan)) {
            $sql_export .= " AND MONTH(l.waktu_masuk) = ?";
            $params_export[] = $log_bulan;
        }
        if (!empty($log_tahun)) {
            $sql_export .= " AND YEAR(l.waktu_masuk) = ?";
            $params_export[] = $log_tahun;
        }
        $sql_export .= " ORDER BY l.waktu_masuk DESC";

        $stmt_ex = $pdo->prepare($sql_export);
        $stmt_ex->execute($params_export);
        $data_export = $stmt_ex->fetchAll(PDO::FETCH_ASSOC);

        // Header untuk memaksa download file Excel (.xls)
        header("Content-type: application/vnd-ms-excel");
        header("Content-Disposition: attachment; filename=Data_Log_Kios_" . date('Ymd_His') . ".xls");
?>
        <table border="1">
            <tr>
                <th style="background-color: #1F4E78; color: white;">No</th>
                <th style="background-color: #1F4E78; color: white;">Waktu Kedatangan</th>
                <th style="background-color: #1F4E78; color: white;">Nama Supplier</th>
                <th style="background-color: #1F4E78; color: white;">Kategori Barang</th>
                <th style="background-color: #1F4E78; color: white;">Detail Bawaan / Pasokan</th>
            </tr>
            <?php
            $no = 1;
            foreach ($data_export as $row) {
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . date('d-m-Y H:i:s', strtotime($row['waktu_masuk'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_supplier'] ?? 'Telah Dihapus') . "</td>";
                echo "<td>" . htmlspecialchars($row['jenis_barang'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['detail_barang']) . "</td>";
                echo "</tr>";
            }
            ?>
        </table>
<?php
        exit;
    }

    // ==========================================
    // VARIABEL & COOLDOWN (DASHBOARD)
    // ==========================================
    define('INPUT_COOLDOWN_SECONDS', 60);

    // ==========================================
    // 5. PROSES POST (CRUD SUPPLIER, LOG AKSES & CRUD MERCHANT_USER)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        $admin_actions = [
            'tambah',
            'edit',
            'hapus',
            'edit_password',
            'hapus_log',
            'tambah_user',
            'edit_user',
            'edit_password_user',
            'hapus_user'
        ];

        if (in_array($action, $admin_actions)) {
            if ($_SESSION['role'] !== 'admin') {
                $pesan_error = "Akses ditolak. Hanya Admin yang dapat mengubah data.";
            } else {
                // --- CRUD SUPPLIER & LOG ---
                if ($action === 'tambah') {
                    $nama         = trim($_POST['nama_supplier'] ?? '');
                    $alamat       = trim($_POST['alamat'] ?? '');
                    $no_hp        = trim($_POST['no_hp'] ?? '');
                    $jenis_barang = trim($_POST['jenis_barang'] ?? '');
                    $password_def = md5('12345');

                    if (!empty($nama) && !empty($jenis_barang)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO merchant_supplier (nama_supplier, alamat, no_hp, jenis_barang, password) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$nama, $alamat, $no_hp, $jenis_barang, $password_def]);
                            $pesan_sukses = "Data supplier baru berhasil ditambahkan!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menambah supplier: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Nama Supplier dan Jenis Barang wajib diisi!";
                    }
                } elseif ($action === 'edit') {
                    $supplier_id  = intval($_POST['supplier_id'] ?? 0);
                    $nama         = trim($_POST['nama_supplier'] ?? '');
                    $alamat       = trim($_POST['alamat'] ?? '');
                    $no_hp        = trim($_POST['no_hp'] ?? '');
                    $jenis_barang = trim($_POST['jenis_barang'] ?? '');

                    if ($supplier_id > 0 && !empty($nama) && !empty($jenis_barang)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE merchant_supplier SET nama_supplier = ?, alamat = ?, no_hp = ?, jenis_barang = ? WHERE supplier_id = ?");
                            $stmt->execute([$nama, $alamat, $no_hp, $jenis_barang, $supplier_id]);
                            $pesan_sukses = "Data supplier berhasil diperbarui!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal memperbarui supplier: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Data tidak valid. Nama dan Jenis Barang wajib diisi!";
                    }
                } elseif ($action === 'edit_password') {
                    $supplier_id  = intval($_POST['supplier_id'] ?? 0);
                    $password_baru = trim($_POST['password_baru'] ?? '');

                    if ($supplier_id > 0 && !empty($password_baru)) {
                        try {
                            $password_hash = md5($password_baru);
                            $stmt = $pdo->prepare("UPDATE merchant_supplier SET password = ? WHERE supplier_id = ?");
                            $stmt->execute([$password_hash, $supplier_id]);
                            $pesan_sukses = "Password supplier berhasil diperbarui!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal memperbarui password: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Password baru tidak boleh kosong!";
                    }
                } elseif ($action === 'hapus') {
                    $supplier_id = intval($_POST['supplier_id'] ?? 0);
                    if ($supplier_id > 0) {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM merchant_supplier WHERE supplier_id = ?");
                            $stmt->execute([$supplier_id]);
                            $pesan_sukses = "Data supplier berhasil dihapus!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menghapus supplier: " . $e->getMessage();
                        }
                    }
                } elseif ($action === 'hapus_log') {
                    $log_id = intval($_POST['log_id'] ?? 0);
                    if ($log_id > 0) {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM log_akses_supplier WHERE id = ?");
                            $stmt->execute([$log_id]);
                            $pesan_sukses = "Data log kedatangan berhasil dihapus!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menghapus log kedatangan: " . $e->getMessage();
                        }
                    }

                    // --- CRUD MERCHANT_USER ---
                } elseif ($action === 'tambah_user') {
                    $username = trim($_POST['username'] ?? '');
                    $nama     = trim($_POST['nama'] ?? '');
                    $role     = trim($_POST['role'] ?? 'admin');
                    $password = trim($_POST['password'] ?? '');

                    if (!empty($username) && !empty($nama) && !empty($password)) {
                        try {
                            // Cek duplikasi username
                            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM merchant_user WHERE username = ?");
                            $stmt_cek->execute([$username]);
                            if ($stmt_cek->fetchColumn() > 0) {
                                $pesan_error = "Username '$username' sudah digunakan. Gunakan username lain.";
                            } else {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO merchant_user (username, nama, role, password) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$username, $nama, $role, $hashed_password]);
                                $pesan_sukses = "User baru berhasil ditambahkan!";
                            }
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menambah user: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Username, Nama, dan Password wajib diisi!";
                    }
                } elseif ($action === 'edit_user') {
                    $user_id  = intval($_POST['user_id'] ?? 0);
                    $username = trim($_POST['username'] ?? '');
                    $nama     = trim($_POST['nama'] ?? '');
                    $role     = trim($_POST['role'] ?? 'admin');

                    if ($user_id > 0 && !empty($username) && !empty($nama)) {
                        try {
                            // Cek duplikasi username kecuali milik sendiri
                            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM merchant_user WHERE username = ? AND {$user_pk_col} != ?");
                            $stmt_cek->execute([$username, $user_id]);
                            if ($stmt_cek->fetchColumn() > 0) {
                                $pesan_error = "Username '$username' sudah digunakan oleh user lain.";
                            } else {
                                $stmt = $pdo->prepare("UPDATE merchant_user SET username = ?, nama = ?, role = ? WHERE {$user_pk_col} = ?");
                                $stmt->execute([$username, $nama, $role, $user_id]);
                                $pesan_sukses = "Data user berhasil diperbarui!";
                            }
                        } catch (Exception $e) {
                            $pesan_error = "Gagal memperbarui user: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Data tidak valid. Username dan Nama wajib diisi!";
                    }
                } elseif ($action === 'edit_password_user') {
                    $user_id       = intval($_POST['user_id'] ?? 0);
                    $password_baru = trim($_POST['password_baru'] ?? '');

                    if ($user_id > 0 && !empty($password_baru)) {
                        try {
                            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE merchant_user SET password = ? WHERE {$user_pk_col} = ?");
                            $stmt->execute([$hashed_password, $user_id]);
                            $pesan_sukses = "Password user berhasil diperbarui!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal memperbarui password user: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Password baru tidak boleh kosong!";
                    }
                } elseif ($action === 'hapus_user') {
                    $user_id = intval($_POST['user_id'] ?? 0);
                    if ($user_id > 0) {
                        if ($user_id == ($_SESSION['id_user'] ?? 0)) {
                            $pesan_error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang login!";
                        } else {
                            try {
                                $stmt = $pdo->prepare("DELETE FROM merchant_user WHERE {$user_pk_col} = ?");
                                $stmt->execute([$user_id]);
                                $pesan_sukses = "Data user berhasil dihapus!";
                            } catch (Exception $e) {
                                $pesan_error = "Gagal menghapus user: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'simpan_akses') {
            $last_submit = $_SESSION['last_supplier_entry'] ?? 0;
            $time_diff   = time() - $last_submit;

            if ($time_diff < INPUT_COOLDOWN_SECONDS) {
                $pesan_error = "Sistem Cooldown. Tunggu beberapa saat lagi.";
            } else {
                $supplier_id   = intval($_POST['supplier_id'] ?? 0);
                $detail_barang = trim($_POST['detail_barang'] ?? '');
                $waktu_masuk   = date('Y-m-d H:i:s');

                if ($supplier_id > 0 && !empty($detail_barang)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO log_akses_supplier (supplier_id, detail_barang, waktu_masuk) VALUES (?, ?, ?)");
                        $stmt->execute([$supplier_id, $detail_barang, $waktu_masuk]);
                        $_SESSION['last_supplier_entry'] = time();
                        $pesan_sukses = "Data barang & kedatangan berhasil dicatat!";
                    } catch (Exception $e) {
                        $pesan_error = "Gagal menyimpan log: " . $e->getMessage();
                    }
                } else {
                    $pesan_error = "Pilih Supplier dan isi detail barang!";
                }
            }
        }
    }

    // ==========================================
    // 6. AMBIL DATA UNTUK TAB 1: MASTER SUPPLIER
    // ==========================================
    $search_keyword = trim($_GET['search'] ?? '');
    $sql = "SELECT * FROM merchant_supplier WHERE 1=1";
    $params = [];

    if (!empty($search_keyword)) {
        $sql .= " AND (nama_supplier LIKE ? OR alamat LIKE ? OR jenis_barang LIKE ? OR no_hp LIKE ?)";
        $searchTerm = "%" . $search_keyword . "%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    $sql .= " ORDER BY supplier_id DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data_supplier = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($data_supplier)) $data_supplier = [];
    } catch (Exception $e) {
        $data_supplier = [];
    }

    // ==========================================
    // 7. AMBIL DATA UNTUK TAB 2: LOG & FILTER
    // ==========================================
    $bulan_ini = date('m');
    $tahun_ini = date('Y');

    try {
        $stmt_tahun = $pdo->query("SELECT DISTINCT YEAR(waktu_masuk) AS tahun FROM log_akses_supplier ORDER BY tahun DESC");
        $list_tahun_log = $stmt_tahun->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $list_tahun_log = [date('Y')];
    }

    $daftar_bulan = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];

    $log_bulan = $_GET['log_bulan'] ?? '';
    $log_tahun = $_GET['log_tahun'] ?? '';

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM log_akses_supplier WHERE MONTH(waktu_masuk) = ? AND YEAR(waktu_masuk) = ?");
    $stmt_count->execute([$bulan_ini, $tahun_ini]);
    $total_masuk_bulan_ini = $stmt_count->fetchColumn();

    $sql_log = "SELECT l.id, l.detail_barang, l.waktu_masuk, m.nama_supplier, m.jenis_barang 
                FROM log_akses_supplier l 
                LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id 
                WHERE 1=1";
    $params_log = [];

    if (!empty($log_bulan)) {
        $sql_log .= " AND MONTH(l.waktu_masuk) = ?";
        $params_log[] = $log_bulan;
    }
    if (!empty($log_tahun)) {
        $sql_log .= " AND YEAR(l.waktu_masuk) = ?";
        $params_log[] = $log_tahun;
    }
    $sql_log .= " ORDER BY l.waktu_masuk DESC";

    try {
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute($params_log);
        $riwayat_akses = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $riwayat_akses = [];
    }

    // ==========================================
    // 8. AMBIL DATA UNTUK TAB 3: MERCHANT_USER
    // ==========================================
    $search_user = trim($_GET['search_user'] ?? '');
    $sql_user = "SELECT * FROM merchant_user WHERE 1=1";
    $params_user = [];

    if (!empty($search_user)) {
        $sql_user .= " AND (username LIKE ? OR nama LIKE ? OR role LIKE ?)";
        $searchUserTerm = "%" . $search_user . "%";
        array_push($params_user, $searchUserTerm, $searchUserTerm, $searchUserTerm);
    }
    $sql_user .= " ORDER BY {$user_pk_col} DESC";

    try {
        $stmt_user = $pdo->prepare($sql_user);
        $stmt_user->execute($params_user);
        $data_users = $stmt_user->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($data_users)) $data_users = [];
    } catch (Exception $e) {
        $data_users = [];
    }

    $last_submit_time = $_SESSION['last_supplier_entry'] ?? 0;
    $time_since_last  = time() - $last_submit_time;
    $initial_cooldown = ($time_since_last < INPUT_COOLDOWN_SECONDS) ? (INPUT_COOLDOWN_SECONDS - $time_since_last) : 0;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_logged_in ? 'Monitoring & Log Supplier' : 'Login - Operasional' ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .table-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
            padding: 20px;
            margin-bottom: 24px;
        }

        .table th {
            background-color: #1F4E78 !important;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge-barang {
            font-size: 0.75rem;
            padding: 5px 10px;
            font-weight: 600;
        }

        .card-custom {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            border: none;
            margin-bottom: 16px;
        }

        .nav-tabs .nav-link {
            font-weight: 600;
            color: #6c757d;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
        }

        .nav-tabs .nav-link.active {
            color: #1F4E78;
            border-bottom: 3px solid #1F4E78;
            background: transparent;
        }

        .log-scroll-area {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .login-page {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 15px;
        }

        .card-login {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 40px 30px;
        }

        .text-primary-custom {
            color: #1F4E78;
        }

        .btn-login {
            background-color: #1F4E78;
            color: white;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background-color: #153959;
            color: white;
        }
    </style>
</head>

<body>

    <?php if (!$is_logged_in): ?>
        <div class="login-page">
            <div class="login-wrapper">
                <div class="card-login">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-shield-halved fa-3x text-primary-custom mb-3"></i>
                        <h4 class="fw-bold text-dark">Portal Operasional</h4>
                        <p class="text-muted small">Silakan login menggunakan akun Admin Anda</p>
                    </div>

                    <?php if (!empty($pesan_error)): ?>
                        <div class="alert alert-danger py-2 small fw-semibold text-center alert-dismissible fade show">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($pesan_error) ?>
                            <button type="button" class="btn-close btn-close-sm" style="padding: 0.75rem;" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="login_action" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                                <input type="text" name="username" class="form-control border-start-0" placeholder="Masukkan username" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-start-0" placeholder="Masukkan password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login w-100"><i class="fa-solid fa-right-to-bracket me-2"></i> Masuk</button>
                    </form>
                </div>
                <div class="text-center mt-4">
                    <small class="text-muted">&copy; <?= date('Y') ?> Divisi Operasional.</small>
                </div>
            </div>
        </div>

    <?php else: ?>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #1F4E78;">
            <div class="container-fluid px-4">
                <a class="navbar-brand fw-bold" href="#"><i class="fa-solid fa-truck-field me-2"></i> Ops Panel</a>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3 small"><i class="fa-regular fa-circle-user me-1"></i> Hai, <?= htmlspecialchars($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
                    <a href="?action=logout" class="btn btn-sm btn-danger"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-3 px-md-4 py-4">
            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($pesan_sukses) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($pesan_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($pesan_error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0"><i class="fa-solid fa-truck-field text-primary me-2"></i> Dashboard Supplier & Area Kios</h4>
            </div>

            <ul class="nav nav-tabs mb-4" id="supplierTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="monitoring-tab" data-bs-toggle="tab" data-bs-target="#monitoring" type="button">
                        <i class="fa-solid fa-users me-1"></i> Data Master Supplier
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="logkios-tab" data-bs-toggle="tab" data-bs-target="#logkios" type="button">
                        <i class="fa-solid fa-store me-1"></i> Area Kios & Log Kedatangan
                    </button>
                </li>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <li class="nav-item">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">
                            <i class="fa-solid fa-user-shield me-1"></i> Manajemen User
                        </button>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content">
                <!-- TAB 1: MASTER DATA SUPPLIER -->
                <div class="tab-pane fade show active" id="monitoring">
                    <div class="table-container">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <div class="col-md-8 col-sm-8">
                                <label class="form-label small fw-bold"><i class="fa-solid fa-magnifying-glass text-primary me-1"></i> Pencarian Master Supplier</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari berdasarkan nama supplier, alamat, atau jenis barang..." value="<?= htmlspecialchars($search_keyword) ?>">
                            </div>
                            <div class="col-md-2 col-sm-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-search me-1"></i> Cari</button>
                                <a href="admin_supplierr.php" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fa-solid fa-rotate-right"></i></a>
                            </div>
                        </form>
                    </div>

                    <div class="table-container">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                            <p class="text-muted small mb-0">Total supplier: <strong><?= count($data_supplier) ?></strong> record.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <button type="button" class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="fa-solid fa-plus me-1"></i> Tambah Supplier Baru</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead>
                                    <tr class="text-center">
                                        <th width="5%">ID</th>
                                        <th width="25%">Nama Supplier</th>
                                        <th width="25%">Alamat</th>
                                        <th width="15%">No. Handphone</th>
                                        <th width="15%">Jenis Barang</th>
                                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><th width="15%">Aksi</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($data_supplier) > 0): ?>
                                        <?php foreach ($data_supplier as $row): ?>
                                            <tr>
                                                <td class="text-center fw-bold"><?= $row['supplier_id'] ?></td>
                                                <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_supplier'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['alamat'] ?? '-') ?></td>
                                                <td>
                                                    <?php if (!empty($row['no_hp'])): ?>
                                                        <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['no_hp'])) ?>" target="_blank" class="text-decoration-none text-success fw-semibold"><i class="fa-brands fa-whatsapp me-1"></i><?= htmlspecialchars($row['no_hp']) ?></a>
                                                        <?php else: ?>-<?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-info text-dark badge-barang"><?= htmlspecialchars($row['jenis_barang'] ?? '-') ?></span></td>
                                                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-warning me-1 btn-edit" data-id="<?= $row['supplier_id'] ?>" data-nama="<?= htmlspecialchars($row['nama_supplier'] ?? '', ENT_QUOTES) ?>" data-alamat="<?= htmlspecialchars($row['alamat'] ?? '', ENT_QUOTES) ?>" data-nohp="<?= htmlspecialchars($row['no_hp'] ?? '', ENT_QUOTES) ?>" data-jenis="<?= htmlspecialchars($row['jenis_barang'] ?? '', ENT_QUOTES) ?>"><i class="fa-solid fa-pen-to-square"></i></button>
                                                        <button class="btn btn-sm btn-outline-secondary me-1 btn-edit-password" data-id="<?= $row['supplier_id'] ?>" data-nama="<?= htmlspecialchars($row['nama_supplier'] ?? '', ENT_QUOTES) ?>" title="Ganti Password"><i class="fa-solid fa-key"></i></button>
                                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus supplier ini?');">
                                                            <input type="hidden" name="action" value="hapus"><input type="hidden" name="supplier_id" value="<?= $row['supplier_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></button>
                                                        </form>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">Tidak ada data supplier.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: AREA KIOS & LOG -->
                <div class="tab-pane fade" id="logkios">
                    <div class="row">
                        <div class="col-md-5 col-lg-4">
                            <div class="card card-custom p-4 bg-primary text-white border-0 mb-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div><small class="text-white-50 fw-bold d-block mb-1">KEDATANGAN BULAN INI</small>
                                        <h2 class="fw-bold mb-0 text-white"><?= $total_masuk_bulan_ini ?> <span class="fs-6 fw-normal">Kali</span></h2>
                                    </div>
                                    <i class="fa-solid fa-calendar-check fa-3x text-white-50"></i>
                                </div>
                            </div>
                            <div class="card card-custom p-4 border-start border-4 border-success">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-box-open text-primary me-2"></i> Lapor Kedatangan Baru</h6>
                                    <small id="cooldown-badge" class="badge bg-warning text-dark d-none">Jeda</small>
                                </div>
                                <form id="formInputBarang" method="POST" action="">
                                    <input type="hidden" name="action" value="simpan_akses">
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">Pilih Supplier <span class="text-danger">*</span></label>
                                        <select name="supplier_id" class="form-select" required>
                                            <option value="">-- Pilih Supplier --</option>
                                            <?php
                                            $stmt_all = $pdo->query("SELECT supplier_id, nama_supplier, jenis_barang FROM merchant_supplier ORDER BY nama_supplier ASC");
                                            while ($sup = $stmt_all->fetch()) {
                                                echo "<option value='{$sup['supplier_id']}'>{$sup['nama_supplier']} ({$sup['jenis_barang']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">Detail Barang / Pasokan <span class="text-danger">*</span></label>
                                        <textarea name="detail_barang" class="form-control bg-light" rows="3" placeholder="Contoh: 20 Batik, 5 Kardus Air..." required></textarea>
                                    </div>
                                    <button type="submit" id="btnSubmitForm" class="btn btn-success w-100 py-2 fw-bold" disabled><i class="fa-solid fa-paper-plane me-1"></i> Simpan Kedatangan</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-7 col-lg-8">
                            <div class="card card-custom p-4 h-100">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-table-list text-primary me-2"></i> Riwayat Kedatangan</h6>
                                    <span class="badge bg-secondary"><?= count($riwayat_akses) ?> Data Ditemukan</span>
                                </div>

                                <form method="GET" action="" class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <select name="log_bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">-- Semua Bulan --</option>
                                            <?php foreach ($daftar_bulan as $key => $nama_bulan): ?>
                                                <option value="<?= $key ?>" <?= ($log_bulan === $key) ? 'selected' : '' ?>><?= $nama_bulan ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="log_tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">-- Semua Tahun --</option>
                                            <?php foreach ($list_tahun_log as $th): ?>
                                                <option value="<?= $th ?>" <?= ($log_tahun == $th) ? 'selected' : '' ?>><?= $th ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5 d-flex gap-2">
                                        <a href="?export=excel&log_bulan=<?= $log_bulan ?>&log_tahun=<?= $log_tahun ?>" class="btn btn-success btn-sm w-100"><i class="fa-solid fa-file-excel"></i> Export</a>
                                    </div>
                                </form>

                                <div class="log-scroll-area mt-2">
                                    <?php if (empty($riwayat_akses)): ?>
                                        <div class="text-center text-muted py-5 border rounded bg-light">Tidak ada riwayat kedatangan yang sesuai dengan filter.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle border">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Waktu</th>
                                                        <th>Supplier</th>
                                                        <th>Bawaan</th>
                                                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                                            <th class="text-center" width="10%">Aksi</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($riwayat_akses as $log): ?>
                                                        <tr>
                                                            <td class="text-nowrap" style="font-size:0.85rem;">
                                                                <strong><?= date('d M Y', strtotime($log['waktu_masuk'])) ?></strong><br>
                                                                <span class="text-muted"><?= date('H:i', strtotime($log['waktu_masuk'])) ?> WIB</span>
                                                            </td>
                                                            <td>
                                                                <span class="fw-bold text-primary"><?= htmlspecialchars($log['nama_supplier'] ?? 'Unknown') ?></span><br>
                                                                <span class="badge bg-info text-dark" style="font-size:0.7rem;"><?= htmlspecialchars($log['jenis_barang'] ?? '-') ?></span>
                                                            </td>
                                                            <td style="font-size:0.85rem;"><?= nl2br(htmlspecialchars($log['detail_barang'])) ?></td>
                                                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                                                <td class="text-center">
                                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data log kedatangan ini?');">
                                                                        <input type="hidden" name="action" value="hapus_log">
                                                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Log"><i class="fa-solid fa-trash-can"></i></button>
                                                                    </form>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: MANAJEMEN USER (MERCHANT_USER) -->
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <div class="tab-pane fade" id="users">
                        <div class="table-container">
                            <form method="GET" action="" class="row g-3 align-items-end">
                                <div class="col-md-8 col-sm-8">
                                    <label class="form-label small fw-bold"><i class="fa-solid fa-magnifying-glass text-primary me-1"></i> Pencarian User</label>
                                    <input type="text" name="search_user" class="form-control form-control-sm" placeholder="Cari berdasarkan username, nama, atau role..." value="<?= htmlspecialchars($search_user) ?>">
                                </div>
                                <div class="col-md-2 col-sm-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-search me-1"></i> Cari</button>
                                    <a href="admin_supplierr.php?tab=users" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fa-solid fa-rotate-right"></i></a>
                                </div>
                            </form>
                        </div>

                        <div class="table-container">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                                <p class="text-muted small mb-0">Total user terdaftar: <strong><?= count($data_users) ?></strong> akun.</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
                                        <i class="fa-solid fa-user-plus me-1"></i> Tambah User Baru
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle">
                                    <thead>
                                        <tr class="text-center">
                                            <th width="8%">ID</th>
                                            <th width="25%">Username</th>
                                            <th width="32%">Nama Lengkap</th>
                                            <th width="15%">Role</th>
                                            <th width="20%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($data_users) > 0): ?>
                                            <?php foreach ($data_users as $u): ?>
                                                <?php $uid = $u[$user_pk_col] ?? 0; ?>
                                                <tr>
                                                    <td class="text-center fw-bold"><?= $uid ?></td>
                                                    <td class="fw-semibold text-primary"><?= htmlspecialchars($u['username'] ?? '-') ?></td>
                                                    <td class="text-dark"><?= htmlspecialchars($u['nama'] ?? '-') ?></td>
                                                    <td class="text-center">
                                                        <?php $u_role = $u['role'] ?? 'admin'; ?>
                                                        <span class="badge <?= $u_role === 'admin' ? 'bg-primary' : 'bg-secondary' ?> badge-barang">
                                                            <?= htmlspecialchars($u_role) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-warning me-1 btn-edit-user"
                                                            data-id="<?= $uid ?>"
                                                            data-username="<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>"
                                                            data-nama="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES) ?>"
                                                            data-role="<?= htmlspecialchars($u_role, ENT_QUOTES) ?>"
                                                            title="Edit User">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary me-1 btn-edit-password-user"
                                                            data-id="<?= $uid ?>"
                                                            data-username="<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>"
                                                            title="Ganti Password User">
                                                            <i class="fa-solid fa-key"></i>
                                                        </button>
                                                        <?php if ($uid != ($_SESSION['id_user'] ?? 0)): ?>
                                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user <?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>?');">
                                                                <input type="hidden" name="action" value="hapus_user">
                                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus User">
                                                                    <i class="fa-solid fa-trash-can"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Sedang Login">
                                                                <i class="fa-solid fa-user-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">Tidak ada data user.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <!-- MODAL SUPPLIER -->
            <div class="modal fade" id="modalTambah" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Tambah Supplier Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="action" value="tambah">
                            <div class="col-12"><label class="form-label small fw-bold">Nama Supplier</label><input type="text" name="nama_supplier" class="form-control form-control-sm" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Alamat</label><input type="text" name="alamat" class="form-control form-control-sm"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No. Handphone</label><input type="text" name="no_hp" class="form-control form-control-sm"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Jenis Barang</label><input type="text" name="jenis_barang" class="form-control form-control-sm" required></div>
                            <div class="col-12"><small class="text-muted d-block mt-2">* Password akan diset default: "12345"</small></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success btn-sm">Simpan</button></div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalEdit" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Data Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="action" value="edit"><input type="hidden" name="supplier_id" id="edit_id">
                            <div class="col-12"><label class="form-label small fw-bold">Nama Supplier</label><input type="text" name="nama_supplier" id="edit_nama" class="form-control form-control-sm" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Alamat</label><input type="text" name="alamat" id="edit_alamat" class="form-control form-control-sm"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No. Handphone</label><input type="text" name="no_hp" id="edit_nohp" class="form-control form-control-sm"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Jenis Barang</label><input type="text" name="jenis_barang" id="edit_jenis" class="form-control form-control-sm" required></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-warning btn-sm">Perbarui</button></div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalEditPassword" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-secondary text-white">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-key me-2"></i> Ganti Password Supplier</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="action" value="edit_password">
                            <input type="hidden" name="supplier_id" id="edit_pass_id">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Nama Supplier</label>
                                <input type="text" id="edit_pass_nama" class="form-control form-control-sm" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Password Baru <span class="text-danger">*</span></label>
                                <input type="text" name="password_baru" class="form-control form-control-sm" placeholder="Masukkan password baru..." required>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan Password</button></div>
                    </form>
                </div>
            </div>

            <!-- MODAL MERCHANT_USER -->
            <div class="modal fade" id="modalTambahUser" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Tambah User Baru</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="action" value="tambah_user">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control form-control-sm" placeholder="Masukkan username login" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control form-control-sm" placeholder="Masukkan nama lengkap" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select form-select-sm" required>
                                    <option value="admin">admin</option>
                                    <option value="admin_dept">admin_dept</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control form-control-sm" placeholder="Masukkan password" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm">Simpan User</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalEditUser" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-user-pen me-2"></i> Edit Data User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" id="edit_user_id">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" id="edit_user_username" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" id="edit_user_nama" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Role <span class="text-danger">*</span></label>
                                <select name="role" id="edit_user_role" class="form-select form-select-sm" required>
                                    <option value="admin">admin</option>
                                    <option value="admin_dept">admin_dept</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning btn-sm">Perbarui User</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalEditPasswordUser" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-secondary text-white">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-key me-2"></i> Ganti Password User</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="action" value="edit_password_user">
                            <input type="hidden" name="user_id" id="edit_pass_user_id">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Username</label>
                                <input type="text" id="edit_pass_user_username" class="form-control form-control-sm" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Password Baru <span class="text-danger">*</span></label>
                                <input type="password" name="password_baru" class="form-control form-control-sm" placeholder="Masukkan password baru..." required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Password</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($is_logged_in): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Modal Edit Supplier
                const editModalEl = document.getElementById('modalEdit');
                if (editModalEl) {
                    const editModal = new bootstrap.Modal(editModalEl);
                    document.querySelectorAll('.btn-edit').forEach(button => {
                        button.addEventListener('click', function() {
                            document.getElementById('edit_id').value = this.dataset.id;
                            document.getElementById('edit_nama').value = this.dataset.nama;
                            document.getElementById('edit_alamat').value = this.dataset.alamat;
                            document.getElementById('edit_nohp').value = this.dataset.nohp;
                            document.getElementById('edit_jenis').value = this.dataset.jenis;
                            editModal.show();
                        });
                    });
                }

                // Modal Edit Password Supplier
                const editPassModalEl = document.getElementById('modalEditPassword');
                if (editPassModalEl) {
                    const editPassModal = new bootstrap.Modal(editPassModalEl);
                    document.querySelectorAll('.btn-edit-password').forEach(button => {
                        button.addEventListener('click', function() {
                            document.getElementById('edit_pass_id').value = this.dataset.id;
                            document.getElementById('edit_pass_nama').value = this.dataset.nama;
                            editPassModal.show();
                        });
                    });
                }

                // Modal Edit User (merchant_user)
                const editUserModalEl = document.getElementById('modalEditUser');
                if (editUserModalEl) {
                    const editUserModal = new bootstrap.Modal(editUserModalEl);
                    document.querySelectorAll('.btn-edit-user').forEach(button => {
                        button.addEventListener('click', function() {
                            document.getElementById('edit_user_id').value = this.dataset.id;
                            document.getElementById('edit_user_username').value = this.dataset.username;
                            document.getElementById('edit_user_nama').value = this.dataset.nama;
                            document.getElementById('edit_user_role').value = this.dataset.role;
                            editUserModal.show();
                        });
                    });
                }

                // Modal Edit Password User (merchant_user)
                const editPassUserModalEl = document.getElementById('modalEditPasswordUser');
                if (editPassUserModalEl) {
                    const editPassUserModal = new bootstrap.Modal(editPassUserModalEl);
                    document.querySelectorAll('.btn-edit-password-user').forEach(button => {
                        button.addEventListener('click', function() {
                            document.getElementById('edit_pass_user_id').value = this.dataset.id;
                            document.getElementById('edit_pass_user_username').value = this.dataset.username;
                            editPassUserModal.show();
                        });
                    });
                }

                // Auto-switch Tab berdasarkan aksi terakhir
                <?php if (isset($_POST['action']) && in_array($_POST['action'], ['simpan_akses', 'hapus_log'])): ?>
                    var logTab = new bootstrap.Tab(document.querySelector('#logkios-tab'));
                    logTab.show();
                <?php elseif (isset($_GET['log_bulan']) || isset($_GET['log_tahun'])): ?>
                    var logTab = new bootstrap.Tab(document.querySelector('#logkios-tab'));
                    logTab.show();
                <?php elseif ((isset($_POST['action']) && in_array($_POST['action'], ['tambah_user', 'edit_user', 'edit_password_user', 'hapus_user'])) || isset($_GET['search_user']) || (($_GET['tab'] ?? '') === 'users')): ?>
                    var usersTabEl = document.querySelector('#users-tab');
                    if (usersTabEl) {
                        var usersTab = new bootstrap.Tab(usersTabEl);
                        usersTab.show();
                    }
                <?php endif; ?>

                let cooldownTimer = <?= $initial_cooldown ?>;

                function updateSubmitButtonState() {
                    const submitBtn = document.getElementById('btnSubmitForm');
                    const cooldownBadge = document.getElementById('cooldown-badge');
                    if (!submitBtn) return;

                    if (cooldownTimer > 0) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = `<i class="fa-solid fa-clock fa-spin me-1"></i> Tunggu (${cooldownTimer}s)`;
                        cooldownBadge.classList.remove('d-none');
                        cooldownBadge.innerText = `Jeda ${cooldownTimer}s`;
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = `<i class="fa-solid fa-paper-plane me-1"></i> Simpan Kedatangan`;
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
            });
        </script>
    <?php endif; ?>

</body>

</html>
