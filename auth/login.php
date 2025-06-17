<?php
session_start();
require_once '../config/db.php'; // Ensure path matches your project structure

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $errors[] = "Please enter your email and password.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM _user WHERE Email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['Password'])) {
                // Successful login - save session data
                $_SESSION['user'] = [
                    'id' => $user['UserID'], 
                    'fullname' => $user['FullName'],
                    'email' => $user['Email']
                ];
                header("Location: ../landing.php"); // Updated to redirect to profile.php
                exit;
            } else {
                $errors[] = "Incorrect email or password.";
            }
        } catch (PDOException $e) {
            $errors[] = "An error occurred during login: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login</title>
  <style>
    body {
      background-color: #f9faf5;
      font-family: "Roboto", sans-serif;
      height: 100vh;
      margin: 0;
    }
    .herologin {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 90vh;
    }
    .login-container {
      background-color: white;
      padding: 30px 40px;
      padding-left: 70px;
      padding-right: 70px;
      border-radius: 12px;
      box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
      width: 400px;
      text-align: center;
    }
    h2 {
      margin-bottom: 20px;
      font-family: "Roboto Serif", serif;
    }
    form {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }
    label {
      margin: 8px 0 4px;
      font-size: 14px;
      color: #333;
    }
    input {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }
    button {
      margin-top: 15px;
      width: 100%;
      background-color: #51A4B1;
      color: white;
      padding: 10px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background-color:rgb(72, 151, 164);
    }
    .register-link {
      margin-top: 15px;
      font-size: 14px;
    }
    .register-link a {
      color: #3aaeff;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <?php include("../includes/header.php"); ?>
  <section class="herologin">
    <div class="login-container">
      <h2>Log in</h2>
      <?php if (!empty($errors)): ?>
        <div style="color: red; text-align: left;">
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <form action="#" method="post">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" placeholder="Email" required />
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" placeholder="Password" required />
        <button type="submit">Log in</button>
      </form>
      <p class="register-link">Don't have an account? <a href="register.php">Register</a></p>
    </div>
  </section>
</body>
</html>