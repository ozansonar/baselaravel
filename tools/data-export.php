<?php

/**
 * Eski site içerik export scripti.
 * Bu dosyayı eski sitenin root dizinine koyun.
 *
 * Kullanım:
 *   site.com/data-export.php?key=GIZLI_ANAHTAR                         → Tüm içerikler (kategorileriyle)
 *   site.com/data-export.php?key=GIZLI_ANAHTAR&page=2&limit=50         → Sayfalı
 *   site.com/data-export.php?key=GIZLI_ANAHTAR&mode=categories         → Sadece kategoriler
 *   site.com/data-export.php?key=GIZLI_ANAHTAR&mode=stats              → Özet istatistik
 */

// ── Güvenlik anahtarı ──
const SECRET_KEY = 'orhan-baba-export-2026';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Geçersiz anahtar'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── DB bağlantısı (bilgileri kendi DB'ine göre düzenle) ──
$host = 'localhost';
$port = '3306';
$db   = 'VERITABANI_ADI';        // ← buraya DB adını yaz
$user = 'VERITABANI_KULLANICI';   // ← buraya DB kullanıcı adını yaz
$pass = 'VERITABANI_SIFRE';       // ← buraya DB şifresini yaz

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB bağlantı hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$mode = $_GET['mode'] ?? 'export';

// ── Mode: İstatistik ──
if ($mode === 'stats') {
    $stmt = $pdo->query('
        SELECT
            cc.id AS cat_id,
            cc.name AS cat_name,
            cc.link AS cat_link,
            COUNT(c.id) AS content_count
        FROM content_categories cc
        LEFT JOIN content c ON c.cat_id = cc.id AND c.deleted = 0
        WHERE cc.deleted = 0
        GROUP BY cc.id, cc.name, cc.link
        ORDER BY cc.show_order
    ');

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStmt = $pdo->query('SELECT COUNT(*) FROM content WHERE deleted = 0');
    $total = (int) $totalStmt->fetchColumn();

    echo json_encode([
        'total_content'  => $total,
        'categories'     => $categories,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Mode: Kategoriler ──
if ($mode === 'categories') {
    $stmt = $pdo->query('
        SELECT id, name, link, img, show_type, show_order, status, created_at, updated_at
        FROM content_categories
        WHERE deleted = 0
        ORDER BY show_order
    ');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['categories' => $categories], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Mode: İçerik export ──
if ($mode === 'export') {
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = min(500, max(1, (int) ($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;
    $catId  = $_GET['cat_id'] ?? null;

    // Toplam kayıt
    $countSql = 'SELECT COUNT(*) FROM content WHERE deleted = 0';
    $params = [];
    if ($catId !== null) {
        $countSql .= ' AND cat_id = ?';
        $params[] = (int) $catId;
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // İçerikleri kategorileriyle birlikte çek
    $sql = '
        SELECT
            c.id,
            c.cat_id,
            cc.name AS category_name,
            cc.link AS category_link,
            c.title,
            c.link,
            c.text,
            c.abstract,
            c.img,
            c.video,
            c.keywords,
            c.description,
            c.show_count,
            c.status,
            c.created_at,
            c.updated_at
        FROM content c
        LEFT JOIN content_categories cc ON cc.id = c.cat_id
        WHERE c.deleted = 0
    ';

    $queryParams = [];
    if ($catId !== null) {
        $sql .= ' AND c.cat_id = ?';
        $queryParams[] = (int) $catId;
    }

    $sql .= ' ORDER BY c.id ASC LIMIT ? OFFSET ?';
    $queryParams[] = $limit;
    $queryParams[] = $offset;

    $stmt = $pdo->prepare($sql);

    // PDO parametre binding
    foreach ($queryParams as $i => $val) {
        $stmt->bindValue($i + 1, $val, PDO::PARAM_INT);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eski URL'yi oluştur (redirect mapping için)
    foreach ($rows as &$row) {
        $row['old_url'] = '/icerik/' . ($row['category_link'] ?? 'genel') . '/' . $row['link'] . '-' . $row['id'];
    }
    unset($row);

    echo json_encode([
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $limit,
        'total_pages' => (int) ceil($total / $limit),
        'data'        => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo json_encode(['error' => "Geçersiz mode: '{$mode}'. Kullanılabilir: export, categories, stats"], JSON_UNESCAPED_UNICODE);
