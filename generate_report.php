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

function getStudentFieldNames($conn) {
    $fields = [];
    $result = $conn->query('SHOW COLUMNS FROM student');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fields[strtolower($row['Field'])] = $row['Field'];
        }
    }

    return [
        'student_name' => $fields['studentname'] ?? ($fields['student_name'] ?? null),
        'year' => $fields['year_of_study'] ?? ($fields['year'] ?? ($fields['yearofstudy'] ?? null)),
        'faculty' => $fields['faculty'] ?? ($fields['schoolfaculty'] ?? null),
        'phone' => $fields['phonenumber'] ?? ($fields['phone'] ?? null),
    ];
}

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

$role = $_SESSION['role'] ?? 'student';
$requestAdmissionNo = trim($_GET['admissionNo'] ?? '');
$downloadDoc = isset($_GET['download']) && $_GET['download'] === 'doc';
$reportTitle = 'Clearance Report';
$records = [];
$errors = [];
$studentInfo = null;

if ($role === 'student') {
    $requestAdmissionNo = trim($_SESSION['admission_no'] ?? '');
    if ($requestAdmissionNo === '') {
        $errors[] = 'Unable to determine your admission number.';
    } else {
        $reportTitle = 'My Clearance Report';
    }
} elseif ($role === 'admin' || $role === 'department') {
    if ($requestAdmissionNo === '') {
        $errors[] = 'Please specify a student by admission number to generate a report.';
    } else {
        $reportTitle = 'Clearance Report for ' . htmlspecialchars($requestAdmissionNo);
    }
} else {
    $errors[] = 'Invalid role for report generation.';
}

if (empty($errors)) {
    $studentCols = getStudentFieldNames($conn);
    $selectExtra = [];
    if ($studentCols['student_name']) { $selectExtra[] = 's.' . $studentCols['student_name'] . ' AS student_name'; }
    if ($studentCols['year']) { $selectExtra[] = 's.' . $studentCols['year'] . ' AS student_year'; }
    if ($studentCols['faculty']) { $selectExtra[] = 's.' . $studentCols['faculty'] . ' AS faculty'; }
    if ($studentCols['phone']) { $selectExtra[] = 's.' . $studentCols['phone'] . ' AS student_phone'; }

    $baseSelect = 'u.full_name, u.admission_no, u.email, u.phone, u.school, u.course';
    if (!empty($selectExtra)) { $baseSelect .= ', ' . implode(', ', $selectExtra); }

    $stmtInfo = $conn->prepare('SELECT ' . $baseSelect . '
                                FROM users u
                                LEFT JOIN student s ON u.admission_no = s.AdmissionNo
                                WHERE u.admission_no = ?
                                LIMIT 1');
    if ($stmtInfo) {
        $stmtInfo->bind_param('s', $requestAdmissionNo);
        $stmtInfo->execute();
        $resultInfo = $stmtInfo->get_result();
        $studentInfo = $resultInfo ? $resultInfo->fetch_assoc() : null;
        if (! $studentInfo) {
            $errors[] = 'Student not found for the requested admission number.';
        }
    }
}

if (empty($errors)) {
    $columns = getDepartmentFieldNames($conn);
    $stmtRecords = $conn->prepare('SELECT cr.RecordID AS record_id, cr.AdmissionNo AS admission_no,
                                        COALESCE(s.StudentName, u.full_name) AS full_name,
                                        cr.Reason AS reason, cr.academic_year,
                                        cr.ClearanceStatus AS status, cr.ClearanceDate AS date_submitted,
                                        d.' . $columns['name'] . ' AS department_name, cr.request_comment AS comment
                                 FROM clearancerecord cr
                                 LEFT JOIN users u ON u.admission_no = cr.AdmissionNo
                                 LEFT JOIN student s ON s.AdmissionNo = cr.AdmissionNo
                                 LEFT JOIN department d ON d.' . $columns['id'] . ' = cr.DepartmentID
                                 WHERE cr.AdmissionNo = ?
                                 ORDER BY cr.ClearanceDate DESC');
    if ($stmtRecords) {
        $stmtRecords->bind_param('s', $requestAdmissionNo);
        $stmtRecords->execute();
        $resultRecords = $stmtRecords->get_result();
        $records = $resultRecords ? $resultRecords->fetch_all(MYSQLI_ASSOC) : [];
    }
}

// Compute overall clearance status for the student based on fetched records
$overallStatus = 'N/A';
if (!empty($records)) {
    $hasRejected = false;
    $hasPending = false;
    foreach ($records as $r) {
        $s = strtolower(trim($r['status'] ?? 'pending'));
        if ($s === 'rejected') { $hasRejected = true; }
        if ($s === 'pending') { $hasPending = true; }
    }
    if ($hasRejected) { $overallStatus = 'Rejected'; }
    elseif ($hasPending) { $overallStatus = 'Pending'; }
    else { $overallStatus = 'Approved'; }
}

if ($downloadDoc && empty($errors) && !empty($records)) {
    $filename = 'clearance_report_' . preg_replace('/[^A-Za-z0-9-_\.]/', '_', $requestAdmissionNo) . '.doc';
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

$generatedAt = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($reportTitle); ?></title>
<link rel="stylesheet" href="style.css?v=2">
<style>
    :root{
        --brand:#0b5ed7; --brand-dark:#063a7a; --accent:#eef5ff;
        --approved:#16a34a; --rejected:#dc2626; --pending:#f59e0b; --muted:#6b7280;
    }
    body{background:#ffffff;font-family:Inter,system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#0f172a;margin:0;padding:20px}
    .report-wrapper{max-width:900px;margin:0 auto;background:#fff;padding:20px 28px;border:1px solid #e6eefc}
    .top-header{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .brand-left img{height:84px}
    .brand-center{text-align:center;flex:1}
    .brand-center h2{margin:0;font-size:20px;color:var(--brand-dark);letter-spacing:0.6px}
    .brand-center p{margin:4px 0 0;color:var(--muted)}
    .report-title{margin-top:14px;text-align:center}
    .report-title h1{margin:6px 0 0;font-size:18px;color:var(--brand-dark)}
    .report-meta{text-align:right;color:var(--muted);font-size:13px}
    .divider{height:4px;background:var(--brand);margin:18px 0;border-radius:2px}

    .student-card{display:grid;grid-template-columns:1fr 1fr;gap:12px;background:#fff;padding:14px;border:1px solid #e6eefc;border-radius:6px}
    .student-card .row{display:flex;justify-content:space-between;padding:6px 8px}
    .student-card .label{color:var(--muted);font-size:13px}
    .student-card .value{font-weight:600;color:#0f172a}

    .dept-table{width:100%;border-collapse:collapse;margin-top:18px}
    .dept-table th,.dept-table td{padding:12px 10px;border:1px solid #e6eefc}
    .dept-table th{background:var(--accent);color:var(--brand-dark);text-align:left}

    .badge{display:inline-block;padding:6px 10px;border-radius:999px;color:#fff;font-weight:600;font-size:13px}
    .badge-approved{background:var(--approved)}
    .badge-rejected{background:var(--rejected)}
    .badge-pending{background:var(--pending);color:#111}

    .signatures{display:flex;gap:20px;margin-top:26px}
    .sig-box{flex:1;border:1px dashed #cbd5e1;padding:18px;border-radius:6px;text-align:center}
    .sig-box .line{height:2px;background:#111;margin:48px auto 12px;width:70%}

    .report-footer{display:flex;justify-content:space-between;align-items:center;margin-top:20px;color:var(--muted);font-size:13px}

    .print-btn{position:fixed;right:18px;top:18px;padding:10px 14px;background:var(--brand);color:#fff;border:none;border-radius:8px;cursor:pointer;box-shadow:0 8px 20px rgba(11,94,215,.14);z-index:999}

    @media (max-width:720px){
        .student-card{grid-template-columns:1fr}
        .brand-left img{height:64px}
    }

    /* Print styles */
    @page { size: A4; margin: 12mm; }
    @media print{
        body{padding:0}
        .print-btn{display:none}
        .report-wrapper{border:none;margin:0;padding:0}
        .sig-box{page-break-inside:avoid}
    }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Print Report</button>
<div class="report-wrapper" role="main">
    <div class="top-header">
        <div class="brand-left"><img src="images/LOGO.jpeg?v=2" alt="Machakos University Logo"></div>
        <div class="brand-center">
            <h2>Machakos University</h2>
            <p>Student Clearance System</p>
            <div class="report-title">
                <h1>STUDENT CLEARANCE REPORT</h1>
            </div>
        </div>
        <div class="report-meta">
            <div>Generated: <?php echo htmlspecialchars($generatedAt); ?></div>
            <div><?php echo htmlspecialchars($reportTitle); ?></div>
        </div>
    </div>

    <div class="divider" aria-hidden="true"></div>

    <?php if (!empty($errors)): ?>
        <div style="margin-top:12px;padding:12px;background:#fff3f2;border:1px solid #fce8e6;color:#7f1d1d;border-radius:6px;">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($records)): ?>
        <div style="margin-top:12px;padding:12px;background:#f8fafc;border:1px solid #e6eefc;color:#334155;border-radius:6px;">No clearance records found for this student.</div>
    <?php else: ?>

        <?php
            $displayName = $studentInfo['student_name'] ?? $studentInfo['full_name'] ?? ($studentInfo['StudentName'] ?? 'N/A');
            $displayPhone = $studentInfo['student_phone'] ?? $studentInfo['phone'] ?? 'N/A';
            $displayFaculty = $studentInfo['faculty'] ?? $studentInfo['school'] ?? 'N/A';
            $displayYear = $studentInfo['student_year'] ?? 'N/A';
        ?>

        <div style="margin-top:14px;">
            <div class="student-card">
                <div class="row"><div class="label">Student Name</div><div class="value"><?php echo htmlspecialchars($displayName); ?></div></div>
                <div class="row"><div class="label">Admission Number</div><div class="value"><?php echo htmlspecialchars($studentInfo['admission_no'] ?? 'N/A'); ?></div></div>
                <div class="row"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($studentInfo['email'] ?? 'N/A'); ?></div></div>
                <div class="row"><div class="label">Phone Number</div><div class="value"><?php echo htmlspecialchars($displayPhone); ?></div></div>
                <div class="row"><div class="label">Course</div><div class="value"><?php echo htmlspecialchars($studentInfo['course'] ?? 'N/A'); ?></div></div>
                <div class="row"><div class="label">School</div><div class="value"><?php echo htmlspecialchars($studentInfo['school'] ?? 'N/A'); ?></div></div>
                <div class="row"><div class="label">Faculty</div><div class="value"><?php echo htmlspecialchars($displayFaculty); ?></div></div>
                <div class="row"><div class="label">Year of Study</div><div class="value"><?php echo htmlspecialchars($displayYear); ?></div></div>
                <div class="row"><div class="label">Overall Clearance Status</div><div class="value">
                    <?php if ($overallStatus === 'Approved'): ?>
                        <span class="badge badge-approved">Approved</span>
                    <?php elseif ($overallStatus === 'Rejected'): ?>
                        <span class="badge badge-rejected">Rejected</span>
                    <?php elseif ($overallStatus === 'Pending'): ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php else: ?>
                        <span class="badge" style="background:#94a3b8">N/A</span>
                    <?php endif; ?>
                </div></div>
            </div>

            <h3 style="margin-top:18px;margin-bottom:8px;color:var(--brand-dark)">Department Clearance Section</h3>
            <table class="dept-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th>Date Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record):
                        $st = strtolower(trim($record['status'] ?? 'pending'));
                        if ($st === 'approved' || $st === 'cleared') { $badge = '<span class="badge badge-approved">Approved</span>'; }
                        elseif ($st === 'rejected') { $badge = '<span class="badge badge-rejected">Rejected</span>'; }
                        else { $badge = '<span class="badge badge-pending">Pending</span>'; }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['department_name'] ?: 'Unknown'); ?></td>
                        <td><?php echo $badge; ?></td>
                        <td><?php echo htmlspecialchars($record['comment'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['date_submitted'] ? date('Y-m-d H:i', strtotime($record['date_submitted'])) : 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="signatures">
                <div class="sig-box">
                    <div class="line"></div>
                    <div style="margin-top:6px;font-weight:600">Administrator Signature</div>
                </div>
                <div class="sig-box">
                    <div style="height:68px;display:flex;align-items:center;justify-content:center;color:var(--muted);">Official Stamp Area</div>
                    <div style="margin-top:6px;font-weight:600">Office Stamp</div>
                </div>
            </div>

            <div class="report-footer">
                <div>Generated by Student Clearance System — Machakos University</div>
                <div><?php echo htmlspecialchars($displayName); ?> • <?php echo htmlspecialchars($generatedAt); ?></div>
            </div>
        </div>

    <?php endif; ?>

</div>
</body>
</html>
