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
        SELECT cr.*, u.FullName, GROUP_CONCAT(a.Name) as PetNames, GROUP_CONCAT(s.Name) as SpeciesNames
        FROM carerequest cr
        JOIN _user u ON cr.UserID = u.UserID
        JOIN carerequest_animals cra ON cr.RequestID = cra.RequestID
        JOIN animal a ON cra.AnimalID = a.AnimalID
        JOIN species s ON a.SpeciesID = s.SpeciesID
        WHERE cr.ProfileID = ?
        GROUP BY cr.RequestID
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

// Get all messages for the user
$messages = [];
$stmt = $conn->prepare("
    SELECT m.*, u.FullName, u.Email, u.PhoneNumber
    FROM messages m
    JOIN _user u ON m.SenderID = u.UserID
    WHERE m.ReceiverID = ?
    ORDER BY m.CreatedAt DESC
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications for pet owner (unread only for dashboard display)
$notifications = [];
if ($is_petowner) {
    $stmt = $conn->prepare("
        SELECT m.*, u.FullName, u.Email, u.PhoneNumber
        FROM messages m
        JOIN _user u ON m.SenderID = u.UserID
        WHERE m.ReceiverID = ? AND m.IsRead = 0
        ORDER BY m.CreatedAt DESC
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark notifications as read after fetching
    $stmt = $conn->prepare("UPDATE messages SET IsRead = 1 WHERE ReceiverID = ? AND IsRead = 0");
    $stmt->execute([$user_id]);
}

// Handle request submission (for pet owners)
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
            header('Location: dashboard.php?success=1');
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Handle request status update (for caregivers)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_request') {
    $request_id = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if ($request_id && $status && in_array($status, ['Pending', 'Approved', 'Declined'])) {
        // Update request status
        $stmt = $conn->prepare("UPDATE carerequest SET Status = ? WHERE RequestID = ? AND ProfileID = ?");
        $stmt->execute([$status, $request_id, $guardian_profile['ProfileID']]);
        
        // Get request details for notification
        $stmt = $conn->prepare("
            SELECT cr.UserID, gp.UserID as CaregiverID
            FROM carerequest cr
            JOIN guardianprofile gp ON cr.ProfileID = gp.ProfileID
            WHERE cr.RequestID = ?
        ");
        $stmt->execute([$request_id]);
        $request_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request_info) {
            // Prepare notification message
            $message_content = '';
            if ($status === 'Approved') {
                $message_content = "Your care request has been approved! Contact the caregiver: Email - {$user['Email']}, Phone - {$user['PhoneNumber']}.";
            } elseif ($status === 'Declined') {
                $message_content = "Sorry, your care request has been declined.";
            }
            
            // Insert notification into messages table
            $stmt = $conn->prepare("
                INSERT INTO messages (SenderID, ReceiverID, RequestID, MessageContent, IsRead)
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([$request_info['CaregiverID'], $request_info['UserID'], $request_id, $message_content]);
        }
        
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
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <title>Pet Care Dashboard</title>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <?php if ($is_petowner && $is_caregiver): ?>
        <div class="user-type-toggle">
            <button class="toggle-btn active" onclick="switchUserType('petowner')" id="petownerBtn">Pet Owner</button>
            <button class="toggle-btn" onclick="switchUserType('caregiver')" id="caregiverBtn">Caregiver</button>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="messages-container">
            <button class="messages-btn" onclick="toggleMessagesDropdown()">
                <svg class="messages-icon" viewBox="0 0 24 24">
                    <path d="M20 2H4a2 2 0 00-2 2v12a2 2 0 002 2h4l4 4 4-4h4a2 2 0 002-2V4a2 2 0 00-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
                <?php if (count($messages) > 0): ?>
                <span class="messages-count"><?php echo count($messages); ?></span>
                <?php endif; ?>
            </button>
            <div class="messages-dropdown" id="messagesDropdown">
                <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <p>No messages found.</p>
                </div>
                <?php else: ?>
                <?php foreach ($messages as $message): ?>
                <div class="message-item <?php echo $message['IsRead'] == 0 ? 'unread' : ''; ?>">
                    <div class="message-sender"><?php echo htmlspecialchars($message['FullName']); ?></div>
                    <div class="message-content"><?php echo htmlspecialchars($message['MessageContent']); ?></div>
                    <div class="message-time"><?php echo date('M d, Y H:i', strtotime($message['CreatedAt'])); ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

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

        <?php if ($is_petowner): ?>
        <div id="petownerDashboard" class="<?php echo $is_caregiver ? 'hidden' : ''; ?>">
            <?php if (!empty($notifications)): ?>
            <div class="notifications">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-message notification-<?php echo strpos($notification['MessageContent'], 'approved') !== false ? 'approved' : 'declined'; ?>">
                    <p><strong><?php echo htmlspecialchars($notification['FullName']); ?>:</strong> <?php echo htmlspecialchars($notification['MessageContent']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

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
                    <h2 class="section-title">My Requests</h2>
                    <a href="#" class="view-all-btn">See All Requests</a>
                </div>
                
                <div id="petownerRequests">
                    <?php 
                    $stmt = $conn->prepare("
                        SELECT cr.*, u.FullName, GROUP_CONCAT(a.Name) as PetNames, GROUP_CONCAT(s.Name) as SpeciesNames, gp.PricePerNight
                        FROM carerequest cr
                        JOIN _user u ON cr.ProfileID = u.UserID
                        JOIN carerequest_animals cra ON cr.RequestID = cra.RequestID
                        JOIN animal a ON cra.AnimalID = a.AnimalID
                        JOIN species s ON a.SpeciesID = s.SpeciesID
                        JOIN guardianprofile gp ON cr.ProfileID = gp.ProfileID
                        WHERE cr.UserID = ?
                        GROUP BY cr.RequestID
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
                                <?php echo htmlspecialchars($request['SpeciesNames'] . ' care for ' . $request['PetNames'] . ' • ' . 
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
                                <?php echo htmlspecialchars($request['SpeciesNames'] . ' care needed • ' . $request['PetNames'] . ' • ' . 
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
                                <div class="edit-form-header">
                                    <h3 class="edit-form-title">Update Request Status</h3>
                                    <button type="button" class="edit-form-close" onclick="toggleEditForm(<?php echo $request['RequestID']; ?>)">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="edit-form-group">
                                    <label class="edit-form-label" for="status-<?php echo $request['RequestID']; ?>">Status</label>
                                    <select name="status" id="status-<?php echo $request['RequestID']; ?>" class="edit-form-select" required>
                                        <option value="Pending" <?php echo $request['Status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Approved" <?php echo $request['Status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Declined" <?php echo $request['Status'] === 'Declined' ? 'selected' : ''; ?>>Declined</option>
                                    </select>
                                </div>
                                <div class="edit-form-actions">
                                    <button type="submit" class="edit-form-btn edit-form-btn-primary">Update Status</button>
                                    <button type="button" class="edit-form-btn edit-form-btn-secondary" onclick="toggleEditForm(<?php echo $request['RequestID']; ?>)">Cancel</button>
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

    <?php include('includes/footer.php'); ?> 

    <script>
        <?php if ($is_petowner && $is_caregiver): ?>
        function switchUserType(type) {
            document.getElementById('petownerDashboard').classList.toggle('hidden', type !== 'petowner');
            document.getElementById('caregiverDashboard').classList.toggle('hidden', type !== 'caregiver');
            document.getElementById('dashboardTitle').textContent = 
                type === 'petowner' ? 'Pet Owner Dashboard' : 'Caregiver Dashboard';
            document.getElementById('sidebarTitle').textContent = 
                type === 'petowner' ? 'MY ANIMALS' : 'MY REQUESTS';
            document.getElementById('sidebarItem').textContent = 
                type === 'petowner' ? '🐾 My Pets' : '📋 Received';
            document.getElementById('sidebarItem').nextElementSibling.textContent = 
                type === 'petowner' ? <?php echo $pet_count; ?> : <?php echo $request_counts['pending']; ?>;
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

        function toggleMessagesDropdown() {
            const dropdown = document.getElementById('messagesDropdown');
            dropdown.classList.toggle('active');
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

            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('messagesDropdown');
                const button = document.querySelector('.messages-btn');
                if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                    dropdown.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>