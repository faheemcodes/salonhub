<?php
$login = false;
$showError = false;
$showSuccess = false;

// Database connection
$connect = mysqli_connect('localhost', 'root', '', 'saloon');

if (!$connect) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $cnic = trim($_POST["cnic"]);
    $cnic = preg_replace('/\s+/', '', $cnic); // Remove any spaces

    // Use prepared statement to check vendor credentials directly from vendor_accounts
    $sql = "SELECT v.*, r.cnic AS shop_cnic 
            FROM vendor_accounts v
            INNER JOIN register_shop r ON v.shop_id = r.id
            WHERE v.username = ? AND v.cnic = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $cnic);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $num = mysqli_num_rows($result);

    if ($num == 1) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            // Fetch complete shop information
            $shop_query = "SELECT * FROM register_shop WHERE id = ?";
            $shop_stmt = mysqli_prepare($connect, $shop_query);
            mysqli_stmt_bind_param($shop_stmt, "i", $row['shop_id']);
            mysqli_stmt_execute($shop_stmt);
            $shop_result = mysqli_stmt_get_result($shop_stmt);
            $shop_data = mysqli_fetch_assoc($shop_result);
            
            $login = true;
            $showSuccess = "Login successful! Redirecting...";
            session_start();
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['shop_data'] = $shop_data; // Store complete shop data
            $_SESSION['vendor_id'] = $row['id'];
            $_SESSION['shop_status'] = $row['shop_status']; // Store shop status
            
            mysqli_stmt_close($shop_stmt);
            
            // Add JavaScript redirect with delay to show success message
            echo "<script>
                    setTimeout(function() {
                        window.location.href = 'VendorShop.php';
                    }, 2000);
                  </script>";
        } else {
            $showError = "Invalid password!";
        }
    } else {
        $showError = "Invalid username or CNIC!";
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Vendor Sign In</title>
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
            --danger: #dc3545;
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
            background: linear-gradient(135deg, #f5f7ff 0%, #e8f0fe 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .form-container {
            background: white;
            width: 100%;
            max-width: 450px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: fadeInUp 0.6s ease;
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
            font-size: 14px;
        }
        
        .form-content {
            padding: 30px;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 25px;
            color: var(--dark);
            font-size: 22px;
            font-weight: 600;
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
            box-shadow: 0 0 0 3px rgba(10, 113, 158, 0.15);
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
        
        .form-footer {
            text-align: center;
            margin-top: 25px;
            color: var(--secondary);
            font-size: 14px;
        }
        
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
            cursor: pointer;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 480px) {
            .form-content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
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
            <p>Vendor Portal</p>
        </div>
        
        <!-- Form Content -->
        <div class="form-content">
            <h2 class="form-title">Vendor Sign In</h2>
            
            <?php if($showError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $showError; ?>
                </div>
            <?php endif; ?>
            
            <?php if($showSuccess): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $showSuccess; ?>
                </div>
            <?php endif; ?>
            
            <form class="vendor-form" action="" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cnic">CNIC</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" id="cnic" name="cnic" class="form-control" 
                               placeholder="XXXXX-XXXXXXX-X" 
                               pattern="[0-9]{5}-[0-9]{7}-[0-9]{1}" 
                               title="Please enter CNIC in correct format (XXXXX-XXXXXXX-X)"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>

                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                
                <div class="form-footer">
                    Don't have an account? <a href="RegisterShop.php">Register your shop</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle the eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>