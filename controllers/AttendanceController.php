<?php

require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Member.php';

class AttendanceController {
    private $attendanceModel;
    private $memberModel;

    public function __construct($conn) {
        $this->attendanceModel = new Attendance($conn);
        $this->memberModel = new Member($conn);
    }

    public function recordAttendance($memberId, $userId) {
        $member_data = $this->memberModel->getById($memberId);
        if (!$member_data || $member_data['status'] !== 'active') {
            return ['success' => false, 'message' => "Member is not found or not active."];
        }

        $result = $this->attendanceModel->create($memberId, $userId);

        if ($result) {
            return ['success' => true, 'message' => "Attendance recorded successfully for member: " . htmlspecialchars($member_data['full_name'])];
        } else {
            return ['success' => false, 'message' => "Error recording attendance or member already checked in today."];
        }
    }

    public function handleDeleteAttendance($attendanceId) {
        if ($this->attendanceModel->delete($attendanceId)) {
            return ['success' => true, 'message' => 'Attendance record deleted successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete attendance record.'];
        }
    }

    public function getAttendanceForView($getParams) {
        $selectedDate = $getParams['date'] ?? date('Y-m-d');
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $limit = 50;
        $page = isset($getParams['page']) ? (int)$getParams['page'] : 1;
        $offset = ($page - 1) * $limit;

        $attendances = $this->attendanceModel->getByDateWithPagination($selectedDate, $limit, $offset);
        $totalAttendance = $this->attendanceModel->getTotalAttendanceByDate($selectedDate);
        $totalPages = ceil($totalAttendance / $limit);

        return [
            'attendances' => $attendances,
            'selectedDate' => $selectedDate,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalAttendance
        ];
    }

    public function searchMembersAjax($searchTerm) {
        return $this->attendanceModel->searchActiveMembers($searchTerm);
    }
}