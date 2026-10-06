<?php

require_once __DIR__ . '/session.php';
startApplicationSession();

function strongPasswordPattern(): string
{
    return '^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$';
}

function strongPasswordMessage(): string
{
    return 'Password must be at least 8 characters long and include letters, numbers, and special characters.';
}

function passwordMeetsPolicy(string $password): bool
{
    return preg_match('/' . strongPasswordPattern() . '/', $password) === 1;
}

function phoneNumberIsValid(string $phoneNumber): bool
{
    return $phoneNumber === '' || preg_match('/^[0-9+()\-\s]{7,20}$/', $phoneNumber) === 1;
}
