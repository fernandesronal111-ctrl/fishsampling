<?php
session_start();
include "../search/connect.php";

if($_SESSION['role']!='admin'){
    header("Location: ../login.php");
    exit();
}

$msg="";

/* =====================
   ADD LOCATION
===================== */
if(isset($_POST['add'])){

    $name = $_POST['location'];
    $lat  = $_POST['latitude'];
    $lng  = $_POST['longitude'];

    $stmt = $conn->prepare("INSERT INTO locations(location_name, latitude, longitude) VALUES(?,?,?)");
    $stmt->bind_param("sdd",$name,$lat,$lng);

    if($stmt->execute()){
        $msg="Location added ✅";
    }
}

/* =====================
   DELETE
===================== */
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $conn->query("DELETE FROM locations WHERE id=$id");
    header("Location: add_location.php");
}

$all = $conn->query("SELECT * FROM locations ORDER BY id ASC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Location</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{ background:#f6f8fc; }
.card{ border-radius:15px; }
</style>
</head>

<body class="p-4">

<div class="container">

<h3 class="mb-4">📍 Add Location</h3>

<!-- ================= FORM ================= -->
<div class="card shadow p-4 mb-4">

<form method="POST" class="row g-3">

<div class="col-md-3">
<input type="text" name="location" class="form-control" placeholder="Location name" required>
</div>

<div class="col-md-3">
<input type="number" step="0.00000001" name="latitude" class="form-control" placeholder="Latitude" required>
</div>

<div class="col-md-3">
<input type="number" step="0.00000001" name="longitude" class="form-control" placeholder="Longitude" required>
</div>

<div class="col-md-3">
<button name="add" class="btn btn-primary w-100">Add</button>
</div>

<div class="col-md-2">
<a href="../dashboard.php" class="btn btn-secondary w-100">Back</a>
</div>

</form>

<?php if($msg): ?>
<div class="alert alert-success mt-3"><?=$msg?></div>
<?php endif; ?>

</div>


<!-- ================= TABLE ================= -->
<div class="card shadow p-4">

<h5 class="mb-3">📋 Location List</h5>

<table class="table table-bordered table-striped">

<tr>
<th>ID</th>
<th>Name</th>
<th>Latitude</th>
<th>Longitude</th>
<th>Action</th>
</tr>

<?php while($row=$all->fetch_assoc()){ ?>
<tr>
<td><?=$row['id']?></td>
<td><?=$row['location_name']?></td>
<td><?=$row['latitude']?></td>
<td><?=$row['longitude']?></td>
<td>
<a href="?delete=<?=$row['id']?>" 
   class="btn btn-danger btn-sm"
   onclick="return confirm('Delete location?')">Delete</a>
</td>
</tr>
<?php } ?>

</table>

</div>

</div>

</body>
</html>