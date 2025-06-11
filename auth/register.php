<?php
require_once '../config/db.php';
 // adjust the path if needed

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $city = $_POST['city'];

    // Validation
    if (empty($fullname)) $errors[] = "Full Name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($city === '-- Select City --') $errors[] = "Please select a city.";

    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO _user (FullName, Email, Password, PhoneNumber, City) 
                                    VALUES (:fullname, :email, :password, :phone, :city)");

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt->execute([
                ':fullname' => $fullname,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':phone' => $phone,
                ':city' => $city
            ]);

            $success = "✅ Registration successful!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $errors[] = " Email already exists.";
            } else {
                $errors[] = " Something went wrong: " . $e->getMessage();
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<style>
    
     * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
  background-color: #f9f6f1;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

  /* height: 100vh; */
}

.container {
  position: relative;
  
}

.form-box {
  background: white;
  padding: 40px 60px 15px 60px;
  border-radius: 20px;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  width: 500px;
  text-align: left;
  position: relative;
  left: 50%;
  transform: translateX(-50%);
  margin-top: 60px;
  height: 90vh;
}

.cat-image {
  position: absolute;
  top: -50px;
  left: -50px;
  width: 100px;
  height: 100px;
  overflow: hidden;
  border-radius: 50%;
  border: 5px solid white;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.cat-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

form {
  display: flex;
  flex-direction: column;
  margin-top: 20px;
}
input{
    	max-width: 200px;
        margin-left:100px ;
}
label{
      margin-left:100px ;
}
select{
    	max-width: 200px;
        margin-left:100px ;
}
form label {
  font-size: 14px;
  margin-top: 10px;
}

form input,
form select {
  padding: 8px;
  margin-top: 5px;
  border: 1px solid #ccc;
  border-radius: 5px;
 
}

form button {
  margin-top: 20px;
  padding: 10px;
  background: #51A4B1;
  color: white;
  border: none;
  border-radius: 20px;
  cursor: pointer;
  font-weight: bold;
  max-width: 200px;
        margin-left:100px ;
}

form button:hover {
  opacity: 0.9;
}

.login-link {
  margin-top: 10px;
  font-size: 14px;
  text-align: center;
}

.login-link a {
  color: #00aaff;
  text-decoration: none;
  margin-left: 5px;
}


</style>
<body>
    <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - MarocPattes</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include("../includes/header.php"); ?>
  <div class="container">
    <div class="form-box">
      <div class="cat-image">
        <img src="assets/img/design-01jtybd1kq-1746922863.jpg" alt="Cat Image">
      </div>

     <?php if (!empty($errors)): ?>
  <div style="color:red;margin-left:100px;">
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
  <div style="color:green;margin-left:100px;">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<form method="POST" action="">

        <label>Full Name :</label>
        <input type="text" name="fullname" placeholder="Name" required>
        <label>Phone :</label>
        <input type="tel" name="phone" placeholder="Phone" required>

        <label>Email :</label>
        <input type="email" name="email" placeholder="Email" required>

        <label>Password :</label>
        <input type="password" name="password" placeholder="Password" required>

        <label>City :</label>
        <select name="city" required>
          <option>-- Select City --</option>
          <option>Agadir</option>
          <option>Casablanca</option>
          <option>Tanger</option>
          <option>Marrakech</option>
        </select>

        <button type="submit">Join MarocPattes</button>

        <p class="login-link">Already have an account?<a href="#">Log in</a></p>
      </form>
    </div>
  </div>
</body>
</html>

</body>
</html>