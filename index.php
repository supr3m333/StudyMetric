<?php
session_start();

function clean($value) {
    return trim($value ?? '');
}

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectTo($page) {
    header('Location: index.php?page=' . $page);
    exit;
}

require __DIR__ . '/database.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.html');
    exit;
}

if (isset($_POST['register'])) {
    $username = clean($_POST['username']);
    $password = $_POST['password'] ?? '';
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $newStudentId = 'STD' . strtoupper(substr(uniqid(), -6));

    try {
        $db->begin_transaction();
        $statement = $db->prepare(
            "INSERT INTO students (student_id, name, email, course, year) VALUES (?, ?, ?, 'Not set', 'First')"
        );
        $statement->bind_param('sss', $newStudentId, $name, $email);
        $statement->execute();
        $studentRecordId = $db->insert_id;

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $db->prepare(
            "INSERT INTO users (username, password_hash, role, student_id) VALUES (?, ?, 'student', ?)"
        );
        $statement->bind_param('ssi', $username, $passwordHash, $studentRecordId);
        $statement->execute();
        $db->commit();
        header('Location: index.html?registered=1');
    } catch (mysqli_sql_exception $error) {
        $db->rollback();
        header('Location: index.html?register_error=1');
    }
    exit;
}

if (isset($_POST['login'])) {
    $username = clean($_POST['username']);
    $statement = $db->prepare('SELECT id, username, password_hash, role, student_id FROM users WHERE username = ?');
    $statement->bind_param('s', $username);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();

    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['student_id'] = $user['student_id'];
        redirectTo('dashboard');
    }
    header('Location: index.html?error=1');
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: index.html');
    exit;
}

$isTeacher = $_SESSION['role'] === 'teacher';

if ($isTeacher && isset($_POST['save_student'])) {
    $id = (int) ($_POST['record_id'] ?? 0);
    $studentId = clean($_POST['student_id']);
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $course = clean($_POST['course']);
    $year = clean($_POST['year']);

    if ($id) {
        $statement = $db->prepare(
            'UPDATE students SET student_id = ?, name = ?, email = ?, course = ?, year = ? WHERE id = ?'
        );
        $statement->bind_param('sssssi', $studentId, $name, $email, $course, $year, $id);
    } else {
        $statement = $db->prepare(
            'INSERT INTO students (student_id, name, email, course, year) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->bind_param('sssss', $studentId, $name, $email, $course, $year);
    }
    $statement->execute();
    redirectTo('students');
}

if ($isTeacher && isset($_POST['delete_student'])) {
    $id = (int) $_POST['id'];
    $statement = $db->prepare('DELETE FROM students WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    redirectTo('students');
}

if ($isTeacher && isset($_POST['save_attendance'])) {
    $studentId = (int) $_POST['student_id'];
    $date = clean($_POST['date']);
    $status = clean($_POST['status']);
    $statement = $db->prepare(
        'INSERT INTO attendance (student_id, attendance_date, status) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)'
    );
    $statement->bind_param('iss', $studentId, $date, $status);
    $statement->execute();
    redirectTo('attendance');
}

if ($isTeacher && isset($_POST['save_result'])) {
    $studentId = (int) $_POST['student_id'];
    $term = clean($_POST['term']);
    $outcome = clean($_POST['outcome']);
    $remarks = clean($_POST['remarks']);
    $statement = $db->prepare(
        'INSERT INTO results (student_id, term, outcome, remarks) VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('isss', $studentId, $term, $outcome, $remarks);
    $statement->execute();
    redirectTo('results');
}

if ($isTeacher && isset($_POST['delete_result'])) {
    $id = (int) $_POST['id'];
    $statement = $db->prepare('DELETE FROM results WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    redirectTo('results');
}

if (!$isTeacher && isset($_POST['save_own_profile'])) {
    $studentId = (int) $_SESSION['student_id'];
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $course = clean($_POST['course']);
    $year = clean($_POST['year']);
    $statement = $db->prepare('UPDATE students SET name = ?, email = ?, course = ?, year = ? WHERE id = ?');
    $statement->bind_param('ssssi', $name, $email, $course, $year, $studentId);
    $statement->execute();
    redirectTo('profile');
}

if (!$isTeacher && isset($_POST['change_password'])) {
    $userId = (int) $_SESSION['user_id'];
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $statement = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $statement->bind_param('i', $userId);
    $statement->execute();
    $account = $statement->get_result()->fetch_assoc();

    if ($account && password_verify($currentPassword, $account['password_hash']) && strlen($newPassword) >= 6) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $statement = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $statement->bind_param('si', $newHash, $userId);
        $statement->execute();
        redirectTo('profile&password=changed');
    }
    redirectTo('profile&password=error');
}

if ($isTeacher) {
    $students = $db->query('SELECT * FROM students ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $attendance = $db->query(
        'SELECT student_id, attendance_date AS date, status FROM attendance ORDER BY attendance_date, id'
    )->fetch_all(MYSQLI_ASSOC);
    $results = $db->query('SELECT * FROM results ORDER BY id')->fetch_all(MYSQLI_ASSOC);
} else {
    $studentId = (int) $_SESSION['student_id'];
    $statement = $db->prepare('SELECT * FROM students WHERE id = ?');
    $statement->bind_param('i', $studentId);
    $statement->execute();
    $students = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

    $statement = $db->prepare(
        'SELECT student_id, attendance_date AS date, status FROM attendance WHERE student_id = ? ORDER BY attendance_date'
    );
    $statement->bind_param('i', $studentId);
    $statement->execute();
    $attendance = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

    $statement = $db->prepare('SELECT * FROM results WHERE student_id = ? ORDER BY id');
    $statement->bind_param('i', $studentId);
    $statement->execute();
    $results = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function studentName($id, $students) {
    foreach ($students as $student) {
        if ((int) $student['id'] === (int) $id) return $student['name'];
    }
    return 'Unknown student';
}

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = $isTeacher
    ? ['dashboard', 'students', 'attendance', 'results']
    : ['dashboard', 'profile', 'attendance', 'results'];
if (!in_array($page, $allowedPages, true)) $page = 'dashboard';

$editStudent = null;
if ($page === 'students' && isset($_GET['edit'])) {
    foreach ($students as $student) {
        if ((int) $student['id'] === (int) $_GET['edit']) $editStudent = $student;
    }
}

$todayRecords = array_filter($attendance, fn($item) => $item['date'] === date('Y-m-d'));
$presentToday = count(array_filter($todayRecords, fn($item) => $item['status'] === 'Present'));
$presentCount = count(array_filter($attendance, fn($item) => $item['status'] === 'Present'));
$absentCount = count(array_filter($attendance, fn($item) => $item['status'] === 'Absent'));
$lateCount = count(array_filter($attendance, fn($item) => $item['status'] === 'Late'));
$attendanceRate = count($attendance) ? round(($presentCount / count($attendance)) * 100) : 0;
$passedCount = count(array_filter($results, fn($item) => $item['outcome'] === 'Passed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($page) ?> | StudyMetric</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
    <header>
        <div class="container header-content">
            <h1>StudyMetric</h1>
            <nav>
                <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">Dashboard</a>
                <?php if ($isTeacher): ?>
                    <a class="<?= $page === 'students' ? 'active' : '' ?>" href="?page=students">Students</a>
                <?php else: ?>
                    <a class="<?= $page === 'profile' ? 'active' : '' ?>" href="?page=profile">My Profile</a>
                <?php endif; ?>
                <a class="<?= $page === 'attendance' ? 'active' : '' ?>" href="?page=attendance"><?= $isTeacher ? 'Attendance' : 'My Attendance' ?></a>
                <a class="<?= $page === 'results' ? 'active' : '' ?>" href="?page=results"><?= $isTeacher ? 'Results' : 'My Results' ?></a>
                <a href="?logout=1">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($page === 'dashboard'): ?>
            <h2>Dashboard</h2>
            <?php if ($isTeacher): ?>
                <p class="page-note">Teacher administrator overview</p>
                <div class="summary-grid">
                    <div class="card"><h3>Registered Students</h3><p class="number"><?= count($students) ?></p></div>
                    <div class="card"><h3>Present Today</h3><p class="number"><?= $presentToday ?></p></div>
                    <div class="card"><h3>Attendance Records</h3><p class="number"><?= count($attendance) ?></p></div>
                    <div class="card"><h3>Result Records</h3><p class="number"><?= count($results) ?></p></div>
                </div>
                <div class="card">
                    <div class="section-heading"><h3>Quick Actions</h3></div>
                    <div class="quick-links">
                        <a class="button" href="?page=students">Register Student</a>
                        <a class="button secondary" href="?page=attendance">Record Attendance</a>
                        <a class="button secondary" href="?page=results">Add Result</a>
                    </div>
                </div>
                <div class="card table-wrapper">
                    <h3>Student Directory</h3>
                    <table>
                        <thead><tr><th>Student ID</th><th>Name</th><th>Course</th><th>Year</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($students, 0, 5) as $student): ?>
                            <tr><td><?= escape($student['student_id']) ?></td><td><?= escape($student['name']) ?></td><td><?= escape($student['course']) ?></td><td><?= escape($student['year']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$students): ?><p class="empty">No students registered yet.</p><?php endif; ?>
                </div>
            <?php else: ?>
                <?php $profile = $students[0] ?? []; ?>
                <div class="welcome-card card">
                    <div>
                        <p class="eyebrow">Student Dashboard</p>
                        <h3>Welcome, <?= escape($profile['name'] ?? $_SESSION['username']) ?></h3>
                        <p>Student ID: <?= escape($profile['student_id'] ?? '') ?></p>
                    </div>
                    <a class="button" href="?page=profile">Edit My Profile</a>
                </div>
                <div class="summary-grid">
                    <div class="card"><h3>Attendance Rate</h3><p class="number"><?= $attendanceRate ?>%</p></div>
                    <div class="card"><h3>Days Present</h3><p class="number"><?= $presentCount ?></p></div>
                    <div class="card"><h3>Days Absent</h3><p class="number"><?= $absentCount ?></p></div>
                    <div class="card"><h3>Results Passed</h3><p class="number"><?= $passedCount ?></p></div>
                </div>
                <div class="dashboard-grid">
                    <div class="card">
                        <div class="section-heading"><h3>My Profile</h3><a href="?page=profile">Update</a></div>
                        <dl class="profile-details">
                            <div><dt>Email</dt><dd><?= escape($profile['email'] ?? '') ?></dd></div>
                            <div><dt>Course</dt><dd><?= escape($profile['course'] ?? 'Not set') ?></dd></div>
                            <div><dt>Year</dt><dd><?= escape($profile['year'] ?? '') ?></dd></div>
                            <div><dt>Username</dt><dd><?= escape($_SESSION['username']) ?></dd></div>
                        </dl>
                    </div>
                    <div class="card attendance-breakdown">
                        <h3>Attendance Summary</h3>
                        <p><span class="status present">Present</span><strong><?= $presentCount ?></strong></p>
                        <p><span class="status absent">Absent</span><strong><?= $absentCount ?></strong></p>
                        <a href="?page=attendance">View all attendance</a>
                    </div>
                </div>
                <div class="card table-wrapper">
                    <div class="section-heading"><h3>Recent Results</h3><a href="?page=results">View all</a></div>
                    <table>
                        <thead><tr><th>Term</th><th>Outcome</th><th>Remarks</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice(array_reverse($results), 0, 4) as $item): ?>
                            <tr><td><?= escape($item['term']) ?></td><td><span class="status <?= strtolower($item['outcome']) ?>"><?= escape($item['outcome']) ?></span></td><td><?= escape($item['remarks']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$results): ?><p class="empty">No results have been added by your teacher yet.</p><?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($page === 'profile'): ?>
            <?php $profile = $students[0] ?? []; ?>
            <h2>My Profile</h2>
            <form method="post" class="card">
                <p><strong>Student ID:</strong> <?= escape($profile['student_id'] ?? '') ?></p>
                <div class="form-grid">
                    <label>Full Name<input name="name" value="<?= escape($profile['name'] ?? '') ?>" required></label>
                    <label>Email<input type="email" name="email" value="<?= escape($profile['email'] ?? '') ?>" required></label>
                    <label>Course<input name="course" value="<?= escape($profile['course'] ?? '') ?>" required></label>
                    <label>Year
                        <select name="year">
                            <?php foreach (['First', 'Second', 'Third', 'Fourth'] as $year): ?>
                                <option <?= ($profile['year'] ?? '') === $year ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <button name="save_own_profile">Save Profile</button>
            </form>
            <form method="post" class="card password-form">
                <h3>Change Password</h3>
                <?php if (($_GET['password'] ?? '') === 'changed'): ?><p class="success">Password changed successfully.</p><?php endif; ?>
                <?php if (($_GET['password'] ?? '') === 'error'): ?><p class="error">Current password is incorrect, or the new password is too short.</p><?php endif; ?>
                <div class="form-grid two-columns">
                    <label>Current Password<input type="password" name="current_password" required></label>
                    <label>New Password<input type="password" name="new_password" minlength="6" required></label>
                </div>
                <button name="change_password">Change Password</button>
            </form>

        <?php elseif ($page === 'students'): ?>
            <h2><?= $editStudent ? 'Edit Student Profile' : 'Student Registration' ?></h2>
            <form method="post" class="card">
                <input type="hidden" name="record_id" value="<?= escape($editStudent['id'] ?? '') ?>">
                <div class="form-grid">
                    <label>Student ID<input name="student_id" value="<?= escape($editStudent['student_id'] ?? '') ?>" required></label>
                    <label>Full Name<input name="name" value="<?= escape($editStudent['name'] ?? '') ?>" required></label>
                    <label>Email<input type="email" name="email" value="<?= escape($editStudent['email'] ?? '') ?>" required></label>
                    <label>Course<input name="course" value="<?= escape($editStudent['course'] ?? '') ?>" required></label>
                    <label>Year
                        <select name="year" required>
                            <?php foreach (['First', 'Second', 'Third', 'Fourth'] as $year): ?>
                                <option <?= ($editStudent['year'] ?? '') === $year ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <button type="submit" name="save_student"><?= $editStudent ? 'Save Changes' : 'Register Student' ?></button>
                <?php if ($editStudent): ?><a class="button secondary" href="?page=students">Cancel</a><?php endif; ?>
            </form>

            <div class="card">
                <div class="section-heading">
                    <h3>Student Profiles</h3>
                    <input id="student-search" class="search" placeholder="Search students">
                </div>
                <div class="table-wrapper">
                    <table id="student-table">
                        <thead><tr><th>ID</th><th>Name</th><th>Course</th><th>Year</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= escape($student['student_id']) ?></td>
                                <td><?= escape($student['name']) ?><br><small><?= escape($student['email']) ?></small></td>
                                <td><?= escape($student['course']) ?></td>
                                <td><?= escape($student['year']) ?></td>
                                <td class="actions">
                                    <a class="button" href="?page=students&edit=<?= escape($student['id']) ?>">Edit</a>
                                    <form method="post"><input type="hidden" name="id" value="<?= escape($student['id']) ?>"><button class="secondary" name="delete_student">Delete</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$students): ?><p class="empty">No students registered yet.</p><?php endif; ?>
                </div>
            </div>

        <?php elseif ($page === 'attendance'): ?>
            <h2><?= $isTeacher ? 'Attendance Management' : 'My Attendance' ?></h2>
            <?php if ($isTeacher): ?>
            <form method="post" class="card inline-form">
                <label>Student
                    <select name="student_id" required>
                        <option value="">Choose a student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= escape($student['id']) ?>"><?= escape($student['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Date<input type="date" name="date" value="<?= date('Y-m-d') ?>" required></label>
                <label>Status
                    <select name="status"><option>Present</option><option>Absent</option><option>Late</option></select>
                </label>
                <button type="submit" name="save_attendance" <?= !$students ? 'disabled' : '' ?>>Save Attendance</button>
            </form>
            <?php else: ?><p class="page-note">Your attendance records are shown below.</p><?php endif; ?>
            <div class="card table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th>Student</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($attendance) as $item): ?>
                        <tr><td><?= escape($item['date']) ?></td><td><?= escape(studentName($item['student_id'], $students)) ?></td><td><span class="status <?= strtolower($item['status']) ?>"><?= escape($item['status']) ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$attendance): ?><p class="empty">No attendance recorded yet.</p><?php endif; ?>
            </div>

        <?php elseif ($page === 'results'): ?>
            <h2><?= $isTeacher ? 'Result Management' : 'My Results' ?></h2>
            <p class="page-note">This optional section records only an overall outcome—no subjects or marks.</p>
            <?php if ($isTeacher): ?>
            <form method="post" class="card inline-form">
                <label>Student
                    <select name="student_id" required>
                        <option value="">Choose a student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= escape($student['id']) ?>"><?= escape($student['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Term<input name="term" placeholder="Example: Semester 1" required></label>
                <label>Outcome<select name="outcome"><option>Passed</option><option>Failed</option><option>Pending</option></select></label>
                <label>Remarks<input name="remarks" placeholder="Optional"></label>
                <button type="submit" name="save_result" <?= !$students ? 'disabled' : '' ?>>Save Result</button>
            </form>
            <?php endif; ?>
            <div class="card table-wrapper">
                <table>
                    <thead><tr><th>Student</th><th>Term</th><th>Outcome</th><th>Remarks</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($results) as $item): ?>
                        <tr>
                            <td><?= escape(studentName($item['student_id'], $students)) ?></td>
                            <td><?= escape($item['term']) ?></td>
                            <td><span class="status <?= strtolower($item['outcome']) ?>"><?= escape($item['outcome']) ?></span></td>
                            <td><?= escape($item['remarks']) ?></td>
                            <td><?php if ($isTeacher): ?><form method="post"><input type="hidden" name="id" value="<?= escape($item['id']) ?>"><button class="secondary" name="delete_result">Delete</button></form><?php else: ?>—<?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$results): ?><p class="empty">No results recorded yet.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="script.js"></script>
</body>
</html>
