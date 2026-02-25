<?php
session_start();
include "../search/connect.php";

if($_SESSION['role']!='admin'){
    header("Location: ../login.php");
    exit();
}

$msg="";

if(isset($_POST['add'])){
    $visit   = $_POST['visit'];
    $species = $_POST['species'];
    $count   = $_POST['count'];
    $weight  = $_POST['weight'];
    $month   = $_POST['month'];

    $stmt=$conn->prepare("INSERT INTO specimens(visit_id,species_id,count,weight,month) VALUES(?,?,?,?,?)");
    $stmt->bind_param("iiiis",$visit,$species,$count,$weight,$month);

    if($stmt->execute()){
        $msg="Sampling added ✅";
    }
}
?>

<!DOCTYPE html>
<html>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<head>
<title>Add Sampling</title>

<style>
body{
    font-family:Arial;
    background:#f2f2f2;
    padding:20px;
}

.box{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
    margin-bottom:20px;
}

input,select,button{
    padding:8px;
    margin:5px;
}

button{
    background:#2e86de;
    color:white;
    border:none;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th,td{
    border:1px solid #ccc;
    padding:8px;
    text-align:center;
}

th{
    background:#2e86de;
    color:white;
}

.msg{
    color:green;
    font-weight:bold;
}
</style>
</head>
<body>

<!-- ===================== -->
<!-- FORM -->
<!-- ===================== -->

<div class="box">

<h2>🐟 Add Sampling</h2>

<form method="POST">

<!-- Visit -->
<select name="visit" required>
<option value="">Select Visit</option>
<?php
$res=$conn->query("
SELECT v.id, v.visit_date, l.location_name
FROM visits v
JOIN locations l ON v.location_id=l.id
ORDER BY v.visit_date ASC
");

while($r=$res->fetch_assoc()){
 echo "<option value='{$r['id']}'>
        {$r['visit_date']} - {$r['location_name']}
      </option>";
}
?>
</select>

<!-- Species -->
<select name="species" required>
<option value="">Select Species</option>
<?php
$res=$conn->query("SELECT * FROM species ORDER BY local_name ASC");
while($r=$res->fetch_assoc()){
 echo "<option value='{$r['id']}'>{$r['local_name']}</option>";
}
?>
</select>

<input type="number" name="count" placeholder="Count" required>

<input type="number" step="0.01" name="weight" placeholder="Weight (kg)" required>

<!-- <input name="month" placeholder="Month (January)" required> -->

<select name="month" required>
    <option value="">Month</option>
    <option value="January">January</option>
    <option value="February">February</option>
    <option value="March">March</option>
    <option value="April">April</option>
    <option value="May">May</option>
    <option value="June">June</option>
    <option value="July">July</option>
    <option value="August">August</option>
    <option value="September">September</option>
    <option value="October">October</option>
    <option value="November">November</option>
    <option value="December">December</option>
</select>

<button name="add">Add Sampling</button>

<div class="col-md-2">
<a href="../dashboard.php" class="btn btn-secondary w-100">Back</a>
</div>

</form>

<p class="msg"><?=$msg?></p>

</div>


<!-- ===================== -->
<!-- TABLE -->
<!-- ===================== -->

<div class="box">

<h3>📋 Sampling Records</h3>

<table>
<tr>
<th>ID</th>
<th>Date</th>
<th>Location</th>
<th>Species</th>
<th>Count</th>
<th>Weight</th>
<th>Month</th>
</tr>

<?php
$sql="
SELECT
s.id,
v.visit_date,
l.location_name,
sp.local_name,
s.count,
s.weight,
s.month
FROM specimens s
JOIN visits v ON s.visit_id=v.id
JOIN locations l ON v.location_id=l.id
JOIN species sp ON s.species_id=sp.id
ORDER BY s.id ASC
";

$res=$conn->query($sql);

while($row=$res->fetch_assoc()){
echo "<tr>
<td>{$row['id']}</td>
<td>{$row['visit_date']}</td>
<td>{$row['location_name']}</td>
<td>{$row['local_name']}</td>
<td>{$row['count']}</td>
<td>{$row['weight']}</td>
<td>{$row['month']}</td>
</tr>";
}
?>

</table>

</div>

<!-- <a href="../dashboard.php">⬅ Back </a> -->

</body>
</html>