<?php
// Include essential files
date_default_timezone_set("Asia/Kolkata");
require_once 'db.php';
require_once 'session.php';

// Basic session control
requireLogin();
$adminId = $_SESSION['admin_id'];
if (isset($_GET['logout'])) logout();
$currentPage = 'teachers';

// Initialize messages
$successMsg = $errorMsg = '';

// Handle delete teacher
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM Teacher WHERE teacher_id = ?");
        $stmt->execute([$_GET['delete']]);
        $successMsg = "Teacher successfully deleted.";
    } catch(PDOException $e) {
        $errorMsg = "Error deleting teacher: " . $e->getMessage();
    }
}

// Handle form submissions (add/update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get common form data
    $teacherId = isset($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $dateOfBirth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $contactNumber = $_POST['contact_number'];
    $email = $_POST['email'];
    $hireDate = $_POST['hire_date'];
    
    try {
        // Update existing teacher
        if (isset($_POST['update_teacher']) && $teacherId) {
            $stmt = $conn->prepare("UPDATE Teacher SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, contact_number = ?, email = ?, hire_date = ? WHERE teacher_id = ?");
            $stmt->execute([$firstName, $lastName, $dateOfBirth, $gender, $contactNumber, $email, $hireDate, $teacherId]);
            $successMsg = "Teacher updated successfully.";
        } 
        // Add new teacher
        elseif (isset($_POST['add_teacher'])) {
            $stmt = $conn->prepare("INSERT INTO Teacher (first_name, last_name, date_of_birth, gender, contact_number, email, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $dateOfBirth, $gender, $contactNumber, $email, $hireDate]);
            $successMsg = "Teacher added successfully.";
        }
    } catch(PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Get teacher to edit
$editTeacher = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM Teacher WHERE teacher_id = ?");
        $stmt->execute([$_GET['edit']]);
        $editTeacher = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $errorMsg = "Error fetching teacher data: " . $e->getMessage();
    }
}

// Setup pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch teachers
try {
    // Handle search if present
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $stmt = $conn->prepare("SELECT * FROM Teacher WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? ORDER BY last_name, first_name");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = 1; // Disable pagination for search
    } else {
        // Regular pagination
        $countStmt = $conn->query("SELECT COUNT(*) FROM Teacher");
        $totalTeachers = $countStmt->fetchColumn();
        $totalPages = ceil($totalTeachers / $limit);
        
        $stmt = $conn->prepare("SELECT * FROM Teacher ORDER BY last_name, first_name LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $errorMsg = "Error fetching teachers: " . $e->getMessage();
    $teachers = [];
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>Teacher Management</title>
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
    /* Arrow link animation */
    .arrow-link {
      position: relative;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.3s ease;
    }
    .arrow-link:hover {
      transform: translateX(5px);
      background-color: rgba(0, 0, 0, 0.05);
    }
    .dark .arrow-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    .arrow-link:hover i {
      transform: scale(1)
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
        <a href="students.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-user-graduate w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Students</span>
        </a>
        <a href="teachers.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'teachers' ? 'active' : ''; ?>">
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
    <!-- Page Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 md:mb-0">
        <i class="fas fa-chalkboard-teacher mr-2 text-green-500"></i>Teacher Management
      </h1>
      
      <!-- Search & Add -->
      <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
        <form action="teachers.php" method="GET" class="flex">
          <input type="text" name="search" placeholder="Search teachers..." class="border rounded-l-md px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
          <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 rounded-r-md">
            <i class="fas fa-search"></i>
          </button>
        </form>
        <button id="add-teacher-btn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">
          <i class="fas fa-plus mr-2"></i>Add Teacher
        </button>
      </div>
    </div>
    
    <!-- Alerts -->
    <?php if ($successMsg): ?>
      <div id="success-alert" class="alert mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">
        <div class="flex">
          <i class="fas fa-check-circle"></i>
          <p class="ml-3"><?= $successMsg ?></p>
          <button class="ml-auto close-alert"><i class="fas fa-times"></i></button>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if ($errorMsg): ?>
      <div id="error-alert" class="alert mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded">
        <div class="flex">
          <i class="fas fa-exclamation-circle"></i>
          <p class="ml-3"><?= $errorMsg ?></p>
          <button class="ml-auto close-alert"><i class="fas fa-times"></i></button>
        </div>
      </div>
    <?php endif; ?>
    
    <!-- Add/Edit Form -->
    <div id="teacher-form-container" class="mb-6 bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-6 border border-gray-200 dark:border-dark-border <?= $editTeacher ? '' : 'hidden' ?>">
      <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
        <?= $editTeacher ? 'Edit Teacher' : 'Add New Teacher' ?>
      </h2>
      <form action="teachers.php" method="POST">
        <?php if ($editTeacher): ?>
          <input type="hidden" name="teacher_id" value="<?= $editTeacher['teacher_id'] ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Form fields -->
          <div>
            <label for="first_name" class="block text-gray-700 dark:text-gray-300 mb-1">First Name</label>
            <input type="text" id="first_name" name="first_name" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $editTeacher ? htmlspecialchars($editTeacher['first_name']) : '' ?>">
          </div>
          
          <div>
            <label for="last_name" class="block text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
            <input type="text" id="last_name" name="last_name" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $editTeacher ? htmlspecialchars($editTeacher['last_name']) : '' ?>">
          </div>
          
          <div>
            <label for="date_of_birth" class="block text-gray-700 dark:text-gray-300 mb-1">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $editTeacher ? htmlspecialchars($editTeacher['date_of_birth']) : '' ?>">
          </div>
          
          <div>
            <label for="gender" class="block text-gray-700 dark:text-gray-300 mb-1">Gender</label>
            <select id="gender" name="gender" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
              <option value="">Select Gender</option>
              <option value="Male" <?= ($editTeacher && $editTeacher['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($editTeacher && $editTeacher['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($editTeacher && $editTeacher['gender'] == 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          
          <div>
            <label for="contact_number" class="block text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
            <input type="tel" id="contact_number" name="contact_number" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $editTeacher ? htmlspecialchars($editTeacher['contact_number']) : '' ?>">
          </div>
          
          <div>
            <label for="email" class="block text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input type="email" id="email" name="email" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $editTeacher ? htmlspecialchars($editTeacher['email']) : '' ?>">
          </div>
          
          <div>
            <label for="hire_date" class="block text-gray-700 dark:text-gray-300 mb-1">Hire Date</label>
            <input type="date" id="hire_date" name="hire_date" required class="w-full rounded-md border px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $editTeacher ? htmlspecialchars($editTeacher['hire_date']) : '' ?>">
          </div>
          
          <!-- Form buttons -->
          <div class="md:col-span-2 flex justify-end space-x-3 mt-4">
            <button type="button" id="cancel-form" class="bg-gray-300 hover:bg-gray-400 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white px-4 py-2 rounded-md">
              Cancel
            </button>
            <?php if ($editTeacher): ?>
              <button type="submit" name="update_teacher" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                Update Teacher
              </button>
            <?php else: ?>
              <button type="submit" name="add_teacher" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md">
                Add Teacher
              </button>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
    
    <!-- Teachers Table -->
    <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 overflow-hidden border border-gray-200 dark:border-dark-border">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Gender</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Contact</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Hire Date</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (count($teachers) > 0): ?>
              <?php foreach ($teachers as $teacher): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                    <?= htmlspecialchars($teacher['teacher_id']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                    <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                    <?= htmlspecialchars($teacher['gender']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                    <?= htmlspecialchars($teacher['contact_number']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                    <?= htmlspecialchars($teacher['email']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                    <?= date('M d, Y', strtotime($teacher['hire_date'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="?edit=<?= $teacher['teacher_id'] ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                      <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $teacher['teacher_id'] ?>" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" onclick="return confirm('Are you sure you want to delete this teacher?')">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No teachers found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1 && !isset($_GET['search'])): ?>
      <div class="mt-4 flex justify-center">
        <div class="flex space-x-2">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="px-3 py-1 rounded <?= $page == $i ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>
  
  <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
  </footer>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Theme toggle functionality
      const themeToggle = document.getElementById('theme-toggle');
      
      // Function to update theme
      function updateTheme(isDark) {
        if (isDark) {
          document.documentElement.classList.add('dark');
          localStorage.theme = 'dark';
        } else {
          document.documentElement.classList.remove('dark');
          localStorage.theme = 'light';
        }
      }
      
      // Check for saved theme preference
      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        updateTheme(true);
      } else {
        updateTheme(false);
      }
      
      // Toggle theme when button is clicked
      themeToggle.addEventListener('click', () => {
        const isDarkMode = document.documentElement.classList.contains('dark');
        updateTheme(!isDarkMode);
      });
      
      // Dropdown menu
      const dropdownButton = document.getElementById('dropdown-button');
      const dropdownMenu = document.getElementById('dropdown-menu');
      const dropdownArrow = document.getElementById('dropdown-arrow');
      
      dropdownButton.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
        dropdownArrow.classList.toggle('transform');
        dropdownArrow.classList.toggle('rotate-180');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (dropdownMenu.classList.contains('show') && !dropdownButton.contains(e.target)) {
          dropdownMenu.classList.remove('show');
          dropdownArrow.classList.remove('transform', 'rotate-180');
        }
      });
      
      // Add teacher button
      const addTeacherBtn = document.getElementById('add-teacher-btn');
      const teacherFormContainer = document.getElementById('teacher-form-container');
      const cancelFormBtn = document.getElementById('cancel-form');
      
      addTeacherBtn.addEventListener('click', () => {
        teacherFormContainer.classList.remove('hidden');
      });
      
      cancelFormBtn.addEventListener('click', () => {
        teacherFormContainer.classList.add('hidden');
        // Reset form if needed
        const form = teacherFormContainer.querySelector('form');
        if (form && !form.querySelector('input[name="teacher_id"]')) {
          form.reset();
        }
      });
      
      // Close alerts
      const closeAlerts = document.querySelectorAll('.close-alert');
      closeAlerts.forEach(btn => {
        btn.addEventListener('click', () => {
          btn.closest('.alert').remove();
        });
      });
      
      // Auto-hide alerts after 5 seconds
      setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
          alert.style.transition = 'opacity 1s';
          alert.style.opacity = '0';
          setTimeout(() => {
            alert.remove();
          }, 1000);
        });
      }, 5000);
      
      // Update date and time
      function updateDateTime() {
        const dateTimeElement = document.getElementById('datetime-display');
        if (dateTimeElement) {
          const now = new Date();
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
          };
          dateTimeElement.textContent = now.toLocaleDateString('en-US', options);
        }
      }
      
      // Update time every minute
      updateDateTime();
      setInterval(updateDateTime, 60000);
      
      // Highlight current page in navbar
      const currentPage = '<?php echo $currentPage; ?>';
      const navTabs = document.querySelectorAll('.nav-tab');
      
      navTabs.forEach(tab => {
        const href = tab.getAttribute('href');
        if (href.includes(currentPage)) {
          tab.classList.add('active');
        }
      });
    });
  </script>
</body>
</html>