<?php
session_start();
include "search/connect.php";

$error = "";

if(isset($_POST['login'])){

    $username = $_POST['username'];
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
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — Fish Sampling</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <style>
    :root {
      --primary: #0a6e9e;
      --accent:  #00b4d8;
      --bg:      #f0f6fa;
      --border:  #d6e6f0;
      --muted:   #6b8899;
      --input-bg:#f7fbfd;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', Arial, sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse 70% 60% at 10% 20%, rgba(0,180,216,.12) 0%, transparent 65%),
        radial-gradient(ellipse 60% 70% at 90% 85%, rgba(10,110,158,.15) 0%, transparent 65%);
      pointer-events: none;
    }

    .wave-bottom {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: 180px; overflow: hidden; pointer-events: none;
    }
    .wave-bottom svg { width: 100%; height: 100%; }

    /* ── Card ── */
    .box {
      background: #fff;
      border-radius: 20px;
      width: 100%;
      max-width: 440px;
      padding: 48px 44px 40px;
      box-shadow: 0 20px 60px rgba(10,110,158,.12), 0 4px 16px rgba(0,0,0,.06);
      border: 1px solid rgba(255,255,255,.8);
      position: relative;
      z-index: 10;
      animation: slideUp .55s ease both;
      text-align: center;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Logo ── */
    .logo-circle {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0a6e9e, #00b4d8);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
      box-shadow: 0 8px 24px rgba(10,110,158,.3);
    }
    .logo-circle i { font-size: 2rem; color: #fff; }

    .box h3 {
      font-size: 1.45rem;
      font-weight: 700;
      color: #1a2b38;
      letter-spacing: -0.02em;
      margin-bottom: 6px;
    }

    .box .subtitle {
      font-size: .875rem;
      color: var(--muted);
      margin-bottom: 30px;
    }

    /* ── Error ── */
    .error {
      background: #fff0f0;
      border: 1px solid #fcc;
      color: #c0392b;
      font-size: .85rem;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
      text-align: left;
    }

    /* ── Inputs ── */
    .field-wrap {
      position: relative;
      margin-bottom: 16px;
      text-align: left;
    }

    .field-wrap .field-icon {
      position: absolute;
      left: 14px; top: 50%; transform: translateY(-50%);
      color: #aac4d4; font-size: 1rem; pointer-events: none;
      transition: color .2s;
    }

    .field-wrap:focus-within .field-icon { color: var(--primary); }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      background: var(--input-bg);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      color: #1a2b38;
      font-family: 'Inter', Arial, sans-serif;
      font-size: .95rem;
      padding: 13px 42px 13px 42px;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }

    input[type="text"]::placeholder,
    input[type="password"]::placeholder { color: #b0c8d4; }

    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: var(--primary);
      background: #fff;
      box-shadow: 0 0 0 3.5px rgba(10,110,158,.1);
    }

    .toggle-pass {
      position: absolute;
      right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none;
      color: #aac4d4; cursor: pointer;
      font-size: 1rem; padding: 4px;
      transition: color .2s;
    }
    .toggle-pass:hover { color: var(--primary); }

    /* ── Button ── */
    button[type="submit"] {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #0a6e9e, #0891b2);
      border: none;
      border-radius: 10px;
      color: #fff;
      font-family: 'Inter', Arial, sans-serif;
      font-size: .95rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 6px;
      transition: transform .15s, box-shadow .2s;
      box-shadow: 0 6px 20px rgba(10,110,158,.3);
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }

    button[type="submit"]:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 28px rgba(10,110,158,.4);
    }

    button[type="submit"]:active { transform: translateY(0); }

    /* ── Signup link ── */
    .signup-link {
      display: block;
      margin-top: 22px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
      font-size: .875rem;
      color: var(--muted);
      text-decoration: none;
      transition: color .2s;
    }

    .signup-link span { color: var(--primary); font-weight: 500; }
    .signup-link:hover span { text-decoration: underline; }

    /* ── Page footer ── */
    .page-footer {
      position: fixed;
      bottom: 18px; left: 0; right: 0;
      text-align: center;
      font-size: .75rem;
      color: rgba(100,130,150,.5);
      z-index: 10;
    }
  </style>
</head>
<body>

  <!-- Wave background -->
  <div class="wave-bottom">
    <svg viewBox="0 0 1440 180" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M0,100 C240,160 480,40 720,100 C960,160 1200,40 1440,100 L1440,180 L0,180 Z" fill="rgba(10,110,158,0.05)"/>
      <path d="M0,130 C360,80 720,170 1080,120 C1260,95 1380,140 1440,130 L1440,180 L0,180 Z" fill="rgba(0,180,216,0.06)"/>
    </svg>
  </div>

  <div class="box">

    <!-- Logo -->
    <!-- <div class="logo-circle">
      <i class="bi bi-water"></i>
    </div> -->
    <h3>Fish Sampling System</h3>
    <p class="subtitle">Sign in to access your dashboard</p>

    <!-- Error message (PHP) -->
    <?php if(!empty($error)) echo "<div class='error'><i class='bi bi-exclamation-circle'></i> $error</div>"; ?>

    <!-- FORM — method/names/button name unchanged -->
    <form method="POST">

      <div class="field-wrap">
        <input type="text" name="username" placeholder="Username" required/>
        <i class="bi bi-person field-icon"></i>
      </div>

      <div class="field-wrap">
        <input type="password" name="password" id="passwordInput" placeholder="Password" required/>
        <i class="bi bi-lock field-icon"></i>
        <button type="button" class="toggle-pass" id="togglePass" title="Show / hide password">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>

      <!-- IMPORTANT: name="login" kept exactly as original -->
      <button type="submit" name="login">
        <i class="bi bi-box-arrow-in-right"></i> Login
      </button>

    </form>

    <a href="signup.php" class="signup-link">
      Don't have an account? <span>Create new account</span>
    </a>

  </div>

  <div class="page-footer">Fish Sampling Research System &copy; 2026</div>

  <script>
    document.getElementById('togglePass').addEventListener('click', function () {
      const input = document.getElementById('passwordInput');
      const icon  = document.getElementById('eyeIcon');
      const show  = input.type === 'password';
      input.type  = show ? 'text' : 'password';
      icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  </script>

</body>
</html>