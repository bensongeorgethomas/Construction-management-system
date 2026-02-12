<!DOCTYPE html>
<html lang="en">
<head>
    <title>Project: City Center Retail Hub - ConstructPro</title>
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
                <img src="mall.jpg" alt="City Center Retail Hub" class="project-image">
                <h1>City Center Retail Hub</h1>
                <div class="project-stats">
                    <div class="stat">
                        <h3>Location</h3>
                        <p>Central Business District</p>
                    </div>
                    <div class="stat">
                        <h3>Completion Date</h3>
                        <p>March 2024</p>
                    </div>
                    <div class="stat">
                        <h3>Service</h3>
                        <p>Project Management</p>
                    </div>
                </div>
                <div class="project-description">
                    <p>The City Center Retail Hub is a large-scale commercial development that has become the city's premier shopping and entertainment destination. As the lead project management firm, ConstructPro was tasked with coordinating this complex, multi-faceted project, which involved numerous stakeholders, tenants, and contractors.</p>
                    <p>Our team provided comprehensive project management services, overseeing everything from budget and schedule management to quality control and tenant coordination. The hub features over 100 retail stores, a diverse food court, and a multiplex cinema. We successfully navigated the challenges of a large-scale urban development to deliver a vibrant and thriving retail center that serves the entire community.</p>
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
