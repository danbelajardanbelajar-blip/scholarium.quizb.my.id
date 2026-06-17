        <?php
        /**
         * SCHOLARIUM DIGITAL LIBRARY - UI/UX MODERN VERSION
         * Designed for Mobile & Desktop Perfection
         */

        // --- KONFIGURASI DATABASE ---
        $host = 'localhost';
        $dbname = 'quic1934_scholarium';
        $user = 'quic1934_zenhkm';
        $pass = '03Maret1990';
        $tableName = 'library_tree'; 
        $rootFolderId = '17wipGAv3rT6eZLV4h25qS_0lnFADvfcm';

        // --- KONEKSI ---
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error Database: " . $e->getMessage());
        }

        // =================================================================
        // BAGIAN 1: API AJAX HANDLER
        // =================================================================
        if (isset($_GET['ajax_search'])) {
            $q = trim($_GET['ajax_search']);
            if (strlen($q) < 2) { echo json_encode([]); exit; }

            $sql = "SELECT t1.drive_id, t1.name, t1.type, t1.link, t2.name as parent_name 
                    FROM $tableName t1 
                    LEFT JOIN $tableName t2 ON t1.parent_id = t2.drive_id 
                    WHERE t1.name LIKE :q LIMIT 8";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['q' => "%$q%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($results as &$row) {
                $row['icon_data'] = getFileIconData($row['name'], $row['type']);
                if ($row['type'] == 'FILE') {
                    $row['preview_url'] = getPreviewUrl($row['drive_id'], $row['link']);
                    $row['download_url'] = "https://drive.google.com/uc?export=download&id=" . $row['drive_id'];
                    $row['thumb_url'] = getDriveThumbnailUrl($row['drive_id'], $row['name'], $row['type']);
                }
            }
            header('Content-Type: application/json');
            echo json_encode($results);
            exit; 
        }

        // =================================================================
        // BAGIAN 2: LOGIKA PHP
        // =================================================================

        $currentId = isset($_GET['id']) ? $_GET['id'] : $rootFolderId;
        $searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
        $fileFilter = 'ALL';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20; 
        $offset = ($page - 1) * $limit;

        // --- Helper Functions (Updated for UI Colors) ---
        function getFileIconData($filename, $type) {
            // Return: [Icon Class, Color Text, Color Background Class]
            if ($type == 'FOLDER') return ['fa-folder', 'text-warning', 'bg-warning-subtle'];
            
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            switch ($ext) {
                case 'pdf': return ['fa-file-pdf', 'text-danger', 'bg-danger-subtle'];
                case 'doc': case 'docx': return ['fa-file-word', 'text-primary', 'bg-primary-subtle'];
                case 'xls': case 'xlsx': return ['fa-file-excel', 'text-success', 'bg-success-subtle'];
                case 'ppt': case 'pptx': return ['fa-file-powerpoint', 'text-warning', 'bg-warning-subtle'];
                case 'jpg': case 'jpeg': case 'png': return ['fa-image', 'text-info', 'bg-info-subtle'];
                case 'zip': case 'rar': return ['fa-file-zipper', 'text-dark', 'bg-secondary-subtle'];
                case 'mp3': return ['fa-music', 'text-primary', 'bg-primary-subtle'];
                case 'mp4': return ['fa-video', 'text-danger', 'bg-danger-subtle'];
                default: return ['fa-file', 'text-secondary', 'bg-light'];
            }
        }

        function getPreviewUrl($driveId, $fallbackUrl) {
            $driveId = trim((string)$driveId);
            if ($driveId !== '') {
                // Google Drive preview (read without direct download)
                return 'https://drive.google.com/file/d/' . rawurlencode($driveId) . '/view';
            }
            return (string)$fallbackUrl;
        }

        function getDriveThumbnailUrl($driveId, $filename, $type, $size = 200) {
            $driveId = trim((string)$driveId);
            if ($type !== 'FILE' || $driveId === '') return '';

            $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
            // Fokus: thumbnail halaman pertama untuk PDF (di-generate & di-cache oleh Google Drive)
            if ($ext !== 'pdf') return '';

            $size = (int)$size;
            if ($size < 40) $size = 40;
            if ($size > 600) $size = 600;
            return 'https://drive.google.com/thumbnail?id=' . rawurlencode($driveId) . '&sz=w' . $size . '-h' . $size;
        }

        // Query Logic
        $params = [];
        $whereClause = "";

        if (!empty($searchQuery)) {
            $whereClause = "WHERE name LIKE :q";
            $params['q'] = "%$searchQuery%";
            $isSearchMode = true;
            $pageTitle = "Hasil: \"$searchQuery\"";
        } else {
            $whereClause = "WHERE parent_id = :pid";
            $params['pid'] = $currentId;
            $isSearchMode = false;
            
            if ($currentId != $rootFolderId) {
                $stmtFolder = $pdo->prepare("SELECT * FROM $tableName WHERE drive_id = :id LIMIT 1");
                $stmtFolder->execute(['id' => $currentId]);
                $folderInfo = $stmtFolder->fetch(PDO::FETCH_ASSOC);
                $pageTitle = $folderInfo ? $folderInfo['name'] : 'Folder';
                $parentId = $folderInfo ? $folderInfo['parent_id'] : $rootFolderId;
            } else {
                $pageTitle = "Katalog Utama";
                $parentId = null;
            }
        }

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM $tableName $whereClause");
        $stmtCount->execute($params);
        $totalItems = $stmtCount->fetchColumn();
        $totalPages = ceil($totalItems / $limit);

        $sql = "SELECT * FROM $tableName $whereClause ORDER BY type DESC, name ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Meta untuk share (Open Graph / Twitter) ---
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = ($host !== '') ? ($scheme . '://' . $host . '/') : '';
        $currentUrl = ($host !== '') ? ($scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/')) : '';

        $defaultShareTitle = 'Scholarium Digital Library';
        $defaultShareDescription = 'Scholarium Digital Library — katalog kitab, dokumen, dan arsip. Cari cepat, baca online, dan unduh dokumen pilihan Anda.';
        $shareImage = 'https://cdn-icons-png.flaticon.com/512/2232/2232688.png';

        $isRootHome = (!$isSearchMode && $currentId == $rootFolderId);
        $shareTitle = $isRootHome ? $defaultShareTitle : ($pageTitle . ' | Scholarium');
        $shareDescription = $defaultShareDescription;
        $shareUrl = $isRootHome && $baseUrl !== '' ? $baseUrl : ($currentUrl !== '' ? $currentUrl : $baseUrl);

        ?>

        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
            <title><?php echo htmlspecialchars($pageTitle); ?> | Scholarium</title>

            <meta name="description" content="<?php echo htmlspecialchars($shareDescription); ?>">
            <?php if (!empty($shareUrl)): ?>
                <link rel="canonical" href="<?php echo htmlspecialchars($shareUrl); ?>">
            <?php endif; ?>

            <!-- Open Graph (Facebook/WhatsApp/Telegram) -->
            <meta property="og:locale" content="id_ID">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="Scholarium Digital Library">
            <meta property="og:title" content="<?php echo htmlspecialchars($shareTitle); ?>">
            <meta property="og:description" content="<?php echo htmlspecialchars($shareDescription); ?>">
            <?php if (!empty($shareUrl)): ?>
                <meta property="og:url" content="<?php echo htmlspecialchars($shareUrl); ?>">
            <?php endif; ?>
            <meta property="og:image" content="<?php echo htmlspecialchars($shareImage); ?>">
            <meta property="og:image:alt" content="Scholarium Digital Library">

            <!-- Twitter Card -->
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="<?php echo htmlspecialchars($shareTitle); ?>">
            <meta name="twitter:description" content="<?php echo htmlspecialchars($shareDescription); ?>">
            <meta name="twitter:image" content="<?php echo htmlspecialchars($shareImage); ?>">

            <meta name="theme-color" content="#0f172a">
            
            <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2232/2232688.png">

            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            
            <style>
                :root {
                    --primary-color: #2563eb;
                    --primary-hover: #1d4ed8;
                    --bg-body: #f1f5f9;
                    --hero-collapse: 0; /* 0..1 (di-set via JS saat scroll) */
                }
                body { 
                    background-color: var(--bg-body); 
                    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif; 
                    color: #334155;
                    -webkit-font-smoothing: antialiased;
                    padding-bottom: 56px; /* ruang untuk footer fixed (dipertipis) */
                }

                /* Hero Section */
                .hero-section {
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                    color: white;
                    padding-top: calc(2.5rem - (1.75rem * var(--hero-collapse)));
                    padding-bottom: calc(1.25rem - (0.75rem * var(--hero-collapse)));
                    border-bottom-left-radius: 2rem;
                    border-bottom-right-radius: 2rem;
                    margin-bottom: 0;

                    position: sticky;
                    top: 0;
                    z-index: 1030;

                    transition: padding 0.2s ease;
                }


                .hero-brand {
                    transition: transform 0.2s ease;
                    transform: translateY(calc(-6px * var(--hero-collapse)));
                }

                .hero-mini-search {
                    display: none;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    border: none;
                    background: #f59e0b;
                    color: white;
                    align-items: center;
                    justify-content: center;
                    flex: 0 0 auto;
                }

                .hero-mini-search:focus {
                    outline: none;
                }

                /* Search Bar */
                .search-container { position: relative; max-width: 600px; margin: 0 auto; }

                /* Saat scroll: search bar mengecil sampai tinggal tombol */
                .search-container {
                    width: 100%;
                    max-width: calc(600px - (556px * var(--hero-collapse))); /* 600 -> 44 */
                    transition: max-width 0.25s ease;
                }
                .search-input {
                    border-radius: 50px;
                    padding: 16px 55px 16px 24px;
                    border: none;
                    background: rgba(255, 255, 255, 0.15);
                    backdrop-filter: blur(10px);
                    color: white;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                    transition: all 0.3s ease;
                    opacity: calc(1 - var(--hero-collapse));
                }
                .search-input::placeholder { color: rgba(255, 255, 255, 0.6); }
                .search-input:focus {
                    background: white;
                    color: #1e293b;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                    outline: none;
                }
                .search-input:focus::placeholder { color: #94a3b8; }
                .search-btn {
                    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
                    background: #f59e0b; color: white; border: none; border-radius: 50%;
                    width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
                    transition: transform 0.2s;
                }
                .search-btn:hover { transform: translateY(-50%) scale(1.1); }

                /* Saat sudah hampir collapse: input tidak bisa diklik (hanya tombol search yang aktif) */
                .hero-section.hero-collapse-end .search-input {
                    pointer-events: none;
                }

                /* Saat sudah collapse penuh: tombol search pindah ke sebelah logo */
                .hero-section.hero-collapse-end .search-container {
                    display: none;
                }
                .hero-section.hero-collapse-end .hero-mini-search {
                    display: inline-flex;
                }

                /* Saat collapse penuh: rapatkan jarak ke bawah */
                .hero-section.hero-collapse-end {
                    padding-bottom: 0.35rem;
                }
                .hero-section.hero-collapse-end .hero-brand {
                    margin-bottom: 0 !important;
                }

                /* Saat user klik mini-search: tampilkan lagi input search (tanpa ikut collapse) */
                .hero-section.hero-force-open .search-container {
                    display: block;
                    max-width: 600px;
                }
                .hero-section.hero-force-open .search-input {
                    opacity: 1;
                    pointer-events: auto;
                }

                /* File Card Styling */
                .file-card {
                    border: none;
                    border-radius: 16px;
                    background: white;
                    padding: 1rem;
                    margin-bottom: 0.8rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                    transition: transform 0.2s, box-shadow 0.2s;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    position: relative;
                }
                .file-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
                }

                /* Icon Wrapper */
                .icon-wrapper {
                    width: 50px; height: 50px;
                    border-radius: 12px;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 1.5rem;
                    flex-shrink: 0;
                    position: relative;
                    overflow: hidden;
                }

                .file-thumb {
                    position: absolute;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .icon-wrapper.thumb-loaded i {
                    opacity: 0;
                }
                .bg-warning-subtle { background-color: #fef3c7 !important; color: #d97706 !important; }
                .bg-danger-subtle { background-color: #fee2e2 !important; color: #dc2626 !important; }
                .bg-primary-subtle { background-color: #dbeafe !important; color: #2563eb !important; }
                .bg-success-subtle { background-color: #dcfce7 !important; color: #16a34a !important; }
                .bg-info-subtle { background-color: #e0f2fe !important; color: #0891b2 !important; }

                /* Typography & Layout */
                .file-name {
                    font-weight: 600;
                    color: #1e293b;
                    text-decoration: none;
                    display: -webkit-box;
                    -webkit-line-clamp: 2; /* Limit 2 baris */
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    font-size: 0.95rem;
                    line-height: 1.4;
                }
                .file-meta { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
                
                /* Action Buttons */
                .action-group {
                    display: flex;
                    gap: 0.5rem;
                    margin-left: auto;
                    flex-shrink: 0;
                    position: relative;
                    z-index: 3;
                }
                .btn-action {
                    width: 36px; height: 36px;
                    border-radius: 10px;
                    display: flex; align-items: center; justify-content: center;
                    border: 1px solid #e2e8f0;
                    background: white; color: #64748b;
                    transition: all 0.2s;
                    position: relative;
                    z-index: 3;
                }
                .btn-action:hover { background: #f8fafc; color: var(--primary-color); border-color: var(--primary-color); }

                /* Ensure stretched-link overlay stays below action buttons */
                .stretched-link::after { z-index: 1; }
                
                /* Breadcrumb scrollable on mobile */
                .breadcrumb-scroll {
                    overflow-x: auto;
                    white-space: nowrap;
                    padding-bottom: 5px;
                    scrollbar-width: none; /* Hide scrollbar Firefox */
                }
                .breadcrumb-scroll::-webkit-scrollbar { display: none; /* Hide Chrome */ }

                /* Live Results */
                #live-results {
                    background: white; border-radius: 16px; 
                    margin-top: 12px;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                    overflow: hidden; display: none;
                    position: absolute; width: 100%; left: 0; z-index: 100;
                }
                .live-item { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px; text-decoration: none; color: #334155; transition: background 0.2s; }
                .live-item:hover { background: #f8fafc; }
                .live-item:last-child { border-bottom: none; }
                
                /* Animation */
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .animate-in { animation: fadeIn 0.4s ease-out forwards; opacity: 0; }
                
                /* Footer Hover Effect */
                .hover-opacity-100 { transition: opacity 0.2s; }
                .hover-opacity-100:hover { opacity: 1 !important; }

                footer.app-footer {
                    position: fixed;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 1030;
                    border-top-left-radius: 1.25rem;
                    border-top-right-radius: 1.25rem;
                    overflow: hidden;
                }

                .footer-text {
                    font-size: 0.8rem;
                }

                @media (max-width: 576px) {
                    body {
                        padding-bottom: 48px;
                    }

                    footer.app-footer {
                        padding-top: 0.25rem !important;
                        padding-bottom: 0.25rem !important;
                    }

                    footer.app-footer .footer-text {
                        font-size: 0.75rem;
                    }

                    footer.app-footer .mb-1 {
                        margin-bottom: 0.25rem !important;
                    }
                }

                
            </style>
        </head>
        <body>

        <div class="hero-section">
            <div class="container px-4">
                <div class="hero-brand d-flex align-items-center justify-content-center mb-3" style="gap: 10px;">
                    <h4 class="fw-bold m-0">
                        <a href="index.php?id=<?php echo urlencode($rootFolderId); ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white" aria-label="Ke halaman utama">
                            <i class="fa-solid fa-graduation-cap text-warning"></i> Scholarium
                        </a>
                    </h4>
                    <button type="button" class="hero-mini-search" id="heroMiniSearch" aria-label="Cari">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>

                <div class="search-container">
                    <form action="" method="GET">
                        <input type="text" id="mainSearch" name="q" class="search-input w-100" 
                            placeholder="Cari kitab, dokumen, arsip..." autocomplete="off"
                            value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                    <div id="live-results"></div>
                </div>
            </div>
        </div>

        <div class="container px-3 pb-5" style="max-width: 900px;">
            
            <div class="d-flex justify-content-between align-items-center mb-3 pt-2">
                
                <div style="flex: 1; min-width: 0;">
                    <?php if ($isSearchMode): ?>
                        <span class="badge bg-primary rounded-pill px-3 py-2">Search: <?php echo htmlspecialchars($searchQuery); ?></span>
                    <?php else: ?>
                        <?php if ($currentId != $rootFolderId): ?>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb breadcrumb-scroll mb-0 small">
                                    <li class="breadcrumb-item"><a href="index.php?id=<?php echo $rootFolderId; ?>" class="text-decoration-none fw-bold text-dark"><i class="fa-solid fa-home"></i></a></li>
                                    <?php if($parentId): ?><li class="breadcrumb-item"><a href="index.php?id=<?php echo $parentId; ?>" class="text-decoration-none text-secondary">...</a></li><?php endif; ?>
                                    <li class="breadcrumb-item active text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($pageTitle); ?></li>
                                </ol>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2 ms-2">
                    <?php if (!$isSearchMode && $currentId != $rootFolderId): ?>
                        <a href="index.php?id=<?php echo $parentId; ?>" class="btn btn-light rounded-circle shadow-sm" style="width:38px; height:38px; padding:0; display:grid; place-items:center;">
                            <i class="fa-solid fa-arrow-turn-up text-secondary"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($items) > 0): ?>
                <?php foreach ($items as $index => $item): ?>
                    <?php 
                        // Animation delay staggering
                        $delay = min($index * 0.05, 0.5); 
                        list($iconClass, $colorText, $bgClass) = getFileIconData($item['name'], $item['type']);
                    ?>
                    
                    <div class="file-card animate-in" style="animation-delay: <?php echo $delay; ?>s;">
                        <div class="icon-wrapper <?php echo $bgClass; ?>">
                            <?php
                                $thumbUrl = '';
                                if ($item['type'] === 'FILE') {
                                    $thumbUrl = getDriveThumbnailUrl($item['drive_id'], $item['name'], $item['type']);
                                }
                            ?>
                            <?php if (!empty($thumbUrl)): ?>
                                <img
                                    src="<?php echo htmlspecialchars($thumbUrl); ?>"
                                    alt=""
                                    class="file-thumb"
                                    loading="lazy"
                                    onload="this.parentElement.classList.add('thumb-loaded')"
                                    onerror="this.remove()"
                                />
                            <?php endif; ?>
                            <i class="fa-solid <?php echo $iconClass; ?>"></i>
                        </div>

                        <div style="flex: 1; min-width: 0;"> <?php if ($item['type'] == 'FOLDER'): ?>
                                <a href="index.php?id=<?php echo $item['drive_id']; ?>" class="file-name stretched-link">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                                <div class="file-meta">Folder</div>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars(getPreviewUrl($item['drive_id'], $item['link'])); ?>" target="_blank" class="file-name stretched-link" title="Baca">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                                <div class="file-meta">
                                    <?php echo $isSearchMode && $item['parent_id'] ? '<i class="fa-regular fa-folder-open"></i> In Directory' : 'File Dokumen'; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="action-group position-relative z-2">
                            <?php if ($item['type'] == 'FILE'): ?>
                                <a href="<?php echo htmlspecialchars(getPreviewUrl($item['drive_id'], $item['link'])); ?>" target="_blank" class="btn-action" title="Baca" aria-label="Baca" data-track-label="Baca" rel="noopener">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a href="https://drive.google.com/uc?export=download&id=<?php echo $item['drive_id']; ?>" class="btn-action text-primary" title="Download" aria-label="Download" data-track-label="Download" rel="noopener">
                                    <i class="fa-solid fa-cloud-arrow-down"></i>
                                </a>
                            <?php else: ?>
                                <a href="#" class="btn-action text-primary" title="Download Folder (ZIP)" aria-label="Download Folder (ZIP)" data-download-folder-id="<?php echo htmlspecialchars((string)$item['drive_id']); ?>" data-download-folder-name="<?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES); ?>">
                                    <i class="fa-solid fa-cloud-arrow-down"></i>
                                </a>
                                <div class="btn-action border-0 bg-transparent" aria-hidden="true">
                                    <i class="fa-solid fa-chevron-right text-muted opacity-50"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center pagination-sm">
                        <?php 
                            $baseUrl = "?";
                            if($isSearchMode) $baseUrl .= "q=".urlencode($searchQuery)."&";
                            else $baseUrl .= "id=".urlencode($currentId)."&";
                        ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-start-pill px-3" href="<?php echo $baseUrl; ?>page=<?php echo $page - 1; ?>">Prev</a>
                        </li>
                        <li class="page-item disabled"><span class="page-link px-3 bg-light border-light text-dark fw-bold"><?php echo $page; ?></span></li>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-end-pill px-3" href="<?php echo $baseUrl; ?>page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 animate-in">
                    <div class="bg-white rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-box-open text-muted fs-2 opacity-50"></i>
                    </div>
                    <h6 class="text-secondary fw-semibold">Tidak ada data ditemukan</h6>
                    <p class="text-muted small">Coba kata kunci lain atau kembali ke root.</p>
                </div>
            <?php endif; ?>

        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // show a spinner briefly on folder-download buttons to indicate action
            document.querySelectorAll('[data-track-label="Download Folder ZIP"]').forEach(function(el){
                el.addEventListener('click', function(){
                    try {
                        var icon = this.querySelector('i');
                        if (!icon) return;
                        icon.classList.remove('fa-file-zipper');
                        icon.classList.add('fa-spinner','fa-spin');
                        this.classList.add('disabled');
                        setTimeout(function(){
                            icon.classList.remove('fa-spinner','fa-spin');
                            icon.classList.add('fa-file-zipper');
                            el.classList.remove('disabled');
                        }, 8000);
                    } catch (e) {}
                });
            });

            // Header collapse on scroll (bertahap): sisakan logo + tombol search
            const hero = document.querySelector('.hero-section');
            const rootStyle = document.documentElement.style;
            const COLLAPSE_AT_PX = 140;
            let forceOpen = false;

            function updateHeroCollapse() {
                if (forceOpen) {
                    rootStyle.setProperty('--hero-collapse', '0');
                    hero.classList.remove('hero-collapse-end');
                    hero.classList.add('hero-force-open');
                    return;
                }

                const y = window.scrollY || document.documentElement.scrollTop || 0;
                const progress = Math.max(0, Math.min(1, y / COLLAPSE_AT_PX));
                rootStyle.setProperty('--hero-collapse', progress.toFixed(3));
                hero.classList.toggle('hero-collapse-end', progress > 0.85);
                hero.classList.remove('hero-force-open');
            }

            window.addEventListener('scroll', updateHeroCollapse, { passive: true });
            updateHeroCollapse();

            const searchInput = document.getElementById('mainSearch');
            const resultsBox = document.getElementById('live-results');
            const heroMiniSearch = document.getElementById('heroMiniSearch');
            let debounceTimer;

            if (heroMiniSearch) {
                heroMiniSearch.addEventListener('click', function() {
                    forceOpen = true;
                    updateHeroCollapse();
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        forceOpen = false;
                        updateHeroCollapse();
                        searchInput.blur();
                    }
                });

                searchInput.addEventListener('blur', function() {
                    if (!forceOpen) return;
                    forceOpen = false;
                    updateHeroCollapse();
                });
            }

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                clearTimeout(debounceTimer);
                
                if (query.length < 2) { resultsBox.style.display = 'none'; return; }

                debounceTimer = setTimeout(() => {
                    fetchResults(query);
                }, 300);
            });

            searchInput.addEventListener('focus', function() {
                if(this.value.length >= 2) resultsBox.style.display = 'block';
            });

            function fetchResults(query) {
                fetch('index.php?ajax_search=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        let html = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                let icon = item.icon_data; // [class, text-color, bg-class]
                                let linkUrl = item.type === 'FOLDER' ? 'index.php?id=' + item.drive_id : (item.preview_url || item.link);
                                let target = item.type === 'FILE' ? 'target="_blank"' : '';
                                let dirText = item.parent_name ? `<span class="ms-1 text-muted fw-normal" style="font-size:0.7rem"><i class="fa-regular fa-folder"></i> ${item.parent_name}</span>` : '';

                                let thumbHtml = '';
                                if (item.thumb_url) {
                                    thumbHtml = `<img src="${item.thumb_url}" alt="" class="file-thumb" loading="lazy" onload="this.parentElement.classList.add('thumb-loaded')" onerror="this.remove()" />`;
                                }

                                html += `
                                <a href="${linkUrl}" ${target} class="live-item">
                                    <div class="icon-wrapper ${icon[2]}" style="width:35px; height:35px; font-size:1rem; border-radius:8px;">
                                        ${thumbHtml}
                                        <i class="fa-solid ${icon[0]}"></i>
                                    </div>
                                    <div style="flex:1; overflow:hidden;">
                                        <div class="text-truncate fw-semibold" style="font-size:0.9rem">${item.name}</div>
                                        <div class="small text-secondary" style="font-size:0.75rem">${item.type} ${dirText}</div>
                                    </div>
                                </a>`;
                            });
                            html += `<a href="index.php?q=${encodeURIComponent(query)}" class="live-item justify-content-center text-primary fw-bold bg-light py-2" style="font-size:0.85rem">Lihat Semua Hasil</a>`;
                        } else {
                            html = `<div class="p-3 text-center text-muted small">Tidak ditemukan hasil.</div>`;
                        }
                        resultsBox.innerHTML = html;
                        resultsBox.style.display = 'block';
                    });
            }

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                    resultsBox.style.display = 'none';
                }
            });
        });
        </script>

        <!-- Footer Section -->
        <footer class="app-footer py-1 py-md-2" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white;">
            <div class="container px-3 px-md-4" style="max-width: 900px;">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start mb-1 mb-md-0">
                        <small class="opacity-75 footer-text">
                            &copy; <?php echo date('Y'); ?> <strong>Scholarium Digital Library</strong>. All rights reserved.
                        </small>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <small class="opacity-75 footer-text">
                            <a href="privacy.php" class="text-white text-decoration-none me-3 hover-opacity-100">Kebijakan Privasi</a>
                            <a href="terms.php" class="text-white text-decoration-none hover-opacity-100">Syarat & Ketentuan</a>
                        </small>
                    </div>
                </div>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="click-tracker.js"></script>

    <!-- Download confirmation modal -->
    <div class="modal fade" id="downloadConfirmModal" tabindex="-1" aria-labelledby="downloadConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="downloadConfirmModalLabel">Konfirmasi unduhan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="downloadConfirmBody">Mengambil informasi folder...</div>
            <div class="mt-2 small text-muted">Jika ukuran besar, pastikan koneksi dan ruang penyimpanan mencukupi.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <a href="#" class="btn btn-primary" id="confirmDownloadBtn">Unduh sekarang</a>
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      const modalEl = document.getElementById('downloadConfirmModal');
      const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;
      const bodyEl = document.getElementById('downloadConfirmBody');
      const confirmBtn = document.getElementById('confirmDownloadBtn');
      let currentId = null;

      function humanSize(bytes){
        if (!bytes || bytes <= 0) return 'N/A';
        const units = ['B','KB','MB','GB','TB'];
        let i=0; let b = bytes;
        while (b >= 1024 && i < units.length-1){ b/=1024; i++; }
        return b.toFixed(2) + ' ' + units[i];
      }

      document.querySelectorAll('[data-download-folder-id]').forEach(function(el){
        el.addEventListener('click', function(e){
          e.preventDefault();
          const fid = this.getAttribute('data-download-folder-id');
          const fname = this.getAttribute('data-download-folder-name') || fid;
          currentId = fid;
          bodyEl.textContent = 'Mengambil informasi folder...';
          confirmBtn.setAttribute('href', '#');
          confirmBtn.classList.add('disabled');
          if (bsModal) bsModal.show();

          fetch('folder_info.php?id=' + encodeURIComponent(fid))
            .then(r => r.json())
            .then(data => {
              if (data.error) {
                bodyEl.innerHTML = '<div class="text-danger">Gagal mengambil info: ' + (data.message || data.error) + '</div>';
                confirmBtn.classList.add('disabled');
                return;
              }
              const human = data.bytes && data.bytes > 0 ? humanSize(data.bytes) : 'Ukuran tidak tersedia (ada ' + data.google_docs + ' file Google Docs)';
              bodyEl.innerHTML = '<strong>' + escapeHtml(fname) + '</strong><br>' +
                'Jumlah file: ' + (data.files || 0) + (data.google_docs ? ' (+'+data.google_docs+' Google Docs)' : '') + '<br>' +
                'Perkiraan ukuran: <strong>' + human + '</strong>';
              confirmBtn.classList.remove('disabled');
              confirmBtn.setAttribute('href', 'download_folder.php?id=' + encodeURIComponent(fid));
              confirmBtn.addEventListener('click', function(){
                if (bsModal) bsModal.hide();
                window.open(this.getAttribute('href'), '_blank');
              }, { once: true });
            }).catch(err => {
              bodyEl.innerHTML = '<div class="text-danger">Gagal mengambil info folder.</div>';
              confirmBtn.classList.add('disabled');
            });
        });
      });

      // small helper for safe text
      function escapeHtml(str){
        if (!str) return '';
        return (str+'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }
    })();
    </script>

        </body>
        </html>