<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.html');
    exit();
}

$admission_no = trim($_POST['admission_no'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$school = trim($_POST['school'] ?? '');
$course = trim($_POST['course'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

$errors = [];
if ($admission_no === '') {
    $errors[] = 'Admission number is required.';
}
if ($full_name === '') {
    $errors[] = 'Full name is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required.';
}
if ($phone === '') {
    $errors[] = 'Phone number is required.';
}
if ($school === '') {
    $errors[] = 'School/Faculty is required.';
}
if ($course === '') {
    $errors[] = 'Course/Program is required.';
}
if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}
if ($password !== $password_confirm) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    echo "<script>alert('" . implode('\\n', $errors) . "'); window.location.href='register.html';</script>";
    exit();
}

$stmt = $conn->prepare('SELECT id FROM users WHERE admission_no = ? OR email = ? LIMIT 1');
if (! $stmt) {
    echo "<script>alert('Database error. Please try again later.'); window.location.href='register.html';</script>";
    exit();
}
$stmt->bind_param('ss', $admission_no, $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    echo "<script>alert('Admission number or email already registered. Please login or use different credentials.'); window.location.href='login.html';</script>";
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$insert = $conn->prepare('INSERT INTO users (username, password, full_name, admission_no, email, phone, school, course, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (! $insert) {
    echo "<script>alert('Unable to create account. Please try again.'); window.location.href='register.html';</script>";
    exit();
}
$username = $admission_no;
$role = 'student';
$insert->bind_param('sssssssss', $username, $hashed_password, $full_name, $admission_no, $email, $phone, $school, $course, $role);
if (! $insert->execute()) {
    echo "<script>alert('Registration failed. Please try again.'); window.location.href='register.html';</script>";
    exit();
}

$studentsInsert = $conn->prepare('INSERT INTO student (AdmissionNo, StudentName, Course, PhoneNumber) VALUES (?, ?, ?, ?)');
if ($studentsInsert) {
    $studentsInsert->bind_param('ssss', $admission_no, $full_name, $course, $phone);
    $studentsInsert->execute();
}

echo "<script>alert('Registration successful! Please login with your admission number and password.'); window.location.href='login.html';</script>";
exit();
