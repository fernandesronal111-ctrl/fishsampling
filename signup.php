<?php
include "search/connect.php";

$msg = "";

if($_SERVER["REQUEST_METHOD"]=="POST"){

    $name  = $_POST['name'];
    $pass  = ($_POST['password']);
    $email = $_POST['email'];

    $role = "user"; // ✅ always user (secure)

    /* check email exists */
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s",$email);
    $check->execute();
    $res = $check->get_result();

    if($res->num_rows > 0){
        $msg = "Email already registered ❌";
    }
    else{

        $stmt = $conn->prepare("INSERT INTO users(username,password,email) VALUES(?,?,?)");
        $stmt->bind_param("sss",$name,$pass,$email);

        if($stmt->execute()){
            $msg = "Account created successfully ✅ <a href='login.php'>Login Now</a>";
        }else{
            $msg = "Error creating account ❌";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Sign Up</title>

<style>
body{
    background:#f2f2f2;
    font-family:Arial;
}

.box{
    width:340px;
    margin:100px auto;
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 0 10px #ccc;
}

input{
    width:100%;
    padding:10px;
    margin:8px 0;
}

button{
    width:100%;
    padding:10px;
    background:#2e86de;
    color:white;
    border:none;
}

.msg{
    text-align:center;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="box">

<h2>📝 Sign Up</h2>

<?php if($msg) echo "<p class='msg'>$msg</p>"; ?>

<form method="POST">

<input type="text" name="name" placeholder="Full Name" required>

<input type="email" name="email" placeholder="Email" required>

<input type="password" name="password" placeholder="Password" required>

<button type="submit">Create Account</button>

</form>

<br>
<a href="login.php">Already have account? Login</a>

</div>

</body>
</html>