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

// Initialize messages
$successMsg = '';
$errorMsg = '';

// Handle add/edit/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new class
    if (isset($_POST['add_class'])) {
        $className = trim($_POST['class_name']);
        $section = trim($_POST['section']);
        $year = trim($_POST['year']);
        
        if (empty($className) || empty($section) || empty($year)) {
            $errorMsg = "All fields are required!";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO Class (class_name, section, year) VALUES (?, ?, ?)");
                $stmt->execute([$className, $section, $year]);
                $successMsg = "Class added successfully!";
            } catch(PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
    
    // Update existing class
    if (isset($_POST['update_class'])) {
        $classId = $_POST['class_id'];
        $className = trim($_POST['class_name']);
        $section = trim($_POST['section']);
        $year = trim($_POST['year']);
        
        if (empty($className) || empty($section) || empty($year)) {
            $errorMsg = "All fields are required!";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE Class SET class_name = ?, section = ?, year = ? WHERE class_id = ?");
                $stmt->execute([$className, $section, $year, $classId]);
                $successMsg = "Class updated successfully!";
            } catch(PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }
    
    // Delete class
    if (isset($_POST['delete_class'])) {
        $classId = $_POST['class_id'];
        
        try {
            // First check if there are any students in this class
            $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM Student WHERE class_id = ?");
            $checkStmt->execute([$classId]);
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                $errorMsg = "Cannot delete class. There are " . $result['count'] . " students assigned to this class.";
            } else {
                $stmt = $conn->prepare("DELETE FROM Class WHERE class_id = ?");
                $stmt->execute([$classId]);
                $successMsg = "Class deleted successfully!";
            }
        } catch(PDOException $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// Fetch all classes with student count
try {
    $stmt = $conn->prepare("
        SELECT c.*, COUNT(s.student_id) as student_count 
        FROM Class c
        LEFT JOIN Student s ON c.class_id = s.class_id
        GROUP BY c.class_id
        ORDER BY c.year DESC, c.class_name ASC, c.section ASC
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch(PDOException $e) {
    $errorMsg = "Error fetching classes: " . $e->getMessage();
    $classes = [];
}

// Get class by ID for editing
function getClassById($conn, $classId) {
    $stmt = $conn->prepare("SELECT * FROM Class WHERE class_id = ?");
    $stmt->execute([$classId]);
    return $stmt->fetch();
}

// Determine active page
$currentPage = 'classes';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   Classes Management
  </title>
  <script src="https://cdn.tailwindcss.com">
  </script>
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;display=swap" rel="stylesheet"/>
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

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 50;
      overflow: auto;
    }
    
    .modal-content {
      position: relative;
      margin: 5% auto;
      width: 90%;
      max-width: 500px;
      animation-name: modalopen;
      animation-duration: 0.4s;
    }
    
    @keyframes modalopen {
      from {opacity: 0; transform: translateY(-60px);}
      to {opacity: 1; transform: translateY(0);}
    }

    /* Table hover effects */
    .data-row {
      transition: all 0.2s ease;
    }
    .data-row:hover {
      background-color: rgba(59, 130, 246, 0.1);
    }
    .dark .data-row:hover {
      background-color: rgba(96, 165, 250, 0.1);
    }
    
    /* Action buttons */
    .action-btn {
      transition: all 0.2s ease;
    }
    .action-btn:hover {
      transform: scale(1.1);
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
        <a href="teachers.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-chalkboard-teacher w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Teachers</span>
        </a>
        <a href="classes.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'classes' ? 'active' : ''; ?>">
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
    <!-- Page Header with Add Button -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
        <i class="fas fa-school mr-2 text-yellow-500 dark:text-yellow-400"></i>Classes Management
      </h2>
      <button id="add-class-btn" class="flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-md transition-colors duration-200 shadow-sm">
        <i class="fas fa-plus mr-2"></i>
        <span>Add New Class</span>
      </button>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($successMsg)): ?>
      <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded dark:bg-green-900/30 dark:text-green-400 dark:border-green-500" role="alert">
        <p><?php echo $successMsg; ?></p>
      </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
      <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded dark:bg-red-900/30 dark:text-red-400 dark:border-red-500" role="alert">
        <p><?php echo $errorMsg; ?></p>
      </div>
    <?php endif; ?>

    <!-- Classes Table -->
    <div class="bg-white dark:bg-dark-card shadow-md rounded-lg overflow-hidden border border-gray-200 dark:border-dark-border">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                ID
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Class Name
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Section
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Year
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Students
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-dark-border">
            <?php if (empty($classes)): ?>
              <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                  No classes found. Add a new class to get started.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($classes as $class): ?>
                <tr class="data-row">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo htmlspecialchars($class['class_id']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                    <?php echo htmlspecialchars($class['class_name']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo htmlspecialchars($class['section']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo htmlspecialchars($class['year']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                      <?php echo htmlspecialchars($class['student_count']); ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button class="edit-class-btn action-btn text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-4" 
                            data-id="<?php echo htmlspecialchars($class['class_id']); ?>"
                            data-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                            data-section="<?php echo htmlspecialchars($class['section']); ?>"
                            data-year="<?php echo htmlspecialchars($class['year']); ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="delete-class-btn action-btn text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" 
                            data-id="<?php echo htmlspecialchars($class['class_id']); ?>"
                            data-name="<?php echo htmlspecialchars($class['class_name']); ?>" 
                            data-students="<?php echo htmlspecialchars($class['student_count']); ?>">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Add Class Modal -->
  <div id="add-class-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Class</h3>
          <button class="close-modal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form action="" method="post">
          <div class="mb-4">
            <label for="class_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class Name</label>
            <input type="text" id="class_name" name="class_name" required 
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 dark:bg-gray-700 dark:text-white"
                   placeholder="Enter class name">
          </div>
          <div class="mb-4">
            <label for="section" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Section</label>
            <input type="text" id="section" name="section" required 
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 dark:bg-gray-700 dark:text-white"
                   placeholder="Enter section (e.g. A, B, C)">
          </div>
          <div class="mb-6">
            <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
            <input type="number" id="year" name="year" required 
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 dark:bg-gray-700 dark:text-white"
                   placeholder="Enter year (e.g. 2025)" min="2000" max="2100" value="<?php echo date('Y'); ?>">
          </div>
          <div class="flex justify-end">
            <button type="button" class="close-modal bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-md mr-2">
              Cancel
            </button>
            <button type="submit" name="add_class" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md">
              Add Class
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Class Modal -->
  <div id="edit-class-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Class</h3>
          <button class="close-modal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form action="" method="post">
          <input type="hidden" id="edit_class_id" name="class_id">
          <div class="mb-4">
            <label for="edit_class_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class Name</label>
            <input type="text" id="edit_class_name" name="class_name" required 
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 dark:bg-gray-700 dark:text-white"
                   placeholder="Enter class name">
          </div>
          <div class="mb-4">
            <label for="edit_section" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Section</label>
            <input type="text" id="edit_section" name="section" required 
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 dark:bg-gray-700 dark:text-white"
                   placeholder="Enter section (e.g. A, B, C)">
          </div>
          <div class="mb-6">
            <label for="edit_year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
            <input type="number" id="edit_year" name="year" required 
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 dark:bg-gray-700 dark:text-white"
                   placeholder="Enter year (e.g. 2025)" min="2000" max="2100">
          </div>
          <div class="flex justify-end">
            <button type="button" class="close-modal bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-md mr-2">
              Cancel
            </button>
            <button type="submit" name="update_class" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-md">
              Update Class
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<!-- Delete Class Modal -->
<div id="delete-class-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Class</h3>
          <button class="close-modal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="mb-6">
          <p class="text-gray-700 dark:text-gray-300">Are you sure you want to delete the class <span id="delete-class-name" class="font-medium"></span>?</p>
          <p id="delete-warning" class="text-red-600 dark:text-red-400 mt-2 hidden">This class has <span id="delete-students-count"></span> students. You must reassign or remove all students first.</p>
        </div>
        <form action="" method="post">
          <input type="hidden" id="delete_class_id" name="class_id">
          <div class="flex justify-end">
            <button type="button" class="close-modal bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-md mr-2">
              Cancel
            </button>
            <button id="confirm-delete-btn" type="submit" name="delete_class" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md">
              Delete Class
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Footer -->
  <footer class="bg-white dark:bg-dark-card shadow-inner border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-center items-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          © 2025 Anurag&Anusha. All rights reserved.
        </p>
      </div>
    </div>
  </footer>

  <script>
    // Theme toggle functionality
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved theme preference or default to light theme
    if (localStorage.getItem('theme') === 'dark' || 
        (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    
    // Toggle theme on button click
    themeToggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });
    
    // Dropdown functionality
    const dropdownButton = document.getElementById('dropdown-button');
    const dropdownMenu = document.getElementById('dropdown-menu');
    const dropdownArrow = document.getElementById('dropdown-arrow');
    
    dropdownButton.addEventListener('click', function() {
      dropdownMenu.classList.toggle('show');
      dropdownArrow.classList.toggle('transform');
      dropdownArrow.classList.toggle('rotate-180');
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(e) {
      if (!dropdownButton.contains(e.target)) {
        dropdownMenu.classList.remove('show');
        dropdownArrow.classList.remove('rotate-180');
      }
    });
    
    // Modal functionality
    const addClassBtn = document.getElementById('add-class-btn');
    const addClassModal = document.getElementById('add-class-modal');
    const editClassModal = document.getElementById('edit-class-modal');
    const deleteClassModal = document.getElementById('delete-class-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    
    addClassBtn.addEventListener('click', function() {
      addClassModal.style.display = 'block';
    });
    
    closeModalBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        addClassModal.style.display = 'none';
        editClassModal.style.display = 'none';
        deleteClassModal.style.display = 'none';
      });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target === addClassModal) {
        addClassModal.style.display = 'none';
      }
      if (e.target === editClassModal) {
        editClassModal.style.display = 'none';
      }
      if (e.target === deleteClassModal) {
        deleteClassModal.style.display = 'none';
      }
    });
    
    // Edit class button functionality
    const editBtns = document.querySelectorAll('.edit-class-btn');
    
    editBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        const classId = this.getAttribute('data-id');
        const className = this.getAttribute('data-name');
        const section = this.getAttribute('data-section');
        const year = this.getAttribute('data-year');
        
        document.getElementById('edit_class_id').value = classId;
        document.getElementById('edit_class_name').value = className;
        document.getElementById('edit_section').value = section;
        document.getElementById('edit_year').value = year;
        
        editClassModal.style.display = 'block';
      });
    });
    
    // Delete class button functionality
    const deleteBtns = document.querySelectorAll('.delete-class-btn');
    const deleteClassName = document.getElementById('delete-class-name');
    const deleteWarning = document.getElementById('delete-warning');
    const deleteStudentsCount = document.getElementById('delete-students-count');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    
    deleteBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        const classId = this.getAttribute('data-id');
        const className = this.getAttribute('data-name');
        const studentCount = parseInt(this.getAttribute('data-students'));
        
        document.getElementById('delete_class_id').value = classId;
        deleteClassName.textContent = className;
        
        // Show warning and disable delete button if class has students
        if (studentCount > 0) {
          deleteWarning.classList.remove('hidden');
          deleteStudentsCount.textContent = studentCount;
          confirmDeleteBtn.disabled = true;
          confirmDeleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
          deleteWarning.classList.add('hidden');
          confirmDeleteBtn.disabled = false;
          confirmDeleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        
        deleteClassModal.style.display = 'block';
      });
    });
    
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
      document.getElementById('datetime-display').textContent = now.toLocaleDateString('en-US', options);
    }
    // Update time every minute
    setInterval(updateDateTime, 60000);
  </script>
 </body>
</html>