<?php
/* ═══════════════════════════════════════════════════════
   Fish Sampling Management System — Location Map
   Interactive map with Leaflet.js + same design system
   ─────────────────────────────────────────────────────
   IMPORTANT: Your `locations` table needs latitude &
   longitude columns. Run this SQL once if not present:

   ALTER TABLE locations
     ADD COLUMN latitude  DECIMAL(10,8) NULL,
     ADD COLUMN longitude DECIMAL(11,8) NULL,
     ADD COLUMN description TEXT NULL,
     ADD COLUMN region VARCHAR(100) NULL;

   Then update your rows with real coordinates, e.g.:
   UPDATE locations SET latitude=16.0590, longitude=73.4637 WHERE location_name='Malvan-Dandi';
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
   LOAD ALL LOCATIONS with their stats
   If lat/lng columns don't exist yet, they default NULL
   and the map shows a "no coordinates" notice per marker
══════════════════════════════════════════════════════ */
$locations = [];

/* ─────────────────────────────────────────────────────
   Detect which optional columns actually exist in the
   locations table — build the SELECT / GROUP BY safely
───────────────────────────────────────────────────── */
function colExists($conn, $table, $col) {
    $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    return ($r && $r->num_rows > 0);
}

$hasLat  = colExists($conn, 'locations', 'latitude');
$hasLng  = colExists($conn, 'locations', 'longitude');
$hasDesc = colExists($conn, 'locations', 'description');
$hasReg  = colExists($conn, 'locations', 'region');

$hasCoords = ($hasLat && $hasLng);

/* Build SELECT fragments */
$selLat  = $hasLat  ? "COALESCE(l.latitude,  0)"  : "0";
$selLng  = $hasLng  ? "COALESCE(l.longitude, 0)"  : "0";
$selDesc = $hasDesc ? "COALESCE(l.description,'')" : "''";
$selReg  = $hasReg  ? "COALESCE(l.region,    '')"  : "''";

/* Build GROUP BY — only include real columns */
$groupExtra = "";
if ($hasLat)  $groupExtra .= ", l.latitude";
if ($hasLng)  $groupExtra .= ", l.longitude";
if ($hasDesc) $groupExtra .= ", l.description";
if ($hasReg)  $groupExtra .= ", l.region";

$res = $conn->query("
    SELECT
        l.id,
        l.location_name,
        {$selLat}  AS lat,
        {$selLng}  AS lng,
        {$selDesc} AS description,
        {$selReg}  AS region,
        COUNT(DISTINCT v.id)          AS total_visits,
        COUNT(s.id)                   AS total_specimens,
        COUNT(DISTINCT s.species_id)  AS species_count,
        ROUND(AVG(s.weight), 2)       AS avg_weight,
        MAX(v.visit_date)             AS last_visit,
        MIN(v.visit_date)             AS first_visit
    FROM locations l
    LEFT JOIN visits    v ON v.location_id = l.id
    LEFT JOIN specimens s ON s.visit_id    = v.id
    GROUP BY l.id, l.location_name{$groupExtra}
    ORDER BY total_specimens DESC
");

if (!$res) {
    die("Query error: " . $conn->error);
}

while ($r = $res->fetch_assoc()) $locations[] = $r;

/* ── Summary stats ─────────────────────────────────── */
$totalLocations = count($locations);
$totalVisits    = array_sum(array_column($locations, 'total_visits'));
$totalSpecimens = array_sum(array_column($locations, 'total_specimens'));

/* ── Species per location (for sidebar list) ──────── */
$speciesByLocation = [];
$res = $conn->query("
    SELECT v.location_id, sp.local_name, COUNT(s.id) cnt
    FROM specimens s
    JOIN visits  v  ON v.id  = s.visit_id
    JOIN species sp ON sp.id = s.species_id
    GROUP BY v.location_id, sp.local_name
    ORDER BY v.location_id, cnt DESC
");
while ($r = $res->fetch_assoc()) {
    $speciesByLocation[$r['location_id']][] = $r;
}

/* ── Recent visits per location ───────────────────── */
$recentVisits = [];
$res = $conn->query("
    SELECT v.location_id, v.visit_date, COUNT(s.id) specimens
    FROM visits v
    LEFT JOIN specimens s ON s.visit_id = v.id
    GROUP BY v.id, v.location_id, v.visit_date
    ORDER BY v.visit_date DESC
    LIMIT 50
");
while ($r = $res->fetch_assoc()) {
    $recentVisits[$r['location_id']][] = $r;
}

/* ── Map centre: average of all coords ───────────── */
$validLocs = array_filter($locations, fn($l) => $l['lat'] != 0 && $l['lng'] != 0);
$mapLat = count($validLocs) > 0 ? array_sum(array_column($validLocs,'lat')) / count($validLocs) : 16.0;
$mapLng = count($validLocs) > 0 ? array_sum(array_column($validLocs,'lng')) / count($validLocs) : 73.5;
$mapZoom = count($validLocs) > 1 ? 10 : 6;

/* ── Pass to JS ────────────────────────────────── */
$jsLocations       = json_encode($locations);
$jsSpeciesByLoc    = json_encode($speciesByLocation);
$jsRecentVisits    = json_encode($recentVisits);
$jsHasCoords       = $hasCoords ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Fish Sampling — Location Map</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<!-- Leaflet CSS -->
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet"/>

<style>
/* ══════════════════════════════════════════
   DESIGN TOKENS  (same as dashboard)
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
  --map-panel:320px;
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
.topbar-btn.active-btn{background:var(--primary-lt);color:var(--primary);border-color:rgba(26,107,255,.3)}
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
   MAP LAYOUT
══════════════════════════════════════════ */
.main-content{
  margin-left:var(--sidebar-w);
  padding-top:var(--topbar-h);
  min-height:100vh;
  display:flex;
  flex-direction:column;
}

/* Stat bar */
.stat-bar{
  background:var(--surface);
  border-bottom:1px solid var(--border);
  padding:14px 28px;
  display:flex;align-items:center;gap:28px;
  flex-wrap:wrap;
  box-shadow:var(--shadow-sm);
  animation:fadeDown .4s ease both;
}
@keyframes fadeDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

.stat-pill{
  display:flex;align-items:center;gap:10px;
  padding:8px 16px;
  border-radius:99px;
  border:1px solid var(--border);
  background:var(--bg);
}
.stat-pill-icon{
  width:32px;height:32px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:14px;flex-shrink:0;
}
.sp-blue   .stat-pill-icon{background:var(--primary-lt);color:var(--primary)}
.sp-green  .stat-pill-icon{background:#e6faf4;color:var(--success)}
.sp-orange .stat-pill-icon{background:#fff8e6;color:var(--warning)}
.sp-red    .stat-pill-icon{background:#fff0f0;color:var(--danger)}
.stat-pill-num{font-size:18px;font-weight:800;line-height:1}
.sp-blue   .stat-pill-num{color:var(--primary)}
.sp-green  .stat-pill-num{color:var(--success)}
.sp-orange .stat-pill-num{color:var(--warning)}
.sp-red    .stat-pill-num{color:var(--danger)}
.stat-pill-lbl{font-size:11px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:1px}

.stat-bar-right{margin-left:auto;display:flex;gap:8px;align-items:center}

/* Main map area */
.map-area{
  flex:1;
  display:flex;
  min-height:0;
  height:calc(100vh - var(--topbar-h) - 68px);
}

/* Map container */
#map{
  flex:1;
  min-height:500px;
  z-index:1;
}

/* Side panel */
.map-panel{
  width:var(--map-panel);
  background:var(--surface);
  border-left:1px solid var(--border);
  display:flex;flex-direction:column;
  overflow:hidden;
  flex-shrink:0;
  animation:slideLeft .35s ease both;
}
@keyframes slideLeft{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}

.panel-tabs{
  display:flex;border-bottom:1px solid var(--border);flex-shrink:0;
}
.ptab{
  flex:1;padding:14px 10px;
  font-size:12px;font-weight:600;
  color:var(--muted);text-align:center;cursor:pointer;
  transition:all .18s;border-bottom:2px solid transparent;
  display:flex;align-items:center;justify-content:center;gap:5px;
}
.ptab:hover{color:var(--text);background:var(--bg)}
.ptab.active{color:var(--primary);border-bottom-color:var(--primary);background:var(--primary-lt)}
.ptab i{font-size:14px}

.panel-body{
  flex:1;overflow-y:auto;overflow-x:hidden;padding:0;
}
.panel-body::-webkit-scrollbar{width:4px}
.panel-body::-webkit-scrollbar-thumb{background:rgba(0,0,0,.1);border-radius:2px}

.panel-section{display:none}
.panel-section.active{display:block}

/* Location list */
.loc-list-item{
  padding:14px 18px;border-bottom:1px solid #f2f4f8;
  cursor:pointer;transition:background .15s;
}
.loc-list-item:hover{background:var(--bg)}
.loc-list-item.selected{background:var(--primary-lt);border-left:3px solid var(--primary)}
.loc-list-item:last-child{border-bottom:none}

.lli-name{
  font-size:13.5px;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:6px;margin-bottom:6px;
}
.lli-meta{display:flex;gap:10px;flex-wrap:wrap}
.lli-badge{
  font-size:11px;font-weight:500;
  padding:2px 8px;border-radius:99px;
}
.badge-blue  {background:var(--primary-lt);color:var(--primary)}
.badge-green {background:#e6faf4;color:var(--success)}
.badge-orange{background:#fff8e6;color:var(--warning)}

/* Detail panel */
.detail-panel{padding:18px}
.detail-header{
  background:linear-gradient(135deg,var(--primary),#4dabf7);
  border-radius:var(--r);padding:18px;margin:-18px -18px 18px;color:#fff;
}
.detail-header h4{font-size:16px;font-weight:800;margin:0 0 4px;letter-spacing:-.3px}
.detail-header p{font-size:12px;opacity:.8;margin:0;display:flex;align-items:center;gap:5px}

.detail-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
.ds-card{
  background:var(--bg);border:1px solid var(--border);
  border-radius:var(--r-sm);padding:12px;text-align:center;
}
.ds-val{font-size:22px;font-weight:800;line-height:1;margin-bottom:3px}
.ds-lbl{font-size:10px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

.detail-section-title{
  font-size:11px;font-weight:700;letter-spacing:1.2px;
  text-transform:uppercase;color:var(--muted);
  margin-bottom:10px;display:flex;align-items:center;gap:6px;
}

.species-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px}
.species-tag{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 10px;border-radius:99px;
  font-size:11.5px;font-weight:600;
  background:var(--primary-lt);color:var(--primary);
  border:1px solid rgba(26,107,255,.15);
}

.visit-log{display:flex;flex-direction:column;gap:8px}
.visit-row{
  display:flex;align-items:center;justify-content:space-between;
  background:var(--bg);border-radius:var(--r-sm);
  padding:9px 12px;font-size:12.5px;
}
.visit-date{font-family:'Fira Code',monospace;font-size:11.5px;color:var(--muted)}
.visit-cnt{
  background:var(--success);color:#fff;
  font-size:11px;font-weight:700;
  padding:2px 8px;border-radius:99px;
}

.no-location-msg{
  padding:28px 20px;text-align:center;color:var(--muted);
}
.no-location-msg i{font-size:36px;display:block;margin-bottom:10px;opacity:.3}
.no-location-msg p{font-size:13px;margin:0}

/* Map search box */
.map-search-wrap{
  position:absolute;top:80px;left:50%;transform:translateX(-50%);
  z-index:900;
  background:var(--surface);
  border-radius:var(--r);
  border:1px solid var(--border);
  box-shadow:var(--shadow-lg);
  display:flex;align-items:center;gap:8px;
  padding:10px 14px;
  min-width:300px;
}
.map-search-wrap i{color:var(--muted-lt);font-size:15px}
.map-search-input{
  border:none;outline:none;
  font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;
  color:var(--text);flex:1;background:transparent;
}
.map-search-input::placeholder{color:var(--muted-lt)}

/* Map legend */
.map-legend{
  position:absolute;bottom:24px;left:24px;
  z-index:900;
  background:var(--surface);
  border-radius:var(--r);
  border:1px solid var(--border);
  box-shadow:var(--shadow);
  padding:14px 16px;
  min-width:160px;
}
.legend-title{
  font-size:10.5px;font-weight:700;letter-spacing:1px;
  text-transform:uppercase;color:var(--muted);margin-bottom:10px;
}
.legend-item{
  display:flex;align-items:center;gap:9px;
  margin-bottom:7px;font-size:12.5px;font-weight:500;
  color:var(--text);
}
.legend-item:last-child{margin-bottom:0}
.legend-dot{
  width:13px;height:13px;border-radius:50%;flex-shrink:0;
  border:2px solid rgba(255,255,255,.8);
  box-shadow:0 1px 4px rgba(0,0,0,.2);
}

/* Layer control buttons */
.map-controls{
  position:absolute;top:80px;right:370px;
  z-index:900;
  display:flex;flex-direction:column;gap:6px;
}
.map-ctrl-btn{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r-sm);box-shadow:var(--shadow);
  padding:9px 14px;font-size:12px;font-weight:600;
  color:var(--muted);cursor:pointer;
  transition:all .18s;display:flex;align-items:center;gap:7px;
  white-space:nowrap;
}
.map-ctrl-btn:hover{background:var(--bg);color:var(--text)}
.map-ctrl-btn.active{background:var(--primary-lt);color:var(--primary);border-color:rgba(26,107,255,.3)}

/* Coords alert */
.coords-alert{
  background:#fff8e6;border:1px solid rgba(245,159,0,.3);
  border-radius:var(--r);padding:14px 18px;margin:14px;
  font-size:12.5px;color:#92400e;
  display:flex;align-items:flex-start;gap:10px;
}
.coords-alert i{font-size:18px;color:var(--warning);flex-shrink:0;margin-top:1px}

/* Buttons */
.btn-primary-c{
  background:var(--primary);color:#fff;border:none;
  border-radius:var(--r-sm);padding:9px 18px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;
  transition:all .18s;box-shadow:0 2px 8px rgba(26,107,255,.25);
}
.btn-primary-c:hover{background:var(--primary-dk);transform:translateY(-1px)}
.btn-outline-c{
  background:var(--surface);color:var(--muted);
  border:1px solid var(--border);border-radius:var(--r-sm);
  padding:9px 16px;font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;font-weight:500;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .18s;
}
.btn-outline-c:hover{background:var(--bg);color:var(--text);border-color:#ccd4e0}
.btn-sm{padding:6px 12px;font-size:12px}

/* Toast */
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

/* Sidebar overlay */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999}

/* Leaflet custom popup */
.custom-popup .leaflet-popup-content-wrapper{
  border-radius:var(--r);
  box-shadow:var(--shadow-lg);
  border:1px solid var(--border);
  padding:0;overflow:hidden;
}
.custom-popup .leaflet-popup-content{margin:0;width:240px!important}
.popup-header{
  background:linear-gradient(135deg,var(--primary),#4dabf7);
  padding:14px 16px;color:#fff;
}
.popup-header h4{font-size:14px;font-weight:800;margin:0 0 3px;letter-spacing:-.2px}
.popup-header p{font-size:11px;opacity:.8;margin:0;display:flex;align-items:center;gap:4px}
.popup-body{padding:14px 16px}
.popup-stat{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:8px;font-size:12.5px;
}
.popup-stat:last-child{margin-bottom:0}
.popup-stat-label{color:var(--muted)}
.popup-stat-val{font-weight:700;color:var(--text)}
.popup-footer{
  padding:10px 16px;border-top:1px solid var(--border);
  background:#fafbfd;text-align:center;
}
.popup-footer button{
  font-size:12px;font-weight:600;color:var(--primary);
  background:none;border:none;cursor:pointer;
  display:inline-flex;align-items:center;gap:5px;
  transition:color .15s;
}
.popup-footer button:hover{color:var(--primary-dk)}

/* Leaflet overrides */
.leaflet-control-zoom{border:1px solid var(--border)!important;border-radius:var(--r-sm)!important;overflow:hidden;box-shadow:var(--shadow)!important}
.leaflet-control-zoom a{font-family:'Plus Jakarta Sans',sans-serif!important;font-size:16px!important;color:var(--text)!important}

/* Responsive */
@media(max-width:991px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}
  .topbar{left:0}.main-content{margin-left:0}
  .sidebar-toggle{display:flex}
  .map-panel{width:280px}
  .map-controls{right:295px}
}
@media(max-width:767px){
  .map-area{flex-direction:column;height:auto}
  #map{height:55vh}
  .map-panel{width:100%;border-left:none;border-top:1px solid var(--border)}
  .map-controls{display:none}
  .map-search-wrap{min-width:220px;top:76px}
  .map-legend{display:none}
  .stat-bar{padding:12px 16px;gap:12px}
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
    <a href="reports.php"      class="nav-link-item"><i class="bi bi-file-earmark-bar-graph-fill"></i><span>Reports</span></a>
    <a href="location_map.php" class="nav-link-item active"><i class="bi bi-map-fill"></i><span>Location Map</span></a>
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
    <div class="page-title">Location Map</div>
    <div class="breadcrumb-sub">Interactive sampling site explorer</div>
  </div>
  <div class="topbar-right">
    <div class="topbar-btn" id="btnSatellite" title="Toggle Satellite View" onclick="toggleLayer()">
      <i class="bi bi-globe"></i>
    </div>
    <div class="topbar-btn" title="Fit All Markers" onclick="fitAllMarkers()">
      <i class="bi bi-arrows-fullscreen"></i>
    </div>
    <div class="topbar-btn" title="Refresh" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise"></i>
    </div>
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
<div class="main-content">

  <!-- Stat bar -->
  <div class="stat-bar">
    <div class="stat-pill sp-blue">
      <div class="stat-pill-icon"><i class="bi bi-geo-alt-fill"></i></div>
      <div>
        <div class="stat-pill-num"><?= $totalLocations ?></div>
        <div class="stat-pill-lbl">Locations</div>
      </div>
    </div>
    <div class="stat-pill sp-green">
      <div class="stat-pill-icon"><i class="bi bi-calendar-check-fill"></i></div>
      <div>
        <div class="stat-pill-num"><?= $totalVisits ?></div>
        <div class="stat-pill-lbl">Total Visits</div>
      </div>
    </div>
    <div class="stat-pill sp-orange">
      <div class="stat-pill-icon"><i class="bi bi-eyedropper-fill"></i></div>
      <div>
        <div class="stat-pill-num"><?= $totalSpecimens ?></div>
        <div class="stat-pill-lbl">Specimens</div>
      </div>
    </div>
    <?php if (!$hasCoords): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fff8e6;border-radius:99px;border:1px solid rgba(245,159,0,.3);font-size:12px;color:#92400e">
      <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i>
      <strong>Coordinates not set.</strong>&nbsp;Add lat/lng to your locations table to see markers.
    </div>
    <?php endif; ?>
    <div class="stat-bar-right">
      <button class="btn-outline-c btn-sm" onclick="fitAllMarkers()">
        <i class="bi bi-arrows-fullscreen"></i> Fit All
      </button>
      <button class="btn-primary-c btn-sm" onclick="toggleLayer()">
        <i class="bi bi-layers-fill"></i> <span id="layerLabel">Satellite</span>
      </button>
    </div>
  </div>


  <!-- Map + Panel -->
  <div class="map-area" style="position:relative">

    <!-- Leaflet Map -->
    <div id="map"></div>

    <!-- Floating search -->
    <div class="map-search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" class="map-search-input" id="mapSearch"
             placeholder="Search location…" oninput="filterLocList()" />
    </div>

    <!-- Layer control buttons -->
    <div class="map-controls">
      <div class="map-ctrl-btn active" id="ctrlStreet" onclick="setLayer('street')">
        <i class="bi bi-map"></i> Street
      </div>
      <div class="map-ctrl-btn" id="ctrlSat" onclick="setLayer('satellite')">
        <i class="bi bi-globe2"></i> Satellite
      </div>
      <div class="map-ctrl-btn" id="ctrlTopo" onclick="setLayer('topo')">
        <i class="bi bi-mountains"></i> Topo
      </div>
    </div>

    <!-- Legend -->
    <div class="map-legend">
      <div class="legend-title">Legend</div>
      <div class="legend-item"><div class="legend-dot" style="background:#1a6bff"></div>High Activity (10+)</div>
      <div class="legend-item"><div class="legend-dot" style="background:#0fba81"></div>Medium (5–9)</div>
      <div class="legend-item"><div class="legend-dot" style="background:#f59f00"></div>Low (1–4)</div>
      <div class="legend-item"><div class="legend-dot" style="background:#b0bcc8"></div>No Visits</div>
    </div>

    <!-- Side Panel -->
    <div class="map-panel" id="mapPanel">

      <div class="panel-tabs">
        <div class="ptab active" id="ptab-list" onclick="switchPTab('list',this)">
          <i class="bi bi-list-ul"></i> Locations
        </div>
        <div class="ptab" id="ptab-detail" onclick="switchPTab('detail',this)">
          <i class="bi bi-info-circle-fill"></i> Details
        </div>
      </div>

      <div class="panel-body">

        <!-- Location list -->
        <div class="panel-section active" id="ps-list">
          <?php if (!$hasCoords): ?>
          <div class="coords-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
              <strong>Coordinates missing.</strong><br>
              Run the SQL in the file header to add lat/lng columns, then update each location row with real coordinates.
            </div>
          </div>
          <?php endif; ?>
          <div id="locList">
            <?php foreach ($locations as $loc): ?>
            <div class="loc-list-item" id="lli-<?= $loc['id'] ?>"
                 onclick="selectLocation(<?= $loc['id'] ?>)">
              <div class="lli-name">
                <i class="bi bi-geo-alt-fill" style="color:var(--primary);font-size:13px"></i>
                <?= htmlspecialchars($loc['location_name']) ?>
              </div>
              <div class="lli-meta">
                <span class="lli-badge badge-blue"><?= (int)$loc['total_specimens'] ?> specimens</span>
                <span class="lli-badge badge-green"><?= (int)$loc['total_visits'] ?> visits</span>
                <span class="lli-badge badge-orange"><?= (int)$loc['species_count'] ?> spp.</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Detail view -->
        <div class="panel-section" id="ps-detail">
          <div id="detailContent">
            <div class="no-location-msg">
              <i class="bi bi-cursor-fill"></i>
              <p>Click a marker or location to view details</p>
            </div>
          </div>
        </div>

      </div><!-- /panel-body -->
    </div><!-- /map-panel -->

  </div><!-- /map-area -->
</div><!-- /main-content -->

<div class="toast-wrap" id="toastWrap"></div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ══════════════════════════════════════════
   PHP DATA → JS
══════════════════════════════════════════ */
const LOCATIONS    = <?= $jsLocations ?>;
const SPECIES_MAP  = <?= $jsSpeciesByLoc ?>;
const VISITS_MAP   = <?= $jsRecentVisits ?>;
const HAS_COORDS   = <?= $jsHasCoords ?>;
const MAP_CENTER   = [<?= round($mapLat,6) ?>, <?= round($mapLng,6) ?>];
const MAP_ZOOM     = <?= $mapZoom ?>;

/* ══════════════════════════════════════════
   MAP INIT
══════════════════════════════════════════ */
const map = L.map('map', {
  center: MAP_CENTER,
  zoom:   MAP_ZOOM,
  zoomControl: true,
});

/* Tile layers */
const layers = {
  street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom:19
  }),
  satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution:'© Esri',
    maxZoom:19
  }),
  topo: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    attribution:'© OpenTopoMap',
    maxZoom:17
  }),
};

let currentLayer = 'street';
layers.street.addTo(map);

function setLayer(name) {
  Object.values(layers).forEach(l => { if (map.hasLayer(l)) map.removeLayer(l); });
  layers[name].addTo(map);
  currentLayer = name;
  document.querySelectorAll('.map-ctrl-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('ctrl'+name.charAt(0).toUpperCase()+name.slice(1)).classList.add('active');
  document.getElementById('layerLabel').textContent = name.charAt(0).toUpperCase()+name.slice(1);
  const btn = document.getElementById('btnSatellite');
  btn.classList.toggle('active-btn', name === 'satellite');
}

function toggleLayer() {
  setLayer(currentLayer === 'street' ? 'satellite' : 'street');
}

/* ══════════════════════════════════════════
   MARKER COLOUR by activity
══════════════════════════════════════════ */
function markerColor(specimens) {
  if (specimens >= 10) return '#1a6bff';
  if (specimens >= 5)  return '#0fba81';
  if (specimens >= 1)  return '#f59f00';
  return '#b0bcc8';
}

function markerSize(specimens) {
  if (specimens >= 10) return 18;
  if (specimens >= 5)  return 15;
  if (specimens >= 1)  return 13;
  return 11;
}

function createIcon(loc) {
  const color = markerColor(loc.total_specimens);
  const size  = markerSize(loc.total_specimens);
  return L.divIcon({
    className: '',
    html: `
      <div style="
        width:${size+10}px;height:${size+10}px;
        background:${color};
        border:3px solid rgba(255,255,255,0.9);
        border-radius:50%;
        box-shadow:0 2px 10px rgba(0,0,0,0.25),0 0 0 3px ${color}44;
        display:flex;align-items:center;justify-content:center;
        font-size:${size*0.5}px;color:#fff;font-weight:800;
        font-family:'Plus Jakarta Sans',sans-serif;
        cursor:pointer;
        transition:transform .15s;
      ">${loc.total_specimens}</div>`,
    iconSize: [size+10, size+10],
    iconAnchor: [(size+10)/2, (size+10)/2],
    popupAnchor: [0, -(size+10)/2]
  });
}

/* ══════════════════════════════════════════
   BUILD MARKERS
══════════════════════════════════════════ */
const markers = {};
const markerGroup = L.featureGroup().addTo(map);

LOCATIONS.forEach(loc => {
  if (!HAS_COORDS || (loc.lat == 0 && loc.lng == 0)) return;

  const species = SPECIES_MAP[loc.id] || [];
  const visits  = VISITS_MAP[loc.id]  || [];
  const topSp   = species.slice(0,3).map(s=>s.local_name).join(', ') || 'None recorded';

  const popupHtml = `
    <div>
      <div class="popup-header">
        <h4><i class="bi bi-geo-alt-fill" style="margin-right:5px"></i>${esc(loc.location_name)}</h4>
        <p><i class="bi bi-pin-map"></i> ${esc(loc.region||'Research Site')}</p>
      </div>
      <div class="popup-body">
        <div class="popup-stat">
          <span class="popup-stat-label">Specimens</span>
          <span class="popup-stat-val" style="color:#1a6bff">${loc.total_specimens}</span>
        </div>
        <div class="popup-stat">
          <span class="popup-stat-label">Total Visits</span>
          <span class="popup-stat-val" style="color:#0fba81">${loc.total_visits}</span>
        </div>
        <div class="popup-stat">
          <span class="popup-stat-label">Species Found</span>
          <span class="popup-stat-val" style="color:#f59f00">${loc.species_count}</span>
        </div>
        <div class="popup-stat">
          <span class="popup-stat-label">Avg Weight</span>
          <span class="popup-stat-val">${loc.avg_weight ? loc.avg_weight+' kg' : '—'}</span>
        </div>
        <div class="popup-stat">
          <span class="popup-stat-label">Last Visit</span>
          <span class="popup-stat-val" style="font-family:'Fira Code',monospace;font-size:11px">${loc.last_visit||'—'}</span>
        </div>
        <div style="margin-top:10px;font-size:11.5px;color:#7a8999">
          <strong style="color:#1a2332">Top Species:</strong> ${esc(topSp)}
        </div>
      </div>
      <div class="popup-footer">
        <button onclick="selectLocation(${loc.id})">
          <i class="bi bi-info-circle-fill"></i> View Full Details
        </button>
      </div>
    </div>`;

  const marker = L.marker([loc.lat, loc.lng], { icon: createIcon(loc) })
    .bindPopup(popupHtml, { className:'custom-popup', maxWidth:260, minWidth:240 })
    .addTo(markerGroup);

  marker.on('click', () => {
    selectLocation(loc.id);
  });

  markers[loc.id] = marker;
});

/* Fit map to all markers if we have any */
if (Object.keys(markers).length > 0) {
  setTimeout(() => {
    try { map.fitBounds(markerGroup.getBounds().pad(0.15)); }
    catch(e) {}
  }, 300);
}

function fitAllMarkers() {
  if (Object.keys(markers).length === 0) {
    toast('No markers with coordinates found', 'warning');
    return;
  }
  try { map.fitBounds(markerGroup.getBounds().pad(0.15)); }
  catch(e) {}
  toast('Fitted to all locations', 'info');
}

/* ══════════════════════════════════════════
   SELECT LOCATION  (list click or marker click)
══════════════════════════════════════════ */
function selectLocation(id) {
  const loc     = LOCATIONS.find(l => l.id == id);
  if (!loc) return;

  const species = SPECIES_MAP[id] || [];
  const visits  = (VISITS_MAP[id]  || []).slice(0, 5);

  /* highlight list item */
  document.querySelectorAll('.loc-list-item').forEach(el => el.classList.remove('selected'));
  const lli = document.getElementById('lli-'+id);
  if (lli) { lli.classList.add('selected'); lli.scrollIntoView({behavior:'smooth',block:'nearest'}); }

  /* zoom to marker */
  if (markers[id]) {
    map.setView([loc.lat, loc.lng], 14, {animate:true});
    markers[id].openPopup();
  }

  /* build detail HTML */
  const speciesTagsHtml = species.length
    ? species.map(s => `<span class="species-tag">🐟 ${esc(s.local_name)} <span style="opacity:.6;font-size:10px">(${s.cnt})</span></span>`).join('')
    : '<span style="color:var(--muted);font-size:12.5px">No species recorded</span>';

  const visitsHtml = visits.length
    ? visits.map(v => `
        <div class="visit-row">
          <span class="visit-date">${v.visit_date}</span>
          <span class="visit-cnt">${v.specimens} spec.</span>
        </div>`).join('')
    : '<div style="color:var(--muted);font-size:12.5px;padding:10px 0">No visits recorded</div>';

  const coordsHtml = (loc.lat != 0 && loc.lng != 0)
    ? `<div style="font-family:'Fira Code',monospace;font-size:11px;color:var(--muted);margin-top:8px">
         📍 ${parseFloat(loc.lat).toFixed(6)}, ${parseFloat(loc.lng).toFixed(6)}
       </div>`
    : '<div style="font-size:11.5px;color:var(--warning);margin-top:6px">⚠ Coordinates not set</div>';

  document.getElementById('detailContent').innerHTML = `
    <div class="detail-panel">
      <div class="detail-header">
        <h4><i class="bi bi-geo-alt-fill" style="margin-right:6px"></i>${esc(loc.location_name)}</h4>
        <p><i class="bi bi-pin-map"></i> ${esc(loc.region || 'Research Site')}</p>
        ${coordsHtml}
      </div>

      <div class="detail-stat-grid">
        <div class="ds-card">
          <div class="ds-val" style="color:var(--primary)">${loc.total_specimens}</div>
          <div class="ds-lbl">Specimens</div>
        </div>
        <div class="ds-card">
          <div class="ds-val" style="color:var(--success)">${loc.total_visits}</div>
          <div class="ds-lbl">Visits</div>
        </div>
        <div class="ds-card">
          <div class="ds-val" style="color:var(--warning)">${loc.species_count}</div>
          <div class="ds-lbl">Species</div>
        </div>
        <div class="ds-card">
          <div class="ds-val" style="color:var(--danger);font-size:18px">${loc.avg_weight ? loc.avg_weight+' kg' : '—'}</div>
          <div class="ds-lbl">Avg Weight</div>
        </div>
      </div>

      <div class="detail-section-title"><i class="bi bi-fish-fill" style="color:var(--primary)"></i> Species Found</div>
      <div class="species-tags">${speciesTagsHtml}</div>

      <div class="detail-section-title"><i class="bi bi-calendar3" style="color:var(--success)"></i> Recent Visits</div>
      <div class="visit-log">${visitsHtml}</div>

      ${loc.description ? `<div style="margin-top:16px;font-size:12.5px;color:var(--muted);line-height:1.5">${esc(loc.description)}</div>` : ''}

      <div style="margin-top:18px;display:flex;gap:8px">
        ${markers[id]
          ? `<button class="btn-primary-c btn-sm" onclick="map.setView([${loc.lat},${loc.lng}],15,{animate:true});markers[${id}].openPopup()">
               <i class="bi bi-zoom-in"></i> Zoom In
             </button>`
          : ''}
        <button class="btn-outline-c btn-sm" onclick="switchPTab('list',document.getElementById('ptab-list'))">
          <i class="bi bi-arrow-left"></i> Back
        </button>
      </div>
    </div>`;

  /* switch to detail tab */
  switchPTab('detail', document.getElementById('ptab-detail'));
}

/* ══════════════════════════════════════════
   PANEL TABS
══════════════════════════════════════════ */
function switchPTab(name, btn) {
  document.querySelectorAll('.ptab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.panel-section').forEach(s => s.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('ps-' + name).classList.add('active');
}

/* ══════════════════════════════════════════
   FILTER LOCATION LIST
══════════════════════════════════════════ */
function filterLocList() {
  const q = document.getElementById('mapSearch').value.trim().toLowerCase();
  document.querySelectorAll('.loc-list-item').forEach(el => {
    const name = el.querySelector('.lli-name').textContent.toLowerCase();
    el.style.display = !q || name.includes(q) ? '' : 'none';
  });
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

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

/* ── Init toast ── */
document.addEventListener('DOMContentLoaded', () => {
  const count = Object.keys(markers).length;
  if (count > 0) {
    toast(`Map loaded — ${count} location${count!==1?'s':''} plotted`, 'success');
  } else if (!HAS_COORDS) {
    toast('Add lat/lng to locations table to see markers', 'warning');
  } else {
    toast('No coordinates set for any location yet', 'warning');
  }
});
</script>
</body>
</html>