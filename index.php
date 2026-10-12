<?php
session_start();
require_once "config/db.php";
require_once "config/helpers.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$userRole = $_SESSION["user_role"] ?? "";
$userName = $_SESSION["user_name"] ?? "";
$userId = (int)($_SESSION["user_id"] ?? 0);

/* Live Cart & Wishlist counts if logged in */
$cartCount = 0;
$wishlistCount = 0;
if ($isLoggedIn && $userId > 0) {
    $cRes = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS c FROM cart WHERE user_id = {$userId}");
    if ($cRes && $r = $cRes->fetch_assoc()) {
        $cartCount = (int)$r['c'];
    }
    $wRes = $conn->query("SELECT COUNT(*) AS c FROM wishlist WHERE user_id = {$userId}");
    if ($wRes && $r = $wRes->fetch_assoc()) {
        $wishlistCount = (int)$r['c'];
    }
}

/* Fetch Categories */
$categories = [];
$catRes = $conn->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.id ASC
");
if ($catRes) {
    while ($c = $catRes->fetch_assoc()) {
        $categories[] = $c;
    }
}

/* Fetch Products */
$products = [];
$prodRes = $conn->query("
    SELECT p.*, c.name AS category_name,
           COALESCE((SELECT ROUND(AVG(rating), 1) FROM reviews WHERE product_id = p.id), 4.9) AS avg_rating,
           COALESCE((SELECT COUNT(id) FROM reviews WHERE product_id = p.id), 18) AS review_count
    FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.id ASC
    LIMIT 8
");
if ($prodRes) {
    while ($p = $prodRes->fetch_assoc()) {
        $products[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QARTIV — Smart E-Commerce Platform | Refined Living</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Cinzel:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --bg-ivory: #fdfbf7;
            --bg-cream: #f7f5f0;
            --bg-white: #ffffff;
            --charcoal: #141724;
            --charcoal-light: #232738;
            --gold: #d4af37;
            --gold-light: #f3e5ab;
            --gold-dark: #aa8620;
            --champagne: #c5a880;
            --border-soft: #ede6d8;
            --text-dark: #12151e;
            --text-muted: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-ivory);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; }

        /* Announcement Bar */
        .announcement-bar {
            background: #0e111a;
            color: var(--gold-light);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-align: center;
            padding: 9px 16px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .announcement-bar strong {
            color: #ffffff;
            background: rgba(212, 175, 55, 0.2);
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px dashed var(--gold);
        }

        /* Navigation */
        .navbar-luxury {
            position: sticky;
            top: 0;
            background: rgba(253, 251, 247, 0.94);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border-soft);
            z-index: 1000;
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5%;
            transition: all 0.3s ease;
        }

        .nav-brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo-mark {
            width: 38px;
            height: 38px;
            background: var(--charcoal);
            color: var(--gold);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
            font-family: 'Cinzel', serif;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        }

        .brand-title-group strong {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
            color: var(--charcoal);
            font-family: 'Cinzel', serif;
            display: block;
            line-height: 1.1;
        }

        .brand-title-group span {
            font-size: 9px;
            letter-spacing: 2px;
            font-weight: 700;
            color: var(--champagne);
            text-transform: uppercase;
        }

        .nav-links-menu {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-links-menu a {
            font-size: 13px;
            font-weight: 700;
            color: var(--charcoal);
            transition: color 0.2s ease;
            position: relative;
        }

        .nav-links-menu a:hover {
            color: var(--gold-dark);
        }

        .nav-actions-group {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--charcoal);
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .nav-icon-btn:hover {
            border-color: var(--gold);
            background: #fcf9f2;
            transform: translateY(-2px);
        }

        .nav-badge-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--charcoal);
            color: var(--gold);
            font-size: 10px;
            font-weight: 800;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-white);
        }

        .btn-account-pill {
            background: var(--charcoal);
            color: #ffffff;
            padding: 9px 18px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-account-pill:hover {
            background: var(--gold);
            color: var(--charcoal);
        }

        /* HERO SECTION */
        .hero-luxury {
            position: relative;
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #090c14;
            color: #ffffff;
            text-align: center;
            padding: 80px 24px;
        }

        .hero-video-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            opacity: 0.35;
            filter: contrast(1.1) brightness(0.9);
            z-index: 1;
        }

        .hero-overlay-gradient {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 40%, rgba(20, 24, 38, 0.4) 0%, rgba(9, 12, 20, 0.88) 80%);
            z-index: 2;
        }

        .hero-particles-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 3;
        }

        .hero-content-inner {
            position: relative;
            z-index: 4;
            max-width: 900px;
            margin: 0 auto;
        }

        .hero-badge-champagne {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 18px;
            border-radius: 30px;
            background: rgba(212, 175, 55, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.4);
            color: var(--gold-light);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .hero-headline {
            font-family: 'Cinzel', serif;
            font-size: clamp(34px, 5.5vw, 64px);
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.15;
            color: #ffffff;
            margin-bottom: 20px;
        }

        .hero-headline span {
            background: linear-gradient(135deg, #ffffff 40%, #e2cb92 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: clamp(14px, 1.8vw, 17px);
            color: #d1d5db;
            max-width: 680px;
            margin: 0 auto 36px;
            line-height: 1.7;
            font-weight: 400;
        }

        .hero-cta-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 48px;
        }

        .btn-gold-primary {
            background: linear-gradient(135deg, #d4af37, #c5a880);
            color: #0e111a;
            padding: 15px 36px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 28px rgba(212, 175, 55, 0.3);
            border: none;
            cursor: pointer;
        }

        .btn-gold-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(212, 175, 55, 0.45);
            background: #ffffff;
        }

        .btn-gold-outline {
            background: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 15px 32px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-gold-outline:hover {
            border-color: var(--gold);
            color: var(--gold);
            background: rgba(255, 255, 255, 0.05);
        }

        .hero-metrics-strip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 36px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding-top: 28px;
            flex-wrap: wrap;
        }

        .hero-metric-item {
            text-align: center;
        }

        .hero-metric-item strong {
            display: block;
            font-size: 18px;
            font-weight: 900;
            color: var(--gold-light);
            font-family: 'Cinzel', serif;
        }

        .hero-metric-item span {
            font-size: 11px;
            color: #9ca3af;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* Marquee Bar */
        .marquee-luxury {
            background: #141724;
            color: var(--gold-light);
            padding: 14px 0;
            overflow: hidden;
            white-space: nowrap;
            border-top: 1px solid rgba(212,175,55,0.2);
            border-bottom: 1px solid rgba(212,175,55,0.2);
        }

        .marquee-inner {
            display: inline-block;
            animation: marqueeScroll 25s linear infinite;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* Section Layouts */
        .section-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding: 80px 24px;
        }

        .section-header-editorial {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 50px;
        }

        .section-tag-gold {
            display: inline-block;
            color: var(--gold-dark);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .section-title-editorial {
            font-family: 'Cinzel', serif;
            font-size: clamp(26px, 3.5vw, 38px);
            font-weight: 800;
            color: var(--charcoal);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .section-subtitle-editorial {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* CATEGORIES GRID */
        .categories-luxury-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .cat-luxury-card {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 18px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
        }

        .cat-luxury-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 36px rgba(20,25,45,0.08);
            border-color: var(--champagne);
        }

        .cat-thumb-frame {
            height: 180px;
            overflow: hidden;
            position: relative;
            background: #f3f0ea;
        }

        .cat-thumb-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .cat-luxury-card:hover .cat-thumb-frame img {
            transform: scale(1.08);
        }

        .cat-card-body {
            padding: 20px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cat-card-body h3 {
            font-size: 16px;
            font-weight: 800;
            color: var(--charcoal);
            margin-bottom: 4px;
        }

        .cat-card-body p {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .cat-card-body span {
            font-size: 11px;
            font-weight: 800;
            color: var(--gold-dark);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* PRODUCTS CATALOG */
        .catalog-luxury-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .prod-luxury-card {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .prod-luxury-card:hover {
            transform: translateY(-6px);
            border-color: var(--champagne);
            box-shadow: 0 16px 40px rgba(25,30,50,0.09);
        }

        .prod-img-stage {
            position: relative;
            height: 250px;
            background: #f8f9fc;
            overflow: hidden;
            display: block;
        }

        .prod-img-stage a {
            display: block;
            width: 100%;
            height: 100%;
            text-decoration: none;
            overflow: hidden;
        }

        .prod-img-stage img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
            display: block;
        }

        .prod-luxury-card:hover .prod-img-stage img {
            transform: scale(1.06);
        }

        .prod-discount-tag {
            position: absolute;
            top: 14px;
            left: 14px;
            background: #141724;
            color: var(--gold);
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        .prod-info-block {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .prod-category-label {
            font-size: 10px;
            font-weight: 800;
            color: var(--champagne);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .prod-item-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--charcoal);
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .prod-star-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .prod-star-row span {
            color: var(--gold);
        }

        .prod-star-row small {
            color: var(--text-muted);
            font-weight: 600;
        }

        .prod-price-stack {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-top: auto;
            margin-bottom: 16px;
        }

        .prod-sale-amt {
            font-size: 18px;
            font-weight: 900;
            color: var(--charcoal);
        }

        .prod-orig-amt {
            font-size: 12px;
            color: #9ca3af;
            text-decoration: line-through;
        }

        .prod-btn-action {
            display: block;
            text-align: center;
            background: var(--charcoal);
            color: #ffffff;
            padding: 11px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            transition: all 0.2s ease;
        }

        .prod-btn-action:hover {
            background: var(--gold);
            color: var(--charcoal);
        }

        /* SPOTLIGHT DEAL */
        .spotlight-deal-wrap {
            background: #141724;
            color: #ffffff;
            border-radius: 24px;
            padding: 60px 48px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 48px;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
        }

        .spotlight-deal-wrap::after {
            content: '';
            position: absolute;
            right: -100px;
            bottom: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .deal-countdown-row {
            display: flex;
            gap: 12px;
            margin: 24px 0 32px;
        }

        .countdown-box {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            padding: 12px 18px;
            text-align: center;
            min-width: 68px;
        }

        .countdown-box strong {
            display: block;
            font-size: 24px;
            font-weight: 900;
            color: var(--gold-light);
            font-family: 'Cinzel', serif;
        }

        .countdown-box span {
            font-size: 9px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .spotlight-img-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            text-align: center;
        }

        .spotlight-img-box img {
            max-width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 12px;
        }

        /* PILLARS */
        .pillars-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .pillar-card {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }

        .pillar-card:hover {
            transform: translateY(-4px);
            border-color: var(--gold);
        }

        .pillar-icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fdfbf7;
            border: 1px solid var(--border-soft);
            color: var(--gold-dark);
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }

        .pillar-card h4 {
            font-size: 15px;
            font-weight: 800;
            color: var(--charcoal);
            margin-bottom: 8px;
        }

        .pillar-card p {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* TESTIMONIALS */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .testimonial-card {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            padding: 32px 28px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .testimonial-text {
            font-size: 14px;
            color: #374151;
            line-height: 1.7;
            margin-bottom: 24px;
            font-style: italic;
        }

        .testimonial-author-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--charcoal);
            color: var(--gold);
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .author-info strong {
            display: block;
            font-size: 13px;
            color: var(--charcoal);
        }

        .author-info span {
            font-size: 11px;
            color: var(--gold-dark);
            font-weight: 700;
        }

        /* FAQ ACCORDION */
        .faq-accordion-wrap {
            max-width: 820px;
            margin: 0 auto;
        }

        .faq-item {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .faq-question {
            padding: 20px 24px;
            font-size: 15px;
            font-weight: 750;
            color: var(--charcoal);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }

        .faq-answer {
            display: none;
            padding: 0 24px 20px;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.7;
            border-top: 1px solid #f6f3eb;
            padding-top: 14px;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .faq-item.active .faq-toggle-icon {
            transform: rotate(45deg);
        }

        .faq-toggle-icon {
            font-size: 18px;
            font-weight: 900;
            color: var(--gold-dark);
            transition: transform 0.2s ease;
        }

        /* VIP NEWSLETTER */
        .vip-newsletter-card {
            background: linear-gradient(135deg, #141724 0%, #1f253a 100%);
            color: #ffffff;
            border-radius: 24px;
            padding: 56px 40px;
            text-align: center;
            position: relative;
            border: 1px solid rgba(212,175,55,0.3);
            box-shadow: 0 16px 44px rgba(0,0,0,0.12);
        }

        .vip-newsletter-card h3 {
            font-family: 'Cinzel', serif;
            font-size: clamp(24px, 3.2vw, 36px);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }

        .vip-newsletter-card p {
            color: #d1d5db;
            font-size: 14px;
            max-width: 580px;
            margin: 0 auto 28px;
        }

        .vip-form {
            display: flex;
            max-width: 480px;
            margin: 0 auto;
            gap: 10px;
        }

        .vip-form input {
            flex: 1;
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.06);
            color: #ffffff;
            outline: none;
            font-size: 13px;
        }

        .vip-form input::placeholder { color: #9ca3af; }

        .vip-form button {
            background: var(--gold);
            color: var(--charcoal);
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .vip-form button:hover {
            background: #ffffff;
        }

        /* FOOTER */
        .footer-luxury {
            background: #0e111a;
            color: #9ca3af;
            padding: 70px 5% 30px;
            border-top: 1px solid rgba(212,175,55,0.2);
        }

        .footer-grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 40px;
            margin-bottom: 50px;
        }

        .footer-brand strong {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #ffffff;
            font-family: 'Cinzel', serif;
            display: block;
            margin-bottom: 12px;
        }

        .footer-brand p {
            font-size: 13px;
            line-height: 1.7;
            max-width: 320px;
        }

        .footer-col h5 {
            font-size: 12px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold-light);
            font-weight: 800;
            margin-bottom: 18px;
        }

        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 10px; }
        .footer-col ul li a {
            font-size: 13px;
            color: #9ca3af;
            transition: color 0.2s;
        }
        .footer-col ul li a:hover { color: #ffffff; }

        .footer-bottom {
            max-width: 1280px;
            margin: 0 auto;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            flex-wrap: wrap;
            gap: 16px;
        }

        @media (max-width: 990px) {
            .categories-luxury-grid { grid-template-columns: repeat(2, 1fr); }
            .catalog-luxury-grid { grid-template-columns: repeat(2, 1fr); }
            .pillars-grid { grid-template-columns: repeat(2, 1fr); }
            .spotlight-deal-wrap { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 600px) {
            .navbar-luxury { padding: 0 16px; }
            .nav-links-menu { display: none; }
            .categories-luxury-grid { grid-template-columns: 1fr; }
            .catalog-luxury-grid { grid-template-columns: 1fr; }
            .pillars-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            .vip-form { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- 1. ANNOUNCEMENT BAR -->
<div class="announcement-bar">
    <span>✦ Complimentary Insured Express Shipping On Orders Over ₹999</span>
    <span>•</span>
    <span>Use Code: <strong>WELCOME10</strong> For 10% Off</span>
</div>

<!-- 2. STICKY NAVIGATION -->
<nav class="navbar-luxury">
    <div class="nav-brand-wrap">
        <a href="index.php" style="display:flex;align-items:center;gap:12px;">
            <div class="brand-logo-mark">Q</div>
            <div class="brand-title-group">
                <strong>QARTIV</strong>
                <span>SMART COMMERCE</span>
            </div>
        </a>
    </div>

    <div class="nav-links-menu">
        <a href="#categories">Collections</a>
        <a href="customer/products.php">Shop Catalog</a>
        <a href="#spotlight">Exclusive Deals</a>
        <a href="#pillars">Why Qartiv</a>
        <a href="#faq">Assistance</a>
    </div>

    <div class="nav-actions-group">
        <a href="customer/products.php" class="nav-icon-btn" title="Search Catalog">⌕</a>
        
        <a href="customer/wishlist.php" class="nav-icon-btn" title="My Wishlist">
            ♡
            <?php if ($wishlistCount > 0): ?>
                <span class="nav-badge-count"><?php echo $wishlistCount; ?></span>
            <?php endif; ?>
        </a>

        <a href="customer/cart.php" class="nav-icon-btn" title="My Cart">
            🛒
            <?php if ($cartCount > 0): ?>
                <span class="nav-badge-count"><?php echo $cartCount; ?></span>
            <?php endif; ?>
        </a>

        <?php if ($isLoggedIn): ?>
            <?php if ($userRole === "admin"): ?>
                <a href="admin/dashboard.php" class="btn-account-pill">
                    <span>👑</span> Admin Console
                </a>
            <?php else: ?>
                <a href="customer/dashboard.php" class="btn-account-pill">
                    <span>👤</span> <?php echo htmlspecialchars(explode(' ', trim($userName))[0]); ?>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="auth/login.php" class="btn-account-pill">
                Sign In
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- 3. HERO WITH HTML5 VIDEO, 3D PARTICLES CANVAS & LUXURY TYPOGRAPHY -->
<section class="hero-luxury">
    <!-- Real HTML5 Video Loop -->
    <video autoplay muted loop playsinline class="hero-video-bg" poster="assets/images/shopping-banner.png">
        <source src="assets/videos/hero-bg.mp4" type="video/mp4">
        <img src="assets/images/shopping-banner.png" alt="QARTIV Smart E-Commerce Platform">
    </video>

    <div class="hero-overlay-gradient"></div>
    <canvas id="heroGoldCanvas" class="hero-particles-canvas"></canvas>

    <div class="hero-content-inner">
        <div class="hero-badge-champagne">
            <span>✦</span> THE NEW PINNACLE OF COMMERCE <span>✦</span>
        </div>

        <h1 class="hero-headline">
            Refined Living.<br>
            <span>Intelligently Curated.</span>
        </h1>

        <p class="hero-desc">
            Explore a sanctuary of precision electronics, bespoke footwear, artisanal home essentials, and clean botanicals designed for those who appreciate understated elegance.
        </p>

        <div class="hero-cta-group">
            <a href="customer/products.php" class="btn-gold-primary">
                Explore The Collection →
            </a>
            <a href="#spotlight" class="btn-gold-outline">
                View Exclusive Deals
            </a>
        </div>

        <div class="hero-metrics-strip">
            <div class="hero-metric-item">
                <strong>40,000+</strong>
                <span>Discerning Clients</span>
            </div>
            <div class="hero-metric-item">
                <strong>100%</strong>
                <span>Certified Genuine</span>
            </div>
            <div class="hero-metric-item">
                <strong>4.9 / 5.0</strong>
                <span>Patron Rating</span>
            </div>
            <div class="hero-metric-item">
                <strong>24-Hour</strong>
                <span>Priority Dispatch</span>
            </div>
        </div>
    </div>
</section>

<!-- 4. MARQUEE BANNER -->
<div class="marquee-luxury">
    <div class="marquee-inner">
        ✦ METICULOUS CRAFTSMANSHIP &nbsp;&nbsp;&nbsp;
        ✦ INTELLIGENT COMMERCE &nbsp;&nbsp;&nbsp;
        ✦ INSURED EXPRESS DELIVERY &nbsp;&nbsp;&nbsp;
        ✦ BESPOKE QUALITY &nbsp;&nbsp;&nbsp;
        ✦ CURATED FOR LIFE &nbsp;&nbsp;&nbsp;
        ✦ METICULOUS CRAFTSMANSHIP &nbsp;&nbsp;&nbsp;
        ✦ INTELLIGENT COMMERCE &nbsp;&nbsp;&nbsp;
        ✦ INSURED EXPRESS DELIVERY &nbsp;&nbsp;&nbsp;
        ✦ BESPOKE QUALITY &nbsp;&nbsp;&nbsp;
        ✦ CURATED FOR LIFE &nbsp;&nbsp;&nbsp;
    </div>
</div>

<!-- 5. CURATED CATEGORIES SECTION -->
<section class="section-wrapper" id="categories">
    <div class="section-header-editorial">
        <span class="section-tag-gold">Curated Departments</span>
        <h2 class="section-title-editorial">Distinct Collections</h2>
        <p class="section-subtitle-editorial">Each department is assembled with exacting criteria to ensure peak performance and refined aesthetics.</p>
    </div>

    <div class="categories-luxury-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="customer/products.php?category=<?php echo $cat['id']; ?>" class="cat-luxury-card">
                <div class="cat-thumb-frame">
                    <img src="<?php echo htmlspecialchars(getProductImageUrl($cat['image'], '')); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" loading="lazy">
                </div>
                <div class="cat-card-body">
                    <div>
                        <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p><?php echo htmlspecialchars($cat['description']); ?></p>
                    </div>
                    <span>Explore Department (<?php echo (int)$cat['product_count']; ?>) →</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- 6. CURATED BEST SELLERS / PRODUCT CATALOG -->
<section class="section-wrapper" id="catalog" style="padding-top:0;">
    <div class="section-header-editorial">
        <span class="section-tag-gold">The Edit</span>
        <h2 class="section-title-editorial">Featured Catalog</h2>
        <p class="section-subtitle-editorial">Signature essentials engineered for everyday excellence.</p>
    </div>

    <div class="catalog-luxury-grid">
        <?php foreach ($products as $prod): ?>
            <?php
                $origPrice = (float)$prod['price'];
                $disc = (float)$prod['discount'];
                $salePrice = calculateSalePrice($origPrice, $disc);
            ?>
            <article class="prod-luxury-card">
                <div class="prod-img-stage">
                    <?php if ($disc > 0): ?>
                        <span class="prod-discount-tag">-<?php echo number_format($disc, 0); ?>%</span>
                    <?php endif; ?>
                    <a href="customer/product_details.php?id=<?php echo $prod['id']; ?>" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo htmlspecialchars(getProductImageUrl($prod['image'], '')); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" loading="lazy">
                    </a>
                </div>

                <div class="prod-info-block">
                    <span class="prod-category-label"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                    <a href="customer/product_details.php?id=<?php echo $prod['id']; ?>">
                        <h4 class="prod-item-title"><?php echo htmlspecialchars($prod['name']); ?></h4>
                    </a>

                    <div class="prod-star-row">
                        <span>★</span>
                        <strong><?php echo number_format($prod['avg_rating'], 1); ?></strong>
                        <small>(<?php echo $prod['review_count']; ?>)</small>
                    </div>

                    <div class="prod-price-stack">
                        <span class="prod-sale-amt">₹<?php echo number_format($salePrice, 2); ?></span>
                        <?php if ($disc > 0): ?>
                            <span class="prod-orig-amt">₹<?php echo number_format($origPrice, 2); ?></span>
                        <?php endif; ?>
                    </div>

                    <a href="customer/product_details.php?id=<?php echo $prod['id']; ?>" class="prod-btn-action">
                        View Details & Order
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- 7. FLASH SPOTLIGHT DEAL -->
<section class="section-wrapper" id="spotlight">
    <div class="spotlight-deal-wrap">
        <div>
            <span class="hero-badge-champagne" style="margin-bottom:14px;">EXCLUSIVE LIMITED RELEASE</span>
            <h2 style="font-family:'Cinzel',serif;font-size: clamp(26px, 3.5vw, 42px);font-weight:900;line-height:1.2;margin-bottom:16px;">
                Smart Watch Ultra Titanium Edition
            </h2>
            <p style="color:#d1d5db;font-size:14px;line-height:1.7;margin-bottom:20px;">
                Precision CNC-milled titanium casing with sapphire crystal OLED retina display, multi-frequency GPS, and continuous biometric health telemetry.
            </p>

            <div class="deal-countdown-row">
                <div class="countdown-box">
                    <strong id="cdHours">08</strong>
                    <span>Hours</span>
                </div>
                <div class="countdown-box">
                    <strong id="cdMinutes">42</strong>
                    <span>Mins</span>
                </div>
                <div class="countdown-box">
                    <strong id="cdSeconds">19</strong>
                    <span>Secs</span>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                <div>
                    <span style="font-size:12px;color:#9ca3af;display:block;">Special Price</span>
                    <strong style="font-size:26px;color:#ffffff;font-weight:900;">₹3,149.10</strong>
                    <span style="font-size:13px;color:#9ca3af;text-decoration:line-through;margin-left:6px;">₹3,499.00</span>
                </div>
                <a href="customer/product_details.php?id=2" class="btn-gold-primary">
                    Claim Deal Now →
                </a>
            </div>
        </div>

        <div class="spotlight-img-box">
            <img src="uploads/products/prod_smartwatch.jpg" alt="Smart Watch Ultra Titanium">
        </div>
    </div>
</section>

<!-- 8. THE FOUR PILLARS OF QARTIV -->
<section class="section-wrapper" id="pillars">
    <div class="section-header-editorial">
        <span class="section-tag-gold">The Qartiv Promise</span>
        <h2 class="section-title-editorial">Pillars of Distinction</h2>
        <p class="section-subtitle-editorial">We bridge luxury aesthetics with frictionless digital convenience.</p>
    </div>

    <div class="pillars-grid">
        <div class="pillar-card">
            <div class="pillar-icon-wrap">💎</div>
            <h4>Curated Authenticity</h4>
            <p>Every piece in our catalog is individually inspected, certified, and sourced directly from verified artisans and manufacturer partners.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon-wrap">⚡</div>
            <h4>Insured Express Courier</h4>
            <p>Orders over ₹999 are dispatched within 24 hours with full transit insurance and live SMS / WhatsApp tracking updates.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon-wrap">🛡️</div>
            <h4>Hassle-Free Returns</h4>
            <p>Shop with confidence. If an item doesn't exceed your expectations, enjoy our smooth 7-day pickup and full refund warranty.</p>
        </div>

        <div class="pillar-card">
            <div class="pillar-icon-wrap">🛎️</div>
            <h4>24/7 Dedicated Concierge</h4>
            <p>Our personal client advisors are available around the clock via live chat and email to assist with sizing, tech specs, or order inquiries.</p>
        </div>
    </div>
</section>

<!-- 9. TESTIMONIALS -->
<section class="section-wrapper" style="background:#faf8f4;border-radius:24px;margin-top:20px;margin-bottom:60px;">
    <div class="section-header-editorial">
        <span class="section-tag-gold">Client Reflections</span>
        <h2 class="section-title-editorial">Trusted By Connoisseurs</h2>
        <p class="section-subtitle-editorial">Read what verified patrons say about their Qartiv experience.</p>
    </div>

    <div class="testimonials-grid">
        <div class="testimonial-card">
            <div class="testimonial-text">
                "The wireless headphones completely exceeded my expectations. The acoustic depth is studio-grade and the matte black finish with champagne accents makes it look like a gallery piece."
            </div>
            <div class="testimonial-author-row">
                <div class="author-avatar">A</div>
                <div class="author-info">
                    <strong>Ananya Sharma</strong>
                    <span>Verified Buyer • Mumbai</span>
                </div>
            </div>
        </div>

        <div class="testimonial-card">
            <div class="testimonial-text">
                "Packaging was impeccable, delivery arrived in under 48 hours, and the minimal table lamp creates the warmest ambient light in my reading study. A genuinely premium platform."
            </div>
            <div class="testimonial-author-row">
                <div class="author-avatar">R</div>
                <div class="author-info">
                    <strong>Rohit Verma</strong>
                    <span>Verified Buyer • Bengaluru</span>
                </div>
            </div>
        </div>

        <div class="testimonial-card">
            <div class="testimonial-text">
                "Finding a website that balances high design standards with instant COD checkout and responsive customer care is rare. Qartiv is now my default gifting platform."
            </div>
            <div class="testimonial-author-row">
                <div class="author-avatar">M</div>
                <div class="author-info">
                    <strong>Manasa Maddula</strong>
                    <span>Verified Buyer • Guntur</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 10. INTERACTIVE FAQ -->
<section class="section-wrapper" id="faq" style="padding-top:0;">
    <div class="section-header-editorial">
        <span class="section-tag-gold">Clear Guidance</span>
        <h2 class="section-title-editorial">Frequently Asked Questions</h2>
        <p class="section-subtitle-editorial">Everything you need to know regarding orders, delivery, and payments.</p>
    </div>

    <div class="faq-accordion-wrap">
        <div class="faq-item active">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>What payment methods are supported on Qartiv?</span>
                <span class="faq-toggle-icon">+</span>
            </div>
            <div class="faq-answer">
                We accept Cash on Delivery (COD) across all serviceable pin codes in India, alongside UPI, NetBanking, and all major Credit/Debit cards upon checkout.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How fast is order dispatch and shipping?</span>
                <span class="faq-toggle-icon">+</span>
            </div>
            <div class="faq-answer">
                Orders placed before 2:00 PM are packaged and dispatched the same day. Metro deliveries arrive in 2 to 3 business days, while other regional hubs take 3 to 5 business days with insured courier partners.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How do I redeem promo coupons?</span>
                <span class="faq-toggle-icon">+</span>
            </div>
            <div class="faq-answer">
                During your cart review or final checkout page, enter your promo code (such as <strong>WELCOME10</strong> or <strong>QARTIV500</strong>) in the coupon field and click Apply. Your discount will automatically reflect in the order summary.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Can I cancel or modify an order after placing it?</span>
                <span class="faq-toggle-icon">+</span>
            </div>
            <div class="faq-answer">
                Yes! You can cancel your order directly from your <a href="customer/orders.php" style="color:#d4af37;font-weight:700;">My Orders</a> dashboard as long as the status is marked as 'Placed'.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Are all products authentic and under warranty?</span>
                <span class="faq-toggle-icon">+</span>
            </div>
            <div class="faq-answer">
                Every single item sold on Qartiv is 100% authentic, brand-new in original retail packaging, and backed by a minimum 1-year manufacturer guarantee with tax invoice documentation.
            </div>
        </div>
    </div>
</section>

<!-- 11. VIP CLUB NEWSLETTER -->
<section class="section-wrapper" style="padding-top:0;">
    <div class="vip-newsletter-card">
        <span class="section-tag-gold" style="color:var(--gold-light);">Private Access</span>
        <h3>Join The Qartiv Circle</h3>
        <p>Subscribe for exclusive access to seasonal drops, collector editions, and private invitations. Receive a 10% welcome privilege on your inaugural acquisition.</p>

        <form class="vip-form" onsubmit="event.preventDefault(); document.getElementById('vipSuccessMsg').style.display='block'; this.style.display='none';">
            <input type="email" placeholder="Enter your private email..." required>
            <button type="submit">Join Circle</button>
        </form>

        <div id="vipSuccessMsg" style="display:none;margin-top:16px;color:#a7f3d0;font-weight:700;font-size:14px;">
            ✓ Welcome to the Circle! Use code <strong>WELCOME10</strong> at checkout for 10% off.
        </div>
    </div>
</section>

<!-- 12. LUXURY EDITORIAL FOOTER -->
<footer class="footer-luxury">
    <div class="footer-grid">
        <div class="footer-brand">
            <strong>QARTIV</strong>
            <p>Smart E-Commerce Platform engineered for refined living. Curated excellence in audio, lifestyle technology, luxury fashion, and botanical wellness.</p>
            <div style="margin-top:16px;color:var(--gold-light);font-size:12px;font-weight:700;">
                ✦ Verified Authentic Commerce
            </div>
        </div>

        <div class="footer-col">
            <h5>Departments</h5>
            <ul>
                <?php foreach ($categories as $c): ?>
                    <li><a href="customer/products.php?category=<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h5>Customer Care</h5>
            <ul>
                <li><a href="customer/orders.php">Order Tracking</a></li>
                <li><a href="customer/cart.php">Shopping Bag</a></li>
                <li><a href="customer/wishlist.php">Wishlist</a></li>
                <li><a href="customer/account.php">Account Details</a></li>
                <li><a href="#faq">Support & FAQs</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h5>Private Concierge</h5>
            <p style="font-size:13px;line-height:1.7;margin-bottom:12px;">
                Direct Client Desk:<br>
                <strong style="color:#ffffff;">concierge@qartiv.com</strong><br>
                +91 (040) 8247-2621
            </p>
            <p style="font-size:11px;color:#6b7280;">
                Tech Park Avenue, 4th Floor, Cyber City, Hyderabad, India - 500081
            </p>
        </div>
    </div>

    <div class="footer-bottom">
        <div>
            © <?php echo date("Y"); ?> QARTIV Commerce Platform. All rights reserved.
        </div>
        <div style="display:flex;gap:18px;color:#9ca3af;">
            <span>Cash on Delivery</span>
            <span>UPI & NetBanking</span>
            <span>Visa & Mastercard</span>
            <span>SSL 256-bit Encrypted</span>
        </div>
    </div>
</footer>

<!-- 3D GOLD PARTICLE ENGINE FOR HERO CANVAS -->
<script>
(function() {
    const canvas = document.getElementById('heroGoldCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = canvas.parentElement.offsetHeight;

    window.addEventListener('resize', () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = canvas.parentElement.offsetHeight;
    });

    const particles = [];
    const count = 45;

    for (let i = 0; i < count; i++) {
        particles.push({
            x: Math.random() * width,
            y: Math.random() * height,
            radius: Math.random() * 2 + 0.8,
            speedY: -Math.random() * 0.4 - 0.1,
            speedX: (Math.random() - 0.5) * 0.3,
            alpha: Math.random() * 0.6 + 0.2
        });
    }

    function renderParticles() {
        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < count; i++) {
            const p = particles[i];
            p.y += p.speedY;
            p.x += p.speedX;

            if (p.y < 0) p.y = height;
            if (p.x < 0) p.x = width;
            if (p.x > width) p.x = 0;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(212, 175, 55, ${p.alpha})`;
            ctx.shadowBlur = 10;
            ctx.shadowColor = 'rgba(212, 175, 55, 0.5)';
            ctx.fill();
        }

        requestAnimationFrame(renderParticles);
    }
    renderParticles();
})();

// Accordion toggle
function toggleFaq(el) {
    const parent = el.parentElement;
    parent.classList.toggle('active');
}

// Countdown timer
(function() {
    let secs = 8 * 3600 + 42 * 60 + 19;
    setInterval(() => {
        if (secs > 0) secs--;
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;
        const hEl = document.getElementById('cdHours');
        const mEl = document.getElementById('cdMinutes');
        const sEl = document.getElementById('cdSeconds');
        if (hEl) hEl.textContent = String(h).padStart(2, '0');
        if (mEl) mEl.textContent = String(m).padStart(2, '0');
        if (sEl) sEl.textContent = String(s).padStart(2, '0');
    }, 1000);
})();
</script>

</body>
</html>