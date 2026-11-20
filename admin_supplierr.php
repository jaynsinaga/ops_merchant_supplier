<?php
// TAMPILKAN ERROR UNTUK DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 1. KONEKSI DATABASE & KONFIGURASI
// ==========================================
require_once '../../config/database.php';

$pesan_sukses = '';
$pesan_error  = '';

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
            $stmt = $pdo->prepare("SELECT * FROM merchant_user WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $is_password_valid = false;
                if (password_verify($password, $user['password'])) {
                    $is_password_valid = true;
                } elseif ($user['password'] === md5($password)) {
                    $is_password_valid = true;
                }

                if ($is_password_valid) {
                    $_SESSION['id_user']  = $user['id'] ?? $user['id_user'] ?? $user['username'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama']     = $user['nama'];
                    $_SESSION['role']     = $user['role'] ?? 'admin';

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

if ($is_logged_in) {

    // Deteksi kolom primary key tabel merchant_user ('id' atau 'id_user')
    $user_pk_col = 'id';
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM merchant_user")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('id_user', $cols)) {
            $user_pk_col = 'id_user';
        }
    } catch (Exception $e) {
        $user_pk_col = 'id';
    }

    // Deteksi kolom primary key tabel merchant_log_akses_supplier ('id' atau 'log_id')
    $log_pk_col = 'id';
    try {
        $log_cols = $pdo->query("SHOW COLUMNS FROM merchant_log_akses_supplier")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('log_id', $log_cols)) {
            $log_pk_col = 'log_id';
        }
    } catch (Exception $e) {
        $log_pk_col = 'id';
    }

    // ==========================================
    // 4. FITUR EXPORT EXCEL (TAB 2 LOG KIOS - 1 BARIS PER KEDATANGAN)
    // ==========================================
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        $log_bulan  = $_GET['log_bulan'] ?? '';
        $log_tahun  = $_GET['log_tahun'] ?? '';
        $log_search = trim($_GET['log_search'] ?? '');

        $sql_export = "SELECT l.waktu_masuk, 
                              m.nama_supplier, 
                              kb.nama_kategori,
                              GROUP_CONCAT(
                                  NULLIF(TRIM(msb.nama_barang), '') 
                                  ORDER BY l.{$log_pk_col} ASC SEPARATOR ', '
                              ) AS daftar_jenis_barang,
                              GROUP_CONCAT(
                                  CASE 
                                      WHEN l.jumlah > 0 THEN CONCAT(
                                          COALESCE(msb.nama_barang, 'Barang'), ' (', l.jumlah, 
                                          IF(sb.nama_satuan IS NOT NULL AND sb.nama_satuan != '', CONCAT(' ', sb.nama_satuan), ''), 
                                          ')'
                                      )
                                      ELSE NULL 
                                  END
                                  ORDER BY l.{$log_pk_col} ASC SEPARATOR ', '
                              ) AS rincian_bawaan
                       FROM merchant_log_akses_supplier l 
                       LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id 
                       LEFT JOIN merchant_supplier_barang msb ON l.barang_id = msb.barang_id
                       LEFT JOIN merchant_kategori_barang kb ON m.kategori_id = kb.kategori_id
                       LEFT JOIN merchant_satuan_barang sb ON l.satuan_id = sb.satuan_id
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
        if (!empty($log_search)) {
            $sql_export .= " AND (m.nama_supplier LIKE ? OR msb.nama_barang LIKE ? OR kb.nama_kategori LIKE ?)";
            $term = "%" . $log_search . "%";
            array_push($params_export, $term, $term, $term);
        }
        $sql_export .= " GROUP BY l.supplier_id, l.waktu_masuk ORDER BY l.waktu_masuk DESC";

        $stmt_ex = $pdo->prepare($sql_export);
        $stmt_ex->execute($params_export);
        $data_export = $stmt_ex->fetchAll(PDO::FETCH_ASSOC);

        header("Content-type: application/vnd-ms-excel");
        header("Content-Disposition: attachment; filename=Data_Log_Kios_" . date('Ymd_His') . ".xls");
?>
        <table border="1">
            <tr>
                <th style="background-color: #1F4E78; color: white;">No</th>
                <th style="background-color: #1F4E78; color: white;">Waktu Kedatangan</th>
                <th style="background-color: #1F4E78; color: white;">Nama Supplier</th>
                <th style="background-color: #1F4E78; color: white;">Kategori Barang</th>
                <th style="background-color: #1F4E78; color: white;">Jenis Barang</th>
                <th style="background-color: #1F4E78; color: white;">Rincian Jumlah Bawaan</th>
            </tr>
            <?php
            $no = 1;
            foreach ($data_export as $row) {
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . date('d-m-Y H:i:s', strtotime($row['waktu_masuk'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_supplier'] ?? 'Telah Dihapus') . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_kategori'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars(!empty($row['daftar_jenis_barang']) ? $row['daftar_jenis_barang'] : '-') . "</td>";
                echo "<td>" . htmlspecialchars(!empty($row['rincian_bawaan']) ? $row['rincian_bawaan'] : '-') . "</td>";
                echo "</tr>";
            }
            ?>
        </table>
<?php
        exit;
    }

    define('INPUT_COOLDOWN_SECONDS', 60);

    // ==========================================
    // 5. PROSES POST (CRUD SUPPLIER, LOG, KATEGORI, SATUAN & USER)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        $admin_actions = [
            'tambah',
            'edit',
            'hapus',
            'edit_password',
            'hapus_log',
            'tambah_kategori',
            'edit_kategori',
            'hapus_kategori',
            'tambah_satuan',
            'edit_satuan',
            'hapus_satuan',
            'tambah_user',
            'edit_user',
            'edit_password_user',
            'hapus_user'
        ];

        if (in_array($action, $admin_actions)) {
            if ($_SESSION['role'] !== 'admin') {
                $pesan_error = "Akses ditolak. Hanya Admin yang dapat mengubah data.";
            } else {
                // --- CRUD SUPPLIER ---
                if ($action === 'tambah') {
                    $nama         = trim(preg_replace('/\s+/', ' ', $_POST['nama_supplier'] ?? ''));
                    $alamat       = trim($_POST['alamat'] ?? '');
                    $no_hp        = trim($_POST['no_hp'] ?? '');
                    $jenis_barang = trim($_POST['jenis_barang'] ?? '');
                    $kategori_id  = !empty($_POST['kategori_id']) ? intval($_POST['kategori_id']) : null;
                    // Password default: nama supplier tanpa spasi
                    $password_def = str_replace(' ', '', $nama);

                    if (!empty($nama)) {
                        try {
                            // Cek duplikasi nama supplier (case-insensitive & abaikan spasi berlebih)
                            $stmtCekNama = $pdo->prepare("SELECT COUNT(*) FROM merchant_supplier WHERE LOWER(TRIM(nama_supplier)) = LOWER(?)");
                            $stmtCekNama->execute([$nama]);

                            if ($stmtCekNama->fetchColumn() > 0) {
                                $pesan_error = "Gagal menambah: Nama supplier '{$nama}' sudah terdaftar di sistem!";
                            } else {
                                $pdo->beginTransaction();

                                $stmt = $pdo->prepare("INSERT INTO merchant_supplier (nama_supplier, alamat, no_hp, kategori_id, password) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$nama, $alamat, $no_hp, $kategori_id, $password_def]);
                                $new_supplier_id = $pdo->lastInsertId();

                                // Simpan daftar barang ke merchant_supplier_barang (dipisahkan koma jika lebih dari 1)
                                if (!empty($jenis_barang)) {
                                    $items = array_filter(array_map('trim', explode(',', $jenis_barang)));
                                    $stmtBrg = $pdo->prepare("INSERT INTO merchant_supplier_barang (supplier_id, nama_barang) VALUES (?, ?)");
                                    foreach ($items as $item) {
                                        $stmtBrg->execute([$new_supplier_id, $item]);
                                    }
                                }

                                $pdo->commit();
                                $pesan_sukses = "Data supplier baru berhasil ditambahkan! (Password default: {$password_def})";
                            }
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $pesan_error = "Gagal menambah supplier: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Nama Supplier wajib diisi!";
                    }
                } elseif ($action === 'edit') {
                    $supplier_id  = intval($_POST['supplier_id'] ?? 0);
                    $nama         = trim(preg_replace('/\s+/', ' ', $_POST['nama_supplier'] ?? ''));
                    $alamat       = trim($_POST['alamat'] ?? '');
                    $no_hp        = trim($_POST['no_hp'] ?? '');
                    $jenis_barang = trim($_POST['jenis_barang'] ?? '');
                    $kategori_id  = !empty($_POST['kategori_id']) ? intval($_POST['kategori_id']) : null;

                    if ($supplier_id > 0 && !empty($nama)) {
                        try {
                            // Cek duplikasi nama supplier milik supplier lain (case-insensitive)
                            $stmtCekNama = $pdo->prepare("SELECT COUNT(*) FROM merchant_supplier WHERE LOWER(TRIM(nama_supplier)) = LOWER(?) AND supplier_id != ?");
                            $stmtCekNama->execute([$nama, $supplier_id]);

                            if ($stmtCekNama->fetchColumn() > 0) {
                                $pesan_error = "Gagal memperbarui: Nama supplier '{$nama}' sudah digunakan oleh supplier lain!";
                            } else {
                                $pdo->beginTransaction();

                                $stmt = $pdo->prepare("UPDATE merchant_supplier SET nama_supplier = ?, alamat = ?, no_hp = ?, kategori_id = ? WHERE supplier_id = ?");
                                $stmt->execute([$nama, $alamat, $no_hp, $kategori_id, $supplier_id]);

                                // Sinkronkan daftar barang di merchant_supplier_barang
                                $new_items = array_filter(array_map('trim', explode(',', $jenis_barang)));
                                $stmtExisting = $pdo->prepare("SELECT barang_id, nama_barang FROM merchant_supplier_barang WHERE supplier_id = ?");
                                $stmtExisting->execute([$supplier_id]);
                                $existing_rows = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);

                                $existing_lower = [];
                                foreach ($existing_rows as $er) {
                                    $existing_lower[strtolower($er['nama_barang'])] = $er['barang_id'];
                                }

                                $new_lower = array_map('strtolower', $new_items);

                                // Hapus barang yang dihapus dari input
                                $stmtDelBrg = $pdo->prepare("DELETE FROM merchant_supplier_barang WHERE barang_id = ?");
                                foreach ($existing_rows as $er) {
                                    if (!in_array(strtolower($er['nama_barang']), $new_lower)) {
                                        $stmtDelBrg->execute([$er['barang_id']]);
                                    }
                                }

                                // Tambah barang baru yang belum ada
                                $stmtInsBrg = $pdo->prepare("INSERT INTO merchant_supplier_barang (supplier_id, nama_barang) VALUES (?, ?)");
                                foreach ($new_items as $ni) {
                                    if (!isset($existing_lower[strtolower($ni)])) {
                                        $stmtInsBrg->execute([$supplier_id, $ni]);
                                    }
                                }

                                $pdo->commit();
                                $pesan_sukses = "Data supplier berhasil diperbarui!";
                            }
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $pesan_error = "Gagal memperbarui supplier: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Data tidak valid. Nama Supplier wajib diisi!";
                    }
                } elseif ($action === 'edit_password') {
                    $supplier_id   = intval($_POST['supplier_id'] ?? 0);
                    $password_baru = trim($_POST['password_baru'] ?? '');

                    if ($supplier_id > 0 && !empty($password_baru)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE merchant_supplier SET password = ? WHERE supplier_id = ?");
                            $stmt->execute([$password_baru, $supplier_id]);
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
                    // Hapus semua record log pada sesi kedatangan tersebut (1 baris = bisa terdiri dari beberapa ID log)
                    $raw_ids = trim($_POST['log_ids'] ?? '');
                    $id_list = array_filter(array_map('intval', explode(',', $raw_ids)));

                    if (!empty($id_list)) {
                        try {
                            $placeholders = implode(',', array_fill(0, count($id_list), '?'));
                            $stmt = $pdo->prepare("DELETE FROM merchant_log_akses_supplier WHERE {$log_pk_col} IN ({$placeholders})");
                            $stmt->execute($id_list);
                            $pesan_sukses = "Data log kedatangan berhasil dihapus!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menghapus log kedatangan: " . $e->getMessage();
                        }
                    }

                    // --- CRUD KATEGORI BARANG (merchant_kategori_barang) ---
                } elseif ($action === 'tambah_kategori') {
                    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
                    if (!empty($nama_kategori)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO merchant_kategori_barang (nama_kategori) VALUES (?)");
                            $stmt->execute([$nama_kategori]);
                            $pesan_sukses = "Kategori barang berhasil ditambahkan!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menambah kategori: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Nama kategori wajib diisi!";
                    }
                } elseif ($action === 'edit_kategori') {
                    $kategori_id   = intval($_POST['kategori_id'] ?? 0);
                    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
                    if ($kategori_id > 0 && !empty($nama_kategori)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE merchant_kategori_barang SET nama_kategori = ? WHERE kategori_id = ?");
                            $stmt->execute([$nama_kategori, $kategori_id]);
                            $pesan_sukses = "Kategori barang berhasil diperbarui!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal memperbarui kategori: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Nama kategori wajib diisi!";
                    }
                } elseif ($action === 'hapus_kategori') {
                    $kategori_id = intval($_POST['kategori_id'] ?? 0);
                    if ($kategori_id > 0) {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM merchant_kategori_barang WHERE kategori_id = ?");
                            $stmt->execute([$kategori_id]);
                            $pesan_sukses = "Kategori barang berhasil dihapus!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menghapus kategori: " . $e->getMessage();
                        }
                    }

                    // --- CRUD SATUAN BARANG (merchant_satuan_barang) ---
                } elseif ($action === 'tambah_satuan') {
                    $nama_satuan = trim($_POST['nama_satuan'] ?? '');
                    if (!empty($nama_satuan)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO merchant_satuan_barang (nama_satuan) VALUES (?)");
                            $stmt->execute([$nama_satuan]);
                            $pesan_sukses = "Satuan barang berhasil ditambahkan!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menambah satuan: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Nama satuan wajib diisi!";
                    }
                } elseif ($action === 'edit_satuan') {
                    $satuan_id   = intval($_POST['satuan_id'] ?? 0);
                    $nama_satuan = trim($_POST['nama_satuan'] ?? '');
                    if ($satuan_id > 0 && !empty($nama_satuan)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE merchant_satuan_barang SET nama_satuan = ? WHERE satuan_id = ?");
                            $stmt->execute([$nama_satuan, $satuan_id]);
                            $pesan_sukses = "Satuan barang berhasil diperbarui!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal memperbarui satuan: " . $e->getMessage();
                        }
                    } else {
                        $pesan_error = "Nama satuan wajib diisi!";
                    }
                } elseif ($action === 'hapus_satuan') {
                    $satuan_id = intval($_POST['satuan_id'] ?? 0);
                    if ($satuan_id > 0) {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM merchant_satuan_barang WHERE satuan_id = ?");
                            $stmt->execute([$satuan_id]);
                            $pesan_sukses = "Satuan barang berhasil dihapus!";
                        } catch (Exception $e) {
                            $pesan_error = "Gagal menghapus satuan: " . $e->getMessage();
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
                $supplier_id = intval($_POST['supplier_id'] ?? 0);
                $barang_id   = filter_var($_POST['barang_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $raw_jumlah  = trim($_POST['jumlah'] ?? '');
                $jumlah      = ($raw_jumlah !== '' && is_numeric($raw_jumlah)) ? (int)$raw_jumlah : 0;
                $satuan_id   = filter_var($_POST['satuan_id'] ?? null, FILTER_VALIDATE_INT);
                $waktu_masuk = date('Y-m-d H:i:s');

                if ($supplier_id > 0) {
                    try {
                        if ($jumlah <= 0 || !$satuan_id) {
                            $jumlah    = 0;
                            $satuan_id = null;
                            $barang_id = null;
                        }
                        $stmt = $pdo->prepare("INSERT INTO merchant_log_akses_supplier (supplier_id, barang_id, jumlah, satuan_id, waktu_masuk) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$supplier_id, $barang_id, $jumlah, $satuan_id, $waktu_masuk]);
                        $_SESSION['last_supplier_entry'] = time();
                        $pesan_sukses = "Data kedatangan supplier berhasil dicatat!";
                    } catch (Exception $e) {
                        $pesan_error = "Gagal menyimpan log: " . $e->getMessage();
                    }
                } else {
                    $pesan_error = "Pilih Supplier terlebih dahulu!";
                }
            }
        }
    }

    // ==========================================
    // 6. AMBIL DATA MASTER KATEGORI & SATUAN
    // ==========================================
    try {
        $data_kategori = $pdo->query("SELECT * FROM merchant_kategori_barang ORDER BY kategori_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $data_kategori = [];
    }

    try {
        $data_satuan = $pdo->query("SELECT * FROM merchant_satuan_barang ORDER BY satuan_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $data_satuan = [];
    }

    // ==========================================
    // 7. AMBIL DATA UNTUK TAB 1: MASTER SUPPLIER
    // ==========================================
    $search_keyword = trim($_GET['search'] ?? '');
    $sql = "SELECT ms.*, kb.nama_kategori,
                   GROUP_CONCAT(msb.nama_barang ORDER BY msb.barang_id ASC SEPARATOR ', ') AS daftar_barang
            FROM merchant_supplier ms 
            LEFT JOIN merchant_kategori_barang kb ON ms.kategori_id = kb.kategori_id 
            LEFT JOIN merchant_supplier_barang msb ON ms.supplier_id = msb.supplier_id
            WHERE 1=1";
    $params = [];

    if (!empty($search_keyword)) {
        $sql .= " AND (ms.nama_supplier LIKE ? OR ms.alamat LIKE ? OR msb.nama_barang LIKE ? OR ms.no_hp LIKE ? OR kb.nama_kategori LIKE ?)";
        $searchTerm = "%" . $search_keyword . "%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    $sql .= " GROUP BY ms.supplier_id ORDER BY ms.supplier_id DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data_supplier = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($data_supplier)) $data_supplier = [];
    } catch (Exception $e) {
        $data_supplier = [];
    }

    // Ambil mapping barang per supplier untuk dropdown dinamis di Tab 2
    $barang_per_supplier = [];
    try {
        $all_brg = $pdo->query("SELECT barang_id, supplier_id, nama_barang FROM merchant_supplier_barang ORDER BY nama_barang ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_brg as $b) {
            $barang_per_supplier[$b['supplier_id']][] = [
                'id'   => $b['barang_id'],
                'nama' => $b['nama_barang']
            ];
        }
    } catch (Exception $e) {
        $barang_per_supplier = [];
    }

    // ==========================================
    // 8. AMBIL DATA UNTUK TAB 2: LOG & FILTER
    // ==========================================
    $bulan_ini = date('m');
    $tahun_ini = date('Y');

    try {
        $stmt_tahun = $pdo->query("SELECT DISTINCT YEAR(waktu_masuk) AS tahun FROM merchant_log_akses_supplier ORDER BY tahun DESC");
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
    $log_bulan  = $_GET['log_bulan'] ?? '';
    $log_tahun  = $_GET['log_tahun'] ?? '';
    $log_search = trim($_GET['log_search'] ?? '');

    // 1. Filter untuk tabel Riwayat Kedatangan
    $where_log  = " WHERE 1=1 ";
    $params_log = [];

    if (!empty($log_bulan)) {
        $where_log .= " AND MONTH(l.waktu_masuk) = ? ";
        $params_log[] = $log_bulan;
    }
    if (!empty($log_tahun)) {
        $where_log .= " AND YEAR(l.waktu_masuk) = ? ";
        $params_log[] = $log_tahun;
    }
    if (!empty($log_search)) {
        $where_log .= " AND (m.nama_supplier LIKE ? OR msb.nama_barang LIKE ? OR kb.nama_kategori LIKE ?) ";
        $term = "%" . $log_search . "%";
        array_push($params_log, $term, $term, $term);
    }

    // 2. Filter untuk Kartu Ringkasan Statistik
    if (empty($log_bulan) && empty($log_tahun) && empty($log_search)) {
        $where_stat    = " WHERE MONTH(l.waktu_masuk) = ? AND YEAR(l.waktu_masuk) = ? ";
        $params_stat   = [$bulan_ini, $tahun_ini];
        $label_periode = "BULAN INI (" . strtoupper($daftar_bulan[$bulan_ini]) . ")";
    } else {
        $where_stat  = $where_log;
        $params_stat = $params_log;

        $label_parts = [];
        if (!empty($log_search)) $label_parts[] = '"' . strtoupper($log_search) . '"';
        if (!empty($log_bulan))  $label_parts[] = strtoupper($daftar_bulan[$log_bulan] ?? '');
        if (!empty($log_tahun))  $label_parts[] = $log_tahun;
        $label_periode = implode(' - ', $label_parts);
    }

    // Total Kedatangan (dihitung per sesi kedatangan unik supplier + waktu_masuk)
    try {
        $stmt_count = $pdo->prepare("
            SELECT COUNT(DISTINCT CONCAT(COALESCE(l.supplier_id, 0), '_', l.waktu_masuk)) 
            FROM merchant_log_akses_supplier l
            LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id
            LEFT JOIN merchant_supplier_barang msb ON l.barang_id = msb.barang_id
            LEFT JOIN merchant_kategori_barang kb ON m.kategori_id = kb.kategori_id
            {$where_stat}
        ");
        $stmt_count->execute($params_stat);
        $total_masuk_periode = (int)$stmt_count->fetchColumn();
    } catch (Exception $e) {
        $total_masuk_periode = 0;
    }

    // Kedatangan per Kategori Barang sesuai filter
    try {
        $stmt_kat_periode = $pdo->prepare("
            SELECT COALESCE(kb.nama_kategori, 'Tanpa Kategori') AS nama_kategori, 
                   COUNT(DISTINCT CONCAT(COALESCE(l.supplier_id, 0), '_', l.waktu_masuk)) AS total
            FROM merchant_log_akses_supplier l
            LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id
            LEFT JOIN merchant_supplier_barang msb ON l.barang_id = msb.barang_id
            LEFT JOIN merchant_kategori_barang kb ON m.kategori_id = kb.kategori_id
            {$where_stat}
            GROUP BY nama_kategori
            ORDER BY total DESC
        ");
        $stmt_kat_periode->execute($params_stat);
        $statistik_kategori_periode = $stmt_kat_periode->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $statistik_kategori_periode = [];
    }

    // Kedatangan per Jenis Barang sesuai filter
    try {
        $stmt_jenis_periode = $pdo->prepare("
            SELECT COALESCE(NULLIF(TRIM(msb.nama_barang), ''), 'Tanpa Barang') AS jenis_barang, COUNT(l.{$log_pk_col}) AS total
            FROM merchant_log_akses_supplier l
            LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id
            LEFT JOIN merchant_supplier_barang msb ON l.barang_id = msb.barang_id
            LEFT JOIN merchant_kategori_barang kb ON m.kategori_id = kb.kategori_id
            {$where_stat}
            GROUP BY jenis_barang
            ORDER BY total DESC
        ");
        $stmt_jenis_periode->execute($params_stat);
        $statistik_jenis_periode = $stmt_jenis_periode->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $statistik_jenis_periode = [];
    }

    // Query Utama Tabel Riwayat Kedatangan (DIGABUNG 1 BARIS PER KEDATANGAN)
    $sql_log = "SELECT GROUP_CONCAT(l.{$log_pk_col} ORDER BY l.{$log_pk_col} ASC SEPARATOR ',') AS log_ids,
                       l.waktu_masuk, 
                       m.nama_supplier, 
                       kb.nama_kategori,
                       GROUP_CONCAT(
                           CASE 
                               WHEN l.jumlah > 0 THEN CONCAT(
                                   COALESCE(msb.nama_barang, 'Barang'), ' (', l.jumlah, 
                                   IF(sb.nama_satuan IS NOT NULL AND sb.nama_satuan != '', CONCAT(' ', sb.nama_satuan), ''), 
                                   ')'
                               )
                               WHEN msb.nama_barang IS NOT NULL AND msb.nama_barang != '' THEN msb.nama_barang
                               ELSE NULL
                           END
                           ORDER BY l.{$log_pk_col} ASC SEPARATOR '||'
                       ) AS rincian_items
                FROM merchant_log_akses_supplier l 
                LEFT JOIN merchant_supplier m ON l.supplier_id = m.supplier_id 
                LEFT JOIN merchant_supplier_barang msb ON l.barang_id = msb.barang_id
                LEFT JOIN merchant_kategori_barang kb ON m.kategori_id = kb.kategori_id
                LEFT JOIN merchant_satuan_barang sb ON l.satuan_id = sb.satuan_id
                {$where_log}
                GROUP BY l.supplier_id, l.waktu_masuk
                ORDER BY l.waktu_masuk DESC";

    try {
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute($params_log);
        $riwayat_akses = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $riwayat_akses = [];
    }

    // ==========================================
    // 9. AMBIL DATA UNTUK TAB 3: MERCHANT_USER
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
                        <button class="nav-link" id="masterdata-tab" data-bs-toggle="tab" data-bs-target="#masterdata" type="button">
                            <i class="fa-solid fa-tags me-1"></i> Kategori & Satuan
                        </button>
                    </li>
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
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari berdasarkan nama supplier, alamat, jenis barang, atau kategori..." value="<?= htmlspecialchars($search_keyword) ?>">
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
                                        <th width="18%">Nama Supplier</th>
                                        <th width="17%">Alamat</th>
                                        <th width="14%">No. Handphone</th>
                                        <th width="20%">Jenis Barang</th>
                                        <th width="13%">Kategori</th>
                                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><th width="13%">Aksi</th><?php endif; ?>
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
                                                <td>
                                                    <?php if (!empty($row['daftar_barang'])): ?>
                                                        <?php foreach (explode(', ', $row['daftar_barang']) as $brg_item): ?>
                                                            <span class="badge bg-primary badge-barang mb-1"><?= htmlspecialchars($brg_item) ?></span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-info text-dark badge-barang"><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?></span></td>
                                                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                                    <td class="text-center text-nowrap">
                                                        <button class="btn btn-sm btn-outline-warning me-1 btn-edit"
                                                            data-id="<?= $row['supplier_id'] ?>"
                                                            data-nama="<?= htmlspecialchars($row['nama_supplier'] ?? '', ENT_QUOTES) ?>"
                                                            data-alamat="<?= htmlspecialchars($row['alamat'] ?? '', ENT_QUOTES) ?>"
                                                            data-nohp="<?= htmlspecialchars($row['no_hp'] ?? '', ENT_QUOTES) ?>"
                                                            data-jenis="<?= htmlspecialchars($row['daftar_barang'] ?? '', ENT_QUOTES) ?>"
                                                            data-kategori="<?= htmlspecialchars($row['kategori_id'] ?? '', ENT_QUOTES) ?>"
                                                            title="Edit Supplier"><i class="fa-solid fa-pen-to-square"></i></button>
                                                        <button class="btn btn-sm btn-outline-secondary me-1 btn-edit-password"
                                                            data-id="<?= $row['supplier_id'] ?>"
                                                            data-nama="<?= htmlspecialchars($row['nama_supplier'] ?? '', ENT_QUOTES) ?>"
                                                            title="Ganti Password"><i class="fa-solid fa-key"></i></button>
                                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus supplier ini?');">
                                                            <input type="hidden" name="action" value="hapus"><input type="hidden" name="supplier_id" value="<?= $row['supplier_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                                        </form>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">Tidak ada data supplier.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: AREA KIOS & LOG -->
                <div class="tab-pane fade" id="logkios">

                    <!-- RINGKASAN STATISTIK DINAMIS -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4 col-lg-3">
                            <div class="card card-custom p-3 bg-primary text-white border-0 h-100 mb-0">
                                <div class="d-flex align-items-center justify-content-between h-100">
                                    <div>
                                        <small class="text-white-50 fw-bold d-block mb-1" style="font-size: 0.75rem;">
                                            TOTAL MASUK (<?= htmlspecialchars($label_periode) ?>)
                                        </small>
                                        <h2 class="fw-bold mb-0 text-white"><?= $total_masuk_periode ?> <span class="fs-6 fw-normal">Kali</span></h2>
                                    </div>
                                    <i class="fa-solid fa-calendar-check fa-2x text-white-50"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 col-lg-5">
                            <div class="card card-custom p-3 border-start border-4 border-info h-100 mb-0">
                                <small class="text-muted fw-bold d-block mb-2" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-layer-group text-info me-1"></i> KEDATANGAN PER KATEGORI (<?= htmlspecialchars($label_periode) ?>)
                                </small>
                                <?php if (!empty($statistik_kategori_periode)): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($statistik_kategori_periode as $stat_kat): ?>
                                            <span class="badge bg-info-subtle text-dark border border-info-subtle px-2 py-1" style="font-size: 0.8rem;">
                                                <?= htmlspecialchars($stat_kat['nama_kategori']) ?>: <strong><?= $stat_kat['total'] ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Tidak ada kedatangan pada periode ini.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-4 col-lg-4">
                            <div class="card card-custom p-3 border-start border-4 border-warning h-100 mb-0">
                                <small class="text-muted fw-bold d-block mb-2" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-tags text-warning me-1"></i> RINCIAN JENIS BARANG (<?= htmlspecialchars($label_periode) ?>)
                                </small>
                                <?php if (!empty($statistik_jenis_periode)): ?>
                                    <div class="d-flex flex-wrap gap-2" style="max-height: 75px; overflow-y: auto;">
                                        <?php foreach ($statistik_jenis_periode as $stat_jns): ?>
                                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.8rem;">
                                                <?= htmlspecialchars(ucwords(strtolower($stat_jns['jenis_barang']))) ?>: <strong class="text-primary"><?= $stat_jns['total'] ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Tidak ada data jenis barang pada periode ini.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-5 col-lg-4">
                            <div class="card card-custom p-4 border-start border-4 border-success">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-box-open text-primary me-2"></i> Lapor Kedatangan Baru</h6>
                                    <small id="cooldown-badge" class="badge bg-warning text-dark d-none">Jeda</small>
                                </div>
                                <form id="formInputBarang" method="POST" action="">
                                    <input type="hidden" name="action" value="simpan_akses">
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">Pilih Supplier <span class="text-danger">*</span></label>
                                        <select name="supplier_id" id="selectSupplierLog" class="form-select" required>
                                            <option value="">-- Pilih Supplier --</option>
                                            <?php
                                            $stmt_all = $pdo->query("
                                                SELECT ms.supplier_id, ms.nama_supplier, kb.nama_kategori 
                                                FROM merchant_supplier ms
                                                LEFT JOIN merchant_kategori_barang kb ON ms.kategori_id = kb.kategori_id
                                                ORDER BY ms.nama_supplier ASC
                                            ");
                                            while ($sup = $stmt_all->fetch()) {
                                                $kat = !empty($sup['nama_kategori']) ? " ({$sup['nama_kategori']})" : "";
                                                echo "<option value='{$sup['supplier_id']}'>" . htmlspecialchars("{$sup['nama_supplier']}{$kat}") . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">Jenis Barang <span class="fw-normal">(Opsional)</span></label>
                                        <select name="barang_id" id="selectBarangLog" class="form-select">
                                            <option value="">-- Pilih Supplier Terlebih Dahulu --</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">Jumlah Barang <span class="fw-normal">(Opsional)</span></label>
                                        <div class="input-group">
                                            <input type="number" name="jumlah" class="form-control" placeholder="0" min="0">
                                            <select name="satuan_id" class="form-select bg-light" style="max-width: 110px;">
                                                <?php foreach ($data_satuan as $sat): ?>
                                                    <option value="<?= $sat['satuan_id'] ?>" <?= (strtolower($sat['nama_satuan']) === 'pcs') ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($sat['nama_satuan']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" id="btnSubmitForm" class="btn btn-success w-100 py-2 fw-bold" disabled><i class="fa-solid fa-paper-plane me-1"></i> Simpan Kedatangan</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-7 col-lg-8">
                            <div class="card card-custom p-4 h-100">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-table-list text-primary me-2"></i> Riwayat Kedatangan</h6>
                                    <span class="badge bg-secondary"><?= count($riwayat_akses) ?> Kedatangan</span>
                                </div>

                                <form method="GET" action="" class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="log_search" class="form-control" placeholder="Cari nama supplier / barang..." value="<?= htmlspecialchars($log_search) ?>">
                                            <button type="submit" class="btn btn-primary" title="Cari"><i class="fa-solid fa-search"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="log_bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">-- Semua Bulan --</option>
                                            <?php foreach ($daftar_bulan as $key => $nama_bulan): ?>
                                                <option value="<?= $key ?>" <?= ($log_bulan === $key) ? 'selected' : '' ?>><?= $nama_bulan ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="log_tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">-- Tahun --</option>
                                            <?php foreach ($list_tahun_log as $th): ?>
                                                <option value="<?= $th ?>" <?= ($log_tahun == $th) ? 'selected' : '' ?>><?= $th ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex gap-1">
                                        <a href="admin_supplierr.php?tab=logkios" class="btn btn-outline-secondary btn-sm" title="Reset Filter"><i class="fa-solid fa-rotate-right"></i></a>
                                        <a href="?export=excel&log_bulan=<?= urlencode($log_bulan) ?>&log_tahun=<?= urlencode($log_tahun) ?>&log_search=<?= urlencode($log_search) ?>" class="btn btn-success btn-sm w-100"><i class="fa-solid fa-file-excel"></i> Excel</a>
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
                                                        <th>Supplier & Kategori</th>
                                                        <th>Barang & Jumlah Bawaan</th>
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
                                                                <span class="badge bg-info text-dark" style="font-size:0.68rem;"><?= htmlspecialchars($log['nama_kategori'] ?? '-') ?></span>
                                                            </td>
                                                            <td style="font-size:0.85rem;">
                                                                <?php if (!empty($log['rincian_items'])): ?>
                                                                    <div class="d-flex flex-wrap gap-1">
                                                                        <?php foreach (explode('||', $log['rincian_items']) as $item_str): ?>
                                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold" style="font-size: 0.78rem;">
                                                                                <?= htmlspecialchars($item_str) ?>
                                                                            </span>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                                                <td class="text-center">
                                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kedatangan ini?');">
                                                                        <input type="hidden" name="action" value="hapus_log">
                                                                        <input type="hidden" name="log_ids" value="<?= htmlspecialchars($log['log_ids']) ?>">
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

                <!-- TAB 3: CRUD MASTER KATEGORI & SATUAN BARANG -->
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <div class="tab-pane fade" id="masterdata">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="table-container h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-layer-group text-primary me-2"></i> Master Kategori Barang</h6>
                                        <button type="button" class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
                                            <i class="fa-solid fa-plus me-1"></i> Tambah Kategori
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-bordered align-middle">
                                            <thead>
                                                <tr class="text-center">
                                                    <th width="15%">ID</th>
                                                    <th>Nama Kategori</th>
                                                    <th width="25%">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($data_kategori) > 0): ?>
                                                    <?php foreach ($data_kategori as $kat): ?>
                                                        <tr>
                                                            <td class="text-center fw-bold"><?= $kat['kategori_id'] ?></td>
                                                            <td class="fw-semibold"><?= htmlspecialchars($kat['nama_kategori']) ?></td>
                                                            <td class="text-center text-nowrap">
                                                                <button class="btn btn-sm btn-outline-warning me-1 btn-edit-kategori"
                                                                    data-id="<?= $kat['kategori_id'] ?>"
                                                                    data-nama="<?= htmlspecialchars($kat['nama_kategori'], ENT_QUOTES) ?>"
                                                                    title="Edit Kategori">
                                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                                </button>
                                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus kategori <?= htmlspecialchars($kat['nama_kategori'], ENT_QUOTES) ?>? Supplier terkait akan menjadi tanpa kategori.');">
                                                                    <input type="hidden" name="action" value="hapus_kategori">
                                                                    <input type="hidden" name="kategori_id" value="<?= $kat['kategori_id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Kategori">
                                                                        <i class="fa-solid fa-trash-can"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4 text-muted">Belum ada data kategori.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="table-container h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-scale-balanced text-primary me-2"></i> Master Satuan Barang</h6>
                                        <button type="button" class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalTambahSatuan">
                                            <i class="fa-solid fa-plus me-1"></i> Tambah Satuan
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-bordered align-middle">
                                            <thead>
                                                <tr class="text-center">
                                                    <th width="15%">ID</th>
                                                    <th>Nama Satuan</th>
                                                    <th width="25%">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($data_satuan) > 0): ?>
                                                    <?php foreach ($data_satuan as $sat): ?>
                                                        <tr>
                                                            <td class="text-center fw-bold"><?= $sat['satuan_id'] ?></td>
                                                            <td class="fw-semibold"><?= htmlspecialchars($sat['nama_satuan']) ?></td>
                                                            <td class="text-center text-nowrap">
                                                                <button class="btn btn-sm btn-outline-warning me-1 btn-edit-satuan"
                                                                    data-id="<?= $sat['satuan_id'] ?>"
                                                                    data-nama="<?= htmlspecialchars($sat['nama_satuan'], ENT_QUOTES) ?>"
                                                                    title="Edit Satuan">
                                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                                </button>
                                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus satuan <?= htmlspecialchars($sat['nama_satuan'], ENT_QUOTES) ?>?');">
                                                                    <input type="hidden" name="action" value="hapus_satuan">
                                                                    <input type="hidden" name="satuan_id" value="<?= $sat['satuan_id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Satuan">
                                                                        <i class="fa-solid fa-trash-can"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4 text-muted">Belum ada data satuan.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: MANAJEMEN USER (MERCHANT_USER) -->
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
                            <div class="col-12"><label class="form-label small fw-bold">Nama Supplier <span class="text-danger">*</span></label><input type="text" name="nama_supplier" class="form-control form-control-sm" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Alamat</label><input type="text" name="alamat" class="form-control form-control-sm"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No. Handphone</label><input type="text" name="no_hp" class="form-control form-control-sm"></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Jenis Barang <span class="fw-normal text-muted">(Pisah koma)</span></label>
                                <input type="text" name="jenis_barang" class="form-control form-control-sm" placeholder="Contoh: BATIK, Daster">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Kategori Barang</label>
                                <select name="kategori_id" class="form-select form-select-sm">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($data_kategori as $kat): ?>
                                        <option value="<?= $kat['kategori_id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><small class="text-muted d-block mt-1">* Password default otomatis: Nama Supplier tanpa spasi</small></div>
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
                            <div class="col-12"><label class="form-label small fw-bold">Nama Supplier <span class="text-danger">*</span></label><input type="text" name="nama_supplier" id="edit_nama" class="form-control form-control-sm" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Alamat</label><input type="text" name="alamat" id="edit_alamat" class="form-control form-control-sm"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">No. Handphone</label><input type="text" name="no_hp" id="edit_nohp" class="form-control form-control-sm"></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Jenis Barang <span class="fw-normal text-muted">(Pisah koma)</span></label>
                                <input type="text" name="jenis_barang" id="edit_jenis" class="form-control form-control-sm" placeholder="Contoh: BATIK, Daster">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Kategori Barang</label>
                                <select name="kategori_id" id="edit_kategori" class="form-select form-select-sm">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($data_kategori as $kat): ?>
                                        <option value="<?= $kat['kategori_id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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

            <!-- MODAL KATEGORI BARANG -->
            <div class="modal fade" id="modalTambahKategori" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-plus me-2"></i> Tambah Kategori Barang</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="tambah_kategori">
                            <label class="form-label small fw-bold">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="nama_kategori" class="form-control form-control-sm" placeholder="Contoh: Makanan dan Minuman" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalEditKategori" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Kategori Barang</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_kategori">
                            <input type="hidden" name="kategori_id" id="edit_kat_id">
                            <label class="form-label small fw-bold">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="nama_kategori" id="edit_kat_nama" class="form-control form-control-sm" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning btn-sm">Perbarui</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL SATUAN BARANG -->
            <div class="modal fade" id="modalTambahSatuan" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-plus me-2"></i> Tambah Satuan Barang</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="tambah_satuan">
                            <label class="form-label small fw-bold">Nama Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_satuan" class="form-control form-control-sm" placeholder="Contoh: pcs, kg, dus, ikat" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalEditSatuan" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="" class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Satuan Barang</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_satuan">
                            <input type="hidden" name="satuan_id" id="edit_sat_id">
                            <label class="form-label small fw-bold">Nama Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_satuan" id="edit_sat_nama" class="form-control form-control-sm" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning btn-sm">Perbarui</button>
                        </div>
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
            const barangPerSupplier = <?= json_encode($barang_per_supplier) ?>;

            document.addEventListener('DOMContentLoaded', function() {
                // Dropdown Dinamis Jenis Barang saat Admin memilih Supplier di Tab Log Kios
                const selectSupplierLog = document.getElementById('selectSupplierLog');
                const selectBarangLog = document.getElementById('selectBarangLog');

                if (selectSupplierLog && selectBarangLog) {
                    selectSupplierLog.addEventListener('change', function() {
                        const supId = this.value;
                        selectBarangLog.innerHTML = '';

                        if (!supId) {
                            selectBarangLog.innerHTML = '<option value="">-- Pilih Supplier Terlebih Dahulu --</option>';
                            return;
                        }

                        const daftar = barangPerSupplier[supId] || [];
                        if (daftar.length === 0) {
                            selectBarangLog.innerHTML = '<option value="">-- Tidak ada barang terdaftar (Lapor tanpa barang) --</option>';
                        } else {
                            selectBarangLog.innerHTML = '<option value="">-- Pilih Barang (Opsional) --</option>';
                            daftar.forEach(item => {
                                const opt = document.createElement('option');
                                opt.value = item.id;
                                opt.textContent = item.nama;
                                selectBarangLog.appendChild(opt);
                            });
                        }
                    });
                }

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
                            document.getElementById('edit_kategori').value = this.dataset.kategori;
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

                // Modal Edit Kategori
                const editKatModalEl = document.getElementById('modalEditKategori');
                if (editKatModalEl) {
                    const editKatModal = new bootstrap.Modal(editKatModalEl);
                    document.querySelectorAll('.btn-edit-kategori').forEach(button => {
                        button.addEventListener('click', function() {
                            document.getElementById('edit_kat_id').value = this.dataset.id;
                            document.getElementById('edit_kat_nama').value = this.dataset.nama;
                            editKatModal.show();
                        });
                    });
                }

                // Modal Edit Satuan
                const editSatModalEl = document.getElementById('modalEditSatuan');
                if (editSatModalEl) {
                    const editSatModal = new bootstrap.Modal(editSatModalEl);
                    document.querySelectorAll('.btn-edit-satuan').forEach(button => {
                        button.addEventListener('click', function() {
                            document.getElementById('edit_sat_id').value = this.dataset.id;
                            document.getElementById('edit_sat_nama').value = this.dataset.nama;
                            editSatModal.show();
                        });
                    });
                }

                // Modal Edit User
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

                // Modal Edit Password User
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
                    new bootstrap.Tab(document.querySelector('#logkios-tab')).show();
                <?php elseif (isset($_GET['log_bulan']) || isset($_GET['log_tahun']) || isset($_GET['log_search']) || (($_GET['tab'] ?? '') === 'logkios')): ?>
                    new bootstrap.Tab(document.querySelector('#logkios-tab')).show();
                <?php elseif (isset($_POST['action']) && in_array($_POST['action'], ['tambah_kategori', 'edit_kategori', 'hapus_kategori', 'tambah_satuan', 'edit_satuan', 'hapus_satuan'])): ?>
                    var masterTabEl = document.querySelector('#masterdata-tab');
                    if (masterTabEl) new bootstrap.Tab(masterTabEl).show();
                <?php elseif ((isset($_POST['action']) && in_array($_POST['action'], ['tambah_user', 'edit_user', 'edit_password_user', 'hapus_user'])) || isset($_GET['search_user']) || (($_GET['tab'] ?? '') === 'users')): ?>
                    var usersTabEl = document.querySelector('#users-tab');
                    if (usersTabEl) new bootstrap.Tab(usersTabEl).show();
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