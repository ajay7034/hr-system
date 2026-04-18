<?php

function now_date(): string
{
    return date('Y-m-d');
}

function expiry_status(?string $expiryDate, int $alertDays = 30): string
{
    if (!$expiryDate) {
        return 'valid';
    }

    $today = strtotime(date('Y-m-d'));
    $expiry = strtotime($expiryDate);

    if ($expiry < $today) {
        return 'expired';
    }

    if ($expiry <= strtotime('+' . $alertDays . ' days', $today)) {
        return 'expiring_soon';
    }

    return 'valid';
}
