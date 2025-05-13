<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: login.php");
    exit;
}

// Initialize variables with default values
$book_count = 0;
$order_count = 0;
$revenue = 0;
$user_data = [
    'fullname' => $_SESSION['fullname'] ?? 'Seller',
    'email' => $_SESSION['email'] ?? '',
    'phone' => '',
    'address' => ''
];

// Get user data
try {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Get book count
try {
    $book_query = "SELECT COUNT(*) as total_books FROM books WHERE seller_id = ?";
    $stmt = $conn->prepare($book_query);
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $book_count = $result->fetch_assoc()['total_books'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching book count: " . $e->getMessage());
}

// Get order count and revenue
try {
    $order_query = "SELECT COUNT(DISTINCT o.id) as total_orders, SUM(od.price * od.quantity) as total_revenue
                   FROM orders o
                   JOIN order_details od ON o.id = od.order_id
                   JOIN books b ON od.book_id = b.id
                   WHERE b.seller_id = ?";
    
    $stmt = $conn->prepare($order_query);
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $order_count = $data['total_orders'] ?? 0;
            $revenue = $data['total_revenue'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching order data: " . $e->getMessage());
}

// Get recent books
$recent_books = [];
try {
    $books_query = "SELECT * FROM books WHERE seller_id = ? ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($books_query);
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_books[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent books: " . $e->getMessage());
}

// Get recent orders
$recent_orders = [];
try {
    $orders_query = "SELECT o.id, o.order_date, o.total_amount, o.status, 
                    u.fullname as customer_name, b.title as book_title, od.quantity, od.price
                    FROM orders o
                    JOIN users u ON o.customer_id = u.id
                    JOIN order_details od ON o.id = od.order_id
                    JOIN books b ON od.book_id = b.id
                    WHERE b.seller_id = ?
                    ORDER BY o.order_date DESC
                    LIMIT 5";
    
    $stmt = $conn->prepare($orders_query);
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_orders[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Additional styles for seller dashboard */
        .welcome-banner {
            background-color: #3498db;
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .welcome-banner h2 {
            margin: 0 0 10px;
            font-size: 24px;
        }
        
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
        }
        
        .stat-icon.books {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .stat-icon.books i {
            color: #3498db;
        }
        
        .stat-icon.orders {
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .stat-icon.orders i {
            color: #2ecc71;
        }
        
        .stat-icon.revenue {
            background-color: rgba(241, 196, 15, 0.1);
        }
        
        .stat-icon.revenue i {
            color: #f1c40f;
        }
        
        .book-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-edit {
            background-color: #f39c12;
        }
        
        .btn-delete {
            background-color: #e74c3c;
        }
        
        .add-book-btn {
            display: inline-block;
            background-color: #2ecc71;
            color: #fff;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .add-book-btn i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Mega Books</h2>
                <span>Seller Panel</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="books.php">
                            <i class="fas fa-book"></i>
                            <span>Manage Books</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="profilead.php">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <header class="top-nav">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search for books...">
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_data['fullname']); ?></span>
                        <img src="../images/avatar.png" alt="User Avatar">
                        <div class="dropdown-menu">
                            <a href="seller_profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="welcome-banner">
                    <h2>Welcome, <?php echo htmlspecialchars($user_data['fullname']); ?>!</h2>
                    <p>Manage your books, track orders, and grow your business.</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon books">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Books</h3>
                            <p><?php echo $book_count; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Orders</h3>
                            <p><?php echo $order_count; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Revenue</h3>
                            <p>$<?php echo number_format($revenue, 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Books -->
                <div class="recent-section">
                    <h2>Recent Books</h2>
                    <a href="add_book.php" class="add-book-btn"><i class="fas fa-plus"></i> Add New Book</a>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Added On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_books) > 0): ?>
                                    <?php foreach ($recent_books as $book): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                            <td>$<?php echo number_format($book['price'], 2); ?></td>
                                            <td><?php echo $book['stock']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($book['created_at'])); ?></td>
                                            <td class="actions">
                                                <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn-edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_book.php?id=<?php echo $book['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this book?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No books found. <a href="add_book.php">Add your first book</a></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="recent-section">
                    <h2>Recent Orders</h2>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Book</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_orders) > 0): ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['book_title']); ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td>$<?php echo number_format($order['price'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>
                                                <span class="badge-table badge-<?php echo $order['status']; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No orders found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="dashboard.js"></script>
</body>
</html>