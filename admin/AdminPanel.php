<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$connect = mysqli_connect('localhost', 'root', '', 'saloon');
if (!$connect) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

// Handle extend and delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'extend') {
        $vendor_id = (int)$_POST['vendor_id'];
        $shop_id = (int)$_POST['shop_id'];
        $months = (int)$_POST['months'];
        
        // Get current end date for this specific shop
        $query = "SELECT account_date FROM vendor_accounts WHERE id = $vendor_id AND shop_id = $shop_id";
        $result = mysqli_query($connect, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $current_date = $row['account_date'];
            $new_date = date('Y-m-d', strtotime($current_date . " +$months month"));
            
            // Update account date for this specific shop
            $update_query = "UPDATE vendor_accounts SET account_date = '$new_date' WHERE id = $vendor_id AND shop_id = $shop_id";
            if (mysqli_query($connect, $update_query)) {
                // Refresh the page to show updated data
                header("Location: ".$_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = "Error extending subscription: " . mysqli_error($connect);
            }
        }
    } elseif ($_POST['action'] === 'delete_vendor') {
        $vendor_id = (int)$_POST['vendor_id'];
        $shop_id = (int)$_POST['shop_id'];
        $delete_query = "DELETE FROM vendor_accounts WHERE id = $vendor_id AND shop_id = $shop_id";
        if (mysqli_query($connect, $delete_query)) {
            // Refresh the page to show updated data
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Error deleting vendor: " . mysqli_error($connect);
        }
    } elseif ($_POST['action'] === 'delete_request') {
        $shop_id = (int)$_POST['shop_id'];
        $delete_query = "DELETE FROM register_shop WHERE id = $shop_id";
        if (mysqli_query($connect, $delete_query)) {
            // Refresh the page to show updated data
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Error deleting registration request: " . mysqli_error($connect);
        }
    } elseif ($_POST['action'] === 'delete_shop') {
        $cnic = mysqli_real_escape_string($connect, $_POST['cnic']);
        $shop_id = (int)$_POST['shop_id'];
        
        // Start transaction
        mysqli_begin_transaction($connect);
        
        try {
            // 1. Delete from vendor_accounts table for this specific shop
            $delete_vendor_query = "DELETE FROM vendor_accounts WHERE shop_id = $shop_id AND cnic = '$cnic'";
            if (!mysqli_query($connect, $delete_vendor_query)) {
                throw new Exception("Failed to delete from vendor_accounts: " . mysqli_error($connect));
            }
            
            // 2. Delete from register_shop table
            $delete_shop_query = "DELETE FROM register_shop WHERE id = $shop_id AND cnic = '$cnic'";
            if (!mysqli_query($connect, $delete_shop_query)) {
                throw new Exception("Failed to delete from register_shop: " . mysqli_error($connect));
            }
            
            // Commit transaction if both queries succeeded
            mysqli_commit($connect);
            
            // Refresh the page to show updated data
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($connect);
            $error = "Error deleting shop and vendor account: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'update_status') {
        $vendor_id = (int)$_POST['vendor_id'];
        $shop_id = (int)$_POST['shop_id'];
        $status = mysqli_real_escape_string($connect, $_POST['status']);
        
        // Update status for this specific shop only
        $update_query = "UPDATE vendor_accounts SET status = '$status' WHERE id = $vendor_id AND shop_id = $shop_id";
        if (mysqli_query($connect, $update_query)) {
            // Refresh the page to show updated data
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Error updating vendor status: " . mysqli_error($connect);
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $shop_id = (int) $_POST['shop_id'];
    $action = $_POST['action'];

    // Format WhatsApp number (convert 03xxxxxxxxx to +923xxxxxxxxx)
    $whatsapp_number = preg_replace('/^0/', '92', $_POST['whatsapp_number']);
    $whatsapp_number = '+' . ltrim($whatsapp_number, '+'); // Ensure it starts with +

    if ($action === 'approve') {
        // Create vendor account
        $username = mysqli_real_escape_string($connect, $_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $shop_name = mysqli_real_escape_string($connect, $_POST['shop_name']);
        $account_date = date('Y-m-d'); // Current date in YYYY-MM-DD format
        $cnic = mysqli_real_escape_string($connect, $_POST['cnic']);

        // Start transaction
        mysqli_begin_transaction($connect);

        try {
            // 1. Insert into vendor_accounts table with account_date and CNIC
            $account_query = "INSERT INTO vendor_accounts (shop_id, username, password, account_date, cnic, status) 
                             VALUES ($shop_id, '$username', '$password', '$account_date', '$cnic', 'working')";
            if (!mysqli_query($connect, $account_query)) {
                throw new Exception("Failed to insert into vendor_accounts: " . mysqli_error($connect));
            }

            // 2. Update shop status to approved
            $status_query = "UPDATE register_shop SET status = 'approved', processed_at = NOW() 
                            WHERE id = $shop_id";
            if (!mysqli_query($connect, $status_query)) {
                throw new Exception("Failed to update register_shop: " . mysqli_error($connect));
            }

            // Commit transaction
            if (!mysqli_commit($connect)) {
                throw new Exception("Commit failed: " . mysqli_error($connect));
            }

            // Prepare WhatsApp message with date information
            $formatted_date = date('F j, Y', strtotime($account_date)); // Format as "Month Day, Year"
            $message = "Congratulations! Your salon '$shop_name' has been approved on $formatted_date.\n\n" .
                "Login details:\nUsername: $username\nPassword: {$_POST['password']}\n\n" .
                "Please login and change your password immediately.";

            // Redirect to WhatsApp
            $whatsapp_url = "https://wa.me/$whatsapp_number?text=" . urlencode($message);
            header("Location: $whatsapp_url");
            exit;

        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($connect);
            $error = "Error processing approval: " . $e->getMessage();
            error_log($error);
        }

    } elseif ($action === 'reject') {
        $reason = mysqli_real_escape_string($connect, $_POST['rejection_reason']);

        // Update shop status to rejected
        $query = "UPDATE register_shop SET 
                 status = 'rejected', 
                 admin_notes = '$reason', 
                 processed_at = NOW() 
                 WHERE id = $shop_id";

        if (mysqli_query($connect, $query)) {
            // Prepare rejection message
            $message = "We regret to inform you that your salon application has been rejected.\n\n" .
                "Reason: $reason\n\n" .
                "Please contact support if you have any questions.";

            // Redirect to WhatsApp
            $whatsapp_url = "https://wa.me/$whatsapp_number?text=" . urlencode($message);
            header("Location: $whatsapp_url");
            exit;
        } else {
            $error = "Error rejecting application: " . mysqli_error($connect);
            error_log($error);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Salon Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 5px 8px;
            font-size: 0.7rem;
        }

        .modal-content {
            background: white;
            padding: 20px;
            width: 400px;
            max-width: 90%;
            border-radius: var(--radius);
        }

        .modal-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h4 {
            margin: 0;
        }
        
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');

        :root {
            --primary: #4a6bff;
            --secondary: #333;
            --danger: #e74c3c;
            --success: #2ecc71;
            --warning: #f39c12;
            --light: #f9f9f9;
            --dark: #222;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
        }

        /* Dashboard Layout */
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: var(--dark);
            color: white;
            padding: 20px 0;
        }

        .admin-profile {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
        }

        .admin-name {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .admin-role {
            color: var(--primary);
            font-size: 0.9rem;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(74, 107, 255, 0.2);
            border-left: 3px solid var(--primary);
        }

        .nav-item i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--dark);
        }

        /* Cards */
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f9f9f9;
            font-weight: 600;
        }

        tr:hover {
            background: #f9f9f9;
        }

        /* Badges */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-primary {
            background: rgba(74, 107, 255, 0.2);
            color: var(--primary);
        }

        .badge-success {
            background: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(243, 156, 18, 0.2);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(52, 152, 219, 0.2);
            color: var(--primary);
        }

        /* Buttons */
        .btn {
            padding: 8px 15px;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--radius);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 80%;
            max-height: 80%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .close,
        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close-modal:hover {
            color: #ccc;
        }

        /* Rejection Modal */
        #rejectionModal .modal-content {
            background: white;
            padding: 20px;
            width: 500px;
            max-width: 90%;
            border-radius: var(--radius);
        }

        #rejectionModal textarea {
            width: 100%;
            min-height: 150px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--radius);
        }

        /* Delete Request Modal */
        #deleteRequestModal .modal-content {
            background: white;
            padding: 20px;
            width: 500px;
            max-width: 90%;
            border-radius: var(--radius);
        }

        /* Status Update Modal */
        #statusModal .modal-content {
            background: white;
            padding: 20px;
            width: 500px;
            max-width: 90%;
            border-radius: var(--radius);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="admin-profile">
                <img src="../images/akash.png" alt="Admin" class="admin-avatar">
                <h3 class="admin-name">Akash Meghwar</h3>
                <p class="admin-role">Super Admin</p>
            </div>

            <div class="nav-menu">
                <div class="nav-item active" data-section="vendor-requests">
                    <i class="fas fa-user-plus"></i>
                    <span>Vendor Requests</span>
                </div>
                <div class="nav-item" data-section="subscriptions">
                    <i class="fas fa-calendar-check"></i>
                    <span>Subscriptions</span>
                </div>
                <div class="nav-item" data-section="payments">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment Records</span>
                </div>
                <div class="nav-item" data-section="shop-management">
                    <i class="fas fa-store"></i>
                    <span>Shop Management</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Display errors if any -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="header">
                <h1 class="page-title" id="pageTitle">Vendor Requests</h1>
                <div class="user-actions">
                    <button class="btn btn-primary" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Vendor Requests Section -->
            <div class="section active" id="vendor-requests">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">New Registration Requests</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Shop Name</th>
                                    <th>Owner</th>
                                    <th>CNIC</th>
                                    <th>Contact</th>
                                    <th>Payment Proof</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch pending applications with error handling
                                $query = "SELECT * FROM register_shop WHERE status = 'pending' ORDER BY created_at DESC";
                                $result = mysqli_query($connect, $query);

                                if ($result && mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        // Unserialize images if needed
                                        $shop_images = !empty($row['shop_images']) ? unserialize($row['shop_images']) : [];
                                        $payment_proof = $row['payment_proof'];

                                        echo '<tr data-shop-id="' . $row['id'] . '">';
                                        echo '<td>' . htmlspecialchars($row['shop_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['owner_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['cnic']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['whatsapp_number']) . '</td>';
                                        echo '<td>';
                                        echo '<button class="btn btn-sm btn-primary view-proof" data-image="' . htmlspecialchars($payment_proof) . '">View</button>';
                                        echo '</td>';
                                        echo '<td>Rs. 1,500</td>';
                                        echo '<td><span class="badge badge-warning">Pending</span></td>';
                                        echo '<td class="action-buttons">';
                                        echo '<button class="btn btn-sm btn-success verify-btn" 
                                                data-shop-id="' . $row['id'] . '"
                                                data-shop-name="' . htmlspecialchars($row['shop_name']) . '"
                                                data-owner-name="' . htmlspecialchars($row['owner_name']) . '"
                                                data-whatsapp="' . htmlspecialchars($row['whatsapp_number']) . '"
                                                data-cnic="' . htmlspecialchars($row['cnic']) . '">
                                                <i class="fas fa-check"></i> Verify
                                              </button>';
                                        echo '<button class="btn btn-sm btn-danger reject-btn" 
                                                data-shop-id="' . $row['id'] . '"
                                                data-whatsapp="' . htmlspecialchars($row['whatsapp_number']) . '">
                                                <i class="fas fa-times"></i> Reject
                                              </button>';
                                        echo '<button class="btn btn-sm btn-warning delete-request-btn" 
                                                data-shop-id="' . $row['id'] . '">
                                                <i class="fas fa-trash"></i> Delete
                                              </button>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">No pending applications found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Verification Form (Hidden by Default) -->
                <div class="card" id="verificationForm" style="display: none;">
                    <div class="card-header">
                        <h3 class="card-title">Create Vendor Account</h3>
                    </div>
                    <form id="vendorAccountForm" method="POST">
                        <input type="hidden" name="shop_id" id="formShopId">
                        <input type="hidden" name="whatsapp_number" id="formWhatsapp">
                        <input type="hidden" name="action" value="approve">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="shopName">Shop Name</label>
                                <input type="text" id="shopName" name="shop_name" readonly>
                            </div>
                            <div class="form-group">
                                <label for="ownerName">Owner Name</label>
                                <input type="text" id="ownerName" name="owner_name" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="cnic">CNIC*</label>
                                <input type="text" id="cnic" name="cnic" pattern="[0-9]{5}-[0-9]{7}-[0-9]{1}" 
                                       placeholder="XXXXX-XXXXXXX-X" required>
                                <small class="text-muted">Format: XXXXX-XXXXXXX-X</small>
                            </div>
                            <div class="form-group">
                                <label for="whatsappNum">WhatsApp Number</label>
                                <input type="text" id="whatsappNum" name="whatsapp_display" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username*</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password*</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="accountDate">Account Date</label>
                                <input type="text" id="accountDate" name="account_date"
                                    value="<?php echo date('F j, Y'); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <!-- Empty column for alignment -->
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Account & Notify
                        </button>
                    </form>
                </div>

                <!-- Rejection Modal (Hidden by Default) -->
                <div class="modal" id="rejectionModal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Reject Application</h4>
                            <span class="close-modal">&times;</span>
                        </div>
                        <form id="rejectionForm" method="POST">
                            <input type="hidden" name="shop_id" id="rejectShopId">
                            <input type="hidden" name="whatsapp_number" id="rejectWhatsapp">
                            <input type="hidden" name="action" value="reject">

                            <div class="form-group">
                                <label for="rejectionReason">Reason for Rejection</label>
                                <textarea id="rejectionReason" name="rejection_reason" required></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> Confirm Rejection
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Delete Request Modal -->
                <div class="modal" id="deleteRequestModal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Delete Registration Request</h4>
                            <span class="close-modal">&times;</span>
                        </div>
                        <form id="deleteRequestForm" method="POST">
                            <input type="hidden" name="shop_id" id="deleteRequestShopId">
                            <input type="hidden" name="action" value="delete_request">

                            <p>Are you sure you want to delete this registration request? This action cannot be undone and will permanently remove the application.</p>

                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Confirm Delete
                            </button>
                            <button type="button" class="btn btn-secondary close-modal">
                                Cancel
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Payment Proof Lightbox -->
                <div id="paymentProofModal" class="modal" style="display: none;">
                    <span class="close">&times;</span>
                    <img class="modal-content" id="proofImage">
                </div>
            </div>

            <!-- Subscriptions Section -->
            <div class="section" id="subscriptions">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Active Subscriptions</h3>
                        <div>
                            <select class="btn btn-sm" id="subscriptionFilter">
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="expired">Expired</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Shop Name</th>
                                    <th>Owner</th>
                                    <th>CNIC</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Account Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch vendor accounts with shop information
                                $query = "SELECT va.*, rs.id as shop_id, rs.shop_name, rs.owner_name, rs.whatsapp_number, rs.cnic 
                                          FROM vendor_accounts va
                                          JOIN register_shop rs ON va.shop_id = rs.id
                                          ORDER BY va.account_date DESC";
                                $result = mysqli_query($connect, $query);

                                if ($result && mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $start_date = date('M j, Y', strtotime($row['account_date']));
                                        $end_date = date('M j, Y', strtotime($row['account_date'] . ' +1 month'));
                                        $current_date = date('Y-m-d');
                                        $status = (strtotime($current_date) <= strtotime($end_date)) ? 'active' : 'expired';
                                        $account_status = $row['status'] ?? 'working'; // Default to working if status not set

                                        echo '<tr data-shop-id="' . $row['shop_id'] . '">';
                                        echo '<td>' . htmlspecialchars($row['shop_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['owner_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['cnic']) . '</td>';
                                        echo '<td>' . $start_date . '</td>';
                                        echo '<td>' . $end_date . '</td>';
                                        echo '<td><span class="badge ' . ($status == 'active' ? 'badge-success' : 'badge-warning') . '">' . ucfirst($status) . '</span></td>';
                                        echo '<td><span class="badge ' . ($account_status == 'working' ? 'badge-success' : 'badge-danger') . '">' . ucfirst($account_status) . '</span></td>';
                                        echo '<td class="action-buttons">';
                                        echo '<button class="btn btn-sm btn-primary extend-btn" 
                                                data-vendor-id="' . $row['id'] . '"
                                                data-shop-id="' . $row['shop_id'] . '"
                                                data-whatsapp="' . htmlspecialchars($row['whatsapp_number']) . '">
                                                <i class="fas fa-calendar-plus"></i> Extend
                                              </button>';
                                        echo '<button class="btn btn-sm ' . ($account_status == 'working' ? 'btn-danger' : 'btn-success') . ' status-btn" 
                                                data-vendor-id="' . $row['id'] . '"
                                                data-shop-id="' . $row['shop_id'] . '"
                                                data-current-status="' . $account_status . '">
                                                <i class="fas ' . ($account_status == 'working' ? 'fa-ban' : 'fa-check-circle') . '"></i> ' . ($account_status == 'working' ? 'Block' : 'Unblock') . '
                                              </button>';
                                        echo '<button class="btn btn-sm btn-danger delete-shop-btn" 
                                                data-shop-id="' . $row['shop_id'] . '"
                                                data-cnic="' . htmlspecialchars($row['cnic']) . '">
                                                <i class="fas fa-trash"></i> Delete Shop
                                              </button>';
                                        echo '<button class="btn btn-sm btn-success whatsapp-btn" 
                                                data-whatsapp="' . htmlspecialchars($row['whatsapp_number']) . '">
                                                <i class="fas fa-comment"></i> Message
                                              </button>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">No vendor accounts found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Extend Subscription Modal -->
                <div class="modal" id="extendModal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Extend Subscription</h4>
                            <span class="close-modal">&times;</span>
                        </div>
                        <form id="extendForm" method="POST">
                            <input type="hidden" name="vendor_id" id="extendVendorId">
                            <input type="hidden" name="shop_id" id="extendShopId">
                            <input type="hidden" name="action" value="extend">

                            <div class="form-group">
                                <label for="extendMonths">Extend by (months)</label>
                                <select id="extendMonths" name="months" class="form-control">
                                    <option value="1">1 Month</option>
                                    <option value="3">3 Months</option>
                                    <option value="6">6 Months</option>
                                    <option value="12">12 Months</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Extend Subscription
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Status Update Modal -->
                <div class="modal" id="statusModal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Update Account Status</h4>
                            <span class="close-modal">&times;</span>
                        </div>
                        <form id="statusForm" method="POST">
                            <input type="hidden" name="vendor_id" id="statusVendorId">
                            <input type="hidden" name="shop_id" id="statusShopId">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" id="newStatus">

                            <p id="statusMessage">Are you sure you want to block this vendor account?</p>

                            <button type="submit" class="btn btn-primary" id="confirmStatusBtn">
                                <i class="fas fa-save"></i> Confirm
                            </button>
                            <button type="button" class="btn btn-secondary close-modal">
                                Cancel
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Delete Shop Confirmation Modal -->
                <div class="modal" id="deleteShopModal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>Confirm Shop Deletion</h4>
                            <span class="close-modal">&times;</span>
                        </div>
                        <form id="deleteShopForm" method="POST">
                            <input type="hidden" name="shop_id" id="deleteShopId">
                            <input type="hidden" name="cnic" id="deleteShopCnic">
                            <input type="hidden" name="action" value="delete_shop">

                            <p>Are you sure you want to delete this shop? This will permanently remove both the shop registration and vendor account from the system.</p>

                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Confirm Delete
                            </button>
                            <button type="button" class="btn btn-secondary close-modal">
                                Cancel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Refresh button functionality
        document.getElementById('refreshBtn').addEventListener('click', function() {
            location.reload();
        });

        // Status update button
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const vendorId = this.getAttribute('data-vendor-id');
                const shopId = this.getAttribute('data-shop-id');
                const currentStatus = this.getAttribute('data-current-status');
                const newStatus = currentStatus === 'working' ? 'blocked' : 'working';
                
                document.getElementById('statusVendorId').value = vendorId;
                document.getElementById('statusShopId').value = shopId;
                document.getElementById('newStatus').value = newStatus;
                
                const message = `Are you sure you want to ${newStatus === 'blocked' ? 'block' : 'unblock'} this vendor account?`;
                document.getElementById('statusMessage').textContent = message;
                
                document.getElementById('statusModal').style.display = 'block';
            });
        });

        // Extend subscription button
        document.querySelectorAll('.extend-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const vendorId = this.getAttribute('data-vendor-id');
                const shopId = this.getAttribute('data-shop-id');
                document.getElementById('extendVendorId').value = vendorId;
                document.getElementById('extendShopId').value = shopId;
                document.getElementById('extendModal').style.display = 'block';
            });
        });

        // Delete shop button
        document.querySelectorAll('.delete-shop-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const shopId = this.getAttribute('data-shop-id');
                const cnic = this.getAttribute('data-cnic');
                document.getElementById('deleteShopId').value = shopId;
                document.getElementById('deleteShopCnic').value = cnic;
                document.getElementById('deleteShopModal').style.display = 'block';
            });
        });

        // WhatsApp message button
        document.querySelectorAll('.whatsapp-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                let whatsappNum = this.getAttribute('data-whatsapp');
                whatsappNum = whatsappNum.replace(/^0/, '92');
                whatsappNum = '+' + whatsappNum.replace(/^\+/, '');
                window.open(`https://wa.me/${whatsappNum}`, '_blank');
            });
        });

        // Filter subscriptions
        document.getElementById('subscriptionFilter').addEventListener('change', function () {
            const filter = this.value;
            document.querySelectorAll('#subscriptions tbody tr').forEach(row => {
                const status = row.querySelector('td:nth-child(6) .badge').textContent.toLowerCase();
                if (filter === 'all' || status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Navigation between sections
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function () {
                // Remove active class from all nav items
                document.querySelectorAll('.nav-item').forEach(nav => {
                    nav.classList.remove('active');
                });

                // Add active class to clicked nav item
                this.classList.add('active');

                // Hide all sections
                document.querySelectorAll('.section').forEach(section => {
                    section.classList.remove('active');
                });

                // Show selected section
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');

                // Update page title
                document.getElementById('pageTitle').textContent = this.querySelector('span').textContent;
            });
        });

        // Verify button - show account creation form
        document.querySelectorAll('.verify-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const shopId = this.getAttribute('data-shop-id');
                const shopName = this.getAttribute('data-shop-name');
                const ownerName = this.getAttribute('data-owner-name');
                let whatsappNum = this.getAttribute('data-whatsapp');
                const cnic = this.getAttribute('data-cnic');

                // Format WhatsApp number (03xx -> +923xx)
                whatsappNum = whatsappNum.replace(/^0/, '92');
                whatsappNum = '+' + whatsappNum.replace(/^\+/, ''); // Ensure single +

                // Fill form with data
                document.getElementById('shopName').value = shopName;
                document.getElementById('ownerName').value = ownerName;
                document.getElementById('whatsappNum').value = whatsappNum;
                document.getElementById('formShopId').value = shopId;
                document.getElementById('formWhatsapp').value = whatsappNum;
                document.getElementById('cnic').value = cnic;

                // Generate suggested username
                const username = shopName.toLowerCase().replace(/\s+/g, '_') + '_' + Math.floor(1000 + Math.random() * 9000);
                document.getElementById('username').value = username;

                // Generate random password
                const password = Math.random().toString(36).slice(-8);
                document.getElementById('password').value = password;

                // Show form
                document.getElementById('verificationForm').style.display = 'block';
                document.getElementById('verificationForm').scrollIntoView({ behavior: 'smooth' });
            });
        });

        // Reject button - show rejection modal
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const shopId = this.getAttribute('data-shop-id');
                let whatsappNum = this.getAttribute('data-whatsapp');
                
                // Format WhatsApp number (03xx -> +923xx)
                whatsappNum = whatsappNum.replace(/^0/, '92');
                whatsappNum = '+' + whatsappNum.replace(/^\+/, ''); // Ensure single +

                document.getElementById('rejectShopId').value = shopId;
                document.getElementById('rejectWhatsapp').value = whatsappNum;
                document.getElementById('rejectionModal').style.display = 'block';
            });
        });

        // View payment proof
        document.querySelectorAll('.view-proof').forEach(btn => {
            btn.addEventListener('click', function () {
                const imagePath = this.getAttribute('data-image');
                const modal = document.getElementById('paymentProofModal');
                const img = document.getElementById('proofImage');
                
                img.src = '../uploads/' + imagePath;
                modal.style.display = 'block';
            });
        });

        // Close modals
        document.querySelectorAll('.close, .close-modal').forEach(closeBtn => {
            closeBtn.addEventListener('click', function () {
                this.closest('.modal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        });

        // CNIC input formatting
        document.getElementById('cnic').addEventListener('input', function (e) {
            // Remove all non-digit characters
            let value = this.value.replace(/\D/g, '');
            
            // Format as XXXXX-XXXXXXX-X
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5);
            }
            if (value.length > 13) {
                value = value.substring(0, 13) + '-' + value.substring(13, 14);
            }
            
            // Limit to 15 characters (5+7+1 + 2 dashes)
            this.value = value.substring(0, 15);
        });

        // Form validation for vendor account creation
        document.getElementById('vendorAccountForm').addEventListener('submit', function (e) {
            const cnic = document.getElementById('cnic').value;
            const cnicRegex = /^\d{5}-\d{7}-\d{1}$/;
            
            if (!cnicRegex.test(cnic)) {
                e.preventDefault();
                alert('Please enter a valid CNIC in the format XXXXX-XXXXXXX-X');
                return false;
            }
            
            return true;
        });

        // Delete request button
        document.querySelectorAll('.delete-request-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const shopId = this.getAttribute('data-shop-id');
                document.getElementById('deleteRequestShopId').value = shopId;
                document.getElementById('deleteRequestModal').style.display = 'block';
            });
        });
    </script>
</body>
</html>