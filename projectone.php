<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project: Downtown Corporate Tower - ConstructPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b;
            --dark-bg: #1f2937;
            --white-bg: #ffffff;
            --text-dark: #111827;
            --text-medium: #4b5563;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; color: var(--text-medium); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 0 1.5rem; }
        a { text-decoration: none; color: inherit; }

        /* Header */
        .main-header { background-color: var(--white-bg); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .main-nav { display: flex; justify-content: space-between; align-items: center; height: 72px; }
        .logo { font-size: 1.5rem; font-weight: 800; }
        .logo span { color: var(--primary-color); }
        .nav-links { display: flex; align-items: center; gap: 2rem; }
        .nav-links a { font-weight: 500; }
        .nav-links a:hover { color: var(--primary-color); }
        .btn { background-color: var(--primary-color); color: var(--white-bg) !important; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 500; transition: background-color 0.3s ease; }
        .btn:hover { background-color: #d97706; }

        /* Project Details */
        .project-detail-section { padding: 4rem 0; background-color: var(--white-bg); }
        .project-image { width: 100%; height: 450px; object-fit: cover; border-radius: 8px; margin-bottom: 2rem; }
        .project-detail-section h1 { font-size: 2.5rem; margin-bottom: 1.5rem; }
        .project-stats { display: flex; gap: 2rem; margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #e5e7eb; }
        .stat { flex: 1; text-align: center; }
        .stat h3 { font-size: 1.2rem; color: var(--primary-color); }
        .stat p { font-weight: bold; font-size: 1.1rem; color: var(--text-dark); }
        .project-description p { margin-bottom: 1rem; font-size: 1.1rem; }

        /* Footer */
        .main-footer { background-color: var(--dark-bg); color: var(--white-bg); padding: 2rem 0; text-align: center; }
        .footer-bottom p { color: #9ca3af; }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <nav class="main-nav">
                <a href="index.php" class="logo">Construct<span>.</span></a>
                <div class="nav-links">
                    <a href="index.php#services">Services</a>
                    <a href="index.php#projects">Projects</a>
                    <a href="index.php#about">About</a>
                    <a href="index.php#contact" class="btn">Contact</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <section class="project-detail-section">
            <div class="container">
                <img src="corporate.jpg" alt="Downtown Corporate Tower" class="project-image">
                <h1>Downtown Corporate Tower</h1>
                <div class="project-stats">
                    <div class="stat">
                        <h3>Location</h3>
                        <p>Metropolis Downtown</p>
                    </div>
                    <div class="stat">
                        <h3>Completion Date</h3>
                        <p>June 2023</p>
                    </div>
                    <div class="stat">
                        <h3>Service</h3>
                        <p>General Contracting</p>
                    </div>
                </div>
                <div class="project-description">
                    <p>The Downtown Corporate Tower is a landmark 20-story office building that has reshaped the city skyline. This project involved the construction of a modern, high-tech commercial space designed for leading corporations. Our role as the general contractor was to oversee the entire construction process, ensuring the project was completed to the highest standards of quality, safety, and efficiency.</p>
                    <p>The tower features state-of-the-art facilities, including smart building technology, energy-efficient systems, and flexible office layouts. A key challenge was the tight urban location, which required meticulous logistical planning and coordination. We successfully managed these complexities to deliver the project on schedule and within budget, creating a premier business address in the heart of the city.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2024 ConstructPro. All Rights Reserved. | <a href="index.php">Back to Home</a></p>
            </div>
        </div>
    </footer>

</body>
</html>
