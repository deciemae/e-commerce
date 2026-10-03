<?php

function fetchProductColors(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare('SELECT color FROM product_colors WHERE product_id = ? ORDER BY product_color_id ASC');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $colors = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'color');
    $stmt->close();

    return $colors;
}

function fetchProductColorsForIds(mysqli $conn, array $productIds): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if (empty($productIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $conn->prepare("SELECT product_id, color FROM product_colors WHERE product_id IN ($placeholders) ORDER BY product_color_id ASC");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['product_id']][] = $row['color'];
    }

    return $map;
}

function setProductColors(mysqli $conn, int $productId, string $commaSeparatedColors): void
{
    $colors = array_values(array_unique(array_filter(array_map('trim', explode(',', $commaSeparatedColors)))));

    $delete = $conn->prepare('DELETE FROM product_colors WHERE product_id = ?');
    $delete->bind_param('i', $productId);
    $delete->execute();
    $delete->close();

    if (empty($colors)) {
        return;
    }

    $insert = $conn->prepare('INSERT INTO product_colors (product_id, color) VALUES (?, ?)');
    foreach ($colors as $color) {
        $insert->bind_param('is', $productId, $color);
        $insert->execute();
    }
    $insert->close();
}
