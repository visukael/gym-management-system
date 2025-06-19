<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;

    public function __construct($conn) {
        $this->userModel = new User($conn);
    }

    public function login($username, $password) {
        $user = $this->userModel->findByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }

        return false;
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    public function registerOwner($data) {
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        return $this->userModel->create($data['name'], $data['username'], $hashedPassword, 'owner');
    }
}
