<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/global.php';

class Tag {
    private $conn;
    private $tags_table = 'tags';
    private $blog_post_tags_table = 'blog_post_tags'; 

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addTags($post_id, $tags) {
        foreach ($tags as $tag) {
            $tag_id = $this->getTagId($tag);
            if (!$tag_id) {
                $tag_id = $this->createTag($tag);
            }
            if ($tag_id) {
                $this->insertTagIntoPost($post_id, $tag_id);
            }
        }
        return true;
    }

    private function getTagId($tag) {
        $query = "SELECT id FROM " . $this->tags_table . " WHERE tag = :tag";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tag', $tag);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['id'] : null;
    }

    private function createTag($tag) {
        $query = "INSERT INTO " . $this->tags_table . " (tag) VALUES (:tag)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tag', $tag);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    // Add or update tags for a blog post
    public function updateTags($postId, $tags) {
        try {
            // Begin transaction
            $this->conn->beginTransaction();

            // Remove existing tags for this post
            $removeQuery = "DELETE FROM " . $this->blog_post_tags_table . " WHERE post_id = :post_id";
            $removeStmt = $this->conn->prepare($removeQuery);
            $removeStmt->bindParam(':post_id', $postId);
            $removeStmt->execute();

            // Insert new tags
            $insertQuery = "INSERT INTO " . $this->blog_post_tags_table . " (post_id, tag_id) VALUES (:post_id, :tag_id)";
            $insertStmt = $this->conn->prepare($insertQuery);

            foreach ($tags as $tag) {
                // Check if the tag already exists
                $checkQuery = "SELECT id FROM " . $this->tags_table . " WHERE tag = :tag";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->bindParam(':tag', $tag);
                $checkStmt->execute();
                $tagId = $checkStmt->fetchColumn();

                if (!$tagId) {
                    // Insert new tag if it doesn't exist
                    $insertTagQuery = "INSERT INTO " . $this->tags_table . " (tag) VALUES (:tag)";
                    $insertTagStmt = $this->conn->prepare($insertTagQuery);
                    $insertTagStmt->bindParam(':tag', $tag);
                    $insertTagStmt->execute();
                    $tagId = $this->conn->lastInsertId();
                }

                // Insert tag into blog_post_tags table
                $insertStmt->bindParam(':post_id', $postId);
                $insertStmt->bindParam(':tag_id', $tagId);
                $insertStmt->execute();
            }

            // Commit transaction
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            // Rollback transaction if something failed
            $this->conn->rollBack();
            return false;
        }
    }
    
    private function insertTagIntoPost($post_id, $tag_id) {
        $query = "INSERT INTO " . $this->blog_post_tags_table . " (post_id, tag_id) VALUES (:post_id, :tag_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':tag_id', $tag_id);

        return $stmt->execute();
    }

    public function getPostIdsByTag($tags) {
        if (empty($tags)) {
            return [];
        }
    
        $placeholders = implode(',', array_fill(0, count($tags), '?'));
    
        $query = "SELECT DISTINCT bp.id
                  FROM blog_posts bp
                  JOIN " . $this->blog_post_tags_table . " bpt ON bp.id = bpt.post_id
                  JOIN " . $this->tags_table . " t ON bpt.tag_id = t.id
                  WHERE t.tag IN ($placeholders)";
    
        $stmt = $this->conn->prepare($query);
        $stmt->execute($tags);
    
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getPostsWithAllTags($post_ids) {
        if (empty($post_ids)) {
            return [];
        }
    
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
    
        $query = "SELECT bp.id, bp.title, bp.description, bp.image, bp.date_uploaded, bp.user_id, 
                         GROUP_CONCAT(t.tag SEPARATOR ', ') as tags
                  FROM blog_posts bp
                  JOIN " . $this->blog_post_tags_table . " bpt ON bp.id = bpt.post_id
                  JOIN " . $this->tags_table . " t ON bpt.tag_id = t.id
                  WHERE bp.id IN ($placeholders)
                  GROUP BY bp.id";
    
        $stmt = $this->conn->prepare($query);
        $stmt->execute($post_ids);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getByTag($tags) {
        if (empty($tags)) {
            return [];
        }
    
        // Step 1: Fetch post IDs by tags
        $post_ids = $this->getPostIdsByTag($tags);
    
        // Step 2: Fetch posts with all tags
        return $this->getPostsWithAllTags($post_ids);
    }    
    
}
