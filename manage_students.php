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
        $stmt = $pdo->query("SELECT id, name, email, username, department, course FROM students ORDER BY id ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'add') {
        // Get the next available ID
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM students");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = ($row['max_id'] ?? 0) + 1;

        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $department = trim($_POST['department']);
        $selected_courses = $_POST['courses']; // This is an array
        $course_ids = implode(',', $selected_courses); // Convert to comma-separated string
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Enforce email format: at least 4 chars before @gmail.com
        if (!preg_match('/^[a-zA-Z0-9._%+-]{4,}@gmail\.com$/', $email)) {
            echo json_encode(['error' => 'Email must be at least 4 characters before @gmail.com and use a valid Gmail address.']);
            exit;
        }

        // Check for duplicate email or username
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetchColumn()) {
            echo json_encode(['error' => 'Email or username already exists']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO students (id, name, email, username, department, course, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$next_id, $name, $email, $username, $department, $course_ids, $password])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to add student']);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $department = trim($_POST['department']);
        $selected_courses = $_POST['courses']; // This is an array
        $course_ids = implode(',', $selected_courses); // Convert to comma-separated string
        $password = $_POST['password'] ?? '';

        // Enforce email format: at least 4 chars before @gmail.com
        if (!preg_match('/^[a-zA-Z0-9._%+-]{4,}@gmail\.com$/', $email)) {
            echo json_encode(['error' => 'Email must be at least 4 characters before @gmail.com and use a valid Gmail address.']);
            exit;
        }

        // Check for duplicate email or username (excluding current)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE (email = ? OR username = ?) AND id != ?");
        $stmt->execute([$email, $username, $id]);
        if ($stmt->fetchColumn()) {
            echo json_encode(['error' => 'Email or username already exists']);
            exit;
        }

        if (!empty($password)) {
            $password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE students SET name=?, email=?, username=?, department=?, course=?, password=? WHERE id=?");
            $result = $stmt->execute([$name, $email, $username, $department, $course_ids, $password, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE students SET name=?, email=?, username=?, department=?, course=? WHERE id=?");
            $result = $stmt->execute([$name, $email, $username, $department, $course_ids, $id]);
        }
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to update student']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $pdo->beginTransaction();
            
            // Delete the student
            $stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
            if (!$stmt->execute([$id])) {
                throw new Exception("Failed to delete student");
            }
            
            // Reorder remaining IDs
            $pdo->exec("SET @count = 0");
            $pdo->exec("UPDATE students SET id = @count:= @count + 1 ORDER BY id");
            
            // Reset auto increment
            $pdo->exec("ALTER TABLE students AUTO_INCREMENT = 1");
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'fetch_courses') {
        $stmt = $pdo->query("SELECT id, title, department FROM courses ORDER BY title ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// If not an AJAX request, render the HTML page below
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Students</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #0f172a;
      color: #f1f5f9;
    }

    header {
      background-color: #1e293b;
      padding: 20px;
      text-align: center;
    }

    header h1 {
      margin: 0;
      color: #7a47e5;
    }

    .container {
      padding: 30px;
      max-width: 1000px;
      margin: auto;
    }

    .form-section {
      background-color: #1e293b;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 30px;
    }

    .form-section h2 {
      margin-bottom: 15px;
    }

    label {
      display: block;
      margin: 10px 0 5px;
    }

    input, select {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 6px;
      border: none;
      background-color: #334155;
      color: white;
      font-size: 16px;
      line-height: 1.5;
      box-sizing: border-box;
      appearance: none;
    }

    select {
      background-image: url('data:image/svg+xml;utf8,<svg fill="white" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
      background-repeat: no-repeat;
      background-position: right 10px center;
      background-size: 16px;
    }

    button {
      background-color: #7a47e5;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }

    button:hover {
      background-color: #6a3cd5;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #1e293b;
      border-radius: 12px;
      overflow: hidden;
    }

    th, td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #334155;
    }

    th {
      background-color: #7a47e5;
      color: white;
    }

    tr:hover {
      background-color: #334155;
    }

    .back-link {
      margin: 20px;
      padding: 8px 10px;
      border-radius: 6px;
      background: #64748b;
      color: #fff;
      font-weight: bold;
      text-decoration: none;
      display: inline-block;
    }

    .back-link:hover {
      background: #475569;
    }

    .back-btn {
      display: inline-block;
      margin-bottom: 1rem;
      padding: 0.5rem 1.2rem;
      background: #7a47e5;
      color: #fff;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }

    .action-btns {
      display: flex;
      gap: 10px;
    }
    .action-btns button {
      margin: 0;
      min-width: 70px;
    }

    .msg {
      margin-bottom: 1rem;
      padding: 0.7rem 1rem;
      border-radius: 6px;
      font-weight: 500;
      text-align: center;
    }

    .msg.error { background: #3b1a2b; color: #ff6b81; }
    .msg.success { background: #1a3b2b; color: #28a745; }

    .course-chip {
      display: inline-block;
      background: #7a47e5;
      color: #fff;
      padding: 4px 12px;
      border-radius: 12px;
      margin: 2px 4px 2px 0;
      font-size: 0.98em;
      white-space: nowrap;
    }

    td.name-cell {
      white-space: nowrap;
    }
  </style>
</head>
<body>

  <header>
    <h1>Manage Students</h1>
  </header>

  <div style="text-align: left;">
    <a href="dashboard.php" class="back-btn" style="display:inline-block;margin-bottom:1rem;padding:0.5rem 1.2rem;background:#7a47e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
      &larr; Back to Dashboard
    </a>
  </div>

  <div class="container">

    <div class="form-section">
      <h2 id="formTitle">Add New Student</h2>
      <div id="formMsg"></div>
      <form id="studentForm" autocomplete="off">
        <input type="hidden" id="studentId" name="id" />
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required />
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required pattern="^[a-zA-Z0-9._%+-]{4,}@gmail\.com$" title="Email must be at least 4 characters before @gmail.com and use a valid Gmail address." />
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required />
        <div class="form-group">
            <label for="department">Department</label>
            <select id="department" name="department" required>
                <option value="">Select Department</option>
                <option value="CSE">CSE</option>
                <option value="EEE">EEE</option>
                <option value="BBA">BBA</option>
            </select>
        </div>
        <div class="form-group">
            <label for="courseDropdown">Course</label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="courseDropdown" style="flex:1;"></select>
                <button type="button" id="addCourseBtn">Add</button>
            </div>
            <div id="selectedCourses" style="margin: 10px 0; min-height: 32px;"></div>
            <input type="hidden" id="course" name="course" />
        </div>
        <label for="password">Password <span id="passwordNote">(required for new student, leave blank to keep unchanged)</span></label>
        <input type="password" id="password" name="password" minlength="8" />
        <button type="submit" id="submitBtn">Add Student</button>
        <button type="button" id="cancelEdit" style="display:none; margin-left:10px;">Cancel</button>
      </form>
    </div>

    <div class="table-section">
      <h2>Student List</h2>
      <div class="search-box" style="margin-bottom: 20px;">
        <input type="text" id="searchInput" placeholder="Search by ID or username..." style="
          width: 100%;
          padding: 10px;
          border-radius: 6px;
          border: none;
          background-color: #334155;
          color: white;
          font-size: 16px;
          margin-bottom: 15px;
        ">
      </div>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Username</th>
            <th>Department</th>
            <th>Course</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="studentTableBody"></tbody>
      </table>
    </div>

  </div>

  <script>
    let allCourses = [];
    let selectedCoursesArr = [];

    function fetchAndPopulateCourses() {
      fetch('manage_students.php?action=fetch_courses')
        .then(res => res.json())
        .then(data => {
          allCourses = data;
          updateCourseDropdown();
        });
    }

    function updateCourseDropdown() {
      const dept = document.getElementById('department').value;
      const courseDropdown = document.getElementById('courseDropdown');
      courseDropdown.innerHTML = '';
      allCourses
        .filter(course => !dept || course.department === dept)
        .forEach(course => {
          if (!selectedCoursesArr.includes(course.id.toString())) {
            const option = document.createElement('option');
            option.value = course.id;
            option.textContent = course.title;
            courseDropdown.appendChild(option);
          }
        });
    }

    document.getElementById('department').addEventListener('change', function() {
      selectedCoursesArr = [];
      renderSelectedCourses();
      updateCourseDropdown();
    });

    document.getElementById('addCourseBtn').onclick = function() {
      const courseDropdown = document.getElementById('courseDropdown');
      const selected = courseDropdown.value;
      if (selected && !selectedCoursesArr.includes(selected)) {
        selectedCoursesArr.push(selected);
        renderSelectedCourses();
        updateCourseDropdown();
      }
    };

    function renderSelectedCourses() {
      const container = document.getElementById('selectedCourses');
      container.innerHTML = '';
      selectedCoursesArr.forEach(courseId => {
        const course = allCourses.find(c => c.id.toString() === courseId);
        if (!course) return;
        const chip = document.createElement('span');
        chip.textContent = course.title;
        chip.className = 'course-chip';
        const removeBtn = document.createElement('span');
        removeBtn.textContent = ' ×';
        removeBtn.style.cursor = 'pointer';
        removeBtn.onclick = function() {
          selectedCoursesArr = selectedCoursesArr.filter(c => c !== courseId);
          renderSelectedCourses();
          updateCourseDropdown();
        };
        chip.appendChild(removeBtn);
        container.appendChild(chip);
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      fetchAndPopulateCourses();
      renderSelectedCourses();
      fetchStudents();
    });

    let allStudents = []; // Store all students data

    function fetchStudents() {
      fetch('manage_students.php?action=fetch')
        .then(res => res.json())
        .then(data => {
          allStudents = data; // Store all students
          filterAndDisplayStudents(''); // Display all students initially
        });
    }

    function filterAndDisplayStudents(searchTerm) {
      const tbody = document.getElementById('studentTableBody');
      tbody.innerHTML = '';
      
      const filteredStudents = allStudents.filter(student => {
        const searchLower = searchTerm.toLowerCase();
        return student.id.toString().includes(searchLower) || 
               student.username.toLowerCase().includes(searchLower) ||
               student.name.toLowerCase().includes(searchLower) ||
               student.email.toLowerCase().includes(searchLower);
      });

      filteredStudents.forEach(student => {
        const tr = document.createElement('tr');
        // Convert course IDs to names
        let courseNames = '';
        if (student.course) {
          const courseIds = student.course.split(',').map(id => id.trim());
          courseNames = courseIds.map(cid => {
            const courseObj = allCourses.find(c => c.id.toString() === cid);
            return courseObj ? `<span class="course-chip">${courseObj.title}</span>` : `<span class="course-chip">${cid}</span>`;
          }).join(' ');
        }
        tr.innerHTML = `
          <td>${student.id}</td>
          <td class="name-cell">${student.name}</td>
          <td>${student.email}</td>
          <td>${student.username}</td>
          <td>${student.department}</td>
          <td>${courseNames}</td>
          <td class="action-btns">
            <button onclick="editStudent(${student.id}, '${student.name.replace(/'/g, "&#39;")}', '${student.email}', '${student.username}', '${student.department}', '${student.course.replace(/'/g, "&#39;")}')" class="edit-btn">Edit</button>
            <button onclick="deleteStudent(${student.id})" class="delete-btn">Delete</button>
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

    document.getElementById('studentForm').onsubmit = function(e) {
      e.preventDefault();
      const id = document.getElementById('studentId').value;
      const name = document.getElementById('name').value;
      const email = document.getElementById('email').value;
      const username = document.getElementById('username').value;
      const department = document.getElementById('department').value;
      const password = document.getElementById('password').value;
      const action = id ? 'edit' : 'add';
      if (!id && password.length < 8) {
        showMsg('Password must be at least 8 characters for new student.', 'error');
        return;
      }
      const formData = new FormData();
      formData.append('action', action);
      if (id) formData.append('id', id);
      formData.append('name', name);
      formData.append('email', email);
      formData.append('username', username);
      formData.append('department', department);
      selectedCoursesArr.forEach(cid => formData.append('courses[]', cid));
      if (password) formData.append('password', password);
      fetch('manage_students.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showMsg('Student ' + (id ? 'updated' : 'added') + ' successfully!', 'success');
          fetchStudents();
          document.getElementById('studentForm').reset();
          document.getElementById('studentId').value = '';
          document.getElementById('formTitle').textContent = 'Add New Student';
          document.getElementById('submitBtn').textContent = 'Add Student';
          document.getElementById('cancelEdit').style.display = 'none';
          document.getElementById('passwordNote').textContent = '(required for new student, leave blank to keep unchanged)';
          selectedCoursesArr = [];
          renderSelectedCourses();
          updateCourseDropdown();
        } else {
          showMsg(data.error || 'Error occurred', 'error');
        }
      });
    };

    window.editStudent = function(id, name, email, username, department, course) {
      document.getElementById('studentId').value = id;
      document.getElementById('name').value = name;
      document.getElementById('email').value = email;
      document.getElementById('username').value = username;
      document.getElementById('department').value = department;
      selectedCoursesArr = course ? course.split(',') : [];
      renderSelectedCourses();
      updateCourseDropdown();
      document.getElementById('formTitle').textContent = 'Edit Student';
      document.getElementById('submitBtn').textContent = 'Update Student';
      document.getElementById('cancelEdit').style.display = '';
      document.getElementById('passwordNote').textContent = '(leave blank to keep unchanged)';
    };

    document.getElementById('cancelEdit').onclick = function() {
      document.getElementById('studentForm').reset();
      document.getElementById('studentId').value = '';
      document.getElementById('formTitle').textContent = 'Add New Student';
      document.getElementById('submitBtn').textContent = 'Add Student';
      document.getElementById('cancelEdit').style.display = 'none';
      document.getElementById('passwordNote').textContent = '(required for new student, leave blank to keep unchanged)';
      selectedCoursesArr = [];
      renderSelectedCourses();
      updateCourseDropdown();
    };

    window.deleteStudent = async function(id) {
      const deleteBtn = event.target;
      const originalBtnText = deleteBtn.textContent;
      deleteBtn.textContent = 'Deleting...';
      deleteBtn.disabled = true;

      try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const response = await fetch('manage_students.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          showMsg('Student deleted successfully!', 'success');
          await fetchStudents();
        } else {
          showMsg(data.error || 'Error occurred', 'error');
        }
      } catch (error) {
        showMsg('Error: ' + error.message, 'error');
      } finally {
        deleteBtn.textContent = originalBtnText;
        deleteBtn.disabled = false;
      }
    };

    // Add event listener for search input
    document.getElementById('searchInput').addEventListener('input', function(e) {
      filterAndDisplayStudents(e.target.value);
    });

    // Add styles for buttons
    const style = document.createElement('style');
    style.textContent = `
      .edit-btn {
        background-color: #7a47e5;
        transition: background-color 0.3s;
      }
      .edit-btn:hover {
        background-color: #6a3cd5;
      }
      .delete-btn {
        background-color: #dc2626;
        transition: background-color 0.3s;
      }
      .delete-btn:hover {
        background-color: #b91c1c;
      }
      button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
      }
    `;
    document.head.appendChild(style);
  </script>

</body>
</html>
