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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new transport
    if (isset($_POST['add_transport'])) {
        $busNumber = $_POST['bus_number'];
        $route = $_POST['route'];
        $capacity = $_POST['capacity'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO Transport (bus_number, route, capacity) VALUES (?, ?, ?)");
            $stmt->execute([$busNumber, $route, $capacity]);
            $messages[] = ["success", "New transport route added successfully."];
        } catch(PDOException $e) {
            $messages[] = ["error", "Error adding transport: " . $e->getMessage()];
        }
    }
    
    // Assign student to transport
    if (isset($_POST['assign_student'])) {
        $studentId = $_POST['student_id'];
        $transportId = $_POST['transport_id'];
        
        try {
            // First check if the assignment already exists
            $check = $conn->prepare("SELECT * FROM Student_Transport WHERE student_id = ? AND transport_id = ?");
            $check->execute([$studentId, $transportId]);
            
            if ($check->rowCount() > 0) {
                $messages[] = ["error", "Student is already assigned to this transport route."];
            } else {
                // Check if bus is at capacity
                $capacityCheck = $conn->prepare("
                    SELECT t.capacity, COUNT(st.student_id) as current_students 
                    FROM Transport t 
                    LEFT JOIN Student_Transport st ON t.transport_id = st.transport_id 
                    WHERE t.transport_id = ? 
                    GROUP BY t.transport_id");
                $capacityCheck->execute([$transportId]);
                $busInfo = $capacityCheck->fetch();
                
                if ($busInfo && $busInfo['current_students'] >= $busInfo['capacity']) {
                    $messages[] = ["error", "This bus is already at full capacity."];
                } else {
                    $stmt = $conn->prepare("INSERT INTO Student_Transport (student_id, transport_id) VALUES (?, ?)");
                    $stmt->execute([$studentId, $transportId]);
                    $messages[] = ["success", "Student assigned to transport successfully."];
                }
            }
        } catch(PDOException $e) {
            $messages[] = ["error", "Error assigning student: " . $e->getMessage()];
        }
    }
    
    // Remove student from transport
    if (isset($_POST['remove_assignment'])) {
        $studentId = $_POST['student_id'];
        $transportId = $_POST['transport_id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM Student_Transport WHERE student_id = ? AND transport_id = ?");
            $stmt->execute([$studentId, $transportId]);
            $messages[] = ["success", "Student removed from transport route."];
        } catch(PDOException $e) {
            $messages[] = ["error", "Error removing assignment: " . $e->getMessage()];
        }
    }
    
    // Delete transport
    if (isset($_POST['delete_transport'])) {
        $transportId = $_POST['transport_id'];
        
        try {
            // First delete all assignments
            $stmt = $conn->prepare("DELETE FROM Student_Transport WHERE transport_id = ?");
            $stmt->execute([$transportId]);
            
            // Then delete the transport
            $stmt = $conn->prepare("DELETE FROM Transport WHERE transport_id = ?");
            $stmt->execute([$transportId]);
            
            $messages[] = ["success", "Transport route deleted successfully."];
        } catch(PDOException $e) {
            $messages[] = ["error", "Error deleting transport: " . $e->getMessage()];
        }
    }
}

// Fetch all transport routes
try {
    $stmt = $conn->prepare("
        SELECT t.*, COUNT(st.student_id) as student_count 
        FROM Transport t 
        LEFT JOIN Student_Transport st ON t.transport_id = st.transport_id 
        GROUP BY t.transport_id 
        ORDER BY t.bus_number");
    $stmt->execute();
    $transports = $stmt->fetchAll();
} catch(PDOException $e) {
    $messages[] = ["error", "Error fetching transports: " . $e->getMessage()];
    $transports = [];
}

// Fetch all students for dropdown
try {
    $stmt = $conn->prepare("SELECT student_id, CONCAT(first_name, ' ', last_name) as name FROM Student ORDER BY name");
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch(PDOException $e) {
    $messages[] = ["error", "Error fetching students: " . $e->getMessage()];
    $students = [];
}

// Fetch students with transport assignments
try {
    $stmt = $conn->prepare("
        SELECT st.*, s.first_name, s.last_name, t.bus_number, t.route
        FROM Student_Transport st
        JOIN Student s ON st.student_id = s.student_id
        JOIN Transport t ON st.transport_id = t.transport_id
        ORDER BY t.bus_number, s.last_name, s.first_name");
    $stmt->execute();
    $assignments = $stmt->fetchAll();
} catch(PDOException $e) {
    $messages[] = ["error", "Error fetching assignments: " . $e->getMessage()];
    $assignments = [];
}

// Determine active page
$currentPage = 'transport';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>Transport Management - School System</title>
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
    /* Tab menu */
    .tab-menu {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
    }
    .tab-menu::-webkit-scrollbar {
      display: none;
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
        <a href="transport.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 active">
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
    <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-6">
      Transport Management
    </h2>
    
    <!-- Alerts -->
    <?php foreach ($messages as $message): ?>
      <div class="mb-4 p-4 rounded-md <?php echo $message[0] === 'success' ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400'; ?>">
        <?php echo htmlspecialchars($message[1]); ?>
      </div>
    <?php endforeach; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Add New Transport -->
      <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 border border-gray-200 dark:border-dark-border md:col-span-1">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Add New Transport Route</h3>
        <form method="post" class="space-y-4">
          <div>
            <label for="bus_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bus Number</label>
            <input type="text" id="bus_number" name="bus_number" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" placeholder="e.g. BUS-001">
          </div>
          <div>
            <label for="route" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Route</label>
            <input type="text" id="route" name="route" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" placeholder="e.g. North Campus - Downtown">
          </div>
          <div>
            <label for="capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Capacity</label>
            <input type="number" id="capacity" name="capacity" required min="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" placeholder="e.g. 45">
          </div>
          <button type="submit" name="add_transport" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
            <i class="fas fa-plus mr-2"></i> Add Transport
          </button>
        </form>
      </div>
      
      <!-- Assign Student to Transport -->
      <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-5 border border-gray-200 dark:border-dark-border md:col-span-2">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Assign Student to Transport</h3>
        <form method="post" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="student_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Student</label>
              <select id="student_id" name="student_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:text-white">
                <option value="">Select Student</option>
                <?php foreach ($students as $student): ?>
                  <option value="<?php echo $student['student_id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="transport_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Transport Route</label>
              <select id="transport_id" name="transport_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:text-white">
                <option value="">Select Transport</option>
                <?php foreach ($transports as $transport): ?>
                  <option value="<?php echo $transport['transport_id']; ?>">
                    Bus <?php echo htmlspecialchars($transport['bus_number']); ?> - 
                    <?php echo htmlspecialchars($transport['route']); ?> 
                    (<?php echo $transport['student_count']; ?>/<?php echo $transport['capacity']; ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" name="assign_student" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800">
            <i class="fas fa-user-plus mr-2"></i> Assign Student
          </button>
        </form>
      </div>
    </div>
    
    <!-- Transport List -->
    <div class="mt-8 bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 overflow-hidden border border-gray-200 dark:border-dark-border">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Transport Routes</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bus Number</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Capacity</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Students</th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (empty($transports)): ?>
              <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No transport routes found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($transports as $transport): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo $transport['transport_id']; ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($transport['bus_number']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($transport['route']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo $transport['capacity']; ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo $transport['student_count']; ?> / <?php echo $transport['capacity']; ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <form method="post" class="inline-block">
                      <input type="hidden" name="transport_id" value="<?php echo $transport['transport_id']; ?>">
                      <button type="submit" name="delete_transport" onclick="return confirm('Are you sure you want to delete this transport route? This will remove all student assignments.')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Student Assignments -->
    <div class="mt-8 bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 overflow-hidden border border-gray-200 dark:border-dark-border">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Student Transport Assignments</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Student</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bus Number</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Route</th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (empty($assignments)): ?>
              <tr>
                <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No student assignments found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($assignments as $assignment): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                    <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo htmlspecialchars($assignment['bus_number']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo htmlspecialchars($assignment['route']); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <form method="post" class="inline-block">
                      <input type="hidden" name="student_id" value="<?php echo $assignment['student_id']; ?>">
                      <input type="hidden" name="transport_id" value="<?php echo $assignment['transport_id']; ?>">
                      <button type="submit" name="remove_assignment" onclick="return confirm('Are you sure you want to remove this student from the transport route?')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                        <i class="fas fa-user-minus"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
  
  <!-- Footer -->
  <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
  </footer>
  <script>
    // Date and time update
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
    
    // Update date/time every minute
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved theme preference or use system preference
    if (localStorage.getItem('dark-mode') === 'true' || 
        (!localStorage.getItem('dark-mode') && 
         window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
    
    // Toggle theme
    themeToggle.addEventListener('click', () => {
      if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('dark-mode', 'false');
      } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('dark-mode', 'true');
      }
    });
    
    // Dropdown menu
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
    
    // Highlight active tab
    document.addEventListener('DOMContentLoaded', () => {
      const currentPage = '<?php echo $currentPage; ?>';
      document.querySelectorAll('.nav-tab').forEach(tab => {
        const href = tab.getAttribute('href');
        const pageName = href.split('.')[0].replace('/', '');
        
        if (pageName === currentPage) {
          tab.classList.add('active');
        }
      });
    });
  </script>
</body>
</html>