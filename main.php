<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Intelligent Student Award System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* 🌟 1. Navbar Background (Deep Navy) */
        .navbar-custom {
            background-color: #0f172a !important; /* Matches the hero section's gradient start */
            border-bottom: none !important; /* Removes white border for a seamless look */
            box-shadow: none !important; /* Removes shadow at the top for smooth transition */
        }
        
        /* 🌟 2. Hero Section (Smooth gradient from Navy to Bright Blue) */
        .hero-section {
            background: linear-gradient(180deg, #0f172a 0%, #1e3a8a 100%) !important;
            padding: 100px 0 120px 0 !important; /* Ensures sufficient height for gradient visibility */
            color: white;
            margin-top: -1px; /* Eliminates the 1px white line bug in some browsers */
        }

        /* 🌟 3. Fix Dropdown Menu Z-Index Layering */
        .dropdown-menu {
            z-index: 1050 !important; 
        }

        /* Dropdown Items Hover Animation */
        .dropdown-item.transition-all {
            transition: all 0.2s ease-in-out;
        }
        .dropdown-item.transition-all:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
    </style>
</head>
<body>

    <!-- Seamless Dark Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top py-3">
        <div class="container">
            <!-- System Logo -->
            <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2" href="index.php">
                <i class="fa-solid fa-graduation-cap text-warning fs-3"></i>
                <span style="letter-spacing: -0.5px;">EduRank</span>
            </a>
            
            <!-- Mobile Menu Toggle Button -->
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link active text-white fw-medium" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-medium" href="#about">About System</a></li>
                    <li class="nav-item"><a class="nav-link text-white fw-medium me-2" href="#categories">Award Categories</a></li>
                    
                    <!-- Login Dropdown Menu -->
                    <li class="nav-item dropdown">
                        <a class="btn btn-warning fw-bold px-4 rounded-pill text-dark dropdown-toggle" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login Portal
                        </a>
                        
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2 rounded-4" aria-labelledby="loginDropdown" style="min-width: 240px;">
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded-3 mb-1 fw-semibold d-flex align-items-center transition-all" href="login.php?type=student">
                                    <div class="bg-primary-subtle p-2 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-user-graduate text-primary fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-dark mb-0" style="font-size: 0.95rem;">Student Portal</div>
                                        <div class="text-muted" style="font-size: 0.7rem; font-weight: normal; margin-top: -2px;">For applicants</div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1 opacity-25"></li>
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded-3 mt-1 fw-semibold d-flex align-items-center transition-all" href="login.php?type=staff">
                                    <div class="bg-warning-subtle p-2 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-user-tie text-warning-emphasis fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-dark mb-0" style="font-size: 0.95rem;">Staff Access</div>
                                        <div class="text-muted" style="font-size: 0.7rem; font-weight: normal; margin-top: -2px;">PA & Committee</div>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Intelligent Student Award Management System</h1>
            <p class="lead mt-3 mx-auto" style="max-width: 700px; color: #cbd5e1;">
                An automated platform to evaluate student achievements, verify certificates, and assist the award committee using Gemini AI insights.
            </p>
            <div class="mt-4">
                <a href="login.php?type=student" class="btn btn-outline-warning btn-lg fw-bold px-4 me-2 rounded-pill shadow"> 
                    <i class="fa-solid fa-user-graduate me-1"></i> Student Portal 
                </a> 
                <a href="login.php?type=staff" class="btn btn-outline-light btn-lg px-4 rounded-pill shadow-sm">
                    <i class="fa-solid fa-user-tie me-1"></i> Staff Access
                </a>
            </div>
        </div>
    </section>

    <!-- About System Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="about-section p-4 p-md-5 bg-white rounded-4 shadow-sm">
                
                <div class="text-center mb-5">
                    <h6 class="text-primary text-uppercase fw-bold tracking-wide">Background & Purpose</h6>
                    <h2 class="fw-bold">About EduRank</h2>
                    <div class="mx-auto bg-primary rounded" style="height: 3px; width: 60px;"></div>
                </div>

                <div class="row align-items-center g-4 mb-5">
                    <div class="col-lg-7">
                        <h4 class="fw-bold text-dark mb-3">Transforming Student Recognition at JTMK PSMZA</h4>
                        <p class="text-muted">
                            Student excellence awards at <strong>JTMK, Polytechnic Sultan Mizan Zainal Abidin (PSMZA)</strong> recognize outstanding achievements across academics, leadership, innovation, sports, and community service. 
                        </p>
                        <p class="text-muted">
                            Previously, managing applications, verifying physical certificates, and calculating scores using spreadsheets was time-consuming and prone to human errors. <strong>EduRank</strong> digitizes and centralizes this entire process into a seamless web platform.
                        </p>
                        <p class="text-muted mb-0">
                            By combining <strong>automated formula scoring</strong> with <strong>Google Gemini AI decision support</strong>, the system provides transparent candidate rankings and qualitative insights while keeping final award decisions under the authority of the Evaluation Committee.
                        </p>
                    </div>

                    <div class="col-lg-5">
                        <div class="p-4 rounded-4 bg-light border">
                            <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-star me-2"></i>Why EduRank?</h5>
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-circle-check text-success fa-lg me-3"></i>
                                    <span><strong>Centralized Submissions:</strong> No more lost paper certificates or scattered spreadsheets.</span>
                                </li>
                                <li class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-circle-check text-success fa-lg me-3"></i>
                                    <span><strong>Instant Formula Calculation:</strong> Automated scoring based on official polytechnic criteria.</span>
                                </li>
                                <li class="d-flex align-items-center mb-0">
                                    <i class="fa-solid fa-circle-check text-success fa-lg me-3"></i>
                                    <span><strong>Gemini AI Insights:</strong> Smart candidate summaries for faster committee decision-making.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <hr class="my-5 text-muted opacity-25">

                <div class="text-center mb-4">
                    <h3 class="fw-bold">How It Works: 3 Key User Roles</h3>
                    <p class="text-muted small">A step-by-step verified workflow ensuring transparency and fairness.</p>
                </div>

                <div class="row g-4">
                    <!-- Workflow: Student -->
                    <div class="col-md-4">
                        <div class="p-4 rounded-4 bg-light border h-100 text-center position-relative">
                            <span class="badge bg-primary rounded-pill position-absolute top-0 start-50 translate-middle px-3 py-2 fs-6">User 1</span>
                            <div class="mt-2 mb-3">
                                <i class="fa-solid fa-user-graduate fa-3x text-primary"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Student</h5>
                            <p class="text-primary fw-semibold small mb-2">Applicant</p>
                            <p class="text-muted small mb-0">
                                Selects 1 of the 5 official award categories, fills out achievements (CGPA, leadership, activities), and uploads supporting PDF certificates for verification.
                            </p>
                        </div>
                    </div>

                    <!-- Workflow: Academic Advisor -->
                    <div class="col-md-4">
                        <div class="p-4 rounded-4 bg-light border h-100 text-center position-relative">
                            <span class="badge bg-warning text-dark rounded-pill position-absolute top-0 start-50 translate-middle px-3 py-2 fs-6">User 2</span>
                            <div class="mt-2 mb-3">
                                <i class="fa-solid fa-user-check fa-3x text-warning"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Academic Advisor (PA)</h5>
                            <p class="text-warning fw-semibold small mb-2">Verifier</p>
                            <p class="text-muted small mb-0">
                                Reviews uploaded certificates against original proof, verifies eligibility, and validates the automated base merit score before forwarding to the committee.
                            </p>
                        </div>
                    </div>

                    <!-- Workflow: Evaluation Committee -->
                    <div class="col-md-4">
                        <div class="p-4 rounded-4 bg-light border h-100 text-center position-relative">
                            <span class="badge bg-success rounded-pill position-absolute top-0 start-50 translate-middle px-3 py-2 fs-6">User 3</span>
                            <div class="mt-2 mb-3">
                                <i class="fa-solid fa-award fa-3x text-success"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Evaluation Committee</h5>
                            <p class="text-success fw-semibold small mb-2">Final Evaluator</p>
                            <p class="text-muted small mb-0">
                                Accesses verified applicants, reviews real-time leaderboards, utilizes <strong>Gemini AI candidate summaries</strong>, and selects the final <strong>Top 1 Winner</strong> per category.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Award Categories Section -->
    <section id="categories" class="py-5" style="background-color: #f8fafc;">
        <div class="container">
            <div class="text-center mb-5">
                <h6 class="text-primary text-uppercase fw-bold tracking-wide">APCP 02 Guidelines</h6>
                <h2 class="fw-bold">7 Award Categories</h2>
                <div class="mx-auto bg-primary rounded my-3" style="height: 3px; width: 60px;"></div>
                <p class="text-muted">Structured into 2 Major Awards and 5 Special Awards. Top 1 student will be awarded in each category.</p>
            </div>

            <!-- 🏆 Category 1: Main Awards (Anugerah Utama) -->
            <div class="text-center mb-4">
                <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill shadow-sm"><i class="fa-solid fa-trophy me-2"></i>Anugerah Utama</span>
            </div>
            <div class="row g-4 justify-content-center mb-5">
                <!-- Main Award 1 -->
                <div class="col-md-6 col-lg-5">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4" style="transition: transform 0.3s; border-top: 4px solid #2563eb !important;">
                        <div class="card-body">
                            <i class="fa-solid fa-user-graduate fa-3x text-primary mb-3"></i>
                            <h5 class="card-title fw-bold text-dark">Kecemerlangan Akademik & Kokurikulum</h5>
                            <p class="card-text text-muted small mt-2">Recognizes outstanding balance in academic excellence and co-curricular achievements.</p>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-2">Min CGPA &ge; 3.60</span>
                        </div>
                    </div>
                </div>
                <!-- Main Award 2 -->
                <div class="col-md-6 col-lg-5">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4" style="transition: transform 0.3s; border-top: 4px solid #2563eb !important;">
                        <div class="card-body">
                            <i class="fa-solid fa-lightbulb fa-3x text-primary mb-3"></i>
                            <h5 class="card-title fw-bold text-dark">Projek Terbaik</h5>
                            <p class="card-text text-muted small mt-2">Honors exceptional achievement in final year projects, research, and innovation.</p>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-2">Min CGPA &ge; 3.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🏅 Category 2: Special Awards (Anugerah Khas) -->
            <div class="text-center mb-4 mt-5">
                <span class="badge bg-secondary px-3 py-2 fs-6 rounded-pill shadow-sm"><i class="fa-solid fa-medal me-2"></i>Anugerah Khas</span>
            </div>
            <div class="row g-4 justify-content-center">
                <!-- Special Award 1: Industry -->
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <i class="fa-solid fa-industry fa-2x text-info mb-3"></i>
                            <h6 class="card-title fw-bold text-dark">Industri</h6>
                            <p class="card-text text-muted small" style="font-size: 0.8rem;">Exceptional technical skills and industry engagement.</p>
                            <span class="badge bg-light text-secondary border mt-1">Min CGPA &ge; 3.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Special Award 2: Leadership -->
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <i class="fa-solid fa-crown fa-2x text-warning mb-3"></i>
                            <h6 class="card-title fw-bold text-dark">Kepimpinan</h6>
                            <p class="card-text text-muted small" style="font-size: 0.8rem;">Exemplary leadership and student body contributions.</p>
                            <span class="badge bg-light text-secondary border mt-1">Min CGPA &ge; 3.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Special Award 3: Sports -->
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <i class="fa-solid fa-trophy fa-2x text-success mb-3"></i>
                            <h6 class="card-title fw-bold text-dark">Sukan</h6>
                            <p class="card-text text-muted small" style="font-size: 0.8rem;">Outstanding athletic performance and representation.</p>
                            <span class="badge bg-light text-secondary border mt-1">Min CGPA &ge; 3.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Special Award 4: Entrepreneurship -->
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <i class="fa-solid fa-briefcase fa-2x text-danger mb-3"></i>
                            <h6 class="card-title fw-bold text-dark">Keusahawanan</h6>
                            <p class="card-text text-muted small" style="font-size: 0.8rem;">Active involvement in business and entrepreneurship.</p>
                            <span class="badge bg-light text-secondary border mt-1">Min CGPA &ge; 3.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Special Award 5: Volunteerism -->
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-4 border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <i class="fa-solid fa-hand-holding-heart fa-2x text-danger-emphasis mb-3"></i>
                            <h6 class="card-title fw-bold text-dark">Sukarelawan</h6>
                            <p class="card-text text-muted small" style="font-size: 0.8rem;">Impactful contributions to community and volunteerism.</p>
                            <span class="badge bg-light text-secondary border mt-1">Min CGPA &ge; 3.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <!-- Bootstrap Core JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS: Navbar Scroll Effect -->
    <script>
        window.addEventListener('scroll', function() {
            var navbar = document.querySelector('.navbar-custom');
            // Add shadow and border when scrolling down
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                navbar.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            } else {
                // Remove shadow when at the very top
                navbar.style.boxShadow = 'none';
                navbar.style.borderBottom = 'none';
            }
        });
    </script>
</body>
</html>