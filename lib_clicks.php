<?php

function clicks_storage_dir(): string {
    require __DIR__ . '/admin_config.php';
    if (!is_dir($CLICK_STORAGE_DIR)) {
        @mkdir($CLICK_STORAGE_DIR, 0755, true);
    }
    return $CLICK_STORAGE_DIR;
}

function clicks_sqlite_path(): string {
    return clicks_storage_dir() . DIRECTORY_SEPARATOR . 'clicks.sqlite';
}

function clicks_jsonl_path(): string {
    return clicks_storage_dir() . DIRECTORY_SEPARATOR . 'clicks.jsonl';
}

function clicks_try_pdo_sqlite(): ?PDO {
    try {
        if (!class_exists('PDO')) return null;
        $pdo = new PDO('sqlite:' . clicks_sqlite_path());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS clicks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            target TEXT NOT NULL,
            label TEXT,
            source TEXT,
            ip TEXT,
            user_agent TEXT
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clicks_target ON clicks(target)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clicks_created ON clicks(created_at)');

        // Migrate older DBs that don't have the label column yet.
        $cols = $pdo->query("PRAGMA table_info(clicks)")->fetchAll(PDO::FETCH_ASSOC);
        $hasLabel = false;
        foreach ($cols as $c) {
            if (isset($c['name']) && $c['name'] === 'label') { $hasLabel = true; break; }
        }
        if (!$hasLabel) {
            $pdo->exec('ALTER TABLE clicks ADD COLUMN label TEXT');
        }

        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function clicks_store(array $row): void {
    $createdAt = $row['created_at'] ?? gmdate('c');
    $target = (string)($row['target'] ?? '');
    $label = (string)($row['label'] ?? '');
    $source = (string)($row['source'] ?? '');
    $ip = (string)($row['ip'] ?? '');
    $ua = (string)($row['user_agent'] ?? '');

    $target = trim($target);
    if ($target === '') return;

    // Basic length limits to avoid abuse
    if (strlen($target) > 2000) $target = substr($target, 0, 2000);
    $label = trim($label);
    if (strlen($label) > 200) $label = substr($label, 0, 200);
    if (strlen($source) > 500) $source = substr($source, 0, 500);
    if (strlen($ip) > 80) $ip = substr($ip, 0, 80);
    if (strlen($ua) > 300) $ua = substr($ua, 0, 300);

    $pdo = clicks_try_pdo_sqlite();
    if ($pdo) {
        $stmt = $pdo->prepare('INSERT INTO clicks(created_at, target, label, source, ip, user_agent) VALUES(:created_at, :target, :label, :source, :ip, :ua)');
        $stmt->execute([
            ':created_at' => $createdAt,
            ':target' => $target,
            ':label' => $label,
            ':source' => $source,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
        return;
    }

    // Fallback JSONL
    $line = json_encode([
        'created_at' => $createdAt,
        'target' => $target,
        'label' => $label,
        'source' => $source,
        'ip' => $ip,
        'user_agent' => $ua,
    ], JSON_UNESCAPED_SLASHES) . "\n";

    $path = clicks_jsonl_path();
    $fp = @fopen($path, 'ab');
    if (!$fp) return;
    try {
        @flock($fp, LOCK_EX);
        @fwrite($fp, $line);
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

function clicks_get_summary(int $limit = 200): array {
    $limit = max(1, min($limit, 1000));

    $pdo = clicks_try_pdo_sqlite();
    if ($pdo) {
        // label is best-effort (can be empty or vary), so we keep a representative value.
        $stmt = $pdo->query('SELECT target, MAX(label) AS label, COUNT(*) AS total, MAX(created_at) AS last_at FROM clicks GROUP BY target ORDER BY total DESC, last_at DESC LIMIT ' . (int)$limit);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fallback JSONL aggregation
    $path = clicks_jsonl_path();
    if (!is_file($path)) return [];

    $counts = [];
    $lastAt = [];
    $labels = [];

    $fp = @fopen($path, 'rb');
    if (!$fp) return [];
    while (!feof($fp)) {
        $line = fgets($fp);
        if ($line === false) break;
        $row = json_decode($line, true);
        if (!is_array($row)) continue;
        $t = (string)($row['target'] ?? '');
        if ($t === '') continue;
        $counts[$t] = ($counts[$t] ?? 0) + 1;
        $lbl = trim((string)($row['label'] ?? ''));
        if ($lbl !== '' && !isset($labels[$t])) $labels[$t] = $lbl;
        $ts = (string)($row['created_at'] ?? '');
        if ($ts !== '') {
            if (!isset($lastAt[$t]) || strcmp($ts, $lastAt[$t]) > 0) $lastAt[$t] = $ts;
        }
    }
    fclose($fp);

    $out = [];
    foreach ($counts as $t => $c) {
        $out[] = ['target' => $t, 'label' => $labels[$t] ?? '', 'total' => $c, 'last_at' => $lastAt[$t] ?? ''];
    }

    usort($out, function ($a, $b) {
        if ($a['total'] === $b['total']) return strcmp($b['last_at'], $a['last_at']);
        return $b['total'] <=> $a['total'];
    });

    return array_slice($out, 0, $limit);
}

function clicks_count_all(): int {
    $pdo = clicks_try_pdo_sqlite();
    if ($pdo) {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) AS c FROM clicks');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return (int)($row['c'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    $path = clicks_jsonl_path();
    if (!is_file($path)) return 0;

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return 0;
    return count($lines);
}

function clicks_get_recent(int $limit = 100, int $offset = 0): array {
    $limit = max(1, min($limit, 1000));
    $offset = max(0, $offset);

    $pdo = clicks_try_pdo_sqlite();
    if ($pdo) {
        $stmt = $pdo->query('SELECT created_at, target, label, source, ip, user_agent FROM clicks ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $path = clicks_jsonl_path();
    if (!is_file($path)) return [];

    // JSONL: read all, reverse (newest first), then apply offset/limit (acceptable for small logs)
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];

    $lines = array_reverse($lines);
    $slice = array_slice($lines, $offset, $limit);

    $out = [];
    foreach ($slice as $line) {
        $row = json_decode($line, true);
        if (!is_array($row)) continue;
        $out[] = [
            'created_at' => (string)($row['created_at'] ?? ''),
            'target' => (string)($row['target'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'source' => (string)($row['source'] ?? ''),
            'ip' => (string)($row['ip'] ?? ''),
            'user_agent' => (string)($row['user_agent'] ?? ''),
        ];
    }
    return $out;
}
