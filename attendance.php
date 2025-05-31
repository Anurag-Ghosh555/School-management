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

// Get admin name from session
$adminId = $_SESSION['admin_id'];

// Handle logout action
if (isset($_GET['logout'])) {
    logout();
}

// Process attendance form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_attendance') {
        try {
            // Begin transaction to ensure all updates happen or none
            $conn->beginTransaction();
            
            foreach ($_POST['attendance'] as $attendanceId => $attended) {
                $stmt = $conn->prepare("UPDATE Attendance SET attended = ? WHERE attendance_id = ?");
                $stmt->execute([$attended, $attendanceId]);
            }
            
            $conn->commit();
            $successMessage = "Attendance records updated successfully!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $errorMessage = "Error updating attendance records: " . $e->getMessage();
        }
    } else if (isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
        try {
            $studentId = $_POST['student_id'];
            $attended = $_POST['attended'];
            $totalClasses = $_POST['total_classes'];
            
            $stmt = $conn->prepare("INSERT INTO Attendance (student_id, attended, total_classes) VALUES (?, ?, ?)");
            $stmt->execute([$studentId, $attended, $totalClasses]);
            
            $successMessage = "New attendance record added successfully!";
        } catch (PDOException $e) {
            $errorMessage = "Error adding attendance record: " . $e->getMessage();
        }
    }
}

// Get attendance records with student names
$query = "SELECT a.attendance_id, a.student_id, CONCAT(s.first_name, ' ', s.last_name) AS student_name, a.attended, a.total_classes 
          FROM Attendance a 
          JOIN Student s ON a.student_id = s.student_id 
          ORDER BY s.first_name, s.last_name";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Error retrieving attendance records: " . $e->getMessage();
    $attendanceRecords = [];
}

// Get all students for the new attendance form
$query = "SELECT student_id, name FROM Student ORDER BY name";
try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
}

// Determine active page
$currentPage = 'attendance';
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   Attendance Management
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

    /* Progress bar styling */
    .progress-bar {
      height: 8px;
      border-radius: 4px;
      overflow: hidden;
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
        <a href="feedback.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
          <i class="fas fa-comment-alt w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Feedback</span>
        </a>
        <a href="attendance.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'attendance' ? 'active' : ''; ?>">
          <i class="fas fa-clipboard-check w-5 h-5 mr-2 text-gray-500 dark:text-gray-400"></i>
          <span>Attendance</span>
        </a>
      </div>
    </div>
  </header>
    
  <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Page title -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
        Student Attendance
      </h2>
      <button id="add-attendance-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center space-x-2 transition-colors duration-200">
        <i class="fas fa-plus"></i>
        <span>Add Attendance</span>
      </button>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($successMessage)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded dark:bg-green-900/30 dark:text-green-400" role="alert">
      <p><?php echo $successMessage; ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded dark:bg-red-900/30 dark:text-red-400" role="alert">
      <p><?php echo $errorMessage; ?></p>
    </div>
    <?php endif; ?>

    <!-- Add Attendance Form (Hidden by default) -->
    <div id="add-attendance-form" class="hidden bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-6 mb-6 border border-gray-200 dark:border-dark-border">
      <h3 class="text-lg font-medium text-gray-800 dark:text-white mb-4">Add New Attendance Record</h3>
      <form method="POST" action="attendance.php">
        <input type="hidden" name="action" value="mark_attendance">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label for="student_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Student</label>
            <select id="student_id" name="student_id" required class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Select Student</option>
              <?php foreach ($students as $student): ?>
              <option value="<?php echo $student['student_id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="attended" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Classes Attended</label>
            <input type="number" id="attended" name="attended" min="0" required class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label for="total_classes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total Classes</label>
            <input type="number" id="total_classes" name="total_classes" min="1" required class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
        <div class="mt-4 flex justify-end space-x-3">
          <button type="button" id="cancel-add" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
            Save Attendance
          </button>
        </div>
      </form>
    </div>

    <!-- Attendance Records Table -->
    <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 overflow-hidden border border-gray-200 dark:border-dark-border">
      <div class="overflow-x-auto">
        <form method="POST" action="attendance.php">
          <input type="hidden" name="action" value="update_attendance">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Student
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Attendance
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Classes Attended
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Total Classes
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Percentage
                </th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-gray-700">
              <?php if (empty($attendanceRecords)): ?>
              <tr>
                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No attendance records found. Add a new record to get started.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($attendanceRecords as $record): ?>
                  <?php 
                    $percentage = ($record['total_classes'] > 0) ? ($record['attended'] / $record['total_classes']) * 100 : 0;
                    $percentageFormatted = number_format($percentage, 1);
                    
                    // Determine color based on percentage
                    if ($percentage >= 85) {
                      $colorClass = "bg-green-500";
                      $statusClass = "text-green-500 dark:text-green-400";
                      $statusText = "Excellent";
                    } elseif ($percentage >= 75) {
                      $colorClass = "bg-blue-500";
                      $statusClass = "text-blue-500 dark:text-blue-400";
                      $statusText = "Good";
                    } elseif ($percentage >= 60) {
                      $colorClass = "bg-yellow-500";
                      $statusClass = "text-yellow-500 dark:text-yellow-400";
                      $statusText = "Average";
                    } else {
                      $colorClass = "bg-red-500";
                      $statusClass = "text-red-500 dark:text-red-400";
                      $statusText = "Poor";
                    }
                  ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                          <?php echo htmlspecialchars($record['student_name']); ?>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?> bg-opacity-10 dark:bg-opacity-20">
                        <?php echo $statusText; ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <input type="number" name="attendance[<?php echo $record['attendance_id']; ?>]" value="<?php echo $record['attended']; ?>" min="0" max="<?php echo $record['total_classes']; ?>" class="w-20 text-sm border border-gray-300 dark:border-gray-600 p-1 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                      <?php echo $record['total_classes']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="w-full max-w-xs">
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                          <?php echo $percentageFormatted; ?>%
                        </div>
                        <div class="progress-bar bg-gray-200 dark:bg-gray-700">
                          <div class="<?php echo $colorClass; ?>" style="width: <?php echo $percentage; ?>%;height:100%"></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <a href="#" class="edit-attendance text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 mr-3" data-id="<?php echo $record['attendance_id']; ?>">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="#" class="delete-attendance text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" data-id="<?php echo $record['attendance_id']; ?>">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
          <?php if (!empty($attendanceRecords)): ?>
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-dark-border flex justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
              Save Changes
            </button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Attendance Summary -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Attendance Overview Card -->
      <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-6 border border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-medium text-gray-800 dark:text-white mb-4">Attendance Overview</h3>
        <div class="space-y-4">
          <?php
            // Calculate attendance statistics
            $totalStudents = count($attendanceRecords);
            $excellentAttendance = 0;
            $goodAttendance = 0;
            $averageAttendance = 0;
            $poorAttendance = 0;
            
            foreach ($attendanceRecords as $record) {
              $percentage = ($record['total_classes'] > 0) ? ($record['attended'] / $record['total_classes']) * 100 : 0;
              
              if ($percentage >= 85) {
                $excellentAttendance++;
              } elseif ($percentage >= 75) {
                $goodAttendance++;
              } elseif ($percentage >= 60) {
                $averageAttendance++;
              } else {
                $poorAttendance++;
              }
            }
            
            // Calculate percentages for overview
            $excellentPercentage = ($totalStudents > 0) ? ($excellentAttendance / $totalStudents) * 100 : 0;
            $goodPercentage = ($totalStudents > 0) ? ($goodAttendance / $totalStudents) * 100 : 0;
            $averagePercentage = ($totalStudents > 0) ? ($averageAttendance / $totalStudents) * 100 : 0;
            $poorPercentage = ($totalStudents > 0) ? ($poorAttendance / $totalStudents) * 100 : 0;
          ?>
          <div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-sm font-medium text-green-600 dark:text-green-400">Excellent (≥85%)</span>
              <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo $excellentAttendance; ?> students</span>
            </div>
            <div class="progress-bar bg-gray-200 dark:bg-gray-700">
              <div class="bg-green-500" style="width: <?php echo $excellentPercentage; ?>%;height:100%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-sm font-medium text-blue-600 dark:text-blue-400">Good (75-84%)</span>
              <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo $goodAttendance; ?> students</span>
            </div>
            <div class="progress-bar bg-gray-200 dark:bg-gray-700">
              <div class="bg-blue-500" style="width: <?php echo $goodPercentage; ?>%;height:100%"></div>
            </div>
          </div>
          <div>
          <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Average (60-74%)</span>
              <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo $averageAttendance; ?> students</span>
            </div>
            <div class="progress-bar bg-gray-200 dark:bg-gray-700">
              <div class="bg-yellow-500" style="width: <?php echo $averagePercentage; ?>%;height:100%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-sm font-medium text-red-600 dark:text-red-400">Poor (<60%)</span>
              <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo $poorAttendance; ?> students</span>
            </div>
            <div class="progress-bar bg-gray-200 dark:bg-gray-700">
              <div class="bg-red-500" style="width: <?php echo $poorPercentage; ?>%;height:100%"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendance Tips Card -->
      <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-6 border border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-medium text-gray-800 dark:text-white mb-4">Attendance Tips</h3>
        <ul class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
          <li class="flex items-start">
            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
            <span>Regularly update attendance records to maintain accurate data.</span>
          </li>
          <li class="flex items-start">
            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
            <span>Contact parents of students with attendance below 75% to address concerns.</span>
          </li>
          <li class="flex items-start">
            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
            <span>Consider implementing attendance incentives for students with excellent records.</span>
          </li>
          <li class="flex items-start">
            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
            <span>For chronically absent students, develop individualized attendance improvement plans.</span>
          </li>
          <li class="flex items-start">
            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
            <span>Generate monthly attendance reports to track trends and patterns.</span>
          </li>
        </ul>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
  </footer>

  <!-- JavaScript -->
  <script>
    // Toggle dropdown
    const dropdownButton = document.getElementById('dropdown-button');
    const dropdownMenu = document.getElementById('dropdown-menu');
    const dropdownArrow = document.getElementById('dropdown-arrow');
    
    dropdownButton.addEventListener('click', () => {
      dropdownMenu.classList.toggle('show');
      dropdownArrow.classList.toggle('transform');
      dropdownArrow.classList.toggle('rotate-180');
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', (e) => {
      if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove('show');
        dropdownArrow.classList.remove('rotate-180');
      }
    });
    
    // Theme toggle
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved theme preference or use system preference
    if (localStorage.getItem('theme') === 'dark' || 
        (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    
    // Toggle theme
    themeToggle.addEventListener('click', () => {
      if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
      }
    });
    
    // Update datetime
    function updateDateTime() {
      const now = new Date();
      const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true
      };
      const formattedDate = now.toLocaleDateString('en-US', options);
      document.getElementById('datetime-display').textContent = formattedDate;
    }
    
    // Update time every minute
    setInterval(updateDateTime, 60000);
    
    // Add attendance form toggle
    const addAttendanceBtn = document.getElementById('add-attendance-btn');
    const addAttendanceForm = document.getElementById('add-attendance-form');
    const cancelAddBtn = document.getElementById('cancel-add');
    
    addAttendanceBtn.addEventListener('click', () => {
      addAttendanceForm.classList.remove('hidden');
    });
    
    cancelAddBtn.addEventListener('click', () => {
      addAttendanceForm.classList.add('hidden');
    });
    
    // Edit and delete attendance event handlers
    document.querySelectorAll('.edit-attendance').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        // Implement edit functionality
        alert('Edit attendance record with ID: ' + id);
      });
    });
    
    document.querySelectorAll('.delete-attendance').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        if (confirm('Are you sure you want to delete this attendance record?')) {
          // Implement delete functionality
          window.location.href = 'attendance.php?delete=' + id;
        }
      });
    });
  </script>
</body>
</html>