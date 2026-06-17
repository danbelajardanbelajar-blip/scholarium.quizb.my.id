<?php
session_start();
require __DIR__ . '/admin_config.php';

// Authentication Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// CSRF Check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// MySQL Connection
$host = 'localhost';
$dbname = 'quic1934_scholarium';
$user = 'quic1934_zenhkm';
$pass = '03Maret1990';
$tableName = 'library_tree';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

// 1. DataTables Server-Side Processing
if ($action === 'list') {
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = $_POST['search']['value'] ?? '';
    
    // Ordering
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
    $orderDir = $_POST['order'][0]['dir'] ?? 'asc';
    $columns = ['id', 'name', 'type', 'parent_id', 'drive_id', 'link'];
    $orderBy = $columns[$orderColumnIndex] ?? 'id';
    if (!in_array($orderDir, ['asc', 'desc'])) $orderDir = 'desc';

    // Build Query
    $where = "";
    $params = [];
    if (!empty($searchValue)) {
        $where = "WHERE name LIKE :search OR drive_id LIKE :search OR parent_id LIKE :search";
        $params['search'] = "%$searchValue%";
    }

    // Total records
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM $tableName");
    $recordsTotal = $stmtTotal->fetchColumn();

    // Filtered records
    $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM $tableName $where");
    $stmtFiltered->execute($params);
    $recordsFiltered = $stmtFiltered->fetchColumn();

    // Fetch data
    $start = max(0, intval($start));
    $length = max(1, intval($length));
    $sql = "SELECT * FROM $tableName $where ORDER BY $orderBy $orderDir LIMIT $start, $length";
    $stmtData = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmtData->bindValue(":$key", $val);
    }
    $stmtData->execute();
    $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => intval($recordsTotal),
        "recordsFiltered" => intval($recordsFiltered),
        "data" => $data
    ]);
    exit;
}

// 2. Create Record
if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'FILE');
    $drive_id = trim($_POST['drive_id'] ?? '');
    $parent_id = trim($_POST['parent_id'] ?? '');
    $link = trim($_POST['link'] ?? '');
    
    if (empty($parent_id)) $parent_id = null;
    if (empty($link)) $link = null;

    if (empty($name) || empty($drive_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and Drive ID are required']);
        exit;
    }

    try {
        // Calculate level_depth and path_visual based on parent
        $level_depth = 0;
        $path_visual = $name;
        if ($parent_id) {
            $stmtParent = $pdo->prepare("SELECT level_depth, path_visual FROM $tableName WHERE drive_id = :pid");
            $stmtParent->execute(['pid' => $parent_id]);
            $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);
            if ($parent) {
                $level_depth = (int)$parent['level_depth'] + 1;
                $path_visual = $parent['path_visual'] . ' > ' . $name;
            }
        }

        // Get next ID (if not auto-increment, though we assume auto_increment)
        // From SQL dump: `id` int(11) NOT NULL. No auto_increment mentioned in standard definition?
        // Wait, often it is. Let's do a safe insert.
        $stmt = $pdo->prepare("INSERT INTO $tableName (drive_id, parent_id, name, type, link, level_depth, path_visual) VALUES (:drive_id, :parent_id, :name, :type, :link, :level_depth, :path_visual)");
        $stmt->execute([
            'drive_id' => $drive_id,
            'parent_id' => $parent_id,
            'name' => $name,
            'type' => $type,
            'link' => $link,
            'level_depth' => $level_depth,
            'path_visual' => $path_visual
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Data created successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// 3. Update Record
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'FILE');
    $drive_id = trim($_POST['drive_id'] ?? '');
    $parent_id = trim($_POST['parent_id'] ?? '');
    $link = trim($_POST['link'] ?? '');
    
    if (empty($parent_id)) $parent_id = null;
    if (empty($link)) $link = null;

    if ($id <= 0 || empty($name) || empty($drive_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, Name, and Drive ID are required']);
        exit;
    }

    try {
        // Recalculate level_depth and path_visual
        $level_depth = 0;
        $path_visual = $name;
        if ($parent_id) {
            $stmtParent = $pdo->prepare("SELECT level_depth, path_visual FROM $tableName WHERE drive_id = :pid");
            $stmtParent->execute(['pid' => $parent_id]);
            $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);
            if ($parent) {
                $level_depth = (int)$parent['level_depth'] + 1;
                $path_visual = $parent['path_visual'] . ' > ' . $name;
            }
        }

        $stmt = $pdo->prepare("UPDATE $tableName SET drive_id = :drive_id, parent_id = :parent_id, name = :name, type = :type, link = :link, level_depth = :level_depth, path_visual = :path_visual WHERE id = :id");
        $stmt->execute([
            'drive_id' => $drive_id,
            'parent_id' => $parent_id,
            'name' => $name,
            'type' => $type,
            'link' => $link,
            'level_depth' => $level_depth,
            'path_visual' => $path_visual,
            'id' => $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Data updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// 4. Delete Record
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }

    try {
        // Check if it's a folder and has children
        $stmtCheck = $pdo->prepare("SELECT drive_id, type FROM $tableName WHERE id = :id");
        $stmtCheck->execute(['id' => $id]);
        $item = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($item && $item['type'] === 'FOLDER') {
            $stmtChild = $pdo->prepare("SELECT COUNT(*) FROM $tableName WHERE parent_id = :pid");
            $stmtChild->execute(['pid' => $item['drive_id']]);
            if ($stmtChild->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete folder because it contains files/subfolders. Please delete them first.']);
                exit;
            }
        }

        $stmt = $pdo->prepare("DELETE FROM $tableName WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        echo json_encode(['success' => true, 'message' => 'Data deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Action not found']);
