<?php
/**
 * Client Dashboard
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
if ($currentUser['role_name'] !== 'client') {
    header('Location: /index.php');
    exit;
}

// Get citizen record
$citizenRecord = fetchOne("SELECT * FROM citizen_records WHERE user_id = ?", [$currentUser['user_id']]);

if (!$citizenRecord) {
    die("Citizen record not found. Please contact administrator.");
}

// Get dashboard metrics for client
$totalApplications = fetchCount("SELECT COUNT(*) FROM applications WHERE citizen_id = ?", [$citizenRecord['citizen_id']]);
$approvedApplications = fetchCount("SELECT COUNT(*) FROM applications WHERE citizen_id = ? AND status = 'approved'", [$citizenRecord['citizen_id']]);
$pendingApplications = fetchCount("SELECT COUNT(*) FROM applications WHERE citizen_id = ? AND status IN ('submitted', 'in_review')", [$citizenRecord['citizen_id']]);
$unreadNotifications = fetchCount("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$currentUser['user_id']]);

// Get recent applications
$recentApplications = fetchAll("
    SELECT 
        a.*,
        s.service_name,
        sect.sector_name
    FROM applications a
    JOIN services s ON a.service_id = s.service_id
    JOIN sectors sect ON s.sector_id = sect.sector_id
    WHERE a.citizen_id = ?
    ORDER BY a.submitted_at DESC
    LIMIT 5
", [$citizenRecord['citizen_id']]);

// Get PWD ID status
$pwdIdRequests = fetchAll("
    SELECT * FROM id_booklet_requests 
    WHERE citizen_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
", [$citizenRecord['citizen_id']]);

// Get available services
$availableServices = fetchAll("
    SELECT 
        s.*,
        sect.sector_name,
        (s.capacity - COALESCE(app_count.total, 0)) as remaining_slots
    FROM services s
    JOIN sectors sect ON s.sector_id = sect.sector_id
    LEFT JOIN (
        SELECT service_id, COUNT(*) as total 
        FROM applications 
        WHERE status IN ('approved', 'submitted', 'in_review')
        GROUP BY service_id
    ) app_count ON s.service_id = app_count.service_id
    WHERE s.status = 'active' 
    AND (s.application_end_date IS NULL OR s.application_end_date >= CURDATE())
    LIMIT 6
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - ACCESS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .service-card {
            background: #FFFFFF;
            border: 1px solid #A3D1E0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .service-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 119, 179, 0.15);
        }
    </style>
</head>

<body class="min-h-screen bg-white">
    
    <!-- Top Navigation Bar -->
    <nav class="w-full h-16 flex items-center justify-between px-6 shadow-md" style="background-color: #005B99;">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                <img src="https://placehold.co/32x32?text=PWD+client+user+icon" alt="PWD Client" class="w-6 h-6">
            </div>
            <div>
                <h1 class="text-white text-lg font-semibold">ACCESS - My Dashboard</h1>
                <p class="text-blue-200 text-sm">Welcome, <?php echo htmlspecialchars($citizenRecord['first_name']); ?></p>
            </div>
        </div>
        
        <div class="flex items-center space-x-6">
            <!-- Search -->
            <div class="relative">
                <input type="text" id="clientSearch" placeholder="Search my applications..." 
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
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- Profile -->
            <div class="relative">
                <button id="profileBtn" class="flex items-center space-x-2 text-white hover:text-blue-200 transition-colors">
                    <div class="w-8 h-8 rounded-full bg-blue-300 flex items-center justify-center">
                        <span class="text-sm font-semibold text-blue-800">
                            <?php echo strtoupper(substr($citizenRecord['first_name'], 0, 1) . substr($citizenRecord['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <span class="hidden md:block"><?php echo htmlspecialchars($citizenRecord['first_name'] . ' ' . $citizenRecord['last_name']); ?></span>
                </button>
            </div>
        </div>
    </nav>
    
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 min-h-screen shadow-lg" style="background-color: #FFFFFF; border-right: 1px solid #A3D1E0;">
            <!-- User Info -->
            <div class="p-6 text-center border-b" style="border-color: #A3D1E0;">
                <div class="w-16 h-16 mx-auto rounded-full mb-3 flex items-center justify-center" style="background-color: #E6F7FF;">
                    <img src="https://placehold.co/48x48?text=PWD+client+profile+avatar" alt="Profile" class="w-10 h-10 rounded-full">
                </div>
                <h2 class="font-bold text-lg" style="color: #005B99;"><?php echo htmlspecialchars($citizenRecord['first_name'] . ' ' . $citizenRecord['last_name']); ?></h2>
                <p class="text-xs text-gray-600">PWD Client</p>
                <?php if ($citizenRecord['pwd_id_number']): ?>
                    <p class="text-xs font-semibold mt-1" style="color: #0077B3;">ID: <?php echo htmlspecialchars($citizenRecord['pwd_id_number']); ?></p>
                <?php endif; ?>
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
                    My Applications
                </a>
                
                <a href="pwd-id.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                    </svg>
                    PWD ID & Booklets
                </a>
                
                <a href="services.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V8a2 2 0 01-2 2H10a2 2 0 01-2-2V6m8 0H8m0 0v2a2 2 0 002 2h4a2 2 0 002-2V6"></path>
                    </svg>
                    Available Services
                </a>
                
                <a href="appointments.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 114 0v4m-4 0h4M8 7H4a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-4M8 7V3a2 2 0 114 0v4m-4 0h4M8 7H4a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-4"></path>
                    </svg>
                    Appointments
                </a>
                
                <a href="assistance.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                    Assistance Requests
                </a>
                
                <a href="complaints.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.734 0L4.08 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    Submit Complaint
                </a>
                
                <a href="profile.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    My Profile
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Dashboard Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">My Dashboard</h1>
                <p class="text-gray-600">Track your applications, PWD ID status, and available services.</p>
            </div>
            
            <!-- Status Alert -->
            <?php if ($citizenRecord['verification_status'] !== 'verified'): ?>
            <div class="mb-6 p-4 rounded-lg" style="background-color: #FEF3C7; border: 1px solid #F59E0B;">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.734 0L4.08 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-yellow-800">Account Verification Pending</h3>
                        <p class="text-yellow-700 text-sm mt-1">
                            Your account is currently under review. You can browse services but cannot apply until verification is complete.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Applications -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">My Applications</p>
                            <p class="text-3xl font-bold" style="color: #005B99;"><?php echo $totalApplications; ?></p>
                            <p class="text-sm text-blue-600 mt-1">Total submitted</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #005B99;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Approved Services -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Approved Services</p>
                            <p class="text-3xl font-bold" style="color: #0077B3;"><?php echo $approvedApplications; ?></p>
                            <p class="text-sm text-green-600 mt-1">Successfully approved</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #0077B3;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Applications -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending Review</p>
                            <p class="text-3xl font-bold" style="color: #A3C1DA;"><?php echo $pendingApplications; ?></p>
                            <p class="text-sm text-orange-600 mt-1">In progress</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3C1DA;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- PWD ID Status -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">PWD ID Status</p>
                            <p class="text-lg font-bold" style="color: #A3D1E0;">
                                <?php echo ucwords(str_replace('_', ' ', $citizenRecord['pwd_id_status'])); ?>
                            </p>
                            <?php if ($citizenRecord['pwd_id_expiry_date']): ?>
                                <p class="text-xs text-gray-600 mt-1">Expires: <?php echo date('M j, Y', strtotime($citizenRecord['pwd_id_expiry_date'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Recent Applications -->
                <div class="lg:col-span-2 bg-white rounded-lg shadow-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Applications</h3>
                        <p class="text-sm text-gray-600">Your latest service requests</p>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($recentApplications)): ?>
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h4 class="text-lg font-semibold text-gray-600 mb-2">No Applications Yet</h4>
                                <p class="text-gray-500 mb-4">You haven't submitted any applications.</p>
                                <a href="services.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white" style="background-color: #0077B3;">
                                    Browse Services
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recentApplications as $app): ?>
                                <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($app['service_name']); ?></h4>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($app['sector_name']); ?> • Ref: <?php echo htmlspecialchars($app['reference_number']); ?></p>
                                            <p class="text-xs text-gray-500 mt-1">Submitted: <?php echo date('M j, Y', strtotime($app['submitted_at'])); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php 
                                            $statusColors = [
                                                'submitted' => 'bg-blue-100 text-blue-800',
                                                'in_review' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'completed' => 'bg-purple-100 text-purple-800'
                                            ];
                                            $colorClass = $statusColors[$app['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $colorClass; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <a href="applications.php" class="text-sm font-medium" style="color: #0077B3;">View all applications →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- PWD ID Status Card -->
                <div class="bg-white rounded-lg shadow-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">PWD ID & Booklets</h3>
                        <p class="text-sm text-gray-600">Your ID and booklet status</p>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($pwdIdRequests)): ?>
                            <div class="text-center py-6">
                                <div class="w-16 h-16 mx-auto rounded-full mb-4 flex items-center justify-center" style="background-color: #E6F7FF;">
                                    <svg class="w-8 h-8" style="color: #0077B3;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold text-gray-700 mb-2">No PWD ID Yet</h4>
                                <p class="text-sm text-gray-600 mb-4">Apply for your PWD ID to access services and benefits.</p>
                                <a href="pwd-id.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white" style="background-color: #0077B3;">
                                    Apply for PWD ID
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($pwdIdRequests as $request): ?>
                                <div class="border rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="font-semibold text-sm text-gray-800"><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></h5>
                                            <p class="text-xs text-gray-600"><?php echo ucwords($request['application_type']); ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></p>
                                        </div>
                                        <?php 
                                        $idStatusColors = [
                                            'applied' => 'bg-blue-100 text-blue-800',
                                            'processing' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'printed' => 'bg-purple-100 text-purple-800',
                                            'released' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $colorClass = $idStatusColors[$request['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $colorClass; ?>">
                                            <?php echo ucwords($request['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <a href="pwd-id.php" class="text-sm font-medium" style="color: #0077B3;">Manage PWD ID →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Available Services -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Available Services</h3>
                            <p class="text-sm text-gray-600">Services you can apply for</p>
                        </div>
                        <a href="services.php" class="text-sm font-medium" style="color: #0077B3;">View all →</a>
                    </div>
                </div>
                
                <div class="p-6">
                    <?php if (empty($availableServices)): ?>
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V8a2 2 0 01-2 2H10a2 2 0 01-2-2V6m8 0H8m0 0v2a2 2 0 002 2h4a2 2 0 002-2V6"></path>
                            </svg>
                            <h4 class="text-lg font-semibold text-gray-600 mb-2">No Services Available</h4>
                            <p class="text-gray-500">Check back later for new services.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($availableServices as $service): ?>
                            <div class="service-card rounded-lg p-4">
                                <div class="mb-3">
                                    <h4 class="font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                    <p class="text-sm" style="color: #0077B3;"><?php echo htmlspecialchars($service['sector_name']); ?></p>
                                </div>
                                
                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($service['description'], 0, 100)); ?>...</p>
                                
                                <div class="flex items-center justify-between">
                                    <div class="text-xs text-gray-500">
                                        <?php if ($service['capacity']): ?>
                                            <?php echo $service['remaining_slots']; ?> / <?php echo $service['capacity']; ?> slots
                                        <?php else: ?>
                                            Unlimited slots
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($citizenRecord['verification_status'] === 'verified'): ?>
                                        <button onclick="applyForService(<?php echo $service['service_id']; ?>)" 
                                                class="text-xs px-3 py-1 rounded-full text-white font-medium hover:opacity-90 transition-opacity"
                                                style="background-color: #0077B3;">
                                            Apply
                                        </button>
                                    <?php else: ?>
                                        <span class="text-xs px-3 py-1 rounded-full bg-gray-200 text-gray-600">
                                            Verification Required
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Client search
        document.getElementById('clientSearch').addEventListener('input', function() {
            const query = this.value;
            if (query.length > 2) {
                // Implement client-specific search
                console.log('Searching for:', query);
            }
        });
        
        // Apply for service function
        function applyForService(serviceId) {
            if (confirm('Do you want to apply for this service?')) {
                window.location.href = `services.php?apply=${serviceId}`;
            }
        }
        
        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            fetch('../php/api.php?endpoint=notifications')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const unreadCount = data.notifications.filter(n => !n.is_read).length;
                        const notificationBadge = document.querySelector('#notificationBtn .absolute');
                        
                        if (unreadCount > 0) {
                            if (!notificationBadge) {
                                const badge = document.createElement('span');
                                badge.className = 'absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center';
                                badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                                document.getElementById('notificationBtn').appendChild(badge);
                            } else {
                                notificationBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                            }
                        } else if (notificationBadge) {
                            notificationBadge.remove();
                        }
                    }
                })
                .catch(error => console.error('Error refreshing notifications:', error));
        }, 30000);
    </script>
</body>
</html>