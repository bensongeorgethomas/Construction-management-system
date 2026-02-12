<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design-Build - ConstructPro</title>
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
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?q=80&w=2070&auto=format&fit=crop') center/cover;
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
                <h1>Design-Build</h1>
            </div>
        </section>

        <!-- Main Content -->
        <section class="main-content">
            <div class="container">
                <h2>A Unified Approach from Concept to Completion</h2>
                <p>
                    The Design-Build model offers a streamlined, efficient, and collaborative approach to construction. By combining the design and construction phases under a single contract, we create a unified team that works together from the outset. This integration fosters collaboration, innovation, and accountability, ultimately saving you time and money.
                </p>
                <p>
                    With ConstructPro as your Design-Build partner, you have one point of contact and one source of responsibility. This simplifies the process, reduces administrative burden, and ensures a cohesive project flow. Our team of architects, designers, and construction experts collaborate closely to deliver a project that meets your aesthetic, functional, and budgetary goals.
                </p>
                <img src="https://placehold.co/900x500/e2e8f0/334155?text=Collaborative+Design-Build+Process" alt="A team of architects and builders collaborating">
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
