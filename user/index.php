<?php
// Database connection
class Database {
    private $host = 'localhost';
    private $user = 'root';
    private $password = '';
    private $dbname = 'saloon';
    private $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function query($sql, $params = []) {
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

    // Method to fetch all rows as associative array
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Method to fetch single row
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // Method to get last inserted ID
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
}

$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $shopId = $_POST['shop_id'];
    $customerName = $_POST['customer_name'];
    $customerPhone = $_POST['customer_phone'];
    $appointmentDate = $_POST['appointment_date'];
    $appointmentTime = $_POST['appointment_time'];
    $serviceType = $_POST['service_type'];
    $paymentMethod = $_POST['payment_method'];
    
    // Insert appointment into database
    $sql = "INSERT INTO appointments (shop_id, customer_name, customer_phone, appointment_date, appointment_time, service_type, payment_method, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $db->query($sql, [$shopId, $customerName, $customerPhone, $appointmentDate, $appointmentTime, $serviceType, $paymentMethod]);
    
    if ($stmt->affected_rows > 0) {
        $bookingSuccess = true;
        $appointmentId = $db->lastInsertId();
    } else {
        $bookingError = "Failed to book appointment. Please try again.";
    }
}

// Get all shops from register_shop table that have vendor accounts and are not blocked
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) {
    $searchTerm = "%$searchTerm%";
    $shops = $db->fetchAll("SELECT rs.*, va.shop_status FROM register_shop rs 
                           JOIN vendor_accounts va ON rs.cnic = va.cnic 
                           WHERE va.status = 'working' 
                           AND (rs.shop_name LIKE ? OR rs.shop_address LIKE ? OR rs.owner_name LIKE ?) 
                           ORDER BY rs.shop_name ASC", 
                          [$searchTerm, $searchTerm, $searchTerm]);
} else {
    $shops = $db->fetchAll("SELECT rs.*, va.shop_status FROM register_shop rs 
                           JOIN vendor_accounts va ON rs.cnic = va.cnic 
                           WHERE va.status = 'working'
                           ORDER BY rs.shop_name ASC");
}

// Get shop details for "See More" modal via AJAX
if (isset($_GET['get_shop_details']) && isset($_GET['cnic'])) {
    $cnic = $_GET['cnic'];
    $shopDetails = $db->fetchOne("SELECT rs.*, va.shop_status, va.status FROM register_shop rs 
                                JOIN vendor_accounts va ON rs.cnic = va.cnic 
                                WHERE rs.cnic = ?", [$cnic]);
    
    // Check if shop is blocked
    if ($shopDetails['status'] !== 'working') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'This shop is currently blocked']);
        exit;
    }
    
    // Process arrays stored as serialized data
    if (!empty($shopDetails['shop_images'])) {
        $shopDetails['shop_images'] = unserialize($shopDetails['shop_images']);
    }
    
    if (!empty($shopDetails['staff_photos'])) {
        $shopDetails['staff_photos'] = unserialize($shopDetails['staff_photos']);
    }
    
    header('Content-Type: application/json');
    echo json_encode($shopDetails);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600;700&display=swap');
        
        :root {
            --primary: rgb(25, 140, 189);
            --secondary: #333;
            --light: #f9f9f9;
            --dark: rgb(4, 66, 92);
            --accent: #ff6b6b;
            --light-bg: #f5f7fa;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--secondary);
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 16px 5%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--dark);
            text-transform: uppercase;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            width: 40%;
        }
        
        .search-bar input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e1e5e9;
            border-radius: var(--radius-md) 0 0 var(--radius-md);
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
        }
        
        .search-bar input:focus {
            border-color: var(--primary);
        }
        
        .search-bar button {
            padding: 12px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .search-bar button:hover {
            background: var(--dark);
        }
        
        /* Main Content */
        .main-content {
            padding: 30px 5%;
            max-width: 1280px;
            margin: 0 auto;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 24px;
            color: var(--dark);
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
        }
        
        /* Shop Cards */
        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }
        
        .shop-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .shop-header {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .shop-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            margin-right: 16px;
            background: white;
        }
        
        .shop-info h3 {
            font-size: 1.1rem;
            margin-bottom: 6px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .shop-address {
            color: #666;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .shop-details {
            padding: 20px;
        }
        
        .shop-contact {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .shop-status {
            text-align: center;
            margin-bottom: 16px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            background: #f8f9fa;
        }
        
        .status-open {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        
        .status-closed {
            color: var(--accent);
            background: rgba(220, 53, 69, 0.1);
        }
        
        .shop-actions {
            display: flex;
            gap: 12px;
        }
        
        .book-btn, .see-more-btn {
            flex: 1;
            padding: 12px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .book-btn {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .book-btn:disabled {
            background: #ced4da;
            cursor: not-allowed;
        }
        
        .see-more-btn {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .book-btn:hover:not(:disabled) {
            background: var(--dark);
        }
        
        .see-more-btn:hover {
            background: #f8f9fa;
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
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--light);
        }
        
        .modal-title {
            font-size: 1.4rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .close {
            color: #6c757d;
            font-size: 24px;
            font-weight: 400;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close:hover {
            color: var(--accent);
        }
        
        .modal-body {
            padding: 24px;
            max-height: calc(85vh - 120px);
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e1e5e9;
            border-radius: var(--radius-sm);
            font-size: 15px;
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(25, 140, 189, 0.15);
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .time-slot {
            padding: 10px;
            text-align: center;
            border: 1px solid #e1e5e9;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .time-slot:hover {
            border-color: var(--primary);
            background: rgba(25, 140, 189, 0.05);
        }
        
        .time-slot.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            background: var(--dark);
        }
        
        /* Shop Details Modal */
        .shop-details-modal .modal-content {
            max-width: 800px;
        }
        
        .shop-details-header {
            display: flex;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .shop-details-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-right: 20px;
            background: white;
        }
        
        .shop-details-info h2 {
            font-size: 1.6rem;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .shop-details-info p {
            color: #666;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .shop-details-section {
            margin-bottom: 24px;
            padding: 0 24px;
        }
        
        .shop-details-section h3 {
            font-size: 1.2rem;
            margin-bottom: 12px;
            color: var(--dark);
            font-weight: 600;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .shop-details-section p {
            color: #666;
            line-height: 1.6;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            min-width: 150px;
            color: var(--dark);
        }
        
        .detail-value {
            flex: 1;
            color: #666;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid var(--primary);
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Gallery Styles */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .gallery-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: transform 0.3s ease;
            background: #f8f9fa;
        }
        
        .gallery-image:hover {
            transform: scale(1.05);
        }
        
        .staff-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .staff-member {
            text-align: center;
        }
        
        .staff-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            margin-bottom: 8px;
            background: #f8f9fa;
        }
        
        /* Success/Error Messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .search-bar {
                width: 50%;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 16px;
                padding: 16px;
            }
            
            .search-bar {
                width: 100%;
            }
            
            .shops-grid {
                grid-template-columns: 1fr;
            }
            
            .time-slots {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .shop-details-header {
                flex-direction: column;
                text-align: center;
            }
            
            .shop-details-logo {
                margin-right: 0;
                margin-bottom: 16px;
            }
            
            .detail-row {
                flex-direction: column;
                margin-bottom: 12px;
            }
            
            .detail-label {
                min-width: auto;
                margin-bottom: 4px;
            }
            
            .shop-actions {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-scissors"></i>
            </div>
            <div class="logo-text">
                Salon<span>Hub</span>
            </div>
        </div>
        
        <div class="search-bar">
            <form action="" method="GET" style="display: flex; width: 100%;">
                <input type="text" name="search" placeholder="Search salons by name, location or owner..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Available Shops Section -->
        <div class="section">
            <h2 class="section-title">Available Salons</h2>
            
            <?php if (empty($shops)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-store" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i>
                    <p>No salons found matching your search or no salons have been activated yet.</p>
                </div>
            <?php else: ?>
                <div class="shops-grid">
                    <?php foreach ($shops as $shop): ?>
                        <div class="shop-card">
                            <div class="shop-header">
                                <img src="<?php echo !empty($shop['shop_logo']) && file_exists($shop['shop_logo']) ? htmlspecialchars($shop['shop_logo']) : 'https://via.placeholder.com/150'; ?>" 
                                     alt="<?php echo htmlspecialchars($shop['shop_name']); ?>" class="shop-logo">
                                <div class="shop-info">
                                    <h3><?php echo htmlspecialchars($shop['shop_name']); ?></h3>
                                    <div class="shop-address">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($shop['shop_address']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="shop-details">
                                <div class="shop-contact">
                                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($shop['contact_number']); ?></span>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($shop['owner_name']); ?></span>
                                </div>
                                
                                <div class="shop-status <?php echo $shop['shop_status'] === 'open' ? 'status-open' : 'status-closed'; ?>">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($shop['shop_status']); ?>
                                </div>
                                
                                <div class="shop-actions">
                                    <button class="book-btn" 
                                            onclick="openBookingModal(<?php echo $shop['id']; ?>, '<?php echo addslashes($shop['shop_name']); ?>')"
                                            <?php echo $shop['shop_status'] === 'closed' ? 'disabled' : ''; ?>>
                                        <i class="fas fa-calendar-plus"></i> Book
                                    </button>
                                    <button class="see-more-btn" onclick="getShopDetails('<?php echo $shop['cnic']; ?>')">
                                        <i class="fas fa-info-circle"></i> More
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Book Appointment</h2>
                <span class="close">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="bookingForm" method="POST">
                    <input type="hidden" id="shopId" name="shop_id">
                    <input type="hidden" name="book_appointment" value="1">
                    
                    <div class="form-group">
                        <label for="customerName">Full Name</label>
                        <input type="text" id="customerName" name="customer_name" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customerPhone">Phone Number</label>
                        <input type="tel" id="customerPhone" name="customer_phone" placeholder="Enter your phone number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointmentDate">Appointment Date</label>
                        <input type="date" id="appointmentDate" name="appointment_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Time Slot</label>
                        <div class="time-slots" id="timeSlots">
                            <!-- Time slots will be generated by JavaScript -->
                        </div>
                        <input type="hidden" id="appointmentTime" name="appointment_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="serviceType">Service Type</label>
                        <select id="serviceType" name="service_type" required>
                            <option value="">Select a service</option>
                            <option value="haircut">Haircut</option>
                            <option value="coloring">Hair Coloring</option>
                            <option value="styling">Hair Styling</option>
                            <option value="facial">Facial</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="paymentMethod">Payment Method</label>
                        <select id="paymentMethod" name="payment_method" required>
                            <option value="">Select payment method</option>
                            <option value="online">Online Payment</option>
                            <option value="physical">Pay at Salon</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="submit-btn">Book Appointment</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Shop Details Modal -->
    <div id="shopDetailsModal" class="modal shop-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Salon Details</h2>
                <span class="close" onclick="closeShopDetailsModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <div id="shopDetailsContent">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading shop details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Function to open booking modal
        function openBookingModal(shopId, shopName) {
            const modal = document.getElementById('bookingModal');
            const modalTitle = document.querySelector('#bookingModal .modal-title');
            const shopIdInput = document.getElementById('shopId');
            
            // Set shop ID and update modal title
            shopIdInput.value = shopId;
            modalTitle.textContent = `Book Appointment - ${shopName}`;
            
            // Show the modal
            modal.style.display = 'block';
            
            // Close modal when clicking on X
            document.querySelector('#bookingModal .close').onclick = function() {
                modal.style.display = 'none';
            }
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
            
            // Initialize date picker (minimum date is today)
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('appointmentDate').min = today;
            
            // Generate time slots
            generateTimeSlots();
        }
        
        // Function to generate time slots
        function generateTimeSlots() {
            const timeSlotsContainer = document.getElementById('timeSlots');
            timeSlotsContainer.innerHTML = '';
            
            // Generate time slots from 10 AM to 6 PM
            const startHour = 10;
            const endHour = 25;
            
            for (let hour = startHour; hour < endHour; hour++) {
                for (let minutes = 0; minutes < 60; minutes += 30) {
                    const timeString = `${hour.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                    const displayTime = `${hour > 12 ? hour - 12 : hour}:${minutes.toString().padStart(2, '0')} ${hour >= 12 ? 'PM' : 'AM'}`;
                    
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-slot';
                    timeSlot.textContent = displayTime;
                    timeSlot.dataset.time = timeString;
                    
                    timeSlot.onclick = function() {
                        // Remove selected class from all time slots
                        document.querySelectorAll('.time-slot').forEach(slot => {
                            slot.classList.remove('selected');
                        });
                        
                        // Add selected class to clicked time slot
                        this.classList.add('selected');
                        document.getElementById('appointmentTime').value = this.dataset.time;
                    };
                    
                    timeSlotsContainer.appendChild(timeSlot);
                }
            }
        }
        
        // Function to get shop details via AJAX
        function getShopDetails(cnic) {
            // Show the modal with loading spinner
            const modal = document.getElementById('shopDetailsModal');
            modal.style.display = 'block';
            
            // Clear previous content and show loading spinner
            document.getElementById('shopDetailsContent').innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading shop details...</p>
                </div>
            `;
            
            // Fetch shop details via AJAX
            fetch(`?get_shop_details=1&cnic=${encodeURIComponent(cnic)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Shop is blocked');
                    }
                    return response.json();
                })
                .then(shop => {
                    // Generate HTML for shop images
                    let shopImagesHTML = '<p>No images available</p>';
                    if (shop.shop_images && shop.shop_images.length > 0) {
                        shopImagesHTML = '<div class="image-gallery">';
                        shop.shop_images.forEach(image => {
                            shopImagesHTML += `<img src="${image}" alt="Shop Image" class="gallery-image">`;
                        });
                        shopImagesHTML += '</div>';
                    }
                    
                   
                    
                    // Format the shop details HTML
                    const shopDetailsHTML = `
                        <div class="shop-details-header">
                            <img src="${shop.shop_logo ? shop.shop_logo : 'https://via.placeholder.com/150'}" 
                                 alt="${shop.shop_name}" class="shop-details-logo">
                            <div class="shop-details-info">
                                <h2>${shop.shop_name}</h2>
                                <p><i class="fas fa-map-marker-alt"></i> ${shop.shop_address}</p>
                                <p><i class="fas fa-phone"></i> ${shop.contact_number}</p>
                                <p><i class="fas fa-envelope"></i> ${shop.email || 'N/A'}</p>
                            </div>
                        </div>
                        
                        <div class="shop-details-section">
                            <h3>About the Salon</h3>
                            <p>${shop.shop_description || 'No description provided.'}</p>
                        </div>
                        
                        <div class="shop-details-section">
                            <h3>Salon Images</h3>
                            ${shopImagesHTML}
                        </div>
                        
                        <div class="shop-details-section">
                            <h3>Owner Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Owner Name:</div>
                                <div class="detail-value">${shop.owner_name}</div>
                            </div>
                            ${shop.owner_photo ? `
                            <div style="margin-top: 15px; text-align: center;">
                                <img src="${shop.owner_photo}" alt="Owner Photo" style="max-width: 200px; border-radius: var(--radius-sm);">
                                <p style="margin-top: 5px;">Owner Photo</p>
                            </div>
                            ` : ''}
                        </div>
                    `;
                    
                    // Update the modal content
                    document.getElementById('shopDetailsContent').innerHTML = shopDetailsHTML;
                })
                .catch(error => {
                    console.error('Error fetching shop details:', error);
                    document.getElementById('shopDetailsContent').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 15px; color: var(--accent);"></i>
                            <p>${error.message || 'Failed to load shop details. Please try again.'}</p>
                        </div>
                    `;
                });
        }
        
        // Function to close shop details modal
        function closeShopDetailsModal() {
            document.getElementById('shopDetailsModal').style.display = 'none';
        }
        
        // Close shop details modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('shopDetailsModal');
            if (event.target == modal) {
                closeShopDetailsModal();
            }
            
            const bookingModal = document.getElementById('bookingModal');
            if (event.target == bookingModal) {
                bookingModal.style.display = 'none';
            }
        }
        
        // Show success/error message if exists
        <?php if (isset($bookingSuccess)): ?>
            setTimeout(() => {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong> Appointment booked successfully! Your appointment ID is <?php echo $appointmentId; ?>
                    </div>
                `;
                document.querySelector('.main-content').prepend(alertDiv);
                
                // Remove the alert after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }, 300);
        <?php elseif (isset($bookingError)): ?>
            setTimeout(() => {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error!</strong> <?php echo $bookingError; ?>
                    </div>
                `;
                document.querySelector('.main-content').prepend(alertDiv);
                
                // Remove the alert after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }, 300);
        <?php endif; ?>
    </script>
</body>
</html>