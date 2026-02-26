<?php
session_start();
include "search/connect.php";

/* ========================
   ADMIN CHECK
======================== */
if(!isset($_SESSION['role']) || $_SESSION['role']!='admin'){
    header("Location: login.php");
    exit();
}

/* ========================
   STATS
======================== */
$species   = $conn->query("SELECT COUNT(*) c FROM species")->fetch_assoc()['c'];
$locations = $conn->query("SELECT COUNT(*) c FROM locations")->fetch_assoc()['c'];
$visits    = $conn->query("SELECT COUNT(*) c FROM visits")->fetch_assoc()['c'];
$specimens = $conn->query("SELECT COUNT(*) c FROM specimens")->fetch_assoc()['c'];


/* ========================
   VISITS PER MONTH (chart 1)
======================== */
$visitData=[];

$res = $conn->query("
SELECT 
    MONTH(visit_date) AS month_num,
    MONTHNAME(visit_date) AS m,
    COUNT(*) AS c
FROM visits
GROUP BY MONTH(visit_date), MONTHNAME(visit_date)
ORDER BY month_num
");

while($r=$res->fetch_assoc()){
    $visitData[$r['m']]=$r['c'];
}


/* ========================
   SPECIES PER MONTH (chart 2)
   ⭐ MAIN REQUEST
======================== */
$speciesMonthData=[];

$res = $conn->query("
SELECT 
    MONTH(v.visit_date) AS month_num,
    MONTHNAME(v.visit_date) AS m,
    COUNT(DISTINCT s.species_id) AS c
FROM specimens s
JOIN visits v ON v.id = s.visit_id
GROUP BY MONTH(v.visit_date), MONTHNAME(v.visit_date)
ORDER BY month_num
");

while($r=$res->fetch_assoc()){
    $speciesMonthData[$r['m']]=$r['c'];
}


/* ========================
   LATEST RECORDS TABLE
======================== */
$latest=$conn->query("
SELECT v.visit_date, sp.local_name, l.location_name,
FROM specimens s
JOIN visits v ON v.id=s.visit_id
JOIN species sp ON sp.id=s.species_id
JOIN locations l ON l.id=v.location_id
ORDER BY v.visit_date ASC
LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    background:#f5f7fb;
}

.card-stat{
    border-radius:15px;
    padding:25px;
    text-align:center;
    font-weight:bold;
    font-size:20px;
}

.sidebar{
    min-height:100vh;
    background:#1e293b;
    color:white;
}

.sidebar a{
    color:white;
    text-decoration:none;
    display:block;
    padding:10px;
}

.sidebar a:hover{
    background:#334155;
}
</style>
</head>

<body>

<div class="container-fluid">
<div class="row">

<!-- ================= SIDEBAR ================= -->
<div class="col-md-2 sidebar p-3">

<h4>Admin Panel</h4>
<hr>

<p>👋 Welcome <b><?= $_SESSION['username'] ?? 'Admin' ?></b></p>

<a href="admin/add_species.php">Add Species</a>
<a href="admin/add_location.php">Add Location</a>
<a href="admin/add_visit.php">Add Visit</a>
<a href="admin/add_sampling.php">Add Sampling</a>

<hr>
<a href="logout.php" class="text-danger">Logout</a>

</div>


<!-- ================= MAIN ================= -->
<div class="col-md-10 p-4">

<h3 class="mb-4">📊 Dashboard Overview</h3>


<!-- ================= CARDS ================= -->
<div class="row g-3">

<div class="col-md-3">
<div class="card card-stat shadow">🐟<br>Species<br><?=$species?></div>
</div>

<div class="col-md-3">
<div class="card card-stat shadow">📍<br>Locations<br><?=$locations?></div>
</div>

<div class="col-md-3">
<div class="card card-stat shadow">🗓<br>Visits<br><?=$visits?></div>
</div>

<div class="col-md-3">
<div class="card card-stat shadow">🧪<br>Specimens<br><?=$specimens?></div>
</div>

</div>







<!-- ================= CHART 2 (YOUR REQUEST) ================= -->
<div class="card mt-4 shadow p-3">
<h5>Species Found Per Month</h5>
<canvas id="speciesMonthChart"></canvas>
</div>



<!-- ================= TABLE ================= -->
<div class="card mt-4 shadow p-3">

<h5>Latest Sampling Records</h5>

<table class="table table-bordered">
<tr>
<th>Date</th>
<th>Fish</th>
<th>Location</th>
<th>Weight</th>
</tr>

<?php while($row=$latest->fetch_assoc()){ ?>
<tr>
<td><?=$row['visit_date']?></td>
<td><?=$row['local_name']?></td>
<td><?=$row['location_name']?></td>
<td><?=$row['weight']?> kg</td>
</tr>
<?php } ?>

</table>

</div>

</div>
</div>
</div>



<!-- ================= SCRIPTS ================= -->
<script>
// /* Visits chart */
// const visitData = <?=json_encode($visitData)?>;

// new Chart(document.getElementById('visitChart'),{
//     type:'bar',
//     data:{
//         labels:Object.keys(visitData),
//         datasets:[{
//             label:'Visits',
//             data:Object.values(visitData)
//         }]
//     }
// });


/* Species per month chart */
const speciesMonthData = <?=json_encode($speciesMonthData)?>;

new Chart(document.getElementById('speciesMonthChart'),{
    type:'bar',
    data:{
        labels:Object.keys(speciesMonthData),
        datasets:[{
            label:'Species Found',
            data:Object.values(speciesMonthData)
        }]
    },
    options:{
        scales:{
            y:{beginAtZero:true}
        }
    }
});
</script>

</body>
</html>