<?php
session_start();
include "search/connect.php";

/* ======================
   LOGIN PROTECTION
====================== */

// not logged in → go login
if(!isset($_SESSION['role'])){
    header("Location: login.php");
    exit();
}

// if admin → go dashboard
if($_SESSION['role'] === "admin"){
    header("Location: dashboard.php");
    exit();
}

// if user → stay here (search page)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fish Sampling Search</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<style>
  :root {
    --primary:    #0a6e9e;
    --primary-lt: #e6f4fb;
    --accent:     #00b4d8;
    --bg:         #f0f6fa;
    --card:       #ffffff;
    --text:       #1a2b38;
    --muted:      #6b8899;
    --border:     #d0e6f0;
    --input-bg:   #f7fbfd;
    --th-bg:      #0a6e9e;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
    background: var(--bg);
    min-height: 100vh;
    padding: 30px 20px 60px;
    color: var(--text);
  }

  /* ── Top bar ── */
  .topbar {
    max-width: 1000px;
    margin: 0 auto 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .topbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .brand-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 14px rgba(10,110,158,.25);
    flex-shrink: 0;
  }

  .brand-icon i { font-size: 1.3rem; color: #fff; }

  .brand-text h1 {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1.2;
    letter-spacing: -0.01em;
  }

  .brand-text span {
    font-size: .75rem;
    color: var(--muted);
    font-weight: 400;
  }

  /* logout button */
  .btn-logout {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    color: var(--muted);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .85rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: border-color .2s, color .2s, box-shadow .2s;
  }

  .btn-logout:hover {
    border-color: #c0392b;
    color: #c0392b;
    box-shadow: 0 2px 10px rgba(192,57,43,.1);
  }

  /* ── Main card ── */
  .box {
    background: var(--card);
    border-radius: 18px;
    padding: 32px 36px 36px;
    box-shadow: 0 8px 40px rgba(10,110,158,.1), 0 2px 8px rgba(0,0,0,.05);
    border: 1px solid rgba(255,255,255,.9);
    max-width: 1000px;
    margin: 0 auto;
    animation: fadeUp .5s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }

  /* ── Form grid ── */
  .search-form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    align-items: end;
  }

  .field-group { display: flex; flex-direction: column; gap: 6px; }

  .field-label {
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted);
  }

  select,
  input[type="text"] {
    width: 100%;
    background: var(--input-bg);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
    font-size: .9rem;
    padding: 10px 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b8899' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
  }

  input[type="text"] {
    background-image: none;
    padding-right: 14px;
    color: var(--muted);
    cursor: default;
  }

  select:focus {
    border-color: var(--primary);
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(10,110,158,.1);
  }

  select option { color: var(--text); }

  /* Search button */
  .btn-search {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 20px;
    background: linear-gradient(135deg, var(--primary), #0891b2);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(10,110,158,.3);
    transition: transform .15s, box-shadow .2s;
    white-space: nowrap;
    align-self: end;
  }

  .btn-search:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(10,110,158,.4);
  }

  .btn-search:active { transform: translateY(0); }

  /* ── Divider ── */
  .divider {
    height: 1px;
    background: var(--border);
    margin: 28px 0;
  }

  /* ── Results ── */
  .results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 8px;
  }

  .results-count {
    font-size: .82rem;
    color: var(--muted);
    font-weight: 500;
  }

  .results-count strong { color: var(--primary); }

  .table-wrap {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border);
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: .875rem;
  }

  thead th {
    background: var(--th-bg);
    color: #fff;
    padding: 12px 16px;
    font-weight: 600;
    letter-spacing: .03em;
    white-space: nowrap;
    text-align: center;
  }

  thead th:first-child { border-radius: 0; }

  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--primary-lt); }

  tbody td {
    padding: 11px 16px;
    text-align: center;
    color: var(--text);
  }

  tbody td:nth-child(3) { font-style: italic; color: var(--muted); }

  .no-data {
    text-align: center;
    padding: 36px 20px;
    color: var(--muted);
    font-size: .9rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
  }

  .no-data i { font-size: 2.2rem; color: #c5dce8; }

  /* ── Responsive ── */
  @media(max-width: 640px) {
    .box { padding: 22px 18px 28px; }
    .search-form { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
  <div class="topbar-brand">
    
    <div class="brand-text">
      <h1>Fish Sampling System</h1>
      <span>Research Data Portal</span>
    </div>
  </div>

  <!-- Logout — kept as form POST to logout.php -->
  <form action="logout.php" method="post">
    <button type="submit" class="btn-logout">
      <i class="bi bi-box-arrow-right"></i> Logout
    </button>
  </form>
</div>

<!-- Main card -->
<div class="box">

  <div class="section-title"><i class="bi bi-search"></i> Search Records</div>

  <!-- ══ FORM — all name/id attributes unchanged ══ -->
  <form method="POST" class="search-form">

    <!-- Species -->
    <div class="field-group">
      <label class="field-label">Local Name</label>
      <select id="speciesSelect" name="species_id" required>
        <option value="">Select Fish</option>
        <?php
        $res=$conn->query("SELECT * FROM species ORDER BY local_name");
        while($r=$res->fetch_assoc()){
            echo "<option value='{$r['id']}' data-sci='{$r['scientific_name']}'>{$r['local_name']}</option>";
        }
        ?>
      </select>
    </div>

    <!-- Scientific name (readonly) -->
    <div class="field-group">
      <label class="field-label">Scientific Name</label>
      <input type="text" id="sciName" readonly placeholder="Auto-filled"/>
    </div>

    <!-- Location -->
    <div class="field-group">
      <label class="field-label">Location</label>
      <select id="locationSelect" name="location">
        <option value="">Select Location</option>
      </select>
    </div>

    <!-- Month -->
    <div class="field-group">
      <label class="field-label">Month</label>
      <select name="month" required>
        <option value="">Select Month</option>
        <?php
        $months=["January","February","March","April","May","June","July","August","September","October","November","December"];
        foreach($months as $m) echo "<option>$m</option>";
        ?>
      </select>
    </div>

    <!-- Date -->
    <div class="field-group">
      <label class="field-label">Date</label>
      <select id="dateSelect" name="date">
        <option value="">Select Date</option>
      </select>
    </div>

    <!-- Submit — name="search" kept exactly -->
    <button type="submit" name="search" class="btn-search">
      <i class="bi bi-search"></i> Search
    </button>

  </form>

  <?php if(isset($_POST['search'])): ?>
  <div class="divider"></div>

  <?php
  $where=[];

  if(!empty($_POST['species_id']))
      $where[]="s.species_id=".(int)$_POST['species_id'];

  if(!empty($_POST['location']))
      $where[]="v.location_id=".(int)$_POST['location'];

  if(!empty($_POST['month'])){
      $month=$conn->real_escape_string($_POST['month']);
      $where[]="s.month='$month'";
  }

  $condition = count($where) ? "WHERE ".implode(" AND ",$where) : "";

  $sql="
  SELECT
  v.visit_date,
  sp.local_name,
  sp.scientific_name,
  s.count,
  
  l.location_name,
  s.month
  FROM specimens s
  JOIN visits v ON s.visit_id=v.id
  JOIN species sp ON s.species_id=sp.id
  JOIN locations l ON v.location_id=l.id
  $condition
  ORDER BY v.visit_date DESC
  ";

  $result=$conn->query($sql);
  ?>

  <div class="results-header">
    <div class="section-title" style="margin-bottom:0;flex:1"><i class="bi bi-table"></i> Results</div>
    <?php if($result->num_rows): ?>
    <div class="results-count">Found <strong><?= $result->num_rows ?></strong> record(s)</div>
    <?php endif; ?>
  </div>

  <?php if($result->num_rows): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Local Name</th>
          <th>Scientific Name</th>
          <th>Count</th>
          <th>Weight</th>
          <th>Location</th>
          <th>Month</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row=$result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['visit_date'] ?></td>
          <td><?= $row['local_name'] ?></td>
          <td><?= $row['scientific_name'] ?></td>
          <td><?= $row['count'] ?></td>
          <td><?= $row['weight'] ?></td>
          <td><?= $row['location_name'] ?></td>
          <td><?= $row['month'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="no-data">
    <i class="bi bi-fish"></i>
    No records found for the selected filters.
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div><!-- .box -->

<script>
// ==========================
// Species → load locations
// ==========================
const species     = document.getElementById("speciesSelect");
const sciBox      = document.getElementById("sciName");
const locationBox = document.getElementById("locationSelect");
const monthBox    = document.querySelector("select[name='month']");
const dateBox     = document.getElementById("dateSelect");

species.addEventListener("change", function(){
    let sci = this.options[this.selectedIndex].dataset.sci;
    sciBox.value = sci || "";

    fetch("get_locations.php?species_id=" + this.value)
        .then(r => r.text())
        .then(data => {
            locationBox.innerHTML = data;
        });
});

// ==========================
// Month → load dates
// ==========================
monthBox.addEventListener("change", function(){
    if(!species.value || !locationBox.value) return;

    fetch(`get_dates.php?species=${species.value}&location=${locationBox.value}&month=${this.value}`)
        .then(r => r.text())
        .then(data => {
            dateBox.innerHTML = data;
        });
});
</script>

</body>
</html>