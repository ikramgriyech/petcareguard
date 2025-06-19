<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../../config/db.php';


if (isset($_GET['delete'])) {
    $animal_id = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM animal WHERE AnimalID = ?");
        $stmt->execute([$animal_id]);
        header("Location: manage_animals.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting animal: " . $e->getMessage();
    }
}

try {
    $animals = $conn->query("SELECT a.*, s.Name as SpeciesName, u.FullName as OwnerName 
                            FROM animal a 
                            JOIN species s ON a.SpeciesID = s.SpeciesID 
                            JOIN _user u ON a.UserID = u.UserID");
} catch (PDOException $e) {
    $error = "Error fetching animals: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Animals - PetCare Admin</title>
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
        <a href="manage_animals.php" class="active"><i class="fas fa-paw me-2"></i> Manage Animals</a>
        <a href="../manage_requests.php"><i class="fas fa-clipboard-list me-2"></i> Care Requests</a>
        <a href="../guardians/manage_guardians.php"><i class="fas fa-user-shield me-2"></i> Guardian Profiles</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="content">
        <h1 class="mb-4">Manage Animals</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Breed</th>
                            <th>Species</th>
                            <th>Owner</th>
                            <th>Birth Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($animal = $animals->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $animal['AnimalID']; ?></td>
                                <td><?php echo htmlspecialchars($animal['Name']); ?></td>
                                <td><?php echo htmlspecialchars($animal['Breed']); ?></td>
                                <td><?php echo htmlspecialchars($animal['SpeciesName']); ?></td>
                                <td><?php echo htmlspecialchars($animal['OwnerName']); ?></td>
                                <td><?php echo $animal['BirthYear']; ?></td>
                                <td>
                                    <a href="edit_animal.php?id=<?php echo $animal['AnimalID']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?php echo $animal['AnimalID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
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