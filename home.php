<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #4f46e5;
            --background: #181f2a;
            --navbar-bg: #232b3e;
            --card-bg: #232b3e;
            --text-main: #f4f6fb;
            --text-muted: #b0b8c1;
            --accent: #8f5aff;
            --border-radius: 10px;
        }
        body {
            margin: 0;
            background: var(--background);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            width: 100vw;
            box-sizing: border-box;
            background: var(--navbar-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 64px;
        }
        .navbar .brand {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        .navbar .nav-links a {
            color: var(--text-main);
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: color 0.2s;
            position: relative;
        }
        .navbar .nav-links a::after {
            content: '';
            display: block;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
            position: absolute;
            left: 0;
            bottom: -4px;
        }
        .navbar .nav-links a:hover {
            color: var(--primary);
        }
        .navbar .nav-links a:hover::after {
            width: 100%;
        }
        .navbar .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 2rem;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .navbar {
                flex-wrap: wrap;
                padding: 1rem;
            }
            .navbar .nav-links {
                display: none;
                flex-direction: column;
                width: 100vw;
                background: var(--navbar-bg);
                position: absolute;
                left: 0;
                top: 64px;
                z-index: 999;
                border-bottom-left-radius: var(--border-radius);
                border-bottom-right-radius: var(--border-radius);
                box-shadow: 0 4px 24px rgba(0,0,0,0.15);
                align-items: flex-start;
                padding: 1rem 2rem;
            }
            .navbar .nav-links.open {
                display: flex;
            }
            .navbar .menu-toggle {
                display: block;
            }
        }
        .hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            text-align: center;
            padding: 3rem 1rem 2rem 1rem;
        }
        .hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .hero p {
            color: var(--text-muted);
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            max-width: 600px;
        }
        .hero .cta-btn {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 1rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(124,58,237,0.15);
            transition: background 0.2s, transform 0.2s;
            min-width: 180px;
            max-width: 100%;
            width: auto;
            display: inline-block;
        }
        .hero .cta-btn:hover {
            background: linear-gradient(90deg, var(--accent), var(--primary));
            transform: translateY(-2px) scale(1.03);
        }
        @media (max-width: 600px) {
            .hero .cta-btn {
                width: 100%;
                min-width: 0;
                font-size: 1rem;
                padding: 1rem 0.5rem;
            }
        }
        .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            margin: 3rem 0 2rem 0;
        }
        .feature-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem 1.5rem;
            min-width: 260px;
            max-width: 340px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.10);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .feature-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .feature-card h3 {
            margin-bottom: 0.7rem;
            font-size: 1.2rem;
            color: var(--text-main);
        }
        .feature-card p {
            color: var(--text-muted);
            font-size: 1rem;
        }
        .footer {
            text-align: center;
            color: var(--text-muted);
            padding: 2rem 1rem 1rem 1rem;
            font-size: 1rem;
        }
        .dashboard-choices {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
            margin: 2rem 0;
        }
        .dashboard-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 12px rgba(0,0,0,0.10);
            padding: 2.5rem 2rem 2rem 2rem;
            min-width: 220px;
            max-width: 320px;
            flex: 1 1 260px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .dashboard-card:hover {
            background: var(--primary-dark);
            color: #fff;
            transform: translateY(-6px) scale(1.04);
            box-shadow: 0 8px 32px rgba(124,58,237,0.15);
        }
        .dashboard-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .dashboard-card h2 {
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
            color: var(--text-main);
        }
        .dashboard-card p {
            color: var(--text-muted);
            font-size: 1rem;
        }
        @media (max-width: 900px) {
            .dashboard-choices {
                flex-direction: column;
                gap: 1.5rem;
            }
            .dashboard-card {
                max-width: 95vw;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="brand"><i class="fas fa-calendar-check"></i> Attendify</div>
        <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        <div class="nav-links" id="navLinks">
            <a href="home.php">Home</a>
            <a href="login.php">Login</a>
           
        </div>
    </nav>
    <section class="hero" id="heroSection">
        <h1>Welcome to Attendify</h1>
        <p>The modern, smart, and secure attendance management system for schools, colleges, and organizations. Track, manage, and analyze attendance with ease and style.</p>
        <a href="#" class="cta-btn" id="getStartedBtn">Get Started</a>
    </section>
    <section class="dashboard-choices" id="dashboardChoices" style="display:none;justify-content:center;align-items:center;flex-wrap:wrap;gap:2rem;margin:2rem 0;">
        <div class="dashboard-card" onclick="location.href='admin_login.php'">
            <i class="fas fa-tachometer-alt"></i>
            <h2>Admin Dashboard</h2>
            <p>Admin area for managing the system.</p>
        </div>
        <div class="dashboard-card" onclick="location.href='teacher_login.php'">
            <i class="fas fa-chalkboard-teacher"></i>
            <h2>Teachers Dashboard</h2>
            <p>Access teacher tools and attendance features.</p>
        </div>
        <div class="dashboard-card" onclick="location.href='student_login.php'">
            <i class="fas fa-user-graduate"></i>
            <h2>Students Dashboard</h2>
            <p>View your attendance and courses.</p>
        </div>
    </section>
    <footer class="footer">
        &copy; 2025 Attendify. All rights reserved.
    </footer>
    <script>
        // Navbar mobile toggle
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            // Prevent body scroll when menu is open (mobile)
            if (navLinks.classList.contains('open')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
        // Get Started button logic
        const getStartedBtn = document.getElementById('getStartedBtn');
        const dashboardChoices = document.getElementById('dashboardChoices');
        const heroSection = document.getElementById('heroSection');
        getStartedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            heroSection.style.display = 'none';
            dashboardChoices.style.display = 'flex';
            window.scrollTo({ top: dashboardChoices.offsetTop - 40, behavior: 'smooth' });
        });
    </script>
</body>
</html> 