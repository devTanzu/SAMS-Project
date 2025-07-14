<?php
session_start();
require 'db.php';

// Only allow admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

// Handle AJAX actions
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'];

    if ($action === 'fetch') {
        $stmt = $pdo->query("SELECT cs.id, cs.teacher_id, cs.course_id, cs.weekday, cs.start_time, cs.end_time, t.name AS teacher_name, c.title AS course_title FROM class_schedules cs JOIN teachers t ON cs.teacher_id = t.id JOIN courses c ON cs.course_id = c.id ORDER BY cs.weekday ASC, cs.start_time ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'add') {
        $teacher_id = intval($_POST['teacher_id']);
        $course_id = intval($_POST['course_id']);
        $weekday = $_POST['weekday'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $stmt = $pdo->prepare("INSERT INTO class_schedules (teacher_id, course_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$teacher_id, $course_id, $weekday, $start_time, $end_time])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to add schedule']);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $teacher_id = intval($_POST['teacher_id']);
        $course_id = intval($_POST['course_id']);
        $weekday = $_POST['weekday'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $stmt = $pdo->prepare("UPDATE class_schedules SET teacher_id=?, course_id=?, weekday=?, start_time=?, end_time=? WHERE id=?");
        if ($stmt->execute([$teacher_id, $course_id, $weekday, $start_time, $end_time, $id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to update schedule']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM class_schedules WHERE id=?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to delete schedule']);
        }
        exit;
    }

    if ($action === 'fetch_teachers') {
        $stmt = $pdo->query("SELECT id, name FROM teachers ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    if ($action === 'fetch_courses') {
        $stmt = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'fetch_courses_for_teacher' && isset($_GET['teacher_id'])) {
        $teacher_id = intval($_GET['teacher_id']);
        $stmt = $pdo->prepare("SELECT c.id, c.title FROM courses c
            JOIN teacher_courses tc ON c.id = tc.course_id
            WHERE tc.teacher_id = ?
            ORDER BY c.title ASC");
        $stmt->execute([$teacher_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Class Schedules</title>
    <style>
        body { background: #0f172a; color: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 900px; margin: 40px auto; background: #1e293b; border-radius: 14px; padding: 32px 28px; box-shadow: 0 4px 24px rgba(0,0,0,0.18); }
        h1 { color: #7a47e5; text-align: center; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; background: #232b3e; border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #7a47e5; color: #fff; }
        tr:hover { background: #334155; }
        .form-section { margin-bottom: 2rem; }
        label { display: block; margin: 10px 0 5px; }
        input, select { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 6px; border: none; background: #334155; color: white; font-size: 16px; }
        button { background: #7a47e5; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #6a3cd5; }
        .action-btns { display: flex; gap: 10px; }
        .msg { margin-bottom: 1rem; padding: 0.7rem 1rem; border-radius: 6px; font-weight: 500; text-align: center; }
        .msg.error { background: #3b1a2b; color: #ff6b81; }
        .msg.success { background: #1a3b2b; color: #28a745; }
        .back-btn {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.5rem 1.2rem;
            background: #7a47e5;
            color: #fff !important;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.08rem;
            border: none;
            transition: background 0.2s;
            box-shadow: none;
            cursor: pointer;
        }
        .back-btn:hover {
            background: #6a3cd5;
            text-decoration: none;
            color: #fff;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
    <div class="container">
        <h1>Manage Class Schedules</h1>

        <div class="form-section">
            <h2 id="formTitle">Add New Schedule</h2>
            <div id="formMsg"></div>
            <form id="scheduleForm" autocomplete="off">
                <input type="hidden" id="scheduleId" name="id" />
                <label for="teacher_id">Teacher</label>
                <select id="teacher_id" name="teacher_id" required></select>
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required></select>
                <label for="weekday">Class Day</label>
                <select id="weekday" name="weekday" required>
                    <option value="">Select Day</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                </select>
                <label for="start_time">Start Time</label>
                <input type="time" id="start_time" name="start_time" required />
                <label for="end_time">End Time</label>
                <input type="time" id="end_time" name="end_time" required />
                <button type="submit" id="submitBtn">Add Schedule</button>
                <button type="button" id="cancelEdit" style="display:none; margin-left:10px;">Cancel</button>
            </form>
        </div>
        <div class="table-section">
            <h2>Class Schedules</h2>
            <input type="text" id="searchTeacher" placeholder="Search by teacher name..." style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 6px; border: none; background: #334155; color: white; font-size: 16px;" />
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Teacher</th>
                        <th>Course</th>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="scheduleTableBody"></tbody>
            </table>
        </div>
    </div>
    <script>
        let allTeachers = [];
        let allCourses = [];
        let allSchedules = [];

        function fetchTeachers() {
            return fetch('manage_schedules.php?action=fetch_teachers')
                .then(res => res.json())
                .then(data => { allTeachers = data; });
        }
        function fetchCourses() {
            return fetch('manage_schedules.php?action=fetch_courses')
                .then(res => res.json())
                .then(data => { allCourses = data; });
        }
        function fetchSchedules() {
            return fetch('manage_schedules.php?action=fetch')
                .then(res => res.json())
                .then(data => { allSchedules = data; renderSchedules(); });
        }
        function populateDropdown(id, data, valueKey, textKey) {
            const select = document.getElementById(id);
            select.innerHTML = '';
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item[valueKey];
                option.textContent = item[textKey];
                select.appendChild(option);
            });
        }
        function renderSchedules(filterTeacher = '') {
            const tbody = document.getElementById('scheduleTableBody');
            tbody.innerHTML = '';
            let filtered = allSchedules;
            if (filterTeacher) {
                const search = filterTeacher.trim().toLowerCase();
                filtered = allSchedules.filter(sch => sch.teacher_name.toLowerCase().includes(search));
            }
            filtered.forEach(sch => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${sch.id}</td>
                    <td>${sch.teacher_name}</td>
                    <td>${sch.course_title}</td>
                    <td>${sch.weekday}</td>
                    <td>${sch.start_time}</td>
                    <td>${sch.end_time}</td>
                    <td class="action-btns">
                        <button onclick="editSchedule(${sch.id})">Edit</button>
                        <button onclick="deleteSchedule(${sch.id})">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        function showMsg(msg, type) {
            const msgDiv = document.getElementById('formMsg');
            msgDiv.className = 'msg ' + (type || '');
            msgDiv.textContent = msg;
            setTimeout(() => { msgDiv.textContent = ''; msgDiv.className = 'msg'; }, 3000);
        }
        document.getElementById('scheduleForm').onsubmit = function(e) {
            e.preventDefault();
            const id = document.getElementById('scheduleId').value;
            const teacher_id = document.getElementById('teacher_id').value;
            const course_id = document.getElementById('course_id').value;
            const weekday = document.getElementById('weekday').value;
            const start_time = document.getElementById('start_time').value;
            const end_time = document.getElementById('end_time').value;
            const action = id ? 'edit' : 'add';
            const formData = new FormData();
            formData.append('action', action);
            if (id) formData.append('id', id);
            formData.append('teacher_id', teacher_id);
            formData.append('course_id', course_id);
            formData.append('weekday', weekday);
            formData.append('start_time', start_time);
            formData.append('end_time', end_time);
            fetch('manage_schedules.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMsg('Schedule ' + (id ? 'updated' : 'added') + ' successfully!', 'success');
                    fetchSchedules();
                    document.getElementById('scheduleForm').reset();
                    document.getElementById('scheduleId').value = '';
                    document.getElementById('formTitle').textContent = 'Add New Schedule';
                    document.getElementById('submitBtn').textContent = 'Add Schedule';
                    document.getElementById('cancelEdit').style.display = 'none';
                } else {
                    showMsg(data.error || 'Error occurred', 'error');
                }
            });
        };
        window.editSchedule = function(id) {
            const sch = allSchedules.find(s => s.id == id);
            if (!sch) return;
            document.getElementById('scheduleId').value = sch.id;
            document.getElementById('teacher_id').value = sch.teacher_id;
            document.getElementById('course_id').value = sch.course_id;
            document.getElementById('weekday').value = sch.weekday;
            document.getElementById('start_time').value = sch.start_time;
            document.getElementById('end_time').value = sch.end_time;
            document.getElementById('formTitle').textContent = 'Edit Schedule';
            document.getElementById('submitBtn').textContent = 'Update Schedule';
            document.getElementById('cancelEdit').style.display = '';
        };
        document.getElementById('cancelEdit').onclick = function() {
            document.getElementById('scheduleForm').reset();
            document.getElementById('scheduleId').value = '';
            document.getElementById('formTitle').textContent = 'Add New Schedule';
            document.getElementById('submitBtn').textContent = 'Add Schedule';
            document.getElementById('cancelEdit').style.display = 'none';
        };
        window.deleteSchedule = function(id) {
            if (!confirm('Are you sure you want to delete this schedule?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            fetch('manage_schedules.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMsg('Schedule deleted successfully!', 'success');
                    fetchSchedules();
                } else {
                    showMsg(data.error || 'Error occurred', 'error');
                }
            });
        };
        function fetchCoursesForTeacher(teacherId) {
            fetch('manage_schedules.php?action=fetch_courses_for_teacher&teacher_id=' + teacherId)
                .then(res => res.json())
                .then(data => {
                    allCourses = data;
                    populateDropdown('course_id', allCourses, 'id', 'title');
                });
        }
        // When teacher changes, update courses
        document.getElementById('teacher_id').addEventListener('change', function() {
            fetchCoursesForTeacher(this.value);
        });
        // On page load, after fetching teachers:
        document.addEventListener('DOMContentLoaded', async function() {
            await fetchTeachers();
            populateDropdown('teacher_id', allTeachers, 'id', 'name');
            if (allTeachers.length > 0) {
                fetchCoursesForTeacher(allTeachers[0].id);
            }
            fetchSchedules();
        });
        // Add search functionality
        document.getElementById('searchTeacher').addEventListener('input', function() {
            renderSchedules(this.value);
        });
    </script>
</body>
</html> 