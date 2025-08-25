# ACCESS System Setup Instructions
## Automated Community and Citizen E-Records Service System for PWD Affair Office at LGU Malasiqui

### Prerequisites
- **XAMPP** (Apache, MySQL, PHP) - Download from https://www.apachefriends.org/
- **VS Code** (recommended) - Download from https://code.visualstudio.com/
- **Web Browser** (Chrome, Firefox, Edge)

---

## Step 1: Install XAMPP

1. **Download XAMPP**
   - Go to https://www.apachefriends.org/
   - Download the latest version for your operating system
   - Run the installer with administrator privileges

2. **Install Components**
   - Select: Apache, MySQL, PHP, phpMyAdmin
   - Install to default directory: `C:\xampp` (Windows) or `/Applications/XAMPP` (Mac)

3. **Start Services**
   - Launch XAMPP Control Panel
   - Start **Apache** and **MySQL** services
   - Verify Apache runs on http://localhost
   - Verify MySQL runs (usually on port 3306)

---

## Step 2: Setup Project Files

1. **Copy Project Files**
   ```bash
   # Navigate to XAMPP htdocs directory
   cd C:\xampp\htdocs  # Windows
   cd /Applications/XAMPP/htdocs  # Mac

   # Create project folder
   mkdir access_system
   cd access_system
   ```

2. **Copy All Project Files**
   - Copy all PHP, HTML, CSS, and JavaScript files to `htdocs/access_system/`
   - Ensure folder structure:
   ```
   access_system/
   ├── index.php
   ├── php/
   │   ├── dbconnection.php
   │   ├── auth.php
   │   ├── security.php
   │   └── api.php
   ├── admin/
   │   └── dashboard.php
   ├── client/
   │   └── dashboard.php
   ├── subadmin/
   │   └── dashboard.php
   ├── database/
   │   └── access_system.sql
   └── uploads/ (create this directory)
   ```

3. **Create Upload Directory**
   ```bash
   mkdir uploads
   chmod 755 uploads  # Linux/Mac
   # For Windows, set full permissions via folder properties
   ```

---

## Step 3: Database Setup

1. **Access phpMyAdmin**
   - Open web browser
   - Go to: http://localhost/phpmyadmin
   - Login with username: `root` (usually no password by default)

2. **Import Database**
   - Click "New" to create a new database
   - Database name: `access_pwd_system`
   - Character set: `utf8mb4_general_ci`
   - Click "Create"

3. **Import SQL Schema**
   - Select the `access_pwd_system` database
   - Click "Import" tab
   - Choose file: `database/access_system.sql`
   - Click "Go" to import

4. **Verify Database Creation**
   - Check that all tables are created:
     - user_roles
     - users
     - citizen_records
     - applications
     - documents
     - notifications
     - services
     - sectors
     - And other tables...

---

## Step 4: Configure Database Connection

1. **Edit Database Configuration**
   - Open `php/dbconnection.php` in VS Code
   - Verify settings match your XAMPP configuration:
   ```php
   $host = 'localhost';
   $dbname = 'access_pwd_system';
   $username = 'root';
   $password = '';  // Usually empty for XAMPP
   ```

2. **Test Database Connection**
   - Save the file
   - Test by accessing: http://localhost/access_system/

---

## Step 5: VS Code Setup (Optional but Recommended)

1. **Install VS Code Extensions**
   - PHP Intelephense
   - PHP Debug
   - HTML CSS Support
   - Tailwind CSS IntelliSense
   - MySQL (for database management)

2. **Open Project in VS Code**
   ```bash
   cd C:\xampp\htdocs\access_system
   code .  # Opens project in VS Code
   ```

3. **Configure PHP Path**
   - Go to File → Preferences → Settings
   - Search for "php executable"
   - Set path to: `C:\xampp\php\php.exe` (Windows)

---

## Step 6: Test the System

1. **Access the Application**
   - Open browser and go to: http://localhost/access_system/
   - You should see the ACCESS login page

2. **Default Login Credentials**
   - **Super Admin:**
     - Username: `admin_pwd`
     - Password: `PWDoffice@123`

3. **Test Features**
   - Login as Super Admin
   - Check dashboard displays correctly
   - Verify navigation works
   - Test user registration forms

---

## Step 7: File Permissions (Linux/Mac Only)

```bash
# Set proper permissions
chmod -R 755 /path/to/access_system/
chmod -R 777 /path/to/access_system/uploads/
chown -R www-data:www-data /path/to/access_system/  # Ubuntu/Debian
```

---

## Step 8: Security Configuration

1. **Create .htaccess File** (Optional - for production)
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   
   # Protect sensitive files
   <Files "*.sql">
       Order allow,deny
       Deny from all
   </Files>
   
   <Files "dbconnection.php">
       Order allow,deny
       Deny from all
   </Files>
   ```

2. **Update PHP Settings** (Optional)
   - Edit `C:\xampp\php\php.ini`
   - Increase: `upload_max_filesize = 500M`
   - Increase: `post_max_size = 500M`
   - Restart Apache after changes

---

## Troubleshooting

### Common Issues:

1. **Database Connection Error**
   - Check MySQL service is running in XAMPP
   - Verify database name and credentials
   - Ensure PHP extension `pdo_mysql` is enabled

2. **File Upload Issues**
   - Check `uploads/` directory exists and has write permissions
   - Verify PHP upload settings in php.ini
   - Check file size limits

3. **Session Issues**
   - Clear browser cookies/cache
   - Check PHP session configuration
   - Restart Apache service

4. **Permission Errors**
   - Ensure web server has read/write access to project directory
   - Check file ownership and permissions

### Error Logs:
- **Apache Error Log**: `C:\xampp\apache\logs\error.log`
- **PHP Error Log**: `C:\xampp\php\logs\php_error_log`
- **MySQL Error Log**: `C:\xampp\mysql\data\[hostname].err`

---

## Default System Users

After database setup, these users are available:

1. **Super Admin**
   - Username: `admin_pwd`
   - Password: `PWDoffice@123`
   - Access: Full system control

2. **Test Users** (Create via registration)
   - Client users: Register through front-end
   - Sub-admin users: Register with sector selection

---

## Development Workflow

1. **Local Development**
   - Work on files in `htdocs/access_system/`
   - Test immediately at http://localhost/access_system/
   - Use browser developer tools for debugging

2. **Database Changes**
   - Use phpMyAdmin for database management
   - Export/import for backup and restore
   - Test queries in SQL tab

3. **File Structure**
   - Keep PHP logic in `/php/` directory
   - Separate admin, client, subadmin interfaces
   - Store uploads in `/uploads/` directory

---

## Production Deployment (Future)

When moving to production server:
1. Update database credentials
2. Enable HTTPS
3. Set proper file permissions
4. Configure backup system
5. Enable error logging
6. Remove debug information

---

## Support and Documentation

- **System Documentation**: See README.md
- **Database Schema**: Check database/access_system.sql
- **API Documentation**: Refer to php/api.php comments
- **Color Scheme**: #FFFFFF, #A3D1E0, #0077B3, #E6F7FF, #005B99, #A3C1DA

---

## Contact Information

For technical support or questions about the ACCESS system:
- PWD Affair Office - LGU Malasiqui
- Municipal Hall, Malasiqui, Pangasinan
- Contact: +63 75 632-8001