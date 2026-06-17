<?php
session_start();
require __DIR__ . '/admin_config.php';
require __DIR__ . '/lib_clicks.php';
date_default_timezone_set('Asia/Jakarta');

// --- Fungsi Waktu ---
function format_time_wib(string $iso): string {
  $iso = trim($iso);
  if ($iso === '') return '';
  try {
    $dt = new DateTimeImmutable($iso);
    $dt = $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return $dt->format('Y-m-d H:i:s') . ' WIB';
  } catch (Throwable $e) {
    return $iso;
  }
}

// --- Proses Login & Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}

// Cek status login
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Data Statistik Klik (Hanya diambil jika sudah login) ---
$summary = [];
$recent = [];
if ($is_logged_in) {
    $summary = clicks_get_summary(500);
    $recent = clicks_get_recent(1000);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Scholarium</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .navbar-custom { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .card-header { background-color: #fff; border-bottom: 1px solid #f1f5f9; padding: 1rem 1.25rem; border-top-left-radius: 12px !important; border-top-right-radius: 12px !important; }
        .nav-tabs .nav-link { color: #64748b; font-weight: 500; border: none; border-bottom: 2px solid transparent; padding: 1rem 1.5rem; }
        .nav-tabs .nav-link:hover { border-color: transparent; color: #1e293b; }
        .nav-tabs .nav-link.active { border-color: #2563eb; color: #2563eb; background: transparent; }
        .table > :not(caption) > * > * { padding: 0.75rem 1rem; }
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .login-container { max-width: 400px; margin: 100px auto; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-4">
  <div class="container">
    <a class="navbar-brand mb-0 h1 d-flex align-items-center gap-2" href="admin.php">
        <i class="fa-solid fa-graduation-cap text-warning"></i> Admin Scholarium
    </a>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-light" href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Situs</a>
        <?php if ($is_logged_in): ?>
            <a class="btn btn-sm btn-danger" href="admin.php?logout=1"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
        <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container mb-5">
    <?php if (!$is_logged_in): ?>
        <!-- Halaman Login -->
        <div class="login-container">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="text-center fw-bold mb-4">Login Admin</h4>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="login" value="1">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Login</button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Halaman Dashboard -->
        
        <!-- Tabs Nav -->
        <ul class="nav nav-tabs mb-4 border-0 bg-white rounded-3 shadow-sm px-2" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="library-tab" data-bs-toggle="tab" data-bs-target="#library" type="button" role="tab" aria-controls="library" aria-selected="true">
                    <i class="fa-solid fa-folder-tree me-2"></i>Data Library (MySQL)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" aria-controls="stats" aria-selected="false">
                    <i class="fa-solid fa-chart-pie me-2"></i>Statistik Klik (SQLite)
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="adminTabsContent">
            
            <!-- TAB 1: Library Tree CRUD -->
            <div class="tab-pane fade show active" id="library" role="tabpanel" aria-labelledby="library-tab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-semibold">Manajemen Library Tree</h5>
                        <button class="btn btn-primary btn-sm" onclick="showModal('create')"><i class="fa-solid fa-plus me-1"></i> Tambah Data</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tableLibrary" class="table table-striped table-hover w-100 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%">ID</th>
                                        <th style="width: 35%">Nama Item</th>
                                        <th style="width: 10%">Tipe</th>
                                        <th style="width: 20%">Drive ID</th>
                                        <th style="width: 20%">Parent ID</th>
                                        <th style="width: 10%" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Diisi oleh DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Statistik Klik -->
            <div class="tab-pane fade" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                <div class="alert alert-warning">
                    <strong>Informasi:</strong> Data ini diambil dari sistem tracking klik (<code>storage/clicks.sqlite</code>).
                </div>
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Ringkasan (Top klik)</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="tableSummary" class="table table-striped table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 45%">Nama</th>
                                        <th style="width: 25%">Target (URL)</th>
                                        <th style="width: 10%" class="text-end">Total</th>
                                        <th style="width: 30%">Terakhir klik (WIB)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($summary)): ?>
                                    <tr><td colspan="4" class="text-muted p-3 text-center">Belum ada data klik.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($summary as $row): ?>
                                        <tr>
                                        <td class="text-break">
                                            <?php
                                            $label = trim((string)($row['label'] ?? ''));
                                            echo htmlspecialchars($label !== '' ? $label : (string)($row['target'] ?? ''));
                                            ?>
                                        </td>
                                        <td class="text-break small">
                                            <a href="<?php echo htmlspecialchars((string)($row['target'] ?? '')); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars((string)($row['target'] ?? '')); ?>
                                            </a>
                                        </td>
                                        <td class="text-end"><?php echo (int)($row['total'] ?? 0); ?></td>
                                        <?php $rawLastAt = (string)($row['last_at'] ?? ''); ?>
                                        <td data-order="<?php echo htmlspecialchars($rawLastAt); ?>"><?php echo htmlspecialchars(format_time_wib($rawLastAt)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header fw-semibold">Klik terbaru</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="tableRecent" class="table table-striped mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 18%">Waktu (WIB)</th>
                                        <th style="width: 26%">Nama</th>
                                        <th style="width: 34%">Target</th>
                                        <th style="width: 28%">Sumber</th>
                                        <th style="width: 12%">IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent)): ?>
                                    <tr><td colspan="5" class="text-muted p-3 text-center">Belum ada data klik.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recent as $row): ?>
                                        <tr>
                                        <?php $rawCreatedAt = (string)($row['created_at'] ?? ''); ?>
                                        <td data-order="<?php echo htmlspecialchars($rawCreatedAt); ?>"><?php echo htmlspecialchars(format_time_wib($rawCreatedAt)); ?></td>
                                        <td class="text-break">
                                            <?php
                                            $label = trim((string)($row['label'] ?? ''));
                                            echo htmlspecialchars($label !== '' ? $label : (string)($row['target'] ?? ''));
                                            ?>
                                        </td>
                                        <td class="text-break"><?php echo htmlspecialchars((string)($row['target'] ?? '')); ?></td>
                                        <td class="text-break"><?php echo htmlspecialchars((string)($row['source'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['ip'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    <?php endif; ?>
</main>

<?php if ($is_logged_in): ?>
<!-- Modal Form Library -->
<div class="modal fade" id="modalLibrary" tabindex="-1" aria-labelledby="modalLibraryLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLibraryLabel">Tambah Data</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formLibrary">
          <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" id="lib_action" name="action" value="create">
              <input type="hidden" id="lib_id" name="id" value="">
              
              <div class="row g-3">
                  <div class="col-md-8">
                      <label class="form-label">Nama Item <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="lib_name" name="name" required>
                  </div>
                  <div class="col-md-4">
                      <label class="form-label">Tipe Item <span class="text-danger">*</span></label>
                      <select class="form-select" id="lib_type" name="type" required>
                          <option value="FILE">FILE</option>
                          <option value="FOLDER">FOLDER</option>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label">Drive ID <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="lib_drive_id" name="drive_id" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label">Parent Drive ID <small class="text-muted">(Kosongkan jika root)</small></label>
                      <input type="text" class="form-control" id="lib_parent_id" name="parent_id">
                  </div>
                  <div class="col-12" id="div_link">
                      <label class="form-label">Link Akses / Preview <small class="text-muted">(Untuk File)</small></label>
                      <input type="url" class="form-control" id="lib_link" name="link">
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary" id="btnSaveLibrary"><i class="fa-solid fa-save"></i> Simpan Data</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

<script>
    const csrfToken = "<?php echo $csrf_token; ?>";
    const idLocaleUrl = 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/id.json';

    $(document).ready(function() {
        // Init table click summary
        if ($('#tableSummary').length) {
            $('#tableSummary').DataTable({ order: [[2, 'desc']], pageLength: 10, language: { url: idLocaleUrl }});
            $('#tableRecent').DataTable({ order: [[0, 'desc']], pageLength: 10, language: { url: idLocaleUrl }});
        }

        // Init table library_tree server-side
        let tableLib = null;
        if ($('#tableLibrary').length) {
            tableLib = $('#tableLibrary').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'admin_api.php?action=list',
                    type: 'POST',
                    error: function(xhr, error, thrown) {
                        console.error('DataTables Error:', xhr.responseText);
                        Swal.fire('Error', 'Gagal memuat data dari server.', 'error');
                    }
                },
                language: { url: idLocaleUrl },
                columns: [
                    { data: 'id', orderable: true },
                    { 
                        data: 'name',
                        render: function(data, type, row) {
                            let icon = row.type === 'FOLDER' ? '<i class="fa-solid fa-folder text-warning me-2"></i>' : '<i class="fa-solid fa-file text-primary me-2"></i>';
                            return icon + '<strong>' + $('<div>').text(data).html() + '</strong>';
                        }
                    },
                    { 
                        data: 'type',
                        render: function(data) {
                            return data === 'FOLDER' ? '<span class="badge bg-warning text-dark">FOLDER</span>' : '<span class="badge bg-info">FILE</span>';
                        }
                    },
                    { data: 'drive_id', orderable: false, render: $.fn.dataTable.render.text() },
                    { data: 'parent_id', orderable: false, render: $.fn.dataTable.render.text() },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            let r = encodeURIComponent(JSON.stringify(row));
                            return `
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-outline-primary btn-action" title="Edit" onclick="showModal('edit', '${r}')">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-action" title="Hapus" onclick="deleteData(${row.id})">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ]
            });
        }

        // Handle Type Change for Link Input
        $('#lib_type').change(function() {
            if ($(this).val() === 'FOLDER') {
                $('#div_link').slideUp();
                $('#lib_link').val('');
            } else {
                $('#div_link').slideDown();
            }
        });

        // Form Submit handler
        $('#formLibrary').submit(function(e) {
            e.preventDefault();
            let action = $('#lib_action').val();
            let formData = $(this).serialize();
            
            $('#btnSaveLibrary').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

            $.ajax({
                url: 'admin_api.php',
                type: 'POST',
                data: formData,
                headers: { 'X-CSRF-Token': csrfToken },
                success: function(res) {
                    $('#btnSaveLibrary').prop('disabled', false).html('<i class="fa-solid fa-save"></i> Simpan Data');
                    let response = JSON.parse(res);
                    if (response.success) {
                        $('#modalLibrary').modal('hide');
                        Swal.fire('Sukses!', response.message, 'success');
                        if (tableLib) tableLib.ajax.reload(null, false); // false to stay on current page
                    } else {
                        Swal.fire('Error', response.error || 'Terjadi kesalahan', 'error');
                    }
                },
                error: function(xhr) {
                    $('#btnSaveLibrary').prop('disabled', false).html('<i class="fa-solid fa-save"></i> Simpan Data');
                    let msg = 'Terjadi kesalahan sistem.';
                    try { let r = JSON.parse(xhr.responseText); msg = r.error || msg; } catch(e) {}
                    Swal.fire('Error', msg, 'error');
                }
            });
        });
    });

    // Global Functions for modal & delete
    const libraryModal = new bootstrap.Modal(document.getElementById('modalLibrary') || document.createElement('div'));

    window.showModal = function(action, rowJson = '') {
        $('#formLibrary')[0].reset();
        $('#lib_action').val(action);
        
        if (action === 'create') {
            $('#modalLibraryLabel').text('Tambah Data Library');
            $('#div_link').show();
            $('#lib_id').val('');
        } else if (action === 'edit' && rowJson) {
            $('#modalLibraryLabel').text('Edit Data Library');
            try {
                let row = JSON.parse(decodeURIComponent(rowJson));
                $('#lib_id').val(row.id);
                $('#lib_name').val(row.name);
                $('#lib_type').val(row.type).trigger('change');
                $('#lib_drive_id').val(row.drive_id);
                $('#lib_parent_id').val(row.parent_id);
                $('#lib_link').val(row.link);
            } catch (e) {
                console.error("Error parsing row data", e);
            }
        }
        libraryModal.show();
    };

    window.deleteData = function(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'admin_api.php',
                    type: 'POST',
                    data: { action: 'delete', id: id, csrf_token: csrfToken },
                    headers: { 'X-CSRF-Token': csrfToken },
                    success: function(res) {
                        let response = JSON.parse(res);
                        if (response.success) {
                            Swal.fire('Terhapus!', response.message, 'success');
                            $('#tableLibrary').DataTable().ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', response.error || 'Gagal menghapus data', 'error');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Terjadi kesalahan sistem.';
                        try { let r = JSON.parse(xhr.responseText); msg = r.error || msg; } catch(e) {}
                        Swal.fire('Error', msg, 'error');
                    }
                });
            }
        });
    };
</script>
<?php endif; ?>
</body>
</html>
