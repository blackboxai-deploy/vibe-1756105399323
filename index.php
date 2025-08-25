<?php
/**
 * Main Login Page
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

session_start();
require_once 'php/dbconnection.php';
require_once 'php/auth.php';
require_once 'php/security.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    header('Location: ' . getDashboardUrl($user['role_name']));
    exit;
}

// Handle login form submission
$loginError = '';
if ($_POST['action'] === 'login') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    // Rate limiting
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit($clientIP)) {
        $loginError = 'Too many failed attempts. Please try again later.';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit;
        } else {
            $loginError = $result['message'];
        }
    }
}

// Handle registration form submission
$registrationMessage = '';
$registrationError = '';
if ($_POST['action'] === 'register_client' || $_POST['action'] === 'register_subadmin') {
    $data = sanitizeInput($_POST);
    unset($data['action']);
    
    if ($_POST['action'] === 'register_client') {
        $result = registerClient($data, $_FILES);
    } else {
        $result = registerSubAdmin($data, $_FILES);
    }
    
    if ($result['success']) {
        $registrationMessage = $result['message'];
    } else {
        $registrationError = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCESS - PWD Affair Office | LGU Malasiqui</title>
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
        .gradient-bg {
            background: linear-gradient(135deg, #E6F7FF 0%, #A3D1E0 50%, #0077B3 100%);
        }
        
        .card-shadow {
            box-shadow: 0 10px 25px rgba(0, 119, 179, 0.1);
        }
        
        .input-focus:focus {
            border-color: #0077B3;
            box-shadow: 0 0 0 3px rgba(0, 119, 179, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0077B3, #005B99);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0, 119, 179, 0.3);
        }
    </style>
</head>

<body class="min-h-screen" style="background-color: #FFFFFF;">
    
    <!-- Header -->
    <header class="w-full py-4 px-6" style="background-color: #005B99;">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                    <img src="https://placehold.co/48x48?text=PWD+Logo+with+accessibility+symbol+and+LGU+emblem" alt="PWD Affairs Office Logo" class="w-8 h-8 object-contain">
                </div>
                <div>
                    <h1 class="text-white text-xl font-bold">ACCESS System</h1>
                    <p class="text-blue-200 text-sm">Automated Community and Citizen E-Records Service System</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-white text-sm font-semibold">PWD Affair Office</p>
                <p class="text-blue-200 text-xs">LGU Malasiqui, Pangasinan</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center py-12 px-6">
        <div class="max-w-md w-full">
            
            <!-- Login Form (Default View) -->
            <div id="loginForm" class="card-shadow rounded-2xl p-8" style="background-color: #FFFFFF; border: 1px solid #A3D1E0;">
                <div class="text-center mb-8">
                    <div class="w-20 h-20 mx-auto rounded-full mb-4 flex items-center justify-center" style="background-color: #E6F7FF;">
                        <img src="https://placehold.co/60x60?text=User+login+icon+with+accessibility+features" alt="Login Icon" class="w-12 h-12 object-contain">
                    </div>
                    <h2 class="text-2xl font-bold" style="color: #005B99;">Welcome Back</h2>
                    <p class="text-gray-600 mt-2">Sign in to access your dashboard</p>
                </div>

                <?php if ($loginError): ?>
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($loginError); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="action" value="login">
                    
                    <div>
                        <label for="username" class="block text-sm font-semibold mb-2" style="color: #005B99;">Username</label>
                        <input type="text" id="username" name="username" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus transition duration-200"
                               placeholder="Enter your username">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-semibold mb-2" style="color: #005B99;">Password</label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus transition duration-200"
                               placeholder="Enter your password">
                    </div>
                    
                    <button type="submit" class="w-full py-3 px-4 btn-primary text-white font-semibold rounded-lg">
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600 text-sm mb-4">No account yet?</p>
                    <button id="showRegisterOptions" 
                            class="px-6 py-2 border-2 rounded-lg font-semibold transition duration-200 hover:shadow-md"
                            style="border-color: #A3D1E0; color: #005B99; background-color: transparent;">
                        Register
                    </button>
                </div>
            </div>

            <!-- Registration Type Selection -->
            <div id="registerOptions" class="card-shadow rounded-2xl p-8 hidden" style="background-color: #FFFFFF; border: 1px solid #A3D1E0;">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold" style="color: #005B99;">Choose Registration Type</h2>
                    <p class="text-gray-600 mt-2">Select your registration category</p>
                </div>

                <div class="space-y-4">
                    <button id="clientRegisterBtn" 
                            class="w-full p-4 rounded-lg border-2 transition duration-200 hover:shadow-md text-left"
                            style="border-color: #A3D1E0; background-color: #E6F7FF;">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                                <img src="https://placehold.co/32x32?text=PWD+client+user+icon" alt="Client Icon" class="w-6 h-6">
                            </div>
                            <div>
                                <h3 class="font-semibold" style="color: #005B99;">PWD Client</h3>
                                <p class="text-sm text-gray-600">Register as a Person with Disability</p>
                            </div>
                        </div>
                    </button>

                    <button id="subadminRegisterBtn" 
                            class="w-full p-4 rounded-lg border-2 transition duration-200 hover:shadow-md text-left"
                            style="border-color: #A3D1E0; background-color: #E6F7FF;">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A3D1E0;">
                                <img src="https://placehold.co/32x32?text=Organization+admin+icon" alt="Sub-Admin Icon" class="w-6 h-6">
                            </div>
                            <div>
                                <h3 class="font-semibold" style="color: #005B99;">Sub-Admin / Organization</h3>
                                <p class="text-sm text-gray-600">Register as service provider or partner organization</p>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="mt-6 text-center">
                    <button id="backToLogin" class="text-gray-500 hover:text-gray-700 text-sm">
                        ← Back to Login
                    </button>
                </div>
            </div>

            <!-- Client Registration Form -->
            <div id="clientRegisterForm" class="card-shadow rounded-2xl p-8 hidden max-h-96 overflow-y-auto" style="background-color: #FFFFFF; border: 1px solid #A3D1E0;">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-bold" style="color: #005B99;">PWD Client Registration</h2>
                    <p class="text-gray-600 text-sm mt-1">Complete your information below</p>
                </div>

                <?php if ($registrationMessage): ?>
                    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200">
                        <p class="text-green-600 text-sm"><?php echo htmlspecialchars($registrationMessage); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($registrationError): ?>
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($registrationError); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="register_client">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">First Name *</label>
                            <input type="text" name="first_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Last Name *</label>
                            <input type="text" name="last_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: #005B99;">Middle Name</label>
                        <input type="text" name="middle_name" 
                               class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Username *</label>
                            <input type="text" name="username" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Email *</label>
                            <input type="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: #005B99;">Password *</label>
                        <input type="password" name="password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters with uppercase, lowercase, number, and special character</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Barangay *</label>
                            <input type="text" name="barangay" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Contact Number</label>
                            <input type="text" name="contact_number" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: #005B99;">Disability Type *</label>
                        <select name="disability_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                            <option value="">Select disability type</option>
                            <option value="Physical">Physical Disability</option>
                            <option value="Visual">Visual Disability</option>
                            <option value="Hearing">Hearing Disability</option>
                            <option value="Intellectual">Intellectual Disability</option>
                            <option value="Psychosocial">Psychosocial Disability</option>
                            <option value="Multiple">Multiple Disabilities</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <h4 class="font-medium text-sm" style="color: #005B99;">Required Documents (JPG, PNG, PDF - Max 500MB each)</h4>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Barangay Clearance</label>
                            <input type="file" name="documents[1]" accept=".jpg,.jpeg,.png,.pdf" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Medical Certificate</label>
                            <input type="file" name="documents[2]" accept=".jpg,.jpeg,.png,.pdf" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Disability Assessment Form</label>
                            <input type="file" name="documents[3]" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-2 px-4 btn-primary text-white font-semibold rounded-lg text-sm">
                        Register as PWD Client
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <button id="backToRegisterOptions1" class="text-gray-500 hover:text-gray-700 text-sm">
                        ← Back to Registration Options
                    </button>
                </div>
            </div>

            <!-- Sub-Admin Registration Form -->
            <div id="subadminRegisterForm" class="card-shadow rounded-2xl p-8 hidden max-h-96 overflow-y-auto" style="background-color: #FFFFFF; border: 1px solid #A3D1E0;">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-bold" style="color: #005B99;">Sub-Admin Registration</h2>
                    <p class="text-gray-600 text-sm mt-1">Register your organization</p>
                </div>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="register_subadmin">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">First Name *</label>
                            <input type="text" name="first_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Last Name *</label>
                            <input type="text" name="last_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Username *</label>
                            <input type="text" name="username" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Email *</label>
                            <input type="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: #005B99;">Password *</label>
                        <input type="password" name="password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Sector *</label>
                            <select name="sector" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                                <option value="">Select sector</option>
                                <option value="education">Education</option>
                                <option value="healthcare">Healthcare</option>
                                <option value="employment">Employment</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: #005B99;">Organization Type *</label>
                            <select name="organization_type" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                                <option value="">Select type</option>
                                <option value="government">Government</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: #005B99;">Contact Person *</label>
                        <input type="text" name="contact_person" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: #005B99;">Contact Number</label>
                        <input type="text" name="contact_number" 
                               class="w-full px-3 py-2 border border-gray-300 rounded input-focus text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <h4 class="font-medium text-sm" style="color: #005B99;">Required Documents (PDF, DOC, JPG, PNG - Max 500MB each)</h4>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">MOA Document</label>
                            <input type="file" name="documents[4]" accept=".pdf,.doc,.docx" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Accreditation Certificate</label>
                            <input type="file" name="documents[5]" accept=".jpg,.jpeg,.png,.pdf" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">License Document</label>
                            <input type="file" name="documents[6]" accept=".jpg,.jpeg,.png,.pdf" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">SEC/DTI Registration</label>
                            <input type="file" name="documents[7]" accept=".jpg,.jpeg,.png,.pdf" 
                                   class="w-full text-xs border border-gray-300 rounded px-2 py-1">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-2 px-4 btn-primary text-white font-semibold rounded-lg text-sm">
                        Register as Sub-Admin
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <button id="backToRegisterOptions2" class="text-gray-500 hover:text-gray-700 text-sm">
                        ← Back to Registration Options
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-4 text-center text-gray-600 text-sm">
        <p>&copy; 2024 PWD Affair Office - LGU Malasiqui. All rights reserved.</p>
        <p class="mt-1">Automated Community and Citizen E-Records Service System (ACCESS)</p>
    </footer>

    <script>
        // Form switching logic
        const loginForm = document.getElementById('loginForm');
        const registerOptions = document.getElementById('registerOptions');
        const clientRegisterForm = document.getElementById('clientRegisterForm');
        const subadminRegisterForm = document.getElementById('subadminRegisterForm');
        
        const showRegisterOptionsBtn = document.getElementById('showRegisterOptions');
        const clientRegisterBtn = document.getElementById('clientRegisterBtn');
        const subadminRegisterBtn = document.getElementById('subadminRegisterBtn');
        
        const backToLoginBtn = document.getElementById('backToLogin');
        const backToRegisterOptions1Btn = document.getElementById('backToRegisterOptions1');
        const backToRegisterOptions2Btn = document.getElementById('backToRegisterOptions2');
        
        function hideAllForms() {
            loginForm.classList.add('hidden');
            registerOptions.classList.add('hidden');
            clientRegisterForm.classList.add('hidden');
            subadminRegisterForm.classList.add('hidden');
        }
        
        showRegisterOptionsBtn.addEventListener('click', () => {
            hideAllForms();
            registerOptions.classList.remove('hidden');
        });
        
        clientRegisterBtn.addEventListener('click', () => {
            hideAllForms();
            clientRegisterForm.classList.remove('hidden');
        });
        
        subadminRegisterBtn.addEventListener('click', () => {
            hideAllForms();
            subadminRegisterForm.classList.remove('hidden');
        });
        
        backToLoginBtn.addEventListener('click', () => {
            hideAllForms();
            loginForm.classList.remove('hidden');
        });
        
        backToRegisterOptions1Btn.addEventListener('click', () => {
            hideAllForms();
            registerOptions.classList.remove('hidden');
        });
        
        backToRegisterOptions2Btn.addEventListener('click', () => {
            hideAllForms();
            registerOptions.classList.remove('hidden');
        });
        
        // Password strength indicator
        document.querySelector('input[name="password"]').addEventListener('input', function() {
            const password = this.value;
            const requirements = [
                password.length >= 8,
                /[A-Z]/.test(password),
                /[a-z]/.test(password),
                /[0-9]/.test(password),
                /[^A-Za-z0-9]/.test(password)
            ];
            
            const strength = requirements.filter(req => req).length;
            let color = '#ef4444'; // red
            let text = 'Weak';
            
            if (strength >= 4) {
                color = '#10b981'; // green
                text = 'Strong';
            } else if (strength >= 3) {
                color = '#f59e0b'; // yellow
                text = 'Medium';
            }
            
            // You can add a strength indicator here if needed
        });
        
        // File upload validation
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const maxSize = 500 * 1024 * 1024; // 500MB
                    if (file.size > maxSize) {
                        alert('File size must be less than 500MB');
                        this.value = '';
                    }
                }
            });
        });
    </script>
</body>
</html>