<?php
// Start PHP processing at the top
$show_form = true; // Initialize as true to show the form by default
$success_message = '';
$error_message = '';

// Database connection
$connect = mysqli_connect('localhost', 'root', '', 'saloon');

if (!$connect) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/registerShop/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if form was submitted
if (isset($_POST['submit'])) {
    // Collect and sanitize input data
    $shop_name = mysqli_real_escape_string($connect, $_POST['shop_name']);
    $shop_address = mysqli_real_escape_string($connect, $_POST['shop_address']);
    $shop_description = mysqli_real_escape_string($connect, $_POST['shop_description']);
    $owner_name = mysqli_real_escape_string($connect, $_POST['owner_name']);
    $contact_number = mysqli_real_escape_string($connect, $_POST['contact_number']);
    $cnic = mysqli_real_escape_string($connect, $_POST['cnic']);
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $transaction_id = mysqli_real_escape_string($connect, $_POST['transaction_id']);

    // First check if CNIC already exists
    $check_cnic_query = "SELECT id FROM register_shop WHERE cnic = '$cnic'";
    $check_cnic_result = mysqli_query($connect, $check_cnic_query);

    if (mysqli_num_rows($check_cnic_result) > 0) {
        $error_message = "This CNIC is already registered. Please use a different CNIC or contact support if you believe this is an error.";
    } else {
        // Format WhatsApp number to +92XXXXXXXXXX (10 digits after country code)
        $whatsapp_number = $_POST['whatsapp_number'];
        $whatsapp_number = preg_replace('/[^0-9+]/', '', $whatsapp_number); // Keep numbers and +

        // Remove any existing country code for processing
        $clean_number = preg_replace('/^\+?92/', '', $whatsapp_number);

        // Validate and format the number
        if (preg_match('/^0/', $clean_number)) {
            $clean_number = substr($clean_number, 1); // Remove leading 0
        }

        // Check if we have exactly 10 digits
        if (strlen($clean_number) === 10 && ctype_digit($clean_number)) {
            $whatsapp_number = '+92' . $clean_number;
        } else {
            // Handle invalid number - maybe set to empty or show error
            $whatsapp_number = '';
            // Or: die("Invalid WhatsApp number - must be 10 digits after country code");
        }

        $whatsapp_number = mysqli_real_escape_string($connect, $whatsapp_number);

        // File upload handling
        $upload_errors = [];

        // Process file uploads (shop logo, owner photo, etc.)
        $shop_logo = processFileUpload('shop_logo', $upload_dir, $upload_errors);
        $owner_photo = processFileUpload('owner_photo', $upload_dir, $upload_errors);
        $payment_proof = processFileUpload('payment_proof', $upload_dir, $upload_errors);

        // Process multiple file uploads
        $shop_images = processMultipleFileUpload('shop_images', $upload_dir, $upload_errors, 5);

        if (empty($upload_errors)) {
            // Serialize arrays for database storage
            $shop_images_serialized = !empty($shop_images) ? serialize($shop_images) : '';

            // Insert data into database
            $query = "INSERT INTO register_shop (
                shop_name, shop_logo, shop_address, 
                shop_description, owner_name, owner_photo, 
                 contact_number, whatsapp_number, cnic, 
                email, payment_proof, transaction_id, shop_images
            ) VALUES (
                '$shop_name', '$shop_logo', '$shop_address',
                '$shop_description', '$owner_name', '$owner_photo', '$contact_number', '$whatsapp_number', '$cnic', '$email', '$payment_proof', '$transaction_id', '$shop_images_serialized'
            )";

            if (mysqli_query($connect, $query)) {
                $show_form = false; // Set to false only after successful submission
                $success_message = 'Shop registered successfully!';
            } else {
                $error_message = "Database error: " . mysqli_error($connect);
            }
        } else {
            $error_message = implode("<br>", $upload_errors);
        }
    }
}

// Close connection
mysqli_close($connect);

// Helper functions for file uploads
function processFileUpload($field, $upload_dir, &$errors)
{
    if (!empty($_FILES[$field]['name'])) {
        $file_name = basename($_FILES[$field]['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES[$field]['tmp_name'], $target_file)) {
            return $target_file;
        } else {
            $errors[] = "Failed to upload $field";
            return '';
        }
    }
    return '';
}

function processMultipleFileUpload($field, $upload_dir, &$errors, $limit = null)
{
    $uploaded_files = [];
    if (!empty($_FILES[$field]['name'][0])) {
        $file_count = count($_FILES[$field]['name']);
        if ($limit !== null && $file_count > $limit) {
            $errors[] = "Maximum $limit files allowed for $field";
            $file_count = $limit;
        }

        for ($i = 0; $i < $file_count; $i++) {
            $file_name = basename($_FILES[$field]['name'][$i]);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES[$field]['tmp_name'][$i], $target_file)) {
                $uploaded_files[] = $target_file;
            } else {
                $errors[] = "Failed to upload $field: $file_name";
            }
        }
    }
    return $uploaded_files;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Vendor Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600;700&display=swap');

        :root {
            --primary: #0a719eff;
            --primary-dark: #0c6a93ff;
            --secondary: #6c757d;
            --dark: #212529;
            --light: #f8f9fa;
            --success: #28a745;
            --border: #dee2e6;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .form-container {
            background: white;
            width: 100%;
            max-width: 900px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .header {
            background: var(--primary);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .logo-icon {
            font-size: 28px;
        }

        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .header p {
            opacity: 0.9;
            margin-top: 5px;
        }

        .form-content {
            padding: 30px;
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }

        .progress-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 70px;
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            color: var(--secondary);
        }

        .step-text {
            font-size: 12px;
            color: var(--secondary);
        }

        .progress-step.active .step-number {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .progress-step.active .step-text {
            color: var(--primary);
            font-weight: 500;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 18px;
            font-weight: 600;
        }

        .section-title i {
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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

        .form-group label.required::after {
            content: '*';
            color: #e53e3e;
            margin-left: 4px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.15);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
            padding: 15px;
        }

        .file-upload {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(74, 108, 247, 0.03);
        }

        .file-upload i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .file-upload p {
            color: var(--secondary);
            margin-bottom: 5px;
        }

        .file-upload small {
            color: var(--secondary);
            font-size: 12px;
        }

        .file-input {
            display: none;
        }

        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .image-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .payment-section {
            background: #f8f9ff;
            border-radius: var(--radius);
            padding: 20px;
            margin: 25px 0;
        }

        .payment-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .payment-header i {
            color: var(--primary);
            font-size: 20px;
        }

        .payment-header h3 {
            font-size: 18px;
            color: var(--dark);
        }

        .subscription-fee {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .subscription-fee .amount {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin: 5px 0;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .payment-method {
            background: white;
            border-radius: var(--radius);
            padding: 15px;
            border: 1px solid var(--border);
        }

        .payment-method h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .payment-method h4 i {
            color: var(--primary);
        }

        .payment-details p {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        .confirmation-message {
            text-align: center;
            padding: 40px 30px;
        }

        .confirmation-message i {
            font-size: 60px;
            color: var(--success);
            margin-bottom: 20px;
        }

        .confirmation-message h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .confirmation-message p {
            color: var(--secondary);
            margin-bottom: 10px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-content {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .progress-bar {
                display: none;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="form-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div class="logo-text">
                    SalonHub
                </div>
            </div>
            <p>Vendor Registration Portal</p>
        </div>

        <?php if ($show_form): ?>
            <!-- Form Content -->
            <div class="form-content" id="registrationForm">


                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form id="shopRegistrationForm" action="" method="post" enctype="multipart/form-data">
                    <!-- Shop Information Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-store"></i>
                            <h3>Shop Information</h3>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="shopName" class="required">Shop Name</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-signature input-icon"></i>
                                    <input type="text" id="shopName" name="shop_name" class="form-control"
                                        placeholder="Enter your salon name" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="shopAddress" class="required">Shop Address</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-map-marker-alt input-icon"></i>
                                    <input type="text" id="shopAddress" name="shop_address" class="form-control"
                                        placeholder="Enter your complete shop address" required>
                                </div>
                            </div>


                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="shopLogo" class="required">Shop Logo</label>
                                <div class="file-upload" onclick="document.getElementById('logoUpload').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Upload Shop Logo</p>
                                    <small>JPG, PNG or GIF (Max 5MB)</small>
                                    <input type="file" id="logoUpload" name="shop_logo" accept="image/*" class="file-input"
                                        required>
                                </div>
                                <div class="image-preview" id="logoPreview"></div>
                            </div>


                            <div class="form-group">
                                <label for="shopImages" class="required">Shop Images</label>
                                <div class="file-upload" onclick="document.getElementById('shopImagesUpload').click()">
                                    <i class="fas fa-images"></i>
                                    <p>Upload Shop Images</p>
                                    <small>Select up to 5 images (Max 5MB each)</small>
                                    <input type="file" id="shopImagesUpload" name="shop_images[]" accept="image/*"
                                        class="file-input" multiple required>
                                </div>
                                <div class="image-preview" id="shopImagesPreview"></div>
                            </div>

                        </div>
                        <div class="form-group">
                            <label for="shopDescription">Shop Description</label>
                            <textarea id="shopDescription" name="shop_description" class="form-control"
                                placeholder="Tell us about your salon services, specialties, etc."></textarea>
                        </div>


                    </div>

                    <!-- Owner & Staff Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-tie"></i>
                            <h3>Owner Information</h3>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="ownerName" class="required">Owner Name</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="ownerName" name="owner_name" class="form-control"
                                        placeholder="Enter owner's full name" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="ownerImage" class="required">Owner Photo</label>
                                <div class="file-upload" onclick="document.getElementById('ownerImageUpload').click()">
                                    <i class="fas fa-camera"></i>
                                    <p>Upload Owner Photo</p>
                                    <small>JPG, PNG or GIF (Max 5MB)</small>
                                    <input type="file" id="ownerImageUpload" name="owner_photo" accept="image/*"
                                        class="file-input" required>
                                </div>
                                <div class="image-preview" id="ownerImagePreview"></div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contactNumber" class="required">Contact Number</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" id="contactNumber" name="contact_number" class="form-control"
                                        placeholder="Enter shop contact number" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="whatsappNumber" class="required">WhatsApp Number</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-whatsapp input-icon"></i>
                                    <input type="tel" id="whatsappNumber" name="whatsapp_number" class="form-control"
                                        placeholder="Enter WhatsApp number">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cnic" class="required">CNIC</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-id-card input-icon"></i>
                                    <input type="text" id="cnic" name="cnic" class="form-control"
                                        placeholder="XXXXX-XXXXXXX-X" pattern="[0-9]{5}-[0-9]{7}-[0-9]{1}"
                                        title="CNIC must be in the format: XXXXX-XXXXXXX-X" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" class="form-control"
                                        placeholder="Enter shop email address" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription & Payment -->
                    <div class="payment-section">
                        <div class="payment-header">
                            <i class="fas fa-credit-card"></i>
                            <h3>Subscription Payment</h3>
                        </div>

                        <div class="subscription-fee">
                            <p>Monthly Subscription Fee</p>
                            <div class="amount">Rs. 2,000</div>
                            <p>Payable in advance every month</p>
                        </div>

                        <div class="payment-methods">
                            <div class="payment-method">
                                <h4><i class="fas fa-university"></i> Bank Transfer</h4>
                                <div class="payment-details">
                                    <p><strong>Bank Name:</strong> HBL</p>
                                    <p><strong>Account Title:</strong> Faheem</p>
                                    <p><strong>Account Number:</strong> 1234-5678901234</p>
                                </div>
                            </div>

                            <div class="payment-method">
                                <h4><i class="fas fa-mobile-alt"></i> EasyPaisa</h4>
                                <div class="payment-details">
                                    <p><strong>Number:</strong> 0300-1234567</p>
                                    <p><strong>Title:</strong> Faheem</p>
                                </div>
                            </div>

                            <div class="payment-method">
                                <h4><i class="fas fa-wallet"></i> JazzCash</h4>
                                <div class="payment-details">
                                    <p><strong>Number:</strong> 0300-1234567</p>
                                    <p><strong>Title:</strong> Faheem</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="transactionId" class="required">Transaction ID</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-receipt input-icon"></i>
                                    <input type="text" id="transactionId" name="transaction_id" class="form-control"
                                        placeholder="Enter transaction ID or reference number" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="paymentProof" class="required">Payment Proof</label>
                                <div class="file-upload" onclick="document.getElementById('paymentProofUpload').click()">
                                    <i class="fas fa-receipt"></i>
                                    <p>Upload Payment Proof</p>
                                    <small>Screenshot or scan of transaction</small>
                                    <input type="file" id="paymentProofUpload" name="payment_proof" accept="image/*"
                                        class="file-input" required>
                                </div>
                                <div class="image-preview" id="paymentProofPreview"></div>
                            </div>


                        </div>
                    </div>

                    <button type="submit" name="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Registration
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Confirmation Message -->
            <div class="confirmation-message" id="confirmationMessage">
                <i class="fas fa-check-circle"></i>
                <h3>Registration Successful!</h3>
                <p>Thank you for registering with SalonHub. Your information is being processed.</p>
                <p>You will receive confirmation via WhatsApp, email, or SMS once your account is activated.</p>
                <p>For any questions, please contact our support team.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Image preview functionality
        function setupImagePreview(uploadId, previewId) {
            const upload = document.getElementById(uploadId);
            const preview = document.getElementById(previewId);

            upload.addEventListener('change', function (e) {
                preview.innerHTML = '';

                if (upload.files.length > 1) {
                    for (let i = 0; i < Math.min(upload.files.length, 5); i++) {
                        const reader = new FileReader();
                        reader.onload = function (event) {
                            const img = document.createElement('img');
                            img.src = event.target.result;
                            preview.appendChild(img);
                        }
                        reader.readAsDataURL(upload.files[i]);
                    }
                } else {
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(upload.files[0]);
                }
            });
        }

        // Setup all image previews
        setupImagePreview('logoUpload', 'logoPreview');
        setupImagePreview('ownerImageUpload', 'ownerImagePreview');
        setupImagePreview('shopImagesUpload', 'shopImagesPreview');
        setupImagePreview('staffImagesUpload', 'staffImagesPreview');
        setupImagePreview('paymentProofUpload', 'paymentProofPreview');

        // Add latitude and longitude hidden fields (kept for PHP compatibility)
        const form = document.getElementById('shopRegistrationForm');
        const latInput = document.createElement('input');
        latInput.type = 'hidden';
        latInput.name = 'latitude';
        latInput.value = '0';
        form.appendChild(latInput);

        const lngInput = document.createElement('input');
        lngInput.type = 'hidden';
        lngInput.name = 'longitude';
        lngInput.value = '0';
        form.appendChild(lngInput);
    </script>
</body>

</html>