<?php
/**
 * Sub-Admin Dashboard
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

session_start();
require_once '../php/dbconnection.php';
require_once '../php/auth.php';
require_once '../php/security.php';

// Check authentication
requireLogin();

$currentUser = getCurrentUser();
$allowedRoles = ['sub_admin_education', 'sub_admin_healthcare', 'sub_admin_employment', 'sub_admin_emergency'];

if (!in_array($currentUser['role_name'], $allowedRoles)) {
    header('Location: /index.php');
    exit;
}

// Determine sector from role
$sectorMap = [
    'sub_admin_education' => 'Education',
    'sub_admin_healthcare' => 'Healthcare', 
    'sub_admin_employment' => 'Employment',
    'sub_admin_emergency' => 'Emergency'
];

$userSector = $sectorMap[$currentUser['role_name']];

// Get sector-specific metrics
$sectorId = fetchOne("SELECT sector_id FROM sectors WHERE sector_name = ?", [$userSector])['sector_id'];

$newApplications = fetchCount("
    SELECT COUNT(*) FROM applications a
    JOIN services s ON a.service_id = s.service_id
    WHERE s.sector_id = ? AND a.status = 'submitted'
", [$sectorId]);

$inReviewApplications = fetchCount("
    SELECT COUNT(*) FROM applications a
    JOIN services s ON a.service_id = s.service_id
    WHERE s.sector_id = ? AND a.status = 'in_review'
", [$sectorId]);

$approvedApplications = fetchCount("
    SELECT COUNT(*) FROM applications a
    JOIN services s ON a.service_id = s.service_id
    WHERE s.sector_id = ? AND a.status = 'approved'
", [$sectorId]);

$overdueApplications = fetchCount("
    SELECT COUNT(*) FROM applications a
    JOIN services s ON a.service_id = s.service_id
    WHERE s.sector_id = ? AND a.sla_due_date < CURDATE() AND a.status IN ('submitted', 'in_review')
", [$sectorId]);

// Get recent applications for this sector
$recentApplications = fetchAll("
    SELECT 
        a.*,
        s.service_name,
        CONCAT(c.first_name, ' ', c.last_name) as citizen_name,
        c.pwd_id_number,
        c.barangay
    FROM applications a
    JOIN services s ON a.service_id = s.service_id
    JOIN citizen_records c ON a.citizen_id = c.citizen_id
    WHERE s.sector_id = ?
    ORDER BY a.submitted_at DESC
    LIMIT 10
", [$sectorId]);

// Get sector services
$sectorServices = fetchAll("
    SELECT 
        s.*,
        COUNT(a.application_id) as total_applications,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM services s
    LEFT JOIN applications a ON s.service_id = a.service_id
    WHERE s.sector_id = ?
    GROUP BY s.service_id
    ORDER BY s.service_name
", [$sectorId]);

// Get unread notifications
$notificationCount = fetchCount("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$currentUser['user_id']]);

// Monthly data for sector
$monthlyData = fetchAll("
    SELECT 
        MONTH(a.submitted_at) as month,
        YEAR(a.submitted_at) as year,
        COUNT(*) as total,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM applications a 
    JOIN services s ON a.service_id = s.service_id
    WHERE s.sector_id = ? AND a.submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(a.submitted_at), MONTH(a.submitted_at)
    ORDER BY year, month
", [$sectorId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $userSector; ?> Dashboard - ACCESS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'pwd-blue': '#0077B3',
                        'pwd-light-blue': '#A3D1E0',
                        'pwd-very-light-blue': '#E6F7FF',
                        'pwd-dark-blue': '#005B99',
                        'pwd-accent': '#A3C1DA'
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-item:hover {
            background-color: #E6F7FF;
            border-left: 4px solid #0077B3;
        }
        .sidebar-item.active {
            background-color: #A3D1E0;
            border-left: 4px solid #005B99;
            color: #005B99;
        }
        .metric-card {
            background: linear-gradient(135deg, #E6F7FF 0%, #A3D1E0 100%);
            box-shadow: 0 4px 15px rgba(0, 119, 179, 0.1);
        }
    </style>
</head>

<body class="min-h-screen bg-white">
    
    <!-- Top Navigation Bar -->
    <nav class="w-full h-16 flex items-center justify-between px-6 shadow-md" style="background-color: #005B99;">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                <img src="https://placehold.co/32x32?text=<?php echo substr($userSector, 0, 1); ?>+sector+icon" alt="<?php echo $userSector; ?>" class="w-6 h-6">
            </div>
            <div>
                <h1 class="text-white text-lg font-semibold"><?php echo $userSector; ?> Sub-Admin Dashboard</h1>
                <p class="text-blue-200 text-sm">ACCESS System</p>
            </div>
        </div>
        
        <div class="flex items-center space-x-6">
            <!-- Search Bar -->
            <div class="relative">
                <input type="text" id="sectorSearch" placeholder="Search <?php echo strtolower($userSector); ?> applications..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
                <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="relative">
                <button id="notificationBtn" class="text-white hover:text-blue-200 transition-colors relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <?php if ($notificationCount > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            <?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- Profile -->
            <div class="relative">
                <button id="profileBtn" class="flex items-center space-x-2 text-white hover:text-blue-200 transition-colors">
                    <div class="w-8 h-8 rounded-full bg-blue-300 flex items-center justify-center">
                        <span class="text-sm font-semibold text-blue-800">
                            <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <span class="hidden md:block"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></span>
                </button>
            </div>
        </div>
    </nav>
    
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 min-h-screen shadow-lg" style="background-color: #FFFFFF; border-right: 1px solid #A3D1E0;">
            <!-- Logo and Sector Info -->
            <div class="p-6 text-center border-b" style="border-color: #A3D1E0;">
                <div class="w-16 h-16 mx-auto rounded-full mb-3 flex items-center justify-center" style="background-color: #E6F7FF;">
                    <img src="https://placehold.co/48x48?text=<?php echo $userSector; ?>+sector+emblem" alt="<?php echo $userSector; ?>" class="w-10 h-10">
                </div>
                <h2 class="font-bold text-lg" style="color: #005B99;"><?php echo $userSector; ?></h2>
                <p class="text-xs text-gray-600">Sub-Admin Dashboard</p>
                <p class="text-xs font-semibold mt-1" style="color: #0077B3;"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></p>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="mt-6">
                <a href="dashboard.php" class="sidebar-item active flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2V7"></path>
                    </svg>
                    Dashboard
                </a>
                
                <a href="applications.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Applications Queue
                </a>
                
                <a href="services.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V8a2 2 0 01-2 2H10a2 2 0 01-2-2V6m8 0H8m0 0v2a2 2 0 002 2h4a2 2 0 002-2V6"></path>
                    </svg>
                    Services Catalog
                </a>
                
                <a href="citizens.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Citizen Snapshot
                </a>
                
                <a href="reports.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Reports & Analytics
                </a>
                
                <a href="settings.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Sector Settings
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Dashboard Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo $userSector; ?> Dashboard</h1>
                <p class="text-gray-600">Monitor and manage <?php echo strtolower($userSector); ?> sector applications and services.</p>
            </div>
            
            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- New Applications -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">New Applications</p>
                            <p class="text-3xl font-bold" style="color: #005B99;"><?php echo $newApplications; ?></p>
                            <p class="text-sm text-blue-600 mt-1">Awaiting review</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #005B99;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- In Review -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">In Review</p>
                            <p class="text-3xl font-bold" style="color: #0077B3;"><?php echo $inReviewApplications; ?></p>
                            <p class="text-sm text-orange-600 mt-1">Under processing</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #0077B3;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Approved -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-3xl font-bold" style="color: #A3C1DA;"><?php echo $approvedApplications; ?></p>
                            <p class="text-sm text-green-600 mt-1">Successfully processed</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3C1DA;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Overdue -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Overdue (SLA)</p>
                            <p class="text-3xl font-bold" style="color: #A3D1E0;"><?php echo $overdueApplications; ?></p>
                            <p class="text-sm text-red-600 mt-1">Requires attention</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.734 0L4.08 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Monthly Trend Chart -->
                <div class="lg:col-span-2 bg-white rounded-lg shadow-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800"><?php echo $userSector; ?> Applications Trend</h3>
                        <p class="text-sm text-gray-600">Monthly application processing statistics</p>
                    </div>
                    <div class="p-6">
                        <canvas id="monthlyChart" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Workload Summary -->
                <div class="bg-white rounded-lg shadow-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">My Workload</h3>
                        <p class="text-sm text-gray-600">Assigned tasks and deadlines</p>
                    </div>
                    <div class="p-6">
                        <?php
                        $assignedToMe = fetchCount("
                            SELECT COUNT(*) FROM applications a
                            JOIN services s ON a.service_id = s.service_id
                            WHERE s.sector_id = ? AND a.assigned_to = ?
                        ", [$sectorId, $currentUser['user_id']]);
                        
                        $dueSoon = fetchCount("
                            SELECT COUNT(*) FROM applications a
                            JOIN services s ON a.service_id = s.service_id
                            WHERE s.sector_id = ? AND a.assigned_to = ? 
                            AND a.sla_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                        ", [$sectorId, $currentUser['user_id']]);
                        ?>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: #E6F7FF;">
                                <div>
                                    <p class="font-semibold text-gray-800">Assigned to Me</p>
                                    <p class="text-sm text-gray-600">Total active assignments</p>
                                </div>
                                <div class="text-2xl font-bold" style="color: #0077B3;"><?php echo $assignedToMe; ?></div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 rounded-lg bg-orange-50">
                                <div>
                                    <p class="font-semibold text-gray-800">Due Soon</p>
                                    <p class="text-sm text-gray-600">Due within 3 days</p>
                                </div>
                                <div class="text-2xl font-bold text-orange-600"><?php echo $dueSoon; ?></div>
                            </div>
                            
                            <div class="text-center">
                                <a href="applications.php?filter=assigned_to_me" 
                                   class="text-sm font-medium" style="color: #0077B3;">
                                    View My Tasks →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Applications Table -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Recent Applications</h3>
                            <p class="text-sm text-gray-600">Latest <?php echo strtolower($userSector); ?> applications</p>
                        </div>
                        <a href="applications.php" class="text-sm font-medium" style="color: #0077B3;">View all →</a>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Citizen</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barangay</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recentApplications)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-lg font-semibold mb-2">No Applications Yet</p>
                                    <p>No applications have been submitted for <?php echo strtolower($userSector); ?> services.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentApplications as $app): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($app['reference_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($app['citizen_name']); ?>
                                        <?php if ($app['pwd_id_number']): ?>
                                            <br><span class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($app['pwd_id_number']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($app['service_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($app['barangay']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($app['submitted_at'])); ?>
                                        <?php if ($app['sla_due_date'] && $app['sla_due_date'] < date('Y-m-d')): ?>
                                            <br><span class="text-xs text-red-500">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $statusColors = [
                                            'submitted' => 'bg-blue-100 text-blue-800',
                                            'in_review' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        $colorClass = $statusColors[$app['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $colorClass; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button onclick="viewApplication('<?php echo $app['reference_number']; ?>')" 
                                                class="text-indigo-600 hover:text-indigo-900">Review</button>
                                        <?php if ($app['status'] === 'submitted'): ?>
                                        <button onclick="assignToMe('<?php echo $app['application_id']; ?>')" 
                                                class="text-green-600 hover:text-green-900">Assign</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Sector search
        document.getElementById('sectorSearch').addEventListener('input', function() {
            const query = this.value;
            if (query.length > 2) {
                // Implement sector-specific search
                console.log('Searching <?php echo $userSector; ?> for:', query);
            }
        });
        
        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($item) {
                    return date('M Y', mktime(0, 0, 0, $item['month'], 1, $item['year']));
                }, $monthlyData)); ?>,
                datasets: [{
                    label: 'Total Applications',
                    data: <?php echo json_encode(array_column($monthlyData, 'total')); ?>,
                    borderColor: '#0077B3',
                    backgroundColor: 'rgba(0, 119, 179, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Approved',
                    data: <?php echo json_encode(array_column($monthlyData, 'approved')); ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Rejected',
                    data: <?php echo json_encode(array_column($monthlyData, 'rejected')); ?>,
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Application actions
        function viewApplication(refNumber) {
            window.location.href = `applications.php?ref=${refNumber}`;
        }
        
        function assignToMe(applicationId) {
            if (confirm('Assign this application to yourself?')) {
                fetch('../php/api.php?endpoint=applications', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: applicationId,
                        assigned_to: <?php echo $currentUser['user_id']; ?>,
                        status: 'in_review'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error assigning application');
                });
            }
        }
        
        // Auto-refresh dashboard every 30 seconds
        setInterval(() => {
            fetch('../php/api.php?endpoint=dashboard-refresh')
                .then(response => response.json())
                .then(data => {
                    console.log('Dashboard refreshed:', data);
                })
                .catch(error => console.error('Error refreshing dashboard:', error));
        }, 30000);
    </script>
</body>
</html>