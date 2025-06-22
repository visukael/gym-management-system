<?php

require_once __DIR__ . '/../models/Attendance.php';

class AttendanceController {
    private $attendanceModel;

    public function __construct($conn) {
        $this->attendanceModel = new Attendance($conn);
    }

    public function recordAttendance($memberId, $userId) {
        if ($memberId <= 0) {
            return ['success' => false, 'message' => "Invalid member ID."];
        }

        $result = $this->attendanceModel->create($memberId, $userId);

        if ($result) {
            return ['success' => true, 'message' => "Attendance recorded successfully for member ID: " . $memberId];
        } else {
            return ['success' => false, 'message' => "Error recording attendance or member already checked in today."];
        }
    }

    public function getAttendanceForView($getParams) {
        $selectedDate = $getParams['date'] ?? date('Y-m-d');
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $activeMembers = $this->attendanceModel->getActiveMembers();
        $attendances = $this->attendanceModel->getByDate($selectedDate);

        return [
            'activeMembers' => $activeMembers,
            'attendances' => $attendances,
            'selectedDate' => $selectedDate
        ];
    }
}