<?php
session_start();
require_once 'includes/customer_system.php';

customerLogout();

header('Location: customer_login.php');
exit;
