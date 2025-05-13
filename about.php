<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get cart count for the user if logged in
$cart_count = 0;
if ($is_logged_in) {
    try {
        $cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
        $stmt = $conn->prepare($cart_query);
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $cart_count = $row['total'] ? $row['total'] : 0;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Mega Book Store</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="about.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Global reset to prevent horizontal scrolling */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }
        
        /* Profile icon styles */
        .profile-icon {
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            transition: background-color 0.3s ease;
        }
        
        .profile-icon:hover {
            background-color: #2980b9;
        }
        
        /* Cart icon styles */
        .cart-icon {
            position: relative;
            margin-left: 15px;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Updated About Hero Section to match homepage style */
        .about-hero {
            height: 80vh;
            margin-top: 70px;
            position: relative;
            overflow: hidden;
        }
        
        .about-hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("asset/book1.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .about-hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
        }
        
        .about-hero-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            text-align: center;
            color: white;
            width: 90%;
            max-width: 800px;
            padding: 30px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
        }
        
        .about-hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
        }
        
        .about-hero-content p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .about-hero-content h1 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <h1>Mega Books</h1>
            </div>
            <div class="menu-toggle" id="mobile-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="index.php#services" class="nav-link">Services</a></li>
                <li><a href="explore.php" class="nav-link">Books</a></li>
                <li><a href="about.php" class="nav-link active">About</a></li>
                <li><a href="index.php#contact-form" class="nav-link">Contact</a></li>
                
                <?php if ($is_logged_in): ?>
                    <li>
                        <a href="cart.php" class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-count"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="customer_dashboard.php" class="profile-icon">
                            <i class="fas fa-user"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link login-btn">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Updated About Hero Section to match homepage style -->
    <header class="about-hero">
        <div class="about-hero-bg"></div>
        <div class="about-hero-content">
            <h1>About Mega Books</h1>
            <p>Learn about our journey, mission, and the team behind Mega Books</p>
        </div>
    </header>

    <!-- Our Story Section -->
    <section class="about-section">
        <div class="container">
            <div class="section-header">
                <h2>Our Story</h2>
                <p>How Mega Books came to be</p>
            </div>
            <div class="about-content">
                <div class="about-image">
                    <img src="asset/book4.jpg" alt="Mega Books Store">
                </div>
                <div class="about-text">
                    <h3>From a Small Shop to a Leading Book Store</h3>
                    <p>Mega Books started as a small bookshop in Hawassa in 2015. Founded by a group of passionate book lovers, our initial goal was to provide quality educational materials to students in the region.</p>
                    <p>Over the years, we expanded our collection to include fiction, non-fiction, and specialized academic texts. What began as a modest shop with just a few hundred books has now grown into one of the largest book retailers in the region with thousands of titles.</p>
                    <p>Our journey has been marked by a commitment to literacy, education, and the joy of reading. We believe that books have the power to transform lives, and we're dedicated to making them accessible to everyone.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Mission Section -->
    <section class="about-section bg-light">
        <div class="container">
            <div class="section-header">
                <h2>Our Mission</h2>
                <p>What drives us every day</p>
            </div>
            <div class="mission-values">
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h3>Promote Literacy</h3>
                    <p>We are committed to promoting literacy and a love for reading across all age groups. We believe that reading is fundamental to personal growth and societal development.</p>
                </div>
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h3>Accessible Knowledge</h3>
                    <p>We strive to make books and knowledge accessible to everyone through competitive pricing, rental options, and community outreach programs.</p>
                </div>
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Support Local Authors</h3>
                    <p>We are dedicated to supporting local authors and publishers by providing a platform for their work and connecting them with readers.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="about-section bg-light">
        <div class="container">
            <div class="section-header">
                <h2>What Our Customers Say</h2>
                <p>Testimonials from our valued readers</p>
            </div>
            <div class="testimonials">
                <div class="testimonial">
                    <div class="quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <p class="testimonial-text">Mega Books has transformed my reading experience. Their rental service is perfect for students like me who need access to textbooks without breaking the bank.</p>
                    <div class="testimonial-author">
                        <h4>Abebe Kebede</h4>
                        <p>University Student</p>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <p class="testimonial-text">I've been a loyal customer for years. The staff is knowledgeable and always ready to recommend great books. It's more than a bookstore; it's a community.</p>
                    <div class="testimonial-author">
                        <h4>Sara Hailu</h4>
                        <p>Book Enthusiast</p>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <p class="testimonial-text">As a local author, I appreciate how Mega Books supports and promotes Ethiopian literature. They've been instrumental in helping me reach readers.</p>
                    <div class="testimonial-author">
                        <h4>Daniel Tadesse</h4>
                        <p>Local Author</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

   

    <script src="index.js"></script>
</body>
</html>