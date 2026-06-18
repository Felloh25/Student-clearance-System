<?php
session_start();
include 'db_connect.php';

function getDepartmentFieldNames($conn) {
    $fields = [];
    $result = $conn->query('SHOW COLUMNS FROM department');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fields[$row['Field']] = true;
        }
    }

    return [
        'id' => isset($fields['id']) ? 'id' : (isset($fields['DepartmentID']) ? 'DepartmentID' : 'id'),
        'name' => isset($fields['name']) ? 'name' : (isset($fields['DepartmentName']) ? 'DepartmentName' : 'name'),
        'code' => isset($fields['code']) ? 'code' : (isset($fields['DepartmentCode']) ? 'DepartmentCode' : null),
    ];
}

function generateRecordID($conn) {
    $prefix = 'REC';
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(RecordID, 4) AS UNSIGNED)) AS max_num FROM clearancerecord WHERE RecordID REGEXP '^REC[0-9]+$'");
    $nextNumber = 1;
    if ($result) {
        $row = $result->fetch_assoc();
        if (isset($row['max_num']) && $row['max_num'] !== null) {
            $nextNumber = (int) $row['max_num'] + 1;
        }
    }

    do {
        $recordId = sprintf('%s%06d', $prefix, $nextNumber);
        $nextNumber++;
        $stmt = $conn->prepare('SELECT 1 FROM clearancerecord WHERE RecordID = ? LIMIT 1');
        if (! $stmt) {
            break;
        }
        $stmt->bind_param('s', $recordId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $recordId;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'new_request') {
    header('Location: dashboard.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$academicYear = trim($_POST['academic_year'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if ($userId <= 0 || $reason === '' || $academicYear === '') {
    echo "<script>alert('Please provide all required clearance information.'); window.location.href='dashboard.php';</script>";
    exit();
}

$stmtUser = $conn->prepare('SELECT admission_no FROM users WHERE id = ? LIMIT 1');
if (! $stmtUser) {
    echo "<script>alert('Unable to retrieve student information. Please try again later.'); window.location.href='dashboard.php';</script>";
    exit();
}
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$userResult = $stmtUser->get_result();
$userRow = $userResult ? $userResult->fetch_assoc() : null;
$admissionNo = trim($userRow['admission_no'] ?? '');
if ($admissionNo === '') {
    echo "<script>alert('Admission number not found. Please contact the administrator.'); window.location.href='dashboard.php';</script>";
    exit();
}

$existingCheck = $conn->prepare('SELECT COUNT(*) AS existing FROM clearancerecord WHERE AdmissionNo = ? AND academic_year = ? AND ClearanceStatus = "Pending"');
if (!$existingCheck) {
    echo "<script>alert('Unable to validate your request. Please try again later.'); window.location.href='dashboard.php';</script>";
    exit();
}
$existingCheck->bind_param('ss', $admissionNo, $academicYear);
$existingCheck->execute();
$existingResult = $existingCheck->get_result();
$existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
if ($existingRow && (int) $existingRow['existing'] > 0) {
    echo "<script>alert('You already have a pending clearance request for this academic year.'); window.location.href='dashboard.php';</script>";
    exit();
}

$departments = [];
$columns = getDepartmentFieldNames($conn);
$deptResult = mysqli_query($conn, 'SELECT ' . $columns['id'] . ' AS id FROM department ORDER BY ' . $columns['name']);
if ($deptResult) {
    while ($deptRow = mysqli_fetch_assoc($deptResult)) {
        $deptId = trim((string) ($deptRow['id'] ?? ''));
        if ($deptId !== '') {
            $departments[] = $deptId;
        }
    }
}

if (empty($departments)) {
    $defaults = [
        ['code' => 'LIB', 'name' => 'Library'],
        ['code' => 'FIN', 'name' => 'Finance'],
        ['code' => 'REG', 'name' => 'Registry'],
        ['code' => 'SPORTS', 'name' => 'Sports'],
        ['code' => 'DIN', 'name' => 'Dining'],
        ['code' => 'WORK', 'name' => 'Workshop'],
        ['code' => 'HOUSE', 'name' => 'Housekeeping'],
        ['code' => 'SCH', 'name' => 'School'],
    ];
    $fields = [$columns['name']];
    $placeholders = ['?'];
    $types = 's';
    if ($columns['code']) {
        $fields[] = $columns['code'];
        $placeholders[] = '?';
        $types .= 's';
    }
    $query = 'INSERT INTO department (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $insertDept = $conn->prepare($query);
    if ($insertDept) {
        foreach ($defaults as $dept) {
            if ($columns['code']) {
                $insertDept->bind_param($types, $dept['name'], $dept['code']);
            } else {
                $insertDept->bind_param($types, $dept['name']);
            }
            $insertDept->execute();
        }
        $insertDept->close();
    }
    $departments = [];
    $deptResult = mysqli_query($conn, 'SELECT ' . $columns['id'] . ' AS id FROM department ORDER BY ' . $columns['name']);
    if ($deptResult) {
        while ($deptRow = mysqli_fetch_assoc($deptResult)) {
            $deptId = trim((string) ($deptRow['id'] ?? ''));
            if ($deptId !== '') {
                $departments[] = $deptId;
            }
        }
    }
}

if (empty($departments)) {
    echo "<script>alert('No departments are configured yet. Please contact the administrator.'); window.location.href='dashboard.php';</script>";
    exit();
}

$conn->begin_transaction();
try {
    $insertStmt = $conn->prepare('INSERT INTO clearancerecord (RecordID, AdmissionNo, DepartmentID, Reason, academic_year, ClearanceStatus, request_comment, ClearanceDate) VALUES (?, ?, ?, ?, ?, "Pending", ?, NOW())');
    if (! $insertStmt) {
        throw new Exception('Failed to prepare clearance request');
    }

    foreach ($departments as $deptId) {
        $recordId = generateRecordID($conn);
        $insertStmt->bind_param('ssssss', $recordId, $admissionNo, $deptId, $reason, $academicYear, $comment);
        if (! $insertStmt->execute()) {
            throw new Exception('Failed to create clearance record for department ' . $deptId);
        }
    }

    $conn->commit();
    echo "<script>alert('Your clearance request has been submitted successfully.'); window.location.href='dashboard.php';</script>";
    exit();
} catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='dashboard.php';</script>";
    exit();
}
