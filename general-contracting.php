<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Contracting - Construct.</title>
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

        /* Page Header */
        .page-header {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1581092448342-5743ce7a3824?q=80&w=2070&auto=format&fit=crop') center/cover;
            padding: 4rem 0;
            text-align: center;
            color: var(--white-bg);
        }
        .page-header h1 {
            font-size: 3rem;
            color: var(--white-bg);
        }

        /* Main Content */
        .main-content {
            padding: 4rem 0;
            background-color: var(--white-bg);
        }
        .main-content h2 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }
        .main-content p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        .main-content ul {
            list-style-position: inside;
            margin-bottom: 1.5rem;
        }
        .main-content li {
            margin-bottom: 0.5rem;
        }
        .main-content img {
            border-radius: 8px;
            margin-top: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

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
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>General Contracting</h1>
            </div>
        </section>

        <!-- Main Content -->
        <section class="main-content">
            <div class="container">
                <h2>Comprehensive Site & Project Oversight</h2>
                <p>
                    As your general contractor, ConstructPro takes full responsibility for the daily operations on the construction site. We are the central point of contact, ensuring a seamless, coordinated, and efficient building process from start to finish. Our experienced team manages all aspects of the project, allowing you to focus on your business while we handle the complexities of construction.
                </p>
                <p>
                    Our approach is built on a foundation of clear communication, meticulous planning, and a commitment to quality craftsmanship. We work closely with architects, engineers, and subcontractors to bring your vision to life, ensuring every detail aligns with the project specifications and your expectations.
                </p>
                
                <h2>Our Key Responsibilities Include:</h2>
                <ul>
                    <li><strong>Subcontractor Management:</strong> We select, hire, and manage the best-qualified subcontractors and tradespeople for every phase of the project.</li>
                    <li><strong>Material & Equipment Procurement:</strong> We handle the sourcing and delivery of all necessary materials and equipment, ensuring quality and timeliness.</li>
                    <li><strong>Cost & Schedule Control:</strong> Our team diligently monitors the budget and project timeline, providing regular updates and proactively addressing any potential issues.</li>
                    <li><strong>Quality Assurance:</strong> We enforce strict quality standards throughout the construction process to deliver a final product that is built to last.</li>
                    <li><strong>Safety Management:</strong> Maintaining a safe and secure work site for all personnel is our top priority.</li>
                </ul>
                <img src="https://placehold.co/900x500/e2e8f0/334155?text=General+Contracting+in+Action" alt="A construction site being managed">
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2024 Construct. All Rights Reserved. | <a href="index.php">Back to Home</a></p>
            </div>
        </div>
    </footer>

</body>
</html>
