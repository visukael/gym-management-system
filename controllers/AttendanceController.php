<?php
require_once __DIR__ . '/../models/Attendance.php';

class AttendanceController {
    private $model;

    public function __construct($conn) {
        $this->model = new Attendance($conn);
    }

    public function record($member_id, $user_id) {
        return $this->model->create($member_id, $user_id);
    }

    public function all() {
        return $this->model->getAll();
    }

    public function byDate($date) {
        return $this->model->getByDate($date);
    }
}
