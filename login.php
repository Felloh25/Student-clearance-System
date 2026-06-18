<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    echo "<script>alert('Please enter both username/admission number and password.'); window.location.href='login.html';</script>";
    exit();
}

$stmt = $conn->prepare('SELECT * FROM users WHERE username = ? OR admission_no = ? LIMIT 1');
if (! $stmt) {
    echo "<script>alert('Database error. Please try again.'); window.location.href='login.html';</script>";
    exit();
}

$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if (! $result || $result->num_rows !== 1) {
    echo "<script>alert('Wrong username or password.'); window.location.href='login.html';</script>";
    exit();
}

$user = $result->fetch_assoc();

if (! password_verify($password, $user['password'])) {
    echo "<script>alert('Wrong username or password.'); window.location.href='login.html';</script>";
    exit();
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['admission_no'] = $user['admission_no'] ?? '';

if (! empty($user['department_id'])) {
    $_SESSION['department_id'] = $user['department_id'];
}

if ($user['role'] === 'student') {
    $admission = $user['admission_no'] ?? '';
    if ($admission !== '') {
        $studentStmt = $conn->prepare('SELECT AdmissionNo FROM student WHERE AdmissionNo = ? LIMIT 1');
        if ($studentStmt) {
            $studentStmt->bind_param('s', $admission);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            if (!($studentResult && $studentResult->num_rows === 1)) {
                $insertStudent = $conn->prepare('INSERT INTO student (AdmissionNo, StudentName, Course, PhoneNumber) VALUES (?, ?, ?, ?)');
                if ($insertStudent) {
                    $studentName = $user['full_name'] ?? '';
                    $studentCourse = $user['course'] ?? '';
                    $studentPhone = $user['phone'] ?? '';
                    $insertStudent->bind_param('ssss', $admission, $studentName, $studentCourse, $studentPhone);
                    $insertStudent->execute();
                }
            }
        }
    }
}

if ($user['role'] === 'department') {
    header('Location: dashboard.php#department');
} elseif ($user['role'] === 'admin') {
    header('Location: dashboard.php#admin');
} else {
    header('Location: dashboard.php#clearance');
}
exit();
