<?php
session_start();
require_once "../config/db.php";

$message = "";
$message_type = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') {
        $message = "You have been successfully logged out.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'registered') {
        $message = "Account created successfully! Please sign in.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'auth_required') {
        $message = "Please sign in to access that page.";
        $message_type = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $message = "Please enter your email and password.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user["status"] !== "active") {
                $message = "Your account is currently blocked.";
                $message_type = "error";
            } elseif (password_verify($password, $user["password"])) {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_role"] = $user["role"];

                if ($user["role"] === "admin") {
                    header("Location: ../admin/dashboard.php");
                    exit;
                } else {
                    header("Location: ../customer/dashboard.php");
                    exit;
                }
            } else {
                $message = "Invalid email or password.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid email or password.";
            $message_type = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | QARTIV — Smart E-Commerce Platform</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.qartiv-login-page {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', Inter, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #090c24;
            color: #ffffff;
            overflow-x: hidden;
        }

        .qartiv-login-wrapper {
            width: 100%;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 52% 48%;
            position: relative;
        }

        /* =========================================================
           LEFT: 3D VIDEO MOVING BACKGROUND PORTAL (IMAGE 2)
        ========================================================= */
        .qartiv-login-visual {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            background: #0a0d2a;
            perspective: 1200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-stage-3d {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
            overflow: hidden;
        }

        /* 3D Moving Video Image Effect */
        .qartiv-video-bg {
            position: absolute;
            top: -5%;
            left: -5%;
            width: 110%;
            height: 110%;
            object-fit: cover;
            display: block;
            filter: brightness(0.95) contrast(1.08) saturate(1.1);
            animation: videoCinemaDrift 16s ease-in-out infinite alternate;
            z-index: 1;
        }

        @keyframes videoCinemaDrift {
            0% {
                transform: scale(1.03) translate(0px, 0px) rotate(0deg);
                filter: brightness(0.95) contrast(1.05);
            }
            33% {
                transform: scale(1.09) translate(-14px, -8px) rotate(0.6deg);
                filter: brightness(1.05) contrast(1.12);
            }
            66% {
                transform: scale(1.07) translate(10px, -12px) rotate(-0.4deg);
                filter: brightness(1.0) contrast(1.08);
            }
            100% {
                transform: scale(1.11) translate(-6px, 10px) rotate(0.5deg);
                filter: brightness(1.08) contrast(1.14);
            }
        }

        /* Ambient video overlay gradient */
        .video-ambient-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                180deg,
                rgba(7, 10, 36, 0.2) 0%,
                rgba(9, 12, 40, 0.4) 60%,
                rgba(8, 11, 35, 0.8) 100%
            );
            z-index: 2;
            pointer-events: none;
        }

        /* Moving Video Holographic Sweep */
        .video-lens-sweep {
            position: absolute;
            top: -120%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                135deg,
                transparent 40%,
                rgba(0, 240, 255, 0.15) 48%,
                rgba(255, 255, 255, 0.3) 50%,
                rgba(168, 85, 247, 0.15) 52%,
                transparent 60%
            );
            transform: rotate(-25deg);
            pointer-events: none;
            z-index: 3;
            animation: lensFlareSweep 6s infinite ease-in-out;
        }

        @keyframes lensFlareSweep {
            0% { transform: translateY(-100%) rotate(-25deg); }
            40% { transform: translateY(100%) rotate(-25deg); }
            100% { transform: translateY(100%) rotate(-25deg); }
        }

        /* Canvas Particle Network for 3D Video */
        #loginParticleCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 4;
            pointer-events: none;
        }

        /* Floating 3D Holographic Badges */
        .video-holo-card {
            position: absolute;
            background: rgba(14, 18, 52, 0.85);
            border: 1px solid rgba(0, 240, 255, 0.35);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 12px 18px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 240, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 5;
            transform: translateZ(40px);
            animation: floatHolo 4.5s ease-in-out infinite alternate;
        }

        @keyframes floatHolo {
            0% { transform: translateY(0px) translateZ(40px); }
            100% { transform: translateY(-8px) translateZ(55px); }
        }

        .holo-badge-1 {
            top: 40px;
            left: 36px;
            animation-delay: 0s;
        }

        .holo-badge-2 {
            bottom: 45px;
            left: 36px;
            animation-delay: 1.2s;
        }

        .holo-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #00f0ff, #6258ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 16px;
        }

        .video-holo-card strong {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #ffffff;
        }

        .video-holo-card span {
            display: block;
            font-size: 10px;
            color: #8c95b8;
            font-weight: 600;
        }

        /* =========================================================
           RIGHT: LOGIN FORM AREA (DARK GLASS THEME)
        ========================================================= */
        .qartiv-login-form-area {
            width: 100%;
            min-height: 100vh;
            background: #ffffff;
            color: #171927;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 70px;
            overflow-y: auto;
            position: relative;
        }

        .desktop-qartiv-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 480px;
            margin-bottom: 30px;
        }

        .logo-mark-square {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, #00f0ff, #6258ff);
            color: white;
            font-size: 18px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(98, 88, 255, 0.35);
        }

        .logo-text-title {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #10143d;
        }

        .qartiv-login-card {
            width: 100%;
            max-width: 480px;
        }

        .mobile-qartiv-logo {
            display: none;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #10143d;
            margin-bottom: 20px;
        }

        .login-small-title {
            color: #6258ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .qartiv-login-card h2 {
            font-size: 34px;
            font-weight: 900;
            color: #10143d;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .login-description {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 28px;
        }

        /* Error/Success Notification */
        .qartiv-login-message {
            padding: 13px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .qartiv-login-message.error {
            background: #fff1f2;
            color: #b91c1c;
            border: 1px solid #fecdd3;
        }

        .qartiv-login-message.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        /* Inputs */
        .qartiv-input-group {
            margin-bottom: 20px;
        }

        .qartiv-input-group label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #272d42;
            margin-bottom: 8px;
        }

        .password-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .password-label-row a {
            color: #6258ff;
            font-size: 12px;
            font-weight: 700;
        }

        .password-label-row a:hover {
            text-decoration: underline;
        }

        .qartiv-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .qartiv-input-icon {
            position: absolute;
            left: 16px;
            color: #9ca3af;
            font-size: 15px;
            pointer-events: none;
        }

        .qartiv-input-wrapper input {
            width: 100%;
            height: 52px;
            padding: 0 44px 0 46px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            font-size: 14px;
            font-family: inherit;
            color: #111827;
            background: #f9fafb;
            outline: none;
            transition: all 0.2s ease;
        }

        .qartiv-input-wrapper input:focus {
            border-color: #6258ff;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(98, 88, 255, 0.12);
        }

        .qartiv-show-password {
            position: absolute;
            right: 16px;
            border: none;
            background: none;
            color: #6258ff;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13px;
            color: #4b5563;
        }

        .remember-row label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-row input {
            accent-color: #6258ff;
            width: 16px;
            height: 16px;
        }

        .qartiv-login-btn {
            width: 100%;
            height: 54px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #00f0ff, #6258ff);
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 240, 255, 0.35);
            transition: all 0.25s ease;
        }

        .qartiv-login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 240, 255, 0.45);
        }

        .qartiv-or {
            display: flex;
            align-items: center;
            margin: 24px 0;
            gap: 14px;
            color: #9ca3af;
            font-size: 12px;
            font-weight: 600;
        }

        .qartiv-or span {
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .qartiv-create-account {
            width: 100%;
            height: 50px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #ffffff;
            color: #1f2937;
            font-size: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .qartiv-create-account:hover {
            background: #f9fafb;
            border-color: #6258ff;
            color: #6258ff;
        }

        .qartiv-terms {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            margin-top: 22px;
        }

        .qartiv-terms a {
            color: #6258ff;
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 950px) {
            .qartiv-login-wrapper {
                grid-template-columns: 1fr;
            }
            .qartiv-login-visual {
                height: 280px;
            }
            .qartiv-login-form-area {
                padding: 40px 24px;
            }
        }
    </style>
</head>
<body class="qartiv-login-page">

<div class="qartiv-login-wrapper">

    <!-- =========================================================
         LEFT: 3D VIDEO MOVING BACKGROUND (IMAGE 2)
    ========================================================= -->
    <div class="qartiv-login-visual" id="visualContainer">
        <div class="video-stage-3d" id="videoStage">
            <!-- 2nd Image as 3D Moving Video -->
            <img 
                src="../assets/images/register-bg.png" 
                alt="QARTIV Smart E-Commerce 3D Visual"
                class="qartiv-video-bg"
                id="loginBgVideoImg"
            >

            <!-- Video Ambient Lighting -->
            <div class="video-ambient-overlay"></div>

            <!-- Moving Lens Sweep Effect -->
            <div class="video-lens-sweep"></div>

            <!-- Ambient Video Particle Canvas -->
            <canvas id="loginParticleCanvas"></canvas>

            <!-- Floating 3D Telemetry Badges -->
            <div class="video-holo-card holo-badge-1">
                <div class="holo-icon-box">⚡</div>
                <div>
                    <strong>Fast & Reliable</strong>
                    <span>Express doorstep delivery</span>
                </div>
            </div>

            <div class="video-holo-card holo-badge-2">
                <div class="holo-icon-box">🔒</div>
                <div>
                    <strong>256-Bit SSL Secured</strong>
                    <span>Encrypted checkout system</span>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         RIGHT: LOGIN FORM
    ========================================================= -->
    <div class="qartiv-login-form-area">

        <!-- LOGO -->
        <a href="../index.php" class="desktop-qartiv-logo">
            <div class="logo-mark-square">Q</div>
            <span class="logo-text-title">QARTIV</span>
        </a>

        <div class="qartiv-login-card">

            <div class="login-small-title">
                <span>✦</span> WELCOME BACK
            </div>

            <h2>Sign in to your account</h2>
            <p class="login-description">Access your orders, wishlist, and smart shopping dashboard.</p>

            <!-- ERROR NOTIFICATION -->
            <?php if (!empty($message)): ?>
                <div class="qartiv-login-message <?php echo $message_type; ?>">
                    <span><?php echo $message_type === "error" ? "⚠" : "✓"; ?></span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form method="POST" action="login.php">

                <!-- EMAIL -->
                <div class="qartiv-input-group">
                    <label for="email">Email Address</label>
                    <div class="qartiv-input-wrapper">
                        <span class="qartiv-input-icon">✉</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="name@example.com"
                            autocomplete="email"
                            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                            required
                        >
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="qartiv-input-group">
                    <div class="password-label-row">
                        <label for="password">Password</label>
                        <a href="#">Forgot password?</a>
                    </div>

                    <div class="qartiv-input-wrapper">
                        <span class="qartiv-input-icon">🔒</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="qartiv-show-password"
                            onclick="toggleQartivPassword()"
                        >
                            Show
                        </button>
                    </div>
                </div>

                <!-- REMEMBER ME -->
                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember">
                        <span>Remember me for 30 days</span>
                    </label>
                </div>

                <!-- SIGN IN BUTTON -->
                <button type="submit" class="qartiv-login-btn">
                    <span>Sign In to Account</span>
                    <span>→</span>
                </button>

            </form>

            <div class="qartiv-or">
                <span></span>
                <p>New to QARTIV?</p>
                <span></span>
            </div>

            <a href="register.php" class="qartiv-create-account">
                Create a Free Account →
            </a>

            <p class="qartiv-terms">
                By continuing, you agree to our 
                <a href="#">Terms</a> and <a href="#">Privacy Policy</a>.
            </p>

        </div>

    </div>

</div>

<!-- =========================================================
     3D VIDEO PARALLAX & PARTICLE SCRIPT
========================================================= -->
<script>
    function toggleQartivPassword() {
        const password = document.getElementById("password");
        const button = document.querySelector(".qartiv-show-password");

        if (password.type === "password") {
            password.type = "text";
            button.textContent = "Hide";
        } else {
            password.type = "password";
            button.textContent = "Show";
        }
    }

    // 3D Parallax Tracking for 2nd Image Video Stage
    const visualContainer = document.getElementById('visualContainer');
    const videoStage = document.getElementById('videoStage');

    if (visualContainer && videoStage) {
        visualContainer.addEventListener('mousemove', (e) => {
            const rect = visualContainer.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;

            const rotateX = (-y / (rect.height / 2)) * 12;
            const rotateY = (x / (rect.width / 2)) * 12;

            videoStage.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });

        visualContainer.addEventListener('mouseleave', () => {
            videoStage.style.transform = `rotateX(0deg) rotateY(0deg)`;
        });
    }

    // Ambient Video Dust Particles Canvas
    const canvas = document.getElementById('loginParticleCanvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let particles = [];

        function resizeCanvas() {
            canvas.width = canvas.parentElement.clientWidth;
            canvas.height = canvas.parentElement.clientHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        class VideoDustParticle {
            constructor() {
                this.reset();
            }
            reset() {
                this.x = Math.random() * canvas.width;
                this.y = canvas.height + Math.random() * 20;
                this.size = Math.random() * 2.5 + 0.6;
                this.speedY = Math.random() * 0.8 + 0.3;
                this.speedX = (Math.random() - 0.5) * 0.4;
                this.alpha = Math.random() * 0.6 + 0.2;
                this.color = Math.random() > 0.4 ? '#00f0ff' : '#a855f7';
            }
            update() {
                this.y -= this.speedY;
                this.x += this.speedX;
                if (this.y < -10 || this.x < 0 || this.x > canvas.width) {
                    this.reset();
                }
            }
            draw() {
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.fillStyle = this.color;
                ctx.shadowColor = this.color;
                ctx.shadowBlur = 6;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
            }
        }

        for (let i = 0; i < 30; i++) {
            particles.push(new VideoDustParticle());
        }

        function animateParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            requestAnimationFrame(animateParticles);
        }
        animateParticles();
    }
</script>

</body>
</html>