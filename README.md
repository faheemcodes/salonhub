# SalonHub - Beauty Salon Management System

SalonHub is a comprehensive web-based platform that connects beauty salon vendors with customers, providing an efficient booking and management system for salon services.

## 🚀 Overview

SalonHub is a three-tier web application that facilitates:
- **Vendors** to register and manage their beauty salons
- **Customers** to discover salons and book appointments
- **Administrators** to oversee the entire platform

## 👥 User Roles & Features

### 1. **End Users (Customers)**
- Browse available salons with search functionality
- View detailed salon information, services, and staff
- Book appointments with preferred time slots
- Receive WhatsApp notifications for booking confirmations

### 2. **Vendors (Salon Owners)**
- **Dashboard**: Real-time overview of business performance
- **Appointment Management**: Accept, reject, or complete customer bookings
- **Service Management**: Add, edit, and manage salon services
- **Staff Management**: Manage salon staff members
- **Real-time Status**: Open/close shop with instant updates
- **Payment Tracking**: Record service completions and payments
- **WhatsApp Integration**: Automated customer communication

### 3. **Administrators**
- **Vendor Approval**: Review and approve/reject salon registrations
- **Subscription Management**: Monitor and extend vendor subscriptions
- **Shop Management**: Oversee all registered salons
- **User Management**: Block/unblock vendor accounts
- **Payment Verification**: Validate payment proofs during registration

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Styling**: Custom CSS with responsive design
- **Icons**: Font Awesome
- **Sliders**: Swiper.js

## 📁 Project Structure

```
salon-hub/
├── admin/
│   └── AdminPanel.php          # Admin dashboard and management
├── vendor/
│   ├── signInSloonShop.php     # Vendor authentication
│   └── VendorShop.php          # Vendor dashboard
├── user/
│   └── index.php               # Customer booking interface
├── home/
│   └── index.html              # Landing page
└── uploads/                    # File uploads directory
```

## 🚀 Key Features

### Customer-Facing Features
- **Responsive Design**: Mobile-friendly interface
- **Advanced Search**: Find salons by name, location, or owner
- **Service Catalog**: Browse available beauty services
- **Real-time Booking**: Instant appointment scheduling
- **Salon Details**: Comprehensive shop information with galleries

### Vendor Features
- **Business Analytics**: Revenue tracking and service statistics
- **Order Management**: Streamlined appointment handling
- **Service Customization**: Flexible service and pricing management
- **Staff Coordination**: Team management capabilities
- **Automated Notifications**: WhatsApp integration for customer updates

### Admin Features
- **Multi-level Approval**: Comprehensive vendor onboarding process
- **Subscription Control**: Flexible billing and subscription management
- **Platform Monitoring**: Oversight of all business activities
- **Content Moderation**: Ensure platform quality and compliance

## ⚙️ Installation & Setup

1. **Requirements**
   - PHP 7.4+
   - MySQL 5.7+
   - Web server (Apache/Nginx)

2. **Database Setup**
   ```sql
   CREATE DATABASE saloon;
   -- Import provided SQL schema
   ```

3. **Configuration**
   - Update database credentials in all PHP files
   - Configure file upload paths
   - Set up WhatsApp integration credentials

4. **File Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/staff/
   ```

## 🔄 Workflow

### Vendor Registration Process
1. Vendor submits registration with payment proof
2. Admin reviews and approves application
3. System creates vendor account automatically
4. Vendor receives login credentials via WhatsApp
5. Vendor sets up shop profile and services

### Customer Booking Process
1. Customer searches and selects a salon
2. Views available services and staff
3. Books appointment with preferred time
4. Vendor confirms/rejects via dashboard
5. System sends confirmation via WhatsApp

## 🚧 Pending Admin Features

The following features are currently under development for the admin panel:

### 🔄 **Required Updates in Admin Panel**

1. **Payment Records Section**
   - 📊 View transaction history and revenue reports
   - 💳 Monitor payment status across all vendors
   - 📈 Generate financial analytics and insights

2. **Shop Management Enhancements**
   - 🏪 Comprehensive vendor performance metrics
   - ⭐ Customer review and rating system
   - 📋 Advanced filtering and search capabilities

3. **Subscription System Improvements**
   - 🔔 Automated renewal reminders
   - 📅 Advanced billing cycle management
   - 💰 Flexible pricing tier configurations

4. **Reporting & Analytics**
   - 📊 Dashboard with key platform metrics
   - 📈 Growth tracking and trend analysis
   - 📋 Custom report generation

5. **User Management**
   - 👥 Bulk operations for vendor management
   - 🔐 Advanced permission system
   - 📱 Mass notification capabilities

## 📱 WhatsApp Integration

The system integrates WhatsApp for:
- Vendor account approval notifications
- Appointment confirmations and rejections
- Service completion updates
- Customer support communications

## 🔒 Security Features

- Password hashing
- SQL injection prevention
- Session management
- File upload validation
- CSRF protection

## 📞 Support

For technical support or questions about the pending admin features, please contact the development team.

## 📄 License

This project is proprietary software. All rights reserved.

---

**Note**: The admin panel is currently being enhanced with additional features to provide more comprehensive platform management capabilities.