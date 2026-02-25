<?php
session_start();
include "../search/connect.php";

if($_SESSION['role']!='admin'){
    header("Location: ../login.php");
    exit();
}

$msg="";

/* =====================
   ADD SPECIES
===================== */
if(isset($_POST['add'])){
    $local = $_POST['local'];
    $sci   = $_POST['sci'];

    $stmt = $conn->prepare("INSERT INTO species(local_name,scientific_name) VALUES(?,?)");
    $stmt->bind_param("ss",$local,$sci);

    if($stmt->execute()){
        $msg="Species added ✅";
    }
}

/* =====================
   DELETE SPECIES
===================== */
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $conn->query("DELETE FROM species WHERE id=$id");
    header("Location: add_species.php");
}

/* =====================
   FETCH ALL SPECIES
===================== */
$all = $conn->query("SELECT * FROM species ORDER BY id ASC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Species</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}
.card{
    border-radius:15px;
}
</style>
</head>

<body class="p-4">

<div class="container">

<h3 class="mb-4">🐟 Add Species</h3>

<!-- ================= FORM ================= -->
<div class="card shadow p-4 mb-4">

<form method="POST" class="row g-3">

<div class="col-md-4">
<input name="local" class="form-control" placeholder="Local name" required>
</div>

<div class="col-md-4">
<input name="sci" class="form-control" placeholder="Scientific name" required>
</div>

<div class="col-md-2">
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

<h5 class="mb-3">📋 Species List</h5>

<table class="table table-bordered table-striped">

<tr>
<th>ID</th>
<th>Local Name</th>
<th>Scientific Name</th>
<th>Action</th>
</tr>

<?php while($row=$all->fetch_assoc()){ ?>
<tr>
<td><?=$row['id']?></td>
<td><?=$row['local_name']?></td>
<td><?=$row['scientific_name']?></td>
<td>
<a href="?delete=<?=$row['id']?>" 
   onclick="return confirm('Delete this species?')"
   class="btn btn-sm btn-danger">Delete</a>
</td>
</tr>
<?php } ?>

</table>

</div>

</div>
</body>
</html>