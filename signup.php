<?php
require_once "config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $role  = trim($_POST["role"] ?? "");
    $name  = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $pass  = $_POST["password"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Allowed Roles
    |--------------------------------------------------------------------------
    */
    $allowed_roles = [
        "client",
        "technician",
        "manager",
        "admin"
    ];


    // Basic validation
    if ($role === "" || $name === "" || $email === "" || $pass === "") {

        $error = "All fields are required.";

    } elseif (!in_array($role, $allowed_roles, true)) {

        $error = "Invalid role selected.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($name) < 2 || strlen($name) > 100) {

        $error = "Name must be between 2 and 100 characters.";

    } elseif (strlen($pass) < 8) {

        $error = "Password must be at least 8 characters long.";

    } elseif (strlen($pass) > 72) {

        $error = "Password is too long.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Existing Email
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $check->execute([$email]);


            if ($check->fetch()) {

                $error = "An account with this email already exists. Please login.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Hash Password
                |--------------------------------------------------------------------------
                */

                $hash = password_hash(
                    $pass,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | Create Account With Selected Role
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (name, email, password_hash, role)
                    VALUES (?, ?, ?, ?)"
                );


                if ($stmt->execute([
                    $name,
                    $email,
                    $hash,
                    $role
                ])) {

                    header(
                        "Location: login.php?signup=success"
                    );

                    exit;

                } else {

                    $error = "Signup failed. Please try again.";

                }

            }

        } catch (PDOException $e) {

            $error = "Something went wrong. Please try again later.";

        }

    }

}
?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <title>Create Account | Armor Bangladesh Ltd.</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">


    <meta
        name="description"
        content="Create an account for Armor Bangladesh Ltd. banking technology service management system.">


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        rel="stylesheet">


    <style>

        :root {

            --brand: #F58220;
            --brand-hover: #D96C13;

            --dark: #202020;
            --gray: #6B6B6B;

            --soft: #FFF8F2;

        }


        body {

            min-height: 100vh;

            display: flex;
            align-items: center;

            background:
                linear-gradient(
                    135deg,
                    #ffffff,
                    var(--soft)
                );

            color: var(--dark);

        }


        .signup-card {

            border: 0;

            border-radius: 18px;

            box-shadow:
                0 15px 40px
                rgba(0, 0, 0, 0.10);

        }


        .brand-logo {

            max-width: 180px;
            max-height: 80px;

            object-fit: contain;

            display: block;

            margin-left: auto;
            margin-right: auto;

        }


        .brand-icon {

            width: 58px;
            height: 58px;

            border-radius: 15px;

            background:
                rgba(245, 130, 32, 0.12);

            display: flex;
            align-items: center;
            justify-content: center;

            color: var(--brand);

            font-size: 24px;

        }


        .form-control,
        .form-select {

            border-radius: 12px;

            padding: 12px 14px;

            min-height: 50px;

        }


        .form-control:focus,
        .form-select:focus {

            border-color: var(--brand);

            box-shadow:
                0 0 0 0.2rem
                rgba(245, 130, 32, 0.15);

        }


        .btn-brand {

            background: var(--brand);

            border-color: var(--brand);

            color: #ffffff;

            border-radius: 999px;

            font-weight: 600;

        }


        .btn-brand:hover,
        .btn-brand:focus {

            background: var(--brand-hover);

            border-color: var(--brand-hover);

            color: #ffffff;

        }


        .login-link {

            color: var(--brand);

            font-weight: 600;

        }


        .login-link:hover {

            color: var(--brand-hover);

        }


        .account-note {

            border-left:
                4px solid var(--brand);

            background:
                rgba(245, 130, 32, 0.07);

            border-radius: 10px;

        }


        @media (max-width: 575.98px) {

            body {

                align-items: flex-start;

                padding-top: 30px;
                padding-bottom: 30px;

            }

            .signup-card {

                padding:
                    30px 22px !important;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="row justify-content-center">


        <div class="col-xl-5 col-lg-6 col-md-8">


            <div class="signup-card bg-white p-4 p-lg-5">


                <!-- Header -->
                <div class="text-center mb-4">


                    <img
                        src="img/armor.jpg"
                        alt="Armor Bangladesh Ltd."
                        class="brand-logo mb-3">


                    <div
                        class="brand-icon
                               mx-auto mb-3">

                        <i class="fa-solid fa-user-plus"></i>

                    </div>


                    <h3 class="fw-bold mb-2">

                        Create Account

                    </h3>


                    <p class="text-muted mb-0">

                        Armor Bangladesh Ltd.

                    </p>


                </div>



                <!-- Account Notice -->
                <div
                    class="account-note
                           p-3 mb-4">


                    <div class="d-flex">


                        <i
                            class="fa-solid fa-circle-info
                                   text-warning mt-1 me-3">
                        </i>


                        <small class="text-muted">

                            Select your account role below:
                            Client, Technician, Manager or Admin.

                        </small>


                    </div>


                </div>



                <!-- Error Message -->
                <?php if (!empty($error)): ?>


                    <div
                        class="alert alert-danger
                               d-flex align-items-center">


                        <i
                            class="fa-solid fa-circle-exclamation
                                   me-2">
                        </i>


                        <div>

                            <?php
                            echo htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>


                    </div>


                <?php endif; ?>



                <!-- Signup Form -->
                <form
                    method="POST"
                    action="signup.php"
                    autocomplete="on">


                    <!-- Register As -->
                    <div class="mb-3">


                        <label
                            for="role"
                            class="form-label fw-semibold">

                            Register As

                        </label>


                        <div class="input-group">


                            <span
                                class="input-group-text
                                       bg-light border-end-0">

                                <i class="fa-solid fa-users"></i>

                            </span>


                            <select
                                id="role"
                                name="role"
                                class="form-select
                                       bg-light
                                       border-start-0"
                                required>


                                <option
                                    value=""
                                    disabled
                                    <?php
                                    echo empty($_POST["role"])
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Select Role

                                </option>


                                <option
                                    value="client"
                                    <?php
                                    echo (($_POST["role"] ?? "") === "client")
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Client

                                </option>


                                <option
                                    value="technician"
                                    <?php
                                    echo (($_POST["role"] ?? "") === "technician")
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Technician

                                </option>


                                <option
                                    value="manager"
                                    <?php
                                    echo (($_POST["role"] ?? "") === "manager")
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Manager

                                </option>


                                <option
                                    value="admin"
                                    <?php
                                    echo (($_POST["role"] ?? "") === "admin")
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Admin

                                </option>


                            </select>


                        </div>


                    </div>



                    <!-- Full Name -->
                    <div class="mb-3">


                        <label
                            for="name"
                            class="form-label fw-semibold">

                            Full Name

                        </label>


                        <div class="input-group">


                            <span
                                class="input-group-text
                                       bg-light border-end-0">

                                <i class="fa-solid fa-user"></i>

                            </span>


                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-control
                                       bg-light
                                       border-start-0"
                                placeholder="Your full name"
                                maxlength="100"
                                autocomplete="name"
                                value="<?php
                                    echo htmlspecialchars(
                                        $_POST["name"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                required>


                        </div>


                    </div>



                    <!-- Email -->
                    <div class="mb-3">


                        <label
                            for="email"
                            class="form-label fw-semibold">

                            Email Address

                        </label>


                        <div class="input-group">


                            <span
                                class="input-group-text
                                       bg-light border-end-0">

                                <i class="fa-solid fa-envelope"></i>

                            </span>


                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control
                                       bg-light
                                       border-start-0"
                                placeholder="you@example.com"
                                maxlength="150"
                                autocomplete="email"
                                value="<?php
                                    echo htmlspecialchars(
                                        $_POST["email"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                required>


                        </div>


                    </div>



                    <!-- Password -->
                    <div class="mb-2">


                        <label
                            for="password"
                            class="form-label fw-semibold">

                            Password

                        </label>


                        <div class="input-group">


                            <span
                                class="input-group-text
                                       bg-light border-end-0">

                                <i class="fa-solid fa-lock"></i>

                            </span>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control
                                       bg-light
                                       border-start-0"
                                placeholder="Create a password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required>


                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                id="togglePassword"
                                aria-label="Show or hide password">

                                <i
                                    class="fa-solid fa-eye"
                                    id="passwordIcon">
                                </i>

                            </button>


                        </div>


                    </div>



                    <div class="mb-4">

                        <small class="text-muted">

                            Use at least 8 characters.

                        </small>

                    </div>



                    <!-- Submit -->
                    <button
                        type="submit"
                        class="btn btn-brand
                               w-100 py-3">


                        Create Account


                        <i
                            class="fa-solid fa-arrow-right
                                   ms-2">
                        </i>


                    </button>


                </form>



                <!-- Login -->
                <div class="text-center mt-4">


                    <small class="text-muted">

                        Already have an account?


                        <a
                            href="login.php"
                            class="text-decoration-none
                                   login-link">

                            Login

                        </a>


                    </small>


                </div>



                <!-- Home -->
                <div class="text-center mt-3">


                    <a
                        href="index.php"
                        class="small text-muted
                               text-decoration-none">

                        <i class="fa-solid fa-house me-1"></i>

                        Back to Armor Bangladesh Website

                    </a>


                </div>


            </div>



            <div
                class="text-center
                       text-muted small mt-3">


                &copy;

                <?php echo date("Y"); ?>

                Armor Bangladesh Ltd.

                All Rights Reserved.


            </div>


        </div>


    </div>


</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>



<script>

    const togglePassword =
        document.getElementById("togglePassword");

    const passwordField =
        document.getElementById("password");

    const passwordIcon =
        document.getElementById("passwordIcon");


    togglePassword.addEventListener(
        "click",
        function () {

            const isPassword =
                passwordField.type === "password";


            passwordField.type =
                isPassword ? "text" : "password";


            passwordIcon.classList.toggle(
                "fa-eye"
            );


            passwordIcon.classList.toggle(
                "fa-eye-slash"
            );

        }
    );

</script>


</body>

</html>