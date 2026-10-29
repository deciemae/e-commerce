-- Purpose: Persist products saved by authenticated customers.
-- Preconditions: users and products tables exist and use InnoDB.
-- Backup: Create and verify a logical backup before applying in a valuable environment.
-- Rollback guidance: DROP TABLE customer_favourites; only after confirming saved-product data may be discarded.

CREATE TABLE customer_favourites (
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    KEY fk_customer_favourites_product (product_id),
    CONSTRAINT fk_customer_favourites_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_customer_favourites_product
        FOREIGN KEY (product_id) REFERENCES products (product_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
