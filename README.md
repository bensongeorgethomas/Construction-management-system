# Construct. | Construction Management System 🏗️

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## 📋 Overview
**Construct.** is a robust, role-based construction management platform designed to streamline operations between Administrators, Workers, Clients, and Suppliers. It features secure authentication, real-time project tracking, inventory management, and automated notifications.

## ✨ Key Features

### 🔐 Security & Authentication
- **Role-Based Access Control (RBAC)**: Distinct portals for Admins, Workers, Clients, and Suppliers.
- **Secure Registration**: Email OTP verification for new accounts.
- **Data Protection**:
  - Environment-based configuration (`.env`).
  - SQL injection prevention via prepared statements.
  - CSRF protection on forms.
  - XSS mitigation strategies.
  - Secure session management.

### 🏗️ Project Management
- **Admin Dashboard**: Centralized view of project status, tasks, and resource allocation.
- **Task Tracking**: Assign tasks to workers, monitor progress, and update status.
- **Equipment Management**: Track inventory, usage, and maintenance schedules.
- **Workforce Management**: Manage worker profiles and performance.
- **Real-Time Attendance Monitoring**: 
  - View live timers for all active workers.
  - Track total hours worked per employee.
  - Login restricted to designated working hours.

### 👥 User Portals
- **Worker Portal**: 
  - **Smart Attendance**: Automated timer starts upon login (restricted to working hours).
  - Access task lists, submit daily reports, and view profile information.
- **Client Portal**: Visualize project progress, communicate with admins, and access project documents.
- **Supplier Portal**: Manage orders, update inventory, and handle supply chain interactions.

### 📢 Communication
- **Automated Notifications**: Email alerts for registration, status updates, and critical events.
- **SMS Integration**: Built-in capability for SMS and WhatsApp notifications (via Infobip).

## 🛠️ Technology Stack
- **Backend**: PHP 7.4+ (Vanilla)
- **Frontend**: HTML5, CSS3, JavaScript, Chart.js
- **Database**: MySQL 5.7+
- **Dependencies**: PHPMailer (via Composer)
- **Server**: Apache/Nginx (XAMPP/WAMP compatible)

## 🚀 Installation & Setup

### Prerequisites
- PHP >= 7.4
- MySQL Database
- Composer
- Web Server (Apache/Nginx)

### Step 1: Clone the Repository
```bash
git clone https://github.com/yourusername/construct-cms.git
cd construct-cms
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Configure Environment
1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
2. Edit `.env` and fill in your configuration:
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_NAME=your_database
   DB_USER=root
   DB_PASSWORD=your_password

   # Email Configuration (Required for OTP)
   SMTP_HOST=smtp.gmail.com
   SMTP_USERNAME=your_email@gmail.com
   SMTP_PASSWORD=your_app_password
   ```

### Step 4: Database Setup
1. Create a MySQL database (e.g., `construct_db`).
2. Import the provided SQL schema file into your database.

### Step 5: Run the Application
1. Configure your web server to point to the project directory.
2. Access the application via your browser (e.g., `http://localhost/construct`).

## � Directory Structure
```
/
├── email_otp_verification/   # Secure registration & OTP logic
├── includes/                 # Core functions, CSRF utilities, helpers
├── uploads/                  # User uploads (securely managed)
├── vendor/                   # Composer dependencies
├── *.php                     # Core application files (Controllers/Views)
├── .env                      # Environment configuration
└── composer.json             # Dependency definitions
```

## 🔒 Security Best Practices
- **Never commit `.env`**: This file contains sensitive credentials.
- **Use HTTPS**: Always run in production with HTTPS enabled (`SESSION_SECURE=true`).
- **File Permissions**: Ensure `uploads/` are writable but not executable.

## 🤝 Contributing
Contributions are welcome! Please fork the repository and submit a Pull Request.

## � License
This project is open-source and available under the [MIT License](LICENSE).

---
*Built with ❤️ for better construction management.*
