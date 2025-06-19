<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/db.php';


if (isset($_GET['delete'])) {
    $request_id = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM carerequest WHERE RequestID = ?");
        $stmt->execute([$request_id]);
        header("Location: manage_requests.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting request: " . $e->getMessage();
    }
}

if (isset($_GET['approve'])) {
    $request_id = intval($_GET['approve']);
    try {
        $stmt = $conn->prepare("UPDATE carerequest SET Status = 'Approved' WHERE RequestID = ?");
        $stmt->execute([$request_id]);
        header("Location: manage_requests.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error approving request: " . $e->getMessage();
    }
}

if (isset($_GET['decline'])) {
    $request_id = intval($_GET['decline']);
    try {
        $stmt = $conn->prepare("UPDATE carerequest SET Status = 'Declined' WHERE RequestID = ?");
        $stmt->execute([$request_id]);
        header("Location: manage_requests.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error declining request: " . $e->getMessage();
    }
}

try {
    $requests = $conn->query("SELECT cr.*, u.FullName as UserName, gp.Bio as GuardianBio 
                            FROM carerequest cr 
                            JOIN _user u ON cr.UserID = u.UserID 
                            JOIN guardianprofile gp ON cr.ProfileID = gp.ProfileID");
} catch (PDOException $e) {
    $error = "Error fetching requests: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Care Requests - PetCare Admin</title>
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
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="user/manage_users.php"><i class="fas fa-users me-2"></i> Manage Users</a>
        <a href="animals/manage_animals.php" class="active"><i class="fas fa-paw me-2"></i> Manage Animals</a>
        <a href="manage_requests.php"><i class="fas fa-clipboard-list me-2"></i> Care Requests</a>
        <a href="guardians/manage_guardians.php"><i class="fas fa-user-shield me-2"></i> Guardian Profiles</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="content">
        <h1 class="mb-4">Manage Care Requests</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Guardian</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Instructions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $requests->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $request['RequestID']; ?></td>
                                <td><?php echo htmlspecialchars($request['UserName']); ?></td>
                                <td><?php echo htmlspecialchars($request['GuardianBio']); ?></td>
                                <td><?php echo $request['StartDate']; ?></td>
                                <td><?php echo $request['EndDate']; ?></td>
                                <td><?php echo $request['Status']; ?></td>
                                <td><?php echo htmlspecialchars($request['SpecialInstructions']); ?></td>
                                <td>
                                    <?php if ($request['Status'] == 'Pending'): ?>
                                        <a href="?approve=<?php echo $request['RequestID']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</a>
                                        <a href="?decline=<?php echo $request['RequestID']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-times"></i> Decline</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $request['RequestID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
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