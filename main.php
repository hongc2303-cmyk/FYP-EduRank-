<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank AI - Intelligent Student Award System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2" href="index.php">
                <i class="fa-solid fa-graduation-cap text-warning fs-3"></i>
                <span>EduRank AI</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About System</a></li>
                    <li class="nav-item"><a class="nav-link" href="#categories">Award Categories</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-warning fw-bold px-4 rounded-pill text-dark" href="login.php">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login Portal
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section id="home" class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Smart Student Award Evaluation System</h1>
            <p class="lead mt-3 mx-auto" style="max-width: 700px;">
                An automated platform to evaluate student achievements, verify certificates, and assist the award committee using Gemini AI insights.
            </p>
            <div class="mt-4">
                <a href="login.php?type=student" class="btn btn-warning btn-lg fw-bold px-4 me-2 rounded-pill shadow">
                    <i class="fa-solid fa-user-graduate me-1"></i> Student Portal
                </a>
                <a href="login.php?type=staff" class="btn btn-outline-light btn-lg px-4 rounded-pill shadow-sm">
                    <i class="fa-solid fa-user-tie me-1"></i> Staff Access
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="py-5">
    <div class="container">
        <div class="about-section p-4 p-md-5 bg-white rounded-4 shadow-sm">
            
            <div class="text-center mb-5">
                <h6 class="text-primary text-uppercase fw-bold tracking-wide">Background & Purpose</h6>
                <h2 class="fw-bold">About EduRank AI</h2>
                <div class="mx-auto bg-primary rounded" style="height: 3px; width: 60px;"></div>
            </div>

            <div class="row align-items-center g-4 mb-5">
                <div class="col-lg-7">
                    <h4 class="fw-bold text-dark mb-3">Transforming Student Recognition at JTMK PSMZA</h4>
                    <p class="text-muted">
                        Student excellence awards at <strong>JTMK, Polytechnic Sultan Mizan Zainal Abidin (PSMZA)</strong> recognize outstanding achievements across academics, leadership, innovation, sports, and community service. 
                    </p>
                    <p class="text-muted">
                        Previously, managing applications, verifying physical certificates, and calculating scores using spreadsheets was time-consuming and prone to human errors. <strong>EduRank AI</strong> digitizes and centralizes this entire process into a seamless web platform.
                    </p>
                    <p class="text-muted mb-0">
                        By combining <strong>automated formula scoring</strong> with <strong>Google Gemini AI decision support</strong>, the system provides transparent candidate rankings and qualitative insights while keeping final award decisions under the authority of the Evaluation Committee.
                    </p>
                </div>

                <div class="col-lg-5">
                    <div class="p-4 rounded-4 bg-light border">
                        <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-star me-2"></i>Why EduRank AI?</h5>
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

    <section id="categories" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">6 Award Categories</h2>
                <p class="text-muted">Top 1 student will be awarded in each official category</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 col-lg-4">
                    <div class="card category-card h-100 text-center p-3">
                        <div class="card-body">
                            <i class="fa-solid fa-hand-holding-heart fa-3x text-danger mb-3"></i>
                            <h5 class="card-title fw-bold">Sukarelawan</h5>
                            <p class="card-text text-muted small">Volunteer & Community Services</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-4">
                    <div class="card category-card h-100 text-center p-3">
                        <div class="card-body">
                            <i class="fa-solid fa-briefcase fa-3x text-success mb-3"></i>
                            <h5 class="card-title fw-bold">Keusahawanan</h5>
                            <p class="card-text text-muted small">Entrepreneurship & Business Initiatives</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-4">
                    <div class="card category-card h-100 text-center p-3">
                        <div class="card-body">
                            <i class="fa-solid fa-crown fa-3x text-warning mb-3"></i>
                            <h5 class="card-title fw-bold">Kepimpinan</h5>
                            <p class="card-text text-muted small">Leadership & Student Body Representative</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-3">
                        <div class="card-body">
                            <i class="fa-solid fa-industry fa-3x text-info mb-3"></i>
                            <h5 class="card-title fw-bold">Industri</h5>
                            <p class="card-text text-muted small">Technical Skills & Industry Recognition</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-3">
                        <div class="card-body">
                            <i class="fa-solid fa-trophy fa-3x text-primary mb-3"></i>
                            <h5 class="card-title fw-bold">Sukan</h5>
                            <p class="card-text text-muted small">Sports Achievement & Athletic Representation</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card category-card h-100 text-center p-3">
                        <div class="card-body">
                            <i class="fa-solid fa-user-graduate fa-3x text-primary mb-3"></i>
                            <h5 class="card-title fw-bold">Anugerah Academic & Cocu</h5>
                            <p class="card-text text-muted small">Academic performance & self-confidence</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
    </footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>