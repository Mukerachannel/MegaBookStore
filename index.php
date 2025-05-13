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

// Process contact form submission
$contact_success = false;
$contact_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contact_submit'])) {
    // Get form data
    $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
    $email = sanitize_input($_POST['email']);
    $message = sanitize_input($_POST['message']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Validate inputs
    if (empty($email)) {
        $contact_error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = "Invalid email format";
    } elseif (empty($message)) {
        $contact_error = "Message is required";
    } else {
        // Save feedback directly with SQL instead of using the function
        try {
            $stmt = $conn->prepare("INSERT INTO feedback (user_id, name, email, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $name, $email, $message);
            
            if ($stmt->execute()) {
                $contact_success = true;
            } else {
                $contact_error = "Error saving your message. Please try again.";
            }
        } catch (Exception $e) {
            $contact_error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get user info if logged in
$user_name = '';
$user_email = '';
if ($is_logged_in) {
    $user = get_user_by_id($conn, $_SESSION['user_id']);
    if ($user) {
        $user_name = $user['fullname'];
        $user_email = $user['email'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mega Book Store</title>
    <link rel="stylesheet" href="index.css">
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
        
        /* Completely redesigned Hero Section */
        .hero {
            position: relative;
            height: 80vh;
            width: 100%;
            margin-top: 70px;
            overflow: hidden;
        }
        
        /* Fixed background approach */
        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            display: none; /* Hide all slides by default */
        }
        
        .hero-slide.active {
            opacity: 1;
            display: block; /* Show only active slide */
        }
        
        /* Use a div with fixed background instead of background-image */
        .slide-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }
        
        /* Dark overlay */
        .slide-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
        }
        
        .hero-content {
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
        
        .hero-content h2 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .hero-content h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero-content p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .catalog-btn {
            display: inline-block;
            background-color: #d4af37; /* Gold/amber color */
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: background-color 0.3s ease;
        }
        
        .catalog-btn:hover {
            background-color: #c19b26;
        }
        
        /* Slider Navigation Dots */
        .slider-dots {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            z-index: 20;
        }
        
        .slider-dot {
            width: 12px;
            height: 12px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            margin: 0 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .slider-dot.active {
            background-color: #fff;
        }
        
        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
        }
        
        /* Fix for container width */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            box-sizing: border-box;
        }
        
        /* Alert styles for contact form */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                <li><a href="index.php" class="nav-link active">Home</a></li>
                <li><a href="#services" class="nav-link">Services</a></li>
                <li><a href="explore.php" class="nav-link">Books</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="#contact-form" class="nav-link">Contact</a></li>
                
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
                        <a href="profile.php" class="profile-icon">
                            <i class="fas fa-user"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link login-btn">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Completely Redesigned Hero Section -->
    <header class="hero">
        <!-- Hero Slides -->
        <div class="hero-slide active">
            <div class="slide-bg" style="background-image: url('asset/book1.jpg');"></div>
            <div class="slide-overlay"></div>
        </div>
        
        <div class="hero-slide">
            <div class="slide-bg" style="background-image: url('asset/book2.jpg');"></div>
            <div class="slide-overlay"></div>
        </div>
        
        <div class="hero-slide">
            <div class="slide-bg" style="background-image: url('asset/book3.jpg');"></div>
            <div class="slide-overlay"></div>
        </div>
        
        <!-- Hero Content -->
        <div class="hero-content">
            <h2>Welcome to Mega Books</h2>
            <h1>New & Used Books</h1>
            <p>From applied literature to educational resources, we have a lot of textbooks to offer you. We provide only the best books for rent.</p>
            <a href="explore.php" class="catalog-btn">View Catalog</a>
        </div>
        
        <!-- Slider Navigation Dots -->
        <div class="slider-dots">
            <div class="slider-dot active" data-slide="0"></div>
            <div class="slider-dot" data-slide="1"></div>
            <div class="slider-dot" data-slide="2"></div>
        </div>
    </header>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <div class="section-header">
                <h2>Our Services</h2>
                <p>Discover what we offer to readers and publishers</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Book Sales</h3>
                    <p>Explore our wide selection of books across all genres with competitive pricing. We offer fiction, non-fiction, academic textbooks, children's books, and more from both local and international authors.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Competitive prices</li>
                        <li><i class="fas fa-check"></i> New releases and bestsellers</li>
                        <li><i class="fas fa-check"></i> Special discounts for members</li>
                    </ul>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3>Book Rentals</h3>
                    <p>Save money with our efficient rental services. Rent books for 15, 30, or 50 days at affordable rates. Perfect for students, researchers, or casual readers who need books temporarily.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Flexible rental periods</li>
                        <li><i class="fas fa-check"></i> Low daily rates (1 ETB per day)</li>
                        <li><i class="fas fa-check"></i> Easy extension options</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Books Section - Updated to fetch latest books from database -->
    <section class="featured-books">
        <div class="container">
            <div class="section-header">
                <h2>Featured Books</h2>
                <p>Our latest additions</p>
            </div>
            <div class="books-slider">
                <?php
                // Fetch latest books from database (most recently added)
                $latest_query = "SELECT b.*, c.name as category_name 
                                FROM books b 
                                LEFT JOIN categories c ON b.category_id = c.id 
                                WHERE b.status = 'available' AND b.stock > 0 
                                ORDER BY b.created_at DESC 
                                LIMIT 3";
                
                $latest_result = $conn->query($latest_query);
                
                if ($latest_result && $latest_result->num_rows > 0) {
                    while ($book = $latest_result->fetch_assoc()) {
                        // Sanitize data
                        $book_id = (int)$book['id'];
                        $title = sanitize_input($book['title']);
                        $author = sanitize_input($book['author']);
                        $price = number_format((float)$book['price'], 2);
                        
                        // Process the image path
                        if (!empty($book['image'])) {
                            // Check if the image path already contains a directory
                            if (strpos($book['image'], '/') !== false) {
                                $image_path = $book['image']; // Use the full path
                            } else {
                                $image_path = 'assets/' . $book['image']; // Prepend the directory
                            }
                        } else {
                            $image_path = 'images/default_book.jpg';
                        }
                        
                        // Display book card
                        echo '<div class="book-card">
                                <div class="book-cover">
                                    <img src="' . $image_path . '" alt="' . $title . '" 
                                         onerror="this.onerror=null; this.src=\'images/default_book.jpg\';">
                                </div>
                                <h3>' . $title . '</h3>
                                <p class="author">By ' . $author . '</p>
                                <p class="price">' . $price . ' ETB</p>
                              </div>';
                    }
                } else {
                    // Fallback if no books found in database
                    echo '<div class="book-card">
                            <div class="book-cover">
                                <img src="asset/book.jpg" alt="Sample Book">
                            </div>
                            <h3>Sample Book</h3>
                            <p class="author">By Sample Author</p>
                            <p class="price">150 ETB</p>
                          </div>';
                    
                    echo '<div class="book-card">
                            <div class="book-cover">
                                <img src="asset/book.jpg" alt="Sample Book">
                            </div>
                            <h3>Sample Book</h3>
                            <p class="author">By Sample Author</p>
                            <p class="price">150 ETB</p>
                          </div>';
                    
                    echo '<div class="book-card">
                            <div class="book-cover">
                                <img src="asset/book.jpg" alt="Sample Book">
                            </div>
                            <h3>Sample Book</h3>
                            <p class="author">By Sample Author</p>
                            <p class="price">150 ETB</p>
                          </div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Footer with Updated Contact Form -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section about">
                    <h2>Mega Books</h2>
                    <p>Your trusted partner for books sales and rental services.</p>
                    <div class="contact">
                        <p><i class="fas fa-map-marker-alt"></i> Sidama, Hawassa </p>
                        <p><i class="fas fa-phone"></i> +251921195638</p>
                        <p><i class="fas fa-envelope"></i> info@megabooks.com</p>
                    </div>
                    <div class="socials">
                        <a href="https://facebook.com"><i class="fab fa-facebook"></i></a>
                        <a href="https://x.com"><i class="fab fa-twitter"></i></a>
                        <a href="https://instagram.com"><i class="fab fa-instagram"></i></a>
                        <a href="https://linkedin.com"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="footer-section contact-form">
                    <h2>Contact Us</h2>
                    <?php if ($contact_success): ?>
                        <div class="alert alert-success">Thank you for your message! We will get back to you soon.</div>
                    <?php elseif (!empty($contact_error)): ?>
                        <div class="alert alert-danger"><?php echo $contact_error; ?></div>
                    <?php endif; ?>
                    
                    <form id="contact-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#contact-form">
                        <?php if (!$is_logged_in): ?>
                        <input type="text" name="name" placeholder="Your Name" value="<?php echo $user_name; ?>">
                        <?php endif; ?>
                        
                        <input type="email" name="email" placeholder="Your Email Address" value="<?php echo $user_email; ?>" <?php echo $is_logged_in ? 'readonly' : ''; ?> required>
                        <textarea name="message" placeholder="Your Message" required></textarea>
                        <button type="submit" name="contact_submit" class="btn primary-btn">Send</button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Mega Books. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Hero Slider Script
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.hero-slide');
            const dots = document.querySelectorAll('.slider-dot');
            let currentSlide = 0;
            
            // Function to change slide
            function goToSlide(slideIndex) {
                // Remove active class from all slides and dots
                slides.forEach(slide => slide.classList.remove('active'));
                dots.forEach(dot => dot.classList.remove('active'));
                
                // Add active class to current slide and dot
                slides[slideIndex].classList.add('active');
                dots[slideIndex].classList.add('active');
                
                currentSlide = slideIndex;
            }
            
            // Auto change slide every 5 seconds
            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                goToSlide(currentSlide);
            }
            
            // Set interval for auto slide change
            let slideInterval = setInterval(nextSlide, 5000);
            
            // Add click event to dots for manual navigation
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    clearInterval(slideInterval);
                    goToSlide(index);
                    slideInterval = setInterval(nextSlide, 5000);
                });
            });
            
            // Preload all slider images to prevent layout shifts
            function preloadImages() {
                const imageUrls = [
                    'asset/book1.jpg',
                    'asset/book2.jpg',
                    'asset/book3.jpg'
                ];
                
                imageUrls.forEach(url => {
                    const img = new Image();
                    img.src = url;
                });
            }
            
            // Call preload function
            preloadImages();
            
            // Mobile menu toggle
            const mobileMenu = document.getElementById('mobile-menu');
            const navMenu = document.querySelector('.nav-menu');
            
            if (mobileMenu) {
                mobileMenu.addEventListener('click', function() {
                    this.classList.toggle('active');
                    navMenu.classList.toggle('active');
                });
            }
            
            // Smooth scroll for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        // Close mobile menu if open
                        if (mobileMenu && mobileMenu.classList.contains('active')) {
                            mobileMenu.classList.remove('active');
                            navMenu.classList.remove('active');
                        }
                        
                        // Scroll to target
                        targetElement.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
    <script src="index.js"></script>
</body>
</html>