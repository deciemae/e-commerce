<?php

function customerFavouriteProductIds(mysqli $conn, int $customerId, array $productIds = []): array
{
    if ($customerId <= 0) {
        return [];
    }

    $sql = 'SELECT product_id FROM customer_favourites WHERE user_id = ?';
    $params = [$customerId];
    $types = 'i';

    $normalizedIds = array_values(array_unique(array_filter(
        array_map('intval', $productIds),
        static fn(int $productId): bool => $productId > 0
    )));

    if ($normalizedIds) {
        $sql .= ' AND product_id IN (' . implode(',', array_fill(0, count($normalizedIds), '?')) . ')';
        $params = array_merge($params, $normalizedIds);
        $types .= str_repeat('i', count($normalizedIds));
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $favouriteIds = array_map('intval', array_column($result ? $result->fetch_all(MYSQLI_ASSOC) : [], 'product_id'));
    $stmt->close();

    return $favouriteIds;
}

function customerFavouriteProducts(mysqli $conn, int $customerId): array
{
    $stmt = $conn->prepare(
        'SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.image_url,
                c.category_name, cf.created_at AS favourited_at
         FROM customer_favourites cf
         INNER JOIN products p ON p.product_id = cf.product_id
         INNER JOIN categories c ON c.category_id = p.category_id
         WHERE cf.user_id = ?
         ORDER BY cf.created_at DESC, p.product_name ASC'
    );
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $products;
}

function customerFavouriteExists(mysqli $conn, int $customerId, int $productId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM customer_favourites WHERE user_id = ? AND product_id = ? LIMIT 1');
    $stmt->bind_param('ii', $customerId, $productId);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    return $exists;
}

function renderFavouriteButton(int $productId, bool $isFavourite, bool $isAuthenticated, string $class = ''): void
{
    $classes = trim('favourite-toggle ' . $class . ($isFavourite ? ' is-favourite' : ''));
    $label = $isFavourite ? 'Remove from favourites' : 'Add to favourites';
    ?>
    <button
        type="button"
        class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>"
        data-favourite-toggle
        data-product-id="<?= $productId ?>"
        data-authenticated="<?= $isAuthenticated ? '1' : '0' ?>"
        aria-label="<?= $label ?>"
        aria-pressed="<?= $isFavourite ? 'true' : 'false' ?>"
        title="<?= $label ?>"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.8 4.7a5.5 5.5 0 0 0-7.8 0L12 5.8l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.5a5.5 5.5 0 0 0 0-7.8Z"/>
        </svg>
    </button>
    <?php
}
