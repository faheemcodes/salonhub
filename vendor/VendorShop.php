<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header("Location: signInSloonShop.php");
    exit;
}

// Get shop data from session
$shop_data = $_SESSION['shop_data'];
$shop_id = $shop_data['id'];
$shop_name = htmlspecialchars($shop_data['shop_name']);
$owner_name = htmlspecialchars($shop_data['owner_name']);
$shop_logo = htmlspecialchars($shop_data['shop_logo']);
$shop_address = htmlspecialchars($shop_data['shop_address']);
$contact_number = htmlspecialchars($shop_data['contact_number']);
$email = htmlspecialchars($shop_data['email']);
$cnic = htmlspecialchars($shop_data['cnic']);

// Database connection
class Database
{
    private $host = 'localhost';
    private $user = 'root';
    private $password = '';
    private $dbname = 'saloon';
    private $conn;

    public function __construct()
    {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die("Error in query preparation: " . $this->conn->error);
        }
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getLastInsertId()
    {
        return $this->conn->insert_id;
    }
}

$db = new Database();

// Handle status toggle (REAL-TIME UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $newStatus = $_POST['new_status'];

    // Update both tables simultaneously
    $sql1 = "UPDATE register_shop SET status = ? WHERE id = ?";
    $sql2 = "UPDATE vendor_accounts SET shop_status = ? WHERE shop_id = ?";

    $db->query($sql1, [$newStatus, $shop_id]);
    $db->query($sql2, [$newStatus, $shop_id]);

    // Update session data
    $_SESSION['shop_data']['status'] = $newStatus;
    $shop_data['status'] = $newStatus;

    // Return JSON response for real-time update
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'new_status' => $newStatus]);
    exit;
}

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $appointmentId = $_POST['appointment_id'];
    $action = $_POST['action'];
    $customerPhone = $_POST['customer_phone'];
    $customerName = $_POST['customer_name'];
    $serviceType = $_POST['service_type'];
    $appointmentDate = $_POST['appointment_date'];
    $appointmentTime = $_POST['appointment_time'];

    $validStatuses = ['confirmed', 'completed', 'cancelled'];
    if (in_array($action, $validStatuses)) {
        $sql = "UPDATE appointments SET status = ? WHERE id = ? AND shop_id = ?";
        $stmt = $db->query($sql, [$action, $appointmentId, $shop_id]);

        // Prepare WhatsApp message for confirmed or cancelled appointments
        $whatsappMessage = '';
        $whatsappNumber = preg_replace('/[^0-9]/', '', $customerPhone);
        
        // Convert phone number to international format with Pakistan country code
        if (substr($whatsappNumber, 0, 2) === '03' && strlen($whatsappNumber) === 10) {
            // Convert 03XXXXXXXX to +923XXXXXXXXX
            $whatsappNumber = '+92' . substr($whatsappNumber, 1);
        } elseif (substr($whatsappNumber, 0, 1) === '3' && strlen($whatsappNumber) === 9) {
            // Convert 3XXXXXXXX to +923XXXXXXXX
            $whatsappNumber = '+92' . $whatsappNumber;
        } elseif (strlen($whatsappNumber) === 11 && substr($whatsappNumber, 0, 2) === '92') {
            // Convert 92XXXXXXXXX to +92XXXXXXXXX
            $whatsappNumber = '+' . $whatsappNumber;
        } elseif (strlen($whatsappNumber) === 12 && substr($whatsappNumber, 0, 3) === '920') {
            // Convert 920XXXXXXXXX to +92XXXXXXXXX
            $whatsappNumber = '+92' . substr($whatsappNumber, 3);
        }

        if ($action === 'confirmed') {
            $whatsappMessage = "Hello $customerName, your appointment for $serviceType on " .
                date('M j, Y', strtotime($appointmentDate)) . " at " .
                date('h:i A', strtotime($appointmentTime)) .
                " has been confirmed by $shop_name. Thank you!";
        } elseif ($action === 'cancelled') {
            $rejectionReason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : 'unavailable time slot';
            $whatsappMessage = "Hello $customerName, we regret to inform you that your appointment for $serviceType on " .
                date('M j, Y', strtotime($appointmentDate)) . " at " .
                date('h:i A', strtotime($appointmentTime)) .
                " has been cancelled by $shop_name. Reason: $rejectionReason. Please contact us for rescheduling.";
        }

        // Create WhatsApp link
        if (!empty($whatsappMessage) && !empty($whatsappNumber)) {
            $encodedMessage = urlencode($whatsappMessage);
            $whatsappLink = "https://wa.me/$whatsappNumber?text=$encodedMessage";

            // Redirect to WhatsApp (this will open WhatsApp with the message)
            header("Location: $whatsappLink");
            exit;
        }

        // Refresh the page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle service completion with payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_service'])) {
    $appointmentId = $_POST['appointment_id'];
    $customerName = $_POST['customer_name'];
    $customerPhone = $_POST['customer_phone'];
    $serviceType = $_POST['service_type'];
    $paymentAmount = $_POST['payment_amount'];
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    
    // Update appointment status to completed
    $sql = "UPDATE appointments SET status = 'completed' WHERE id = ? AND shop_id = ?";
    $stmt = $db->query($sql, [$appointmentId, $shop_id]);
    
    // Record the payment in manual_payments table
    $paymentDate = date('Y-m-d');
    $sql = "INSERT INTO manual_payments (shop_id, shop_cnic, customer_name, customer_phone, service_type, payment_amount, payment_date, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->query($sql, [
        $shop_id,
        $cnic,
        $customerName,
        $customerPhone,
        $serviceType,
        $paymentAmount,
        $paymentDate,
        $paymentMethod
    ]);

    $_SESSION['success_message'] = "Service completed and payment recorded successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle service management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name = $_POST['name'];
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'];
    $duration = $_POST['duration'];
    $category = $_POST['category'] ?? 'general';

    $sql = "INSERT INTO services (shop_id, name, description, price, duration, category) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->query($sql, [
        $shop_id,
        $name,
        $description,
        $price,
        $duration,
        $category
    ]);

    $_SESSION['success_message'] = "Service added successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "#services-management");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $serviceId = $_POST['service_id'];
    $name = $_POST['name'];
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'];
    $duration = $_POST['duration'];
    $category = $_POST['category'] ?? 'general';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "UPDATE services SET name = ?, description = ?, price = ?, duration = ?, category = ?, is_active = ? 
            WHERE id = ? AND shop_id = ?";
    $stmt = $db->query($sql, [
        $name,
        $description,
        $price,
        $duration,
        $category,
        $is_active,
        $serviceId,
        $shop_id
    ]);

    $_SESSION['success_message'] = "Service updated successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "#services-management");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    $serviceId = $_POST['service_id'];

    $sql = "DELETE FROM services WHERE id = ? AND shop_id = ?";
    $stmt = $db->query($sql, [$serviceId, $shop_id]);

    $_SESSION['success_message'] = "Service deleted successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "#services-management");
    exit;
}

// Handle staff management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Handle staff photo upload
    $photoPath = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/staff/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $photoPath = $targetPath;
        }
    }

    $sql = "INSERT INTO staff (shop_id, name, role, phone, email, photo) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->query($sql, [
        $shop_id,
        $name,
        $role,
        $phone,
        $email,
        $photoPath
    ]);

    $_SESSION['success_message'] = "Staff member added successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "#staff-management");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $staffId = $_POST['staff_id'];
    $name = $_POST['name'];
    $role = $_POST['role'];
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Handle staff photo upload
    $photoPath = $_POST['existing_photo'] ?? '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/staff/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Delete old photo if exists
        if (!empty($photoPath) && file_exists($photoPath)) {
            unlink($photoPath);
        }
        
        $fileName = time() . '_' . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $photoPath = $targetPath;
        }
    }

    $sql = "UPDATE staff SET name = ?, role = ?, phone = ?, email = ?, photo = ?, is_active = ? 
            WHERE id = ? AND shop_id = ?";
    $stmt = $db->query($sql, [
        $name,
        $role,
        $phone,
        $email,
        $photoPath,
        $is_active,
        $staffId,
        $shop_id
    ]);

    $_SESSION['success_message'] = "Staff member updated successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "#staff-management");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    $staffId = $_POST['staff_id'];

    // Get photo path to delete the file
    $staff = $db->fetchOne("SELECT photo FROM staff WHERE id = ? AND shop_id = ?", [$staffId, $shop_id]);
    if ($staff && !empty($staff['photo']) && file_exists($staff['photo'])) {
        unlink($staff['photo']);
    }

    $sql = "DELETE FROM staff WHERE id = ? AND shop_id = ?";
    $stmt = $db->query($sql, [$staffId, $shop_id]);

    $_SESSION['success_message'] = "Staff member deleted successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "#staff-management");
    exit;
}

// Get counts for dashboard
$newAppointmentsCount = $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE shop_id = ? AND status = 'pending'", [$shop_id])['count'];
$completedAppointmentsCount = $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE shop_id = ? AND status = 'completed'", [$shop_id])['count'];

// Get today's earnings from completed appointments
$todayEarnings = $db->fetchOne("SELECT SUM(payment_amount) as total FROM manual_payments 
                                  WHERE shop_cnic = ? AND payment_date = CURDATE()", [$cnic])['total'] ?? 0;

// Get all-time total services and earnings
$allTimeServices = $db->fetchOne("SELECT COUNT(*) as count FROM appointments 
                               WHERE shop_id = ? AND status = 'completed'", [$shop_id])['count'] ?? 0;

$allTimeManualServices = $db->fetchOne("SELECT COUNT(*) as count FROM manual_payments 
                                     WHERE shop_cnic = ?", [$cnic])['count'] ?? 0;

$allTimeTotalServices = $allTimeServices + $allTimeManualServices;

$allTimeEarnings = $db->fetchOne("SELECT SUM(payment_amount) as total FROM manual_payments 
                                     WHERE shop_cnic = ?", [$cnic])['total'] ?? 0;

// Get appointments data
$newAppointments = $db->fetchAll("SELECT * FROM appointments WHERE shop_id = ? AND status = 'pending' ORDER BY appointment_date, appointment_time", [$shop_id]);
$allAppointments = $db->fetchAll("SELECT * FROM appointments WHERE shop_id = ? ORDER BY appointment_date DESC, appointment_time DESC", [$shop_id]);

// Get today's completed services
$todayServices = $db->fetchAll("SELECT m.* FROM manual_payments m 
                                WHERE m.shop_cnic = ? AND m.payment_date = CURDATE() 
                                ORDER BY m.created_at DESC", [$cnic]);

// Get services data
$services = $db->fetchAll("SELECT * FROM services WHERE shop_id = ? ORDER BY created_at DESC", [$shop_id]);

// Get staff data
$staff = $db->fetchAll("SELECT * FROM staff WHERE shop_id = ? ORDER BY created_at DESC", [$shop_id]);

// Handle shop logo display
$logo_src = (!empty($shop_logo) && file_exists($shop_logo)) ? $shop_logo : 'https://via.placeholder.com/150';

// Check if we're editing a service or staff member
$editingService = isset($_GET['edit_service']) ? intval($_GET['edit_service']) : null;
$editingStaff = isset($_GET['edit_staff']) ? intval($_GET['edit_staff']) : null;

// Get service data if editing
$serviceData = null;
if ($editingService) {
    $serviceData = $db->fetchOne("SELECT * FROM services WHERE id = ? AND shop_id = ?", [$editingService, $shop_id]);
}

// Get staff data if editing
$staffData = null;
if ($editingStaff) {
    $staffData = $db->fetchOne("SELECT * FROM staff WHERE id = ? AND shop_id = ?", [$editingStaff, $shop_id]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $shop_name; ?> - Shop Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: #198cbd;
            --primary-dark: #127aa8;
            --secondary: #022c3e;
            --light: #f9f9f9;
            --danger: #ff6b6b;
            --success: #2ecc71;
            --warning: #f39c12;
            --info: #3498db;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--secondary);
            line-height: 1.6;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            background: var(--secondary);
            color: white;
            padding: 20px 0;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .shop-info {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .shop-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
        }

        .shop-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .owner-name {
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
            background: rgba(25, 140, 189, 0.15);
            border-left: 3px solid var(--primary);
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            color: var(--primary);
        }

        .shop-status {
            position: absolute;
            bottom: 80px;
            left: 0;
            right: 0;
            padding: 0 25px;
        }

        .status-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 15px;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .status-text {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success);
        }

        .status-indicator.closed {
            background: var(--danger);
        }

        /* Logout Button */
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            padding: 0 25px;
        }

        .logout-btn a {
            display: block;
            padding: 12px 15px;
            width: 100%;
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger);
            text-align: center;
            border-radius: var(--radius);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn a:hover {
            background: rgba(231, 76, 60, 0.3);
        }

        /* Main Content Styles */
        .main-content {
            padding: 20px;
            background: #f8f9fa;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--secondary);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            background: var(--secondary);
            color: white;
            padding: 15px 20px;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: var(--shadow);
        }

        .mobile-header .shop-name {
            font-size: 1.2rem;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Dashboard Cards */
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary);
        }

        .btn {
            padding: 8px 16px;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* Order Management Table */
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--radius);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background: #f9f9f9;
            font-weight: 600;
            color: var(--secondary);
        }

        tr:hover {
            background: #f9f9f9;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(52, 152, 219, 0.2);
            color: var(--info);
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            margin-right: 5px;
            transition: all 0.3s ease;
        }

        .btn-accept {
            background: var(--success);
            color: white;
        }

        .btn-reject {
            background: var(--danger);
            color: white;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-family: inherit;
            transition: border 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Today's Summary */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card h3 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 5px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--secondary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        /* Hidden Sections */
        .section {
            display: none;
        }

        .section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        /* Payment Form Styles */
        .payment-form {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-submit {
            text-align: right;
            margin-top: 20px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--radius);
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: var(--success);
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: var(--danger);
        }

        /* Service and Staff Cards */
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .service-card, .staff-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            position: relative;
            transition: transform 0.3s ease;
        }

        .service-card:hover, .staff-card:hover {
            transform: translateY(-5px);
        }

        .service-card h3, .staff-card h3 {
            margin-bottom: 10px;
            color: var(--secondary);
        }

        .service-price {
            font-size: 1.5rem;
            color: var(--primary);
            margin: 10px 0;
            font-weight: 600;
        }

        .service-duration, .staff-role {
            color: var(--gray);
            margin-bottom: 15px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .staff-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 2px solid var(--primary);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--success);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .grid-cards {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .sidebar {
    background: var(--secondary);
    color: white;
    padding: 20px 0;
    position: fixed;
    height: 100vh;
    width: 250px;
    top: 0;
    left: 0;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
    overflow-y: auto; /* Allow sidebar to scroll if content is too long */
    z-index: 1000;
}

            .sidebar.active {
                left: 0;
            }

            .mobile-header {
                display: flex;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .grid-cards {
                grid-template-columns: 1fr;
            }
            
            .summary-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .card-actions {
                flex-direction: column;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .modal-content {
                padding: 20px 15px;
            }
        }
        

        
    </style>
</head>

<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <h2 class="shop-name"><?php echo $shop_name; ?></h2>
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="shop-info">
                <img src="<?php echo $logo_src; ?>" alt="Shop Logo" class="shop-logo">
                <h3 class="shop-name"><?php echo $shop_name; ?></h3>
                <p class="owner-name">Owner: <?php echo $owner_name; ?></p>
            </div>

            <div class="nav-menu">
                <div class="nav-item active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" data-section="new-orders">
                    <i class="fas fa-bell"></i>
                    <span>New Orders (<?php echo $newAppointmentsCount; ?>)</span>
                </div>
                <div class="nav-item" data-section="all-orders">
                    <i class="fas fa-list"></i>
                    <span>All Orders</span>
                </div>
                <div class="nav-item" data-section="services-management">
                    <i class="fas fa-concierge-bell"></i>
                    <span> Services</span>
                </div>
                <div class="nav-item" data-section="staff-management">
                    <i class="fas fa-users"></i>
                    <span> Staff</span>
                </div>
            </div>

            <div class="shop-status">
                <form id="statusForm">
                    <input type="hidden" name="toggle_status" value="1">
                    <div class="status-toggle" id="statusToggle">
                        <div class="status-text">
                            <div class="status-indicator <?php echo $shop_data['status'] === 'closed' ? 'closed' : ''; ?>"></div>
                            <span>Shop <?php echo ucfirst($shop_data['status']); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </form>
            </div>

            <div class="logout-btn">
                <a href="../home/index.html"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Display success/error messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="header">
                <h1 class="page-title">Shop Dashboard</h1>
                <div class="user-profile">
                    <span><?php echo $owner_name; ?></span>
                    <img src="<?php echo $logo_src; ?>" alt="User" class="user-avatar">
                </div>
            </div>

            <!-- Dashboard Section -->
            <div class="section active" id="dashboard">
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3><?php echo $newAppointmentsCount; ?></h3>
                        <p>New Orders</p>
                    </div>
                    <div class="summary-card">
                        <h3>Rs. <?php echo number_format($todayEarnings); ?></h3>
                        <p>Today's Earnings</p>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo $completedAppointmentsCount; ?></h3>
                        <p>Completed Services</p>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo count($services); ?></h3>
                        <p>Total Services</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Shop Statistics</h3>
                    </div>
                    <div class="summary-cards">
                        <div class="summary-card">
                            <h3><?php echo $allTimeTotalServices; ?></h3>
                            <p>Total Services</p>
                        </div>
                        <div class="summary-card">
                            <h3>Rs. <?php echo number_format($allTimeEarnings); ?></h3>
                            <p>Total Earnings</p>
                        </div>
                        <div class="summary-card">
                            <h3><?php echo count($staff); ?></h3>
                            <p>Staff Members</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Orders</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allAppointments)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No appointments found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($allAppointments, 0, 5) as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['customer_name']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($appointment['service_type'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])) . ' at ' . date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                if ($appointment['status'] === 'pending')
                                                    $badgeClass = 'badge-warning';
                                                elseif ($appointment['status'] === 'confirmed')
                                                    $badgeClass = 'badge-info';
                                                elseif ($appointment['status'] === 'completed')
                                                    $badgeClass = 'badge-success';
                                                elseif ($appointment['status'] === 'cancelled')
                                                    $badgeClass = 'badge-danger';
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($appointment['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- New Orders Section -->
            <div class="section" id="new-orders">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">New Order Requests</h3>
                        <span class="badge badge-warning"><?php echo $newAppointmentsCount; ?> Pending</span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Requested Time</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($newAppointments)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No new appointment requests</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($newAppointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['customer_name']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($appointment['service_type'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])) . ' at ' . date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['customer_phone']); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="update_appointment" value="1">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <input type="hidden" name="action" value="confirmed">
                                                    <input type="hidden" name="customer_phone" value="<?php echo $appointment['customer_phone']; ?>">
                                                    <input type="hidden" name="customer_name" value="<?php echo $appointment['customer_name']; ?>">
                                                    <input type="hidden" name="service_type" value="<?php echo $appointment['service_type']; ?>">
                                                    <input type="hidden" name="appointment_date" value="<?php echo $appointment['appointment_date']; ?>">
                                                    <input type="hidden" name="appointment_time" value="<?php echo $appointment['appointment_time']; ?>">
                                                    <button type="submit" class="btn btn-success">Confirm</button>
                                                </form>
                                                <button type="button" class="btn btn-danger" onclick="openRejectionModal(
                                                    '<?php echo $appointment['id']; ?>',
                                                    '<?php echo $appointment['customer_phone']; ?>',
                                                    '<?php echo $appointment['customer_name']; ?>',
                                                    '<?php echo $appointment['service_type']; ?>',
                                                    '<?php echo $appointment['appointment_date']; ?>',
                                                    '<?php echo $appointment['appointment_time']; ?>'
                                                )">Reject</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Orders Section -->
            <div class="section" id="all-orders">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Orders</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allAppointments)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No appointments found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allAppointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['customer_name']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($appointment['service_type'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])) . ' at ' . date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['customer_phone']); ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                if ($appointment['status'] === 'pending')
                                                    $badgeClass = 'badge-warning';
                                                elseif ($appointment['status'] === 'confirmed')
                                                    $badgeClass = 'badge-info';
                                                elseif ($appointment['status'] === 'completed')
                                                    $badgeClass = 'badge-success';
                                                elseif ($appointment['status'] === 'cancelled')
                                                    $badgeClass = 'badge-danger';
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($appointment['status']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($appointment['status'] === 'confirmed'): ?>
                                                    <button type="button" class="btn btn-success" onclick="openCompleteModal(
                                                        '<?php echo $appointment['id']; ?>',
                                                        '<?php echo $appointment['customer_name']; ?>',
                                                        '<?php echo $appointment['customer_phone']; ?>',
                                                        '<?php echo $appointment['service_type']; ?>'
                                                    )">Mark Done</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Services Management Section -->
            <div class="section" id="services-management">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Manage Services</h3>
                        <button class="btn btn-primary" onclick="openServiceModal()">
                            <i class="fas fa-plus"></i> Add Service
                        </button>
                    </div>
                    
                    <div class="grid-cards">
                        <?php if (empty($services)): ?>
                            <p style="text-align: center; width: 100%; padding: 20px;">No services added yet.</p>
                        <?php else: ?>
                            <?php foreach ($services as $service): ?>
                                <div class="service-card">
                                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                    <div class="service-price">Rs. <?php echo number_format($service['price']); ?></div>
                                    <div class="service-duration">Duration: <?php echo $service['duration']; ?> mins</div>
                                    <?php if (!empty($service['description'])): ?>
                                        <p><?php echo htmlspecialchars($service['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="toggle-label">
                                        <span>Status:</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" <?php echo $service['is_active'] ? 'checked' : ''; ?> 
                                                onchange="toggleServiceStatus(<?php echo $service['id']; ?>, this.checked)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span><?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                    </div>
                                    
                                    <div class="card-actions">
                                        <button class="btn btn-info" onclick="editService(<?php echo $service['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_service" value="1">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this service?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Staff Management Section -->
            <div class="section" id="staff-management">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Manage Staff</h3>
                        <button class="btn btn-primary" onclick="openStaffModal()">
                            <i class="fas fa-plus"></i> Add Staff
                        </button>
                    </div>
                    
                    <div class="grid-cards">
                        <?php if (empty($staff)): ?>
                            <p style="text-align: center; width: 100%; padding: 20px;">No staff members added yet.</p>
                        <?php else: ?>
                            <?php foreach ($staff as $staffMember): ?>
                                <div class="staff-card">
                                    <?php if (!empty($staffMember['photo']) && file_exists($staffMember['photo'])): ?>
                                        <img src="<?php echo $staffMember['photo']; ?>" alt="Staff Photo" class="staff-photo">
                                    <?php else: ?>
                                        <div class="staff-photo" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user" style="font-size: 2rem; color: #999;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h3><?php echo htmlspecialchars($staffMember['name']); ?></h3>
                                    <div class="staff-role"><?php echo htmlspecialchars($staffMember['role']); ?></div>
                                    
                                    <?php if (!empty($staffMember['phone'])): ?>
                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($staffMember['phone']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($staffMember['email'])): ?>
                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staffMember['email']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="toggle-label" style="margin-top: 15px;">
                                        <span>Status:</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" <?php echo $staffMember['is_active'] ? 'checked' : ''; ?> 
                                                onchange="toggleStaffStatus(<?php echo $staffMember['id']; ?>, this.checked)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span><?php echo $staffMember['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                    </div>
                                    
                                    <div class="card-actions">
                                        <button class="btn btn-info" onclick="editStaff(<?php echo $staffMember['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_staff" value="1">
                                            <input type="hidden" name="staff_id" value="<?php echo $staffMember['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this staff member?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal" id="rejectionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Appointment</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="rejectionForm">
                <input type="hidden" name="update_appointment" value="1">
                <input type="hidden" name="appointment_id" id="modalAppointmentId">
                <input type="hidden" name="action" value="cancelled">
                <input type="hidden" name="customer_phone" id="modalCustomerPhone">
                <input type="hidden" name="customer_name" id="modalCustomerName">
                <input type="hidden" name="service_type" id="modalServiceType">
                <input type="hidden" name="appointment_date" id="modalAppointmentDate">
                <input type="hidden" name="appointment_time" id="modalAppointmentTime">

                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection</label>
                    <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3" required></textarea>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Complete Service Modal -->
    <div class="modal" id="completeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Complete Service</h3>
                <button class="close-modal" onclick="closeCompleteModal()">&times;</button>
            </div>
            <form method="POST" id="completeForm">
                <input type="hidden" name="complete_service" value="1">
                <input type="hidden" name="appointment_id" id="completeAppointmentId">
                <input type="hidden" name="customer_name" id="completeCustomerName">
                <input type="hidden" name="customer_phone" id="completeCustomerPhone">
                <input type="hidden" name="service_type" id="completeServiceType">
                
                <div class="form-group">
                    <label for="payment_amount">Payment Amount (Rs.) *</label>
                    <input type="number" id="payment_amount" name="payment_amount" required step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="online">Online Payment</option>
                    </select>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeCompleteModal()" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-success">Complete Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Service Modal -->
    <div class="modal" id="serviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="serviceModalTitle"><?php echo $editingService ? 'Edit Service' : 'Add Service'; ?></h3>
                <button class="close-modal" onclick="closeServiceModal()">&times;</button>
            </div>
            <form method="POST" id="serviceForm">
                <?php if ($editingService): ?>
                    <input type="hidden" name="update_service" value="1">
                    <input type="hidden" name="service_id" value="<?php echo $editingService; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_service" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="service_name">Service Name *</label>
                    <input type="text" id="service_name" name="name" value="<?php echo $serviceData['name'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="service_description">Description</label>
                    <textarea id="service_description" name="description" rows="3"><?php echo $serviceData['description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="service_price">Price (Rs.) *</label>
                        <input type="number" id="service_price" name="price" value="<?php echo $serviceData['price'] ?? ''; ?>" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="service_duration">Duration (minutes) *</label>
                        <input type="number" id="service_duration" name="duration" value="<?php echo $serviceData['duration'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="service_category">Category</label>
                    <select id="service_category" name="category">
                        <option value="hair" <?php echo (isset($serviceData['category']) && $serviceData['category'] === 'hair') ? 'selected' : ''; ?>>Hair</option>
                        <option value="skin" <?php echo (isset($serviceData['category']) && $serviceData['category'] === 'skin') ? 'selected' : ''; ?>>Skin</option>
                        <option value="nails" <?php echo (isset($serviceData['category']) && $serviceData['category'] === 'nails') ? 'selected' : ''; ?>>Nails</option>
                        <option value="spa" <?php echo (isset($serviceData['category']) && $serviceData['category'] === 'spa') ? 'selected' : ''; ?>>Spa</option>
                        <option value="other" <?php echo (isset($serviceData['category']) && $serviceData['category'] === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <?php if ($editingService): ?>
                <div class="form-group toggle-label">
                    <label for="service_status">Active</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="service_status" name="is_active" <?php echo ($serviceData['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <?php endif; ?>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeServiceModal()" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Staff Modal -->
    <div class="modal" id="staffModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="staffModalTitle"><?php echo $editingStaff ? 'Edit Staff Member' : 'Add Staff Member'; ?></h3>
                <button class="close-modal" onclick="closeStaffModal()">&times;</button>
            </div>
            <form method="POST" id="staffForm" enctype="multipart/form-data">
                <?php if ($editingStaff): ?>
                    <input type="hidden" name="update_staff" value="1">
                    <input type="hidden" name="staff_id" value="<?php echo $editingStaff; ?>">
                    <input type="hidden" name="existing_photo" value="<?php echo $staffData['photo'] ?? ''; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_staff" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="staff_photo">Photo</label>
                    <input type="file" id="staff_photo" name="photo" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="staff_name">Full Name *</label>
                    <input type="text" id="staff_name" name="name" value="<?php echo $staffData['name'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="staff_role">Role *</label>
                    <input type="text" id="staff_role" name="role" value="<?php echo $staffData['role'] ?? ''; ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_phone">Phone</label>
                        <input type="text" id="staff_phone" name="phone" value="<?php echo $staffData['phone'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="staff_email">Email</label>
                        <input type="email" id="staff_email" name="email" value="<?php echo $staffData['email'] ?? ''; ?>">
                    </div>
                </div>
                
                <?php if ($editingStaff): ?>
                <div class="form-group toggle-label">
                    <label for="staff_status">Active</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="staff_status" name="is_active" <?php echo ($staffData['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <?php endif; ?>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeStaffModal()" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Staff</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Navigation functionality
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function () {
                // Remove active class from all nav items
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));

                // Add active class to clicked item
                this.classList.add('active');

                // Hide all sections
                document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));

                // Show selected section
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');
                
                // Close sidebar on mobile after selection
                if (window.innerWidth <= 992) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });

        // Shop status toggle with AJAX for real-time updates
        document.getElementById('statusToggle').addEventListener('click', function () {
            const indicator = this.querySelector('.status-indicator');
            const statusText = this.querySelector('.status-text span');
            const currentStatus = indicator.classList.contains('closed') ? 'closed' : 'open';
            const newStatus = currentStatus === 'open' ? 'closed' : 'open';

            // Send AJAX request to update status
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `toggle_status=1&new_status=${newStatus}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update UI in real-time
                        if (data.new_status === 'open') {
                            indicator.classList.remove('closed');
                            statusText.textContent = 'Shop Open';
                        } else {
                            indicator.classList.add('closed');
                            statusText.textContent = 'Shop Closed';
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        });

        // Rejection modal functions
        function openRejectionModal(appointmentId, customerPhone, customerName, serviceType, appointmentDate, appointmentTime) {
            document.getElementById('modalAppointmentId').value = appointmentId;
            document.getElementById('modalCustomerPhone').value = customerPhone;
            document.getElementById('modalCustomerName').value = customerName;
            document.getElementById('modalServiceType').value = serviceType;
            document.getElementById('modalAppointmentDate').value = appointmentDate;
            document.getElementById('modalAppointmentTime').value = appointmentTime;

            document.getElementById('rejectionModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('rejectionModal').style.display = 'none';
        }

        // Complete service modal functions
        function openCompleteModal(appointmentId, customerName, customerPhone, serviceType) {
            document.getElementById('completeAppointmentId').value = appointmentId;
            document.getElementById('completeCustomerName').value = customerName;
            document.getElementById('completeCustomerPhone').value = customerPhone;
            document.getElementById('completeServiceType').value = serviceType;

            document.getElementById('completeModal').style.display = 'flex';
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').style.display = 'none';
        }

        // Service modal functions
        function openServiceModal() {
            document.getElementById('serviceModal').style.display = 'flex';
        }
        
        function closeServiceModal() {
            document.getElementById('serviceModal').style.display = 'none';
        }
        
        function editService(serviceId) {
            window.location.href = window.location.pathname + '?edit_service=' + serviceId + '#services-management';
        }
        
        function toggleServiceStatus(serviceId, isActive) {
            // This would typically update service status via AJAX
            console.log('Toggling service ' + serviceId + ' to ' + (isActive ? 'active' : 'inactive'));
            // Implement AJAX call to update service status
        }

        // Staff modal functions
        function openStaffModal() {
            document.getElementById('staffModal').style.display = 'flex';
        }
        
        function closeStaffModal() {
            document.getElementById('staffModal').style.display = 'none';
        }
        
        function editStaff(staffId) {
            window.location.href = window.location.pathname + '?edit_staff=' + staffId + '#staff-management';
        }
        
        function toggleStaffStatus(staffId, isActive) {
            // This would typically update staff status via AJAX
            console.log('Toggling staff ' + staffId + ' to ' + (isActive ? 'active' : 'inactive'));
            // Implement AJAX call to update staff status
        }

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target === document.getElementById('rejectionModal')) {
                closeModal();
            }
            if (event.target === document.getElementById('completeModal')) {
                closeCompleteModal();
            }
            if (event.target === document.getElementById('serviceModal')) {
                closeServiceModal();
            }
            if (event.target === document.getElementById('staffModal')) {
                closeStaffModal();
            }
        });

        // Auto open service modal if editing
        <?php if ($editingService): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openServiceModal();
        });
        <?php endif; ?>

        // Auto open staff modal if editing
        <?php if ($editingStaff): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openStaffModal();
        });
        <?php endif; ?>
    </script>
</body>
</html>