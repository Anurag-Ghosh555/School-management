<?php
// Include database and session files
date_default_timezone_set("Asia/Kolkata");
require_once 'db.php';
require_once 'session.php';

// Ensure user is logged in
requireLogin();

// Get admin name from session
$adminId = $_SESSION['admin_id'];

// Handle logout action
if (isset($_GET['logout'])) {
    logout();
}

// Initialize messages array
$messages = [];

// Handle student delete
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $student_id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM Student WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $messages[] = ['type' => 'success', 'text' => 'Student deleted successfully.'];
    } catch(PDOException $e) {
        $messages[] = ['type' => 'error', 'text' => 'Error deleting student: ' . $e->getMessage()];
    }
}

// Handle form submissions for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_id = $_POST['student_id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $class_id = $_POST['class_id'] ?? null;
    $gender = $_POST['gender'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Validate form data
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($gender)) $errors[] = "Gender is required";
    
    if (empty($errors)) {
        try {
            // If student_id is provided, update existing student
            if (!empty($student_id)) {
                $stmt = $conn->prepare("UPDATE Student SET 
                    first_name = ?, 
                    last_name = ?, 
                    class_id = ?, 
                    gender = ?, 
                    contact_number = ?, 
                    email = ? 
                    WHERE student_id = ?");
                $stmt->execute([$first_name, $last_name, $class_id ?: null, $gender, $contact_number, $email, $student_id]);
                $messages[] = ['type' => 'success', 'text' => 'Student updated successfully.'];
            } 
            // Otherwise, add new student
            else {
                $stmt = $conn->prepare("INSERT INTO Student 
                    (first_name, last_name, class_id, gender, contact_number, email) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $class_id ?: null, $gender, $contact_number, $email]);
                $messages[] = ['type' => 'success', 'text' => 'Student added successfully.'];
            }
        } catch(PDOException $e) {
            $messages[] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    } else {
        // If there are validation errors
        foreach ($errors as $error) {
            $messages[] = ['type' => 'error', 'text' => $error];
        }
    }
}

// Get selected student for editing
$selectedStudent = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM Student WHERE student_id = ?");
        $stmt->execute([$_GET['edit']]);
        $selectedStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $messages[] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }
}

// Fetch all classes for dropdown
try {
    $stmt = $conn->prepare("SELECT class_id, class_name FROM Class ORDER BY class_name");
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch(PDOException $e) {
    $messages[] = ['type' => 'error', 'text' => 'Error fetching classes: ' . $e->getMessage()];
}

// Fetch students data - UPDATED SQL QUERY to match your database structure
try {
    $stmt = $conn->prepare("SELECT s.*, c.class_name FROM Student s 
                           LEFT JOIN Class c ON s.class_id = c.class_id 
                           ORDER BY s.student_id");
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch(PDOException $e) {
    $messages[] = ['type' => 'error', 'text' => "Error: " . $e->getMessage()];
}

// Determine active page
$currentPage = 'students';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>Students - School Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            dark: {
              bg: '#1a1a1a',
              card: '#2d2d2d',
              text: '#e0e0e0',
              border: '#4a4a4a'
            }
          }
        }
      }
    }
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
   body {
      font-family: 'Inter', sans-serif;
    }
    .dropdown {
      position: relative;
      display: inline-block;
    }
    .dropdown-content {
      position: absolute;
      right: 0;
      min-width: 160px;
      z-index: 10;
      visibility: hidden;
    }
    .dropdown-content.show {
      visibility: visible;
      transform: scale(1);
      opacity: 1;
    }
    /* Tab active state */
    .nav-tab.active {
      color: #3b82f6;
      font-weight: 600;
    }
    .dark .nav-tab.active {
      color: #60a5fa;
      font-weight: 600;
    }
    /* Transition for tabs */
    .tab-transition {
      transition: all 0.3s ease-in-out;
    }
    /* Tab hover animation */
    .nav-tab {
      position: relative;
      overflow: hidden;
    }
    .nav-tab::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 0;
      height: 2px;
      background-color: #3b82f6;
      transition: all 0.3s ease-in-out;
      transform: translateX(-50%);
    }
    .dark .nav-tab::after {
      background-color: #60a5fa;
    }
    .nav-tab:hover::after {
      width: 100%;
    }
    .nav-tab:hover {
      color: #3b82f6;
      transform: translateY(-2px);
    }
    .dark .nav-tab:hover {
      color: #60a5fa;
    }
    /* Responsive tab menu */
    .tab-menu {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none; /* Firefox */
    }
    .tab-menu::-webkit-scrollbar {
      display: none; /* Chrome, Safari, Opera */
    }
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      z-index: 50;
      overflow-y: auto;
    }
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      width: 100%;
      max-width: 500px;
      margin: 2rem auto;
      animation: modal-appear 0.3s ease-out forwards;
    }
    @keyframes modal-appear {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
 </head>
 <body class="bg-gray-50 dark:bg-dark-bg min-h-screen flex flex-col transition-colors duration-200">
  <!-- Header with tabs -->
  <header class="bg-white dark:bg-dark-card shadow-md dark:shadow-gray-900/30 sticky top-0 z-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <div class="flex items-center space-x-3">
          <img alt="School Management logo" class="h-10 w-10 rounded" src="assets/logo.png"/>
          <h1 class="text-xl font-semibold text-gray-800 dark:text-white truncate hidden sm:block">
            School Management
          </h1>
        </div>
        
        <!-- Current Date and Time -->
        <div id="current-datetime" class="hidden md:flex items-center text-gray-600 dark:text-gray-300">
          <i class="fas fa-clock mr-2"></i>
          <span id="datetime-display"><?php echo date('F j, Y - g:i A'); ?></span>
        </div>
        
        <div class="flex items-center space-x-4">
          <!-- Dark mode toggle -->
          <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <i class="fas fa-moon text-gray-600 dark:text-yellow-300 dark:hidden"></i>
            <i class="fas fa-sun text-yellow-400 hidden dark:block"></i>
          </button>
          
          <!-- User dropdown -->
          <div class="dropdown">
            <button id="dropdown-button" aria-label="User menu" class="flex items-center space-x-2 focus:outline-none">
              <img alt="User avatar" class="rounded-full" height="32" src="assets/<?php echo htmlspecialchars($adminId); ?>.jpg" width="32"/>
              <span class="hidden sm:block text-gray-700 dark:text-gray-200 font-medium">
                <?php echo htmlspecialchars($adminId); ?>
              </span>
              <i id="dropdown-arrow" class="fas fa-chevron-down text-gray-500 dark:text-gray-400 transition-transform duration-300"></i>
            </button>
            <div id="dropdown-menu" class="dropdown-content bg-white dark:bg-dark-card shadow-lg rounded-md mt-2 py-1 border border-gray-200 dark:border-dark-border transform origin-top-right scale-95 opacity-0 transition-all duration-200 ease-out">
              <a href="?logout=1" id="logout-btn" class="block px-4 py-2 text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">Logout</a>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Navigation Tabs -->
      <div class="tab-menu flex mt-2">
        <a href="dashboard.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-tachometer-alt w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Dashboard</span>
        </a>
        <a href="students.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'students' ? 'active' : ''; ?>">
          <i class="fas fa-user-graduate w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Students</span>
        </a>
        <a href="teachers.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-chalkboard-teacher w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Teachers</span>
        </a>
        <a href="classes.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-school w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Classes</span>
        </a>
        <a href="extracurricular.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-running w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Extracurricular</span>
        </a>
        <a href="events.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-calendar-alt w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Events</span>
        </a>
        <a href="transport.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-bus w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Transport</span>
        </a>
        <a href="complaints.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-exclamation-circle w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Complaints</span>
        </a>
        <a href="feedback.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-comment-alt w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Feedback</span>
        </a>
        <a href="attendance.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-clipboard-check w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Attendance</span>
        </a>
      </div>
    </div>
  </header>
    
  <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Students List Section -->
    <section class="mb-8">
      <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white flex items-center">
          <i class="fas fa-user-graduate text-blue-500 dark:text-blue-400 mr-3"></i> Students
      </h2>
        <button id="add-student-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition-colors flex items-center">
          <i class="fas fa-plus mr-2"></i> Add Student
        </button>
      </div>
      
      <!-- Message display area -->
      <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $message): ?>
          <div class="<?php echo $message['type'] === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?> px-4 py-3 rounded mb-4 border">
            <?php echo $message['text']; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      
      <!-- Students Table -->
      <div class="bg-white dark:bg-dark-card rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
            <thead class="bg-gray-50 dark:bg-dark-card">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Class</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Gender</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-dark-border">
              <?php if (!empty($students)): ?>
                <?php foreach ($students as $student): ?>
                  <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($student['student_id']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                      <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($student['gender']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($student['contact_number']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($student['email']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <a href="?edit=<?php echo $student['student_id']; ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $student['student_id']; ?>)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No students found</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
  
  <!-- Student Form Modal -->
  <div id="student-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl p-6 max-w-lg mx-4">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800 dark:text-white" id="modal-title">
          <?php echo isset($selectedStudent) ? 'Edit Student' : 'Add New Student'; ?>
        </h3>
        <button id="close-modal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form id="student-form" method="POST">
        <!-- Hidden field for student ID when editing -->
        <input type="hidden" name="student_id" value="<?php echo $selectedStudent['student_id'] ?? ''; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <!-- First Name -->
          <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name*</label>
            <input type="text" name="first_name" id="first_name" value="<?php echo $selectedStudent['first_name'] ?? ''; ?>" required
                   class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
          </div>
          
          <!-- Last Name -->
          <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name*</label>
            <input type="text" name="last_name" id="last_name" value="<?php echo $selectedStudent['last_name'] ?? ''; ?>" required
                   class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <!-- Class -->
          <div>
            <label for="class_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class</label>
            <select name="class_id" id="class_id" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="">Select Class</option>
              <?php if (isset($classes)): ?>
                <?php foreach ($classes as $class): ?>
                  <option value="<?php echo $class['class_id']; ?>" <?php echo (isset($selectedStudent) && $selectedStudent['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($class['class_name']); ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          
          <!-- Gender -->
          <div>
            <label for="gender" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gender*</label>
            <select name="gender" id="gender" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="">Select Gender</option>
              <option value="Male" <?php echo (isset($selectedStudent) && $selectedStudent['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo (isset($selectedStudent) && $selectedStudent['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
              <option value="Other" <?php echo (isset($selectedStudent) && $selectedStudent['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <!-- Contact Number -->
          <div>
            <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
            <input type="tel" name="contact_number" id="contact_number" value="<?php echo $selectedStudent['contact_number'] ?? ''; ?>"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
          </div>
          
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input type="email" name="email" id="email" value="<?php echo $selectedStudent['email'] ?? ''; ?>"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
          </div>
        </div>
        
        <div class="flex justify-end space-x-3">
          <button type="button" id="cancel-btn" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
            <?php echo isset($selectedStudent) ? 'Update Student' : 'Add Student'; ?>
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div id="delete-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl p-6 max-w-md mx-4">
      <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Confirm Delete</h3>
      <p class="text-gray-700 dark:text-gray-300 mb-6">Are you sure you want to delete this student? This action cannot be undone.</p>
      
      <div class="flex justify-end space-x-3">
        <button id="cancel-delete" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
          Cancel
        </button>
        <a id="confirm-delete" href="#" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
          Delete
        </a>
      </div>
    </div>
  </div>
  
  <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
  </footer>
  
  <script>
    // Update current date and time
    function updateDateTime() {
      const now = new Date();
      const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      };
      const dateTimeString = now.toLocaleDateString('en-US', options).replace(',', ' -');
      document.getElementById('datetime-display').textContent = dateTimeString;
    }
    
    // Update time every minute
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Dropdown menu functionality
    const dropdownButton = document.getElementById('dropdown-button');
    const dropdownMenu = document.getElementById('dropdown-menu');
    const dropdownArrow = document.getElementById('dropdown-arrow');
    
    dropdownButton.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdownMenu.classList.toggle('show');
      // Rotate arrow when dropdown is open
      if (dropdownMenu.classList.contains('show')) {
        dropdownArrow.style.transform = 'rotate(180deg)';
      } else {
        dropdownArrow.style.transform = 'rotate(0)';
      }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
      if (dropdownMenu.classList.contains('show')) {
        dropdownMenu.classList.remove('show');
        dropdownArrow.style.transform = 'rotate(0)';
      }
    });
    
    // Prevent closing when clicking inside dropdown
    dropdownMenu.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    // Dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved theme preference or prefer-color-scheme
    if (localStorage.getItem('color-theme') === 'dark' || 
       (!localStorage.getItem('color-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    
    // Toggle dark/light mode
    themeToggle.addEventListener('click', () => {
      // Toggle class on document element
      document.documentElement.classList.toggle('dark');
      
      // Update localStorage
      if (document.documentElement.classList.contains('dark')) {
        localStorage.setItem('color-theme', 'dark');
      } else {
        localStorage.setItem('color-theme', 'light');
      }
    });
    
    // Modal functionality
    const studentModal = document.getElementById('student-modal');
    const deleteModal = document.getElementById('delete-modal');
    const addStudentBtn = document.getElementById('add-student-btn');
    const closeModalBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    const modalTitle = document.getElementById('modal-title');
    
    // Open add student modal
    addStudentBtn.addEventListener('click', () => {
      modalTitle.textContent = 'Add New Student';
      document.getElementById('student-form').reset();
      studentModal.classList.add('active');
    });
    
    // Close modal buttons
    closeModalBtn.addEventListener('click', () => {
      studentModal.classList.remove('active');
    });
    
    cancelBtn.addEventListener('click', () => {
      studentModal.classList.remove('active');
    });
    
    cancelDeleteBtn.addEventListener('click', () => {
      deleteModal.classList.remove('active');
    });
    
    // Close modals when clicking outside
    studentModal.addEventListener('click', (e) => {
      if (e.target === studentModal) {
        studentModal.classList.remove('active');
      }
    });
    
    deleteModal.addEventListener('click', (e) => {
      if (e.target === deleteModal) {
        deleteModal.classList.remove('active');
      }
    });
    
    // Show delete confirmation modal
    function confirmDelete(studentId) {
      deleteModal.classList.add('active');
      confirmDeleteBtn.href = `?delete=${studentId}`;
    }
    
    // Show student form modal if edit parameter is present
    <?php if (isset($_GET['edit'])): ?>
      document.addEventListener('DOMContentLoaded', () => {
        modalTitle.textContent = 'Edit Student';
        studentModal.classList.add('active');
      });
    <?php endif; ?>
  </script>