<?php
/* ═══════════════════════════════════════════════════════
   Fish Sampling Management System — Admin Dashboard
   Same beautiful design + real database connection
═══════════════════════════════════════════════════════ */
session_start();
include "search/connect.php";   // your existing DB connection

/* ── Auth Guard ─────────────────────────────────────── */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName   = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$adminInitial = strtoupper(substr($adminName, 0, 1));

/* ══════════════════════════════════════════════════════
   STAT CARDS  — 4 counts
══════════════════════════════════════════════════════ */
$statSpecies   = (int) $conn->query("SELECT COUNT(*) c FROM species")  ->fetch_assoc()['c'];
$statLocations = (int) $conn->query("SELECT COUNT(*) c FROM locations")->fetch_assoc()['c'];
$statVisits    = (int) $conn->query("SELECT COUNT(*) c FROM visits")   ->fetch_assoc()['c'];
$statSpecimens = (int) $conn->query("SELECT COUNT(*) c FROM specimens")->fetch_assoc()['c'];

/* ══════════════════════════════════════════════════════
   CHART 1 — Species found per month
   (distinct species per calendar month, all years)
══════════════════════════════════════════════════════ */
$chartSpeciesLabels = [];
$chartSpeciesData   = [];

$res = $conn->query("
    SELECT
        MONTH(v.visit_date)     AS mn,
        MONTHNAME(v.visit_date) AS mname,
        COUNT(DISTINCT s.species_id) AS cnt
    FROM specimens s
    JOIN visits v ON v.id = s.visit_id
    GROUP BY mn, mname
    ORDER BY mn
");
while ($r = $res->fetch_assoc()) {
    $chartSpeciesLabels[] = substr($r['mname'], 0, 3);   // "Jan", "Feb" …
    $chartSpeciesData[]   = (int) $r['cnt'];
}

/* ══════════════════════════════════════════════════════
   CHART 2 — Visits over time (per month)
══════════════════════════════════════════════════════ */
$chartVisitLabels = [];
$chartVisitData   = [];

$res = $conn->query("
    SELECT
        MONTH(visit_date)     AS mn,
        MONTHNAME(visit_date) AS mname,
        COUNT(*)              AS cnt
    FROM visits
    GROUP BY mn, mname
    ORDER BY mn
");
while ($r = $res->fetch_assoc()) {
    $chartVisitLabels[] = substr($r['mname'], 0, 3);
    $chartVisitData[]   = (int) $r['cnt'];
}

/* ══════════════════════════════════════════════════════
   FILTER DROP-DOWN VALUES — unique months & locations
   pulled directly from DB so they always match real data
══════════════════════════════════════════════════════ */
$dbMonths = [];
$res = $conn->query("
    SELECT DISTINCT MONTHNAME(visit_date) AS m, MONTH(visit_date) AS mn
    FROM visits ORDER BY mn
");
while ($r = $res->fetch_assoc()) $dbMonths[] = $r['m'];

$dbLocations = [];
$res = $conn->query("SELECT id, location_name FROM locations ORDER BY location_name");
while ($r = $res->fetch_assoc()) $dbLocations[] = $r;

$dbSpecies = [];
$res = $conn->query("SELECT id, local_name FROM species ORDER BY local_name");
while ($r = $res->fetch_assoc()) $dbSpecies[] = $r;

/* ══════════════════════════════════════════════════════
   TABLE DATA — latest sampling records
   All records fetched; JS handles search / pagination
══════════════════════════════════════════════════════ */
$tableRows = [];
$res = $conn->query("
    SELECT
        v.visit_date,
        MONTHNAME(v.visit_date) AS month_name,
        sp.local_name           AS fish,
        l.location_name         AS location,
        s.weight
    FROM specimens s
    JOIN visits    v  ON v.id  = s.visit_id
    JOIN species   sp ON sp.id = s.species_id
    JOIN locations l  ON l.id  = v.location_id
    ORDER BY v.visit_date DESC
");
while ($r = $res->fetch_assoc()) $tableRows[] = $r;

/* ── Pass PHP data to JS ──────────────────────────── */
$jsRecords           = json_encode(array_values($tableRows));
$jsChartSpeciesLabels = json_encode($chartSpeciesLabels);
$jsChartSpeciesData   = json_encode($chartSpeciesData);
$jsChartVisitLabels  = json_encode($chartVisitLabels);
$jsChartVisitData    = json_encode($chartVisitData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Fish Sampling — Admin Dashboard</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet" />
<!-- SheetJS -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<style>
/* ══════════════════════════════════════════
   ROOT TOKENS
══════════════════════════════════════════ */
:root {
  --sidebar-w:  255px;
  --topbar-h:   64px;
  --primary:    #1a6bff;
  --primary-lt: #e8f0ff;
  --primary-dk: #1254cc;
  --success:    #0fba81;
  --warning:    #f59f00;
  --danger:     #fa5252;
  --info:       #4dabf7;
  --bg:         #f0f4fb;
  --surface:    #ffffff;
  --sidebar-bg: #0d1b2e;
  --sidebar-hover:  rgba(255,255,255,0.06);
  --sidebar-active: rgba(26,107,255,0.20);
  --border:     #e3e8f0;
  --text:       #1a2332;
  --muted:      #7a8999;
  --muted-lt:   #b0bcc8;
  --r-sm: 10px;
  --r:    14px;
  --r-lg: 20px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow:    0 4px 16px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.04);
  --shadow-lg: 0 12px 40px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.05);
}

/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);margin:0;overflow-x:hidden}
a{text-decoration:none}

/* ══════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════ */
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
  border-bottom:1px solid rgba(255,255,255,.07);
  flex-shrink:0;
}
.brand-logo{
  width:38px;height:38px;
  background:linear-gradient(135deg,var(--primary),#4dabf7);
  border-radius:11px;
  display:flex;align-items:center;justify-content:center;
  font-size:18px;
  box-shadow:0 4px 12px rgba(26,107,255,.4);
  flex-shrink:0;
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
  color:rgba(255,255,255,.55);
  font-size:13.5px;font-weight:500;
  margin-bottom:2px;cursor:pointer;
  transition:all .18s ease;position:relative;
  white-space:nowrap;
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
  font-size:10px;font-weight:700;padding:2px 7px;
  border-radius:99px;line-height:1.6;
}

.sidebar-user{
  padding:14px 16px;
  border-top:1px solid rgba(255,255,255,.07);
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

/* ══════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════ */
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
.notif-dot{
  position:absolute;top:6px;right:7px;
  width:7px;height:7px;background:var(--danger);
  border-radius:50%;border:2px solid var(--surface);
}
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

/* ══════════════════════════════════════════
   MAIN
══════════════════════════════════════════ */
.main-content{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh}
.content-inner{padding:28px}

/* ══════════════════════════════════════════
   STAT CARDS
══════════════════════════════════════════ */
.stat-card{
  background:var(--surface);border-radius:var(--r-lg);
  padding:22px 24px;border:1px solid var(--border);
  box-shadow:var(--shadow-sm);position:relative;overflow:hidden;
  transition:transform .22s ease,box-shadow .22s ease;
  animation:fadeSlideUp .5s ease both;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}

@keyframes fadeSlideUp{
  from{opacity:0;transform:translateY(20px)}
  to{opacity:1;transform:translateY(0)}
}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.10s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-card:nth-child(4){animation-delay:.20s}

.stat-card::after{
  content:'';position:absolute;bottom:-18px;right:-18px;
  width:90px;height:90px;border-radius:50%;opacity:.07;
}
.c-blue::after{background:var(--primary)}
.c-green::after{background:var(--success)}
.c-orange::after{background:var(--warning)}
.c-red::after{background:var(--danger)}

.stat-icon{
  width:48px;height:48px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  font-size:22px;margin-bottom:16px;
}
.c-blue  .stat-icon{background:#e8f0ff;color:var(--primary)}
.c-green .stat-icon{background:#e6faf4;color:var(--success)}
.c-orange .stat-icon{background:#fff8e6;color:var(--warning)}
.c-red   .stat-icon{background:#fff0f0;color:var(--danger)}

.stat-value{font-size:34px;font-weight:800;line-height:1;margin-bottom:4px;letter-spacing:-1px}
.c-blue  .stat-value{color:var(--primary)}
.c-green .stat-value{color:var(--success)}
.c-orange .stat-value{color:var(--warning)}
.c-red   .stat-value{color:var(--danger)}

.stat-label{font-size:12.5px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.6px}
.stat-trend{
  display:inline-flex;align-items:center;gap:4px;
  font-size:11px;font-weight:600;margin-top:10px;
  padding:3px 8px;border-radius:99px;
}
.trend-up{background:#e6faf4;color:var(--success)}
.trend-neu{background:var(--bg);color:var(--muted)}

/* ══════════════════════════════════════════
   SECTION HEADERS
══════════════════════════════════════════ */
.section-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:16px;flex-wrap:wrap;gap:10px;
}
.section-title{
  font-size:15px;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:8px;
}
.title-icon{
  width:30px;height:30px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;font-size:14px;
}
.ti-blue  {background:var(--primary-lt);color:var(--primary)}
.ti-green {background:#e6faf4;color:var(--success)}
.ti-orange{background:#fff8e6;color:var(--warning)}

/* ══════════════════════════════════════════
   CHART CARDS
══════════════════════════════════════════ */
.chart-card{
  background:var(--surface);border-radius:var(--r-lg);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  padding:24px;animation:fadeSlideUp .5s ease .25s both;
}
.chart-card canvas{max-height:270px}

/* ══════════════════════════════════════════
   FILTER BAR
══════════════════════════════════════════ */
.filter-bar{
  background:var(--surface);border-radius:var(--r);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  padding:16px 20px;display:flex;align-items:center;
  gap:12px;flex-wrap:wrap;margin-bottom:20px;
  animation:fadeSlideUp .5s ease .30s both;
}
.filter-label{
  font-size:12px;font-weight:600;color:var(--muted);
  text-transform:uppercase;letter-spacing:.8px;
  white-space:nowrap;display:flex;align-items:center;gap:5px;
}
.filter-select{
  border:1px solid var(--border);border-radius:var(--r-sm);
  padding:7px 12px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;
  color:var(--text);background:var(--bg);cursor:pointer;
  transition:border-color .18s;min-width:130px;
}
.filter-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,255,.1)}

/* ══════════════════════════════════════════
   TABLE CARD
══════════════════════════════════════════ */
.table-card{
  background:var(--surface);border-radius:var(--r-lg);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  overflow:hidden;animation:fadeSlideUp .5s ease .35s both;
}
.table-toolbar{
  padding:18px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:12px;
}
.search-wrap{position:relative;flex:1;min-width:200px;max-width:320px}
.search-wrap i{
  position:absolute;left:12px;top:50%;transform:translateY(-50%);
  color:var(--muted-lt);font-size:15px;pointer-events:none;
}
.search-input{
  width:100%;border:1px solid var(--border);border-radius:var(--r-sm);
  padding:8px 12px 8px 36px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;
  color:var(--text);background:var(--bg);transition:border-color .18s;
}
.search-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,255,.1);background:var(--surface)}
.search-input::placeholder{color:var(--muted-lt)}
.toolbar-right{display:flex;align-items:center;gap:8px}

/* ── Buttons ── */
.btn-primary-c{
  background:var(--primary);color:#fff;border:none;
  border-radius:var(--r-sm);padding:8px 16px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:6px;
  transition:all .18s;box-shadow:0 2px 8px rgba(26,107,255,.25);
}
.btn-primary-c:hover{background:var(--primary-dk);box-shadow:0 4px 14px rgba(26,107,255,.35);transform:translateY(-1px)}

.btn-outline-c{
  background:var(--surface);color:var(--muted);
  border:1px solid var(--border);border-radius:var(--r-sm);
  padding:8px 14px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:500;
  cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .18s;
}
.btn-outline-c:hover{background:var(--bg);color:var(--text);border-color:#ccd4e0}

.btn-success-c{
  background:var(--success);color:#fff;border:none;
  border-radius:var(--r-sm);padding:8px 16px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:6px;
  transition:all .18s;box-shadow:0 2px 8px rgba(15,186,129,.25);
}
.btn-success-c:hover{background:#0ca572;transform:translateY(-1px)}

.btn-danger-c{
  background:var(--danger);color:#fff;border:none;
  border-radius:var(--r-sm);padding:8px 14px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .18s;
}
.btn-danger-c:hover{background:#e03131;transform:translateY(-1px)}

/* ── Table ── */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{
  font-size:11px;font-weight:700;letter-spacing:1.2px;
  text-transform:uppercase;color:var(--muted);
  padding:12px 22px;background:#fafbfd;
  border-bottom:1px solid var(--border);
  white-space:nowrap;cursor:pointer;user-select:none;transition:background .15s;
}
.data-table thead th:hover{background:#f3f5f9}
.data-table thead th .sort-icon{margin-left:5px;opacity:.4;font-size:10px;transition:opacity .15s}
.data-table thead th.sort-asc .sort-icon,
.data-table thead th.sort-desc .sort-icon{opacity:1;color:var(--primary)}

.data-table tbody td{
  padding:14px 22px;font-size:13.5px;
  border-bottom:1px solid #f2f4f8;
  vertical-align:middle;transition:background .15s;
}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover td{background:#f8faff}

.fish-chip{
  display:inline-flex;align-items:center;gap:6px;
  padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600;
  background:var(--primary-lt);color:var(--primary);
  border:1px solid rgba(26,107,255,.15);
}
.loc-chip{display:inline-flex;align-items:center;gap:5px;font-size:13px;color:var(--muted)}
.weight-badge{
  background:#fff8e6;color:var(--warning);
  border:1px solid rgba(245,159,0,.2);
  padding:3px 10px;border-radius:6px;
  font-size:12px;font-weight:700;
  font-family:'Fira Code',monospace;
}
.date-text{font-family:'Fira Code',monospace;font-size:12.5px;color:var(--muted)}
.status-logged {background:#e6faf4;color:var(--success);padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600}
.status-pending{background:#fff8e6;color:var(--warning);padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600}

/* ── Pagination ── */
.pagination-bar{
  padding:14px 22px;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:10px;
}
.pagination-info{font-size:12.5px;color:var(--muted)}
.pagination-info span{font-weight:700;color:var(--text)}
.page-btns{display:flex;gap:4px}
.page-btn{
  width:32px;height:32px;border-radius:8px;
  border:1px solid var(--border);background:var(--surface);
  color:var(--muted);font-size:13px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:500;transition:all .18s;
}
.page-btn:hover:not(:disabled){background:var(--bg);color:var(--text)}
.page-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700;box-shadow:0 2px 8px rgba(26,107,255,.3)}
.page-btn:disabled{opacity:.4;cursor:not-allowed}

/* ── Empty State ── */
.empty-state{text-align:center;padding:50px 20px;color:var(--muted)}
.empty-state i{font-size:40px;margin-bottom:12px;opacity:.3;display:block}
.empty-state p{font-size:14px;margin:0}

/* ══════════════════════════════════════════
   QUICK ACTIONS PANEL
══════════════════════════════════════════ */
.quick-panel{
  background:var(--surface);border-radius:var(--r-lg);
  border:1px solid var(--border);box-shadow:var(--shadow-sm);
  padding:22px;animation:fadeSlideUp .5s ease .40s both;
}
.quick-btn{
  display:flex;align-items:center;gap:12px;
  padding:12px 14px;border-radius:var(--r-sm);
  border:1px solid var(--border);background:var(--bg);
  color:var(--text);font-size:13.5px;font-weight:500;
  font-family:'Plus Jakarta Sans',sans-serif;
  cursor:pointer;transition:all .2s;
  width:100%;margin-bottom:8px;text-decoration:none;
}
.quick-btn:hover{background:var(--surface);border-color:var(--primary);color:var(--primary);box-shadow:0 2px 10px rgba(26,107,255,.1);transform:translateX(2px)}
.quick-btn:last-child{margin-bottom:0}
.qb-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.qb-arrow{margin-left:auto;color:var(--muted-lt);font-size:13px;transition:transform .18s}
.quick-btn:hover .qb-arrow{transform:translateX(3px);color:var(--primary)}

/* ══════════════════════════════════════════
   TOAST
══════════════════════════════════════════ */
.toast-container{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast-msg{
  background:#1a2332;color:#fff;border-radius:var(--r-sm);
  padding:12px 18px;font-size:13.5px;
  display:flex;align-items:center;gap:10px;
  box-shadow:var(--shadow-lg);animation:toastIn .3s ease;min-width:240px;
}
@keyframes toastIn{from{opacity:0;transform:translateY(14px)}}
.toast-msg.success i{color:var(--success)}
.toast-msg.info    i{color:var(--info)}
.toast-msg.warning i{color:var(--warning)}

/* ══════════════════════════════════════════
   SIDEBAR OVERLAY + RESPONSIVE
══════════════════════════════════════════ */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999}

@media(max-width:991px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}
  .topbar{left:0}
  .main-content{margin-left:0}
  .sidebar-toggle{display:flex}
}
@media(max-width:768px){
  .content-inner{padding:16px}
  .filter-bar{flex-direction:column;align-items:flex-start}
  .toolbar-right{flex-wrap:wrap}
  .topbar{padding:0 16px}
}

.divider{height:1px;background:var(--border);margin:4px 0}
</style>
</head>
<body>

<!-- Sidebar Overlay -->
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
    <a href="dashboard.php" class="nav-link-item active">
      <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
    </a>

    <div class="nav-section" style="margin-top:10px">Data Entry</div>
    <a href="admin/add_species.php" class="nav-link-item">
      <i class="bi bi-fish-fill"></i><span>Add Species</span>
    </a>
    <a href="admin/add_location.php" class="nav-link-item">
      <i class="bi bi-geo-alt-fill"></i><span>Add Location</span>
    </a>
    <a href="admin/add_visit.php" class="nav-link-item">
      <i class="bi bi-calendar-plus-fill"></i><span>Add Visit</span>
    </a>
    <a href="admin/add_sampling.php" class="nav-link-item">
      <i class="bi bi-droplet-fill"></i><span>Add Sampling</span>
      <!-- <span class="nav-badge">New</span> -->
    </a>

    <div class="nav-section" style="margin-top:10px">Analytics</div>
    <a href="reports.php" class="nav-link-item">
      <i class="bi bi-file-earmark-bar-graph-fill"></i><span>Reports</span>
    </a>
    <a href="location_map.php" class="nav-link-item">
      <i class="bi bi-map-fill"></i><span>Location Map</span>
    </a>
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
    <div class="page-title">Dashboard</div>
    <div class="breadcrumb-sub">Overview &amp; Analytics</div>
  </div>

  <div class="topbar-right">
    <div class="topbar-btn" title="Refresh" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise"></i>
    </div>
    <div class="topbar-btn" title="Notifications">
      <i class="bi bi-bell"></i>
      <span class="notif-dot"></span>
    </div>
    <div class="topbar-btn" title="Settings">
      <i class="bi bi-gear"></i>
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

  <!-- ═══ STAT CARDS (PHP values) ═══ -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="stat-card c-blue">
        <div class="stat-icon"><i class="bi bi-fish-fill"></i></div>
        <div class="stat-value" data-target="<?= $statSpecies ?>">0</div>
        <div class="stat-label">Total Species</div>
        <div><span class="stat-trend trend-up"><i class="bi bi-arrow-up"></i> Active Records</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="stat-card c-green">
        <div class="stat-icon"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="stat-value" data-target="<?= $statLocations ?>">0</div>
        <div class="stat-label">Total Locations</div>
        <div><span class="stat-trend trend-up"><i class="bi bi-arrow-up"></i> Sampling Sites</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="stat-card c-orange">
        <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
        <div class="stat-value" data-target="<?= $statVisits ?>">0</div>
        <div class="stat-label">Total Visits</div>
        <div><span class="stat-trend trend-neu"><i class="bi bi-dash"></i> Field Trips</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="stat-card c-red">
        <div class="stat-icon"><i class="bi bi-eyedropper-fill"></i></div>
        <div class="stat-value" data-target="<?= $statSpecimens ?>">0</div>
        <div class="stat-label">Total Specimens</div>
        <div><span class="stat-trend trend-up"><i class="bi bi-arrow-up"></i> Collected</span></div>
      </div>
    </div>
  </div>


  <!-- ═══ CHARTS ═══ -->
  <div class="row g-3 mb-4">

    <div class="col-xl-8">
      <div class="chart-card h-100">
        <div class="section-header">
          <div class="section-title">
            <div class="title-icon ti-blue"><i class="bi bi-bar-chart-fill"></i></div>
            Species Found Per Month
          </div>
          <button class="btn-outline-c" onclick="toggleChartType()">
            <i class="bi bi-arrow-repeat"></i> Toggle Type
          </button>
        </div>
        <canvas id="chartSpecies"></canvas>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="chart-card h-100">
        <div class="section-header">
          <div class="section-title">
            <div class="title-icon ti-green"><i class="bi bi-graph-up"></i></div>
            Visits Over Time
          </div>
        </div>
        <canvas id="chartVisits"></canvas>
      </div>
    </div>

  </div>


  <!-- ═══ FILTER + TABLE + QUICK ACTIONS ═══ -->
  <div class="row g-3">

    <div class="col-xl-9">

      <!-- Filter Bar (options from DB) -->
      <div class="filter-bar">
        <span class="filter-label"><i class="bi bi-funnel-fill"></i> Filter</span>

        <select class="filter-select" id="filterMonth" onchange="applyFilters()">
          <option value="">All Months</option>
          <?php foreach ($dbMonths as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>

        <select class="filter-select" id="filterLocation" onchange="applyFilters()">
          <option value="">All Locations</option>
          <?php foreach ($dbLocations as $loc): ?>
          <option value="<?= htmlspecialchars($loc['location_name']) ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
          <?php endforeach; ?>
        </select>

        <select class="filter-select" id="filterSpecies" onchange="applyFilters()">
          <option value="">All Species</option>
          <?php foreach ($dbSpecies as $sp): ?>
          <option value="<?= htmlspecialchars($sp['local_name']) ?>"><?= htmlspecialchars($sp['local_name']) ?></option>
          <?php endforeach; ?>
        </select>

        <button class="btn-outline-c" onclick="resetFilters()">
          <i class="bi bi-x-circle"></i> Clear
        </button>

        <button class="btn-success-c ms-auto" onclick="exportToExcel()">
          <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
        </button>
      </div>

      <!-- Table -->
      <div class="table-card">
        <div class="table-toolbar">
          <div class="section-title">
            <div class="title-icon ti-orange"><i class="bi bi-table"></i></div>
            Latest Sampling Records
            <span id="recordCount" style="font-size:12px;font-weight:500;color:var(--muted);margin-left:4px"></span>
          </div>
          <div class="toolbar-right">
            <div class="search-wrap">
              <i class="bi bi-search"></i>
              <input type="text" class="search-input" id="tableSearch"
                     placeholder="Search fish, location, date…"
                     oninput="handleSearch()" />
            </div>
            <button class="btn-outline-c" onclick="resetSort()">
              <i class="bi bi-arrow-down-up"></i> Sort
            </button>
          </div>
        </div>

        <div style="overflow-x:auto">
          <table class="data-table" id="dataTable">
            <thead>
              <tr>
                <th onclick="sortTable(0)" data-col="0">#<i class="bi bi-chevron-expand sort-icon"></i></th>
                <th onclick="sortTable(1)" data-col="1">Date <i class="bi bi-chevron-expand sort-icon"></i></th>
                <th onclick="sortTable(2)" data-col="2">Fish Species <i class="bi bi-chevron-expand sort-icon"></i></th>
                <th onclick="sortTable(3)" data-col="3">Location <i class="bi bi-chevron-expand sort-icon"></i></th>
                <th onclick="sortTable(4)" data-col="4">Weight <i class="bi bi-chevron-expand sort-icon"></i></th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="tableBody"></tbody>
          </table>
        </div>

        <div class="pagination-bar">
          <div class="pagination-info" id="paginationInfo"></div>
          <div class="page-btns" id="paginationBtns"></div>
        </div>
      </div>

    </div><!-- /col -->


    <!-- Quick Actions -->
    <div class="col-xl-3">
      <div class="quick-panel">
        <div class="section-header" style="margin-bottom:16px">
          <div class="section-title">
            <div class="title-icon ti-blue"><i class="bi bi-lightning-fill"></i></div>
            Quick Actions
          </div>
        </div>

        <a href="admin/add_species.php" class="quick-btn">
          <div class="qb-icon" style="background:#e8f0ff;color:var(--primary)"><i class="bi bi-fish-fill"></i></div>
          Add New Species
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>
        <a href="admin/add_location.php" class="quick-btn">
          <div class="qb-icon" style="background:#e6faf4;color:var(--success)"><i class="bi bi-geo-alt-fill"></i></div>
          Add Location
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>
        <a href="admin/add_visit.php" class="quick-btn">
          <div class="qb-icon" style="background:#fff8e6;color:var(--warning)"><i class="bi bi-calendar-plus-fill"></i></div>
          Log New Visit
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>
        <a href="admin/add_sampling.php" class="quick-btn">
          <div class="qb-icon" style="background:#fff0f0;color:var(--danger)"><i class="bi bi-droplet-fill"></i></div>
          Add Sampling
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>

        <div class="divider my-3"></div>

        <a href="#" class="quick-btn" onclick="exportToExcel();return false">
          <div class="qb-icon" style="background:#e6faf4;color:#1d7a4e"><i class="bi bi-file-earmark-excel-fill"></i></div>
          Export to Excel
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>
        <a href="reports.php" class="quick-btn">
          <div class="qb-icon" style="background:#e8f0ff;color:var(--primary)"><i class="bi bi-file-earmark-bar-graph-fill"></i></div>
          View Reports
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>

        <div class="divider my-3"></div>

        <a href="logout.php" class="quick-btn" style="color:var(--danger)">
          <div class="qb-icon" style="background:#fff0f0;color:var(--danger)"><i class="bi bi-box-arrow-right"></i></div>
          Logout
          <i class="bi bi-arrow-right qb-arrow"></i>
        </a>
      </div>
    </div>

  </div><!-- /row -->

</div><!-- /content-inner -->
</main>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ═════════════════════════════════════════════════
   DATA FROM PHP / DATABASE
═════════════════════════════════════════════════ */
// All sampling records — injected by PHP from MySQL
const allRecords = <?= $jsRecords ?>;

// Chart data from DB
const dbSpeciesLabels = <?= $jsChartSpeciesLabels ?>;
const dbSpeciesData   = <?= $jsChartSpeciesData ?>;
const dbVisitLabels   = <?= $jsChartVisitLabels ?>;
const dbVisitData     = <?= $jsChartVisitData ?>;

/* ═════════════════════════════════════════════════
   TABLE STATE
═════════════════════════════════════════════════ */
let filteredRecords   = [...allRecords];
let currentPage       = 1;
const rowsPerPage     = 8;
let sortCol           = -1;
let sortAsc           = true;
let chartSpeciesInst  = null;
let chartType         = 'bar';

/* ═════════════════════════════════════════════════
   INIT
═════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  // Animate counters
  document.querySelectorAll('.stat-value[data-target]').forEach(el => {
    animateCounter(el, parseInt(el.dataset.target));
  });

  renderTable();
  buildChartSpecies();
  buildChartVisits();
  showToast('Dashboard loaded — ' + allRecords.length + ' records', 'success');
});

/* ═════════════════════════════════════════════════
   COUNTER ANIMATION
═════════════════════════════════════════════════ */
function animateCounter(el, target) {
  let val = 0;
  const step = Math.max(1, Math.floor(target / 35));
  const t = setInterval(() => {
    val = Math.min(val + step, target);
    el.textContent = val;
    if (val >= target) clearInterval(t);
  }, 35);
}

/* ═════════════════════════════════════════════════
   SIDEBAR
═════════════════════════════════════════════════ */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}

/* ═════════════════════════════════════════════════
   CHART 1 — Species per Month (DB data)
═════════════════════════════════════════════════ */
function buildChartSpecies() {
  if (chartSpeciesInst) { chartSpeciesInst.destroy(); }

  const ctx  = document.getElementById('chartSpecies').getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 280);
  grad.addColorStop(0, 'rgba(26,107,255,0.5)');
  grad.addColorStop(1, 'rgba(26,107,255,0.03)');

  const labels = dbSpeciesLabels.length ? dbSpeciesLabels : ['No Data'];
  const data   = dbSpeciesData.length   ? dbSpeciesData   : [0];

  chartSpeciesInst = new Chart(ctx, {
    type: chartType,
    data: {
      labels,
      datasets: [{
        label: 'Species Found',
        data,
        backgroundColor: chartType === 'bar' ? grad : 'rgba(26,107,255,0.1)',
        borderColor: '#1a6bff',
        borderWidth: 2,
        borderRadius: chartType === 'bar' ? 8 : 0,
        borderSkipped: false,
        fill: chartType === 'line',
        tension: 0.4,
        pointBackgroundColor: '#1a6bff',
        pointRadius: 5,
        pointHoverRadius: 7,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1a2332', titleColor: '#fff',
          bodyColor: '#94a3b8', padding: 12, cornerRadius: 10,
          displayColors: false,
          callbacks: { label: c => `  ${c.parsed.y} species` }
        }
      },
      scales: {
        x: { grid:{color:'rgba(0,0,0,0.04)'}, ticks:{color:'#7a8999',font:{family:'Plus Jakarta Sans',size:11}}, border:{color:'transparent'} },
        y: { beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{color:'#7a8999',stepSize:1,font:{family:'Plus Jakarta Sans',size:11}}, border:{color:'transparent'} }
      }
    }
  });
}

function toggleChartType() {
  chartType = chartType === 'bar' ? 'line' : 'bar';
  buildChartSpecies();
  showToast('Switched to ' + chartType + ' chart', 'info');
}

/* ═════════════════════════════════════════════════
   CHART 2 — Visits Over Time (DB data)
═════════════════════════════════════════════════ */
function buildChartVisits() {
  const ctx  = document.getElementById('chartVisits').getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 260);
  grad.addColorStop(0, 'rgba(15,186,129,0.3)');
  grad.addColorStop(1, 'rgba(15,186,129,0.02)');

  const labels = dbVisitLabels.length ? dbVisitLabels : ['No Data'];
  const data   = dbVisitData.length   ? dbVisitData   : [0];

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Visits',
        data,
        borderColor: '#0fba81',
        backgroundColor: grad,
        fill: true, tension: 0.45, borderWidth: 2.5,
        pointBackgroundColor: '#0fba81',
        pointRadius: 4, pointHoverRadius: 7,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1a2332', titleColor: '#fff',
          bodyColor: '#94a3b8', padding: 12, cornerRadius: 10,
          displayColors: false,
        }
      },
      scales: {
        x: { grid:{display:false}, ticks:{color:'#7a8999',font:{family:'Plus Jakarta Sans',size:11}}, border:{color:'transparent'} },
        y: { beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{color:'#7a8999',stepSize:1,font:{family:'Plus Jakarta Sans',size:11}}, border:{color:'transparent'} }
      }
    }
  });
}

/* ═════════════════════════════════════════════════
   TABLE RENDERING
═════════════════════════════════════════════════ */
function renderTable() {
  const tbody    = document.getElementById('tableBody');
  const total    = filteredRecords.length;
  const start    = (currentPage - 1) * rowsPerPage;
  const end      = Math.min(start + rowsPerPage, total);
  const pageData = filteredRecords.slice(start, end);

  document.getElementById('recordCount').textContent = `(${total} records)`;

  if (pageData.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6">
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p>No records match your filters.</p>
      </div></td></tr>`;
  } else {
    tbody.innerHTML = pageData.map((r, i) => {
      const w       = r.weight ? parseFloat(r.weight).toFixed(2) + ' kg' : '—';
      const status  = r.weight ? 'Logged' : 'Pending';
      const stClass = status === 'Logged' ? 'status-logged' : 'status-pending';
      const stIcon  = status === 'Logged' ? '✓ ' : '⏳ ';
      return `<tr>
        <td style="color:var(--muted-lt);font-weight:600;font-size:12px">${start + i + 1}</td>
        <td><span class="date-text">${r.visit_date}</span></td>
        <td><span class="fish-chip">🐟 ${esc(r.fish)}</span></td>
        <td><span class="loc-chip"><i class="bi bi-geo-alt" style="color:var(--muted-lt)"></i>${esc(r.location)}</span></td>
        <td><span class="weight-badge">${w}</span></td>
        <td><span class="${stClass}">${stIcon}${status}</span></td>
      </tr>`;
    }).join('');
  }
  renderPagination(total);
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ═════════════════════════════════════════════════
   PAGINATION
═════════════════════════════════════════════════ */
function renderPagination(total) {
  const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
  const s = (currentPage - 1) * rowsPerPage + 1;
  const e = Math.min(currentPage * rowsPerPage, total);

  document.getElementById('paginationInfo').innerHTML =
    `Showing <span>${total === 0 ? 0 : s}–${e}</span> of <span>${total}</span> records`;

  const btns = document.getElementById('paginationBtns');
  let html = `<button class="page-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}><i class="bi bi-chevron-left"></i></button>`;
  for (let p = 1; p <= totalPages; p++) {
    if (totalPages > 7 && p > 2 && p < totalPages - 1 && Math.abs(p - currentPage) > 1) {
      if (p === 3 || p === totalPages - 2) html += `<button class="page-btn" disabled>…</button>`;
      continue;
    }
    html += `<button class="page-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</button>`;
  }
  html += `<button class="page-btn" onclick="goPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}><i class="bi bi-chevron-right"></i></button>`;
  btns.innerHTML = html;
}

function goPage(p) {
  const max = Math.ceil(filteredRecords.length / rowsPerPage);
  if (p < 1 || p > max) return;
  currentPage = p;
  renderTable();
}

/* ═════════════════════════════════════════════════
   SEARCH & FILTER
═════════════════════════════════════════════════ */
function handleSearch() {
  const q   = document.getElementById('tableSearch').value.trim().toLowerCase();
  const mon = document.getElementById('filterMonth').value;
  const loc = document.getElementById('filterLocation').value;
  const sp  = document.getElementById('filterSpecies').value;

  filteredRecords = allRecords.filter(r => {
    const matchQ  = !q  || r.fish.toLowerCase().includes(q)
                        || r.location.toLowerCase().includes(q)
                        || r.visit_date.includes(q);
    const matchM  = !mon || r.month_name === mon;
    const matchL  = !loc || r.location   === loc;
    const matchSp = !sp  || r.fish       === sp;
    return matchQ && matchM && matchL && matchSp;
  });

  currentPage = 1;
  renderTable();
}

function applyFilters() { handleSearch(); }

function resetFilters() {
  document.getElementById('filterMonth').value    = '';
  document.getElementById('filterLocation').value = '';
  document.getElementById('filterSpecies').value  = '';
  document.getElementById('tableSearch').value    = '';
  filteredRecords = [...allRecords];
  currentPage = 1;
  renderTable();
  showToast('Filters cleared', 'info');
}

/* ═════════════════════════════════════════════════
   SORTING
═════════════════════════════════════════════════ */
const colKeys = ['id','visit_date','fish','location','weight','status'];

function sortTable(col) {
  document.querySelectorAll('.data-table thead th').forEach((h, i) => {
    h.classList.remove('sort-asc','sort-desc');
    const ic = h.querySelector('.sort-icon');
    if (ic) ic.className = 'bi bi-chevron-expand sort-icon';
  });

  if (sortCol === col) { sortAsc = !sortAsc; }
  else { sortCol = col; sortAsc = true; }

  const key = colKeys[col];
  filteredRecords.sort((a, b) => {
    let av = a[key] ?? '', bv = b[key] ?? '';
    if (key === 'weight') { av = parseFloat(av)||0; bv = parseFloat(bv)||0; }
    if (av < bv) return sortAsc ? -1 : 1;
    if (av > bv) return sortAsc ?  1 : -1;
    return 0;
  });

  const th = document.querySelectorAll('.data-table thead th')[col];
  if (th) {
    th.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');
    const ic = th.querySelector('.sort-icon');
    if (ic) ic.className = `bi bi-chevron-${sortAsc?'up':'down'} sort-icon`;
  }

  currentPage = 1;
  renderTable();
}

function resetSort() {
  sortCol = -1; sortAsc = true;
  filteredRecords = [...allRecords];
  currentPage = 1;
  renderTable();
  showToast('Sort reset', 'info');
}

/* ═════════════════════════════════════════════════
   EXPORT TO EXCEL
═════════════════════════════════════════════════ */
function exportToExcel() {
  showToast('Preparing Excel export…', 'info');
  setTimeout(() => {
    const rows = [
      ['#', 'Date', 'Month', 'Fish Species', 'Location', 'Weight (kg)', 'Status'],
      ...filteredRecords.map((r, i) => [
        i + 1,
        r.visit_date,
        r.month_name,
        r.fish,
        r.location,
        r.weight ? parseFloat(r.weight) : '',
        r.weight ? 'Logged' : 'Pending'
      ])
    ];

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{wch:5},{wch:14},{wch:12},{wch:18},{wch:24},{wch:12},{wch:10}];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sampling Records');
    XLSX.writeFile(wb, `fish_sampling_${new Date().toISOString().slice(0,10)}.xlsx`);
    showToast('Excel file downloaded!', 'success');
  }, 400);
}

/* ═════════════════════════════════════════════════
   TOAST NOTIFICATIONS
═════════════════════════════════════════════════ */
const TICONS = {
  success: 'bi-check-circle-fill',
  info:    'bi-info-circle-fill',
  warning: 'bi-exclamation-triangle-fill'
};

function showToast(msg, type = 'info') {
  const c  = document.getElementById('toastContainer');
  const el = document.createElement('div');
  el.className = `toast-msg ${type}`;
  el.innerHTML = `<i class="bi ${TICONS[type]||TICONS.info}"></i><span>${msg}</span>`;
  c.appendChild(el);
  setTimeout(() => {
    el.style.transition = 'opacity .3s';
    el.style.opacity    = '0';
    setTimeout(() => el.remove(), 300);
  }, 3000);
}
</script>
</body>
</html>