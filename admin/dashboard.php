<?php
/**
 * Super Admin Dashboard
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

session_start();
require_once '../php/dbconnection.php';
require_once '../php/auth.php';
require_once '../php/security.php';

// Check authentication and permissions
requireLogin();
requirePermission('all'); // Super admin only

$currentUser = getCurrentUser();

// Get dashboard metrics
$dashboardMetrics = fetchOne("SELECT * FROM dashboard_metrics");

// Get monthly application data for chart
$monthlyData = fetchAll("
    SELECT 
        MONTH(submitted_at) as month,
        YEAR(submitted_at) as year,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM applications 
    WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(submitted_at), MONTH(submitted_at)
    ORDER BY year, month
");

// Get services availed data
$servicesData = fetchAll("
    SELECT 
        s.service_name,
        COUNT(a.application_id) as total_applications
    FROM services s
    LEFT JOIN applications a ON s.service_id = a.service_id
    WHERE a.status = 'approved'
    GROUP BY s.service_id, s.service_name
    ORDER BY total_applications DESC
    LIMIT 10
");

// Get recent applications
$recentApplications = fetchAll("SELECT * FROM recent_applications LIMIT 10");

// Get unread notifications count
$notificationCount = fetchCount("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$currentUser['user_id']]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - ACCESS System</title>
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
                <img src="https://placehold.co/32x32?text=PWD+logo" alt="PWD Logo" class="w-6 h-6">
            </div>
            <h1 class="text-white text-lg font-semibold">ACCESS - Super Admin Dashboard</h1>
        </div>
        
        <div class="flex items-center space-x-6">
            <!-- Search Bar -->
            <div class="relative">
                <input type="text" id="globalSearch" placeholder="Search across system..." 
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
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>
        </div>
    </nav>
    
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 min-h-screen shadow-lg" style="background-color: #FFFFFF; border-right: 1px solid #A3D1E0;">
            <!-- Logo and System Name -->
            <div class="p-6 text-center border-b" style="border-color: #A3D1E0;">
                <div class="w-16 h-16 mx-auto rounded-full mb-3 flex items-center justify-center" style="background-color: #E6F7FF;">
                    <img src="https://placehold.co/48x48?text=ACCESS+system+logo+with+PWD+emblem" alt="ACCESS Logo" class="w-10 h-10">
                </div>
                <h2 class="font-bold text-lg" style="color: #005B99;">ACCESS</h2>
                <p class="text-xs text-gray-600">PWD E-Records System</p>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="mt-6">
                <a href="dashboard.php" class="sidebar-item active flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2V7"></path>
                    </svg>
                    Dashboard
                </a>
                
                <a href="citizen-records.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Citizen Records
                </a>
                
                <a href="requests.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Requests
                </a>
                
                <a href="id-booklets.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                    </svg>
                    ID & Booklets
                </a>
                
                <!-- Services Dropdown -->
                <div class="relative">
                    <button id="servicesDropdown" class="sidebar-item flex items-center justify-between w-full px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V8a2 2 0 01-2 2H10a2 2 0 01-2-2V6m8 0H8m0 0v2a2 2 0 002 2h4a2 2 0 002-2V6"></path>
                            </svg>
                            Services
                        </div>
                        <svg class="w-4 h-4 transition-transform" id="servicesChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="servicesMenu" class="hidden bg-gray-50 border-t border-gray-200">
                        <a href="services/education.php" class="block px-12 py-2 text-sm text-gray-600 hover:text-blue-800 hover:bg-blue-50">Education</a>
                        <a href="services/healthcare.php" class="block px-12 py-2 text-sm text-gray-600 hover:text-blue-800 hover:bg-blue-50">Healthcare</a>
                        <a href="services/employment.php" class="block px-12 py-2 text-sm text-gray-600 hover:text-blue-800 hover:bg-blue-50">Employment</a>
                        <a href="services/emergency.php" class="block px-12 py-2 text-sm text-gray-600 hover:text-blue-800 hover:bg-blue-50">Emergency</a>
                    </div>
                </div>
                
                <a href="assistance.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                    Assistance
                </a>
                
                <a href="complaints.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.734 0L4.08 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    Complaints
                </a>
                
                <a href="reports.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Reports
                </a>
                
                <a href="settings.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-blue-800 transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Settings
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Dashboard Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Overview</h1>
                <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($currentUser['first_name']); ?>. Here's what's happening today.</p>
            </div>
            
            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Registered PWD Clients -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Registered PWD</p>
                            <p class="text-3xl font-bold" style="color: #005B99;"><?php echo number_format($dashboardMetrics['total_verified_pwd']); ?></p>
                            <p class="text-sm text-green-600 mt-1">Verified clients</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #005B99;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Verifications -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending Verifications</p>
                            <p class="text-3xl font-bold" style="color: #0077B3;"><?php echo number_format($dashboardMetrics['pending_verifications']); ?></p>
                            <p class="text-sm text-orange-600 mt-1">Awaiting review</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #0077B3;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Active Service Requests -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Requests</p>
                            <p class="text-3xl font-bold" style="color: #A3C1DA;"><?php echo number_format($dashboardMetrics['active_requests']); ?></p>
                            <p class="text-sm text-blue-600 mt-1">In progress</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3C1DA;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Partner Sectors Active -->
                <div class="metric-card rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Partners</p>
                            <p class="text-3xl font-bold" style="color: #A3D1E0;"><?php echo number_format($dashboardMetrics['active_partners']); ?></p>
                            <p class="text-sm text-purple-600 mt-1">Sub-admins</p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V8a2 2 0 01-2 2H10a2 2 0 01-2-2V6m8 0H8m0 0v2a2 2 0 002 2h4a2 2 0 002-2V6"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Monthly Applications Chart -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Applications Trend</h3>
                    <canvas id="monthlyChart" height="200"></canvas>
                </div>
                
                <!-- Services Distribution Chart -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Services Availed by Category</h3>
                    <canvas id="servicesChart" height="200"></canvas>
                </div>
            </div>
            
            <!-- Recent Requests Table -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Requests</h3>
                    <p class="text-sm text-gray-600">Latest application requests requiring attention</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sector</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentApplications as $app): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($app['reference_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($app['citizen_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($app['service_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($app['sector_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($app['submitted_at'])); ?>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewApplication('<?php echo $app['reference_number']; ?>')" 
                                            class="text-indigo-600 hover:text-indigo-900">View</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <p class="text-sm text-gray-700">Showing recent requests</p>
                        <a href="requests.php" class="text-sm font-medium" style="color: #0077B3;">View all requests →</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Services Dropdown
        document.getElementById('servicesDropdown').addEventListener('click', function() {
            const menu = document.getElementById('servicesMenu');
            const chevron = document.getElementById('servicesChevron');
            
            menu.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        });
        
        // Global Search
        document.getElementById('globalSearch').addEventListener('input', function() {
            const query = this.value;
            if (query.length > 2) {
                // Implement search functionality
                console.log('Searching for:', query);
            }
        });
        
        // Monthly Applications Chart
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
        
        // Services Chart
        const servicesCtx = document.getElementById('servicesChart').getContext('2d');
        const servicesChart = new Chart(servicesCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($servicesData, 'service_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($servicesData, 'total_applications')); ?>,
                    backgroundColor: [
                        '#0077B3',
                        '#A3D1E0',
                        '#005B99',
                        '#A3C1DA',
                        '#E6F7FF',
                        '#4B5563',
                        '#9CA3AF',
                        '#6B7280',
                        '#374151',
                        '#1F2937'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // View Application Function
        function viewApplication(refNumber) {
            window.location.href = `requests.php?ref=${refNumber}`;
        }
        
        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            // Refresh notification count and other real-time data
            fetch('api/dashboard-refresh.php')
                .then(response => response.json())
                .then(data => {
                    // Update notification count if needed
                    console.log('Dashboard refreshed:', data);
                })
                .catch(error => console.error('Error refreshing dashboard:', error));
        }, 30000);
    </script>
</body>
</html>