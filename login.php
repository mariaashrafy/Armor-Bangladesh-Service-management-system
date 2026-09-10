<?php

session_start();

require_once "config.php";

$error = "";


/*
|--------------------------------------------------------------------------
| If Already Logged In
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["user_id"], $_SESSION["role"])) {

    $role = $_SESSION["role"];

    if ($role === "admin") {

        header("Location: admin-dashboard.php");
        exit;

    } elseif ($role === "technician") {

        header("Location: technician-dashboard.php");
        exit;

    } elseif ($role === "manager") {

        header("Location: manager-dashboard.php");
        exit;

    } else {

        header("Location: client-dashboard.php");
        exit;

    }

}


/*
|--------------------------------------------------------------------------
| Login Processing
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $pass  = $_POST["password"] ?? "";
    $role  = trim($_POST["role"] ?? "");


    $allowed_roles = [
        "admin",
        "technician",
        "client",
        "manager"
    ];


    if (
        $email === "" ||
        $pass === "" ||
        $role === ""
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!in_array($role, $allowed_roles, true)) {

        $error = "Invalid role selected.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Find User By Email
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    name,
                    email,
                    password_hash,
                    role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);

            $user = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | Validate Login
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $error = "Invalid email or password.";

            } elseif (!password_verify(
                $pass,
                $user["password_hash"]
            )) {

                $error = "Invalid email or password.";

            } elseif ($user["role"] !== $role) {

                $error = "Role does not match this account.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Login Successful
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                $_SESSION["user_id"] =
                    $user["id"];


                $_SESSION["name"] =
                    $user["name"];


                $_SESSION["email"] =
                    $user["email"];


                $_SESSION["role"] =
                    $user["role"];


                /*
                |--------------------------------------------------------------------------
                | Redirect According To Role
                |--------------------------------------------------------------------------
                */

                if ($user["role"] === "admin") {

                    header(
                        "Location: admin-dashboard.php"
                    );

                } elseif ($user["role"] === "technician") {

                    header(
                        "Location: technician-dashboard.php"
                    );

                } elseif ($user["role"] === "manager") {

                    header(
                        "Location: manager-dashboard.php"
                    );

                } else {

                    header(
                        "Location: client-dashboard.php"
                    );

                }

                exit;

            }

        } catch (PDOException $e) {

            $error = "Something went wrong. Please try again later.";

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Login | Armor Bangladesh Ltd.
    </title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">


    <meta
        name="description"
        content="Login to the Armor Bangladesh Ltd. banking technology service management system.">


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet">


    <style>

        :root {

            --brand: #F58220;
            --brand-hover: #D96C13;

            --dark: #202020;

            --soft: #FFF8F2;

        }


        body {

            background:
                linear-gradient(
                    120deg,
                    #ffffff,
                    var(--soft)
                );

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px 15px;

        }


        .login-card {

            max-width: 440px;

            width: 100%;

            border: 0;

            border-radius: 20px;

            box-shadow:
                0 15px 40px
                rgba(0, 0, 0, 0.10);

        }


        .brand-logo {

            max-width: 180px;

            max-height: 80px;

            object-fit: contain;

            display: block;

            margin: 0 auto 15px;

        }


        .brand-icon {

            width: 58px;

            height: 58px;

            margin: 0 auto 15px;

            border-radius: 15px;

            background:
                rgba(245, 130, 32, 0.12);

            color: var(--brand);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;

        }


        .form-control,
        .form-select {

            border-radius: 12px;

            min-height: 50px;

            padding: 12px 14px;

        }


        .form-control:focus,
        .form-select:focus {

            border-color: var(--brand);

            box-shadow:
                0 0 0 .2rem
                rgba(245, 130, 32, 0.15);

        }


        .btn-brand {

            background: var(--brand);

            border-color: var(--brand);

            color: #ffffff;

            border-radius: 999px;

            font-weight: 700;

        }


        .btn-brand:hover,
        .btn-brand:focus {

            background: var(--brand-hover);

            border-color: var(--brand-hover);

            color: #ffffff;

        }


        .signup-link {

            color: var(--brand);

            font-weight: 600;

        }


        .signup-link:hover {

            color: var(--brand-hover);

        }

    </style>

</head>


<body>


<div class="card login-card p-4 p-lg-5">


    <!-- Header -->
    <div class="text-center mb-4">


        <img
            src="img/armor.jpg"
            alt="Armor Bangladesh Ltd."
            class="brand-logo">


        <div class="brand-icon">

            <i class="fa-solid fa-right-to-bracket"></i>

        </div>


        <h3 class="fw-bold mb-2">

            Account Login

        </h3>


        <p class="text-muted mb-0">

            Armor Bangladesh Ltd.

        </p>


    </div>



    <!-- Error -->
    <?php if (!empty($error)): ?>


        <div
            class="alert alert-danger
                   d-flex align-items-center">


            <i
                class="fa-solid
                       fa-circle-exclamation
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



    <!-- Signup Successful -->
    <?php
    if (
        isset($_GET["signup"]) &&
        $_GET["signup"] === "success"
    ):
    ?>


        <div
            class="alert alert-success
                   d-flex align-items-center">


            <i
                class="fa-solid
                       fa-circle-check
                       me-2">
            </i>


            <div>

                Signup successful!
                Please login.

            </div>


        </div>


    <?php endif; ?>



    <!-- Login Form -->
    <form
        method="POST"
        action="login.php"
        autocomplete="on">


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
                           bg-light border-start-0"
                    placeholder="you@example.com"
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
        <div class="mb-3">


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
                           bg-light border-start-0"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required>


                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    id="togglePassword"
                    aria-label="Show or hide password">

                    <i
                        class="fa-solid fa-eye"
                        id="passwordIcon">
                    </i>

                </button>


            </div>


        </div>



        <!-- Role -->
        <div class="mb-4">


            <label
                for="role"
                class="form-label fw-semibold">

                Login As

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
                           bg-light border-start-0"
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



        <!-- Login Button -->
        <button
            type="submit"
            class="btn btn-brand
                   w-100 py-3">


            <i
                class="fa-solid
                       fa-right-to-bracket
                       me-2">
            </i>

            Login

        </button>


    </form>



    <!-- Signup Link -->
    <div class="text-center mt-4">


        <small class="text-muted">

            Don't have an account?


            <a
                href="signup.php"
                class="text-decoration-none
                       signup-link">

                Sign Up

            </a>


        </small>


    </div>



    <!-- Website -->
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

            const hidden =
                passwordField.type === "password";


            passwordField.type =
                hidden ? "text" : "password";


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