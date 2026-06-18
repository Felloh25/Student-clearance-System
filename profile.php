<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

function fetchProfile($conn, $userId) {
    $stmt = $conn->prepare(
        'SELECT u.full_name, u.email, u.phone, u.school, u.course, u.gender, u.year_of_study, u.admission_no,
                s.StudentName AS student_name, s.Course AS student_course, s.PhoneNumber AS student_phone
         FROM users u
         LEFT JOIN student s ON u.admission_no = s.AdmissionNo
         WHERE u.id = ? LIMIT 1'
    );
    if (! $stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (! $result || $result->num_rows !== 1) {
        return null;
    }
    $row = $result->fetch_assoc();
    $row['course'] = $row['student_course'] ?: $row['course'];
    $row['phone'] = $row['student_phone'] ?: $row['phone'];
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $school = trim($_POST['school'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $year = trim($_POST['year_of_study'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($full_name === '' || $email === '') {
        $errorMessage = 'Please provide your full name and valid email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please provide a valid email address.';
    } else {
        $updateUser = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, school = ?, course = ?, gender = ?, year_of_study = ? WHERE id = ?');
        if ($updateUser) {
            $yearValue = $year !== '' ? (int) $year : null;
            $updateUser->bind_param('ssssssii', $full_name, $email, $phone, $school, $course, $gender, $yearValue, $userId);
            $updateUser->execute();
        }

        $profile = fetchProfile($conn, $userId);
        $admissionNo = $profile['admission_no'] ?? '';
        if ($admissionNo !== '') {
            $stmtStudent = $conn->prepare('SELECT AdmissionNo FROM student WHERE AdmissionNo = ? LIMIT 1');
            if ($stmtStudent) {
                $stmtStudent->bind_param('s', $admissionNo);
                $stmtStudent->execute();
                $resultStudent = $stmtStudent->get_result();
                $studentName = $full_name;
                $studentCourse = $course;
                $studentPhone = $phone;
                if ($resultStudent && $resultStudent->num_rows === 1) {
                    $updateStudent = $conn->prepare('UPDATE student SET StudentName = ?, Course = ?, PhoneNumber = ? WHERE AdmissionNo = ?');
                    if ($updateStudent) {
                        $updateStudent->bind_param('ssss', $studentName, $studentCourse, $studentPhone, $admissionNo);
                        $updateStudent->execute();
                    }
                } else {
                    $insertStudent = $conn->prepare('INSERT INTO student (AdmissionNo, StudentName, Course, PhoneNumber) VALUES (?, ?, ?, ?)');
                    if ($insertStudent) {
                        $insertStudent->bind_param('ssss', $admissionNo, $studentName, $studentCourse, $studentPhone);
                        $insertStudent->execute();
                    }
                }
            }
        }

        if ($password !== '') {
            if (strlen($password) < 7) {
                $errorMessage = 'Password must be at least 7 characters if you choose to update it.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtPass = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                if ($stmtPass) {
                    $stmtPass->bind_param('si', $hashedPassword, $userId);
                    $stmtPass->execute();
                }
            }
        }

        if ($errorMessage === '') {
            $_SESSION['full_name'] = $full_name;
            $successMessage = 'Profile updated successfully.';
        }
    }
}

$profile = fetchProfile($conn, $userId);
if (! $profile) {
    $profile = ['full_name' => '', 'email' => '', 'phone' => '', 'school' => '', 'course' => '', 'gender' => '', 'year_of_study' => '', 'admission_no' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Clearance System</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="images/LOGO.jpeg?v=2" alt="Logo" class="logo">
                <h1>Clearance</h1>
            </div>
            <div class="nav-title">Navigation</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="profile.php" class="active">My Profile</a></li>
                <li><a href="logout.php" class="logout-btn">Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <div class="top-nav">
                    <div class="site-title">My Profile</div>
                    <div class="user-actions">
                        <span><?php echo ucfirst(htmlspecialchars($_SESSION['role'] ?? 'student')); ?></span>
                        <a href="logout.php" class="btn btn-ghost">Logout</a>
                    </div>
                </div>
                <p class="section-subtitle">Update your student details and password below.</p>
            </header>

            <section class="content-panel profile-page">
                <div class="profile-card">
                    <?php if ($successMessage): ?>
                        <div class="success-message" style="display:block; margin-bottom:16px;"><?php echo htmlspecialchars($successMessage); ?></div>
                    <?php endif; ?>
                    <?php if ($errorMessage): ?>
                        <div class="error-message" style="display:block; margin-bottom:16px;"><?php echo htmlspecialchars($errorMessage); ?></div>
                    <?php endif; ?>

                    <div class="profile-header">
                        <div class="profile-avatar">
                            <img src="images/LOGO.jpeg?v=2" alt="Profile Photo">
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($profile['full_name'] ?: 'Student Name'); ?></h2>
                            <p><strong>Admission No:</strong> <?php echo htmlspecialchars($profile['admission_no'] ?? ''); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email'] ?? ''); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone'] ?? ''); ?></p>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($profile['course'] ?? ''); ?></p>
                            <a href="#editProfileForm" class="btn btn-primary" style="margin-top: 16px; display: inline-flex;">Edit Profile</a>
                        </div>
                    </div>

                    <form id="editProfileForm" action="profile.php" method="POST" class="modal-content" style="padding:24px;">
                        <div class="modal-body" style="grid-template-columns:1fr 1fr;">
                            <div class="form-group">
                                <label for="input_full_name">Full Name</label>
                                <input id="input_full_name" name="full_name" type="text" class="input" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="input_email">Email</label>
                                <input id="input_email" name="email" type="email" class="input" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="input_phone">Phone</label>
                                <input id="input_phone" name="phone" type="text" class="input" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="input_course">Course</label>
                                <input id="input_course" name="course" type="text" class="input" value="<?php echo htmlspecialchars($profile['course']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="input_school">School / Faculty</label>
                                <input id="input_school" name="school" type="text" class="input" value="<?php echo htmlspecialchars($profile['school']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="input_gender">Gender</label>
                                <select id="input_gender" name="gender" class="input">
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo $profile['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $profile['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $profile['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="input_year">Year of Study</label>
                                <input id="input_year" name="year_of_study" type="number" class="input" min="1" max="10" value="<?php echo htmlspecialchars($profile['year_of_study']); ?>">
                            </div>
                            <div class="form-group full">
                                <label for="input_password">New Password</label>
                                <input id="input_password" name="password" type="password" class="input" placeholder="Leave blank to keep current password">
                            </div>
                        </div>
                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
    <footer class="site-footer">
        <p>Student Clearance System © 2026.</p>
    </footer>
</body>
</html>
