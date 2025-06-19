<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../../config/db.php';


$profile_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$guardian = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = filter_var($_POST['bio'], FILTER_SANITIZE_STRING);
    $price = floatval($_POST['price_per_night']);
    $status = $_POST['status'];
    $user_id = intval($_POST['user_id']);

    try {
        $stmt = $conn->prepare("UPDATE guardianprofile SET Bio = ?, PricePerNight = ?, Status = ?, UserID = ? WHERE ProfileID = ?");
        $stmt->execute([$bio, $price, $status, $user_id, $profile_id]);
        header("Location: manage_guardians.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating guardian: " . $e->getMessage();
    }
}

if ($profile_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM guardianprofile WHERE ProfileID = ?");
        $stmt->execute([$profile_id]);
        $guardian = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching guardian: " . $e->getMessage();
    }
}

try {
    $users = $conn->query("SELECT UserID, FullName FROM _user");
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Guardian Profile - PetCare Admin</title>
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
        <a href="../user/manage_users.php"><i class="fas fa-users me-2"></i> Manage Users</a>
        <a href="../animals/manage_animals.php" class="active"><i class="fas fa-paw me-2"></i> Manage Animals</a>
        <a href="../manage_requests.php"><i class="fas fa-clipboard-list me-2"></i> Care Requests</a>
        <a href="manage_guardians.php"><i class="fas fa-user-shield me-2"></i> Guardian Profiles</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="content">
        <h1 class="mb-4">Edit Guardian Profile</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($guardian): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="bio" class="form-label">Bio</label>
                    <textarea class="form-control" id="bio" name="bio"><?php echo htmlspecialchars($guardian['Bio']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="price_per_night" class="form-label">Price Per Night</label>
                    <input type="number" step="0.01" class="form-control" id="price_per_night" name="price_per_night" value="<?php echo $guardian['PricePerNight']; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="Active" <?php if ($guardian['Status'] == 'Active') echo 'selected'; ?>>Active</option>
                        <option value="Inactive" <?php if ($guardian['Status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="user_id" class="form-label">User</label>
                    <select class="form-control" id="user_id" name="user_id" required>
                        <?php while ($user = $users->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $user['UserID']; ?>" <?php if ($user['UserID'] == $guardian['UserID']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['FullName']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Guardian</button>
                <a href="guardians/manage_guardians.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">Guardian profile not found.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>