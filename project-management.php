<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management - ConstructPro</title>
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
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2070&auto=format&fit=crop') center/cover;
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
                <h1>Project Management</h1>
            </div>
        </section>

        <!-- Main Content -->
        <section class="main-content">
            <div class="container">
                <h2>Expert Guidance Every Step of the Way</h2>
                <p>
                    Effective project management is the cornerstone of any successful construction project. At ConstructPro, we provide expert planning, coordination, and control from inception to completion. Our dedicated project managers act as your trusted advisors, ensuring your project is delivered on time, within budget, and to the highest quality standards.
                </p>
                <p>
                    We utilize proven methodologies and the latest technology to manage every detail, from initial planning and resource allocation to risk management and final handover. Our proactive approach allows us to anticipate challenges, mitigate risks, and make informed decisions that keep the project on track.
                </p>
                <img src="https://placehold.co/900x500/e2e8f0/334155?text=Detailed+Project+Planning" alt="A project manager reviewing blueprints">
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
