<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../../config/db.php';


$animal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$animal = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $breed = filter_var($_POST['breed'], FILTER_SANITIZE_STRING);
    $birth_year = intval($_POST['birth_year']);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    $species_id = intval($_POST['species_id']);
    $user_id = intval($_POST['user_id']);

    try {
        $stmt = $conn->prepare("UPDATE animal SET Name = ?, Breed = ?, BirthYear = ?, Description = ?, SpeciesID = ?, UserID = ? WHERE AnimalID = ?");
        $stmt->execute([$name, $breed, $birth_year, $description, $species_id, $user_id, $animal_id]);
        header("Location: manage_animals.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating animal: " . $e->getMessage();
    }
}

if ($animal_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM animal WHERE AnimalID = ?");
        $stmt->execute([$animal_id]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching animal: " . $e->getMessage();
    }
}

try {
    $species = $conn->query("SELECT * FROM species");
    $users = $conn->query("SELECT UserID, FullName FROM _user");
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Animal - PetCare Admin</title>
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
        <a href="../user/manage_users.php"><i class="fas fa-users me-2"></i> Manage Users</a>
        <a href="manage_animals.php" class="active"><i class="fas fa-paw me-2"></i> Manage Animals</a>
        <a href="../manage_requests.php"><i class="fas fa-clipboard-list me-2"></i> Care Requests</a>
        <a href="../guardians/manage_guardians.php"><i class="fas fa-user-shield me-2"></i> Guardian Profiles</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="content">
        <h1 class="mb-4">Edit Animal</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($animal): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($animal['Name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="breed" class="form-label">Breed</label>
                    <input type="text" class="form-control" id="breed" name="breed" value="<?php echo htmlspecialchars($animal['Breed']); ?>">
                </div>
                <div class="mb-3">
                    <label for="birth_year" class="form-label">Birth Year</label>
                    <input type="number" class="form-control" id="birth_year" name="birth_year" value="<?php echo $animal['BirthYear']; ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($animal['Description']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="species_id" class="form-label">Species</label>
                    <select class="form-control" id="species_id" name="species_id" required>
                        <?php while ($spec = $species->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $spec['SpeciesID']; ?>" <?php if ($spec['SpeciesID'] == $animal['SpeciesID']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($spec['Name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="user_id" class="form-label">Owner</label>
                    <select class="form-control" id="user_id" name="user_id" required>
                        <?php while ($user = $users->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $user['UserID']; ?>" <?php if ($user['UserID'] == $animal['UserID']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['FullName']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Animal</button>
                <a href="manage_animals.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">Animal not found.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>