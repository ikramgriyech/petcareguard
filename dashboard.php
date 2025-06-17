<?php
session_start();
require_once 'config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user']['id'])) {
    header('Location: auth/login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// Get user information
$stmt = $conn->prepare("SELECT * FROM _user WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is a caregiver
$stmt = $conn->prepare("SELECT * FROM guardianprofile WHERE UserID = ?");
$stmt->execute([$user_id]);
$guardian_profile = $stmt->fetch(PDO::FETCH_ASSOC);
$is_caregiver = !empty($guardian_profile);

// Check if user is a pet owner
$stmt = $conn->prepare("SELECT COUNT(*) as pet_count FROM animal WHERE UserID = ?");
$stmt->execute([$user_id]);
$pet_count = $stmt->fetch(PDO::FETCH_ASSOC)['pet_count'];
$is_petowner = $pet_count > 0;

// Get available caregivers for pet owners
$caregivers = [];
if ($is_petowner) {
    $stmt = $conn->prepare("
        SELECT gp.*, u.FullName, u.City, u.PhoneNumber, u.Email, 
               GROUP_CONCAT(s.Name) as SpeciesList
        FROM guardianprofile gp
        JOIN _user u ON gp.UserID = u.UserID
        LEFT JOIN can_guard cg ON gp.ProfileID = cg.ProfileID
        LEFT JOIN species s ON cg.SpeciesID = s.SpeciesID
        WHERE gp.Status = 'Active'
        GROUP BY gp.ProfileID
    ");
    $stmt->execute();
    $caregivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's pets for request creation
$pets = [];
if ($is_petowner) {
    $stmt = $conn->prepare("SELECT a.*, s.Name as SpeciesName FROM animal a JOIN species s ON a.SpeciesID = s.SpeciesID WHERE a.UserID = ?");
    $stmt->execute([$user_id]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get care requests for caregivers (received only)
$received_requests = [];
if ($is_caregiver) {
    $stmt = $conn->prepare("
        SELECT cr.*, u.FullName, a.Name as PetName, s.Name as SpeciesName
        FROM carerequest cr
        JOIN _user u ON cr.UserID = u.UserID
        JOIN animal a ON a.UserID = u.UserID
        JOIN species s ON a.SpeciesID = s.SpeciesID
        WHERE cr.ProfileID = ?
    ");
    $stmt->execute([$guardian_profile['ProfileID']]);
    $received_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get request counts for caregiver dashboard
$request_counts = ['pending' => 0, 'approved' => 0, 'declined' => 0];
if ($is_caregiver) {
    $stmt = $conn->prepare("
        SELECT Status, COUNT(*) as count 
        FROM carerequest 
        WHERE ProfileID = ? 
        GROUP BY Status
    ");
    $stmt->execute([$guardian_profile['ProfileID']]);
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($counts as $count) {
        $status = strtolower($count['Status']);
        if (in_array($status, ['pending', 'approved', 'declined'])) {
            $request_counts[$status] = $count['count'];
        }
    }
}

// Handle request submission (for pet owners)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $animal_id = $_POST['animal_id'] ?? 0;
    $profile_id = $_POST['profile_id'] ?? 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $instructions = $_POST['instructions'] ?? '';
    
    if ($animal_id && $profile_id && $start_date && $end_date) {
        $stmt = $conn->prepare("
            INSERT INTO carerequest (StartDate, EndDate, SpecialInstructions, Status, ProfileID, UserID)
            VALUES (?, ?, ?, 'Pending', ?, ?)
        ");
        $stmt->execute([$start_date, $end_date, $instructions, $profile_id, $user_id]);
        header('Location: dashboard.php?success=1');
        exit();
    }
}

// Handle request status update (for caregivers)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_request') {
    $request_id = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if ($request_id && $status && in_array($status, ['Pending', 'Approved', 'Declined'])) {
        $stmt = $conn->prepare("UPDATE carerequest SET Status = ? WHERE RequestID = ? AND ProfileID = ?");
        $stmt->execute([$status, $request_id, $guardian_profile['ProfileID']]);
        header('Location: dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care Dashboard</title>
    <style>
/* Reset and Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary-color: #3b82f6;
    --secondary-color: #64748b;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --pink-color: #ec4899;
    --yellow-color: #fbbf24;
    --green-color: #10b981;
    --background-color: #f8fafc;
    --card-background: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: var(--background-color);
    color: var(--text-primary);
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* User Type Toggle */
.user-type-toggle {
    display: flex;
    justify-content: center;
    margin: 2rem auto;
    background: var(--card-background);
    border-radius: 12px;
    padding: 0.5rem;
    box-shadow: var(--shadow);
    max-width: 400px;
}

.toggle-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.toggle-btn.active {
    background: var(--primary-color);
    color: white;
    box-shadow: var(--shadow);
}

.toggle-btn:hover:not(.active) {
    background: #f1f5f9;
    color: var(--text-primary);
}

/* Dashboard Header */
.dashboard-header {
    text-align: center;
    margin: 2rem 0;
}

.dashboard-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

/* Sidebar */
.sidebar {
    background: var(--card-background);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.sidebar h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}

.sidebar-item:last-child {
    border-bottom: none;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-background);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-left: 4px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Specific stat card colors */
.stat-card:nth-child(1) {
    border-left-color: var(--pink-color);
    background: linear-gradient(135deg, #fdf2f8 0%, #ffffff 100%);
}

.stat-card:nth-child(1) .stat-number {
    color: var(--pink-color);
}

.stat-card:nth-child(2) {
    border-left-color: var(--yellow-color);
    background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
}

.stat-card:nth-child(2) .stat-number {
    color: var(--yellow-color);
}

.stat-card:nth-child(3) {
    border-left-color: var(--green-color);
    background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
}

.stat-card:nth-child(3) .stat-number {
    color: var(--green-color);
}

.stat-card:nth-child(4) {
    border-left-color: var(--primary-color);
    background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
}

.stat-card:nth-child(4) .stat-number {
    color: var(--primary-color);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.875rem;
}

/* Requests Section */
.requests-section {
    margin-bottom: 3rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

.search-bar {
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.875rem;
    min-width: 250px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-bar:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.view-all-btn {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.view-all-btn:hover {
    background-color: rgba(59, 130, 246, 0.1);
}

/* Caregiver Cards */
.caregiver-card {
    background: var(--card-background);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.caregiver-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.caregiver-info {
    flex: 1;
}

.caregiver-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.caregiver-details {
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.species-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.species-btn {
    padding: 0.25rem 0.75rem;
    background: #f1f5f9;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    font-size: 0.75rem;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.species-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.price {
    font-weight: 600;
    color: var(--success-color);
    font-size: 1.1rem;
}

.request-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s ease, transform 0.2s ease;
    align-self: flex-start;
}

.request-btn:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

/* Request Forms */
.request-form {
    display: none;
    background: #f8fafc;
    border-radius: 12px;
    padding: 2rem;
    margin-top: 1rem;
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

.request-form h2 {
    margin-bottom: 1.5rem;
    color: var(--text-primary);
    font-size: 1.25rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.875rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* Request Cards */
.request-card {
    background: var(--card-background);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.request-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.request-info {
    flex: 1;
}

.request-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.request-details {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.request-meta {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.request-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

/* Status Badges */
.status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-declined {
    background: #fee2e2;
    color: #991b1b;
}

/* Buttons */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    font-size: 0.875rem;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-decline {
    background: var(--danger-color);
    color: white;
}

.btn-decline:hover {
    background: #dc2626;
}

.btn-edit-btn {
    background: #f1f5f9;
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}

.btn-edit-btn:hover {
    background: #e2e8f0;
    color: var(--text-primary);
}

/* Edit Forms */
.edit-form {
    display: none;
    background: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1rem;
    border: 1px solid var(--border-color);
    width: 100%;
}

.edit-form.active {
    display: block;
    animation: slideDown 0.3s ease;
}

/* Success Message */
.success-message {
    background: #d1fae5;
    color: #065f46;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid #a7f3d0;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-state p {
    font-size: 1.1rem;
}

/* Hidden Class */
.hidden {
    display: none !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 0 0.5rem;
    }
    
    .dashboard-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .caregiver-card {
        flex-direction: column;
        text-align: center;
    }
    
    .request-card {
        flex-direction: column;
    }
    
    .request-actions {
        align-items: stretch;
    }
    
    .section-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-bar {
        min-width: auto;
    }
    
    .form-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .toggle-btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .caregiver-card,
    .request-card {
        padding: 1rem;
    }
}
</style>
</head>
<body>
    <!-- Header -->
    <?php include('includes/header.php'); ?>

    <!-- User Type Toggle (only for users who are both pet owner and caregiver) -->
    <?php if ($is_petowner && $is_caregiver): ?>
        <div class="user-type-toggle container">
            <button class="toggle-btn active" onclick="switchUserType('petowner')" id="petownerBtn">Pet Owner</button>
            <button class="toggle-btn" onclick="switchUserType('caregiver')" id="caregiverBtn">Caregiver</button>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1 class="dashboard-title" id="dashboardTitle">
                <?php echo $is_caregiver && !$is_petowner ? 'Caregiver Dashboard' : 'Pet Owner Dashboard'; ?>
            </h1>
        </div>

        <div class="sidebar">
            <h3 id="sidebarTitle"><?php echo $is_petowner && !$is_caregiver ? 'MY ANIMALS' : 'MY REQUESTS'; ?></h3>
            <div class="sidebar-item">
                <span id="sidebarItem"><?php echo $is_petowner && !$is_caregiver ? '🐾 My Pets' : '📋 Received'; ?></span>
                <?php if ($is_petowner && !$is_caregiver): ?>
                <span style="color: var(--primary-color); font-weight: bold;"><?php echo $pet_count; ?></span>
                <?php elseif ($is_caregiver): ?>
                <span style="color: var(--primary-color); font-weight: bold;"><?php echo $request_counts['pending']; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pet Owner Dashboard -->
        <?php if ($is_petowner): ?>
        <div id="petownerDashboard" class="<?php echo $is_caregiver ? 'hidden' : ''; ?>">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pet_count; ?></div>
                    <div class="stat-label">Total Pets</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM carerequest WHERE UserID = ? AND Status = 'Pending'");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                    </div>
                    <div class="stat-label">Active Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM review WHERE ProfileID IN (SELECT ProfileID FROM guardianprofile WHERE UserID = ?)");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                    </div>
                    <div class="stat-label">Reviews</div>
                </div>
            </div>

            <div class="requests-section">
                <div class="section-header">
                    <h2 class="section-title">Available Caregivers</h2>
                    <input type="text" class="search-bar" placeholder="Search by city">
                </div>
                <?php foreach ($caregivers as $caregiver): ?>
                <div class="caregiver-card">
                    <div class="avatar"><?php echo strtoupper(substr($caregiver['FullName'], 0, 2)); ?></div>
                    <div class="caregiver-info">
                        <div class="caregiver-name"><?php echo htmlspecialchars($caregiver['FullName']); ?></div>
                        <div class="caregiver-details">
                            <?php echo htmlspecialchars($caregiver['Email']); ?> <br>
                            <?php echo htmlspecialchars($caregiver['PhoneNumber']); ?> <br>
                            <?php echo htmlspecialchars($caregiver['City']); ?>
                        </div>
                        <div class="caregiver-details">
                            <?php echo htmlspecialchars($caregiver['Bio']); ?>
                        </div>
                        <div class="species-buttons">
                            <?php
                            $species = explode(',', $caregiver['SpeciesList']);
                            foreach ($species as $specie) {
                                echo '<button class="species-btn" onclick="showRequestForm(' . $caregiver['ProfileID'] . ', \'' . trim($specie) . '\')">' . htmlspecialchars(trim($specie)) . '</button>';
                            }
                            ?>
                        </div>
                        <div class="price">
                            Price per night: <?php echo htmlspecialchars($caregiver['PricePerNight'] ?? '20'); ?> MAD
                        </div>
                    </div>
                    <button class="request-btn" onclick="showRequestForm(<?php echo $caregiver['ProfileID']; ?>)">Request Care</button>
                </div>
                <div class="request-form" id="request-form-<?php echo $caregiver['ProfileID']; ?>">
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                        <div class="success-message">Request submitted successfully!</div>
                    <?php endif; ?>
                    <h2>Request Care for <?php echo htmlspecialchars($caregiver['FullName']); ?></h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_request">
                        <input type="hidden" name="profile_id" value="<?php echo $caregiver['ProfileID']; ?>">
                        <div class="form-group">
                            <label for="animal_id">Select Your Pet:</label>
                            <select name="animal_id" id="animal_id" required>
                                <option value="">Select a pet</option>
                                <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['AnimalID']; ?>">
                                    <?php echo htmlspecialchars($pet['Name'] . ' (' . $pet['SpeciesName'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" required>
                        </div>
                        <div class="form-group">
                            <label for="instructions">Special Instructions:</label>
                            <textarea name="instructions" id="instructions"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="request-btn">Submit Request</button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="requests-section">
                <div class="section-header">
                    <h2 class="section-title">My Requests</h2>
                    <a href="#" class="view-all-btn">See All Requests</a>
                </div>
                
                <div id="petownerRequests">
                    <?php 
                    $stmt = $conn->prepare("
                        SELECT cr.*, u.FullName, a.Name as PetName, s.Name as SpeciesName, gp.PricePerNight
                        FROM carerequest cr
                        JOIN _user u ON cr.ProfileID = u.UserID
                        JOIN animal a ON a.UserID = cr.UserID
                        JOIN species s ON a.SpeciesID = s.SpeciesID
                        JOIN guardianprofile gp ON cr.ProfileID = gp.ProfileID
                        WHERE cr.UserID = ?
                    ");
                    $stmt->execute([$user_id]);
                    $my_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($my_requests)): ?>
                    <div class="empty-state">
                        <p>No requests found.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($my_requests as $request): ?>
                    <div class="request-card">
                        <div class="avatar"><?php echo strtoupper(substr($request['FullName'], 0, 2)); ?></div>
                        <div class="request-info">
                            <div class="request-name"><?php echo htmlspecialchars($request['FullName']); ?></div>
                            <div class="request-details">
                                <?php echo htmlspecialchars($request['SpeciesName'] . ' care for ' . $request['PetName'] . ' • ' . 
                                    date('M d', strtotime($request['StartDate'])) . '-' . date('M d, Y', strtotime($request['EndDate']))); ?>
                            </div>
                            <div class="request-meta">
                                Requested <?php echo date('M d, Y', strtotime($request['StartDate'])); ?> • 
                                <?php echo htmlspecialchars($request['PricePerNight'] ?? '0'); ?> MAD/night
                            </div>
                        </div>
                        <div class="request-actions">
                            <span class="status status-<?php echo strtolower($request['Status']); ?>">
                                <?php echo htmlspecialchars($request['Status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Caregiver Dashboard -->
        <?php if ($is_caregiver): ?>
        <div id="caregiverDashboard" class="<?php echo $is_petowner ? 'hidden' : ''; ?>">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum($request_counts); ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $request_counts['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $request_counts['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $request_counts['declined']; ?></div>
                    <div class="stat-label">Declined</div>
                </div>
            </div>

            <div class="requests-section">
                <div class="section-header">
                    <h2 class="section-title">Received Requests</h2>
                </div>

                <div class="request-list" id="receivedRequests">
                    <?php if (empty($received_requests)): ?>
                    <div class="empty-state">
                        <p>No received requests found.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($received_requests as $request): ?>
                    <div class="request-card">
                        <div class="avatar"><?php echo strtoupper(substr($request['FullName'], 0, 2)); ?></div>
                        <div class="request-info">
                            <div class="request-name"><?php echo htmlspecialchars($request['FullName']); ?></div>
                            <div class="request-details">
                                <?php echo htmlspecialchars($request['SpeciesName'] . ' care needed • ' . $request['PetName'] . ' • ' . 
                                    date('M d', strtotime($request['StartDate'])) . '-' . date('M d, Y', strtotime($request['EndDate']))); ?>
                            </div>
                            <div class="request-meta">
                                Requested <?php echo date('M d, Y', strtotime($request['StartDate'])); ?> • 
                                <?php echo htmlspecialchars($guardian_profile['PricePerNight'] ?? '0'); ?> MAD/night
                            </div>
                        </div>
                        <div class="request-actions">
                            <span class="status status-<?php echo strtolower($request['Status']); ?>">
                                <?php echo htmlspecialchars($request['Status']); ?>
                            </span>
                            <button class="btn btn-edit-btn" onclick="toggleEditForm(<?php echo $request['RequestID']; ?>)">Edit Status</button>
                        </div>
                        <div class="edit-form" id="edit-form-<?php echo $request['RequestID']; ?>">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_request">
                                <input type="hidden" name="request_id" value="<?php echo $request['RequestID']; ?>">
                                <div class="form-group">
                                    <label for="status-<?php echo $request['RequestID']; ?>">Update Status:</label>
                                    <select name="status" id="status-<?php echo $request['RequestID']; ?>" required>
                                        <option value="Pending" <?php echo $request['Status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Approved" <?php echo $request['Status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Declined" <?php echo $request['Status'] === 'Declined' ? 'selected' : ''; ?>>Declined</option>
                                    </select>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" class="btn btn-decline" onclick="toggleEditForm(<?php echo $request['RequestID']; ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include('includes/footer.php'); ?> 

    <script>
        <?php if ($is_petowner && $is_caregiver): ?>
        function switchUserType(type) {
            // Toggle dashboard visibility
            document.getElementById('petownerDashboard').classList.toggle('hidden', type !== 'petowner');
            document.getElementById('caregiverDashboard').classList.toggle('hidden', type !== 'caregiver');
            
            // Update dashboard title
            document.getElementById('dashboardTitle').textContent = 
                type === 'petowner' ? 'Pet Owner Dashboard' : 'Caregiver Dashboard';
            
            // Update sidebar content
            document.getElementById('sidebarTitle').textContent = 
                type === 'petowner' ? 'MY ANIMALS' : 'MY REQUESTS';
            document.getElementById('sidebarItem').textContent = 
                type === 'petowner' ? '🐾 My Pets' : '📋 Received';
            document.getElementById('sidebarItem').nextElementSibling.textContent = 
                type === 'petowner' ? <?php echo $pet_count; ?> : <?php echo $request_counts['pending']; ?>;
            
            // Update toggle button active state
            document.getElementById('petownerBtn').classList.toggle('active', type === 'petowner');
            document.getElementById('caregiverBtn').classList.toggle('active', type === 'caregiver');
        }
        <?php endif; ?>

        function showRequestForm(profileId, specie = '') {
            const form = document.getElementById(`request-form-${profileId}`);
            form.classList.toggle('active');
            if (specie) {
                const instructions = form.querySelector('#instructions');
                instructions.value = `Please care for my pet, specifically for ${specie} species.`;
            }
        }

        function toggleEditForm(requestId) {
            const form = document.getElementById(`edit-form-${requestId}`);
            form.classList.toggle('active');
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'auth/login.php';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_petowner && !$is_caregiver): ?>
            document.getElementById('petownerDashboard').classList.remove('hidden');
            <?php elseif ($is_caregiver && !$is_petowner): ?>
            document.getElementById('caregiverDashboard').classList.remove('hidden');
            <?php elseif ($is_petowner && $is_caregiver): ?>
            switchUserType('petowner');
            <?php endif; ?>
        });
    </script>
</body>
</html>