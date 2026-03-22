<?php
/* ═══════════════════════════════════════════════════════
   Fish Sampling Management System — Reports Page
   Full analytics with same design as dashboard
═══════════════════════════════════════════════════════ */
session_start();
include "search/connect.php";

/* ── Auth Guard ──────────────────────────────────────── */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName    = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$adminInitial = strtoupper(substr($adminName, 0, 1));

/* ══════════════════════════════════════════════════════
   SUMMARY STATS
══════════════════════════════════════════════════════ */
$totalSpecies   = (int)$conn->query("SELECT COUNT(*) c FROM species")  ->fetch_assoc()['c'];
$totalLocations = (int)$conn->query("SELECT COUNT(*) c FROM locations")->fetch_assoc()['c'];
$totalVisits    = (int)$conn->query("SELECT COUNT(*) c FROM visits")   ->fetch_assoc()['c'];
$totalSpecimens = (int)$conn->query("SELECT COUNT(*) c FROM specimens")->fetch_assoc()['c'];

$avgWeight = $conn->query("SELECT ROUND(AVG(weight),2) v FROM specimens WHERE weight IS NOT NULL")->fetch_assoc()['v'] ?? 0;
$maxWeight = $conn->query("SELECT MAX(weight) v FROM specimens WHERE weight IS NOT NULL")         ->fetch_assoc()['v'] ?? 0;
$minWeight = $conn->query("SELECT MIN(weight) v FROM specimens WHERE weight IS NOT NULL")         ->fetch_assoc()['v'] ?? 0;

/* ══════════════════════════════════════════════════════
   CHART 1 — Species found per month
══════════════════════════════════════════════════════ */
$ch1Labels = []; $ch1Data = [];
$res = $conn->query("
    SELECT MONTH(v.visit_date) mn, MONTHNAME(v.visit_date) mname,
           COUNT(DISTINCT s.species_id) cnt
    FROM specimens s JOIN visits v ON v.id = s.visit_id
    GROUP BY mn, mname ORDER BY mn");
while ($r = $res->fetch_assoc()) {
    $ch1Labels[] = substr($r['mname'], 0, 3);
    $ch1Data[]   = (int)$r['cnt'];
}

/* ══════════════════════════════════════════════════════
   CHART 2 — Total specimens per month
══════════════════════════════════════════════════════ */
$ch2Labels = []; $ch2Data = [];
$res = $conn->query("
    SELECT MONTH(v.visit_date) mn, MONTHNAME(v.visit_date) mname,
           COUNT(s.id) cnt
    FROM specimens s JOIN visits v ON v.id = s.visit_id
    GROUP BY mn, mname ORDER BY mn");
while ($r = $res->fetch_assoc()) {
    $ch2Labels[] = substr($r['mname'], 0, 3);
    $ch2Data[]   = (int)$r['cnt'];
}

/* ══════════════════════════════════════════════════════
   CHART 3 — Visits per month
══════════════════════════════════════════════════════ */
$ch3Labels = []; $ch3Data = [];
$res = $conn->query("
    SELECT MONTH(visit_date) mn, MONTHNAME(visit_date) mname, COUNT(*) cnt
    FROM visits GROUP BY mn, mname ORDER BY mn");
while ($r = $res->fetch_assoc()) {
    $ch3Labels[] = substr($r['mname'], 0, 3);
    $ch3Data[]   = (int)$r['cnt'];
}

/* ══════════════════════════════════════════════════════
   CHART 4 — Top species by specimen count (doughnut)
══════════════════════════════════════════════════════ */
$ch4Labels = []; $ch4Data = [];
$res = $conn->query("
    SELECT sp.local_name, COUNT(s.id) cnt
    FROM specimens s JOIN species sp ON sp.id = s.species_id
    GROUP BY s.species_id, sp.local_name ORDER BY cnt DESC LIMIT 6");
while ($r = $res->fetch_assoc()) {
    $ch4Labels[] = $r['local_name'];
    $ch4Data[]   = (int)$r['cnt'];
}

/* ══════════════════════════════════════════════════════
   CHART 5 — Specimens per location (horizontal bar)
══════════════════════════════════════════════════════ */
$ch5Labels = []; $ch5Data = [];
$res = $conn->query("
    SELECT l.location_name, COUNT(s.id) cnt
    FROM specimens s
    JOIN visits v ON v.id = s.visit_id
    JOIN locations l ON l.id = v.location_id
    GROUP BY v.location_id, l.location_name ORDER BY cnt DESC");
while ($r = $res->fetch_assoc()) {
    $ch5Labels[] = $r['location_name'];
    $ch5Data[]   = (int)$r['cnt'];
}

/* ══════════════════════════════════════════════════════
   CHART 6 — Average weight per species (bar)
══════════════════════════════════════════════════════ */
$ch6Labels = []; $ch6Data = [];
$res = $conn->query("
    SELECT sp.local_name, ROUND(AVG(s.weight),2) avg_w
    FROM specimens s JOIN species sp ON sp.id = s.species_id
    WHERE s.weight IS NOT NULL
    GROUP BY s.species_id, sp.local_name ORDER BY avg_w DESC LIMIT 8");
while ($r = $res->fetch_assoc()) {
    $ch6Labels[] = $r['local_name'];
    $ch6Data[]   = (float)$r['avg_w'];
}

/* ══════════════════════════════════════════════════════
   SPECIES SUMMARY TABLE
══════════════════════════════════════════════════════ */
$speciesTable = [];
$res = $conn->query("
    SELECT
        sp.local_name,
        sp.scientific_name,
        COUNT(s.id)              AS total_specimens,
        ROUND(AVG(s.weight),2)  AS avg_weight,
        MAX(s.weight)            AS max_weight,
        MIN(s.weight)            AS min_weight,
        COUNT(DISTINCT v.location_id) AS locations_found
    FROM species sp
    LEFT JOIN specimens s  ON s.species_id = sp.id
    LEFT JOIN visits    v  ON v.id = s.visit_id
    GROUP BY sp.id, sp.local_name, sp.scientific_name
    ORDER BY total_specimens DESC");
while ($r = $res->fetch_assoc()) $speciesTable[] = $r;

/* ══════════════════════════════════════════════════════
   LOCATION SUMMARY TABLE
══════════════════════════════════════════════════════ */
$locationTable = [];
$res = $conn->query("
    SELECT
        l.location_name,
        COUNT(DISTINCT v.id)        AS total_visits,
        COUNT(s.id)                 AS total_specimens,
        COUNT(DISTINCT s.species_id) AS species_count,
        ROUND(AVG(s.weight),2)      AS avg_weight,
        MAX(v.visit_date)           AS last_visit
    FROM locations l
    LEFT JOIN visits   v ON v.location_id = l.id
    LEFT JOIN specimens s ON s.visit_id = v.id
    GROUP BY l.id, l.location_name
    ORDER BY total_specimens DESC");
while ($r = $res->fetch_assoc()) $locationTable[] = $r;

/* ══════════════════════════════════════════════════════
   FULL SAMPLING LOG (for print/export)
══════════════════════════════════════════════════════ */
$fullLog = [];
$res = $conn->query("
    SELECT
        v.visit_date,
        MONTHNAME(v.visit_date) AS month_name,
        sp.local_name           AS fish,
        sp.scientific_name,
        l.location_name         AS location,
        s.weight
    FROM specimens s
    JOIN visits    v  ON v.id  = s.visit_id
    JOIN species   sp ON sp.id = s.species_id
    JOIN locations l  ON l.id  = v.location_id
    ORDER BY v.visit_date DESC");
while ($r = $res->fetch_assoc()) $fullLog[] = $r;

/* ── JSON for JS ─────────────────────────────────── */
$js = [
    'ch1' => ['labels' => $ch1Labels, 'data' => $ch1Data],
    'ch2' => ['labels' => $ch2Labels, 'data' => $ch2Data],
    'ch3' => ['labels' => $ch3Labels, 'data' => $ch3Data],
    'ch4' => ['labels' => $ch4Labels, 'data' => $ch4Data],
    'ch5' => ['labels' => $ch5Labels, 'data' => $ch5Data],
    'ch6' => ['labels' => $ch6Labels, 'data' => $ch6Data],
    'log' => $fullLog,
];
$jsData = json_encode($js);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Fish Sampling — Reports</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<style>
/* ══════════════════════════════════════════
   DESIGN TOKENS  (identical to dashboard)
══════════════════════════════════════════ */
:root{
  --sidebar-w:255px; --topbar-h:64px;
  --primary:#1a6bff; --primary-lt:#e8f0ff; --primary-dk:#1254cc;
  --success:#0fba81; --warning:#f59f00; --danger:#fa5252; --info:#4dabf7;
  --bg:#f0f4fb; --surface:#fff;
  --sidebar-bg:#0d1b2e;
  --sidebar-hover:rgba(255,255,255,.06);
  --sidebar-active:rgba(26,107,255,.20);
  --border:#e3e8f0; --text:#1a2332;
  --muted:#7a8999; --muted-lt:#b0bcc8;
  --r-sm:10px; --r:14px; --r-lg:20px;
  --shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --shadow:0 4px 16px rgba(0,0,0,.07),0 1px 4px rgba(0,0,0,.04);
  --shadow-lg:0 12px 40px rgba(0,0,0,.10),0 2px 8px rgba(0,0,0,.05);
}
*,*::before,*::after{box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);margin:0;overflow-x:hidden}
a{text-decoration:none}

/* ── Sidebar ─────────────────────────────── */
.sidebar{
  position:fixed;top:0;left:0;
  width:var(--sidebar-w);height:100vh;
  background:var(--sidebar-bg);
  display:flex;flex-direction:column;
  z-index:1000;
  transition:transform .3s cubic-bezier(.4,0,.2,1);
  overflow-y:auto;overflow-x:hidden;
}
.sidebar::-webkit-scrollbar{width:4px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}

.sidebar-brand{
  display:flex;align-items:center;gap:11px;
  padding:22px 20px 20px;
  border-bottom:1px solid rgba(255,255,255,.07);flex-shrink:0;
}
.brand-logo{
  width:38px;height:38px;
  background:linear-gradient(135deg,var(--primary),#4dabf7);
  border-radius:11px;display:flex;align-items:center;justify-content:center;
  font-size:18px;box-shadow:0 4px 12px rgba(26,107,255,.4);flex-shrink:0;
}
.brand-name{font-size:14px;font-weight:800;color:#fff;line-height:1.2;letter-spacing:-.2px}
.brand-tagline{font-size:10px;color:rgba(255,255,255,.35);letter-spacing:.3px}

.sidebar-nav{padding:16px 12px;flex:1}
.nav-section{
  font-size:9.5px;font-weight:700;letter-spacing:1.8px;
  text-transform:uppercase;color:rgba(255,255,255,.25);
  padding:12px 8px 6px;margin-bottom:2px;
}
.nav-link-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:var(--r-sm);
  color:rgba(255,255,255,.55);font-size:13.5px;font-weight:500;
  margin-bottom:2px;cursor:pointer;
  transition:all .18s ease;position:relative;white-space:nowrap;
}
.nav-link-item i{font-size:16px;width:20px;flex-shrink:0;text-align:center}
.nav-link-item:hover{background:var(--sidebar-hover);color:rgba(255,255,255,.9)}
.nav-link-item.active{background:var(--sidebar-active);color:#fff;font-weight:600}
.nav-link-item.active::before{
  content:'';position:absolute;left:0;top:20%;bottom:20%;
  width:3px;border-radius:0 3px 3px 0;background:var(--primary);
}
.nav-badge{
  margin-left:auto;background:var(--primary);color:#fff;
  font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;line-height:1.6;
}
.sidebar-user{
  padding:14px 16px;border-top:1px solid rgba(255,255,255,.07);
  display:flex;align-items:center;gap:10px;flex-shrink:0;
}
.user-avatar{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,#f59f00,#fa5252);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:13px;color:#fff;flex-shrink:0;
}
.user-details .name{font-size:13px;font-weight:600;color:#fff}
.user-details .role{font-size:10.5px;color:rgba(255,255,255,.35)}

/* ── Topbar ──────────────────────────────── */
.topbar{
  position:fixed;top:0;left:var(--sidebar-w);right:0;
  height:var(--topbar-h);background:var(--surface);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 28px;
  z-index:900;box-shadow:var(--shadow-sm);gap:12px;
}
.sidebar-toggle{
  display:none;background:none;border:none;
  font-size:20px;color:var(--muted);cursor:pointer;
  padding:6px;border-radius:8px;transition:all .15s;
}
.sidebar-toggle:hover{background:var(--bg);color:var(--text)}
.topbar-breadcrumb{flex:1}
.topbar-breadcrumb .page-title{font-size:17px;font-weight:700;color:var(--text);margin:0;line-height:1}
.topbar-breadcrumb .breadcrumb-sub{font-size:11.5px;color:var(--muted);margin-top:2px}
.topbar-right{display:flex;align-items:center;gap:8px}
.topbar-btn{
  width:38px;height:38px;border-radius:var(--r-sm);
  border:1px solid var(--border);background:var(--surface);
  color:var(--muted);display:flex;align-items:center;justify-content:center;
  font-size:17px;cursor:pointer;transition:all .18s;position:relative;
}
.topbar-btn:hover{background:var(--bg);color:var(--text);border-color:#ccd4e0}
.topbar-admin{
  display:flex;align-items:center;gap:9px;
  padding:6px 12px 6px 8px;border-radius:var(--r-sm);
  border:1px solid var(--border);cursor:pointer;transition:all .18s;
}
.topbar-admin:hover{background:var(--bg)}
.topbar-avatar{
  width:28px;height:28px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),#4dabf7);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:800;color:#fff;
}
.topbar-admin-name{font-size:13px;font-weight:600;color:var(--text)}

/* ── Layout ──────────────────────────────── */
.main-content{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh}
.content-inner{padding:28px}

/* ── Page header bar ─────────────────────── */
.page-header{
  background:var(--surface);border-radius:var(--r-lg);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  padding:22px 28px;
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:14px;margin-bottom:24px;
  animation:fadeSlideUp .4s ease both;
}
.ph-left h2{
  font-size:22px;font-weight:800;color:var(--text);
  letter-spacing:-.5px;margin:0 0 4px;
}
.ph-left p{font-size:13px;color:var(--muted);margin:0}
.ph-right{display:flex;gap:10px;flex-wrap:wrap}

/* ── Stat cards ──────────────────────────── */
@keyframes fadeSlideUp{
  from{opacity:0;transform:translateY(18px)}
  to{opacity:1;transform:translateY(0)}
}
.stat-card{
  background:var(--surface);border-radius:var(--r-lg);
  padding:20px 22px;border:1px solid var(--border);box-shadow:var(--shadow-sm);
  position:relative;overflow:hidden;
  transition:transform .22s ease,box-shadow .22s ease;
  animation:fadeSlideUp .5s ease both;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.08s}
.stat-card:nth-child(3){animation-delay:.11s}
.stat-card:nth-child(4){animation-delay:.14s}
.stat-card:nth-child(5){animation-delay:.17s}
.stat-card:nth-child(6){animation-delay:.20s}
.stat-card:nth-child(7){animation-delay:.23s}

.stat-card::after{
  content:'';position:absolute;bottom:-18px;right:-18px;
  width:80px;height:80px;border-radius:50%;opacity:.07;
}
.c-blue::after{background:var(--primary)} .c-green::after{background:var(--success)}
.c-orange::after{background:var(--warning)} .c-red::after{background:var(--danger)}
.c-violet::after{background:#7c3aed} .c-teal::after{background:#0891b2}
.c-pink::after{background:#db2777}

.stat-icon{
  width:42px;height:42px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:20px;margin-bottom:14px;
}
.c-blue  .stat-icon{background:#e8f0ff;color:var(--primary)}
.c-green .stat-icon{background:#e6faf4;color:var(--success)}
.c-orange .stat-icon{background:#fff8e6;color:var(--warning)}
.c-red   .stat-icon{background:#fff0f0;color:var(--danger)}
.c-violet .stat-icon{background:#f3e8ff;color:#7c3aed}
.c-teal  .stat-icon{background:#e0f2fe;color:#0891b2}
.c-pink  .stat-icon{background:#fce7f3;color:#db2777}

.stat-value{font-size:30px;font-weight:800;line-height:1;margin-bottom:4px;letter-spacing:-1px}
.c-blue  .stat-value{color:var(--primary)} .c-green .stat-value{color:var(--success)}
.c-orange .stat-value{color:var(--warning)} .c-red  .stat-value{color:var(--danger)}
.c-violet .stat-value{color:#7c3aed} .c-teal .stat-value{color:#0891b2}
.c-pink  .stat-value{color:#db2777}

.stat-label{font-size:11.5px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.6px}

/* ── Panel / Card ────────────────────────── */
.panel{
  background:var(--surface);border-radius:var(--r-lg);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  padding:24px;
  animation:fadeSlideUp .5s ease .3s both;
}
.panel-hd{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:20px;flex-wrap:wrap;gap:10px;
}
.panel-title{
  font-size:15px;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:9px;
}
.title-icon{
  width:30px;height:30px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;font-size:14px;
}
.ti-blue  {background:var(--primary-lt);color:var(--primary)}
.ti-green {background:#e6faf4;color:var(--success)}
.ti-orange{background:#fff8e6;color:var(--warning)}
.ti-violet{background:#f3e8ff;color:#7c3aed}
.ti-teal  {background:#e0f2fe;color:#0891b2}
.ti-pink  {background:#fce7f3;color:#db2777}
.ti-red   {background:#fff0f0;color:var(--danger)}

.tag{
  font-size:11px;padding:4px 10px;border-radius:99px;
  font-weight:500;background:var(--bg);
  border:1px solid var(--border);color:var(--muted);
}

/* Charts */
.chart-wrap canvas{max-height:280px}

/* ── Data Table ──────────────────────────── */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{
  font-size:10.5px;font-weight:700;letter-spacing:1.2px;
  text-transform:uppercase;color:var(--muted);
  padding:11px 18px;background:#fafbfd;
  border-bottom:1px solid var(--border);
  white-space:nowrap;
}
.data-table tbody td{
  padding:13px 18px;font-size:13px;
  border-bottom:1px solid #f2f4f8;vertical-align:middle;
}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover td{background:#f8faff}

.fish-chip{
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:600;
  background:var(--primary-lt);color:var(--primary);
  border:1px solid rgba(26,107,255,.15);
}
.loc-chip{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;color:var(--muted)}
.wt{font-family:'Fira Code',monospace;font-size:12px;font-weight:600;color:var(--warning)}
.dt{font-family:'Fira Code',monospace;font-size:12px;color:var(--muted)}
.sci{font-style:italic;font-size:12px;color:var(--muted-lt)}

/* rank badge */
.rank{
  width:24px;height:24px;border-radius:50%;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:800;
}
.rank-1{background:#fef3c7;color:#d97706}
.rank-2{background:#f3f4f6;color:#6b7280}
.rank-3{background:#fce7f3;color:#db2777}
.rank-n{background:var(--bg);color:var(--muted)}

/* progress bar */
.prog-wrap{flex:1;height:6px;background:var(--bg);border-radius:99px;overflow:hidden;min-width:60px}
.prog-fill{height:100%;border-radius:99px;transition:width 1s ease}

/* ── Buttons ─────────────────────────────── */
.btn-primary-c{
  background:var(--primary);color:#fff;border:none;
  border-radius:var(--r-sm);padding:9px 18px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;
  transition:all .18s;box-shadow:0 2px 8px rgba(26,107,255,.25);
}
.btn-primary-c:hover{background:var(--primary-dk);transform:translateY(-1px)}

.btn-success-c{
  background:var(--success);color:#fff;border:none;
  border-radius:var(--r-sm);padding:9px 18px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;
  transition:all .18s;box-shadow:0 2px 8px rgba(15,186,129,.25);
}
.btn-success-c:hover{background:#0ca572;transform:translateY(-1px)}

.btn-outline-c{
  background:var(--surface);color:var(--muted);
  border:1px solid var(--border);border-radius:var(--r-sm);
  padding:9px 16px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:500;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .18s;
}
.btn-outline-c:hover{background:var(--bg);color:var(--text);border-color:#ccd4e0}

.btn-danger-c{
  background:var(--danger);color:#fff;border:none;
  border-radius:var(--r-sm);padding:9px 16px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .18s;
}
.btn-danger-c:hover{background:#e03131;transform:translateY(-1px)}

/* ── Tabs ────────────────────────────────── */
.tab-bar{
  display:flex;gap:4px;
  background:var(--bg);border-radius:var(--r-sm);
  padding:4px;border:1px solid var(--border);
  width:fit-content;
}
.tab-btn{
  padding:8px 18px;border-radius:8px;
  font-size:13px;font-weight:500;color:var(--muted);
  cursor:pointer;transition:all .18s;border:none;background:transparent;
  font-family:'Plus Jakarta Sans',sans-serif;
  display:flex;align-items:center;gap:7px;
}
.tab-btn.active{background:var(--surface);color:var(--text);font-weight:600;box-shadow:var(--shadow-sm)}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* ── Pagination ──────────────────────────── */
.pag-bar{
  padding:14px 18px;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.pag-info{font-size:12.5px;color:var(--muted)}
.pag-info span{font-weight:700;color:var(--text)}
.pag-btns{display:flex;gap:4px}
.pag-btn{
  width:32px;height:32px;border-radius:8px;
  border:1px solid var(--border);background:var(--surface);
  color:var(--muted);font-size:13px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all .18s;font-family:'Plus Jakarta Sans',sans-serif;
}
.pag-btn:hover:not(:disabled){background:var(--bg);color:var(--text)}
.pag-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700}
.pag-btn:disabled{opacity:.4;cursor:not-allowed}

/* ── Toast ───────────────────────────────── */
.toast-wrap{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast-msg{
  background:#1a2332;color:#fff;border-radius:var(--r-sm);
  padding:12px 18px;font-size:13.5px;
  display:flex;align-items:center;gap:10px;
  box-shadow:var(--shadow-lg);min-width:240px;
  animation:toastIn .3s ease;
}
@keyframes toastIn{from{opacity:0;transform:translateY(12px)}}
.toast-msg.success i{color:var(--success)}
.toast-msg.info    i{color:var(--info)}
.toast-msg.warning i{color:var(--warning)}

/* ── Sidebar overlay + Responsive ──────── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999}
@media(max-width:991px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}
  .topbar{left:0}.main-content{margin-left:0}
  .sidebar-toggle{display:flex}
}
@media(max-width:768px){
  .content-inner{padding:16px}
  .topbar{padding:0 16px}
  .ph-left h2{font-size:18px}
}
.divider{height:1px;background:var(--border);margin:6px 0}

/* ── Print styles ────────────────────────── */
@media print{
  .sidebar,.topbar,.no-print{display:none!important}
  .main-content{margin-left:0;padding-top:0}
  .panel{box-shadow:none;border:1px solid #ddd;break-inside:avoid}
  body{background:#fff}
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══════════════════════════════════
     SIDEBAR
══════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">🐟</div>
    <div>
      <div class="brand-name">FishSample</div>
      <div class="brand-tagline">Management System</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="nav-link-item">
      <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
    </a>

    <div class="nav-section" style="margin-top:10px">Data Entry</div>
    <a href="admin/add_species.php"  class="nav-link-item"><i class="bi bi-fish-fill"></i><span>Add Species</span></a>
    <a href="admin/add_location.php" class="nav-link-item"><i class="bi bi-geo-alt-fill"></i><span>Add Location</span></a>
    <a href="admin/add_visit.php"    class="nav-link-item"><i class="bi bi-calendar-plus-fill"></i><span>Add Visit</span></a>
    <a href="admin/add_sampling.php" class="nav-link-item">
      <i class="bi bi-droplet-fill"></i><span>Add Sampling</span>
      <span class="nav-badge">New</span>
    </a>

    <div class="nav-section" style="margin-top:10px">Analytics</div>
    <a href="reports.php"      class="nav-link-item active"><i class="bi bi-file-earmark-bar-graph-fill"></i><span>Reports</span></a>
    <a href="location_map.php" class="nav-link-item"><i class="bi bi-map-fill"></i><span>Location Map</span></a>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= $adminInitial ?></div>
    <div class="user-details">
      <div class="name"><?= $adminName ?></div>
      <div class="role">Super Administrator</div>
    </div>
  </div>
</aside>

<!-- ══════════════════════════════════
     TOPBAR
══════════════════════════════════ -->
<header class="topbar">
  <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
  <div class="topbar-breadcrumb">
    <div class="page-title">Reports &amp; Analytics</div>
    <div class="breadcrumb-sub">Fish sampling data insights &amp; summaries</div>
  </div>
  <div class="topbar-right">
    <div class="topbar-btn" title="Refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></div>
    <div class="topbar-btn" title="Print" onclick="printReport()"><i class="bi bi-printer"></i></div>
    <div class="topbar-btn" title="Back to Dashboard" onclick="location.href='admin_dashboard.php'">
      <i class="bi bi-arrow-left"></i>
    </div>
    <div class="topbar-admin">
      <div class="topbar-avatar"><?= $adminInitial ?></div>
      <div class="topbar-admin-name"><?= $adminName ?></div>
      <i class="bi bi-chevron-down" style="font-size:10px;color:var(--muted)"></i>
    </div>
  </div>
</header>


<!-- ══════════════════════════════════
     MAIN CONTENT
══════════════════════════════════ -->
<main class="main-content">
<div class="content-inner">

  <!-- ── Page Header ── -->
  <div class="page-header no-print">
    <div class="ph-left">
      <h2>📊 Reports &amp; Analytics</h2>
      <p>Generated on <?= date('l, d F Y \a\t H:i') ?> &nbsp;·&nbsp; <?= $totalSpecimens ?> total specimens across <?= $totalLocations ?> locations</p>
    </div>
    <div class="ph-right">
      <button class="btn-outline-c"  onclick="printReport()"><i class="bi bi-printer"></i> Print Report</button>
      <button class="btn-success-c"  onclick="exportAllExcel()"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</button>
      <button class="btn-primary-c"  onclick="exportSpeciesExcel()"><i class="bi bi-fish"></i> Species Report</button>
      <button class="btn-danger-c"   onclick="exportLocationExcel()"><i class="bi bi-geo-alt-fill"></i> Location Report</button>
    </div>
  </div>


  <!-- ── KPI STAT CARDS ── -->
  <div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-blue">
        <div class="stat-icon"><i class="bi bi-fish-fill"></i></div>
        <div class="stat-value" data-target="<?= $totalSpecies ?>"  >0</div>
        <div class="stat-label">Species</div>
      </div>
    </div>
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-green">
        <div class="stat-icon"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="stat-value" data-target="<?= $totalLocations ?>">0</div>
        <div class="stat-label">Locations</div>
      </div>
    </div>
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-orange">
        <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
        <div class="stat-value" data-target="<?= $totalVisits ?>"   >0</div>
        <div class="stat-label">Visits</div>
      </div>
    </div>
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-red">
        <div class="stat-icon"><i class="bi bi-eyedropper-fill"></i></div>
        <div class="stat-value" data-target="<?= $totalSpecimens ?>">0</div>
        <div class="stat-label">Specimens</div>
      </div>
    </div>
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-violet">
        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="stat-value" style="font-size:22px" data-float="<?= $avgWeight ?>"><?= $avgWeight ?></div>
        <div class="stat-label">Avg Weight (kg)</div>
      </div>
    </div>
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-teal">
        <div class="stat-icon"><i class="bi bi-arrow-up-circle-fill"></i></div>
        <div class="stat-value" style="font-size:22px" data-float="<?= $maxWeight ?>"><?= $maxWeight ?></div>
        <div class="stat-label">Max Weight (kg)</div>
      </div>
    </div>
    <div class="col-xl col-md-4 col-6">
      <div class="stat-card c-pink">
        <div class="stat-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
        <div class="stat-value" style="font-size:22px" data-float="<?= $minWeight ?>"><?= $minWeight ?></div>
        <div class="stat-label">Min Weight (kg)</div>
      </div>
    </div>
  </div>


  <!-- ── CHARTS ROW 1 ── -->
  <div class="row g-3 mb-4">

    <!-- Species per Month -->
    <div class="col-xl-8">
      <div class="panel h-100">
        <div class="panel-hd">
          <div class="panel-title">
            <div class="title-icon ti-blue"><i class="bi bi-bar-chart-line-fill"></i></div>
            Species Found Per Month
          </div>
          <span class="tag">Biodiversity trend</span>
        </div>
        <div class="chart-wrap"><canvas id="ch1"></canvas></div>
      </div>
    </div>

    <!-- Top Species Doughnut -->
    <div class="col-xl-4">
      <div class="panel h-100">
        <div class="panel-hd">
          <div class="panel-title">
            <div class="title-icon ti-orange"><i class="bi bi-pie-chart-fill"></i></div>
            Top Species
          </div>
          <span class="tag">By specimens</span>
        </div>
        <div class="chart-wrap"><canvas id="ch4"></canvas></div>
      </div>
    </div>

  </div>


  <!-- ── CHARTS ROW 2 ── -->
  <div class="row g-3 mb-4">

    <!-- Specimens per Month -->
    <div class="col-xl-4">
      <div class="panel h-100">
        <div class="panel-hd">
          <div class="panel-title">
            <div class="title-icon ti-green"><i class="bi bi-graph-up"></i></div>
            Specimens Per Month
          </div>
          <span class="tag">Collection volume</span>
        </div>
        <div class="chart-wrap"><canvas id="ch2"></canvas></div>
      </div>
    </div>

    <!-- Visits per Month -->
    <div class="col-xl-4">
      <div class="panel h-100">
        <div class="panel-hd">
          <div class="panel-title">
            <div class="title-icon ti-violet"><i class="bi bi-calendar3"></i></div>
            Visits Per Month
          </div>
          <span class="tag">Field trips</span>
        </div>
        <div class="chart-wrap"><canvas id="ch3"></canvas></div>
      </div>
    </div>

    <!-- Avg Weight per Species -->
    <div class="col-xl-4">
      <div class="panel h-100">
        <div class="panel-hd">
          <div class="panel-title">
            <div class="title-icon ti-teal"><i class="bi bi-speedometer2"></i></div>
            Avg Weight / Species
          </div>
          <span class="tag">kg</span>
        </div>
        <div class="chart-wrap"><canvas id="ch6"></canvas></div>
      </div>
    </div>

  </div>


  <!-- ── SPECIMENS PER LOCATION (full width) ── -->
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="panel">
        <div class="panel-hd">
          <div class="panel-title">
            <div class="title-icon ti-pink"><i class="bi bi-pin-map-fill"></i></div>
            Specimens Per Location
          </div>
          <span class="tag">All sampling sites</span>
        </div>
        <div class="chart-wrap"><canvas id="ch5" style="max-height:220px"></canvas></div>
      </div>
    </div>
  </div>


  <!-- ── TABBED DETAIL TABLES ── -->
  <div class="panel mb-4">
    <div class="panel-hd">
      <div class="panel-title">
        <div class="title-icon ti-blue"><i class="bi bi-table"></i></div>
        Detailed Data Tables
      </div>
      <!-- Tabs -->
      <div class="tab-bar no-print">
        <button class="tab-btn active" onclick="switchTab('species',this)">
          <i class="bi bi-fish-fill"></i> Species Summary
        </button>
        <button class="tab-btn" onclick="switchTab('location',this)">
          <i class="bi bi-geo-alt-fill"></i> Location Summary
        </button>
        <button class="tab-btn" onclick="switchTab('fulllog',this)">
          <i class="bi bi-journal-text"></i> Full Sampling Log
        </button>
      </div>
    </div>

    <!-- ── Tab: Species Summary ── -->
    <div class="tab-panel active" id="tab-species">
      <div style="overflow-x:auto">
        <table class="data-table" id="tblSpecies">
          <thead>
            <tr>
              <th>#</th>
              <th>Species (Local Name)</th>
              <th>Scientific Name</th>
              <th>Total Specimens</th>
              <th>Avg Weight (kg)</th>
              <th>Max Weight</th>
              <th>Min Weight</th>
              <th>Locations Found</th>
              <th>Spread</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($speciesTable as $i => $r): ?>
            <tr>
              <td>
                <?php
                  if    ($i === 0) echo '<span class="rank rank-1">🥇</span>';
                  elseif($i === 1) echo '<span class="rank rank-2">🥈</span>';
                  elseif($i === 2) echo '<span class="rank rank-3">🥉</span>';
                  else             echo '<span class="rank rank-n">'.($i+1).'</span>';
                ?>
              </td>
              <td><span class="fish-chip">🐟 <?= htmlspecialchars($r['local_name']) ?></span></td>
              <td><span class="sci"><?= htmlspecialchars($r['scientific_name'] ?? '—') ?></span></td>
              <td><strong><?= (int)$r['total_specimens'] ?></strong></td>
              <td><span class="wt"><?= $r['avg_weight'] ? number_format($r['avg_weight'],2) : '—' ?></span></td>
              <td><span class="wt"><?= $r['max_weight'] ? number_format($r['max_weight'],2) : '—' ?></span></td>
              <td><span class="wt"><?= $r['min_weight'] ? number_format($r['min_weight'],2) : '—' ?></span></td>
              <td><?= (int)$r['locations_found'] ?> site<?= $r['locations_found']!=1?'s':'' ?></td>
              <td style="min-width:100px">
                <?php $maxSp = max(array_column($speciesTable,'total_specimens') ?: [1]); $pct = $maxSp > 0 ? round(($r['total_specimens']/$maxSp)*100) : 0; ?>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="prog-wrap"><div class="prog-fill" style="width:<?=$pct?>%;background:var(--primary)"></div></div>
                  <span style="font-size:11px;color:var(--muted);min-width:30px"><?=$pct?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Tab: Location Summary ── -->
    <div class="tab-panel" id="tab-location">
      <div style="overflow-x:auto">
        <table class="data-table" id="tblLocation">
          <thead>
            <tr>
              <th>#</th>
              <th>Location Name</th>
              <th>Total Visits</th>
              <th>Total Specimens</th>
              <th>Species Count</th>
              <th>Avg Weight (kg)</th>
              <th>Last Visit</th>
              <th>Activity</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($locationTable as $i => $r): ?>
            <tr>
              <td><span class="rank <?= $i<3?'rank-'.($i+1):'rank-n' ?>"><?= $i+1 ?></span></td>
              <td>
                <span class="loc-chip">
                  <i class="bi bi-geo-alt-fill" style="color:var(--success)"></i>
                  <strong><?= htmlspecialchars($r['location_name']) ?></strong>
                </span>
              </td>
              <td><?= (int)$r['total_visits'] ?></td>
              <td><strong><?= (int)$r['total_specimens'] ?></strong></td>
              <td><?= (int)$r['species_count'] ?> sp.</td>
              <td><span class="wt"><?= $r['avg_weight'] ? number_format($r['avg_weight'],2) : '—' ?></span></td>
              <td><span class="dt"><?= $r['last_visit'] ?? '—' ?></span></td>
              <td style="min-width:100px">
                <?php $maxLoc = max(array_column($locationTable,'total_specimens') ?: [1]); $pct = $maxLoc > 0 ? round(($r['total_specimens']/$maxLoc)*100) : 0; ?>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="prog-wrap"><div class="prog-fill" style="width:<?=$pct?>%;background:var(--success)"></div></div>
                  <span style="font-size:11px;color:var(--muted);min-width:30px"><?=$pct?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Tab: Full Sampling Log ── -->
    <div class="tab-panel" id="tab-fulllog">
      <!-- search inside log -->
      <div style="padding:0 0 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center" class="no-print">
        <div style="position:relative;flex:1;min-width:200px;max-width:340px">
          <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted-lt);pointer-events:none"></i>
          <input type="text" id="logSearch" placeholder="Search fish, location, date…"
            style="width:100%;border:1px solid var(--border);border-radius:var(--r-sm);padding:8px 12px 8px 34px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text)"
            oninput="filterLog()" />
        </div>
        <button class="btn-success-c" onclick="exportAllExcel()">
          <i class="bi bi-file-earmark-excel-fill"></i> Export This Log
        </button>
      </div>

      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Month</th>
              <th>Species</th>
              <th>Scientific Name</th>
              <th>Location</th>
              <th>Weight (kg)</th>
            </tr>
          </thead>
          <tbody id="logBody"></tbody>
        </table>
      </div>
      <div class="pag-bar">
        <div class="pag-info" id="logPagInfo"></div>
        <div class="pag-btns" id="logPagBtns"></div>
      </div>
    </div>

  </div><!-- /panel -->

</div><!-- /content-inner -->
</main>

<div class="toast-wrap" id="toastWrap"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ══════════════════════════════════════════
   PHP DATA → JS
══════════════════════════════════════════ */
const D = <?= $jsData ?>;

/* ══════════════════════════════════════════
   SHARED CHART HELPERS
══════════════════════════════════════════ */
const PALETTE = ['#1a6bff','#0fba81','#f59f00','#fa5252','#7c3aed','#0891b2','#db2777','#059669','#d97706'];

const tooltipBase = {
  backgroundColor:'#1a2332', titleColor:'#fff',
  bodyColor:'#94a3b8', padding:12, cornerRadius:10, displayColors:false
};

const axisBase = {
  grid:   { color:'rgba(0,0,0,0.04)' },
  ticks:  { color:'#7a8999', font:{ family:'Plus Jakarta Sans', size:11 } },
  border: { color:'transparent' }
};

function makeGrad(ctx, color, alpha1=0.4, alpha2=0.03) {
  const g = ctx.createLinearGradient(0,0,0,280);
  g.addColorStop(0, color.replace(')',`,${alpha1})`).replace('rgb','rgba'));
  g.addColorStop(1, color.replace(')',`,${alpha2})`).replace('rgb','rgba'));
  return g;
}

/* ══════════════════════════════════════════
   BUILD ALL CHARTS
══════════════════════════════════════════ */
function buildCharts() {
  /* CH1 — Species per Month (bar+line combo) */
  (() => {
    const ctx = document.getElementById('ch1').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,280);
    grad.addColorStop(0,'rgba(26,107,255,0.45)');
    grad.addColorStop(1,'rgba(26,107,255,0.03)');
    new Chart(ctx, {
      type:'bar',
      data:{
        labels: D.ch1.labels.length ? D.ch1.labels : ['No Data'],
        datasets:[{
          label:'Species Found',
          data: D.ch1.data.length ? D.ch1.data : [0],
          backgroundColor: grad,
          borderColor:'#1a6bff', borderWidth:2,
          borderRadius:8, borderSkipped:false,
          hoverBackgroundColor:'rgba(26,107,255,0.6)',
        }]
      },
      options:{
        responsive:true,
        plugins:{ legend:{display:false}, tooltip:{...tooltipBase, callbacks:{label:c=>`  ${c.parsed.y} species`}} },
        scales:{ x:axisBase, y:{...axisBase, beginAtZero:true, ticks:{...axisBase.ticks, stepSize:1}} }
      }
    });
  })();

  /* CH2 — Specimens per Month (area line) */
  (() => {
    const ctx = document.getElementById('ch2').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,260);
    grad.addColorStop(0,'rgba(15,186,129,0.35)');
    grad.addColorStop(1,'rgba(15,186,129,0.02)');
    new Chart(ctx, {
      type:'line',
      data:{
        labels: D.ch2.labels.length ? D.ch2.labels : ['No Data'],
        datasets:[{
          label:'Specimens', data: D.ch2.data.length ? D.ch2.data : [0],
          borderColor:'#0fba81', backgroundColor:grad, fill:true,
          tension:0.45, borderWidth:2.5,
          pointBackgroundColor:'#0fba81', pointRadius:4, pointHoverRadius:7
        }]
      },
      options:{
        responsive:true,
        plugins:{ legend:{display:false}, tooltip:{...tooltipBase} },
        scales:{ x:{...axisBase, grid:{display:false}}, y:{...axisBase, beginAtZero:true, ticks:{...axisBase.ticks, stepSize:1}} }
      }
    });
  })();

  /* CH3 — Visits per Month (bar) */
  (() => {
    const ctx = document.getElementById('ch3').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,260);
    grad.addColorStop(0,'rgba(124,58,237,0.4)');
    grad.addColorStop(1,'rgba(124,58,237,0.03)');
    new Chart(ctx, {
      type:'bar',
      data:{
        labels: D.ch3.labels.length ? D.ch3.labels : ['No Data'],
        datasets:[{
          label:'Visits', data: D.ch3.data.length ? D.ch3.data : [0],
          backgroundColor:grad, borderColor:'#7c3aed',
          borderWidth:2, borderRadius:8, borderSkipped:false
        }]
      },
      options:{
        responsive:true,
        plugins:{ legend:{display:false}, tooltip:{...tooltipBase} },
        scales:{ x:axisBase, y:{...axisBase, beginAtZero:true, ticks:{...axisBase.ticks, stepSize:1}} }
      }
    });
  })();

  /* CH4 — Top species doughnut */
  (() => {
    new Chart(document.getElementById('ch4'), {
      type:'doughnut',
      data:{
        labels: D.ch4.labels.length ? D.ch4.labels : ['No Data'],
        datasets:[{
          data: D.ch4.data.length ? D.ch4.data : [1],
          backgroundColor: PALETTE,
          borderColor:'rgba(240,244,251,0.8)', borderWidth:3, hoverOffset:8
        }]
      },
      options:{
        responsive:true, cutout:'65%',
        plugins:{
          legend:{ position:'bottom', labels:{color:'#7a8999', font:{family:'Plus Jakarta Sans',size:11}, boxWidth:10, padding:10} },
          tooltip:{...tooltipBase}
        }
      }
    });
  })();

  /* CH5 — Specimens per Location (horizontal bar) */
  (() => {
    new Chart(document.getElementById('ch5'), {
      type:'bar',
      data:{
        labels: D.ch5.labels.length ? D.ch5.labels : ['No Data'],
        datasets:[{
          label:'Specimens', data: D.ch5.data.length ? D.ch5.data : [0],
          backgroundColor: D.ch5.data.map((_,i) => PALETTE[i % PALETTE.length]),
          borderRadius:6, borderSkipped:false
        }]
      },
      options:{
        indexAxis:'y', responsive:true,
        plugins:{ legend:{display:false}, tooltip:{...tooltipBase} },
        scales:{
          x:{...axisBase, beginAtZero:true, ticks:{...axisBase.ticks, stepSize:1}},
          y:{...axisBase, grid:{display:false}}
        }
      }
    });
  })();

  /* CH6 — Avg weight per species (bar) */
  (() => {
    const ctx = document.getElementById('ch6').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,260);
    grad.addColorStop(0,'rgba(8,145,178,0.45)');
    grad.addColorStop(1,'rgba(8,145,178,0.03)');
    new Chart(ctx, {
      type:'bar',
      data:{
        labels: D.ch6.labels.length ? D.ch6.labels : ['No Data'],
        datasets:[{
          label:'Avg Weight (kg)', data: D.ch6.data.length ? D.ch6.data : [0],
          backgroundColor:grad, borderColor:'#0891b2',
          borderWidth:2, borderRadius:8, borderSkipped:false
        }]
      },
      options:{
        responsive:true,
        plugins:{ legend:{display:false}, tooltip:{...tooltipBase, callbacks:{label:c=>`  ${c.parsed.y} kg avg`}} },
        scales:{ x:axisBase, y:{...axisBase, beginAtZero:true} }
      }
    });
  })();
}

/* ══════════════════════════════════════════
   COUNTER ANIMATION
══════════════════════════════════════════ */
function animateCounters() {
  document.querySelectorAll('.stat-value[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target);
    let val = 0;
    const step = Math.max(1, Math.floor(target / 35));
    const t = setInterval(() => {
      val = Math.min(val + step, target);
      el.textContent = val;
      if (val >= target) clearInterval(t);
    }, 35);
  });
}

/* ══════════════════════════════════════════
   TABS
══════════════════════════════════════════ */
function switchTab(name, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-' + name).classList.add('active');
  if (name === 'fulllog') { renderLog(); }
}

/* ══════════════════════════════════════════
   FULL LOG PAGINATION
══════════════════════════════════════════ */
let logAll  = D.log;
let logFilt = [...logAll];
let logPage = 1;
const logPerPage = 10;

function filterLog() {
  const q = document.getElementById('logSearch').value.trim().toLowerCase();
  logFilt = logAll.filter(r =>
    r.fish.toLowerCase().includes(q)     ||
    r.location.toLowerCase().includes(q) ||
    r.visit_date.includes(q)             ||
    (r.month_name||'').toLowerCase().includes(q)
  );
  logPage = 1;
  renderLog();
}

function renderLog() {
  const tbody = document.getElementById('logBody');
  const total = logFilt.length;
  const start = (logPage - 1) * logPerPage;
  const end   = Math.min(start + logPerPage, total);
  const page  = logFilt.slice(start, end);

  if (!page.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">
      <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:10px;opacity:.3"></i>
      No records found.</td></tr>`;
  } else {
    tbody.innerHTML = page.map((r, i) => `
      <tr>
        <td style="color:var(--muted-lt);font-size:12px;font-weight:600">${start+i+1}</td>
        <td><span class="dt">${r.visit_date}</span></td>
        <td style="color:var(--muted);font-size:12.5px">${r.month_name||''}</td>
        <td><span class="fish-chip">🐟 ${esc(r.fish)}</span></td>
        <td><span class="sci">${esc(r.scientific_name||'—')}</span></td>
        <td><span class="loc-chip"><i class="bi bi-geo-alt" style="color:var(--muted-lt)"></i>${esc(r.location)}</span></td>
        <td><span class="wt">${r.weight ? parseFloat(r.weight).toFixed(2)+' kg' : '—'}</span></td>
      </tr>`).join('');
  }

  /* pagination */
  const totalPages = Math.max(1, Math.ceil(total / logPerPage));
  const s = start + 1, e = end;

  document.getElementById('logPagInfo').innerHTML =
    `Showing <span>${total===0?0:s}–${e}</span> of <span>${total}</span> records`;

  let html = `<button class="pag-btn" onclick="goLog(${logPage-1})" ${logPage===1?'disabled':''}><i class="bi bi-chevron-left"></i></button>`;
  for (let p=1; p<=totalPages; p++) {
    if (totalPages>7 && p>2 && p<totalPages-1 && Math.abs(p-logPage)>1) {
      if (p===3 || p===totalPages-2) html += `<button class="pag-btn" disabled>…</button>`;
      continue;
    }
    html += `<button class="pag-btn ${p===logPage?'active':''}" onclick="goLog(${p})">${p}</button>`;
  }
  html += `<button class="pag-btn" onclick="goLog(${logPage+1})" ${logPage===totalPages?'disabled':''}><i class="bi bi-chevron-right"></i></button>`;
  document.getElementById('logPagBtns').innerHTML = html;
}

function goLog(p) {
  const max = Math.ceil(logFilt.length / logPerPage);
  if (p<1 || p>max) return;
  logPage = p;
  renderLog();
}

/* ══════════════════════════════════════════
   EXCEL EXPORTS
══════════════════════════════════════════ */
function exportAllExcel() {
  toast('Preparing full log export…','info');
  setTimeout(() => {
    const rows = [
      ['#','Date','Month','Species','Scientific Name','Location','Weight (kg)'],
      ...logFilt.map((r,i) => [
        i+1, r.visit_date, r.month_name||'', r.fish,
        r.scientific_name||'', r.location,
        r.weight ? parseFloat(r.weight) : ''
      ])
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{wch:4},{wch:14},{wch:12},{wch:16},{wch:22},{wch:24},{wch:12}];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Full Sampling Log');
    XLSX.writeFile(wb, `full_sampling_log_${today()}.xlsx`);
    toast('Full log exported!','success');
  }, 400);
}

function exportSpeciesExcel() {
  toast('Preparing species report…','info');
  setTimeout(() => {
    const rows = [
      ['#','Local Name','Scientific Name','Total Specimens','Avg Weight (kg)','Max Weight','Min Weight','Locations Found'],
      ...<?= json_encode($speciesTable) ?>.map((r,i) => [
        i+1, r.local_name, r.scientific_name||'',
        r.total_specimens, r.avg_weight||'',
        r.max_weight||'', r.min_weight||'',
        r.locations_found
      ])
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{wch:4},{wch:16},{wch:22},{wch:16},{wch:14},{wch:12},{wch:12},{wch:16}];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Species Summary');
    XLSX.writeFile(wb, `species_report_${today()}.xlsx`);
    toast('Species report exported!','success');
  }, 400);
}

function exportLocationExcel() {
  toast('Preparing location report…','info');
  setTimeout(() => {
    const rows = [
      ['#','Location','Total Visits','Total Specimens','Species Count','Avg Weight (kg)','Last Visit'],
      ...<?= json_encode($locationTable) ?>.map((r,i) => [
        i+1, r.location_name, r.total_visits,
        r.total_specimens, r.species_count,
        r.avg_weight||'', r.last_visit||''
      ])
    ];
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{wch:4},{wch:24},{wch:12},{wch:16},{wch:14},{wch:14},{wch:14}];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Location Summary');
    XLSX.writeFile(wb, `location_report_${today()}.xlsx`);
    toast('Location report exported!','success');
  }, 400);
}

/* ══════════════════════════════════════════
   PRINT
══════════════════════════════════════════ */
function printReport() {
  toast('Opening print dialog…','info');
  setTimeout(() => window.print(), 600);
}

/* ══════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════ */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}

/* ══════════════════════════════════════════
   TOAST
══════════════════════════════════════════ */
const TICONS = { success:'bi-check-circle-fill', info:'bi-info-circle-fill', warning:'bi-exclamation-triangle-fill' };
function toast(msg, type='info') {
  const c = document.getElementById('toastWrap');
  const el = document.createElement('div');
  el.className = `toast-msg ${type}`;
  el.innerHTML = `<i class="bi ${TICONS[type]||TICONS.info}"></i><span>${msg}</span>`;
  c.appendChild(el);
  setTimeout(()=>{ el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(()=>el.remove(),300); }, 3000);
}

/* ══════════════════════════════════════════
   UTILS
══════════════════════════════════════════ */
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function today(){ return new Date().toISOString().slice(0,10); }

/* ══════════════════════════════════════════
   INIT
══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  animateCounters();
  buildCharts();
  renderLog();
  toast(`Reports loaded — ${D.log.length} total records`, 'success');
});
</script>
</body>
</html>