<?php
session_start();
require_once '../config/db.php';

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
    if ($city === '' || $city === '-- Select City --') $errors[] = "Please select a city.";

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

            $success = " Registration successful!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $errors[] = "Email already exists.";
            } else {
                $errors[] = "Something went wrong: " . $e->getMessage();
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
    <link rel="stylesheet" href="../assets/css/register.css">
    <title>Register - MarocPattes</title>
    <style>

    </style>
</head>
<body>
    <?php include("../includes/header.php"); ?>
    
    <div class="main-wrapper">
        <div class="container">
            <div class="cat-image">
                <img src="../assets/img/design-01jtybd1kq-1746922863.jpg" alt="Cat with glasses">
            </div>
            
            <div class="form-content">
                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullname">Full Name :</label>
                            <input type="text" id="fullname" name="fullname" placeholder="Name" 
                                   value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email :</label>
                            <input type="email" id="email" name="email" placeholder="Email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone :</label>
                            <input type="tel" id="phone" name="phone" placeholder="Phone" 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password :</label>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="city">City :</label>
                            <select id="city" name="city" required>
                                <option value="">-- Select City --</option>
                                <option value="Agadir" <?= (($_POST['city'] ?? '') === 'Agadir') ? 'selected' : '' ?>>Agadir</option>
                                <option value="Casablanca" <?= (($_POST['city'] ?? '') === 'Casablanca') ? 'selected' : '' ?>>Casablanca</option>
                                <option value="Tanger" <?= (($_POST['city'] ?? '') === 'Tanger') ? 'selected' : '' ?>>Tanger</option>
                                <option value="Marrakech" <?= (($_POST['city'] ?? '') === 'Marrakech') ? 'selected' : '' ?>>Marrakech</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Join MarocPattes</button>
                    
                    <div class="login-link">
                        Already have an account? <a href="#">Log in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>