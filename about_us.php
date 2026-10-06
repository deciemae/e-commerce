<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

$customerActivePage = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page about-page">

<?php require_once 'includes/customer_nav.php'; ?>

<main id="main-content">
    <section class="about-hero" aria-labelledby="about-page-title">
        <div class="about-hero-copy">
            <p class="about-eyebrow">Our story</p>
            <h1 id="about-page-title">Everyday living, thoughtfully gathered.</h1>
            <p class="about-hero-intro">Thoughtful home essentials, everyday favorites, and products selected to make daily life feel lighter and more beautiful.</p>
            <a href="shop.php" class="about-primary-action">Shop products</a>
        </div>
        <figure class="about-hero-media">
            <img src="assets/hero-image.jpg" alt="A neutral collection of clothing, accessories, and gift bags" width="512" height="287">
        </figure>
    </section>

    <section class="about-story about-section" aria-labelledby="about-story-title">
        <div class="about-section-heading">
            <p class="about-eyebrow">Who we are</p>
            <h2 id="about-story-title">Made for the moments that make up a day.</h2>
        </div>
        <div class="about-story-copy">
            <p>Bloom &amp; Basket is a modern e-commerce store that brings together beautiful, practical, and affordable items for everyday living. We believe shopping should feel personal, simple, and inspiring, so we curate products that help customers organize, decorate, and enjoy their homes and routines.</p>
            <p>From cozy essentials to lifestyle upgrades, our business was built to support customers who want quality products with a warm, welcoming shopping experience. We focus on items that add comfort, convenience, and charm to daily life while staying accessible to families and individuals alike.</p>
        </div>
    </section>

    <section class="about-facts" aria-label="Bloom and Basket quick facts">
        <div class="about-fact">
            <span>Business type</span>
            <strong>Lifestyle &amp; home essentials</strong>
        </div>
        <div class="about-fact">
            <span>Founded</span>
            <strong>2024</strong>
        </div>
        <div class="about-fact">
            <span>Location</span>
            <strong>Makati City, Philippines</strong>
        </div>
    </section>

    <section class="about-purpose about-section" aria-labelledby="about-mission-title">
        <div class="about-purpose-panel">
            <p class="about-eyebrow">Our mission</p>
            <h2 id="about-mission-title">Style, practicality, and convenience in one place.</h2>
            <p>Our mission is to make quality lifestyle products easy to discover, enjoyable to buy, and meaningful to use. We aim to bring together style, practicality, and convenience so customers can build spaces and routines they genuinely love.</p>
        </div>

        <div class="about-offer-panel">
            <p class="about-eyebrow">What we offer</p>
            <h2>Useful finds for everyday life.</h2>
            <ul class="about-offer-list">
                <li><span>01</span>Home and d&eacute;cor essentials</li>
                <li><span>02</span>Kitchen and daily-use products</li>
                <li><span>03</span>Seasonal lifestyle items</li>
                <li><span>04</span>Well-designed everyday accessories</li>
            </ul>
        </div>
    </section>

    <section class="about-contact about-section" aria-labelledby="about-contact-title">
        <div>
            <p class="about-eyebrow">Contact us</p>
            <h2 id="about-contact-title">We&rsquo;d love to hear from you.</h2>
        </div>
        <div class="about-contact-list">
            <div>
                <span>Email</span>
                <a href="mailto:hello@bloomandbasket.com">hello@bloomandbasket.com</a>
            </div>
            <div>
                <span>Phone</span>
                <a href="tel:+639171234567">+63 917 123 4567</a>
            </div>
            <div>
                <span>Address</span>
                <address>18 Orchard Lane, Makati City, Philippines</address>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/customer_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
