<?php
session_start();
require_once "../config/db.php";

/* CUSTOMER LOGIN CHECK */
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$message = "";
$messageType = "";

/* HANDLE POST ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");

    /* 1. UPDATE PROFILE */
    if ($action === "update_profile") {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");

        if (empty($name) || empty($email)) {
            $message = "Name and email cannot be empty.";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
        } else {
            // Check if email taken by another user
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->bind_param("si", $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = "This email is already in use by another account.";
                $messageType = "error";
            } else {
                $uStmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $uStmt->bind_param("ssi", $name, $email, $user_id);
                if ($uStmt->execute()) {
                    $_SESSION["user_name"] = $name;
                    $_SESSION["user_email"] = $email;
                    $message = "Profile details updated successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to update profile.";
                    $messageType = "error";
                }
                $uStmt->close();
            }
            $chk->close();
        }
    }

    /* 2. CHANGE PASSWORD */
    elseif ($action === "change_password") {
        $currentPass = $_POST["current_password"] ?? "";
        $newPass = $_POST["new_password"] ?? "";
        $confirmPass = $_POST["confirm_password"] ?? "";

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $message = "All password fields are required.";
            $messageType = "error";
        } elseif (strlen($newPass) < 6) {
            $message = "New password must be at least 6 characters.";
            $messageType = "error";
        } elseif ($newPass !== $confirmPass) {
            $message = "New passwords do not match.";
            $messageType = "error";
        } else {
            // Fetch password
            $pStmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $pStmt->bind_param("i", $user_id);
            $pStmt->execute();
            $currHash = $pStmt->get_result()->fetch_assoc()["password"];
            $pStmt->close();

            if (!password_verify($currentPass, $currHash)) {
                $message = "Current password is incorrect.";
                $messageType = "error";
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updPass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updPass->bind_param("si", $newHash, $user_id);
                if ($updPass->execute()) {
                    $message = "Password changed successfully.";
                    $messageType = "success";
                } else {
                    $message = "Database error while updating password.";
                    $messageType = "error";
                }
                $updPass->close();
            }
        }
    }

    /* 3. ADD DELIVERY ADDRESS */
    elseif ($action === "add_address") {
        $fullName = trim($_POST["full_name"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $city = trim($_POST["city"] ?? "");
        $state = trim($_POST["state"] ?? "");
        $pincode = trim($_POST["pincode"] ?? "");

        if (empty($fullName) || empty($phone) || empty($address) || empty($city) || empty($state) || empty($pincode)) {
            $message = "Please complete all address fields.";
            $messageType = "error";
        } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
            $message = "Please enter a valid 10-digit phone number.";
            $messageType = "error";
        } elseif (!preg_match("/^[0-9]{6}$/", $pincode)) {
            $message = "Please enter a valid 6-digit pincode.";
            $messageType = "error";
        } else {
            $addrStmt = $conn->prepare("INSERT INTO addresses (user_id, full_name, phone, address, city, state, pincode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $addrStmt->bind_param("issssss", $user_id, $fullName, $phone, $address, $city, $state, $pincode);
            if ($addrStmt->execute()) {
                $message = "New delivery address added successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to save address.";
                $messageType = "error";
            }
            $addrStmt->close();
        }
    }

    /* 4. DELETE ADDRESS */
    elseif ($action === "delete_address") {
        $addrId = (int)($_POST["address_id"] ?? 0);
        if ($addrId > 0) {
            $delStmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
            $delStmt->bind_param("ii", $addrId, $user_id);
            if ($delStmt->execute()) {
                $message = "Address deleted.";
                $messageType = "success";
            }
            $delStmt->close();
        }
    }
}

/* GET USER DETAILS */
$stmt = $conn->prepare("
    SELECT id, name, email, role, status, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/* INITIALS */
$name_parts = explode(" ", trim($user["name"]));
$initials = "";
foreach ($name_parts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
}
$initials = substr($initials, 0, 2);
$member_since = date("F Y", strtotime($user["created_at"]));

/* FETCH ADDRESSES */
$addresses = [];
$aStmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC");
$aStmt->bind_param("i", $user_id);
$aStmt->execute();
$aRes = $aStmt->get_result();
while ($row = $aRes->fetch_assoc()) {
    $addresses[] = $row;
}
$aStmt->close();

/* STATS */
$orderCount = (int)$conn->query("SELECT COUNT(*) AS c FROM orders WHERE user_id = {$user_id}")->fetch_assoc()["c"];
$cartCount = (int)$conn->query("SELECT COALESCE(SUM(quantity), 0) AS c FROM cart WHERE user_id = {$user_id}")->fetch_assoc()["c"];
$wishCount = (int)$conn->query("SELECT COUNT(*) AS c FROM wishlist WHERE user_id = {$user_id}")->fetch_assoc()["c"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | QARTIV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #faf9f6; color: #1a1e36; margin: 0; }
        .account-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .account-sidebar { background: #101322; color: #ffffff; padding: 28px 20px; display: flex; flex-direction: column; }
        .account-brand { font-size: 22px; font-weight: 900; letter-spacing: 1px; color: #ffffff; margin-bottom: 30px; }
        .account-brand span { color: #d4af37; }
        .account-menu-title { font-size: 10px; letter-spacing: 1.5px; color: #555e7d; font-weight: 800; margin-bottom: 12px; }
        .account-nav a {
            display: flex; align-items: center; gap: 12px; color: #a4adca; text-decoration: none;
            padding: 11px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 6px; transition: 0.2s ease;
        }
        .account-nav a:hover, .account-nav a.active { background: #1a1e36; color: #ffffff; }
        .account-nav a.active { border-left: 3px solid #d4af37; color: #d4af37; }
        .account-main { padding: 32px 40px; }
        .top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .top-row h1 { font-size: 26px; font-weight: 850; margin: 0; color: #1a1e36; }

        .profile-hero {
            background: #ffffff; border: 1px solid #ede8de; border-radius: 18px; padding: 28px;
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02);
        }
        .profile-main { display: flex; align-items: center; gap: 20px; }
        .profile-avatar {
            width: 64px; height: 64px; border-radius: 50%; background: #1a1e36; color: #d4af37;
            font-size: 22px; font-weight: 900; display: flex; align-items: center; justify-content: center;
        }
        .profile-info h2 { margin: 0 0 4px; font-size: 20px; color: #1a1e36; }
        .profile-info p { margin: 0; font-size: 13px; color: #6b7280; }

        .account-sections-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        .account-card {
            background: #ffffff; border: 1px solid #ede8de; border-radius: 16px; padding: 28px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.02);
        }
        .card-heading { border-bottom: 1px solid #f1ece4; padding-bottom: 14px; margin-bottom: 20px; }
        .card-heading h3 { margin: 0; font-size: 17px; font-weight: 800; color: #1a1e36; }
        .form-row { margin-bottom: 16px; }
        .form-row label { display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color: #4b526d; }
        .form-row input, .form-row textarea {
            width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #dcdfe8;
            border-radius: 9px; font-size: 13px; outline: none; font-family: inherit;
        }
        .form-row input:focus, .form-row textarea:focus { border-color: #1a1e36; }
        .btn-submit {
            background: #1a1e36; color: #ffffff; border: none; padding: 10px 20px; border-radius: 9px;
            font-size: 13px; font-weight: 800; cursor: pointer; transition: 0.2s ease;
        }
        .btn-submit:hover { background: #d4af37; color: #1a1e36; }

        .address-item {
            border: 1px solid #ede8de; border-radius: 12px; padding: 16px; margin-bottom: 14px;
            background: #faf8f5; display: flex; justify-content: space-between; align-items: flex-start;
        }
        .address-item strong { display: block; color: #1a1e36; font-size: 14px; margin-bottom: 4px; }
        .address-item p { margin: 0; font-size: 12px; color: #555d70; line-height: 1.5; }
        .btn-del-addr {
            background: none; border: 1px solid #fca5a5; color: #dc2626; padding: 4px 10px;
            border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer;
        }
        .btn-del-addr:hover { background: #fee2e2; }

        @media (max-width: 900px) {
            .account-layout { grid-template-columns: 1fr; }
            .account-sidebar { display: none; }
            .account-sections-grid { grid-template-columns: 1fr; }
            .account-main { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="account-layout">

    <!-- SIDEBAR -->
    <aside class="account-sidebar">
        <div class="account-brand">QART<span>IV</span></div>

        <div class="account-menu-title">SHOPPING</div>
        <nav class="account-nav">
            <a href="dashboard.php"><span class="account-nav-icon">⌂</span> Dashboard</a>
            <a href="products.php"><span class="account-nav-icon">◈</span> Shop Products</a>
            <a href="cart.php"><span class="account-nav-icon">🛒</span> My Cart (<?php echo $cartCount; ?>)</a>
            <a href="wishlist.php"><span class="account-nav-icon">♡</span> Wishlist (<?php echo $wishCount; ?>)</a>
        </nav>

        <div class="account-menu-title" style="margin-top:24px;">ACCOUNT</div>
        <nav class="account-nav">
            <a href="orders.php"><span class="account-nav-icon">▣</span> My Orders (<?php echo $orderCount; ?>)</a>
            <a href="account.php" class="active"><span class="account-nav-icon">◉</span> My Account</a>
        </nav>

        <div style="margin-top:auto;padding-top:20px;border-top:1px solid #20263f;">
            <a href="../auth/logout.php" style="color:#f87171;text-decoration:none;font-size:13px;font-weight:700;">↪ Sign Out</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="account-main">

        <div class="top-row">
            <div>
                <h1>Account Settings</h1>
                <p style="margin:4px 0 0;color:#7b829c;font-size:13px;">Manage your personal profile, credentials, and delivery addresses.</p>
            </div>
            <a href="products.php" class="btn-submit" style="text-decoration:none;padding:10px 18px;">Explore Products →</a>
        </div>

        <?php if (!empty($message)): ?>
            <div style="padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;background:<?php echo $messageType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>;color:<?php echo $messageType === 'success' ? '#065f46' : '#991b1b'; ?>;border:1px solid <?php echo $messageType === 'success' ? '#a7f3d0' : '#fecaca'; ?>;">
                <span><?php echo $messageType === 'success' ? '✓' : '⚠'; ?></span>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <div class="profile-main">
                <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user["name"]); ?></h2>
                    <p><?php echo htmlspecialchars($user["email"]); ?> • Member since <?php echo htmlspecialchars($member_since); ?></p>
                </div>
            </div>
            <div style="text-align:right;">
                <span style="display:inline-block;padding:5px 12px;border-radius:20px;background:#ecfdf5;color:#047857;font-size:12px;font-weight:800;">✓ Verified Member</span>
            </div>
        </div>

        <div class="account-sections-grid">

            <!-- EDIT PROFILE -->
            <div class="account-card">
                <div class="card-heading">
                    <h3>Personal Information</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" class="btn-submit">Save Profile Changes</button>
                </form>
            </div>

            <!-- CHANGE PASSWORD -->
            <div class="account-card">
                <div class="card-heading">
                    <h3>Security & Password</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-row">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required placeholder="••••••••">
                    </div>
                    <div class="form-row">
                        <label>New Password (min 6 characters)</label>
                        <input type="password" name="new_password" required placeholder="••••••••">
                    </div>
                    <div class="form-row">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-submit">Update Password</button>
                </form>
            </div>

        </div>

        <!-- SAVED ADDRESSES -->
        <div class="account-card">
            <div class="card-heading" style="display:flex;justify-content:space-between;align-items:center;">
                <h3>Saved Delivery Addresses</h3>
                <span style="font-size:12px;color:#8c90a4;"><?php echo count($addresses); ?> saved</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

                <!-- Address List -->
                <div>
                    <?php if (empty($addresses)): ?>
                        <p style="color:#8c90a4;font-size:13px;padding:20px 0;">No saved delivery addresses. Use the form on the right to add one.</p>
                    <?php else: ?>
                        <?php foreach ($addresses as $ad): ?>
                            <div class="address-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($ad['full_name']); ?></strong>
                                    <p>
                                        <?php echo nl2br(htmlspecialchars($ad['address'])); ?><br>
                                        <?php echo htmlspecialchars($ad['city']); ?>, <?php echo htmlspecialchars($ad['state']); ?> - <?php echo htmlspecialchars($ad['pincode']); ?><br>
                                        📞 <?php echo htmlspecialchars($ad['phone']); ?>
                                    </p>
                                </div>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this address?');">
                                    <input type="hidden" name="action" value="delete_address">
                                    <input type="hidden" name="address_id" value="<?php echo $ad['id']; ?>">
                                    <button type="submit" class="btn-del-addr">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Address Form -->
                <div style="background:#faf8f5;border:1px solid #ede8de;border-radius:12px;padding:20px;">
                    <h4 style="margin:0 0 14px;font-size:14px;font-weight:800;color:#1a1e36;">+ Add New Address</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_address">
                        <div class="form-row">
                            <label>Recipient Name *</label>
                            <input type="text" name="full_name" required placeholder="Full Name">
                        </div>
                        <div class="form-row">
                            <label>Phone Number (10 digits) *</label>
                            <input type="tel" name="phone" pattern="[0-9]{10}" required placeholder="e.g. 9876543210">
                        </div>
                        <div class="form-row">
                            <label>Street Address *</label>
                            <textarea name="address" rows="2" required placeholder="Flat, Apartment, Street"></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-row">
                                <label>City *</label>
                                <input type="text" name="city" required placeholder="City">
                            </div>
                            <div class="form-row">
                                <label>State *</label>
                                <input type="text" name="state" required placeholder="State">
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Pincode *</label>
                            <input type="text" name="pincode" pattern="[0-9]{6}" required placeholder="6-digit pincode">
                        </div>
                        <button type="submit" class="btn-submit" style="width:100%;">Save Delivery Address</button>
                    </form>
                </div>

            </div>
        </div>

    </main>

</div>

</body>
</html>