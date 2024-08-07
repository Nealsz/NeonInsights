<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/modules/global.php';
require_once __DIR__ . '/modules/user.php';
require_once __DIR__ . '/modules/blogpost.php';
require_once __DIR__ . '/modules/tag.php';
require_once __DIR__ . '/modules/comment.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

if ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    // Registration Endpoint
    if (strpos($requestUri, '/api/register') !== false) {
        if (!empty($data->email) && !empty($data->username) && !empty($data->password)) {
            $user = new User($db);
            $user->email = $data->email;
            $user->username = $data->username;
            $user->password = $data->password;

            if ($user->register()) {
                GlobalMethods::sendPayload(array("message" => "User was registered."), 201);
            } else {
                GlobalMethods::sendPayload(array("message" => "Unable to register user."), 503);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }

    // Login Endpoint
    if (strpos($requestUri, '/api/login') !== false) {
        if (!empty($data->email) && !empty($data->password)) {
            $user = new User($db);
            $user->email = $data->email;
            $user->password = $data->password;

            if ($user->login()) {
                $token = GlobalMethods::generateJWT($user->id, $user->email, $user->username);
                GlobalMethods::sendPayload(array("message" => "Login successful.", "token" => $token), 200);
            } else {
                GlobalMethods::sendPayload(array("message" => "Invalid credentials."), 401);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }

    // Blog Creation Endpoint
    if (strpos($requestUri, '/api/blog/create') !== false) {
        if (!empty($data->title) && !empty($data->description) && !empty($data->user_id)) {
            $blogPost = new BlogPost($db);
            $blogPost->title = $data->title;
            $blogPost->description = $data->description;
            $blogPost->image = $data->image;
            $blogPost->user_id = $data->user_id;

            $post_id = $blogPost->create();
            if ($post_id) {
                if (!empty($data->tags)) {
                    $tag = new Tag($db);
                    $tag->addTags($post_id, $data->tags);
                }
                GlobalMethods::sendPayload(array("message" => "Blog post was created."), 201);
            } else {
                GlobalMethods::sendPayload(array("message" => "Unable to create blog post."), 503);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }

    // Comment Creation Endpoint
    if (strpos($requestUri, '/api/comment/create') !== false) {
        if (!empty($data->content) && !empty($data->user_id) && !empty($data->post_id)) {
            $comment = new Comment($db);
            $comment->content = $data->content;
            $comment->user_id = $data->user_id;
            $comment->post_id = $data->post_id;

            if ($comment->create()) {
                GlobalMethods::sendPayload(array("message" => "Comment was created."), 201);
            } else {
                GlobalMethods::sendPayload(array("message" => "Unable to create comment."), 503);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }
}

if ($requestMethod === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));

    // Edit Profile Endpoint
    if (strpos($requestUri, '/api/user/edit') !== false) {
        if (!empty($data->id) && !empty($data->email) && !empty($data->username)) {
            $user = new User($db);
            $user->id = $data->id;
            $user->email = $data->email;
            $user->username = $data->username;
            $user->profile_picture = isset($data->profile_picture) ? $data->profile_picture : null;
            $user->phone_number = isset($data->phone_number) ? $data->phone_number : null;

            // Only set the password if it's provided
            if (!empty($data->password)) {
                $user->password = $data->password;
                $user->old_password = $data->old_password;
            }

            if ($user->editProfile()) {
                GlobalMethods::sendPayload(array("message" => "User profile was updated."), 200);
            } else {
                GlobalMethods::sendPayload(array("message" => "Unable to update user profile."), 503);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }

    // Blog Update Endpoint
    if (strpos($requestUri, '/api/blog/update') !== false && $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $user_id = $_POST['user_id'];
    
        $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
    
        $blogPost = new BlogPost($db);
        $blogPost->id = $id;
        $blogPost->title = $title;
        $blogPost->description = $description;
        $blogPost->user_id = $user_id;
    
        if ($blogPost->update($tags)) {
            GlobalMethods::sendPayload(array("message" => "Blog post was updated."), 200);
        } else {
            GlobalMethods::sendPayload(array("message" => "Unable to update blog post."), 503);
        }
    }
}

if ($requestMethod === 'PATCH') {
    $data = json_decode(file_get_contents("php://input"));

    // Comment Update Endpoint
    if (strpos($requestUri, '/api/comment/update') !== false) {
        if (!empty($data->id) && !empty($data->user_id) && !empty($data->post_id) && !empty($data->content)) {
            $comment = new Comment($db);
            $comment->id = $data->id;
            $comment->user_id = $data->user_id;
            $comment->post_id = $data->post_id;
            $comment->content = $data->content;

            if ($comment->update()) {
                GlobalMethods::sendPayload(array("message" => "Comment was updated."), 200);
            } else {
                GlobalMethods::sendPayload(array("message" => "Unable to update comment."), 503);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }
}


if ($requestMethod === 'GET') {
    // Fetch All Blogs Endpoint
    if (strpos($requestUri, '/api/blogs') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $user_id = $_POST['user_id'];
    
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = file_get_contents($_FILES['image']['tmp_name']);
        }
    
        $blogPost = new BlogPost($db);
        if ($blogPost->create($title, $description, $user_id, $image)) {
            GlobalMethods::sendPayload(["message" => "Blog post created successfully."], 201);
        } else {
            GlobalMethods::sendPayload(["message" => "Failed to create blog post."], 500);
        }
    }

    // Blog Image Endpoint

    if (strpos($requestUri, '/api/blogs/image') !== false && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $postId = $_GET['id'];
        $blogPost = new BlogPost($db);
        $blogPost->getBlogImage($postId);
    }

    // Search Blogs by Tag Endpoint
    if (strpos($requestUri, '/api/blogs/search') !== false) {
        $tags = array_filter(explode(',', $_GET['tags'] ?? ''));
        if (count($tags) > 0) {
            $tag = new Tag($db);
            $blogs = $tag->getByTag($tags);
            GlobalMethods::sendPayload($blogs, 200);
        } else {
            GlobalMethods::sendPayload(array("message" => "Tags parameter is missing."), 400);
        }
    }

    // Get Comments by Post ID Endpoint
    if (strpos($requestUri, '/api/comments') !== false) {
        $post_id = $_GET['post_id'] ?? null;
        if ($post_id) {
            $comment = new Comment($db);
            $comments = $comment->getCommentsByPost($post_id);
            GlobalMethods::sendPayload($comments, 200);
        } else {
            GlobalMethods::sendPayload(array("message" => "Post ID parameter is missing."), 400);
        }
    }

}

if ($requestMethod === 'DELETE') {
    // Blog Delete Endpoint
    if (strpos($requestUri, '/api/blog/delete') !== false) {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id) && !empty($data->user_id)) {
            $blogPost = new BlogPost($db);
            $blogPost->id = $data->id;
            $blogPost->user_id = $data->user_id;

            // Check if the user is the owner of the blog post
            if ($blogPost->isOwner()) {
                if ($blogPost->delete()) {
                    GlobalMethods::sendPayload(array("message" => "Blog post and comments were deleted."), 200);
                } else {
                    GlobalMethods::sendPayload(array("message" => "Unable to delete blog post."), 503);
                }
            } else {
                GlobalMethods::sendPayload(array("message" => "Unauthorized action."), 401);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }

    // Comment Delete Endpoint
    if (strpos($requestUri, '/api/comment/delete') !== false) {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->user_id) && !empty($data->id)) {
            $comment = new Comment($db);
            $comment->user_id = $data->user_id;
            $comment->id = $data->id;

            if ($comment->delete()) {
                GlobalMethods::sendPayload(array("message" => "Comment was deleted."), 200);
            } else {
                GlobalMethods::sendPayload(array("message" => "Unable to delete comment."), 503);
            }
        } else {
            GlobalMethods::sendPayload(array("message" => "Incomplete data."), 400);
        }
    }
}
