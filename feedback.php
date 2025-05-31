<?php
// Include database and session files
date_default_timezone_set("Asia/Kolkata");
require_once 'db.php';
require_once 'session.php';

// Ensure user is logged in
requireLogin();

// Function to get count from a table
function getTableCount($conn, $table) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'];
    } catch(PDOException $e) {
        return 0;
    }
}

// Get total feedback count
$feedbackCount = getTableCount($conn, 'Feedback');

// Get admin name from session
$adminId = $_SESSION['admin_id'];

// Function to get feedback with student names
function getFeedback($conn) {
    try {
        $sql = "SELECT f.feedback_id, f.student_id, CONCAT(s.first_name, ' ', s.last_name) as student_name, 
                f.feedback_text, f.feedback_date, f.type 
                FROM Feedback f 
                JOIN Student s ON f.student_id = s.student_id
                ORDER BY f.feedback_id ASC"; // Changed to order by ID number
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// Function to delete feedback
function deleteFeedback($conn, $feedbackId) {
    try {
        $stmt = $conn->prepare("DELETE FROM Feedback WHERE feedback_id = :feedback_id");
        $stmt->bindParam(':feedback_id', $feedbackId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch(PDOException $e) {
        return false;
    }
}

// Handle feedback deletion
$deleteSuccess = null;
$deleteError = null;

if (isset($_POST['delete_feedback']) && isset($_POST['feedback_id'])) {
    $feedbackId = $_POST['feedback_id'];
    if (deleteFeedback($conn, $feedbackId)) {
        $deleteSuccess = "Feedback #$feedbackId has been successfully deleted.";
    } else {
        $deleteError = "Failed to delete feedback #$feedbackId. Please try again.";
    }
}

// Get all feedback, ordered by ID
$feedback = getFeedback($conn);

// Handle logout action
if (isset($_GET['logout'])) {
    logout();
}

// Determine active page
$currentPage = 'feedback';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   School Management - Feedback
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
    /* Type badge styles */
    .type-badge {
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-align: center;
      min-width: 80px;
    }
    .type-academic {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .dark .type-academic {
      background-color: rgba(59, 130, 246, 0.2);
      color: #60a5fa;
    }
    .type-facilities {
      background-color: #fef3c7;
      color: #92400e;
    }
    .dark .type-facilities {
      background-color: rgba(245, 158, 11, 0.2);
      color: #fbbf24;
    }
    .type-teaching {
      background-color: #d1fae5;
      color: #065f46;
    }
    .dark .type-teaching {
      background-color: rgba(16, 185, 129, 0.2);
      color: #34d399;
    }
    .type-general {
      background-color: #f3f4f6;
      color: #4b5563;
    }
    .dark .type-general {
      background-color: rgba(75, 85, 99, 0.2);
      color: #9ca3af;
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
        <a href="feedback.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'feedback' ? 'active' : ''; ?>">
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
    <!-- Page header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-1">Student Feedback</h2>
        <p class="text-gray-600 dark:text-gray-400">View all student feedback</p>
      </div>
    </div>
    
    <!-- Notification for delete success/error -->
    <?php if ($deleteSuccess): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm dark:bg-green-900/30 dark:text-green-400 dark:border-green-700">
      <div class="flex items-center">
        <i class="fas fa-check-circle mr-2"></i>
        <p><?php echo htmlspecialchars($deleteSuccess); ?></p>
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($deleteError): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm dark:bg-red-900/30 dark:text-red-400 dark:border-red-700">
      <div class="flex items-center">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <p><?php echo htmlspecialchars($deleteError); ?></p>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Feedback table -->
    <div class="bg-white dark:bg-dark-card shadow-md rounded-lg overflow-hidden border border-gray-200 dark:border-dark-border">
      <!-- Feedback table header -->
      <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          All Feedback
        </h3>
      </div>
      
      <?php if (empty($feedback)): ?>
      <div class="p-6 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
          <i class="fas fa-comment-alt text-gray-500 dark:text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No feedback found</h3>
        <p class="text-gray-500 dark:text-gray-400">
          There is no feedback in the system yet.
        </p>
      </div>
      <?php else: ?>
      <!-- Feedback list -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Student</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Feedback</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-dark-border">
            <?php foreach ($feedback as $item): ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                <?php echo htmlspecialchars($item['feedback_id']); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                <?php echo htmlspecialchars($item['student_name']); ?> <span class="text-gray-500 dark:text-gray-400">(ID: <?php echo htmlspecialchars($item['student_id']); ?>)</span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">
                <?php echo htmlspecialchars($item['feedback_text']); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                <?php echo date('M d, Y', strtotime($item['feedback_date'])); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="type-badge <?php 
                  if ($item['type'] == 'Academic') echo 'type-academic';
                  else if ($item['type'] == 'Facilities') echo 'type-facilities';
                  else if ($item['type'] == 'Teaching') echo 'type-teaching';
                  else echo 'type-general';
                ?>">
                  <?php echo htmlspecialchars($item['type']); ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                  <input type="hidden" name="feedback_id" value="<?php echo htmlspecialchars($item['feedback_id']); ?>">
                  <button type="submit" name="delete_feedback" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 focus:outline-none transition-colors">
                    <i class="fas fa-trash-alt"></i> Delete
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
  
  <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
  </footer>
  
  <script>
    // Theme toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Check for saved theme
      if (localStorage.getItem('dark-mode') === 'true' || 
          (!('dark-mode' in localStorage) && 
           window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      }
      
      // Theme toggle button
      document.getElementById('theme-toggle').addEventListener('click', function() {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('dark-mode', document.documentElement.classList.contains('dark'));
      });
      
      // User dropdown
      const dropdownButton = document.getElementById('dropdown-button');
      const dropdownMenu = document.getElementById('dropdown-menu');
      const dropdownArrow = document.getElementById('dropdown-arrow');
      
      dropdownButton.addEventListener('click', function() {
        dropdownMenu.classList.toggle('show');
        dropdownArrow.style.transform = dropdownMenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
      });
      
      // Close dropdown when clicking outside
      window.addEventListener('click', function(event) {
        if (!event.target.closest('#dropdown-button') && !event.target.closest('#dropdown-menu')) {
          dropdownMenu.classList.remove('show');
          dropdownArrow.style.transform = 'rotate(0)';
        }
      });
      
      // Set active tab
      document.querySelectorAll('.nav-tab').forEach(tab => {
        if (tab.getAttribute('href') === 'feedback.php') {
          tab.classList.add('active');
        }
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
      updateDateTime();
      setInterval(updateDateTime, 60000);
    });
  </script>
 </body>
</html>