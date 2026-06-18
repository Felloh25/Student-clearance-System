<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'student';
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$departmentId = isset($_SESSION['department_id']) ? (string) $_SESSION['department_id'] : null;

function generateDepartmentId($name) {
    $id = preg_replace('/[^A-Z0-9]/', '', strtoupper(substr($name, 0, 10)));
    if ($id === '') {
        $id = 'DEPT';
    }
    return $id;
}

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

function getDepartmentCodeByNumericId($conn, $numericId) {
    if (!is_numeric($numericId)) {
        return $numericId;
    }
    
    $mapping = [
        1 => 'LIB',
        2 => 'FIN',
        3 => 'SPORTS',
        4 => 'HOUSE',
        5 => 'REG',
        6 => 'DIN',
        7 => 'WORK',
        8 => 'SCH',
        9 => 'ALUM',
    ];
    
    $numericId = (int) $numericId;
    return isset($mapping[$numericId]) ? $mapping[$numericId] : null;
}

function ensureDefaultDepartments($conn) {
    $columns = getDepartmentFieldNames($conn);
    $defaults = [
        'LIB' => 'Library',
        'FIN' => 'Finance',
        'REG' => 'Registry',
        'SPORTS' => 'Sports',
        'DIN' => 'Dining',
        'WORK' => 'Workshop',
        'HOUSE' => 'Housekeeping',
        'SCH' => 'School',
    ];
    $existingNames = [];
    $result = $conn->query('SELECT ' . $columns['name'] . ' AS name FROM department');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existingNames[$row['name']] = true;
        }
    }

    $fields = [$columns['name']];
    $placeholders = ['?'];
    $types = 's';
    if ($columns['code']) {
        $fields[] = $columns['code'];
        $placeholders[] = '?';
        $types .= 's';
    }
    $query = 'INSERT INTO department (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($query);
    if ($stmt) {
        foreach ($defaults as $code => $name) {
            if (! isset($existingNames[$name])) {
                if ($columns['code']) {
                    $stmt->bind_param($types, $name, $code);
                } else {
                    $stmt->bind_param($types, $name);
                }
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

function fetchDepartments($conn) {
    ensureDefaultDepartments($conn);
    $columns = getDepartmentFieldNames($conn);
    $sql = 'SELECT ' . $columns['id'] . ' AS id, ' . $columns['name'] . ' AS name FROM department ORDER BY ' . $columns['name'];
    $result = mysqli_query($conn, $sql);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function getStudentFieldNames($conn) {
    $fields = [];
    $result = $conn->query('SHOW COLUMNS FROM student');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fields[strtolower($row['Field'])] = $row['Field'];
        }
    }

    return [
        'user_id' => $fields['user_id'] ?? null,
        'admission_no' => $fields['admissionno'] ?? ($fields['admission_no'] ?? null),
        'student_name' => $fields['studentname'] ?? ($fields['student_name'] ?? null),
        'course' => $fields['course'] ?? null,
        'year_of_study' => $fields['year_of_study'] ?? null,
        'school' => $fields['school'] ?? null,
        'phone' => $fields['phonenumber'] ?? ($fields['phone'] ?? null),
    ];
}

function fetchStudentProfile($conn, $userId) {
    $studentColumns = getStudentFieldNames($conn);
    $selectFields = [
        'u.admission_no',
        'u.full_name',
        'u.email',
        'u.phone',
        'u.school',
        'u.course',
        'u.gender',
        'u.year_of_study',
    ];

    if ($studentColumns['student_name']) {
        $selectFields[] = 's.' . $studentColumns['student_name'] . ' AS student_name';
    }
    if ($studentColumns['course']) {
        $selectFields[] = 's.' . $studentColumns['course'] . ' AS student_course';
    }
    if ($studentColumns['year_of_study']) {
        $selectFields[] = 's.' . $studentColumns['year_of_study'] . ' AS student_year';
    }
    if ($studentColumns['school']) {
        $selectFields[] = 's.' . $studentColumns['school'] . ' AS student_school';
    }
    if ($studentColumns['phone']) {
        $selectFields[] = 's.' . $studentColumns['phone'] . ' AS student_phone';
    }

    if ($studentColumns['user_id']) {
        $joinCondition = 's.' . $studentColumns['user_id'] . ' = u.id';
    } elseif ($studentColumns['admission_no']) {
        $joinCondition = 's.' . $studentColumns['admission_no'] . ' = u.admission_no';
    } else {
        $joinCondition = '1=0';
    }

    $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM users u LEFT JOIN student s ON ' . $joinCondition . ' WHERE u.id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
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
    $row['full_name'] = $row['student_name'] ?? $row['full_name'];
    $row['course'] = $row['student_course'] ?? $row['course'];
    $row['school'] = $row['student_school'] ?? $row['school'];
    $row['year_of_study'] = $row['student_year'] ?? $row['year_of_study'];
    return $row;
}

function fetchStudentRequests($conn, $admissionNo) {
    $sql = 'SELECT MIN(cr.RecordID) AS record_id, cr.Reason AS reason, cr.academic_year,
                   SUM(cr.ClearanceStatus = "Approved") AS approved_count,
                   SUM(cr.ClearanceStatus = "Rejected") AS rejected_count,
                   SUM(cr.ClearanceStatus = "Pending") AS pending_count,
                   COUNT(*) AS department_count,
                   MAX(cr.ClearanceDate) AS date_submitted,
                   CASE
                        WHEN SUM(cr.ClearanceStatus = "Rejected") > 0 THEN "Rejected"
                        WHEN SUM(cr.ClearanceStatus = "Pending") > 0 THEN "Pending"
                        WHEN SUM(cr.ClearanceStatus = "Approved") = COUNT(*) THEN "Approved"
                        ELSE "Pending"
                   END AS status
            FROM clearancerecord cr
            WHERE cr.AdmissionNo = ?
            GROUP BY cr.Reason, cr.academic_year
            ORDER BY MAX(cr.RecordID) DESC';
    $stmt = $conn->prepare($sql);
    if (! $stmt) { return []; }
    $stmt->bind_param('s', $admissionNo);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetchClearanceItems($conn, $admissionNo, $reason, $academicYear) {
    $columns = getDepartmentFieldNames($conn);
    $sql = 'SELECT cr.RecordID AS item_id, cr.DepartmentID AS department_id, cr.ClearanceStatus AS status, cr.request_comment AS comment, cr.ClearanceDate AS updated_at, d.' . $columns['name'] . ' AS department_name
            FROM clearancerecord cr
            LEFT JOIN department d ON d.' . $columns['id'] . ' = cr.DepartmentID
            WHERE cr.AdmissionNo = ? AND cr.Reason = ? AND cr.academic_year = ?
            ORDER BY cr.ClearanceDate DESC';
    $stmt = $conn->prepare($sql);
    if (! $stmt) { return []; }
    $stmt->bind_param('sss', $admissionNo, $reason, $academicYear);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetchDepartmentInfo($conn, $departmentId) {
    $columns = getDepartmentFieldNames($conn);
    $sql = 'SELECT ' . $columns['id'] . ' AS id, ' . $columns['name'] . ' AS name FROM department WHERE ' . $columns['id'] . ' = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (! $stmt) {
        return null;
    }
    $stmt->bind_param('s', $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result && $result->num_rows === 1) ? $result->fetch_assoc() : null;
}

function fetchDepartmentTasks($conn, $departmentId) {
    $sql = 'SELECT cr.RecordID AS item_id, cr.AdmissionNo AS admission_no, COALESCE(s.StudentName, u.full_name) AS full_name, COALESCE(s.Course, u.course) AS course,
                   u.year_of_study AS year_of_study,
                   cr.Reason AS reason, cr.academic_year, cr.ClearanceStatus AS status, cr.request_comment AS comment, cr.ClearanceDate AS date_submitted
            FROM clearancerecord cr
            LEFT JOIN users u ON u.admission_no = cr.AdmissionNo
            LEFT JOIN student s ON s.AdmissionNo = cr.AdmissionNo
            WHERE cr.DepartmentID = ?
            ORDER BY cr.ClearanceDate DESC';
    $stmt = $conn->prepare($sql);
    if (! $stmt) { return []; }
    $stmt->bind_param('s', $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetchAllClearances($conn) {
    $columns = getDepartmentFieldNames($conn);
    $sql = 'SELECT cr.AdmissionNo AS admission_no, COALESCE(s.StudentName, u.full_name) AS full_name, COALESCE(s.Course, u.course) AS course,
                   MAX(cr.ClearanceDate) AS date_submitted,
                   CASE
                        WHEN SUM(cr.ClearanceStatus = "Rejected") > 0 THEN "Rejected"
                        WHEN SUM(cr.ClearanceStatus = "Pending") > 0 THEN "Pending"
                        WHEN SUM(cr.ClearanceStatus = "Approved") = COUNT(*) THEN "Approved"
                        ELSE "Pending"
                   END AS status,
                   GROUP_CONCAT(DISTINCT CONCAT(IFNULL(d.' . $columns['name'] . ', "Unknown"), ": ", cr.ClearanceStatus) ORDER BY d.' . $columns['name'] . ' SEPARATOR ", ") AS department_status
            FROM clearancerecord cr
            LEFT JOIN users u ON u.admission_no = cr.AdmissionNo
            LEFT JOIN student s ON s.AdmissionNo = cr.AdmissionNo
            LEFT JOIN department d ON d.' . $columns['id'] . ' = cr.DepartmentID
            GROUP BY cr.AdmissionNo
            ORDER BY date_submitted DESC';
    $result = mysqli_query($conn, $sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function updateDepartmentItem($conn, $itemId, $status, $comment, $departmentId) {
    $status = ucfirst(strtolower(trim($status)));
    $stmt = $conn->prepare('UPDATE clearancerecord SET ClearanceStatus = ?, request_comment = ?, ClearanceDate = NOW() WHERE RecordID = ? AND DepartmentID = ?');
    if (! $stmt) {
        return ['success' => false, 'error' => mysqli_error($conn)];
    }
    $stmt->bind_param('ssis', $status, $comment, $itemId, $departmentId);
    if (! $stmt->execute()) {
        return ['success' => false, 'error' => mysqli_error($conn)];
    }
    return ['success' => true];
}

function updateAdminClearance($conn, $admissionNo, $status) {
    $status = ucfirst(strtolower(trim($status)));
    $stmt = $conn->prepare('UPDATE clearancerecord SET ClearanceStatus = ?, ClearanceDate = NOW() WHERE AdmissionNo = ?');
    if (! $stmt) { return ['success' => false, 'error' => mysqli_error($conn)]; }
    $stmt->bind_param('ss', $status, $admissionNo);
    if (! $stmt->execute()) { return ['success' => false, 'error' => mysqli_error($conn)]; }
    return ['success' => true];
}

function getBadge($status) {
    switch (strtolower($status)) {
        case 'approved':
        case 'cleared':
            return '<span class="badge badge-cleared">Approved</span>';
        case 'rejected':
            return '<span class="badge badge-rejected">Rejected</span>';
        default:
            return '<span class="badge badge-pending">Pending</span>';
    }
}

function formatDate($rawDate) {
    return $rawDate ? date('Y-m-d', strtotime($rawDate)) : 'N/A';
}

$departments = fetchDepartments($conn);
$message = '';
$profileInfo = fetchStudentProfile($conn, $userId) ?: ['full_name' => $fullName, 'admission_no' => $_SESSION['admission_no'] ?? '', 'email' => '', 'phone' => '', 'school' => '', 'course' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_department' && $role === 'department' && $departmentId) {
        $departmentIdCode = getDepartmentCodeByNumericId($conn, $departmentId);
        if ($departmentIdCode) {
            $itemId = trim($_POST['item_id'] ?? '');
            $recordId = trim($_POST['record_id'] ?? '');
            $status = trim($_POST['status'] ?? 'pending');
            $comment = trim($_POST['comment'] ?? '');
            $allowed = ['pending', 'approved', 'rejected'];
            if ($itemId !== '' && $recordId !== '' && in_array($status, $allowed, true)) {
                $result = updateDepartmentItem($conn, $itemId, $status, $comment, $departmentIdCode);
                if ($result['success']) {
                    $message = 'Department request updated successfully.';
                } else {
                    $message = 'Update failed: ' . htmlspecialchars($result['error']);
                }
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_overall' && $role === 'admin') {
        $admissionNo = trim($_POST['admission_no'] ?? '');
        $status = trim($_POST['overall_status'] ?? 'pending');
        $allowed = ['pending', 'approved', 'rejected'];
        if ($admissionNo !== '' && in_array($status, $allowed, true)) {
            $result = updateAdminClearance($conn, $admissionNo, $status);
            $message = $result['success'] ? 'Clearance status updated successfully.' : 'Update failed: ' . htmlspecialchars($result['error']);
        }
    }
}

$studentRequests = [];
$latestRequest = null;
$latestRequestItems = [];
$departmentRequests = [];
$departmentInfo = null;
$allRequests = [];

if ($role === 'student') {
    $admissionNo = $_SESSION['admission_no'] ?? '';
    if ($admissionNo !== '') {
        $studentRequests = fetchStudentRequests($conn, $admissionNo);
        if (!empty($studentRequests)) {
            $latestRequest = $studentRequests[0];
            $latestRequestItems = fetchClearanceItems($conn, $admissionNo, $latestRequest['reason'], $latestRequest['academic_year']);
        }
    }
}

if ($role === 'department' && $departmentId) {
    $departmentIdCode = getDepartmentCodeByNumericId($conn, $departmentId);
    if ($departmentIdCode) {
        $departmentInfo = fetchDepartmentInfo($conn, $departmentIdCode);
        $departmentRequests = fetchDepartmentTasks($conn, $departmentIdCode);
    }
}

if ($role === 'admin') {
    $allRequests = fetchAllClearances($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Dashboard</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body class="bg-machakos">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="logo-container">
                <img src="images/LOGO.jpeg?v=2" alt="University Logo">
                <h2>Clearance Portal</h2>
            </div>
            <div class="nav-title">Navigation</div>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <?php if ($role === 'student'): ?>
                    <li><a href="dashboard.php#clearance">Track Clearance</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                <?php elseif ($role === 'department'): ?>
                    <li><a href="dashboard.php#department">Department Tasks</a></li>
                <?php else: ?>
                    <li><a href="dashboard.php#admin">Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="logout-btn">Logout</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <div class="top-nav">
                    <div class="site-title">Welcome, <?php echo htmlspecialchars($fullName); ?></div>
                    <div class="user-actions">
                        <span><?php echo ucfirst(htmlspecialchars($role)); ?></span>
                        <a href="logout.php" class="btn btn-ghost">Logout</a>
                    </div>
                </div>
                <p>Use this portal to initiate, track, and manage student clearance.</p>
                <?php if ($message): ?>
                    <div class="content-panel message-panel"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
            </header>

            <?php if ($role === 'student'): ?>
                <section id="clearance" class="content-panel">
                    <div class="section-grid">
                        <div class="form-card">
                            <h2>Clearance Request Form</h2>
                            <p>Submit your clearance request with the reason, academic year and an optional message.</p>
                            <?php if (!empty($studentRequests)): ?>
                                <div class="content-panel message-panel">You have already initiated a clearance request. Duplicate submissions are not allowed.</div>
                            <?php elseif (empty($departments)): ?>
                                <div class="content-panel message-panel">No clearance departments are configured. Please contact the administrator.</div>
                            <?php else: ?>
                                <form method="POST" action="process_clearance.php" class="request-form">
                                    <input type="hidden" name="action" value="new_request">
                                    <div class="form-group">
                                        <label for="reason">Reason for clearance</label>
                                        <select id="reason" name="reason" class="input" required>
                                            <option value="">Select a reason</option>
                                            <option value="Graduation">Graduation</option>
                                            <option value="Transfer">Transfer</option>
                                            <option value="Deferment">Deferment</option>
                                            <option value="Discontinuation">Discontinuation</option>
                                            <option value="Continuation">COntinuation</option>
                                            <option value="Others">Others</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="academic_year">Academic year</label>
                                        <select id="academic_year" name="academic_year" class="input" required>
                                            <option value="">Select year</option>
                                            <option value="1st Year">1st Year</option>
                                            <option value="2nd Year">2nd Year</option>
                                            <option value="3rd Year">3rd Year</option>
                                            <option value="4th Year">4th Year</option>
                                            <option value="5th Year">5th Year</option>
                                        </select>
                                    </div>
                                    <div class="form-group full">
                                        <label for="comment">Comment (optional)</label>
                                        <textarea id="comment" name="comment" class="input" rows="4" placeholder="Add any notes or additional details..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Submit Request</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="info-card">
                            <h2>Student Profile</h2>
                            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($profileInfo['full_name'] ?? $fullName); ?></p>
                            <p><strong>Admission No:</strong> <?php echo htmlspecialchars($profileInfo['admission_no'] ?? ''); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($profileInfo['email'] ?? ''); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($profileInfo['phone'] ?? ''); ?></p>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($profileInfo['course'] ?? ''); ?></p>
                            <a href="profile.php" class="btn btn-secondary" style="margin-top: 16px; display: inline-flex;">Edit Profile</a>
                            <?php if (!empty($profileInfo['admission_no'])): ?>
                                <a href="generate_report.php?admissionNo=<?php echo urlencode($profileInfo['admission_no']); ?>&download=doc" class="btn btn-secondary" style="margin-top: 16px; display: inline-flex; margin-left: 8px;">Download My Report</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 style="margin-top: 28px; margin-bottom: 14px;">My Clearance Requests</h3>
                    <?php if (empty($studentRequests)): ?>
                        <p>You have not submitted any clearance requests yet.</p>
                    <?php else: ?>
                        <div class="filter-row" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
                            <div class="filter-group" style="flex:1;min-width:220px;">
                                <label for="studentSearch">Search</label>
                                <input id="studentSearch" type="text" class="input" placeholder="Search reason or academic year...">
                            </div>
                            <div class="filter-group" style="flex:1;min-width:180px;">
                                <label for="studentStatusFilter">Status</label>
                                <select id="studentStatusFilter" class="input">
                                    <option value="all">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                        <table id="studentRequestsTable" class="data-table">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th>Academic Year</th>
                                    <th>Departments</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentRequests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['reason'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($request['academic_year'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($request['department_count'] ?? ($request['department_status'] ?? 'N/A')); ?></td>
                                        <td><?php echo getBadge($request['status'] ?? 'pending'); ?></td>
                                        <td><?php echo htmlspecialchars(formatDate($request['date_submitted'] ?? '')); ?></td>
                                        <td>
                                            <a href="generate_report.php?admissionNo=<?php echo urlencode($profileInfo['admission_no'] ?? $_SESSION['admission_no'] ?? ''); ?>&reason=<?php echo urlencode($request['reason'] ?? ''); ?>&year=<?php echo urlencode($request['academic_year'] ?? ''); ?>" class="btn btn-secondary" target="_blank">Report</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($latestRequest): ?>
                            <div class="tracking-panel" style="margin-top:24px;">
                                <h3 style="margin-bottom:14px;">Latest Clearance Progress</h3>
                                <div class="grid-summary" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                                    <div><strong>Reason:</strong> <?php echo htmlspecialchars($latestRequest['reason']); ?></div>
                                    <div><strong>Academic Year:</strong> <?php echo htmlspecialchars($latestRequest['academic_year']); ?></div>
                                    <div><strong>Submitted:</strong> <?php echo htmlspecialchars(formatDate($latestRequest['date_submitted'])); ?></div>
                                    <div><strong>Overall Status:</strong> <?php echo getBadge($latestRequest['status']); ?></div>
                                </div>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Comment</th>
                                            <th>Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($latestRequestItems)): ?>
                                            <tr>
                                                <td colspan="4">No department responses available yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($latestRequestItems as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['department_name'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo getBadge($item['status']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['comment']); ?></td>
                                                    <td><?php echo htmlspecialchars(formatDate($item['updated_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($role === 'department'): ?>
                <section id="department" class="content-panel">
                    <h2><?php echo htmlspecialchars($departmentInfo['name'] ?? 'Department'); ?> Department Tasks</h2>
                    <p>Review pending student clearance items and update the status for your department.</p>
                    <?php if (empty($departmentRequests)): ?>
                        <p>No student clearance items are assigned to your department yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Admission No</th>
                                    <th>Course</th>
                                    <th>Year</th>
                                    <th>Reason</th>
                                    <th>Academic Year</th>
                                    <th>Date Submitted</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departmentRequests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['admission_no']); ?></td>
                                        <td><?php echo htmlspecialchars($request['course'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($request['year_of_study'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                        <td><?php echo htmlspecialchars($request['academic_year']); ?></td>
                                        <td><?php echo htmlspecialchars(formatDate($request['date_submitted'])); ?></td>
                                        <td><?php echo getBadge($request['status']); ?></td>
                                        <td>
                                            <form class="action-form" method="POST">
                                                <input type="hidden" name="action" value="update_department">
                                                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($request['item_id']); ?>">
                                                <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($request['item_id']); ?>">
                                                <select name="status" class="input">
                                                    <option value="pending" <?php echo strtolower($request['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="approved" <?php echo in_array(strtolower($request['status'] ?? ''), ['approved', 'cleared'], true) ? 'selected' : ''; ?>>Approved</option>
                                                    <option value="rejected" <?php echo strtolower($request['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                                <input type="text" name="comment" class="input" placeholder="Optional note" value="<?php echo htmlspecialchars($request['comment']); ?>">
                                                <button type="submit" class="report-button btn btn-primary">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <section id="admin" class="content-panel">
                    <div class="section-grid admin-grid">
                        <div>
                            <h2>Administration Dashboard</h2>
                            <p>Manage clearance requests across the system and search for students quickly. Click on a student to generate their clearance report.</p>
                        </div>
                    </div>
                    <div class="filter-row" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
                        <div class="filter-group" style="flex:1;min-width:220px;">
                            <label for="adminSearch">Search</label>
                            <input id="adminSearch" type="text" class="input" placeholder="Search student, admission, or reason...">
                        </div>
                        <div class="filter-group" style="flex:1;min-width:180px;">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter" class="input">
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <?php if (empty($allRequests)): ?>
                        <p>No clearance requests have been created yet.</p>
                    <?php else: ?>
                        <table id="adminRequestsTable" class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Admission No</th>
                                    <th>Departments</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allRequests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['full_name'] ?: 'Student'); ?></td>
                                        <td><?php echo htmlspecialchars($request['admission_no']); ?></td>
                                        <td><?php echo htmlspecialchars($request['department_status'] ?: 'No responses yet'); ?></td>
                                        <td><?php echo getBadge($request['status']); ?></td>
                                        <td><?php echo htmlspecialchars(formatDate($request['date_submitted'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <form class="action-form" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_overall">
                                                    <input type="hidden" name="admission_no" value="<?php echo htmlspecialchars($request['admission_no']); ?>">
                                                    <select name="overall_status" class="input">
                                                        <option value="pending" <?php echo strtolower($request['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved" <?php echo strtolower($request['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="rejected" <?php echo strtolower($request['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                    <button type="submit" class="report-button btn btn-primary">Save</button>
                                                </form>
                                                <a href="generate_report.php?admissionNo=<?php echo urlencode($request['admission_no']); ?>" class="btn btn-secondary" target="_blank">Report</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <footer class="site-footer">
        <p>Student Clearance System &copy; 2026</p>
    </footer>

    <script>
        const adminSearch = document.getElementById('adminSearch');
        const statusFilter = document.getElementById('statusFilter');
        const adminTable = document.getElementById('adminRequestsTable');

        if (adminSearch && statusFilter && adminTable) {
            const rows = Array.from(adminTable.querySelectorAll('tbody tr'));
            function filterAdminTable() {
                const searchValue = adminSearch.value.trim().toLowerCase();
                const statusValue = statusFilter.value;
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const matchesSearch = text.includes(searchValue);
                    const statusCell = row.querySelector('td:nth-child(6)');
                    const statusText = statusCell ? statusCell.textContent.trim().toLowerCase() : '';
                    const matchesStatus = statusValue === 'all' || statusText === statusValue;
                    row.style.display = matchesSearch && matchesStatus ? '' : 'none';
                });
            }
            adminSearch.addEventListener('input', filterAdminTable);
            statusFilter.addEventListener('change', filterAdminTable);
        }
        // Student requests table filtering (mirrors admin filters)
        const studentSearch = document.getElementById('studentSearch');
        const studentStatusFilter = document.getElementById('studentStatusFilter');
        const studentTable = document.getElementById('studentRequestsTable');

        if (studentSearch && studentStatusFilter && studentTable) {
            const sRows = Array.from(studentTable.querySelectorAll('tbody tr'));
            function filterStudentTable() {
                const searchValue = studentSearch.value.trim().toLowerCase();
                const statusValue = studentStatusFilter.value;
                sRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const matchesSearch = text.includes(searchValue);
                    const statusCell = row.querySelector('td:nth-child(4)');
                    const statusText = statusCell ? statusCell.textContent.trim().toLowerCase() : '';
                    const matchesStatus = statusValue === 'all' || statusText === statusValue;
                    row.style.display = matchesSearch && matchesStatus ? '' : 'none';
                });
            }
            studentSearch.addEventListener('input', filterStudentTable);
            studentStatusFilter.addEventListener('change', filterStudentTable);
        }
    </script>
</body>
</html>
