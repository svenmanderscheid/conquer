-- Lightweight one-to-one chat used by the permanent city/world chat dock.
CREATE TABLE IF NOT EXISTS private_chat_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id INT NOT NULL,
 sender_id BIGINT UNSIGNED NOT NULL,
 recipient_id BIGINT UNSIGNED NOT NULL,
 message VARCHAR(200) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX conversation_a(world_id,sender_id,recipient_id,id),
 INDEX conversation_b(world_id,recipient_id,sender_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
