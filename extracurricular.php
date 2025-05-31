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

// Initialize message variables
$successMsg = "";
$errorMsg = "";

// Handle Add New Extracurricular Activity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_activity'])) {
    $activity_name = trim($_POST['activity_name']);
    $description = trim($_POST['description']);
    
    // Validate inputs
    if (empty($activity_name)) {
        $errorMsg = "Activity name is required";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO Extracurricular_Activity (activity_name, description) VALUES (?, ?)");
            $stmt->execute([$activity_name, $description]);
            $successMsg = "Extracurricular activity added successfully!";
        } catch(PDOException $e) {
            $errorMsg = "Error adding activity: " . $e->getMessage();
        }
    }
}

// Handle Delete Extracurricular Activity
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $activity_id = $_GET['delete'];
    
    try {
        // First delete from junction table to avoid foreign key constraint issues
        $stmt = $conn->prepare("DELETE FROM Student_Extracurricular WHERE activity_id = ?");
        $stmt->execute([$activity_id]);
        
        // Then delete the activity
        $stmt = $conn->prepare("DELETE FROM Extracurricular_Activity WHERE activity_id = ?");
        $stmt->execute([$activity_id]);
        
        $successMsg = "Activity deleted successfully!";
    } catch(PDOException $e) {
        $errorMsg = "Error deleting activity: " . $e->getMessage();
    }
}

// Handle Edit Extracurricular Activity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_activity'])) {
    $activity_id = $_POST['activity_id'];
    $activity_name = trim($_POST['activity_name']);
    $description = trim($_POST['description']);
    
    // Validate inputs
    if (empty($activity_name)) {
        $errorMsg = "Activity name is required";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE Extracurricular_Activity SET activity_name = ?, description = ? WHERE activity_id = ?");
            $stmt->execute([$activity_name, $description, $activity_id]);
            $successMsg = "Activity updated successfully!";
        } catch(PDOException $e) {
            $errorMsg = "Error updating activity: " . $e->getMessage();
        }
    }
}

// Get activity details if editing
$editActivityData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM Extracurricular_Activity WHERE activity_id = ?");
        $stmt->execute([$_GET['edit']]);
        $editActivityData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $errorMsg = "Error fetching activity details: " . $e->getMessage();
    }
}

// Fetch all extracurricular activities
try {
    $stmt = $conn->prepare("SELECT * FROM Extracurricular_Activity ORDER BY activity_name");
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMsg = "Error fetching activities: " . $e->getMessage();
    $activities = [];
}

// Get student count for each activity
function getActivityStudentCount($conn, $activity_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Student_Extracurricular WHERE activity_id = ?");
        $stmt->execute([$activity_id]);
        $result = $stmt->fetch();
        return $result['count'];
    } catch(PDOException $e) {
        return 0;
    }
}

// Determine active page
$currentPage = 'extracurricular';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   Extracurricular Activities Management
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
    /* Modal animations */
    .modal {
      transition: opacity 0.3s ease-in-out;
      opacity: 0;
      visibility: hidden;
    }
    .modal.show {
      opacity: 1;
      visibility: visible;
    }
    .modal-content {
      transition: transform 0.3s ease-in-out;
      transform: scale(0.9);
    }
    .modal.show .modal-content {
      transform: scale(1);
    }
    /* Table hover effects */
    .table-row-hover:hover {
      background-color: rgba(59, 130, 246, 0.05);
      transform: translateY(-1px);
      transition: all 0.2s ease;
    }
    .dark .table-row-hover:hover {
      background-color: rgba(96, 165, 250, 0.05);
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
        <a href="teachers.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-chalkboard-teacher w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Teachers</span>
        </a>
        <a href="classes.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-school w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Classes</span>
        </a>
        <a href="extracurricular.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'extracurricular' ? 'active' : ''; ?>">
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
    <!-- Page Title and Add Button -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
        <i class="fas fa-running mr-2 text-purple-500 dark:text-purple-400"></i>Extracurricular Activities
      </h2>
      <button id="add-activity-btn" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-md shadow transition-colors duration-200 flex items-center">
        <i class="fas fa-plus mr-2"></i>Add New Activity
      </button>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($successMsg)): ?>
    <div id="success-alert" class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md dark:bg-green-900/30 dark:text-green-300 flex justify-between items-center">
      <div><i class="fas fa-check-circle mr-2"></i><?php echo $successMsg; ?></div>
      <button onclick="this.parentElement.style.display='none'" class="text-green-500 hover:text-green-700 dark:text-green-300 dark:hover:text-green-100">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($errorMsg)): ?>
    <div id="error-alert" class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md dark:bg-red-900/30 dark:text-red-300 flex justify-between items-center">
      <div><i class="fas fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?></div>
      <button onclick="this.parentElement.style.display='none'" class="text-red-500 hover:text-red-700 dark:text-red-300 dark:hover:text-red-100">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <?php endif; ?>
    
    <!-- Extracurricular Activities Table -->
    <div class="bg-white dark:bg-dark-card shadow-md rounded-lg overflow-hidden border border-gray-200 dark:border-dark-border">
      <!-- Table Header -->
      <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-medium text-gray-800 dark:text-white">All Activities</h3>
        <div class="flex items-center">
          <input type="text" id="search-input" placeholder="Search activities..." class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
      </div>
      
      <!-- Table Content -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Activity Name</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Students Enrolled</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-dark-border" id="activities-table-body">
            <?php if (empty($activities)): ?>
              <tr>
                <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No extracurricular activities found</td>
              </tr>
            <?php else: ?>
              <?php foreach ($activities as $activity): ?>
                <tr class="table-row-hover">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($activity['activity_id']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($activity['activity_name']); ?></td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($activity['description']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200"><?php echo getActivityStudentCount($conn, $activity['activity_id']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center space-x-3">
                      <a href="?edit=<?php echo $activity['activity_id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="#" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 delete-btn" data-id="<?php echo $activity['activity_id']; ?>" data-name="<?php echo htmlspecialchars($activity['activity_name']); ?>">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Table Footer with Pagination -->
      <div class="bg-gray-50 dark:bg-gray-800 px-6 py-3 border-t border-gray-200 dark:border-dark-border flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Showing <span id="item-count"><?php echo count($activities); ?></span> activities
        </div>
        <!-- Pagination would go here if needed -->
      </div>
    </div>
  </main>
  
  <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
  </footer>
  
  <!-- Add/Edit Activity Modal -->
  <div id="activity-modal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-lg w-full max-w-md mx-4">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 id="modal-title" class="text-xl font-semibold text-gray-800 dark:text-white">Add New Activity</h3>
          <button id="close-modal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form id="activity-form" method="POST" action="">
          <input type="hidden" id="activity_id" name="activity_id" value="<?php echo isset($editActivityData) ? $editActivityData['activity_id'] : ''; ?>">
          <div class="mb-4">
            <label for="activity_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Name</label>
            <input type="text" id="activity_name" name="activity_name" value="<?php echo isset($editActivityData) ? htmlspecialchars($editActivityData['activity_name']) : ''; ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" required>
          </div>
          <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <textarea id="description" name="description" rows="4" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"><?php echo isset($editActivityData) ? htmlspecialchars($editActivityData['description']) : ''; ?></textarea>
          </div>
          <div class="flex justify-end">
            <button type="button" id="cancel-btn" class="bg-gray-300 hover:bg-gray-400 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium py-2 px-4 rounded-md mr-2">Cancel</button>
            <button type="submit" name="<?php echo isset($editActivityData) ? 'edit_activity' : 'add_activity'; ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-md">
              <?php echo isset($editActivityData) ? 'Update Activity' : 'Add Activity'; ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div id="delete-modal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-lg w-full max-w-md mx-4">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Confirm Delete</h3>
          <button id="close-delete-modal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <p class="text-gray-600 dark:text-gray-300 mb-6">Are you sure you want to delete "<span id="delete-activity-name"></span>"? This action cannot be undone.</p>
        <div class="flex justify-end">
          <button id="cancel-delete" class="bg-gray-300 hover:bg-gray-400 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium py-2 px-4 rounded-md mr-2">Cancel</button>
          <a id="confirm-delete" href="#" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md">Delete</a>
        </div>
      </div>
    </div>
  </div>
  
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
      dropdownArrow.classList.toggle('rotate-180');
    });
    
    // Close dropdown when clicking elsewhere
    document.addEventListener('click', () => {
      dropdownMenu.classList.remove('show');
      dropdownArrow.classList.remove('rotate-180');
    });
    
    // Dark mode toggle functionality
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved theme preference or use preferred color scheme
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
    
    // Toggle theme
    themeToggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      
      // Save preference
      if (document.documentElement.classList.contains('dark')) {
        localStorage.setItem('theme', 'dark');
      } else {
        localStorage.setItem('theme', 'light');
      }
    });
    
    // Modal functionality
    const activityModal = document.getElementById('activity-modal');
    const deleteModal = document.getElementById('delete-modal');
    const addActivityBtn = document.getElementById('add-activity-btn');
    const closeModalBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-btn');
    const closeDeleteModalBtn = document.getElementById('close-delete-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    
    // Show add activity modal
    addActivityBtn.addEventListener('click', () => {
      document.getElementById('modal-title').textContent = 'Add New Activity';
      document.getElementById('activity-form').reset();
      document.getElementById('activity_id').value = '';
      
      // Update submit button text
      const submitBtn = document.querySelector('#activity-form button[type="submit"]');
      submitBtn.textContent = 'Add Activity';
      submitBtn.name = 'add_activity';
      
      activityModal.classList.add('show');
    });
    
    // Close activity modal
    [closeModalBtn, cancelBtn].forEach(btn => {
      btn.addEventListener('click', () => {
        activityModal.classList.remove('show');
      });
    });
    
    // Close delete modal
    [closeDeleteModalBtn, cancelDeleteBtn].forEach(btn => {
      btn.addEventListener('click', () => {
        deleteModal.classList.remove('show');
      });
    });
    
    // Setup delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        
        document.getElementById('delete-activity-name').textContent = name;
        document.getElementById('confirm-delete').href = `?delete=${id}`;
        deleteModal.classList.add('show');
      });
    });
    
    // Show edit modal if edit parameter exists
    <?php if (isset($editActivityData)): ?>
    window.addEventListener('DOMContentLoaded', () => {
      document.getElementById('modal-title').textContent = 'Edit Activity';
      activityModal.classList.add('show');
    });
    <?php endif; ?>
    
    // Search functionality
    const searchInput = document.getElementById('search-input');
    const activitiesTable = document.getElementById('activities-table-body');
    const itemCount = document.getElementById('item-count');
    
    searchInput.addEventListener('input', () => {
      const searchTerm = searchInput.value.toLowerCase();
      const rows = activitiesTable.querySelectorAll('tr');
      let visibleCount = 0;
      
      rows.forEach(row => {
        if (row.textContent.toLowerCase().includes(searchTerm)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      itemCount.textContent = visibleCount;
    });
    
    // Auto-hide alerts after 5 seconds
    const successAlert = document.getElementById('success-alert');
    const errorAlert = document.getElementById('error-alert');
    
    if (successAlert) {
      setTimeout(() => {
        successAlert.style.display = 'none';
      }, 5000);
    }
    
    if (errorAlert) {
      setTimeout(() => {
        errorAlert.style.display = 'none';
      }, 5000);
    }
  </script>
 </body>
</html>