<?php
session_start();

/* ======================
   SECURITY CHECK
====================== */
if(!isset($_SESSION['role'])){
    header("Location: login.php");
    exit();
}

if($_SESSION['role'] != "admin"){
    echo "Access Denied ❌";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<style>
body{
    font-family: Arial;
    background:#f2f2f2;
}

.box{
    width:400px;
    margin:100px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
}

a{
    display:block;
    margin:10px 0;
    padding:10px;
    background:#2e86de;
    color:white;
    text-decoration:none;
    border-radius:6px;
    text-align:center;
}

a:hover{
    background:#1b4f72;
}

.logout{
    background:#e74c3c;
}
</style>
</head>

<body>

<div class="box">

<h2>👑 Admin Dashboard</h2>

<p>Welcome <b><?= $_SESSION['user'] ?></b></p>

<a href="admin/add_species.php">Add Species</a>
<a href="admin/add_location.php">Add Location</a>
<a href="admin/add_visit.php">Add Visit</a>
<a href="admin/add_sampling.php">Add Sampling</a>

<a class="logout" href="logout.php">Logout</a>

</div>

</body>
</html>