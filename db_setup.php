<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "megabooks";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists, if not create it
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);
echo "Connected to database successfully.<br>";

// Create password_resets table if not exists
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating password_resets table: " . $conn->error);
} else {
    echo "Password resets table created or already exists.<br>";
}

// Create users table if not exists - MODIFIED for unique phone with 10 digits
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(10) NOT NULL UNIQUE,
    address VARCHAR(255),
    role ENUM('admin', 'manager', 'seller', 'customer', 'pending') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating users table: " . $conn->error);
} else {
    echo "Users table created or already exists.<br>";
}

// Create feedback table for contact form submissions
$sql = "CREATE TABLE IF NOT EXISTS feedback (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) DEFAULT NULL,
    name VARCHAR(100),
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating feedback table: " . $conn->error);
} else {
    echo "Feedback table created or already exists.<br>";
}

// Check if admin user exists, if not create it
$admin_email = "admin@gmail.com";
$admin_password = password_hash("Admin@123", PASSWORD_DEFAULT); // More secure default password
$admin_phone = "0921195638"; // Default admin phone
$check_admin = "SELECT id FROM users WHERE email = '$admin_email'";
$result = $conn->query($check_admin);

if ($result->num_rows == 0) {
    // Create admin user
    $sql = "INSERT INTO users (fullname, email, password, phone, role, status) 
            VALUES ('Super Admin', '$admin_email', '$admin_password', '$admin_phone', 'admin', 'active')";

    if ($conn->query($sql) !== TRUE) {
        echo "Error creating admin user: " . $conn->error . "<br>";
    } else {
        echo "Admin user created successfully.<br>";
    }
} else {
    echo "Admin user already exists.<br>";
}

// Create categories table if not exists
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating categories table: " . $conn->error);
} else {
    echo "Categories table created or already exists.<br>";
}

// Add default categories if none exist
$check_categories = "SELECT COUNT(*) as count FROM categories";
$result = $conn->query($check_categories);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add default categories
    $categories = [
        "Fiction", "Non-Fiction", "Science", "History", "Biography", 
        "Self-Help", "Business", "Technology", "Art", "Cooking", 
        "Travel", "Children", "Education", "Religion", "Philosophy"
    ];
    
    foreach ($categories as $category) {
        $sql = "INSERT INTO categories (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category);
        
        if ($stmt->execute()) {
            echo "Added category: {$category}<br>";
        } else {
            echo "Error adding category {$category}: " . $stmt->error . "<br>";
        }
    }
    echo "Default categories added.<br>";
} else {
    echo "Categories already exist in the database.<br>";
}

// Create books table if not exists - MODIFIED to use category_id
$sql = "CREATE TABLE IF NOT EXISTS books (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    rent_price_per_day DECIMAL(10,2) DEFAULT 0,
    stock INT(11) NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT 'default_book.jpg',
    category_id INT(11),
    seller_id INT(11),
    is_rentable TINYINT(1) DEFAULT 0,
    popularity INT(11) DEFAULT 0,
    status ENUM('available', 'out_of_stock', 'discontinued') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating books table: " . $conn->error);
} else {
    echo "Books table created or already exists.<br>";
}

// Create orders table if not exists - ENHANCED with more details and payment fields
$sql = "CREATE TABLE IF NOT EXISTS orders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    customer_id INT(11) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'accepted', 'processing', 'shipped', 'delivered', 'rejected', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    shipping_phone VARCHAR(20) NOT NULL,
    shipping_name VARCHAR(100) NOT NULL,
    payment_method ENUM('telebirr', 'cbebirr', 'chapa', 'cash_on_delivery') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_transaction_id VARCHAR(255) DEFAULT NULL,
    payment_date DATETIME DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    viewed TINYINT(1) DEFAULT 0,
    seller_viewed TINYINT(1) DEFAULT 0,
    customer_viewed TINYINT(1) DEFAULT 0,
    updated_by INT(11) DEFAULT NULL,
    payment_mobile VARCHAR(20) DEFAULT NULL,
    payment_details TEXT,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating orders table: " . $conn->error);
} else {
    echo "Orders table created or already exists.<br>";
}

// Create order_items table if not exists
$sql = "CREATE TABLE IF NOT EXISTS order_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    book_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_rental TINYINT(1) DEFAULT 0,
    rental_days INT(11) DEFAULT NULL,
    return_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating order_items table: " . $conn->error);
} else {
    echo "Order items table created or already exists.<br>";
}

// Create payment_transactions table for detailed payment tracking
$sql = "CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    transaction_id VARCHAR(255) NOT NULL,
    payment_method ENUM('telebirr', 'cbebirr', 'chapa', 'cash_on_delivery') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payment_data TEXT,
    response_data TEXT,
    callback_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating payment_transactions table: " . $conn->error);
} else {
    echo "Payment transactions table created or already exists.<br>";
}

// Create chapa_transactions table for Chapa-specific payment details
$sql = "CREATE TABLE IF NOT EXISTS chapa_transactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    tx_ref VARCHAR(255) NOT NULL UNIQUE,
    checkout_url VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'ETB',
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    verification_status TINYINT(1) DEFAULT 0,
    callback_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating chapa_transactions table: " . $conn->error);
} else {
    echo "Chapa transactions table created or already exists.<br>";
}

// Create telebirr_transactions table for Telebirr-specific payment details
$sql = "CREATE TABLE IF NOT EXISTS telebirr_transactions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    out_trade_no VARCHAR(255) NOT NULL UNIQUE,
    mobile_number VARCHAR(20) NOT NULL,
    trade_no VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_note TEXT,
    response_data TEXT,
    callback_data TEXT,
    verify_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating telebirr_transactions table: " . $conn->error);
} else {
    echo "Telebirr transactions table created or already exists.<br>";
}

// Create system_settings table if not exists
$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating system_settings table: " . $conn->error);
} else {
    echo "System settings table created or already exists.<br>";
}

// Create password_history table to enforce unique passwords
$sql = "CREATE TABLE IF NOT EXISTS password_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating password_history table: " . $conn->error);
} else {
    echo "Password history table created or already exists.<br>";
}

// Insert default system settings if not exists
$check_settings = "SELECT COUNT(*) as count FROM system_settings";
$result = $conn->query($check_settings);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add default settings
    $settings = [
        ["site_name", "Mega Book Store", "Name of the website"],
        ["site_description", "Your one-stop destination for books and distribution services", "Website description"],
        ["contact_email", "info@megabooks.com", "Contact email address"],
        ["contact_phone", "+251921195638", "Contact phone number"],
        ["address", "Sidama, Hawassa", "Physical address"],
        ["currency", "ETB", "Currency used for pricing"],
        ["tax_rate", "15", "Tax rate percentage"],
        ["shipping_fee", "50", "Default shipping fee"],
        ["free_shipping_threshold", "500", "Order amount for free shipping"],
        ["chapa_secret_key", "CHAPA_SECRET_KEY", "Chapa payment gateway secret key"],
        ["chapa_public_key", "CHAPA_PUBLIC_KEY", "Chapa payment gateway public key"],
        ["telebirr_app_id", "TELEBIRR_APP_ID", "Telebirr app ID"],
        ["telebirr_app_key", "TELEBIRR_APP_KEY", "Telebirr app key"],
        ["telebirr_short_code", "TELEBIRR_SHORT_CODE", "Telebirr short code"],
        ["telebirr_public_key", "TELEBIRR_PUBLIC_KEY", "Telebirr public key"],
        ["payment_verification_required", "1", "Whether payment verification is required (1=yes, 0=no)"]
    ];
    
    foreach ($settings as $setting) {
        $sql = "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
        
        if ($stmt->execute()) {
            echo "Added setting: {$setting[0]}<br>";
        } else {
            echo "Error adding setting {$setting[0]}: " . $stmt->error . "<br>";
        }
    }
    echo "Default system settings added.<br>";
} else {
    echo "System settings already exist in the database.<br>";
}

// Create directories for book images if they don't exist
$directories = ['assets', 'images', 'uploads', 'uploads/books', 'logs', 'logs/payments'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir<br>";
        } else {
            echo "Failed to create directory: $dir<br>";
        }
    } else {
        echo "Directory already exists: $dir<br>";
    }
}

echo "Database setup completed successfully!";
$conn->close();
?>
