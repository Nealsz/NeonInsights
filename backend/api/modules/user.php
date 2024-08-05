<?php
// backend/api/modules/user.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/global.php';

class User {
    private $conn;
    private $table_name = 'users';

    public $id;
    public $email;
    public $username;
    public $password;
    public $old_password;
    public $profile_picture;
    public $phone_number;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register() {
        $query = "INSERT INTO " . $this->table_name . " SET email=:email, username=:username, password=:password";
        
        $stmt = $this->conn->prepare($query);
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);

        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':password', $this->password);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function login() {
        $query = "SELECT id, email, username, password FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);

        $this->email = htmlspecialchars(strip_tags($this->email));
        $stmt->bindParam(':email', $this->email);

        $stmt->execute();
        $num = $stmt->rowCount();

        if ($num > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($this->password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                return true;
            }
        }
        return false;
    }

    public function editProfile() {
        // Verify old password if new password is provided
        if (!empty($this->password) && !empty($this->old_password)) {
            $query = "SELECT password FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($this->old_password, $row['password'])) {
                return false; // Old password is incorrect
            }

            $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        }

        $query = "UPDATE " . $this->table_name . " 
                  SET email = :email, username = :username, profile_picture = :profile_picture, phone_number = :phone_number";

        // Only update password if it's provided
        if (!empty($this->password)) {
            $query .= ", password = :password";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->profile_picture = htmlspecialchars(strip_tags($this->profile_picture));
        $this->phone_number = htmlspecialchars(strip_tags($this->phone_number));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':profile_picture', $this->profile_picture);
        $stmt->bindParam(':phone_number', $this->phone_number);
        $stmt->bindParam(':id', $this->id);

        if (!empty($this->password)) {
            $stmt->bindParam(':password', $this->password);
        }

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }
}
?>
