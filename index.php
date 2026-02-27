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
<html>
<head>
<title>Fish Sampling Search</title>

<style>
body{font-family:Arial;background:#f2f2f2;padding:20px;}
.box{background:white;padding:20px;border-radius:10px;box-shadow:0 0 10px #ccc;}
select,input,button{padding:8px;margin:5px;width: 200px;}
table{width:100%;margin-top:20px;border-collapse:collapse;}
th,td{border:1px solid #ccc;padding:8px;text-align:center;}
th{background:#2e86de;color:white;}
</style>
</head>
<body>

<div class="box">

<h2>🐟 Fish Sampling Search</h2>

<form method="POST">

<!-- Species -->
<label>Local Name:</label>
<select id="speciesSelect" name="species_id" required>
<option value="">Select Fish</option>
<?php
$res=$conn->query("SELECT * FROM species ORDER BY local_name");
while($r=$res->fetch_assoc()){
    echo "<option value='{$r['id']}' data-sci='{$r['scientific_name']}'>{$r['local_name']}</option>";
}
?>
</select>

<label>Scientific Name:</label>
<input type="text" id="sciName" readonly>

<!-- Location -->
<label>Location:</label>
<select id="locationSelect" name="location">
<option value="">Select Location</option>
</select>

<!-- Month -->
<select name="month" required>
<option value="">Month</option>
<?php
$months=["January","February","March","April","May","June","July","August","September","October","November","December"];
foreach($months as $m) echo "<option>$m</option>";
?>
</select>

<label>Date:</label>
<select id="dateSelect" name="date">
<option value="">Select Date</option>
</select>

<button type="submit" name="search">Search</button>

</form>

<?php
if(isset($_POST['search'])){

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

if($result->num_rows){
echo "<table>
<tr>
<th>Date</th><th>Local</th><th>Scientific</th><th>Count</th><th>Location</th><th>Month</th>
</tr>";

while($row=$result->fetch_assoc()){
echo "<tr>
<td>{$row['visit_date']}</td>
<td>{$row['local_name']}</td>
<td>{$row['scientific_name']}</td>
<td>{$row['count']}</td>
<td>{$row['location_name']}</td>
<td>{$row['month']}</td>
</tr>";
}
echo "</table>";
}else{
echo "No data found";
}
}
?>
<form action="logout.php" method="post">
    <button type="submit">Logout</button>
</form>
<script>

const species = document.getElementById("speciesSelect");
const sciBox = document.getElementById("sciName");
const locationBox = document.getElementById("locationSelect");

const monthBox = document.querySelector("select[name='month']");
const dateBox  = document.getElementById("dateSelect");


// ==========================
// Species → load locations
// ==========================
species.addEventListener("change", function(){

let sci=this.options[this.selectedIndex].dataset.sci;
sciBox.value=sci || "";

fetch("get_locations.php?species_id="+this.value)
.then(r=>r.text())
.then(data=>{
    locationBox.innerHTML=data;
});
});


// ==========================
// Month → load dates
// ==========================
monthBox.addEventListener("change", function(){

if(!species.value || !locationBox.value) return;

fetch(`get_dates.php?species=${species.value}&location=${locationBox.value}&month=${this.value}`)
.then(r=>r.text())
.then(data=>{
    dateBox.innerHTML = data;
});
});

</script>
</body>
</html>