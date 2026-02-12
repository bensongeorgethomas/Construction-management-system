<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safety Consulting - ConstructPro</title>
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
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1449157291145-7efd050a4d0e?q=80&w=2070&auto=format&fit=crop') center/cover;
            padding: 4rem 0;
            text-align: center;
            color: var(--white-bg);
        }
        .page-header h1 {
            font-size: 3rem;
            color: var(--white-bg);
        }

        /* Main Content */
        .main-content { padding: 4rem 0; background-color: var(--white-bg); }
        .main-content h2 { font-size: 2rem; color: var(--text-dark); margin-bottom: 1.5rem; }
        .main-content p { margin-bottom: 1.5rem; font-size: 1.1rem; }
        .main-content img { border-radius: 8px; margin-top: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }

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
                <a href="index.php" class="logo">Construct<span>Pro</span></a>
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
                <h1>Safety Consulting</h1>
            </div>
        </section>

        <!-- Main Content -->
        <section class="main-content">
            <div class="container">
                <h2>Prioritizing a Safe and Secure Work Environment</h2>
                <p>
                    At ConstructPro, safety is not just a policy; it's our culture. We believe that a safe project is a successful project. Our safety consulting services are designed to protect your most valuable assets: your people. We work proactively to identify potential hazards, mitigate risks, and ensure full compliance with all safety regulations.
                </p>
                <p>
                    Our certified safety professionals develop and implement comprehensive, site-specific safety plans. We conduct regular inspections, provide ongoing training for all personnel, and foster a culture of safety awareness and accountability on every job site. Our goal is to achieve zero incidents and ensure everyone returns home safely at the end of the day.
                </p>
                <img src="https://placehold.co/900x500/e2e8f0/334155?text=Safety+First+on+Site" alt="A safety inspection being conducted on a construction site">
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
