<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';

$userRoleForAccess = $_SESSION['user_role'] ?? '';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRoleDisplay = htmlspecialchars(ucfirst($userRoleForAccess));

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
    header('Location: ../../login.php');
    exit;
}

$userModel = new User($conn);

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $name = htmlspecialchars(trim($_POST['name']));
        $username = htmlspecialchars(trim($_POST['username']));
        $password = $_POST['password'];
        $role = htmlspecialchars(trim($_POST['role']));

        if (empty($name) || empty($username) || empty($password) || empty($role)) {
            $_SESSION['error_message'] = "Please fill all required fields for adding a user.";
        } else {
            if ($_SESSION['user_role'] === 'admin' && $role === 'owner') {
                $_SESSION['error_message'] = "Admin cannot create an 'owner' account.";
            } else {
                if ($userModel->findByUsername($username)) { 
                    $_SESSION['error_message'] = "Username already exists. Please choose a different one.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    if ($userModel->create($name, $username, $hashed_password, $role)) {
                        $_SESSION['success_message'] = "User '" . $name . "' added successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error adding user.";
                    }
                }
            }
        }
    } elseif (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $name = htmlspecialchars(trim($_POST['name']));
        $role = htmlspecialchars(trim($_POST['role']));
        $new_password = $_POST['new_password'] ?? ''; 

        if (empty($name) || empty($role)) {
            $_SESSION['error_message'] = "Please fill all required fields for updating a user.";
        } else {
            $currentUser = $userModel->getById($id);
            if (!$currentUser) {
                $_SESSION['error_message'] = "User not found for update.";
                header("Location: user.php");
                exit;
            }

            if ($_SESSION['user_role'] === 'admin' && $role === 'owner' && $currentUser['id'] !== $_SESSION['user_id']) {
                $_SESSION['error_message'] = "Admin cannot change another user's role to 'owner'.";
                header("Location: user.php");
                exit;
            }
            if ($id === $_SESSION['user_id'] && $_SESSION['user_role'] === 'owner' && $role !== 'owner') {
                 $_SESSION['error_message'] = "You cannot demote your own 'owner' account.";
                 header("Location: user.php");
                 exit;
            }

            $updateSuccess = $userModel->update($id, $name, $currentUser['username'], $role);

            if (!empty($new_password)) {
                $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
                $passwordUpdateSuccess = $userModel->updatePassword($id, $hashed_new_password);
                if (!$passwordUpdateSuccess) {
                    $updateSuccess = false;
                    error_log("Failed to update password for user ID: " . $id);
                }
            }

            if ($updateSuccess) {
                $_SESSION['success_message'] = "User '" . $name . "' updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating user.";
            }
        }
    }
    header("Location: user.php");
    exit;
}

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editUser = $userModel->getById($editId);
    if (!$editUser) {
        $_SESSION['error_message'] = "User not found.";
        header("Location: user.php");
        exit;
    }
    if ($_SESSION['user_role'] === 'admin' && $editUser['role'] === 'owner' && $editUser['id'] !== $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You do not have permission to edit this user.";
        header("Location: user.php");
        exit;
    }
}

if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    if ($deleteId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
    } else {
        $userToDelete = $userModel->getById($deleteId);
        if ($userToDelete && $userToDelete['role'] === 'owner' && $_SESSION['user_role'] === 'admin') {
            $_SESSION['error_message'] = "Admin cannot delete an 'owner' account.";
        } else {
            if ($userModel->delete($deleteId)) {
                $_SESSION['success_message'] = "User deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting user. User might have related data (e.g., created attendance/transactions).";
            }
        }
    }
    header("Location: user.php");
    exit;
}

$users = $userModel->getAll();

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRoleDisplay = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Gym Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="../../assets/css/style.css"> </head>

<body class="bg-gray-50 text-gray-900">
    <div class="flex h-screen overflow-hidden">
        <div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64 border-r border-gray-200 bg-white">
        <div class="flex items-center justify-center h-16 px-4 border-b border-gray-200">
            <div class="flex items-center">
                <span class="ml-2 text-xl font-bold text-red-600">FORZA</span>
            </div>
        </div>
        <div class="flex flex-col flex-grow px-4 py-4 overflow-y-auto">
            <nav class="flex-1 space-y-2">
                <a href="../dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Dashboard
                </a>
                <a href="../members/members.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'active' : '' ?>">
                    <i data-lucide="users" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Members
                </a>
                <a href="../products/products.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : '' ?>">
                    <i data-lucide="shopping-bag" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Products
                </a>
                <a href="../transactions/transactions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'transactions.php') ? 'active' : '' ?>">
                    <i data-lucide="credit-card" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Transactions
                </a>
                <a href="../attendance/attendance.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : '' ?>">
                    <i data-lucide="calendar-check" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Attendance
                </a>
                <a href="../promotions/promotions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'promotions.php') ? 'active' : '' ?>">
                    <i data-lucide="percent" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Promotions
                </a>
                
                <?php if ($userRoleForAccess === 'owner'): ?>
                <a href="user.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'user.php') ? 'active' : '' ?>">
                    <i data-lucide="settings" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Manage Users
                </a>
                <?php endif; ?>

                <a href="../settings/setting.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'setting.php') ? 'active' : '' ?>">
                    <i data-lucide="user" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    My Profile
                </a>
                <a href="../../logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                    <i data-lucide="log-out" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                    Logout
                </a>
            </nav>
            <div class="mt-auto">
                <a href="../settings/setting.php" class="flex items-center p-4 mt-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                    <div class="flex-shrink-0">
                        <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=ef4444&color=fff" alt="Profile">
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900"><?= $userName ?></p>
                        <p class="text-xs text-gray-500"><?= $userRoleDisplay ?></p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

        <div class="flex flex-col flex-1 overflow-hidden">
            <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 bg-white">
                <div class="flex items-center md:hidden">
                    <button class="text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 rounded-md p-1" onclick="toggleSidebar()">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
                <div class="flex items-center space-x-4 ml-auto">
                    <button class="p-2 text-gray-500 rounded-full hover:bg-gray-100 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                    </button>
                    <img class="w-8 h-8 rounded-full cursor-pointer hover:shadow-md transition-shadow" src="https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=ef4444&color=fff" alt="Profile">
                </div>
            </div>

            <div class="flex-1 overflow-auto p-4 md:p-6 bg-gray-50">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Manage User Accounts</h1>
                        <p class="text-gray-500">Create, update, and manage access for your gym staff.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <h2 class="text-lg font-medium text-gray-800 mb-4"><?= $editUser ? 'Edit User Account' : 'Add New User Account' ?></h2>
                    <form method="POST" action="user.php" class="space-y-4">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                        <?php endif; ?>

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" name="name" id="name" required value="<?= htmlspecialchars($editUser['name'] ?? '') ?>"
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" name="username_display" id="username_display" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>"
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 cursor-not-allowed sm:text-sm" readonly>
                            <input type="hidden" name="username" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>"> </div>

                        <?php if (!$editUser): ?>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input type="password" name="password" id="password" required
                                    class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            </div>
                        <?php else: ?>
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password (Leave blank to keep current)</label>
                                <input type="password" name="new_password" id="new_password"
                                    class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" id="role" required
                                class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                <option value="staff" <?= (isset($editUser) && $editUser['role'] == 'staff') ? 'selected' : '' ?>>Staff</option>
                                <option value="admin" <?= (isset($editUser) && $editUser['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                <?php if ($_SESSION['user_role'] === 'owner'): ?>
                                    <option value="owner" <?= (isset($editUser) && $editUser['role'] == 'owner') ? 'selected' : '' ?>>Owner</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-center space-x-3 pt-4">
                            <button type="submit" name="<?= $editUser ? 'update_user' : 'add_user' ?>" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i data-lucide="<?= $editUser ? 'save' : 'user-plus' ?>" class="w-4 h-4 mr-2"></i> <?= $editUser ? 'Update User' : 'Add User' ?>
                            </button>
                            <?php if ($editUser): ?>
                                <a href="user.php" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">User Accounts List</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr class="table-row-hover" id="user-<?= $u['id'] ?>">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($u['name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($u['username']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                    <?php if ($_SESSION['user_role'] === 'owner' || ($_SESSION['user_role'] === 'admin' && $u['role'] === 'staff')): // Owner can edit all, Admin can edit staff ?>
                                                        <a href="?edit=<?= $u['id'] ?>" class="action-link text-blue-600 hover:text-blue-900 mr-3">
                                                            <i data-lucide="edit" class="w-4 h-4 inline-block align-text-bottom"></i> Edit
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($_SESSION['user_role'] === 'owner' || ($_SESSION['user_role'] === 'admin' && $u['role'] === 'staff')): // Owner can delete all, Admin can delete staff ?>
                                                        <button onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>', '<?= htmlspecialchars($u['role']) ?>')" class="action-link text-red-600 hover:text-red-900">
                                                            <i data-lucide="trash-2" class="w-4 h-4 inline-block align-text-bottom"></i> Delete
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">(No actions)</span>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <span class="text-gray-500">(Your account)</span>
                                                <?php endif ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <script>
        lucide.createIcons();

        function toggleSidebar() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('absolute');
            sidebar.classList.toggle('inset-y-0');
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('z-40');
        }

        function confirmDelete(id, name, role) {
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                if (role === 'owner') {
                    showToast('Admin cannot delete an Owner account.', 'error');
                    return false;
                }
            <?php endif; ?>

            if (confirm(`Are you sure you want to delete user "${name}" (ID: ${id})? This action cannot be undone.`)) {
                window.location.href = `user.php?delete=${id}`;
            }
        }

        const toastContainer = document.getElementById('toastContainer');

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.textContent = message;
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3500);
        }

        <?php if ($success_message): ?>
            showToast('<?= $success_message ?>', 'success');
        <?php endif; ?>
        <?php if ($error_message): ?>
            showToast('<?= $error_message ?>', 'error');
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const currentUserRole = '<?= $_SESSION['user_role'] ?>';
            const editingUserId = '<?= $editUser['id'] ?? '' ?>';
            const loggedInUserId = '<?= $_SESSION['user_id'] ?>';

            const usernameDisplay = document.getElementById('username_display');
            if (usernameDisplay) {
                usernameDisplay.setAttribute('readonly', true);
                usernameDisplay.classList.add('bg-gray-100', 'cursor-not-allowed');
            }
            
            if (roleSelect && currentUserRole === 'admin') {
                const ownerOption = roleSelect.querySelector('option[value="owner"]');
                if (ownerOption) {
                    ownerOption.remove();
                }

                if (editingUserId && editingUserId === loggedInUserId) {
                    roleSelect.setAttribute('disabled', true);
                    roleSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
                } else if (editingUserId) {
                    const originalRoleOfEditedUser = '<?= $editUser['role'] ?? '' ?>';
                    Array.from(roleSelect.options).forEach(option => {
                        if (option.value === 'owner' || option.value === 'admin') {
                            option.remove();
                        }
                    });
                    if (originalRoleOfEditedUser === 'staff') {
                         roleSelect.value = 'staff';
                    } else if (originalRoleOfEditedUser === 'admin') {
                         roleSelect.setAttribute('disabled', true);
                         roleSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
                         roleSelect.value = 'admin';
                    }
                }
            } else if (roleSelect && currentUserRole === 'owner') {
                if (editingUserId && editingUserId === loggedInUserId) {
                    roleSelect.setAttribute('disabled', true);
                    roleSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
                }
            }
        });
    </script>
</body>

</html>