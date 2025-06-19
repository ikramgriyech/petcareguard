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
                    // Update existing profile
                    $stmt = $conn->prepare("UPDATE guardianprofile SET Bio = ?, PricePerNight = ?, Status = ? WHERE UserID = ?");
                    $stmt->execute([$bio, $price, $status, $user_id]);
                    $profile_id = $guardian_profile['ProfileID'];
                } else {
                    // Create new profile
                    $stmt = $conn->prepare("INSERT INTO guardianprofile (Bio, PricePerNight, Status, UserID) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$bio, $price, $status, $user_id]);
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
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM carerequest WHERE ProfileID = ? AND Status = 'Pending'");
                    $stmt->execute([$guardian_profile['ProfileID']]);
                    $active_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($active_requests > 0) {
                        $error_message = "Cannot remove guardian role: You have $active_requests active care request(s). Please resolve them first.";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM messages WHERE RequestID IN (SELECT RequestID FROM carerequest WHERE ProfileID = ?)");
                        $stmt->execute([$guardian_profile['ProfileID']]);
                        $stmt = $conn->prepare("DELETE FROM carerequest WHERE ProfileID = ?");
                        $stmt->execute([$guardian_profile['ProfileID']]);
                        $stmt = $conn->prepare("DELETE FROM can_guard WHERE ProfileID = ?");
                        $stmt->execute([$guardian_profile['ProfileID']]);
                        $stmt = $conn->prepare("DELETE FROM guardianprofile WHERE UserID = ?");
                        $stmt->execute([$user_id]);
                    }
                } elseif ($role === 'petowner') {
                    // Check for active care requests
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM carerequest WHERE UserID = ? AND Status = 'Pending'");
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
    <link rel="stylesheet" href="assets/css/profile.css">
    <title>Profile - Pet Care</title>

    <style>

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

            <!-- User Information Form -->
            <form method="POST">
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