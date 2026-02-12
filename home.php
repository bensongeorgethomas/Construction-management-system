<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construct - Premium Construction Services</title>
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="homestyle.css">
</head>
<body>

    <!-- Header & Navigation -->
    <header class="main-header">
        <div class="container">
            <nav class="main-nav">
                <a href="#" class="logo">Construct<span>.</span></a>
                <div class="nav-links">
                    <a href="email_otp_verification/index.php" class="btn btn-primary">Register as Worker</a>
                    <a href="email_otp_verification/clientregister.php" class="btn btn-outline">Client Sign Up</a>
                    <a href="login.php" class="btn btn-outline">Login</a>
                </div>
                <button class="menu-toggle"><i class="fas fa-bars"></i></button>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section id="home" class="hero">
            <div class="hero-content">
                <h1>Building Your Vision,<br><span>One Brick at a Time</span></h1>
                <p>Your trusted partner in construction, delivering quality and excellence from concept to completion.</p>
                <div class="hero-buttons">
                    <a href="email_otp_verification/index.php" class="btn btn-primary">Join Our Team</a>
                    <a href="#projects" class="btn btn-outline">View Projects</a>
                </div>
            </div>
        </section>

        <!-- Services Overview -->
        <section id="services" class="section-padding">
            <div class="container">
                <div class="section-header">
                    <span class="section-subtitle">What We Do</span>
                    <h2>Our Services</h2>
                    <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">We offer a comprehensive range of construction services to meet the needs of any project, large or small.</p>
                </div>
                
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-home"></i></div>
                        <h3>Residential Construction</h3>
                        <p>Custom homes and residential projects crafted with precision from foundation to finish.</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-building"></i></div>
                        <h3>Commercial Construction</h3>
                        <p>Modern office buildings, retail spaces, and large-scale commercial developments.</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-hammer"></i></div>
                        <h3>Renovation & Remodeling</h3>
                        <p>Transforming existing spaces into modern masterpieces with our expert renovation services.</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-drafting-compass"></i></div>
                        <h3>Design-Build Services</h3>
                        <p>Seamlessly integrated design and construction services under one roof.</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-tasks"></i></div>
                        <h3>Project Management</h3>
                        <p>Professional oversight ensuring your project is completed on time and on budget.</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-hard-hat"></i></div>
                        <h3>General Contracting</h3>
                        <p>Full-service contracting for construction projects of all sizes and scopes.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Projects Section -->
        <section id="projects" class="section-padding">
            <div class="container">
                <div class="section-header">
                    <span class="section-subtitle">Portfolio</span>
                    <h2>Featured Projects</h2>
                    <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">A glimpse into the quality and diversity of our completed work.</p>
                </div>
                
                <div class="projects-grid">
                    <a href="projectone.php" class="project-card">
                        <div class="img-wrapper">
                            <img src="corporate.jpg" alt="Modern Office Building">
                        </div>
                        <div class="project-content">
                            <h3>Downtown Corporate Tower</h3>
                            <p>A 20-story office building featuring state-of-the-art facilities and sustainable design.</p>
                            <span class="read-more">View Details <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </a>
                    
                    <a href="projecttwo.php" class="project-card">
                        <div class="img-wrapper">
                            <img src="villa.jpg" alt="Luxury Residential Complex">
                        </div>
                        <div class="project-content">
                            <h3>Lakeside Villa Community</h3>
                            <p>An exclusive gated community with 50 luxury villas and premium amenities.</p>
                            <span class="read-more">View Details <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </a>
                    
                    <a href="projectthree.php" class="project-card">
                        <div class="img-wrapper">
                            <img src="mall.jpg" alt="Retail Shopping Center">
                        </div>
                        <div class="project-content">
                            <h3>City Center Retail Hub</h3>
                            <p>A bustling retail complex with over 100 stores, a food court, and a cinema.</p>
                            <span class="read-more">View Details <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <h3 class="logo">Construct<span>.</span></h3>
                <p class="copyright">Building the future, restoring the past. &copy; <?php echo date("Y"); ?> All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // JavaScript for mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const navLinks = document.querySelector('.nav-links');

            menuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('active');
                
                // Animate hamburger to X (optional simple toggle)
                const icon = menuToggle.querySelector('i');
                if (navLinks.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!menuToggle.contains(event.target) && !navLinks.contains(event.target) && navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
        });
    </script>
</body>
</html>
