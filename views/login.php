<?php
session_start();

require_once '../config/database.php';
require_once '../models/User.php';
require_once '../controllers/AuthController.php'; 

$auth = new AuthController($conn);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($auth->login($username, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gym Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
        }
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.3);
        }
        .shake {
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden transition-all duration-300 transform hover:scale-[1.01]">
            <div class="p-8">
                <div class="flex justify-center mb-8">
                    <div class="bg-red-100 p-4 rounded-full">
                        <i class="fas fa-dumbbell text-red-600 text-3xl"></i>
                    </div>
                </div>
                
                <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">Welcome Back</h1>
                <p class="text-gray-600 text-center mb-8">Sign in to your Gym Management account</p>
                
                <?php if (!empty($error)): ?>
                    <div id="error-message" class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded flex items-center shake">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required 
                                class="input-focus pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-red-500 focus:outline-none transition duration-300"
                                placeholder="Enter your username">
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required 
                                class="input-focus pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-indigo-500 focus:outline-none transition duration-300"
                                placeholder="Enter your password">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                        <span>Login</span>
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </form>
            </div>
            
            <div class="bg-gray-50 px-8 py-4 rounded-b-2xl">
                <p class="text-xs text-gray-500 text-center">
                    © 2024 Forza Management.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        const errorMessage = document.getElementById('error-message');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0';
                setTimeout(() => {
                    errorMessage.remove();
                }, 300);
            }, 5000);
        }

        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                if (!this.readOnly && !this.disabled) {
                    this.closest('.relative').classList.add('ring-2', 'ring-red-300', 'rounded-lg');
                }
            });
            
            input.addEventListener('blur', function() {
                this.closest('.relative').classList.remove('ring-2', 'ring-red-300', 'rounded-lg');
            });
        });
    </script>
</body>
</html>