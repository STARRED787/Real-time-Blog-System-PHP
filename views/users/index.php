<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/JWT.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/BlogModel.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize User model
$userModel = new User($pdo);

// Initialize BlogModel
$blogModel = new BlogModel($pdo);

// Initialize AuthMiddleware with both PDO and UserModel
$authMiddleware = new AuthMiddleware($pdo, $userModel);

// Check authentication
if (!$authMiddleware->isAuthenticated()) {
    header('Location: /KD Enterprise/blog-site/public/index.php');
    exit();
}

// Get user data
$user = $authMiddleware->getUser();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <script>
        let ws;
        let reconnectAttempts = 0;
        const maxReconnectAttempts = 5;

        function connectWebSocket() {
            ws = new WebSocket('ws://localhost:8080');
            
            ws.onopen = function() {
                console.log('WebSocket connected');
                reconnectAttempts = 0;
            };
            
            ws.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('Received message:', data);
                    
                    switch(data.type) {
                        case 'create':
                            addBlogToList(data.blog);
                            break;
                        case 'update':
                            updateBlogInList(data.blog);
                            break;
                        case 'delete':
                            removeBlogFromList(data.blogId);
                            break;
                    }
                } catch (error) {
                    console.error('Error processing WebSocket message:', error);
                }
            };
            
            ws.onerror = function(error) {
                console.error('WebSocket error:', error);
            };
            
            ws.onclose = function() {
                console.log('WebSocket disconnected');
                if (reconnectAttempts < maxReconnectAttempts) {
                    reconnectAttempts++;
                    setTimeout(connectWebSocket, 5000);
                }
            };
        }

        function addBlogToList(blog) {
            const container = document.getElementById('blogContainer');
            const blogElement = createBlogElement(blog);
            container.insertAdjacentHTML('afterbegin', blogElement);
        }

        function updateBlogInList(blog) {
            const element = document.querySelector(`[data-blog-id="${blog.id}"]`);
            if (element) {
                element.outerHTML = createBlogElement(blog);
            }
        }

        function removeBlogFromList(blogId) {
            const element = document.querySelector(`[data-blog-id="${blogId}"]`);
            if (element) element.remove();
        }

        function createBlogElement(blog) {
            return `
                <div class="card mb-3" data-blog-id="${blog.id}">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(blog.title)}</h5>
                        <p class="card-text">${escapeHtml(blog.content)}</p>
                        <p class="card-text"><small class="text-muted">Created at: ${blog.created_at}</small></p>
                    </div>
                </div>
            `;
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Initialize WebSocket connection
        connectWebSocket();
    </script>

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">User Dashboard</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">Welcome,
                    <?php echo htmlspecialchars($user['username']); ?></span>
                <a class="nav-link" href="/KD Enterprise/blog-site/auth/logout.php">Logout</a>
            </div>


        </div>
    </nav>

    <div class="container mt-4">
        <h1>Latest Blogs</h1>
        <?php 
        $blogs = $blogModel->getAllBlogs();
        if (empty($blogs)): ?>
            <div class="alert alert-info">No blogs available at the moment.</div>
        <?php else: ?>
            <div id="blogContainer">
                <?php foreach ($blogs as $blog): ?>
                    <div class="card mb-3" data-blog-id="<?php echo $blog['id']; ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($blog['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($blog['content']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">Created at: <?php echo $blog['created_at']; ?></small>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>