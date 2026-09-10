<?php
require_once "config.php";
$error = "";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <title>Armor Bangladesh Ltd. | Service Request</title>

    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <meta
        content="Armor Bangladesh, ATM Service Request, CRM Support, Banking Technology, Preventive Maintenance, Technical Support"
        name="keywords">

    <meta
        content="Request a service visit from Armor Bangladesh Ltd. for ATM, CRM, RCDM, POS, preventive maintenance, repair and technical support."
        name="description">


    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">


    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com">

    <link
        href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Roboto:wght@400;700&display=swap"
        rel="stylesheet">


    <!-- Icon Font Stylesheet -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css"
        rel="stylesheet">


    <!-- Libraries Stylesheet -->
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <link
        href="lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css"
        rel="stylesheet">


    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet">


    <!-- Main Stylesheet -->
    <link href="css/style.css?v=15" rel="stylesheet">

</head>


<body>


<!-- Topbar Start -->
<div class="container-fluid py-2 border-bottom d-none d-lg-block">

    <div class="container">

        <div class="row">

            <div class="col-md-6 text-center text-lg-start mb-2 mb-lg-0">

                <div class="d-inline-flex align-items-center">


                    <a
                        class="text-decoration-none text-body pe-3"
                        href="tel:+8809606991444">

                        <i class="bi bi-telephone me-2"></i>

                        +880 9606 991444

                    </a>


                    <span class="text-body">|</span>


                    <a
                        class="text-decoration-none text-body px-3"
                        href="mailto:info@armor-bd.com">

                        <i class="bi bi-envelope me-2"></i>

                        info@armor-bd.com

                    </a>


                </div>

            </div>


            <div class="col-md-6 text-center text-lg-end">

                <div class="d-inline-flex align-items-center">


                    <a class="text-body px-2" href="#!">
                        <i class="fab fa-facebook-f"></i>
                    </a>


                    <a class="text-body px-2" href="#!">
                        <i class="fab fa-twitter"></i>
                    </a>


                    <a class="text-body px-2" href="#!">
                        <i class="fab fa-linkedin-in"></i>
                    </a>


                    <a class="text-body px-2" href="#!">
                        <i class="fab fa-instagram"></i>
                    </a>


                    <a class="text-body ps-2" href="#!">
                        <i class="fab fa-youtube"></i>
                    </a>


                </div>

            </div>

        </div>

    </div>

</div>
<!-- Topbar End -->



<!-- Navbar Start -->
<div class="container-fluid sticky-top bg-white shadow-sm mb-5">

    <div class="container">

        <nav class="navbar navbar-expand-lg bg-white navbar-light py-3 py-lg-0">


            <a
                href="index.php"
                class="navbar-brand d-flex align-items-center">


                <img
                    src="img/armor.jpg"
                    alt="Armor Bangladesh Ltd."
                    class="navbar-logo">


                <h1 class="m-0 text-primary">

                    Armor Bangladesh Ltd.

                </h1>


            </a>


            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarCollapse"
                aria-controls="navbarCollapse"
                aria-expanded="false"
                aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>

            </button>


            <div
                class="collapse navbar-collapse"
                id="navbarCollapse">


                <div class="navbar-nav ms-auto py-0">


                    <a
                        href="index.php"
                        class="nav-item nav-link">

                        Home

                    </a>


                    <a
                        href="about.php"
                        class="nav-item nav-link">

                        About

                    </a>


                    <a
                        href="service.php"
                        class="nav-item nav-link">

                        Services

                    </a>


                    <a
                        href="price.php"
                        class="nav-item nav-link">

                        Solutions

                    </a>


                    <div class="nav-item dropdown">


                        <a
                            href="#"
                            class="nav-link dropdown-toggle active"
                            data-bs-toggle="dropdown">

                            Pages

                        </a>


                        <div class="dropdown-menu m-0">


                            <a href="blog.php" class="dropdown-item">
                                Blog Grid
                            </a>


                            <a href="detail.php" class="dropdown-item">
                                Blog Detail
                            </a>


                            <a href="team.php" class="dropdown-item">
                                Our Team
                            </a>


                            <a href="testimonial.php" class="dropdown-item">
                                Testimonials
                            </a>


                            <a
                                href="appointment.php"
                                class="dropdown-item active">

                                Service Request

                            </a>


                            <a href="search.php" class="dropdown-item">
                                Search
                            </a>


                        </div>

                    </div>


                    <a
                        href="contact.php"
                        class="nav-item nav-link">

                        Contact

                    </a>


                </div>

            </div>

        </nav>

    </div>

</div>
<!-- Navbar End -->



<!-- Service Request Start -->
<div class="container my-5">


    <div class="row g-4 align-items-stretch">


        <!-- Form -->
        <div class="col-12 col-lg-7">


            <div class="bg-white rounded p-5 shadow-sm h-100">


                <div class="mb-4">


                    <span
                        class="text-primary text-uppercase fw-semibold"
                        style="letter-spacing: 2px;">

                        Service Request

                    </span>


                    <h2 class="mt-2 mb-1">

                        Book a Technical Service Visit

                    </h2>


                    <p class="text-muted mb-0">

                        Schedule installation, preventive maintenance,
                        repair, inspection or technical support
                        for your banking equipment.

                    </p>


                </div>



                <form
                    method="post"
                    action="appointment.php">


                    <div class="row g-4">


                        <!-- Service Type -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Service Type

                            </label>


                            <select
                                name="service_type"
                                class="form-select bg-light border-0"
                                style="height: 55px;"
                                required>


                                <option value="" selected disabled>

                                    Select Service Type

                                </option>


                                <option value="ATM Installation">

                                    ATM Installation

                                </option>


                                <option value="CRM Installation">

                                    CRM / RCDM Installation

                                </option>


                                <option value="Preventive Maintenance">

                                    Preventive Maintenance

                                </option>


                                <option value="Repair">

                                    Repair & Troubleshooting

                                </option>


                                <option value="Technical Support">

                                    Technical Support

                                </option>


                                <option value="Inspection">

                                    Inspection & Performance Testing

                                </option>


                                <option value="Upgrade">

                                    Hardware / Software Upgrade

                                </option>


                            </select>


                        </div>



                        <!-- Equipment Type -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Equipment Type

                            </label>


                            <!--
                            Keep name="building_type" for compatibility
                            with the current backend/database.
                            -->

                            <select
                                name="building_type"
                                class="form-select bg-light border-0"
                                style="height: 55px;"
                                required>


                                <option value="" selected disabled>

                                    Select Equipment Type

                                </option>


                                <option value="ATM">
                                    ATM
                                </option>


                                <option value="CRM">
                                    CRM
                                </option>


                                <option value="RCDM">
                                    RCDM / CDM
                                </option>


                                <option value="POS">
                                    POS Terminal
                                </option>


                                <option value="Cash Counting Machine">
                                    Cash Counting Machine
                                </option>


                                <option value="Other">
                                    Other Equipment
                                </option>


                            </select>


                        </div>



                        <!-- Name -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Your Name

                            </label>


                            <input
                                type="text"
                                name="customer_name"
                                class="form-control bg-light border-0"
                                placeholder="Full Name"
                                style="height: 55px;"
                                required>


                        </div>



                        <!-- Phone -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Phone Number

                            </label>


                            <input
                                type="tel"
                                name="phone"
                                class="form-control bg-light border-0"
                                placeholder="+880..."
                                style="height: 55px;"
                                required>


                        </div>



                        <!-- Terminal ID -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Terminal ID

                            </label>


                            <input
                                type="text"
                                name="terminal_id"
                                class="form-control bg-light border-0"
                                placeholder="ATM / Terminal ID"
                                style="height: 55px;">


                        </div>



                        <!-- Location -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Service Location

                            </label>


                            <input
                                type="text"
                                name="location"
                                class="form-control bg-light border-0"
                                placeholder="Branch / Area / Location"
                                style="height: 55px;"
                                required>


                        </div>



                        <!-- Preferred Date -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Preferred Date

                            </label>


                            <input
                                type="date"
                                name="preferred_date"
                                class="form-control bg-light border-0"
                                style="height: 55px;"
                                required>


                        </div>



                        <!-- Preferred Time -->
                        <div class="col-md-6">


                            <label
                                class="form-label small text-muted">

                                Preferred Time

                            </label>


                            <input
                                type="time"
                                name="preferred_time"
                                class="form-control bg-light border-0"
                                style="height: 55px;"
                                required>


                        </div>



                        <!-- Additional Details -->
                        <div class="col-12">


                            <label
                                class="form-label small text-muted">

                                Problem / Service Details

                            </label>


                            <textarea
                                name="service_details"
                                class="form-control bg-light border-0"
                                rows="5"
                                placeholder="Describe the equipment issue or required service"></textarea>


                        </div>



                        <!-- Submit -->
                        <div class="col-12 pt-2">


                            <button
                                class="btn btn-primary w-100
                                       py-3 rounded-pill fw-semibold"
                                type="submit"
                                name="submit_service">

                                <i class="fa fa-calendar-check me-2"></i>

                                Schedule Service Visit

                            </button>


                        </div>


                    </div>


                </form>


            </div>


        </div>



        <!-- Information Card -->
        <div class="col-12 col-lg-5">


            <div
                class="bg-primary text-white
                       rounded p-5 shadow h-100">


                <span
                    class="text-uppercase fw-semibold"
                    style="letter-spacing: 1.5px;">

                    Technical Support

                </span>


                <h3 class="text-white mt-2 mb-3">

                    Need Technical Assistance?

                </h3>


                <p class="mb-4">

                    Contact Armor Bangladesh Ltd. for ATM,
                    CRM, RCDM, POS, preventive maintenance,
                    troubleshooting and field technical support.

                </p>



                <!-- Office -->
                <div class="d-flex align-items-start mb-4">


                    <i
                        class="fa fa-map-marker-alt
                               me-3 mt-1 fa-lg">
                    </i>


                    <div>


                        <strong>
                            Office
                        </strong>

                        <br>

                        <span class="opacity-75">
                            Dhaka, Bangladesh
                        </span>


                    </div>


                </div>



                <!-- Phone -->
                <div class="d-flex align-items-start mb-4">


                    <i
                        class="fa fa-phone-alt
                               me-3 mt-1 fa-lg">
                    </i>


                    <div>


                        <strong>
                            Phone
                        </strong>

                        <br>


                        <a
                            href="tel:+8809606991444"
                            class="text-white text-decoration-none opacity-75">

                            +880 9606 991444

                        </a>


                    </div>


                </div>



                <!-- Email -->
                <div class="d-flex align-items-start">


                    <i
                        class="fa fa-envelope
                               me-3 mt-1 fa-lg">
                    </i>


                    <div>


                        <strong>
                            Email
                        </strong>

                        <br>


                        <a
                            href="mailto:info@armor-bd.com"
                            class="text-white text-decoration-none opacity-75">

                            info@armor-bd.com

                        </a>


                    </div>


                </div>



                <hr class="border-light opacity-25 my-4">



                <h5 class="text-white mb-3">

                    Common Service Areas

                </h5>


                <ul class="list-unstyled mb-4">


                    <li class="mb-2">

                        <i class="fa fa-check-circle me-2"></i>

                        ATM Installation & Support

                    </li>


                    <li class="mb-2">

                        <i class="fa fa-check-circle me-2"></i>

                        CRM / RCDM Service

                    </li>


                    <li class="mb-2">

                        <i class="fa fa-check-circle me-2"></i>

                        Preventive Maintenance

                    </li>


                    <li class="mb-2">

                        <i class="fa fa-check-circle me-2"></i>

                        Repair & Troubleshooting

                    </li>


                    <li>

                        <i class="fa fa-check-circle me-2"></i>

                        Field Technical Support

                    </li>


                </ul>



                <a
                    href="contact.php"
                    class="btn btn-light
                           rounded-pill px-4 py-2 fw-semibold">

                    <i class="fa fa-envelope me-2"></i>

                    Contact Us

                </a>


            </div>


        </div>


    </div>


</div>
<!-- Service Request End -->



<!-- Footer Start -->
<div class="container-fluid bg-dark text-light mt-5 py-5">


    <div class="container py-5">


        <div class="row g-5">


            <!-- Company Info -->
            <div class="col-lg-3 col-md-6">


                <h4
                    class="d-inline-block text-primary
                           text-uppercase border-bottom
                           border-5 border-secondary mb-4">

                    Get In Touch

                </h4>


                <p class="mb-4">

                    Armor Bangladesh Ltd. provides banking technology
                    solutions including ATM, CRM, self-service banking
                    equipment, preventive maintenance,
                    troubleshooting and technical field support.

                </p>


                <p class="mb-2">

                    <i
                        class="fa fa-map-marker-alt
                               text-primary me-3">
                    </i>

                    Dhaka, Bangladesh

                </p>


                <p class="mb-2">

                    <i
                        class="fa fa-envelope
                               text-primary me-3">
                    </i>

                    info@armor-bd.com

                </p>


                <p class="mb-0">

                    <i
                        class="fa fa-phone-alt
                               text-primary me-3">
                    </i>

                    +880 9606 991444

                </p>


            </div>



            <!-- Quick Links -->
            <div class="col-lg-3 col-md-6">


                <h4
                    class="d-inline-block text-primary
                           text-uppercase border-bottom
                           border-5 border-secondary mb-4">

                    Quick Links

                </h4>


                <div class="d-flex flex-column justify-content-start">


                    <a class="text-light mb-2" href="index.php">
                        <i class="fa fa-angle-right me-2"></i>
                        Home
                    </a>


                    <a class="text-light mb-2" href="about.php">
                        <i class="fa fa-angle-right me-2"></i>
                        About Us
                    </a>


                    <a class="text-light mb-2" href="service.php">
                        <i class="fa fa-angle-right me-2"></i>
                        Our Services
                    </a>


                    <a class="text-light mb-2" href="price.php">
                        <i class="fa fa-angle-right me-2"></i>
                        Solutions
                    </a>


                    <a class="text-light mb-2" href="blog.php">
                        <i class="fa fa-angle-right me-2"></i>
                        Insights & Blog
                    </a>


                    <a class="text-light" href="contact.php">
                        <i class="fa fa-angle-right me-2"></i>
                        Contact Us
                    </a>


                </div>


            </div>



            <!-- Services -->
            <div class="col-lg-3 col-md-6">


                <h4
                    class="d-inline-block text-primary
                           text-uppercase border-bottom
                           border-5 border-secondary mb-4">

                    Our Services

                </h4>


                <div class="d-flex flex-column justify-content-start">


                    <a class="text-light mb-2" href="service.php">

                        <i class="fa fa-angle-right me-2"></i>

                        ATM Installation

                    </a>


                    <a class="text-light mb-2" href="service.php">

                        <i class="fa fa-angle-right me-2"></i>

                        CRM / RCDM Solutions

                    </a>


                    <a class="text-light mb-2" href="service.php">

                        <i class="fa fa-angle-right me-2"></i>

                        Preventive Maintenance

                    </a>


                    <a class="text-light mb-2" href="service.php">

                        <i class="fa fa-angle-right me-2"></i>

                        Repair & Troubleshooting

                    </a>


                    <a class="text-light" href="service.php">

                        <i class="fa fa-angle-right me-2"></i>

                        Technical Support

                    </a>


                </div>


            </div>



            <!-- Newsletter -->
            <div class="col-lg-3 col-md-6">


                <h4
                    class="d-inline-block text-primary
                           text-uppercase border-bottom
                           border-5 border-secondary mb-4">

                    Newsletter

                </h4>


                <p>

                    Subscribe for banking technology,
                    maintenance information and company updates.

                </p>


                <form>


                    <div class="input-group">


                        <input
                            type="email"
                            class="form-control p-3 border-0"
                            placeholder="Your Email Address">


                        <button
                            class="btn btn-primary"
                            type="submit">

                            Subscribe

                        </button>


                    </div>


                </form>



                <h6
                    class="text-primary
                           text-uppercase mt-4 mb-3">

                    Follow Us

                </h6>


                <div class="d-flex">


                    <a
                        class="btn btn-lg btn-primary
                               btn-lg-square rounded-circle me-2"
                        href="#">

                        <i class="fab fa-facebook-f"></i>

                    </a>


                    <a
                        class="btn btn-lg btn-primary
                               btn-lg-square rounded-circle me-2"
                        href="#">

                        <i class="fab fa-linkedin-in"></i>

                    </a>


                    <a
                        class="btn btn-lg btn-primary
                               btn-lg-square rounded-circle me-2"
                        href="#">

                        <i class="fab fa-twitter"></i>

                    </a>


                    <a
                        class="btn btn-lg btn-primary
                               btn-lg-square rounded-circle"
                        href="#">

                        <i class="fab fa-instagram"></i>

                    </a>


                </div>


            </div>


        </div>


    </div>


</div>
<!-- Footer End -->



<!-- Footer Bottom -->
<div
    class="container-fluid bg-dark text-light
           border-top border-secondary py-4">


    <div class="container">


        <div class="row g-5">


            <div class="col-md-6 text-center text-md-start">


                <p class="mb-md-0">

                    &copy;

                    <span class="text-primary">

                        Armor Bangladesh Ltd.

                    </span>

                    All Rights Reserved.

                </p>


            </div>



            <div class="col-md-6 text-center text-md-end">


                <p class="mb-0">

                    Expanding Possibilities Through Technology

                </p>


            </div>


        </div>


    </div>


</div>



<!-- Back to Top -->
<a
    href="#!"
    class="btn btn-lg btn-primary
           btn-lg-square back-to-top">

    <i class="bi bi-arrow-up"></i>

</a>



<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="lib/easing/easing.min.js"></script>

<script src="lib/waypoints/waypoints.min.js"></script>

<script src="lib/owlcarousel/owl.carousel.min.js"></script>

<script src="lib/tempusdominus/js/moment.min.js"></script>

<script src="lib/tempusdominus/js/moment-timezone.min.js"></script>

<script src="lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>

<!-- Template Javascript -->
<script src="js/main.js"></script>


</body>

</html>