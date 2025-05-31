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

// Get counts for dashboard stats
$studentCount = getTableCount($conn, 'Student');
$teacherCount = getTableCount($conn, 'Teacher');
$classCount = getTableCount($conn, 'Class');
$extracurricularCount = getTableCount($conn, 'Extracurricular_Activity');
$eventCount = getTableCount($conn, 'Event');
$transportCount = getTableCount($conn, 'Transport');
$complaintCount = getTableCount($conn, 'Complaint');
$feedbackCount = getTableCount($conn, 'Feedback');

// Get admin name from session
$adminId = $_SESSION['admin_id'];

// Handle logout action
if (isset($_GET['logout'])) {
    logout();
}

// Determine active page
$currentPage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   School Management Dashboard
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
      
      <!-- Navigation Tabs - Removed border-b -->
      <div class="tab-menu flex mt-2">
        <a href="dashboard.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'dashboard' ? 'active' : ''; ?>">
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
    <!-- Welcome and stats -->
    <section class="mb-8">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">
        Welcome back, <?php echo htmlspecialchars($adminId); ?>!
      </h2>
      <!-- Dashboard stats cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-user-graduate fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Students</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $studentCount; ?></p>
          </div>
          <a href="students.php" class="ml-auto text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-chalkboard-teacher fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Teachers</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $teacherCount; ?></p>
          </div>
          <a href="teachers.php" class="ml-auto text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-school fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Classes</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $classCount; ?></p>
          </div>
          <a href="classes.php" class="ml-auto text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-running fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Extracurricular</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $extracurricularCount; ?></p>
          </div>
          <a href="extracurricular.php" class="ml-auto text-purple-500 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-calendar-alt fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Events</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $eventCount; ?></p>
          </div>
          <a href="events.php" class="ml-auto text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-bus fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Transport</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $transportCount; ?></p>
          </div>
          <a href="transport.php" class="ml-auto text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-exclamation-circle fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Complaints</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $complaintCount; ?></p>
          </div>
          <a href="complaints.php" class="ml-auto text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-comment-alt fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Feedback</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $feedbackCount; ?></p>
          </div>
          <a href="feedback.php" class="ml-auto text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 flex items-center space-x-4 border border-gray-200 dark:border-dark-border">
          <div class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 p-3 rounded-full flex items-center justify-center">
            <i class="fas fa-clipboard-check fa-2x"></i>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Attendance</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white">System</p>
          </div>
          <a href="attendance.php" class="ml-auto text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-300 arrow-link">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>
    </section>
  </main>
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
    
    // Add mobile-friendly scroll for tab navigation
    const tabMenu = document.querySelector('.tab-menu');
    let isScrolling = false;
    let startX;
    let scrollLeft;
    
    tabMenu.addEventListener('mousedown', (e) => {
      isScrolling = true;
      startX = e.pageX - tabMenu.offsetLeft;
      scrollLeft = tabMenu.scrollLeft;
    });
    
    tabMenu.addEventListener('mouseleave', () => {
      isScrolling = false;
    });
    
    tabMenu.addEventListener('mouseup', () => {
      isScrolling = false;
    });
    
    tabMenu.addEventListener('mousemove', (e) => {
      if (!isScrolling) return;
      e.preventDefault();
      const x = e.pageX - tabMenu.offsetLeft;
      const walk = (x - startX) * 2;
      tabMenu.scrollLeft = scrollLeft - walk;
    });
  </script>
 </body>
</html>