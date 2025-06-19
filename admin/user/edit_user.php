<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../../config/db.php';


$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $city = filter_var($_POST['city'], FILTER_SANITIZE_STRING);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : null;

    try {
        if ($password) {
            $stmt = $conn->prepare("UPDATE _user SET FullName = ?, Email = ?, PhoneNumber = ?, City = ?, Password = ? WHERE UserID = ?");
            $stmt->execute([$full_name, $email, $phone, $city, $password, $user_id]);
        } else {
            $stmt = $conn->prepare("UPDATE _user SET FullName = ?, Email = ?, PhoneNumber = ?, City = ? WHERE UserID = ?");
            $stmt->execute([$full_name, $email, $phone, $city, $user_id]);
        }
        header("Location: manage_users.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

if ($user_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM _user WHERE UserID = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching user: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - PetCare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; height: 100vh; position: fixed; }
        .sidebar a { color: white; text-decoration: none; padding: 15px; display: block; }
        .sidebar a:hover { background: #34495e; }
        .content { margin-left: 250px; padding: 20px; width: calc(100% - 250px); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-3 text-center"><h4>PetCare Admin</h4></div>
        <a href="../dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="manage_users.php" class="active"><i class="fas fa-users me-2"></i> Manage Users</a>
        <a href="../animals/manage_animals.php"><i class="fas fa-paw me-2"></i> Manage Animals</a>
        <a href="../manage_requests.php"><i class="fas fa-clipboard-list me-2"></i> Care Requests</a>
        <a href="../guardians/manage_guardians.php"><i class="fas fa-user-shield me-2"></i> Guardian Profiles</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="content">
        <h1 class="mb-4">Edit User</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($user): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['FullName']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['PhoneNumber']); ?>">
                </div>
                <div class="mb-3">
                    <label for="city" class="form-label">City</label>
                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['City']); ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">New Password (leave blank to keep unchanged)</label>
                    <input type="password" class="form-control" id="password" name="password">
                </div>
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">User not found.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>