<?php
// Include database and session files
date_default_timezone_set("Asia/Kolkata");
require_once 'db.php';
require_once 'session.php';

// Ensure user is logged in
requireLogin();

// Handle logout action
if (isset($_GET['logout'])) {
    logout();
}

// Determine active page
$currentPage = 'events';

// Get admin name from session
$adminId = $_SESSION['admin_id'];

// Function to get all events
function getAllEvents($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT e.*, 
                   (SELECT COUNT(*) FROM Student_Event WHERE event_id = e.event_id) as participant_count
            FROM Event e 
            ORDER BY e.event_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>Database error: ' . $e->getMessage() . '</p>
              </div>';
        return [];
    }
}

// Function to get event by ID
function getEventById($conn, $eventId) {
    try {
        $stmt = $conn->prepare("SELECT * FROM Event WHERE event_id = :event_id");
        $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

// Function to get all students for an event
function getEventStudents($conn, $eventId) {
    try {
        $stmt = $conn->prepare("
            SELECT s.* 
            FROM Student s
            JOIN Student_Event se ON s.student_id = se.student_id
            WHERE se.event_id = :event_id
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// Function to get all students not in an event
function getStudentsNotInEvent($conn, $eventId) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM Student 
            WHERE student_id NOT IN (
                SELECT student_id FROM Student_Event WHERE event_id = :event_id
            )
            ORDER BY last_name, first_name
        ");
        $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// Function to add student to event
function addStudentToEvent($conn, $studentId, $eventId) {
    try {
        // Check if record already exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM Student_Event WHERE student_id = :student_id AND event_id = :event_id");
        $checkStmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $checkStmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("INSERT INTO Student_Event (student_id, event_id) VALUES (:student_id, :event_id)");
            $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
            return $stmt->execute();
        }
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Function to remove student from event
function removeStudentFromEvent($conn, $studentId, $eventId) {
    try {
        $stmt = $conn->prepare("DELETE FROM Student_Event WHERE student_id = :student_id AND event_id = :event_id");
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch(PDOException $e) {
        return false;
    }
}

// Process form submissions
$successMessage = '';
$errorMessage = '';

// Add new event
if (isset($_POST['add_event'])) {
    $eventName = trim($_POST['event_name']);
    $eventDate = $_POST['event_date'];
    $description = trim($_POST['description']);
    
    if (empty($eventName) || empty($eventDate)) {
        $errorMessage = "Event name and date are required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO Event (event_name, event_date, description) VALUES (:event_name, :event_date, :description)");
            $stmt->bindParam(':event_name', $eventName, PDO::PARAM_STR);
            $stmt->bindParam(':event_date', $eventDate, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $successMessage = "Event added successfully!";
            } else {
                $errorMessage = "Failed to add event.";
            }
        } catch(PDOException $e) {
            $errorMessage = "Database error: " . $e->getMessage();
        }
    }
}

// Update existing event
if (isset($_POST['update_event'])) {
    $eventId = $_POST['event_id'];
    $eventName = trim($_POST['event_name']);
    $eventDate = $_POST['event_date'];
    $description = trim($_POST['description']);
    
    if (empty($eventName) || empty($eventDate)) {
        $errorMessage = "Event name and date are required.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE Event 
                SET event_name = :event_name, 
                    event_date = :event_date, 
                    description = :description 
                WHERE event_id = :event_id
            ");
            $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindParam(':event_name', $eventName, PDO::PARAM_STR);
            $stmt->bindParam(':event_date', $eventDate, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $successMessage = "Event updated successfully!";
            } else {
                $errorMessage = "Failed to update event.";
            }
        } catch(PDOException $e) {
            $errorMessage = "Database error: " . $e->getMessage();
        }
    }
}

// Delete event
if (isset($_POST['delete_event'])) {
    $eventId = $_POST['event_id'];
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Delete from junction table first
        $stmt1 = $conn->prepare("DELETE FROM Student_Event WHERE event_id = :event_id");
        $stmt1->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmt1->execute();
        
        // Then delete the event
        $stmt2 = $conn->prepare("DELETE FROM Event WHERE event_id = :event_id");
        $stmt2->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmt2->execute();
        
        // Commit transaction
        $conn->commit();
        
        $successMessage = "Event deleted successfully!";
    } catch(PDOException $e) {
        // Roll back on error
        $conn->rollBack();
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// Add student to event
if (isset($_POST['add_student_to_event'])) {
    $eventId = $_POST['event_id'];
    $studentId = $_POST['student_id'];
    
    if (addStudentToEvent($conn, $studentId, $eventId)) {
        $successMessage = "Student added to event successfully!";
    } else {
        $errorMessage = "Failed to add student to event.";
    }
}

// Remove student from event
if (isset($_POST['remove_student_from_event'])) {
    $eventId = $_POST['event_id'];
    $studentId = $_POST['student_id'];
    
    if (removeStudentFromEvent($conn, $studentId, $eventId)) {
        $successMessage = "Student removed from event successfully!";
    } else {
        $errorMessage = "Failed to remove student from event.";
    }
}

// Get events for display
$events = getAllEvents($conn);

// Get event details if viewing specific event
$selectedEvent = null;
$eventStudents = [];
$availableStudents = [];

if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $selectedEvent = getEventById($conn, $_GET['view']);
    if ($selectedEvent) {
        $eventStudents = getEventStudents($conn, $_GET['view']);
        $availableStudents = getStudentsNotInEvent($conn, $_GET['view']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>School Management - Events</title>
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
                <a href="events.php" class="nav-tab tab-transition flex items-center px-4 py-3 font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 <?php echo $currentPage == 'events' ? 'active' : ''; ?>">
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
        <!-- Page header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
                <?php if ($selectedEvent): ?>
                    Event: <?php echo htmlspecialchars($selectedEvent['event_name']); ?>
                <?php else: ?>
                    School Events
                <?php endif; ?>
            </h2>
            
            <?php if (!$selectedEvent): ?>
                <button id="openAddEventModal" class="mt-3 md:mt-0 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-300 flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    <span>Add New Event</span>
                </button>
            <?php else: ?>
                <div class="mt-3 md:mt-0 flex space-x-3">
                    <button id="openEditEventModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-300 flex items-center">
                        <i class="fas fa-edit mr-2"></i>
                        <span>Edit Event</span>
                    </button>
                    <button id="openDeleteEventModal" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md transition-colors duration-300 flex items-center">
                        <i class="fas fa-trash-alt mr-2"></i>
                        <span>Delete</span>
                    </button>
                    <a href="events.php" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-medium rounded-md transition-colors duration-300 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <span>Back</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $successMessage; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($selectedEvent): ?>
            <!-- Event details section -->
            <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Event Details</h3>
                        <div class="space-y-3">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Name:</span>
                                <span class="ml-2 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($selectedEvent['event_name']); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Date:</span>
                                <span class="ml-2 text-gray-800 dark:text-white font-medium"><?php echo date('F j, Y', strtotime($selectedEvent['event_date'])); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Description:</span>
                                <p class="mt-1 text-gray-800 dark:text-white"><?php echo nl2br(htmlspecialchars($selectedEvent['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Student Participants</h3>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-gray-500 dark:text-gray-400">Total Students: <?php echo count($eventStudents); ?></span>
                            <button id="openAddStudentModal" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded transition-colors duration-300 flex items-center">
                                <i class="fas fa-user-plus mr-1"></i>
                                <span>Add Student</span>
                            </button>
                        </div>
                        
                        <?php if (empty($eventStudents)): ?>
                            <p class="text-gray-500 dark:text-gray-400 italic">No students are participating in this event yet.</p>
                        <?php else: ?>
                            <div class="max-h-80 overflow-y-auto">
                                <ul class="space-y-2">
                                    <?php foreach ($eventStudents as $student): ?>
                                        <li class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-dark-bg rounded">
                                            <span class="text-gray-800 dark:text-white">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </span>
                                            <form method="post" class="removeStudentForm">
                                                <input type="hidden" name="event_id" value="<?php echo $selectedEvent['event_id']; ?>">
                                                <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                <button type="submit" name="remove_student_from_event" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                    <i class="fas fa-user-minus"></i>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Events list table -->
            <?php if (empty($events)): ?>
                <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 p-6 text-center">
                    <p class="text-gray-500 dark:text-gray-400">No events have been added yet.</p>
                </div>
            <?php else: ?>
                <div class="bg-white dark:bg-dark-card rounded-lg shadow dark:shadow-gray-900/30 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-dark-bg">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Event Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Participants
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-dark-card divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($events as $event): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($event['event_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 line-clamp-
                                            <div class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                                <?php echo !empty($event['description']) ? htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : '') : 'No description'; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <?php echo $event['participant_count']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="?view=<?php echo $event['event_id']; ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <footer class="bg-white dark:bg-dark-card border-t border-gray-200 dark:border-dark-border py-4 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400 text-sm">
      © 2025 Anurag&Anusha. All rights reserved.
    </div>
    </footer>
    <!-- Add Event Modal -->
    <div id="addEventModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
        <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl z-10 w-full max-w-md mx-4">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Event</h3>
                <button class="closeModal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post" class="p-4">
                <div class="mb-4">
                    <label for="event_name" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Event Name</label>
                    <input type="text" name="event_name" id="event_name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white">
                </div>
                <div class="mb-4">
                    <label for="event_date" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Event Date</label>
                    <input type="date" name="event_date" id="event_date" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white">
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Description</label>
                    <textarea name="description" id="description" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="closeModal mr-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                    <button type="submit" name="add_event" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">Add Event</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Event Modal -->
    <?php if ($selectedEvent): ?>
        <div id="editEventModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
            <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
            <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl z-10 w-full max-w-md mx-4">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Event</h3>
                    <button class="closeModal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="post" class="p-4">
                    <input type="hidden" name="event_id" value="<?php echo $selectedEvent['event_id']; ?>">
                    <div class="mb-4">
                        <label for="edit_event_name" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Event Name</label>
                        <input type="text" name="event_name" id="edit_event_name" value="<?php echo htmlspecialchars($selectedEvent['event_name']); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label for="edit_event_date" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Event Date</label>
                        <input type="date" name="event_date" id="edit_event_date" value="<?php echo date('Y-m-d', strtotime($selectedEvent['event_date'])); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="mb-4">
                        <label for="edit_description" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Description</label>
                        <textarea name="description" id="edit_description" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white"><?php echo htmlspecialchars($selectedEvent['description']); ?></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" class="closeModal mr-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                        <button type="submit" name="update_event" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Update Event</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Delete Event Modal -->
        <div id="deleteEventModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
            <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
            <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl z-10 w-full max-w-md mx-4">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Event</h3>
                    <button class="closeModal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <p class="text-gray-700 dark:text-gray-300 mb-4">Are you sure you want to delete this event? This action cannot be undone.</p>
                    <form method="post" class="flex justify-end">
                        <input type="hidden" name="event_id" value="<?php echo $selectedEvent['event_id']; ?>">
                        <button type="button" class="closeModal mr-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                        <button type="submit" name="delete_event" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Add Student Modal -->
        <div id="addStudentModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
            <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
            <div class="modal-content bg-white dark:bg-dark-card rounded-lg shadow-xl z-10 w-full max-w-md mx-4">
                <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Student to Event</h3>
                    <button class="closeModal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <?php if (empty($availableStudents)): ?>
                        <p class="text-gray-700 dark:text-gray-300 text-center">All students are already participating in this event.</p>
                        <div class="flex justify-end mt-4">
                            <button type="button" class="closeModal px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Close</button>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="event_id" value="<?php echo $selectedEvent['event_id']; ?>">
                            <div class="mb-4">
                                <label for="student_id" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Select Student</label>
                                <select name="student_id" id="student_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white">
                                    <option value="">-- Select a student --</option>
                                    <?php foreach ($availableStudents as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" class="closeModal mr-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                                <button type="submit" name="add_student_to_event" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">Add Student</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;
        
        // Check for saved theme preference or use system preference
        const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (savedTheme === 'dark') {
            html.classList.add('dark');
        }
        
        // Toggle theme
        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
        });
        
        // User dropdown functionality
        const dropdownButton = document.getElementById('dropdown-button');
        const dropdownMenu = document.getElementById('dropdown-menu');
        const dropdownArrow = document.getElementById('dropdown-arrow');
        
        dropdownButton.addEventListener('click', () => {
            dropdownMenu.classList.toggle('show');
            dropdownArrow.classList.toggle('transform');
            dropdownArrow.classList.toggle('rotate-180');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.classList.remove('rotate-180');
            }
        });
        
        // Modal functionality
        const modals = ['addEventModal', 'editEventModal', 'deleteEventModal', 'addStudentModal'];
        
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                const openBtn = document.getElementById('open' + modalId.charAt(0).toUpperCase() + modalId.slice(1));
                const closeBtns = modal.querySelectorAll('.closeModal');
                const backdrop = modal.querySelector('.modal-backdrop');
                
                if (openBtn) {
                    openBtn.addEventListener('click', () => {
                        modal.classList.remove('hidden');
                    });
                }
                
                closeBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        modal.classList.add('hidden');
                    });
                });
                
                backdrop.addEventListener('click', () => {
                    modal.classList.add('hidden');
                });
            }
        });
        
        // Confirm delete
        const removeStudentForms = document.querySelectorAll('.removeStudentForm');
        removeStudentForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!confirm('Are you sure you want to remove this student from the event?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Update date and time
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
        
        setInterval(updateDateTime, 60000); // Update every minute
    </script>
</body>
</html>