<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

$customerActivePage = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-content">
        <div class="customer-hero mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="small text-uppercase text-white-50 fw-semibold mb-2">Our Story</div>
                    <h1 class="display-6 fw-bold mb-2">Bloom &amp; Basket</h1>
                    <p class="mb-0">Thoughtful home essentials, everyday favorites, and products designed to make life feel lighter and more beautiful.</p>
                </div>
                <a href="shop.php" class="btn btn-light btn-sm fw-semibold">Shop Now</a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="customer-card h-100">
                    <div class="card-header">Who We Are</div>
                    <div class="card-body p-4">
                        <p class="mb-3">Bloom &amp; Basket is a modern e-commerce store that brings together beautiful, practical, and affordable items for everyday living. We believe shopping should feel personal, simple, and inspiring, so we curate products that help customers organize, decorate, and enjoy their homes and routines.</p>
                        <p class="mb-0">From cozy essentials to lifestyle upgrades, our business was built to support customers who want quality products with a warm, welcoming shopping experience. We focus on items that add comfort, convenience, and charm to daily life while staying accessible to families and individuals alike.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="customer-card h-100">
                    <div class="card-header">Quick Facts</div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <div class="text-muted small text-uppercase fw-semibold">Business Type</div>
                            <div class="fw-bold">Lifestyle &amp; Home Essentials Store</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small text-uppercase fw-semibold">Founded</div>
                            <div class="fw-bold">2024</div>
                        </div>
                        <div class="mb-0">
                            <div class="text-muted small text-uppercase fw-semibold">Location</div>
                            <div class="fw-bold">Makati City, Philippines</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="customer-card h-100">
                    <div class="card-header">Our Mission</div>
                    <div class="card-body p-4">
                        <p class="mb-0">Our mission is to make quality lifestyle products easy to discover, enjoyable to buy, and meaningful to use. We aim to bring together style, practicality, and convenience so customers can build spaces and routines they genuinely love.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="customer-card h-100">
                    <div class="card-header">What We Offer</div>
                    <div class="card-body p-4">
                        <ul class="mb-0 ps-3">
                            <li>Home and décor essentials</li>
                            <li>Kitchen and daily-use products</li>
                            <li>Seasonal lifestyle items</li>
                            <li>Well-designed accessories for everyday living</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="customer-card">
            <div class="card-header">Contact Us</div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="fw-semibold mb-1">Email</div>
                        <div class="text-muted">hello@bloomandbasket.com</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-semibold mb-1">Phone</div>
                        <div class="text-muted">+63 917 123 4567</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-semibold mb-1">Address</div>
                        <div class="text-muted">18 Orchard Lane, Makati City, Philippines</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
