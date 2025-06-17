<?php
session_start();
require_once 'config/db.php'; 

$user_id = $_SESSION['user']['id'];

// Ensure user is logged in
if (!isset($user_id)) {
    header('Location: auth/login.php');
    exit();
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM _user WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's guardian profile if exists
$stmt = $conn->prepare("SELECT * FROM guardianprofile WHERE UserID = ?");
$stmt->execute([$user_id]);
$guardian_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's pets
$stmt = $conn->prepare("SELECT a.*, s.Name as SpeciesName FROM animal a LEFT JOIN species s ON a.SpeciesID = s.SpeciesID WHERE a.UserID = ?");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all species for dropdown
$stmt = $conn->query("SELECT * FROM species ORDER BY Name");
$species_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize error message for active care requests
$error_message = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                // Update user profile
                $stmt = $conn->prepare("UPDATE _user SET FullName = ?, Email = ?, PhoneNumber = ?, City = ? WHERE UserID = ?");
                $stmt->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['city'], $user_id]);
                break;
                
            case 'save_guardian':
                // Save or update guardian profile
                $bio = $_POST['bio'] ?? '';
                $price = $_POST['price'] ?? 0;
                $status = isset($_POST['active']) ? 'Active' : 'Inactive';
                
                if ($guardian_profile && is_array($guardian_profile)) {
                    $photo_path = $guardian_profile['Photo'] ?? null;

                                        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                                            $photo_name = uniqid() . '_' . basename($_FILES['photo']['name']);
                                            $target_dir = 'uploads/';
                                            $target_path = $target_dir . $photo_name;

                                            if (!is_dir($target_dir)) {
                                                mkdir($target_dir, 0755, true);
                                            }

                                            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                                                $photo_path = $target_path;
                                            }
                                        }

                    // Update existing profile
                    $stmt = $conn->prepare("UPDATE guardianprofile SET Bio = ?, PricePerNight = ?, Status = ?, Photo = ? WHERE UserID = ?");
                    $stmt->execute([$bio, $price, $status, $photo_path, $user_id]);

                    $profile_id = $guardian_profile['ProfileID'];
                } else {
                    // Create new profile
                   $stmt = $conn->prepare("INSERT INTO guardianprofile (Bio, PricePerNight, Status, Photo, UserID) VALUES (?, ?, ?, ?, ?)");
                   $stmt->execute([$bio, $price, $status, $photo_path, $user_id]);

                    $profile_id = $conn->lastInsertId();
                }
                
                // Handle species that can be guarded
                $stmt = $conn->prepare("DELETE FROM can_guard WHERE ProfileID = ?");
                $stmt->execute([$profile_id]);
                
                if (isset($_POST['species']) && is_array($_POST['species'])) {
                    foreach ($_POST['species'] as $species_id) {
                        $stmt = $conn->prepare("INSERT INTO can_guard (ProfileID, SpeciesID) VALUES (?, ?)");
                        $stmt->execute([$profile_id, $species_id]);
                    }
                }
                break;
                
            case 'add_pet':
                // Add new pet
                $name = $_POST['pet_name'] ?? '';
                $breed = $_POST['breed'] ?? '';
                $birth_year = $_POST['birth_year'] ?? null;
                $description = $_POST['description'] ?? '';
                $species_id = $_POST['species_id'] ?? null;
                
                if ($name && $species_id) {
                    $stmt = $conn->prepare("INSERT INTO animal (Name, Breed, BirthYear, Description, SpeciesID, UserID) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $breed, $birth_year, $description, $species_id, $user_id]);
                }
                break;
                
            case 'delete_pet':
                // Delete pet
                $pet_id = $_POST['pet_id'] ?? 0;
                $stmt = $conn->prepare("DELETE FROM animal WHERE AnimalID = ? AND UserID = ?");
                $stmt->execute([$pet_id, $user_id]);
                break;
                
            case 'remove_role':
                // Remove role-specific data
                $role = $_POST['role'] ?? '';
                if ($role === 'guardian' && $guardian_profile && is_array($guardian_profile)) {
                    // Check for active care requests
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM carerequest WHERE ProfileID = ? AND Status IN ('Pending', 'Approved')");
                    $stmt->execute([$guardian_profile['ProfileID']]);
                    $active_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($active_requests > 0) {
                        $error_message = "Cannot remove guardian role: You have $active_requests active care request(s). Please resolve them first.";
                    } else {
                        // Delete related carerequest records
                        $stmt = $conn->prepare("DELETE FROM carerequest WHERE ProfileID = ?");
                        $stmt->execute([$guardian_profile['ProfileID']]);
                        // Delete can_guard entries
                        $stmt = $conn->prepare("DELETE FROM can_guard WHERE ProfileID = ?");
                        $stmt->execute([$guardian_profile['ProfileID']]);
                        // Delete guardian profile
                        $stmt = $conn->prepare("DELETE FROM guardianprofile WHERE UserID = ?");
                        $stmt->execute([$user_id]);
                    }
                } elseif ($role === 'petowner') {
                    // Check for active care requests
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM carerequest WHERE UserID = ? AND Status IN ('Pending', 'Approved')");
                    $stmt->execute([$user_id]);
                    $active_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($active_requests > 0) {
                        $error_message = "Cannot remove pet owner role: You have $active_requests active care request(s). Please resolve them first.";
                    } else {
                        // Delete all pets for the user
                        $stmt = $conn->prepare("DELETE FROM animal WHERE UserID = ?");
                        $stmt->execute([$user_id]);
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission, preserving error message
        if ($error_message) {
            $_SESSION['error_message'] = $error_message;
        }
        header('Location: profile.php');
        exit();
    }
}

// Re-fetch data after potential updates
$stmt = $conn->prepare("SELECT * FROM _user WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM guardianprofile WHERE UserID = ?");
$stmt->execute([$user_id]);
$guardian_profile = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT a.*, s.Name as SpeciesName FROM animal a LEFT JOIN species s ON a.SpeciesID = s.SpeciesID WHERE a.UserID = ?");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get guardian's species capabilities
$guardian_species = [];
if ($guardian_profile && is_array($guardian_profile)) {
    $stmt = $conn->prepare("SELECT s.SpeciesID, s.Name FROM species s JOIN can_guard cg ON s.SpeciesID = cg.SpeciesID WHERE cg.ProfileID = ?");
    $stmt->execute([$guardian_profile['ProfileID']]);
    $guardian_species = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Pet Care</title>

    <style>
        :root {
            --primary-color: #4A90E2;
            --secondary-color: #50C878;
            --accent-color: #F7DC6F;
            --text-color: #2D3748;
            --bg-color: #F7FAFC;
            --card-bg: #FFFFFF;
            --border-color: #E2E8F0;
            --success-color: #48BB78;
            --warning-color: #ECC94B;
            --error-color: #F56565;
            --focus-color: #51A4B1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color:rgb(246, 246, 231);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 80rem;
            margin: 2rem auto;
            padding: 0 1.2rem;
            
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 2.75rem;
           margin: 4rem;
            padding: 3rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .profile-picture {
            width: 10rem;
            height: 10rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #51A4B1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3.5rem;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(74, 144, 226, 0.3);
        }

        .profile-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .error-message {
            color: var(--error-color);
            padding: 1rem;
            background: #FEE2E2;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--error-color);
        }

        .from-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
        }

        .form-group label {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            padding: 0;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            outline: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--focus-color);
            box-shadow: 0 0 0 4px rgba(81, 164, 177, 0.15);
            transform: translateY(-1px);
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #B8D4D8;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .form-group input[type="file"] {
            padding: 0.75rem;
            background: #F8FAFC;
            border-style: dashed;
        }

        .form-group input[type="file"]:hover {
            background: #F1F5F9;
        }

        /* Button Styles - Moved to Left */
        .form-actions-1,
        .form-actions-2,
        .form-actions-main {
            text-align: left;
            margin: 1.5rem 0;
            padding: 0;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            background: #51A4B1;
            color: white;
            box-shadow: 0 4px 12px rgba(81, 164, 177, 0.3);
            min-width: 140px;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }

        .btn:hover {
            background: #4A94A1;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(81, 164, 177, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: #51A4B1;
            box-shadow: 0 4px 12px rgba(80, 200, 120, 0.3);
        }

        .btn-secondary:hover {
            background: #38A169;
            box-shadow: 0 6px 16px rgba(80, 200, 120, 0.4);
        }

        .btn-danger {
            background: var(--error-color);
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
        }

        .btn-danger:hover {
            background: #F56565;
            box-shadow: 0 6px 16px rgba(245, 101, 101, 0.4);
            border-radius: 1rem;
           
        }

        .row {
            display: flex;
            flex-direction: row;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .row > div {
            flex: 1;
            min-width: 280px;
        }

        .role-selection {
            display: flex;
            gap: 2rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: linear-gradient(135deg, #F5EDF7, #EBF8FF);
            border-radius: 1rem;
            border: 2px solid #E2E8F0;
        }

        .role-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .role-option input[type="checkbox"] {
            width: 1.5rem;
            height: 1.5rem;
            accent-color: var(--focus-color);
        }

        .role-option label {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-color);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            border-bottom: 3px solid var(--focus-color);
            padding-bottom: 0.5rem;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .checkbox-item:hover {
            background-color: #F8FAFC;
        }

        .checkbox-item input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            accent-color: var(--focus-color);
        }

        .checkbox-item label {
            font-weight: 500;
            color: var(--text-color);
        }

        #petForm {
            background: linear-gradient(135deg, #F6F6ED, #F0F9FF);
            padding: 2rem;
            border-radius: 1.5rem;
            border: 2px solid #E2E8F0;
            margin-bottom: 2rem;
        }

        #guardianSection {
            border: 2px solid var(--focus-color);
            padding: 2rem;
            border-radius: 1.5rem;
            background: linear-gradient(135deg, #FAFAFA, #F8FAFC);
            margin-bottom: 2rem;
        }

        .species-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .species-tag {
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #EBF8FF, #DBEAFE);
            color: var(--primary-color);
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid #BFDBFE;
        }

        .pets-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .pet-card {
            background: var(--card-bg);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #E2E8F0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .pet-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .pet-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-color);
        }

        .pet-info {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            color: #64748B;
            line-height: 1.5;
        }

        .pet-info strong {
            color: var(--text-color);
            font-weight: 600;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .from-row {
                grid-template-columns: 1fr;
            }

            .role-selection {
                flex-direction: column;
                gap: 1rem;
            }

            .pets-container {
                grid-template-columns: 1fr;
            }

            .row {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-picture">
                    <?php echo strtoupper(substr($user['FullName'] ?? '', 0, 2)); ?>
                </div>
                <div>
                    <h2 class="profile-name">Profile Settings</h2>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (!empty($guardian_profile['Photo'])): ?>
                <div style="margin-bottom: 20px; text-align: center;">
                    <img src="<?php echo htmlspecialchars($guardian_profile['Photo']); ?>" alt="Profile Picture" width="150" style="border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                </div>
            <?php endif; ?>

            <!-- User Information Form -->
            <form method="POST" enctype="multipart/form-data" action="update_profile.php">
                <div class="form-group">
                    <label for="photo"> Profile Picture</label>
                    <input type="file" name="photo" id="photo" accept="image/*">
                </div>

                <div class="from-row">
                    <div>
                        <div class="form-group">
                            <label for="full_name"> Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['FullName'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" required>
                        </div>
                    </div>        
                    <div>
                        <div class="form-group">
                            <label for="phone"> Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['PhoneNumber'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="city"> City</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['City'] ?? ''); ?>">
                        </div>
                    </div>   
                </div>

                <input type="hidden" name="action" value="update_profile">

                <div class="form-actions-main">
                    <button type="submit" class="btn"> Save Profile</button>
                </div>
            </form>

            <!-- Role Selection -->
            <div class="role-selection">
                <div class="role-option">
                    <input type="checkbox" id="guardian" name="role" value="guardian" 
                           <?php echo ($guardian_profile && is_array($guardian_profile)) ? 'checked' : ''; ?> 
                           onchange="handleRoleChange('guardian')">
                    <label for="guardian">Guardian</label>
                </div>
                <div class="role-option">
                    <input type="checkbox" id="petowner" name="role" value="petowner" 
                           <?php echo count($pets) > 0 ? 'checked' : ''; ?> 
                           onchange="handleRoleChange('petowner')">
                    <label for="petowner">🐾 Pet Owner</label>
                </div>
            </div>

            <!-- Role Removal Form -->
            <form method="POST" id="roleRemovalForm" style="display: none;">
                <input type="hidden" name="action" value="remove_role">
                <input type="hidden" name="role" id="roleToRemove">
            </form>

            <!-- Guardian Profile Section -->
            <div id="guardianSection" class="<?php echo (!$guardian_profile || !is_array($guardian_profile)) ? 'hidden' : ''; ?>">
                <div class="section-title">Guardian Profile</div>
                
                <form method="POST" id="guardianForm">
                    <input type="hidden" name="action" value="save_guardian">
                    
                    <div class="form-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="active" name="active" 
                                   <?php echo ($guardian_profile && is_array($guardian_profile) && $guardian_profile['Status'] == 'Active') ? 'checked' : ''; ?>>
                            <label for="active"> Active Status</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio:</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself and your experience with pets..."><?php echo ($guardian_profile && is_array($guardian_profile)) ? htmlspecialchars($guardian_profile['Bio'] ?? '') : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price"> Price per night (MAD):</label>
                        <input type="number" id="price" name="price" step="0.01" placeholder="0.00"
                               value="<?php echo ($guardian_profile && is_array($guardian_profile)) ? htmlspecialchars($guardian_profile['PricePerNight'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Species you can take care of:</label>
                        <div class="checkbox-group">
                            <?php 
                            $guardian_species_ids = array_column($guardian_species, 'SpeciesID');
                            foreach ($species_list as $species): 
                            ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="species[]" value="<?php echo $species['SpeciesID']; ?>" 
                                       id="species_<?php echo $species['SpeciesID']; ?>"
                                       <?php echo in_array($species['SpeciesID'], $guardian_species_ids) ? 'checked' : ''; ?>>
                                <label for="species_<?php echo $species['SpeciesID']; ?>"><?php echo htmlspecialchars($species['Name']); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-actions-1">
                        <button type="submit" class="btn"> Save Guardian Profile</button>
                    </div>
                </form>

                <?php if ($guardian_profile && is_array($guardian_profile) && !empty($guardian_species)): ?>
                <div class="species-tags">
                    <?php foreach ($guardian_species as $species): ?>
                    <span class="species-tag"><?php echo htmlspecialchars($species['Name']); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pet Owner Section -->
            <div id="petownerSection" class="<?php echo count($pets) == 0 ? 'hidden' : ''; ?>">
                <div class="section-title"> My Pets</div>
                
                <!-- Add Pet Form -->
                <form method="POST" id="petForm">
                    <input type="hidden" name="action" value="add_pet">
                    
                    <div class="row">   
                        <div>
                            <div class="form-group">
                                <label for="pet_name"> Animal Name:</label>
                                <input type="text" id="pet_name" name="pet_name" placeholder="Enter your pet's name" required>
                            </div>
                            <div class="form-group">
                                <label for="species_id"> Species:</label>
                                <select id="species_id" name="species_id" required>
                                    <option value="">Select Species</option>
                                    <?php foreach ($species_list as $species): ?>
                                    <option value="<?php echo $species['SpeciesID']; ?>"><?php echo htmlspecialchars($species['Name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label for="breed">Breed:</label>
                                <input type="text" id="breed" name="breed" placeholder="Enter breed (optional)">
                            </div>
                            <div class="form-group">
                                <label for="birth_year"> Year of Birth:</label>
                                <input type="number" id="birth_year" name="birth_year" min="1990" max="<?php echo date('Y'); ?>" placeholder="<?php echo date('Y'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="3" placeholder="Tell us about your pet's personality, habits, special needs..."></textarea>
                    </div>

                    <div class="form-actions-2">
                        <button type="submit" class="btn btn-secondary">Add Pet</button>
                    </div>
                </form>

                <!-- Display Pets -->
                <?php if (!empty($pets)): ?>
                <div class="pets-container">
                    <?php foreach ($pets as $pet): ?>
                    <div class="pet-card">
                        <h3>🐾 <?php echo htmlspecialchars($pet['Name'] ?? ''); ?></h3>
                        <div class="pet-info">
                            <strong>Species:</strong> <?php echo htmlspecialchars($pet['SpeciesName'] ?? 'Unknown'); ?>
                        </div>
                        <?php if ($pet['Breed']): ?>
                        <div class="pet-info">
                            <strong>Breed:</strong> <?php echo htmlspecialchars($pet['Breed']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($pet['BirthYear']): ?>
                        <div class="pet-info">
                            <strong>Age:</strong> <?php echo date('Y') - $pet['BirthYear']; ?> years old
                        </div>
                        <?php endif; ?>
                        <?php if ($pet['Description']): ?>
                        <div class="pet-info">
                            <strong>Description:</strong> <?php echo htmlspecialchars($pet['Description']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" style="margin-top: 1rem;" onsubmit="return confirm('Are you sure you want to delete this pet?')">
                            <input type="hidden" name="action" value="delete_pet">
                            <input type="hidden" name="pet_id" value="<?php echo $pet['AnimalID']; ?>">
                            <button type="submit" class="btn btn-danger"> Delete Pet</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include('includes/footer.php'); ?> 

    <script>
        function toggleSections() {
            const guardianCheckbox = document.getElementById('guardian');
            const petownerCheckbox = document.getElementById('petowner');
            const guardianSection = document.getElementById('guardianSection');
            const petownerSection = document.getElementById('petownerSection');

            guardianSection.classList.toggle('hidden', !guardianCheckbox.checked);
            petownerSection.classList.toggle('hidden', !petownerCheckbox.checked);
        }

        function handleRoleChange(role) {
            const checkbox = document.getElementById(role);
            const form = document.getElementById('roleRemovalForm');
            const roleInput = document.getElementById('roleToRemove');

            if (!checkbox.checked) {
                if (confirm(`Are you sure you want to remove the ${role} role? This will delete all associated data.`)) {
                    roleInput.value = role;
                    form.submit();
                } else {
                    checkbox.checked = true;
                }
            } else {
                toggleSections();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleSections();
        });
    </script>
</body>
</html>