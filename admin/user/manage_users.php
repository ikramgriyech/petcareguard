<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../../config/db.php';

if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    try {
        $conn->beginTransaction();

        // Delete from messages where UserID is SenderID or ReceiverID
        $stmt = $conn->prepare("DELETE FROM messages WHERE SenderID = ? OR ReceiverID = ?");
        $stmt->execute([$user_id, $user_id]);

        // Get guardian profile ID for the user
        $stmt = $conn->prepare("SELECT ProfileID FROM guardianprofile WHERE UserID = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        $profile_id = $profile ? $profile['ProfileID'] : null;

        if ($profile_id) {
            // Delete from can_guard
            $stmt = $conn->prepare("DELETE FROM can_guard WHERE ProfileID = ?");
            $stmt->execute([$profile_id]);

            // Delete from review where ProfileID is referenced
            $stmt = $conn->prepare("DELETE FROM review WHERE ProfileID = ?");
            $stmt->execute([$profile_id]);

            // Delete from carerequest where ProfileID is referenced
            $stmt = $conn->prepare("DELETE FROM carerequest WHERE ProfileID = ?");
            $stmt->execute([$profile_id]);

            // Delete from guardianprofile
            $stmt = $conn->prepare("DELETE FROM guardianprofile WHERE ProfileID = ?");
            $stmt->execute([$profile_id]);
        }

        // Delete from review where UserID is referenced
        $stmt = $conn->prepare("DELETE FROM review WHERE UserID = ?");
        $stmt->execute([$user_id]);

        // Delete from carerequest where UserID is referenced
        $stmt = $conn->prepare("DELETE FROM carerequest WHERE UserID = ?");
        $stmt->execute([$user_id]);

        // Delete from animal
        $stmt = $conn->prepare("DELETE FROM animal WHERE UserID = ?");
        $stmt->execute([$user_id]);

        // Delete from _user
        $stmt = $conn->prepare("DELETE FROM _user WHERE UserID = ?");
        $stmt->execute([$user_id]);

        $conn->commit();
        header("Location: manage_users.php");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error deleting user: " . $e->getMessage();
    }
}

try {
    $users = $conn->query("SELECT * FROM _user");
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - PetCare Admin</title>
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
        <h1 class="mb-4">Manage Users</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $user['UserID']; ?></td>
                                <td><?php echo htmlspecialchars($user['FullName']); ?></td>
                                <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                <td><?php echo htmlspecialchars($user['PhoneNumber']); ?></td>
                                <td><?php echo htmlspecialchars($user['City']); ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $user['UserID']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?php echo $user['UserID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>