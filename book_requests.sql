CREATE TABLE IF NOT EXISTS book_requests (
    id INT NOT NULL AUTO_INCREMENT,
    book_id INT NOT NULL,
    borrower_id INT NOT NULL,
    quantity INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_book_requests_book_id (book_id),
    KEY idx_book_requests_borrower_id (borrower_id)
);
