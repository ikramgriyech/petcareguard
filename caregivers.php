<?php
session_start();
require_once 'config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user']['id'])) {
    header('Location: auth/login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// Get user's pets
$stmt = $conn->prepare("SELECT a.*, s.Name as SpeciesName FROM animal a JOIN species s ON a.SpeciesID = s.SpeciesID WHERE a.UserID = ?");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique species of user's pets for automatic filtering
$pet_species = array_unique(array_column($pets, 'SpeciesName'));

// Get all active caregivers with city filter, excluding the current user's profile, and matching user's pet species
$city_filter = $_GET['city'] ?? '';
$sql = "
    SELECT gp.*, u.FullName, u.Email, u.PhoneNumber, u.City, 
           GROUP_CONCAT(s.Name) as SpeciesList
    FROM guardianprofile gp
    JOIN _user u ON gp.UserID = u.UserID
    LEFT JOIN can_guard cg ON gp.ProfileID = cg.ProfileID
    LEFT JOIN species s ON cg.SpeciesID = s.SpeciesID
    WHERE gp.Status = 'Active'
    AND gp.UserID != ?
";

$params = [$user_id];

// Add city filter
if ($city_filter) {
    $sql .= " AND u.City LIKE ?";
    $params[] = "%$city_filter%";
}

// Add automatic species filter based on user's pets
if (!empty($pet_species)) {
    $sql .= " AND s.Name IN (" . implode(',', array_fill(0, count($pet_species), '?')) . ")";
    $params = array_merge($params, $pet_species);
}

$sql .= " GROUP BY gp.ProfileID ORDER BY u.FullName";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$caregivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle care request submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $animal_ids = $_POST['animal_ids'] ?? [];
    $profile_id = $_POST['profile_id'] ?? 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $instructions = $_POST['instructions'] ?? '';
    
    if (!empty($animal_ids) && $profile_id && $start_date && $end_date) {
        try {
            $conn->beginTransaction();
            
            // Insert care request
            $stmt = $conn->prepare("
                INSERT INTO carerequest (StartDate, EndDate, SpecialInstructions, Status, ProfileID, UserID)
                VALUES (?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->execute([$start_date, $end_date, $instructions, $profile_id, $user_id]);
            $request_id = $conn->lastInsertId();
            
            // Insert animal associations
            $stmt = $conn->prepare("
                INSERT INTO carerequest_animals (RequestID, AnimalID)
                VALUES (?, ?)
            ");
            foreach ($animal_ids as $animal_id) {
                $stmt->execute([$request_id, $animal_id]);
            }
            
            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Request submitted successfully, awaiting approval!']);
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error submitting request: ' . $e->getMessage()]);
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields and select at least one pet.']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/cargivers.css">
    <title>Research Pet Caregivers</title>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="container">
        <?php if (empty($pets)): ?>
            <div class="no-pets-message">
                <div class="no-pets-icon">🐾</div>
                <div class="no-pets-text">You haven't added any pets yet</div>
                <p>To request care, please add your pets to your profile first.</p>
                <a href="profile.php" class="no-pets-link">Add Pets to Profile</a>
            </div>
        <?php else: ?>
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="search-group">
                        <label for="city">City:</label>
                        <input type="text" id="city" name="city" class="search-input" 
                               value="<?php echo htmlspecialchars($_GET['city'] ?? ''); ?>" 
                               placeholder="Enter city name">
                    </div>
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>

            <?php if (empty($caregivers)): ?>
                <div class="no-results">
                    <div class="no-results-icon">😕</div>
                    <div class="no-results-text">No caregivers found for your pets in this location</div>
                    <p>Try searching for a different city or check back later!</p>
                </div>
            <?php else: ?>
                <div class="caregivers-grid">
                    <?php foreach ($caregivers as $caregiver): ?>
                    <div class="caregiver-card">
                        <div class="caregiver-header">
                            <div class="avatar"><?php echo strtoupper(substr($caregiver['FullName'], 0, 2)); ?></div>
                            <div class="caregiver-info">
                                <div class="caregiver-name"><?php echo htmlspecialchars($caregiver['FullName']); ?></div>
                                <div class="caregiver-location"><?php echo htmlspecialchars($caregiver['City']); ?></div>
                            </div>
                        </div>

                        <div class="caregiver-details">
                            <div class="detail-row">
                                <span class="detail-icon">📧</span>
                                <span><?php echo htmlspecialchars($caregiver['Email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-icon">📞</span>
                                <span><?php echo htmlspecialchars($caregiver['PhoneNumber']); ?></span>
                            </div>
                        </div>

                        <?php if ($caregiver['Bio']): ?>
                        <div class="bio-text">
                            "<?php echo htmlspecialchars($caregiver['Bio']); ?>"
                        </div>
                        <?php endif; ?>

                        <div class="species-section">
                            <div class="species-label">Can care for:</div>
                            <div class="species-buttons">
                                <?php
                                $species = explode(',', $caregiver['SpeciesList']);
                                foreach ($species as $specie) {
                                    if (trim($specie)) {
                                        echo '<button class="species-btn" onclick="showRequestForm(' . $caregiver['ProfileID'] . ', \'' . trim($specie) . '\')">' . htmlspecialchars(trim($specie)) . '</button>';
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <div class="price-section">
                            <div class="price">
                                <?php echo htmlspecialchars($caregiver['PricePerNight'] ?? '20'); ?> MAD/night
                            </div>
                            <button class="request-btn" onclick="showRequestForm(<?php echo $caregiver['ProfileID']; ?>)">
                                Request Care
                            </button>
                        </div>

                        <div class="request-form" id="request-form-<?php echo $caregiver['ProfileID']; ?>">
                            <div id="message-<?php echo $caregiver['ProfileID']; ?>" class="message"></div>
                            <h2 class="form-title">Request Care from <?php echo htmlspecialchars($caregiver['FullName']); ?></h2>
                            <form id="request-form-<?php echo $caregiver['ProfileID']; ?>-form" class="care-request-form" method="POST" data-profile-id="<?php echo $caregiver['ProfileID']; ?>">
                                <input type="hidden" name="action" value="create_request">
                                <input type="hidden" name="profile_id" value="<?php echo $caregiver['ProfileID']; ?>">
                                
                                <div class="form-group">
                                    <label for="animal_ids-<?php echo $caregiver['ProfileID']; ?>">🐕 Select Your Pets:</label>
                                    <select name="animal_ids[]" id="animal_ids-<?php echo $caregiver['ProfileID']; ?>" multiple required>
                                        <?php foreach ($pets as $pet): ?>
                                        <option value="<?php echo $pet['AnimalID']; ?>">
                                            <?php echo htmlspecialchars($pet['Name'] . ' (' . $pet['SpeciesName'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="start_date-<?php echo $caregiver['ProfileID']; ?>">Start Date:</label>
                                    <input type="date" name="start_date" id="start_date-<?php echo $caregiver['ProfileID']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_date-<?php echo $caregiver['ProfileID']; ?>">End Date:</label>
                                    <input type="date" name="end_date" id="end_date-<?php echo $caregiver['ProfileID']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="instructions-<?php echo $caregiver['ProfileID']; ?>">Special Instructions:</label>
                                    <textarea name="instructions" id="instructions-<?php echo $caregiver['ProfileID']; ?>" rows="4" placeholder="Any special care instructions, feeding schedule, medications, etc."></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn-cancel" onclick="showRequestForm(<?php echo $caregiver['ProfileID']; ?>)">Cancel</button>
                                    <button type="submit" class="btn-submit">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include('includes/footer.php'); ?> 

    <script>
        function showRequestForm(profileId, specie = '') {
            const form = document.getElementById(`request-form-${profileId}`);
            form.classList.toggle('active');
            
            if (specie && form.classList.contains('active')) {
                const instructions = form.querySelector(`#instructions-${profileId}`);
                instructions.value = `Please provide special care for my ${specie}. `;
                instructions.focus();
            }
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'auth/login.php';
            }
        }

        document.querySelectorAll('.care-request-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const profileId = this.getAttribute('data-profile-id');
                const messageDiv = document.getElementById(`message-${profileId}`);
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.btn-submit');
                
                const startDate = new Date(formData.get('start_date'));
                const endDate = new Date(formData.get('end_date'));
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (startDate < today) {
                    messageDiv.innerHTML = '<div class="error-message">Start date cannot be in the past.</div>';
                    return;
                }
                
                if (endDate <= startDate) {
                    messageDiv.innerHTML = '<div class="error-message">End date must be after start date.</div>';
                    return;
                }

                const animalIds = formData.getAll('animal_ids[]');
                if (animalIds.length === 0) {
                    messageDiv.innerHTML = '<div class="error-message">Please select at least one pet.</div>';
                    return;
                }

                if (confirm('Are you sure you want to submit this care request?')) {
                    submitBtn.textContent = 'Submitting...';
                    submitBtn.disabled = true;
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        messageDiv.innerHTML = `<div class="${data.success ? 'success-message' : 'error-message'}">${data.message}</div>`;
                        if (data.success) {
                            this.reset();
                            setTimeout(() => {
                                messageDiv.innerHTML = '';
                                showRequestForm(profileId);
                            }, 3000);
                        }
                    })
                    .catch(error => {
                        messageDiv.innerHTML = `<div class="error-message">An error occurred: ${error.message}</div>`;
                    })
                    .finally(() => {
                        submitBtn.textContent = 'Submit Request';
                        submitBtn.disabled = false;
                    });
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelectorAll('.success-message, .error-message').forEach(msg => {
                    if (msg.parentElement.id.includes('message-')) {
                        msg.style.opacity = '0';
                        setTimeout(() => msg.remove(), 300);
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>