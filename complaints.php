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

// Get total complaint count
$complaintCount = getTableCount($conn, 'Complaint');

// Get admin name from session
$adminId = $_SESSION['admin_id'];

// Function to get complaints with student names
function getComplaints($conn, $status = null, $limit = null) {
    try {
        $sql = "SELECT c.complaint_id, c.student_id, CONCAT(s.first_name, ' ', s.last_name) as student_name, 
                c.complaint_text, c.complaint_date, c.status 
                FROM Complaint c 
                JOIN Student s ON c.student_id = s.student_id";
        
        if ($status !== null) {
            $sql .= " WHERE c.status = :status";
        }
        
        $sql .= " ORDER BY c.complaint_date DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $conn->prepare($sql);
        
        if ($status !== null) {
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        }
        
        if ($limit !== null) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// Handle complaint actions
if (isset($_POST['action'])) {
    // Process status update
    if ($_POST['action'] === 'update_status' && isset($_POST['complaint_id']) && isset($_POST['status'])) {
        $complaint_id = $_POST['complaint_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE Complaint SET status = :status WHERE complaint_id = :complaint_id");
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':complaint_id', $complaint_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Redirect to prevent form resubmission
            header("Location: complaints.php?status_updated=1");
            exit();
        } catch(PDOException $e) {
            $errorMessage = "Error updating complaint status: " . $e->getMessage();
        }
    }
    
    // Process complaint deletion
    if ($_POST['action'] === 'delete' && isset($_POST['complaint_id'])) {
        $complaint_id = $_POST['complaint_id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM Complaint WHERE complaint_id = :complaint_id");
            $stmt->bindParam(':complaint_id', $complaint_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Redirect to prevent form resubmission
            header("Location: complaints.php?deleted=1");
            exit();
        } catch(PDOException $e) {
            $errorMessage = "Error deleting complaint: " . $e->getMessage();
        }
    }
}

// Get filtered complaints
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$complaints = getComplaints($conn, $statusFilter);

// Count complaints by status
function getComplaintCountByStatus($conn, $status) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Complaint WHERE status = :status");
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'];
    } catch(PDOException $e) {
        return 0;
    }
}

$pendingCount = getComplaintCountByStatus($conn, 'Pending');
$inProgressCount = getComplaintCountByStatus($conn, 'In Progress');
$resolvedCount = getComplaintCountByStatus($conn, 'Resolved');
$closedCount = getComplaintCountByStatus($conn, 'Closed');

// Handle logout action
if (isset($_GET['logout'])) {
    logout();
}

// Determine active page
$currentPage = 'complaints';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   School Management - Complaints
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
    
    /* Status badge styles */
    .status-badge {
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-align: center;
      min-width: 80px;
    }
    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    .dark .status-pending {
      background-color: rgba(245, 158, 11, 0.2);
      color: #fbbf24;
    }
    .status-in-progress {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .dark .status-in-progress {
      background-color: rgba(59, 130, 246, 0.2);
      color: #60a5fa;
    }
    .status-resolved {
      background-color: #d1fae5;
      color: #065f46;
    }
    .dark .status-resolved {
      background-color: rgba(16, 185, 129, 0.2);
      color: #34d399;
    }
    .status-closed {
      background-color: #f3f4f6;
      color: #4b5563;
    }
    .dark .status-closed {
      background-color: rgba(75, 85, 99, 0.2);
      color: #9ca3af;
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
      margin: 10% auto;
      width: 90%;
      max-width: 500px;
      animation: modalFade 0.3s ease-out;
    }
    @keyframes modalFade {
      from {
        opacity: 0;
        transform: translateY(-30px);
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
        <a href="complaints.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'complaints' ? 'active' : ''; ?>">
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
    <!-- Page header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-1">Complaints Management</h2>
        <p class="text-gray-600 dark:text-gray-400">View and manage student complaints</p>
      </div>
      
      <!-- Status filters on desktop -->
      <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
        <a href="complaints.php" class="px-3 py-1 rounded-full text-sm <?php echo !isset($_GET['status']) ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
          All (<?php echo $complaintCount; ?>)
        </a>
        <a href="complaints.php?status=Pending" class="px-3 py-1 rounded-full text-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
          Pending (<?php echo $pendingCount; ?>)
        </a>
        <a href="complaints.php?status=In Progress" class="px-3 py-1 rounded-full text-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'In Progress' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
          In Progress (<?php echo $inProgressCount; ?>)
        </a>
        <a href="complaints.php?status=Resolved" class="px-3 py-1 rounded-full text-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'Resolved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
          Resolved (<?php echo $resolvedCount; ?>)
        </a>
        <a href="complaints.php?status=Closed" class="px-3 py-1 rounded-full text-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'Closed' ? 'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
          Closed (<?php echo $closedCount; ?>)
        </a>
      </div>
    </div>
    
    <?php if (isset($_GET['status_updated'])): ?>
    <div id="status-alert" class="bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 p-4 rounded-md mb-6 flex items-center justify-between">
      <div class="flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <span>Complaint status updated successfully!</span>
      </div>
      <button onclick="document.getElementById('status-alert').style.display='none'" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
    <div id="deleted-alert" class="bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400 p-4 rounded-md mb-6 flex items-center justify-between">
      <div class="flex items-center">
        <i class="fas fa-trash mr-3"></i>
        <span>Complaint deleted successfully!</span>
      </div>
      <button onclick="document.getElementById('deleted-alert').style.display='none'" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <?php endif; ?>
    
    <!-- Complaints table -->
    <div class="bg-white dark:bg-dark-card shadow-md rounded-lg overflow-hidden border border-gray-200 dark:border-dark-border">
      <!-- Complaints table header -->
      <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          <?php
          if (isset($_GET['status'])) {
              echo htmlspecialchars($_GET['status']) . " Complaints";
          } else {
              echo "All Complaints";
          }
          ?>
        </h3>
      </div>
      
      <?php if (empty($complaints)): ?>
      <div class="p-6 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
          <i class="fas fa-clipboard-list text-gray-500 dark:text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No complaints found</h3>
        <p class="text-gray-500 dark:text-gray-400">
          <?php 
          if (isset($_GET['status'])) {
              echo "There are no " . strtolower(htmlspecialchars($_GET['status'])) . " complaints at the moment.";
          } else {
              echo "There are no complaints in the system yet.";
          }
          ?>
        </p>
      </div>
      <?php else: ?>
      <!-- Complaints list -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Student</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Complaint</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-dark-border">
            <?php foreach ($complaints as $complaint): ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                <?php echo htmlspecialchars($complaint['complaint_id']); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                <?php echo htmlspecialchars($complaint['student_name']); ?> <span class="text-gray-500 dark:text-gray-400">(ID: <?php echo htmlspecialchars($complaint['student_id']); ?>)</span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">
                <?php echo htmlspecialchars($complaint['complaint_text']); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                <?php echo date('M d, Y', strtotime($complaint['complaint_date'])); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="status-badge <?php 
                  if ($complaint['status'] == 'Pending') echo 'status-pending';
                  else if ($complaint['status'] == 'In Progress') echo 'status-in-progress';
                  else if ($complaint['status'] == 'Resolved') echo 'status-resolved';
                  else echo 'status-closed';
                ?>">
                  <?php echo htmlspecialchars($complaint['status']); ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button onclick="viewComplaint(<?php echo $complaint['complaint_id']; ?>, '<?php echo addslashes(htmlspecialchars($complaint['student_name'])); ?>', '<?php echo addslashes(htmlspecialchars($complaint['complaint_text'])); ?>', '<?php echo date('M d, Y', strtotime($complaint['complaint_date'])); ?>', '<?php echo $complaint['status']; ?>')" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mr-3">
                  <i class="fas fa-eye"></i>
                </button>
                <button onclick="updateStatus(<?php echo $complaint['complaint_id']; ?>, '<?php echo $complaint['status']; ?>')" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 mr-3">
                  <i class="fas fa-edit"></i>
                </button>
                <button onclick="confirmDelete(<?php echo $complaint['complaint_id']; ?>)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">
                  <i class="fas fa-trash"></i>
                </button>
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
<!-- View Complaint Modal -->
<div id="view-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white" id="modal-title">View Complaint</h3>
        <button onclick="closeModal('view-modal')" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="px-6 py-4">
        <div class="mb-4">
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Student</p>
          <p class="text-base text-gray-900 dark:text-white font-medium" id="view-student"></p>
        </div>
        <div class="mb-4">
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Date</p>
          <p class="text-base text-gray-900 dark:text-white" id="view-date"></p>
        </div>
        <div class="mb-4">
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
          <p id="view-status"></p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Complaint Text</p>
          <p class="text-base text-gray-900 dark:text-white whitespace-pre-wrap" id="view-text"></p>
        </div>
      </div>
      <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 flex justify-end">
        <button onclick="closeModal('view-modal')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Close</button>
      </div>
    </div>
  </div>
  
  <!-- Update Status Modal -->
  <div id="status-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Update Complaint Status</h3>
        <button onclick="closeModal('status-modal')" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="status-form" method="post" action="">
        <div class="px-6 py-4">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" id="complaint-id" name="complaint_id">
          <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
          <select id="status-select" name="status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
            <option value="Closed">Closed</option>
          </select>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 flex justify-end space-x-3">
          <button type="button" onclick="closeModal('status-modal')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Update Status</button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div id="delete-modal" class="modal">
    <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Confirm Deletion</h3>
        <button onclick="closeModal('delete-modal')" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="px-6 py-4">
        <div class="flex items-center">
          <div class="bg-red-100 dark:bg-red-900/30 rounded-full p-3 mr-4">
            <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400"></i>
          </div>
          <p class="text-gray-700 dark:text-gray-300">Are you sure you want to delete this complaint? This action cannot be undone.</p>
        </div>
      </div>
      <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 flex justify-end space-x-3">
        <button onclick="closeModal('delete-modal')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cancel</button>
        <form id="delete-form" method="post" action="">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" id="delete-id" name="complaint_id">
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">Delete</button>
        </form>
      </div>
    </div>
  </div>
  
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
        if (tab.getAttribute('href') === 'complaints.php') {
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
    
    // Modal functions
    function openModal(modalId) {
      document.getElementById(modalId).style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
      document.body.style.overflow = '';
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
      const modals = document.getElementsByClassName('modal');
      for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
          closeModal(modals[i].id);
        }
      }
    }
    
    // Complaint actions
    function viewComplaint(id, student, text, date, status) {
      document.getElementById('view-student').textContent = student;
      document.getElementById('view-text').textContent = text;
      document.getElementById('view-date').textContent = date;
      
      // Set status with appropriate styling
      const statusElement = document.getElementById('view-status');
      statusElement.textContent = status;
      statusElement.className = 'status-badge inline-block';
      
      if (status === 'Pending') {
        statusElement.classList.add('status-pending');
      } else if (status === 'In Progress') {
        statusElement.classList.add('status-in-progress');
      } else if (status === 'Resolved') {
        statusElement.classList.add('status-resolved');
      } else {
        statusElement.classList.add('status-closed');
      }
      
      openModal('view-modal');
    }
    
    function updateStatus(id, currentStatus) {
      document.getElementById('complaint-id').value = id;
      document.getElementById('status-select').value = currentStatus;
      openModal('status-modal');
    }
    
    function confirmDelete(id) {
      document.getElementById('delete-id').value = id;
      openModal('delete-modal');
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
      const statusAlert = document.getElementById('status-alert');
      const deletedAlert = document.getElementById('deleted-alert');
      
      if (statusAlert) {
        statusAlert.style.display = 'none';
      }
      
      if (deletedAlert) {
        deletedAlert.style.display = 'none';
      }
    }, 5000);
  </script>
 </body>
</html>