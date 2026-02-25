<?php
session_start();
include "search/connect.php";

$error = "";

if(isset($_POST['login'])){

    $username = $_POST['username'];   // ← match input name
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss",$username,$password);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows == 1){

        $row = $result->fetch_assoc();

        $_SESSION['role'] = $row['role'];

        if($row['role']=="admin")
            header("Location: dashboard.php");
        else
            header("Location: index.php");

        exit();
    }
    else{
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login</title>

<style>
body{
    font-family: Arial;
    background:#f2f2f2;
}
.box{
    width:300px;
    margin:120px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
    text-align:center;
}
input{
    width:90%;
    padding:8px;
    margin:8px 0;
}
button{
    padding:8px 15px;
    background:#2e86de;
    color:white;
    border:none;
    width:100%;
}
.error{
    color:red;
}
</style>
</head>

<body>

<div class="box">
<h3>🔐 Login</h3>

<?php if(!empty($error)) echo "<p class='error'>$error</p>"; ?>

<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>

<!-- IMPORTANT -->
<button type="submit" name="login">Login</button>
</form>

<a href="signup.php">Create new account</a>

</div>

</body>
</html>