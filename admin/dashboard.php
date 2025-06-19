<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
require_once '../config/db.php';


try {
    $user_count = $conn->query("SELECT COUNT(*) as count FROM _user")->fetch(PDO::FETCH_ASSOC)['count'];
    $animal_count = $conn->query("SELECT COUNT(*) as count FROM animal")->fetch(PDO::FETCH_ASSOC)['count'];
    $request_count = $conn->query("SELECT COUNT(*) as count FROM carerequest")->fetch(PDO::FETCH_ASSOC)['count'];
    $guardian_count = $conn->query("SELECT COUNT(*) as count FROM guardianprofile")->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetCare Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 15px;
            display: block;
        }
        .sidebar a:hover {
            background: #34495e;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        .card-stats {
            transition: transform 0.3s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-3 text-center">
            <h4>PetCare Admin</h4>
        </div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="user/manage_users.php"><i class="fas fa-users me-2"></i> Manage Users</a>
        <a href="animals/manage_animals.php" class="active"><i class="fas fa-paw me-2"></i> Manage Animals</a>
        <a href="manage_requests.php"><i class="fas fa-clipboard-list me-2"></i> Care Requests</a>
        <a href="guardians/manage_guardians.php"><i class="fas fa-user-shield me-2"></i> Guardian Profiles</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="content">
        <h1 class="mb-4">Admin Dashboard</h1>
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card card-stats bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <h2><?php echo $user_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card card-stats bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Animals</h5>
                        <h2><?php echo $animal_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card card-stats bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Care Requests</h5>
                        <h2><?php echo $request_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card card-stats bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Guardians</h5>
                        <h2><?php echo $guardian_count; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Recent Care Requests</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT cr.RequestID, u.FullName, cr.StartDate, cr.EndDate, cr.Status 
                                                 FROM carerequest cr 
                                                 JOIN _user u ON cr.UserID = u.UserID 
                                                 ORDER BY cr.RequestID DESC LIMIT 5");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>
                                        <td>{$row['RequestID']}</td>
                                        <td>" . htmlspecialchars($row['FullName']) . "</td>
                                        <td>{$row['StartDate']}</td>
                                        <td>{$row['EndDate']}</td>
                                        <td>{$row['Status']}</td>
                                        <td><a href='manage_requests.php' class='btn btn-sm btn-primary'>View</a></td>
                                      </tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='6'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>