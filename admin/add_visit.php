<?php
session_start();
include "../search/connect.php";

if($_SESSION['role']!='admin'){
    header("Location: ../login.php");
    exit();
}

$msg="";

if(isset($_POST['add'])){
    $date=$_POST['date'];
    $location=$_POST['location'];
    $season=$_POST['season'];

    $stmt=$conn->prepare("INSERT INTO visits(visit_date,location_id,season_id) VALUES(?,?,?)");
    $stmt->bind_param("sii",$date,$location,$season);

    if($stmt->execute()){
        $msg="Visit added ✅";
    }
}
?>

<!DOCTYPE html>
<html>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<head>
<title>Add Visit</title>

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

<div class="box">
<h2>📅 Add Visit</h2>

<form method="POST">

<input type="date" name="date" required>

<select name="location" required>
<option value="">Select Location</option>
<?php
$res=$conn->query("SELECT * FROM locations ORDER BY location_name ASC");
while($r=$res->fetch_assoc()){
 echo "<option value='{$r['id']}'>{$r['location_name']}</option>";
}
?>
</select>

<select name="season" required>
<option value="">Select Season</option>
<?php
$res=$conn->query("SELECT * FROM seasons ORDER BY season_name ASC");
while($r=$res->fetch_assoc()){
 echo "<option value='{$r['id']}'>{$r['season_name']}</option>";
}
?>
</select>

<button name="add">Add Visit</button>

<div class="col-md-2">
<a href="../dashboard.php" class="btn btn-secondary w-100">Back</a>
</div>

</form>

<p class="msg"><?=$msg?></p>
</div>


<!-- ===================== -->
<!-- VISIT TABLE -->
<!-- ===================== -->

<div class="box">
<h3>📋 Visit Records</h3>

<table>
<tr>
<th>ID</th>
<th>Date</th>
<th>Location</th>
<th>Season</th>
</tr>

<?php
$sql="
SELECT v.id, v.visit_date, l.location_name, s.season_name
FROM visits v
JOIN locations l ON v.location_id=l.id
JOIN seasons s ON v.season_id=s.id
ORDER BY v.id ASC
";

$res=$conn->query($sql);

while($row=$res->fetch_assoc()){
echo "<tr>
<td>{$row['id']}</td>
<td>{$row['visit_date']}</td>
<td>{$row['location_name']}</td>
<td>{$row['season_name']}</td>
</tr>";
}
?>

</table>

</div>

<!-- <a href="../dashboard.php">⬅ Back</a> -->

</body>
</html>