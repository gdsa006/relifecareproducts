<?php
/**
 * RelifeProducts — Backend API
 * File: api.php
 * Simple PHP REST API using JSON flat-file storage (no database required)
 * Drop on any PHP 7.4+ shared hosting — works out of the box.
 */

// ── CORS & Headers ────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Config ────────────────────────────────────────────────────────────────────
define('DATA_DIR',   __DIR__ . '/data/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('PRODUCTS_FILE', DATA_DIR . 'products.json');
define('QUERIES_FILE',  DATA_DIR . 'queries.json');
define('ADMIN_KEY', 'relife_admin_2025');   // Change this!
define('MAX_UPLOAD_MB', 5);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Seed products if empty
if (!file_exists(PRODUCTS_FILE)) {
    file_put_contents(PRODUCTS_FILE, json_encode(seed_products(), JSON_PRETTY_PRINT));
}
if (!file_exists(QUERIES_FILE)) {
    file_put_contents(QUERIES_FILE, json_encode([], JSON_PRETTY_PRINT));
}

// ── Router ────────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path   = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts  = explode('/', $path);

// Strip script name if present (e.g. api.php/products)
if (isset($parts[0]) && strpos($parts[0], '.php') !== false) array_shift($parts);

$resource = $parts[0] ?? '';
$id       = $parts[1] ?? null;

switch ($resource) {

    case 'products':
        switch ($method) {
            case 'GET':    respond(load(PRODUCTS_FILE)); break;
            case 'POST':   require_admin(); respond(create_product()); break;
            case 'DELETE': require_admin(); respond(delete_product($id)); break;
            case 'PUT':    require_admin(); respond(update_product($id)); break;
            default: respond_error(405, 'Method not allowed');
        }
        break;

    case 'queries':
        switch ($method) {
            case 'GET':    require_admin(); respond(load(QUERIES_FILE)); break;
            case 'POST':   respond(create_query()); break;
            case 'DELETE': require_admin(); respond(delete_query($id)); break;
            default: respond_error(405, 'Method not allowed');
        }
        break;

    case 'upload':
        if ($method !== 'POST') respond_error(405, 'Method not allowed');
        require_admin();
        respond(handle_upload());
        break;

    case 'login':
        if ($method !== 'POST') respond_error(405, 'Method not allowed');
        $body = json_input();
        if (($body['key'] ?? '') === ADMIN_KEY) {
            respond(['success' => true, 'token' => ADMIN_KEY]);
        } else {
            respond_error(401, 'Invalid admin key');
        }
        break;

    case 'stats':
        require_admin();
        $products = load(PRODUCTS_FILE);
        $queries  = load(QUERIES_FILE);
        $cats = [];
        foreach ($products as $p) {
            $c = $p['category'] ?? 'Unknown';
            $cats[$c] = ($cats[$c] ?? 0) + 1;
        }
        respond([
            'product_count'  => count($products),
            'query_count'    => count($queries),
            'categories'     => $cats,
            'recent_queries' => array_slice($queries, 0, 5),
        ]);
        break;

    default:
        respond_error(404, 'Endpoint not found');
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function load(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save(string $file, array $data): void {
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond_error(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function require_admin(): void {
    $key = $_SERVER['HTTP_X_ADMIN_KEY'] ?? ($_GET['key'] ?? '');
    if ($key !== ADMIN_KEY) respond_error(401, 'Unauthorized');
}

function slugify(string $text): string {
    return preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text)));
}

// ── CRUD: Products ────────────────────────────────────────────────────────────

function create_product(): array {
    // Handle multipart form (with image) OR JSON
    if (!empty($_POST)) {
        $body = $_POST;
        $img_url = '';
        if (!empty($_FILES['image']['tmp_name'])) {
            $img_url = upload_image($_FILES['image']);
        }
    } else {
        $body = json_input();
        $img_url = $body['image'] ?? '';
    }

    if (empty($body['name'])) respond_error(400, 'name is required');
    if (empty($body['category'])) respond_error(400, 'category is required');

    $product = [
        'id'       => uniqid('p_'),
        'name'     => strip_tags($body['name']),
        'category' => strip_tags($body['category']),
        'emoji'    => $body['emoji'] ?? '🌿',
        'desc'     => strip_tags($body['desc'] ?? ''),
        'origin'   => strip_tags($body['origin'] ?? ''),
        'image'    => $img_url,
        'created'  => date('Y-m-d H:i:s'),
    ];

    $products = load(PRODUCTS_FILE);
    array_unshift($products, $product);
    save(PRODUCTS_FILE, $products);

    return $product;
}

function update_product(?string $id): array {
    if (!$id) respond_error(400, 'ID required');
    $body     = json_input();
    $products = load(PRODUCTS_FILE);

    foreach ($products as &$p) {
        if ($p['id'] === $id) {
            foreach (['name','category','emoji','desc','origin','image'] as $field) {
                if (isset($body[$field])) $p[$field] = strip_tags($body[$field]);
            }
            $p['updated'] = date('Y-m-d H:i:s');
            save(PRODUCTS_FILE, $products);
            return $p;
        }
    }
    respond_error(404, 'Product not found');
}

function delete_product(?string $id): array {
    if (!$id) respond_error(400, 'ID required');
    $products = load(PRODUCTS_FILE);
    $filtered = array_filter($products, fn($p) => $p['id'] !== $id);
    if (count($filtered) === count($products)) respond_error(404, 'Not found');
    save(PRODUCTS_FILE, $filtered);
    return ['deleted' => $id];
}

// ── CRUD: Queries ─────────────────────────────────────────────────────────────

function create_query(): array {
    $body = json_input();

    $required = ['company', 'email'];
    foreach ($required as $f) {
        if (empty($body[$f])) respond_error(400, "$f is required");
    }

    // Basic email validation
    if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
        respond_error(400, 'Invalid email address');
    }

    $query = [
        'id'       => uniqid('q_'),
        'company'  => strip_tags($body['company']),
        'name'     => strip_tags($body['name'] ?? ''),
        'email'    => filter_var($body['email'], FILTER_SANITIZE_EMAIL),
        'phone'    => strip_tags($body['phone'] ?? ''),
        'country'  => strip_tags($body['country'] ?? ''),
        'product'  => strip_tags($body['product'] ?? ''),
        'quantity' => strip_tags($body['quantity'] ?? ''),
        'message'  => strip_tags($body['message'] ?? ''),
        'status'   => 'new',
        'created'  => date('Y-m-d H:i:s'),
        'date'     => date('d M Y'),
    ];

    $queries = load(QUERIES_FILE);
    array_unshift($queries, $query);
    save(QUERIES_FILE, $queries);

    // Optional: send email notification (uncomment and configure)
    /*
    mail(
        'exports@relifeproducts.com',
        'New Enquiry from ' . $query['company'],
        "Company: {$query['company']}\nEmail: {$query['email']}\nProduct: {$query['product']}\nMessage: {$query['message']}",
        'From: noreply@relifeproducts.com'
    );
    */

    return $query;
}

function delete_query(?string $id): array {
    if (!$id) respond_error(400, 'ID required');
    $queries  = load(QUERIES_FILE);
    $filtered = array_filter($queries, fn($q) => $q['id'] !== $id);
    if (count($filtered) === count($queries)) respond_error(404, 'Not found');
    save(QUERIES_FILE, $filtered);
    return ['deleted' => $id];
}

// ── File Upload ───────────────────────────────────────────────────────────────

function handle_upload(): array {
    if (empty($_FILES['image'])) respond_error(400, 'No image file');
    $url = upload_image($_FILES['image']);
    return ['url' => $url];
}

function upload_image(array $file): string {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) respond_error(400, 'Invalid file type');
    if ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) respond_error(400, 'File too large');

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . strtolower($ext);
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        respond_error(500, 'Upload failed');
    }

    // Return relative URL
    $base = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    return $base . '/uploads/' . $filename;
}

// ── Seed Data ─────────────────────────────────────────────────────────────────

function seed_products(): array {
    return [
        ['id'=>'p_001','name'=>'Premium Basmati Rice','category'=>'Cereals & Grains','emoji'=>'🌾','desc'=>'Long-grain aromatic basmati, aged 2 years for optimal fragrance and cooking quality.','origin'=>'Punjab, India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_002','name'=>'Organic Quinoa','category'=>'Cereals & Grains','emoji'=>'🌿','desc'=>'Certified organic white quinoa, triple-washed and ready for global food brands.','origin'=>'Bolivia','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_003','name'=>'Rolled Oats (Jumbo)','category'=>'Cereals & Grains','emoji'=>'🥣','desc'=>'Large-flake jumbo oats, ideal for private label breakfast and snack manufacturing.','origin'=>'India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_004','name'=>'Turmeric Powder','category'=>'Spices & Herbs','emoji'=>'🟡','desc'=>'High-curcumin turmeric (5%+), steam sterilised, food-grade certified.','origin'=>'Erode, India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_005','name'=>'Black Pepper (Whole)','category'=>'Spices & Herbs','emoji'=>'⚫','desc'=>'Malabar grade black pepper, 550GL density, suitable for retail and processing.','origin'=>'Kerala, India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_006','name'=>'Cardamom (Green)','category'=>'Spices & Herbs','emoji'=>'🌱','desc'=>'Bold size green cardamom, hand-sorted, intense aroma for premium applications.','origin'=>'Guatemala / India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_007','name'=>'Dark Chocolate Compound','category'=>'Confectionery','emoji'=>'🍫','desc'=>'55% cocoa dark compound for coating, moulding and confectionery production.','origin'=>'Belgium / India blend','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_008','name'=>'Glucose Syrup (DE42)','category'=>'Confectionery','emoji'=>'🍬','desc'=>'DE42 glucose syrup in 280kg barrels, suitable for candy and gum manufacture.','origin'=>'India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_009','name'=>'Raw Cane Sugar (ICUMSA 45)','category'=>'Sweeteners','emoji'=>'🍯','desc'=>'ICUMSA 45 and 150 grades available in bulk; full export documentation.','origin'=>'Brazil / India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_010','name'=>'Stevia Extract (RA97)','category'=>'Sweeteners','emoji'=>'🌼','desc'=>'High-purity rebaudioside A (97%) stevia, non-GMO, suitable for beverages.','origin'=>'China / India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_011','name'=>'Palm Olein (RBD)','category'=>'Fats & Oils','emoji'=>'🫙','desc'=>'Refined, bleached, deodorised palm olein for frying, baking and food service.','origin'=>'Malaysia / Indonesia','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_012','name'=>'High Oleic Sunflower Oil','category'=>'Fats & Oils','emoji'=>'🌻','desc'=>'Extended shelf life, ideal for snack and fried food sectors.','origin'=>'Ukraine / India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_013','name'=>'Instant Noodles (OEM)','category'=>'Ready-to-Eat','emoji'=>'🍜','desc'=>'Private label instant noodles in 65g-100g packs; custom flavour sachets available.','origin'=>'India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_014','name'=>'Alphonso Mango Pulp','category'=>'Ready-to-Eat','emoji'=>'🥭','desc'=>'Aseptic Alphonso mango pulp, Brix 16-17, in 850g tins or 210kg aseptic bags.','origin'=>'Ratnagiri, India','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_015','name'=>'Coriander Seeds (Eagle)','category'=>'Spices & Herbs','emoji'=>'🍃','desc'=>'Eagle-grade whole coriander, low moisture, fumigation-free on request.','origin'=>'Rajasthan, India','image'=>'','created'=>date('Y-m-d H:i:s')],
    ];
}
