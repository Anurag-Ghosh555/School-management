<?php
// Include database and session files
require_once 'db.php';
require_once 'session.php';

// Redirect if already logged in
redirectIfLoggedIn();

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($admin_id) || empty($password)) {
        $error = 'Please enter both admin ID and password';
    } else {
        try {
            // Prepare SQL statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT * FROM Admin WHERE admin_id = :admin_id");
            $stmt->bindParam(':admin_id', $admin_id);
            $stmt->execute();
            
            // Check if admin exists
            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch();
                
                // Verify password (in a real system, you'd use password_verify with hashed passwords)
                if ($password === $admin['password']) {
                    // Authentication successful - create session
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    
                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'Admin ID not found';
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>Login - School Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(to bottom, #add8e6, #1e3a8a);
    }
  </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center">
  <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-lg">
    <div class="text-center">
      <img src="assets/logo.png" alt="Logo" class="h-16 w-16 rounded-full mx-auto mb-4"/>
      <h2 class="text-2xl font-semibold text-gray-800 mb-2">School Management</h2>
      <p class="text-gray-600 mb-6">Please sign in to continue.</p>
    </div>
    
    <form action="login.php" method="POST">
      <div class="mb-4">
        <label for="admin-id" class="block text-sm font-medium text-gray-700">Admin ID</label>
        <input type="text" id="admin-id" name="username" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required/>
      </div>
      
      <div class="mb-6">
        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
        <input type="password" id="password" name="password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required/>
      </div>
      
      <?php if (!empty($error)): ?>
        <p class="text-red-500 text-sm mb-4"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>
      
      <button type="submit" class="w-full py-2 px-4 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        Login
      </button>
    </form>
    <div class="mt-4 text-center">
      <p class="text-sm text-gray-600">Forgot your password? <a href="#" class="text-blue-600 hover:text-blue-700">Reset it</a></p>
    </div>
  </div>
  <!-- Footer -->
  <div class="mt-6 text-center text-white text-sm">
    © 2025 Anurag&Anusha. All rights reserved.
  </div>
</body>
</html>