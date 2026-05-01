<?php
/**
 * RelifeProducts — Admin Dashboard
 * Reads/writes JSON data files directly — no internal HTTP/curl calls
 */

define('ADMIN_KEY',   'relife_admin_2025');
define('DATA_DIR',    dirname(__DIR__) . '/data/');
define('UPLOAD_DIR',  dirname(__DIR__) . '/uploads/');
define('PRODUCTS_FILE', DATA_DIR . 'products.json');
define('QUERIES_FILE',  DATA_DIR . 'queries.json');
define('MAX_UPLOAD_MB', 5);

session_start();
$logged_in = isset($_SESSION['rp_admin']) && $_SESSION['rp_admin'] === true;

/* ── Helpers ── */
function load_json(string $file): array {
    if (!file_exists($file)) return [];
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : [];
}
function save_json(string $file, array $data): void {
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function seed_if_empty(): void {
    if (file_exists(PRODUCTS_FILE) && filesize(PRODUCTS_FILE) > 10) return;
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    $seed = [
        ['id'=>'p_001','name'=>'Premium Basmati Rice',       'category'=>'Cereals & Grains','emoji'=>'🌾','desc'=>'Long-grain aromatic basmati, aged 2 years for optimal fragrance.','origin'=>'Punjab, India',      'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_002','name'=>'Organic Quinoa',              'category'=>'Cereals & Grains','emoji'=>'🌿','desc'=>'Certified organic white quinoa, triple-washed, global food brands.','origin'=>'Bolivia',           'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_003','name'=>'Rolled Oats (Jumbo)',         'category'=>'Cereals & Grains','emoji'=>'🥣','desc'=>'Large-flake jumbo oats for private label breakfast manufacturing.','origin'=>'India',             'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_004','name'=>'Turmeric Powder',             'category'=>'Spices & Herbs',  'emoji'=>'🟡','desc'=>'High-curcumin turmeric (5%+), steam sterilised, food-grade.','origin'=>'Erode, India',         'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_005','name'=>'Black Pepper (Whole)',        'category'=>'Spices & Herbs',  'emoji'=>'⚫','desc'=>'Malabar grade, 550GL density. Retail and industrial processing.','origin'=>'Kerala, India',      'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_006','name'=>'Green Cardamom',              'category'=>'Spices & Herbs',  'emoji'=>'🌱','desc'=>'Bold-size green cardamom, hand-sorted, intense aroma.','origin'=>'Guatemala / India',           'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_007','name'=>'Dark Chocolate Compound',    'category'=>'Confectionery',    'emoji'=>'🍫','desc'=>'55% cocoa dark compound for coating and confectionery production.','origin'=>'India / Belgium',  'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_008','name'=>'Glucose Syrup (DE42)',        'category'=>'Confectionery',    'emoji'=>'🍬','desc'=>'DE42 glucose syrup in 280kg barrels for candy manufacturing.','origin'=>'India',               'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_009','name'=>'Raw Cane Sugar ICUMSA 45',   'category'=>'Sweeteners',       'emoji'=>'🍯','desc'=>'ICUMSA 45 and 150 grades in bulk with full export documentation.','origin'=>'Brazil / India',   'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_010','name'=>'Stevia Extract RA97',         'category'=>'Sweeteners',       'emoji'=>'🌼','desc'=>'High-purity rebaudioside A 97%, non-GMO, for beverages.','origin'=>'India / China',            'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_011','name'=>'Palm Olein (RBD)',            'category'=>'Fats & Oils',      'emoji'=>'🫙','desc'=>'Refined bleached deodorised palm olein for frying and baking.','origin'=>'Malaysia / Indonesia','image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_012','name'=>'High Oleic Sunflower Oil',   'category'=>'Fats & Oils',      'emoji'=>'🌻','desc'=>'Extended shelf life sunflower oil for snack manufacturing.','origin'=>'Ukraine / India',        'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_013','name'=>'Instant Noodles (OEM)',      'category'=>'Ready-to-Eat',     'emoji'=>'🍜','desc'=>'Private label instant noodles 65g-100g. Custom flavour sachets.','origin'=>'India',             'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_014','name'=>'Alphonso Mango Pulp',        'category'=>'Ready-to-Eat',     'emoji'=>'🥭','desc'=>'Aseptic Alphonso mango pulp Brix 16-17, tins or aseptic bags.','origin'=>'Ratnagiri, India',  'image'=>'','created'=>date('Y-m-d H:i:s')],
        ['id'=>'p_015','name'=>'Coriander Seeds (Eagle)',    'category'=>'Spices & Herbs',   'emoji'=>'🍃','desc'=>'Eagle-grade whole coriander, low moisture, fumigation-free.','origin'=>'Rajasthan, India',     'image'=>'','created'=>date('Y-m-d H:i:s')],
    ];
    save_json(PRODUCTS_FILE, $seed);
}

seed_if_empty();

/* ── Login / Logout ── */
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        if (($_POST['key'] ?? '') === ADMIN_KEY) {
            $_SESSION['rp_admin'] = true;
            $logged_in = true;
        } else {
            $login_error = 'Invalid admin key. Please try again.';
        }
    }
    if ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

/* ── Admin actions (direct file I/O) ── */
$success_msg = '';
$error_msg   = '';

if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ADD PRODUCT */
    if ($action === 'add_product') {
        $name     = trim(strip_tags($_POST['name']     ?? ''));
        $category = trim(strip_tags($_POST['category'] ?? ''));
        $emoji    = trim($_POST['emoji']    ?? '🌿');
        $desc     = trim(strip_tags($_POST['desc']     ?? ''));
        $origin   = trim(strip_tags($_POST['origin']   ?? ''));

        if (!$name || !$category) {
            $error_msg = 'Product name and category are required.';
        } else {
            // Handle image upload
            $img_path = '';
            if (!empty($_FILES['image']['tmp_name'])) {
                $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                $mime    = mime_content_type($_FILES['image']['tmp_name']);
                if (!in_array($mime, $allowed)) {
                    $error_msg = 'Invalid image type. Use JPG, PNG or WEBP.';
                } elseif ($_FILES['image']['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
                    $error_msg = 'Image too large (max ' . MAX_UPLOAD_MB . 'MB).';
                } else {
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                    $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $filename = 'img_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename)) {
                        $img_path = 'uploads/' . $filename;
                    } else {
                        $error_msg = 'Image upload failed. Check folder permissions.';
                    }
                }
            }

            if (!$error_msg) {
                $products = load_json(PRODUCTS_FILE);
                array_unshift($products, [
                    'id'      => 'p_' . uniqid(),
                    'name'    => $name,
                    'category'=> $category,
                    'emoji'   => $emoji,
                    'desc'    => $desc,
                    'origin'  => $origin,
                    'image'   => $img_path,
                    'created' => date('Y-m-d H:i:s'),
                ]);
                save_json(PRODUCTS_FILE, $products);
                $success_msg = "Product \"$name\" added successfully!";
            }
        }
    }

    /* DELETE PRODUCT */
    if ($action === 'delete_product') {
        $id       = $_POST['product_id'] ?? '';
        $products = load_json(PRODUCTS_FILE);
        $filtered = array_filter($products, fn($p) => $p['id'] !== $id);
        if (count($filtered) < count($products)) {
            save_json(PRODUCTS_FILE, array_values($filtered));
            $success_msg = 'Product deleted.';
        } else {
            $error_msg = 'Product not found.';
        }
    }

    /* DELETE QUERY */
    if ($action === 'delete_query') {
        $id      = $_POST['query_id'] ?? '';
        $queries = load_json(QUERIES_FILE);
        $filtered = array_filter($queries, fn($q) => $q['id'] !== $id);
        if (count($filtered) < count($queries)) {
            save_json(QUERIES_FILE, array_values($filtered));
            $success_msg = 'Enquiry deleted.';
        } else {
            $error_msg = 'Enquiry not found.';
        }
    }

    /* MARK QUERY READ */
    if ($action === 'mark_read') {
        $id      = $_POST['query_id'] ?? '';
        $queries = load_json(QUERIES_FILE);
        foreach ($queries as &$q) {
            if ($q['id'] === $id) { $q['status'] = 'read'; break; }
        }
        save_json(QUERIES_FILE, $queries);
    }
}

/* ── Load data for display ── */
$products = $logged_in ? load_json(PRODUCTS_FILE) : [];
$queries  = $logged_in ? load_json(QUERIES_FILE)  : [];

$CATEGORIES = ['Cereals & Grains','Spices & Herbs','Confectionery','Sweeteners','Fats & Oils','Ready-to-Eat'];
$active_tab = $_GET['tab'] ?? 'dashboard';
$new_queries = count(array_filter($queries, fn($q) => ($q['status'] ?? 'new') === 'new'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>RelifeProducts — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#F7F3EC;--warm:#FEFCF8;--olive:#3D4A2E;--olive-l:#5A6B42;
  --gold:#C8963E;--gold-l:#E8B86D;--dark:#1A1F12;--mid:#4A4A3A;
  --muted:#8A8A7A;--border:#DDD8CE;--bg:#F0EDE8;
  --red:#C0392B;--green:#2E7D32;--blue:#1565C0;
  --ff-d:'Playfair Display',serif;--ff-b:'DM Sans',sans-serif;--ff-m:'DM Mono',monospace;
}
html{-webkit-font-smoothing:antialiased}
body{font-family:var(--ff-b);background:var(--bg);color:var(--dark);line-height:1.6;min-height:100vh}
a{text-decoration:none;color:inherit}img{max-width:100%;display:block}

/* LAYOUT */
.layout{display:grid;grid-template-columns:240px 1fr;min-height:100vh}

/* SIDEBAR */
.sidebar{background:var(--dark);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-logo{padding:1.6rem 1.5rem 1.4rem;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-logo-text{font-family:var(--ff-d);font-size:1.1rem;font-weight:800;color:#fff;letter-spacing:-.02em}
.sidebar-logo-text span{color:var(--gold)}
.sidebar-logo-sub{font-size:.65rem;font-family:var(--ff-m);color:rgba(255,255,255,.28);margin-top:.15rem;letter-spacing:.08em;text-transform:uppercase}
.sidebar-nav{flex:1;padding:1rem 0}
.sidebar-sec{font-size:.6rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.22);padding:.6rem 1.4rem .2rem;font-family:var(--ff-m)}
.sidebar-link{display:flex;align-items:center;gap:.75rem;padding:.65rem 1.4rem;color:rgba(255,255,255,.48);font-size:.84rem;font-weight:400;transition:all .15s;cursor:pointer;border-left:3px solid transparent;text-decoration:none}
.sidebar-link:hover{background:rgba(255,255,255,.05);color:rgba(255,255,255,.85)}
.sidebar-link.active{background:rgba(200,150,62,.1);color:var(--gold-l);border-left-color:var(--gold)}
.sidebar-link-ico{width:18px;text-align:center;font-size:.95rem;flex-shrink:0}
.sidebar-badge{margin-left:auto;font-family:var(--ff-m);font-size:.62rem;background:var(--gold);color:var(--dark);padding:.12rem .45rem;border-radius:3px;font-weight:700}
.sidebar-badge.muted{background:rgba(255,255,255,.1);color:rgba(255,255,255,.4)}
.sidebar-footer{padding:1rem 1.4rem;border-top:1px solid rgba(255,255,255,.07)}
.sidebar-logout{background:transparent;border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.38);padding:.42rem 1rem;border-radius:5px;font-size:.76rem;cursor:pointer;font-family:var(--ff-b);transition:all .2s;width:100%}
.sidebar-logout:hover{border-color:rgba(192,57,43,.5);color:#E07060}

/* MAIN */
.main{padding:2rem 2.5rem;min-width:0}
.page-hd{margin-bottom:2rem;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem}
.page-title{font-family:var(--ff-d);font-size:1.7rem;font-weight:800;color:var(--dark);letter-spacing:-.02em}
.page-sub{font-size:.83rem;color:var(--muted);margin-top:.2rem}

/* ALERTS */
.alert{padding:.8rem 1.1rem;border-radius:6px;font-size:.84rem;margin-bottom:1.5rem;border-left:4px solid;display:flex;align-items:center;gap:.5rem}
.alert-ok{background:#F0FFF4;border-color:var(--green);color:var(--green)}
.alert-err{background:#FFF5F5;border-color:var(--red);color:var(--red)}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
.kpi{background:var(--warm);border:1px solid var(--border);border-radius:10px;padding:1.3rem 1.5rem}
.kpi-n{font-family:var(--ff-d);font-size:2rem;font-weight:800;color:var(--dark);letter-spacing:-.04em;line-height:1}
.kpi-l{font-size:.72rem;font-family:var(--ff-m);text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-top:.35rem}
.kpi:nth-child(1) .kpi-n{color:var(--olive)}
.kpi:nth-child(2) .kpi-n{color:var(--gold)}
.kpi:nth-child(3) .kpi-n{color:#C4633A}
.kpi:nth-child(4) .kpi-n{color:var(--blue)}

/* PANEL */
.panel{background:var(--warm);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:1.5rem}
.panel-hd{padding:.95rem 1.4rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.panel-title{font-family:var(--ff-m);font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.09em;color:var(--mid)}
.panel-body{padding:1.4rem}

/* GRIDS */
.two-col{display:grid;grid-template-columns:380px 1fr;gap:1.5rem;align-items:start}

/* FORM */
.fg-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.form-full{grid-column:1/-1}
.fg{display:flex;flex-direction:column;gap:.38rem}
.fl{font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--mid);font-family:var(--ff-m)}
.fi,.fs,.ft{background:var(--cream);border:1px solid var(--border);border-radius:6px;padding:.62rem .88rem;font-family:var(--ff-b);font-size:.86rem;color:var(--dark);outline:none;transition:border-color .2s,box-shadow .2s;width:100%;-webkit-appearance:none}
.fi:focus,.fs:focus,.ft:focus{border-color:var(--olive);box-shadow:0 0 0 3px rgba(61,74,46,.08)}
.ft{resize:vertical;min-height:85px}
.fs{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='none' viewBox='0 0 12 12'%3E%3Cpath d='M2 4l4 4 4-4' stroke='%238A8A7A' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .85rem center;padding-right:2.2rem;cursor:pointer}

/* IMAGE UPLOAD */
.upload-area{border:2px dashed var(--border);border-radius:8px;padding:1.6rem;text-align:center;cursor:pointer;transition:all .2s;background:var(--cream);position:relative;overflow:hidden}
.upload-area:hover{border-color:var(--olive);background:rgba(61,74,46,.03)}
.upload-area input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-ico{font-size:1.5rem;margin-bottom:.4rem}
.upload-txt{font-size:.8rem;color:var(--muted)}.upload-txt strong{color:var(--olive)}
.upload-preview{width:100%;height:120px;object-fit:cover;border-radius:6px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem 1.3rem;border-radius:6px;font-family:var(--ff-b);font-size:.83rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;letter-spacing:.01em}
.btn-olive{background:var(--olive);color:var(--cream)}.btn-olive:hover{background:var(--dark)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--mid)}.btn-outline:hover{border-color:var(--mid)}
.btn-danger{background:#FFF0EE;color:var(--red);border:1px solid #F5C5C0}.btn-danger:hover{background:var(--red);color:#fff;border-color:var(--red)}
.btn-sm{padding:.32rem .7rem;font-size:.72rem}
.btn-full{width:100%;justify-content:center}

/* TABLE */
.tbl{width:100%;border-collapse:collapse}
.tbl th{font-family:var(--ff-m);font-size:.65rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:.7rem .95rem;border-bottom:2px solid var(--border);text-align:left;white-space:nowrap}
.tbl td{padding:.75rem .95rem;border-bottom:1px solid var(--border);font-size:.84rem;color:var(--mid);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:rgba(61,74,46,.02)}
.prod-cell{display:flex;align-items:center;gap:.75rem}
.prod-thumb-sm{width:40px;height:40px;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;background:var(--cream)}
.prod-thumb-sm img{width:100%;height:100%;object-fit:cover}
.prod-name-txt{font-weight:600;color:var(--dark);font-size:.85rem}
.prod-origin-txt{font-size:.72rem;color:var(--muted)}
.cat-badge{display:inline-block;font-size:.65rem;font-family:var(--ff-m);letter-spacing:.04em;padding:.2rem .55rem;border-radius:4px;background:rgba(61,74,46,.08);color:var(--olive);white-space:nowrap}
.status-new{background:rgba(200,150,62,.12);color:#885500;font-family:var(--ff-m);font-size:.65rem;padding:.18rem .5rem;border-radius:4px;font-weight:600}
.status-read{background:rgba(61,74,46,.06);color:var(--muted);font-family:var(--ff-m);font-size:.65rem;padding:.18rem .5rem;border-radius:4px}
.date-txt{font-family:var(--ff-m);font-size:.72rem;color:var(--muted)}

/* QUERY CARD */
.qcard{border:1px solid var(--border);border-radius:8px;padding:1.1rem 1.2rem;background:var(--cream);margin-bottom:.8rem;transition:box-shadow .2s}
.qcard:hover{box-shadow:0 3px 14px rgba(26,31,18,.07)}
.qcard.status-new-card{border-left:3px solid var(--gold)}
.qcard-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.4rem;gap:.5rem}
.qcard-company{font-weight:700;color:var(--dark);font-size:.9rem}
.qcard-meta{display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.qcard-contact{font-size:.8rem;color:var(--mid);margin-bottom:.35rem}
.qcard-msg{font-size:.8rem;color:var(--muted);background:var(--warm);padding:.55rem .75rem;border-radius:5px;line-height:1.6;margin-top:.45rem;border:1px solid var(--border)}
.qcard-actions{display:flex;gap:.4rem;margin-top:.8rem;flex-wrap:wrap}

/* BAR CHART */
.bar-wrap{display:flex;flex-direction:column;gap:.75rem;padding:.5rem 0}
.bar-row{display:flex;align-items:center;gap:.75rem}
.bar-lbl{font-size:.76rem;color:var(--mid);width:130px;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar-track{flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden}
.bar-fill{height:100%;background:var(--olive);border-radius:3px;transition:width .6s ease}
.bar-num{font-family:var(--ff-m);font-size:.7rem;color:var(--muted);width:20px;text-align:right;flex-shrink:0}

/* RECENT */
.recent-item{display:flex;align-items:center;gap:.75rem;padding:.55rem .75rem;background:var(--cream);border-radius:6px;margin-bottom:.4rem}
.recent-dot{width:7px;height:7px;border-radius:50%;background:var(--gold);flex-shrink:0}
.recent-co{font-weight:600;font-size:.83rem;color:var(--dark);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.recent-dt{font-family:var(--ff-m);font-size:.7rem;color:var(--muted);flex-shrink:0}

/* SEARCH */
.search-row{display:flex;gap:.75rem;margin-bottom:1.1rem;flex-wrap:wrap}
.search-inp{background:var(--cream);border:1px solid var(--border);border-radius:6px;padding:.52rem .88rem;font-family:var(--ff-b);font-size:.84rem;outline:none;transition:border-color .2s;flex:1;min-width:200px}
.search-inp:focus{border-color:var(--olive)}
.filter-sel{background:var(--cream);border:1px solid var(--border);border-radius:6px;padding:.52rem .88rem;font-family:var(--ff-b);font-size:.84rem;outline:none;cursor:pointer;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='none' viewBox='0 0 12 12'%3E%3Cpath d='M2 4l4 4 4-4' stroke='%238A8A7A' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;padding-right:1.9rem}

/* TABS */
.tabs{display:flex;gap:.3rem;background:var(--cream);border-radius:7px;padding:3px;border:1px solid var(--border);width:fit-content;margin-bottom:1.4rem}
.tab-btn{font-family:var(--ff-m);font-size:.72rem;font-weight:500;letter-spacing:.04em;padding:.42rem 1.1rem;border-radius:5px;border:none;background:transparent;color:var(--muted);cursor:pointer;transition:all .15s;white-space:nowrap}
.tab-btn.active{background:var(--warm);color:var(--dark);box-shadow:0 1px 4px rgba(26,31,18,.1)}

/* EMPTY */
.empty-state{padding:2.5rem;text-align:center;color:var(--muted)}
.empty-ico{font-size:2rem;margin-bottom:.4rem;opacity:.4}

/* LOGIN */
.login-page{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--dark);padding:2rem}
.login-card{background:var(--warm);border-radius:12px;padding:2.8rem;width:100%;max-width:380px;box-shadow:0 32px 80px rgba(0,0,0,.4)}
.login-logo{font-family:var(--ff-d);font-size:1.4rem;font-weight:800;color:var(--dark);margin-bottom:.25rem}
.login-logo span{color:var(--olive)}
.login-sub{font-size:.83rem;color:var(--muted);margin-bottom:1.8rem}
.login-err{background:#FFF0EE;border:1px solid #F5C5C0;color:var(--red);padding:.62rem .88rem;border-radius:6px;font-size:.82rem;margin-bottom:1rem}
.login-form{display:flex;flex-direction:column;gap:.9rem}
.login-hint{font-size:.7rem;color:var(--muted);font-family:var(--ff-m);margin-top:1rem;text-align:center}

/* TIPS */
.tips{padding:.5rem 0}
.tip{display:flex;align-items:flex-start;gap:.6rem;padding:.45rem 0;font-size:.8rem;color:var(--muted)}
.tip-dot{width:5px;height:5px;border-radius:50%;background:var(--gold);flex-shrink:0;margin-top:.45rem}

/* RESPONSIVE */
@media(max-width:1024px){.two-col{grid-template-columns:1fr}.kpi-grid{grid-template-columns:1fr 1fr}.layout{grid-template-columns:1fr}.sidebar{display:none}.main{padding:1.5rem 1rem}}
@media(max-width:640px){.kpi-grid{grid-template-columns:1fr 1fr}.fg-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- LOGIN -->
<div class="login-page">
  <div class="login-card">
    <div style="font-size:2rem;margin-bottom:1rem">🔐</div>
    <div class="login-logo">Relife<span>Products</span></div>
    <div class="login-sub">Admin dashboard — authorised access only.</div>
    <?php if ($login_error): ?>
      <div class="login-err">✗ <?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    <form class="login-form" method="POST">
      <input type="hidden" name="action" value="login"/>
      <div class="fg">
        <label class="fl">Admin Key</label>
        <input class="fi" type="password" name="key" placeholder="Enter admin key" required autofocus/>
      </div>
      <button type="submit" class="btn btn-olive btn-full" style="margin-top:.3rem">Sign In →</button>
    </form>
    <div class="login-hint">Default key: relife_admin_2025</div>
  </div>
</div>

<?php else: ?>
<!-- DASHBOARD -->
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="sidebar-logo-text">Relife<span>Products</span></div>
      <div class="sidebar-logo-sub">Admin Panel</div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-sec">Overview</div>
      <a class="sidebar-link <?= $active_tab==='dashboard'?'active':'' ?>" href="?tab=dashboard">
        <span class="sidebar-link-ico">📊</span> Dashboard
      </a>
      <div class="sidebar-sec" style="margin-top:.4rem">Catalogue</div>
      <a class="sidebar-link <?= $active_tab==='products'?'active':'' ?>" href="?tab=products">
        <span class="sidebar-link-ico">📦</span> Products
        <span class="sidebar-badge muted"><?= count($products) ?></span>
      </a>
      <a class="sidebar-link <?= $active_tab==='add'?'active':'' ?>" href="?tab=add">
        <span class="sidebar-link-ico">➕</span> Add Product
      </a>
      <div class="sidebar-sec" style="margin-top:.4rem">Enquiries</div>
      <a class="sidebar-link <?= $active_tab==='queries'?'active':'' ?>" href="?tab=queries">
        <span class="sidebar-link-ico">📬</span> All Enquiries
        <?php if ($new_queries > 0): ?>
          <span class="sidebar-badge"><?= $new_queries ?> new</span>
        <?php else: ?>
          <span class="sidebar-badge muted"><?= count($queries) ?></span>
        <?php endif; ?>
      </a>
      <div class="sidebar-sec" style="margin-top:.4rem">Site</div>
      <a class="sidebar-link" href="../index.html" target="_blank">
        <span class="sidebar-link-ico">🌐</span> View Website
      </a>
      <a class="sidebar-link" href="../api.php/products" target="_blank">
        <span class="sidebar-link-ico">🔗</span> Products API
      </a>
    </nav>
    <div class="sidebar-footer">
      <form method="POST">
        <input type="hidden" name="action" value="logout"/>
        <button type="submit" class="sidebar-logout">Sign Out</button>
      </form>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">

    <?php if ($success_msg): ?>
      <div class="alert alert-ok">✓ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
      <div class="alert alert-err">✗ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php

    /* ═══════════ DASHBOARD ═══════════ */
    if ($active_tab === 'dashboard'):
      $cat_counts = [];
      foreach ($products as $p) {
        $c = $p['category'] ?? 'Unknown';
        $cat_counts[$c] = ($cat_counts[$c] ?? 0) + 1;
      }
      $max_count = max(array_values($cat_counts) ?: [1]);
    ?>

    <div class="page-hd">
      <div>
        <div class="page-title">Dashboard</div>
        <div class="page-sub">Welcome back — here is an overview of your RelifeProducts data.</div>
      </div>
      <a href="?tab=add" class="btn btn-olive">+ Add Product</a>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="kpi-n"><?= count($products) ?></div><div class="kpi-l">Total Products</div></div>
      <div class="kpi"><div class="kpi-n"><?= count($queries) ?></div><div class="kpi-l">Enquiries</div></div>
      <div class="kpi"><div class="kpi-n"><?= $new_queries ?></div><div class="kpi-l">New (Unread)</div></div>
      <div class="kpi"><div class="kpi-n"><?= count(array_filter($products, fn($p) => !empty($p['image']))) ?></div><div class="kpi-l">With Images</div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

      <div class="panel">
        <div class="panel-hd"><div class="panel-title">Products by Category</div></div>
        <div class="panel-body">
          <div class="bar-wrap">
            <?php foreach ($CATEGORIES as $cat):
              $n = $cat_counts[$cat] ?? 0;
              $pct = $max_count > 0 ? round(($n / $max_count) * 100) : 0;
            ?>
            <div class="bar-row">
              <div class="bar-lbl"><?= htmlspecialchars($cat) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= max(3,$pct) ?>%"></div></div>
              <div class="bar-num"><?= $n ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-hd">
          <div class="panel-title">Recent Enquiries</div>
          <a href="?tab=queries" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="panel-body">
          <?php if (empty($queries)): ?>
            <div class="empty-state"><div class="empty-ico">📬</div><p>No enquiries yet.</p></div>
          <?php else: ?>
            <?php foreach (array_slice($queries, 0, 6) as $q): ?>
            <div class="recent-item">
              <div class="recent-dot" style="<?= ($q['status']??'new')==='new'?'':'background:var(--border)' ?>"></div>
              <div class="recent-co"><?= htmlspecialchars($q['company'] ?: ($q['name'] ?? '—')) ?></div>
              <?php if (!empty($q['product'])): ?><span class="cat-badge"><?= htmlspecialchars($q['product']) ?></span><?php endif; ?>
              <div class="recent-dt"><?= htmlspecialchars($q['date'] ?? '') ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-hd"><div class="panel-title">Quick Actions</div></div>
      <div class="panel-body" style="display:flex;gap:.75rem;flex-wrap:wrap">
        <a href="?tab=add"     class="btn btn-olive">+ Add Product</a>
        <a href="?tab=queries" class="btn btn-outline">View Enquiries</a>
        <a href="../index.html" target="_blank" class="btn btn-outline">🌐 View Site</a>
        <a href="../api.php/products" target="_blank" class="btn btn-outline" style="font-family:var(--ff-m);font-size:.75rem">📋 Products JSON</a>
        <a href="../api.php/queries?key=<?= ADMIN_KEY ?>" target="_blank" class="btn btn-outline" style="font-family:var(--ff-m);font-size:.75rem">📋 Queries JSON</a>
      </div>
    </div>

    <?php

    /* ═══════════ PRODUCTS ═══════════ */
    elseif ($active_tab === 'products'): ?>

    <div class="page-hd">
      <div>
        <div class="page-title">Products</div>
        <div class="page-sub"><?= count($products) ?> products in catalogue</div>
      </div>
      <a href="?tab=add" class="btn btn-olive">+ Add Product</a>
    </div>

    <div class="search-row">
      <input class="search-inp" type="text" id="prod-srch" placeholder="Search by name, category, origin…" oninput="filterTbl()"/>
      <select class="filter-sel" id="cat-filter" onchange="filterTbl()">
        <option value="">All Categories</option>
        <?php foreach ($CATEGORIES as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="panel">
      <div class="panel-hd"><div class="panel-title">Product Catalogue (<?= count($products) ?>)</div></div>
      <?php if (empty($products)): ?>
        <div class="empty-state"><div class="empty-ico">📦</div><p>No products yet. <a href="?tab=add" style="color:var(--olive)">Add your first product →</a></p></div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="tbl" id="prod-tbl">
          <thead>
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th>Origin</th>
              <th>Image</th>
              <th>Added</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
            <tr data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                data-cat="<?= htmlspecialchars($p['category']) ?>"
                data-origin="<?= htmlspecialchars(strtolower($p['origin']??'')) ?>">
              <td>
                <div class="prod-cell">
                  <div class="prod-thumb-sm" style="background:var(--cream)">
                    <?php if (!empty($p['image'])): ?>
                      <img src="../<?= htmlspecialchars($p['image']) ?>" alt=""/>
                    <?php else: ?>
                      <?= htmlspecialchars($p['emoji'] ?? '🌿') ?>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="prod-name-txt"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-origin-txt"><?= htmlspecialchars($p['origin'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td><span class="cat-badge"><?= htmlspecialchars($p['category']) ?></span></td>
              <td style="font-size:.8rem"><?= htmlspecialchars($p['origin'] ?? '—') ?></td>
              <td><?= !empty($p['image']) ? '<span style="color:var(--green);font-size:.78rem;font-weight:600">✓ Yes</span>' : '<span style="color:var(--muted);font-size:.78rem">—</span>' ?></td>
              <td class="date-txt"><?= date('d M Y', strtotime($p['created'] ?? 'now')) ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this product?')">
                  <input type="hidden" name="action" value="delete_product"/>
                  <input type="hidden" name="product_id" value="<?= htmlspecialchars($p['id']) ?>"/>
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php

    /* ═══════════ ADD PRODUCT ═══════════ */
    elseif ($active_tab === 'add'): ?>

    <div class="page-hd">
      <div>
        <div class="page-title">Add Product</div>
        <div class="page-sub">Add a new product to the RelifeProducts catalogue.</div>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_product"/>
      <div class="two-col">

        <!-- Left -->
        <div style="display:flex;flex-direction:column;gap:1.2rem">
          <div class="panel">
            <div class="panel-hd"><div class="panel-title">Product Image</div></div>
            <div class="panel-body">
              <div class="upload-area" id="upload-area">
                <input type="file" name="image" accept="image/*" onchange="previewImg(this)"/>
                <div id="upload-ph">
                  <div class="upload-ico">📷</div>
                  <div class="upload-txt"><strong>Click to upload</strong> or drag &amp; drop<br/><span style="font-size:.72rem">JPG, PNG, WEBP — max 5MB</span></div>
                </div>
                <img id="upload-prev" class="upload-preview" style="display:none" alt="Preview"/>
              </div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-hd"><div class="panel-title">Tips</div></div>
            <div class="panel-body">
              <div class="tips">
                <?php foreach (['Use a square image for best display (1:1 ratio)','Description should be 1-2 concise sentences','Origin helps buyers identify source region','Emoji shows as fallback when no image is set'] as $tip): ?>
                <div class="tip"><div class="tip-dot"></div><?= $tip ?></div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Right -->
        <div class="panel">
          <div class="panel-hd"><div class="panel-title">Product Details</div></div>
          <div class="panel-body">
            <div class="fg-grid">
              <div class="fg form-full">
                <label class="fl">Product Name *</label>
                <input class="fi" type="text" name="name" required placeholder="e.g. White Sesame Seeds" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"/>
              </div>
              <div class="fg">
                <label class="fl">Category *</label>
                <select class="fs" name="category" required>
                  <option value="">Select category…</option>
                  <?php foreach ($CATEGORIES as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"<?= (($_POST['category']??'')===$cat)?' selected':'' ?>><?= htmlspecialchars($cat) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="fg">
                <label class="fl">Emoji Icon</label>
                <input class="fi" type="text" name="emoji" maxlength="4" placeholder="🌿" value="<?= htmlspecialchars($_POST['emoji'] ?? '🌿') ?>"/>
              </div>
              <div class="fg form-full">
                <label class="fl">Origin / Source</label>
                <input class="fi" type="text" name="origin" placeholder="e.g. Gujarat, India" value="<?= htmlspecialchars($_POST['origin'] ?? '') ?>"/>
              </div>
              <div class="fg form-full">
                <label class="fl">Description *</label>
                <textarea class="ft" name="desc" required placeholder="Short description shown in the product catalogue (1-2 sentences)…"><?= htmlspecialchars($_POST['desc'] ?? '') ?></textarea>
              </div>
            </div>
            <div style="margin-top:1.1rem;display:flex;gap:.7rem">
              <button type="submit" class="btn btn-olive">Add to Catalogue →</button>
              <a href="?tab=products" class="btn btn-outline">Cancel</a>
            </div>
          </div>
        </div>

      </div>
    </form>

    <?php

    /* ═══════════ QUERIES ═══════════ */
    elseif ($active_tab === 'queries'): ?>

    <div class="page-hd">
      <div>
        <div class="page-title">Enquiries</div>
        <div class="page-sub"><?= count($queries) ?> total &nbsp;·&nbsp; <?= $new_queries ?> unread</div>
      </div>
    </div>

    <div class="search-row">
      <input class="search-inp" type="text" id="q-srch" placeholder="Search by company, email, product…" oninput="filterQueries()"/>
      <select class="filter-sel" id="q-cat" onchange="filterQueries()">
        <option value="">All Categories</option>
        <?php foreach ($CATEGORIES as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-sel" id="q-status" onchange="filterQueries()">
        <option value="">All Status</option>
        <option value="new">New</option>
        <option value="read">Read</option>
      </select>
    </div>

    <?php if (empty($queries)): ?>
      <div class="panel"><div class="empty-state"><div class="empty-ico">📬</div><p>No enquiries yet. They will appear here once submitted via the website.</p></div></div>
    <?php else: ?>
    <div id="q-list">
      <?php foreach ($queries as $q):
        $is_new = ($q['status'] ?? 'new') === 'new';
      ?>
      <div class="qcard <?= $is_new ? 'status-new-card' : '' ?>"
           data-company="<?= htmlspecialchars(strtolower($q['company']??'')) ?>"
           data-email="<?= htmlspecialchars(strtolower($q['email']??'')) ?>"
           data-cat="<?= htmlspecialchars($q['product']??'') ?>"
           data-status="<?= htmlspecialchars($q['status']??'new') ?>">
        <div class="qcard-top">
          <div>
            <div class="qcard-company"><?= htmlspecialchars($q['company'] ?: ($q['name'] ?? '—')) ?></div>
            <div style="display:flex;gap:.4rem;margin-top:.3rem;flex-wrap:wrap">
              <?php if (!empty($q['product'])): ?><span class="cat-badge"><?= htmlspecialchars($q['product']) ?></span><?php endif; ?>
              <span class="<?= $is_new ? 'status-new' : 'status-read' ?>"><?= $is_new ? 'New' : 'Read' ?></span>
            </div>
          </div>
          <div class="qcard-meta">
            <span class="date-txt"><?= htmlspecialchars($q['date'] ?? '') ?></span>
            <form method="POST" onsubmit="return confirm('Delete this enquiry?')" style="display:inline">
              <input type="hidden" name="action" value="delete_query"/>
              <input type="hidden" name="query_id" value="<?= htmlspecialchars($q['id']) ?>"/>
              <button type="submit" class="btn btn-danger btn-sm">✕</button>
            </form>
          </div>
        </div>
        <div class="qcard-contact">
          📧 <?= htmlspecialchars($q['email'] ?? '') ?>
          <?php if (!empty($q['phone'])): ?> &nbsp;·&nbsp; 📞 <?= htmlspecialchars($q['phone']) ?><?php endif; ?>
          <?php if (!empty($q['country'])): ?> &nbsp;·&nbsp; 📍 <?= htmlspecialchars($q['country']) ?><?php endif; ?>
        </div>
        <?php if (!empty($q['name']) && $q['name'] !== $q['company']): ?>
          <div style="font-size:.78rem;color:var(--muted)">Contact: <?= htmlspecialchars($q['name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($q['quantity'])): ?>
          <div style="font-size:.78rem;color:var(--mid);margin-top:.2rem">📦 Qty: <?= htmlspecialchars($q['quantity']) ?></div>
        <?php endif; ?>
        <?php if (!empty($q['message'])): ?>
          <div class="qcard-msg"><?= nl2br(htmlspecialchars($q['message'])) ?></div>
        <?php endif; ?>
        <div class="qcard-actions">
          <a href="mailto:<?= htmlspecialchars($q['email']??'') ?>?subject=Re%3A+Your+RelifeProducts+Enquiry&body=Dear+<?= urlencode($q['name']??$q['company']??'') ?>%2C%0A%0AThank+you+for+your+enquiry." class="btn btn-olive btn-sm">✉ Reply by Email</a>
          <?php if (!empty($q['phone'])): ?>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$q['phone']) ?>?text=Hi+<?= urlencode($q['name']??$q['company']??'') ?>%2C+thank+you+for+your+enquiry+to+RelifeProducts." target="_blank" class="btn btn-sm" style="background:#25D366;color:#fff;border:none">💬 WhatsApp</a>
          <?php endif; ?>
          <?php if ($is_new): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="mark_read"/>
              <input type="hidden" name="query_id" value="<?= htmlspecialchars($q['id']) ?>"/>
              <button type="submit" class="btn btn-outline btn-sm">Mark Read</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </main>
</div>
<?php endif; ?>

<script>
/* Image preview */
function previewImg(input) {
  if (!input.files?.[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('upload-prev').src = e.target.result;
    document.getElementById('upload-prev').style.display = 'block';
    document.getElementById('upload-ph').style.display = 'none';
  };
  reader.readAsDataURL(input.files[0]);
}

/* Product table filter */
function filterTbl() {
  const s = (document.getElementById('prod-srch')?.value || '').toLowerCase();
  const c = document.getElementById('cat-filter')?.value || '';
  document.querySelectorAll('#prod-tbl tbody tr').forEach(row => {
    const nameOk  = !s || (row.dataset.name   || '').includes(s) || (row.dataset.origin || '').includes(s);
    const catOk   = !c || row.dataset.cat === c;
    row.style.display = (nameOk && catOk) ? '' : 'none';
  });
}

/* Query filter */
function filterQueries() {
  const s  = (document.getElementById('q-srch')?.value || '').toLowerCase();
  const c  = document.getElementById('q-cat')?.value   || '';
  const st = document.getElementById('q-status')?.value || '';
  document.querySelectorAll('#q-list .qcard').forEach(card => {
    const textOk   = !s  || (card.dataset.company || '').includes(s) || (card.dataset.email || '').includes(s);
    const catOk    = !c  || card.dataset.cat === c;
    const statusOk = !st || card.dataset.status === st;
    card.style.display = (textOk && catOk && statusOk) ? '' : 'none';
  });
}

/* Auto-hide alerts */
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity .5s'; a.style.opacity = '0';
    setTimeout(() => a.remove(), 600);
  });
}, 4000);
</script>
</body>
</html>