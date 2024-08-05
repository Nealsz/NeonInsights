<?php
// backend/api/modules/comment.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/global.php';

class Comment {
    private $conn;
    private $table_name = 'comments';

    public $id;
    public $user_id;
    public $post_id;
    public $content;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create comment
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET user_id=:user_id, post_id=:post_id, content=:content, created_at=NOW()";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':post_id', $this->post_id);
        $stmt->bindParam(':content', $this->content);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET content = :content 
                  WHERE id = :id AND user_id = :user_id AND post_id = :post_id";
        $stmt = $this->conn->prepare($query);
    
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->post_id = htmlspecialchars(strip_tags($this->post_id));
        $this->content = htmlspecialchars(strip_tags($this->content));
    
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':post_id', $this->post_id);
        $stmt->bindParam(':content', $this->content);
    
        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete comment
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND (user_id = :user_id OR post_id IN (SELECT id FROM blog_posts WHERE user_id = :user_id))";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    // Get comments by post ID
    public function getCommentsByPost($post_id) {
        $query = "SELECT c.id, c.user_id, c.post_id, c.content, c.created_at, u.username 
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.user_id = u.id
                  WHERE c.post_id = :post_id
                  ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();

        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $comments;
    }
}
?>
