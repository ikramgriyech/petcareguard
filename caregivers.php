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

// Get all active caregivers with optional city filter
$city_filter = $_GET['city'] ?? '';
$sql = "
    SELECT gp.*, u.FullName, u.Email, u.PhoneNumber, u.City, 
           GROUP_CONCAT(s.Name) as SpeciesList
    FROM guardianprofile gp
    JOIN _user u ON gp.UserID = u.UserID
    LEFT JOIN can_guard cg ON gp.ProfileID = cg.ProfileID
    LEFT JOIN species s ON cg.SpeciesID = s.SpeciesID
    WHERE gp.Status = 'Active'
";

if ($city_filter) {
    $sql .= " AND u.City = ?";
}

$sql .= " GROUP BY gp.ProfileID ORDER BY u.FullName";

$stmt = $conn->prepare($sql);
if ($city_filter) {
    $stmt->execute([$city_filter]);
} else {
    $stmt->execute();
}
$caregivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle care request submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $animal_id = $_POST['animal_id'] ?? 0;
    $profile_id = $_POST['profile_id'] ?? 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $instructions = $_POST['instructions'] ?? '';
    
    if ($animal_id && $profile_id && $start_date && $end_date) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO carerequest (StartDate, EndDate, SpecialInstructions, Status, ProfileID, UserID)
                VALUES (?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->execute([$start_date, $end_date, $instructions, $profile_id, $user_id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Request submitted successfully, awaiting approval!']);
            exit();
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error submitting request: ' . $e->getMessage()]);
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Pet Caregivers</title>
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
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }



        
         
   

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #64748B;
            font-weight: 500;
        }

      

      .search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
    justify-content: center;
    margin: 20px 0;
}

.search-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.search-group label {
    margin-bottom: 5px;
    font-size: 0.9rem;
    font-weight: 500;
    color: #333;
}

.search-select {
    padding: 10px 15px;
    border: 1.5px solid #ccc;
    border-radius: 8px;
    font-size: 1rem;
    background-color: #fff;
    transition: 0.3s ease;
}

.search-select:focus {
    border-color: #64748B;
    outline: none;
    box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
}

.search-btn {
    padding: 10px 20px;
    border: none;
    background: #51A4B1;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s ease;
}

.search-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}


        .caregivers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .caregiver-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .caregiver-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            
        }

        .caregiver-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .caregiver-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 28px;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .caregiver-info {
            flex: 1;
        }

        .caregiver-name {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .caregiver-location {
            color: #64748B;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .caregiver-details {
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            color: var(--primary-color);
        }

        .bio-text {
            background: #F8FAFC;
            padding: 15px;
            border-radius: 10px;
            font-style: italic;
            color: #64748B;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }

        .species-section {
            margin-bottom: 20px;
        }

        .species-label {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .species-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .species-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #f3e6ff, #e6f3ff);
            border: 1px solid #d1c4e9;
            border-radius: 20px;
            cursor: pointer;
            color: #5e35b1;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .species-btn:hover {
            background: linear-gradient(135deg, #e1bee7, #bbdefb);
            transform: translateY(-1px);
        }

        .price-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 1px solid var(--border-color);
        }

        .price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .request-btn {
            background: var(--gradient-secondary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .request-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .request-form {
            display: none;
            background: var(--card-bg);
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .request-form.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-color);
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
            font-family: inherit;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn-cancel {
            background: #6B7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #4B5563;
        }

        .btn-submit {
            background: var(--gradient-secondary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .success-message, .error-message {
            margin-bottom: 20px;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: 500;
        }

        .success-message {
            color: var(--success-color);
            background-color: #F0FDF4;
            border: 1px solid #BBF7D0;
        }

        .error-message {
            color: var(--error-color);
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #64748B;
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .no-results-text {
            font-size: 1.2rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .caregivers-grid {
                grid-template-columns: 1fr;
            }

            .caregiver-header {
                flex-direction: column;
                text-align: center;
            }

            .search-form {
                flex-direction: column;
            }

            .search-group {
                min-width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }

            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="container">
     

        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="search-group">
                    <label for="city"> City:</label>
                    <select id="city" name="city" class="search-select">
                        <option value="">-- Select City --</option>
                        <option value="Agadir" <?= (($_GET['city'] ?? '') === 'Agadir') ? 'selected' : '' ?>>Agadir</option>
                        <option value="Casablanca" <?= (($_GET['city'] ?? '') === 'Casablanca') ? 'selected' : '' ?>>Casablanca</option>
                        <option value="Tanger" <?= (($_GET['city'] ?? '') === 'Tanger') ? 'selected' : '' ?>>Tanger</option>
                        <option value="Marrakech" <?= (($_GET['city'] ?? '') === 'Marrakech') ? 'selected' : '' ?>>Marrakech</option>
                        <option value="Rabat" <?= (($_GET['city'] ?? '') === 'Rabat') ? 'selected' : '' ?>>Rabat</option>
                        <option value="Fes" <?= (($_GET['city'] ?? '') === 'Fes') ? 'selected' : '' ?>>Fes</option>
                    </select>
                </div>
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>

        <?php if (empty($caregivers)): ?>
            <div class="no-results">
                <div class="no-results-icon"></div>
                <div class="no-results-text">No caregivers found in the selected area</div>
                <p>Try searching in a different city or check back later!</p>
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
                            <span class="detail-icon"></span>
                            <span><?php echo htmlspecialchars($caregiver['Email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-icon"></span>
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
                </div>

                <div class="request-form" id="request-form-<?php echo $caregiver['ProfileID']; ?>">
                    <div id="message-<?php echo $caregiver['ProfileID']; ?>" class="message"></div>
                    <h2 class="form-title"> Request Care from <?php echo htmlspecialchars($caregiver['FullName']); ?></h2>
                    <form id="request-form-<?php echo $caregiver['ProfileID']; ?>-form" class="care-request-form" method="POST" data-profile-id="<?php echo $caregiver['ProfileID']; ?>">
                        <input type="hidden" name="action" value="create_request">
                        <input type="hidden" name="profile_id" value="<?php echo $caregiver['ProfileID']; ?>">
                        
                        <div class="form-group">
                            <label for="animal_id-<?php echo $caregiver['ProfileID']; ?>">🐕 Select Your Pet:</label>
                            <select name="animal_id" id="animal_id-<?php echo $caregiver['ProfileID']; ?>" required>
                                <option value="">Select a pet</option>
                                <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['AnimalID']; ?>">
                                    <?php echo htmlspecialchars($pet['Name'] . ' (' . $pet['SpeciesName'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_date-<?php echo $caregiver['ProfileID']; ?>"> Start Date:</label>
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
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
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

        // Handle form submission with AJAX
        document.querySelectorAll('.care-request-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const profileId = this.getAttribute('data-profile-id');
                const messageDiv = document.getElementById(`message-${profileId}`);
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.btn-submit');
                
                // Validate dates
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
                        submitBtn.textContent = ' Submit Request';
                        submitBtn.disabled = false;
                    });
                }
            });
        });

        // Auto-hide messages after 5 seconds
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