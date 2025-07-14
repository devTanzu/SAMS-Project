<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'attendance_system');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Add department column to teachers table if it doesn't exist
$checkColumn = $db->query("SHOW COLUMNS FROM teachers LIKE 'department'");
if ($checkColumn->num_rows === 0) {
    $alterTable = "ALTER TABLE teachers ADD COLUMN department VARCHAR(50) NOT NULL DEFAULT 'CSE'";
    if (!$db->query($alterTable)) {
        die("Error adding department column: " . $db->error);
    }
}

// Remove courses column from teachers table if it exists
$checkCoursesCol = $db->query("SHOW COLUMNS FROM teachers LIKE 'courses'");
if ($checkCoursesCol->num_rows > 0) {
    $db->query("ALTER TABLE teachers DROP COLUMN courses");
}

// Create teacher_courses table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS teacher_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    course_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_course (teacher_id, course_id)
)";

if (!$db->query($createTable)) {
    die("Error creating table: " . $db->error);
}

// Handle AJAX actions
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'];

    if ($action === 'fetch') {
        $result = $db->query("
            SELECT t.id, t.name, t.email, t.username, t.department,
                   GROUP_CONCAT(c.id) as course_ids,
                   GROUP_CONCAT(c.title) as course_titles
            FROM teachers t
            LEFT JOIN teacher_courses tc ON t.id = tc.teacher_id
            LEFT JOIN courses c ON tc.course_id = c.id
            GROUP BY t.id
            ORDER BY t.id ASC
        ");
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $row['courses'] = [];
            if ($row['course_ids']) {
                $ids = explode(',', $row['course_ids']);
                $titles = explode(',', $row['course_titles']);
                for ($i = 0; $i < count($ids); $i++) {
                    $row['courses'][] = [
                        'id' => $ids[$i],
                        'title' => $titles[$i]
                    ];
                }
            }
            unset($row['course_ids'], $row['course_titles']);
            $teachers[] = $row;
        }
        echo json_encode($teachers);
        exit;
    }

    if ($action === 'fetch_courses') {
        $department = $db->real_escape_string($_GET['department']);
        $result = $db->query("SELECT id, code, title FROM courses WHERE department = '$department' ORDER BY code");
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        echo json_encode($courses);
        exit;
    }

    if ($action === 'add') {
        // Get the next available ID
        $result = $db->query("SELECT MAX(id) as max_id FROM teachers");
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        $name = $db->real_escape_string($_POST['teacherName']);
        $email = $db->real_escape_string($_POST['email']);
        $username = $db->real_escape_string($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $department = $db->real_escape_string($_POST['department']);
        $courses = isset($_POST['courses']) ? $_POST['courses'] : '';
        $courseIds = array_filter(array_map('intval', explode(',', $courses)));
        
        try {
            $db->begin_transaction();

            // Insert teacher
            $sql = "INSERT INTO teachers (id, name, email, username, password, department) 
                    VALUES ($next_id, '$name', '$email', '$username', '$password', '$department')";
            if (!$db->query($sql)) {
                throw new Exception("Failed to add teacher: " . $db->error);
            }

            // Insert teacher courses (by ID)
            if (!empty($courseIds)) {
                foreach ($courseIds as $courseId) {
                    $sql = "INSERT INTO teacher_courses (teacher_id, course_id) VALUES ($next_id, $courseId)";
                    $db->query($sql);
                }
            }

            // Now, auto-create a schedule for the next 5 days for each course
            foreach ($courseIds as $course_id) {
                for ($i = 0; $i < 5; $i++) {
                    $date = date('Y-m-d', strtotime("+$i days"));
                    $stmt = $db->prepare("INSERT INTO class_schedules (teacher_id, course_id, class_date, start_time, end_time) VALUES (?, ?, ?, '09:00:00', '10:00:00')");
                    $stmt->bind_param('iis', $next_id, $course_id, $date);
                    $stmt->execute();
                }
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = $db->real_escape_string($_POST['teacherName']);
        $email = $db->real_escape_string($_POST['email']);
        $username = $db->real_escape_string($_POST['username']);
        $department = $db->real_escape_string($_POST['department']);
        $courses = isset($_POST['courses']) ? $_POST['courses'] : '';
        $courseIds = array_filter(array_map('intval', explode(',', $courses)));
        $password = $_POST['password'] ?? '';

        try {
            $db->begin_transaction();

            // Update teacher
            if (!empty($password)) {
                $password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE teachers SET name='$name', email='$email', username='$username', 
                        password='$password', department='$department' WHERE id=$id";
            } else {
                $sql = "UPDATE teachers SET name='$name', email='$email', username='$username', 
                        department='$department' WHERE id=$id";
            }
            if (!$db->query($sql)) {
                throw new Exception("Failed to update teacher: " . $db->error);
            }

            // Update teacher courses (by ID)
            $db->query("DELETE FROM teacher_courses WHERE teacher_id = $id");
            if (!empty($courseIds)) {
                foreach ($courseIds as $courseId) {
                    $sql = "INSERT INTO teacher_courses (teacher_id, course_id) VALUES ($id, $courseId)";
                    $db->query($sql);
                }
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        try {
            $db->begin_transaction();
            
            // Delete teacher (teacher_courses will be deleted automatically due to CASCADE)
            $sql = "DELETE FROM teachers WHERE id=$id";
            if (!$db->query($sql)) {
                throw new Exception("Failed to delete teacher: " . $db->error);
            }
            
            // Reorder remaining IDs
            $db->query("SET @count = 0");
            $db->query("UPDATE teachers SET id = @count:= @count + 1 ORDER BY id");
            
            // Reset auto increment
            $db->query("ALTER TABLE teachers AUTO_INCREMENT = 1");
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
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
  <title>Manage Teachers</title>
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
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      box-sizing: border-box;
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
      background-color: #7a47e5;
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
      padding: 8px 18px;
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

    .msg {
      margin-bottom: 1rem;
      padding: 0.7rem 1rem;
      border-radius: 6px;
      font-weight: 500;
      text-align: center;
    }

    .msg.error { background: #3b1a2b; color: #ff6b81; }
    .msg.success { background: #1a3b2b; color: #28a745; }

    .action-btns {
      display: flex;
      gap: 10px;
    }
    .action-btns button {
      margin: 0;
      min-width: 70px;
    }

    .course-chip {
      display: block;
      background: #7a47e5;
      color: #fff;
      padding: 4px 12px;
      border-radius: 12px;
      margin: 2px 0 2px 0;
      font-size: 0.98em;
      white-space: nowrap;
    }

    .selected-courses {
      margin: 10px 0;
      min-height: 32px;
    }

    .course-select {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-bottom: 15px;
    }

    .course-select select {
      flex: 1;
    }
  </style>
</head>
<body>

  <header>
    <h1>Manage Teachers</h1>
  </header>

  <div style="text-align: left;">
    <a href="dashboard.php" class="back-btn" style="display:inline-block;margin-bottom:1rem;padding:0.5rem 1.2rem;background:#7a47e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
      &larr; Back to Dashboard
    </a>
  </div>

  <div class="container">

    <div class="form-section">
      <h2 id="formTitle">Add New Teacher</h2>
      <div id="formMsg"></div>
      <form id="teacherForm" autocomplete="off">
        <input type="hidden" id="teacherId" name="id" />
        <label for="teacherName">Full Name</label>
        <input type="text" id="teacherName" name="teacherName" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>

        <label for="department">Department</label>
        <select id="department" name="department" required>
          <option value="">Select Department</option>
          <option value="CSE">CSE</option>
          <option value="EEE">EEE</option>
          <option value="BBA">BBA</option>
        </select>

        <label for="courses">Courses</label>
        <div class="course-select">
          <select id="courseSelect"></select>
          <button type="button" id="addCourseBtn">Add</button>
        </div>
        <div id="selectedCourses" class="selected-courses"></div>
        <input type="hidden" id="courses" name="courses" />

        <label for="password">Password <span id="passwordNote">(required for new teacher, leave blank to keep unchanged)</span></label>
        <input type="password" id="password" name="password" minlength="8" />

        <button type="submit" id="submitBtn">Add Teacher</button>
        <button type="button" id="cancelEdit" style="display:none; margin-left:10px;">Cancel</button>
      </form>
    </div>

    <div class="table-section">
      <h2>Teacher List</h2>
      <div class="search-box" style="margin-bottom: 20px;">
        <input type="text" id="searchInput" placeholder="Search by ID, name, or department..." style="
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
            <th>Courses</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="teacherTableBody"></tbody>
      </table>
    </div>

  </div>

  <script>
    let allTeachers = [];
    let isSubmitting = false;
    let selectedCoursesArr = [];
    let allCourses = {};

    // Department-wise courses (copy from students)
    const departmentCourses = {
      CSE: [
        'Introduction to Programming',
        'Database Systems',
        'Computer Networks',
        'Operating Systems',
        'Software Engineering'
      ],
      EEE: [
        'Basic Electrical Engineering',
        'Circuit Theory',
        'Electronics I',
        'Signals and Systems',
        'Power Systems',
        'Digital Electronics'
      ],
      BBA: [
        'Principles of Management',
        'Microeconomics',
        'Business Mathematics',
        'Financial Accounting',
        'Marketing Management',
        'Organizational Behavior'
      ]
    };

    function showMsg(msg, type) {
      const msgDiv = document.getElementById('formMsg');
      msgDiv.className = 'msg ' + (type || '');
      msgDiv.textContent = msg;
      setTimeout(() => { msgDiv.textContent = ''; msgDiv.className = 'msg'; }, 3000);
    }

    async function fetchCoursesForDepartment(department) {
      try {
        const response = await fetch(`manage_teachers.php?action=fetch_courses&department=${encodeURIComponent(department)}`);
        const courses = await response.json();
        allCourses = {};
        courses.forEach(course => {
          allCourses[course.id] = course;
        });
        updateCourseDropdown();
      } catch (error) {
        showMsg('Error fetching courses: ' + error.message, 'error');
      }
    }

    function updateCourseDropdown() {
      const courseDropdown = document.getElementById('courseSelect');
      courseDropdown.innerHTML = '';
      Object.values(allCourses).forEach(course => {
        if (!selectedCoursesArr.includes(course.id)) {
          const option = document.createElement('option');
          option.value = course.id;
          option.textContent = course.title;
          courseDropdown.appendChild(option);
        }
      });
    }

    function renderSelectedCourses() {
      const container = document.getElementById('selectedCourses');
      container.innerHTML = '';
      selectedCoursesArr.forEach(courseId => {
        const course = allCourses[courseId];
        if (!course) return;
        const chip = document.createElement('span');
        chip.textContent = course.title;
        chip.className = 'course-chip';
        const removeBtn = document.createElement('span');
        removeBtn.textContent = ' ×';
        removeBtn.style.cursor = 'pointer';
        removeBtn.style.marginLeft = '6px';
        removeBtn.onclick = function() {
          selectedCoursesArr = selectedCoursesArr.filter(c => c !== courseId);
          renderSelectedCourses();
          updateCourseDropdown();
          document.getElementById('courses').value = selectedCoursesArr.join(',');
        };
        chip.appendChild(removeBtn);
        container.appendChild(chip);
      });
      document.getElementById('courses').value = selectedCoursesArr.join(',');
    }

    document.getElementById('department').addEventListener('change', function() {
      selectedCoursesArr = [];
      renderSelectedCourses();
      fetchCoursesForDepartment(this.value);
    });

    document.getElementById('addCourseBtn').onclick = function() {
      const courseDropdown = document.getElementById('courseSelect');
      const selected = courseDropdown.value;
      if (selected && !selectedCoursesArr.includes(selected)) {
        selectedCoursesArr.push(selected);
        renderSelectedCourses();
        updateCourseDropdown();
      }
    };

    async function fetchTeachers() {
      try {
        const response = await fetch('manage_teachers.php?action=fetch');
        const data = await response.json();
        allTeachers = data;
        filterAndDisplayTeachers('');
      } catch (error) {
        showMsg('Error fetching teachers: ' + error.message, 'error');
      }
    }

    function filterAndDisplayTeachers(searchTerm) {
      const tbody = document.getElementById('teacherTableBody');
      tbody.innerHTML = '';
      
      const filteredTeachers = allTeachers.filter(teacher => {
        const searchLower = searchTerm.toLowerCase();
        return teacher.id.toString().includes(searchLower) || 
               teacher.name.toLowerCase().includes(searchLower) ||
               teacher.email.toLowerCase().includes(searchLower) ||
               teacher.username.toLowerCase().includes(searchLower) ||
               teacher.department.toLowerCase().includes(searchLower);
      });

      filteredTeachers.forEach(teacher => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${teacher.id}</td>
          <td>${teacher.name}</td>
          <td>${teacher.email}</td>
          <td>${teacher.username}</td>
          <td>${teacher.department}</td>
          <td>${
            teacher.courses.map(course => 
              `<span class="course-chip">${course.title}</span>`
            ).join('')
          }</td>
          <td class="action-btns">
            <button onclick='editTeacher(
              ${teacher.id},
              ${JSON.stringify(teacher.name)},
              ${JSON.stringify(teacher.email)},
              ${JSON.stringify(teacher.username)},
              ${JSON.stringify(teacher.department)},
              ${JSON.stringify(teacher.courses)}
            )' class="edit-btn">Edit</button>
            <button onclick="deleteTeacher(${teacher.id})" class="delete-btn">Delete</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    document.getElementById('teacherForm').onsubmit = async function(e) {
      e.preventDefault();
      
      if (isSubmitting) return;
      isSubmitting = true;

      const id = document.getElementById('teacherId').value;
      const name = document.getElementById('teacherName').value;
      const email = document.getElementById('email').value;
      const username = document.getElementById('username').value;
      const department = document.getElementById('department').value;
      const password = document.getElementById('password').value;
      const courses = selectedCoursesArr.join(',');
      const action = id ? 'edit' : 'add';

      if (!validateForm(name, email, username, password, department, !!id)) {
        isSubmitting = false;
        return;
      }

      const submitBtn = document.getElementById('submitBtn');
      const originalBtnText = submitBtn.textContent;
      submitBtn.textContent = 'Processing...';
      submitBtn.disabled = true;

      try {
        const formData = new FormData();
        formData.append('action', action);
        if (id) formData.append('id', id);
        formData.append('teacherName', name);
        formData.append('email', email);
        formData.append('username', username);
        formData.append('department', department);
        if (password) formData.append('password', password);
        formData.append('courses', courses);

        const response = await fetch('manage_teachers.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          showMsg('Teacher ' + (id ? 'updated' : 'added') + ' successfully!', 'success');
          await fetchTeachers();
          resetForm();
        } else {
          showMsg(data.error || 'Error occurred', 'error');
        }
      } catch (error) {
        showMsg('Error: ' + error.message, 'error');
      } finally {
        submitBtn.textContent = originalBtnText;
        submitBtn.disabled = false;
        isSubmitting = false;
      }
    };

    function validateForm(name, email, username, password, department, isEdit) {
      if (!name.trim()) {
        showMsg('Name is required', 'error');
        return false;
      }
      if (!email.trim()) {
        showMsg('Email is required', 'error');
        return false;
      }
      if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        showMsg('Please enter a valid email address', 'error');
        return false;
      }
      if (!username.trim()) {
        showMsg('Username is required', 'error');
        return false;
      }
      if (!department) {
        showMsg('Department is required', 'error');
        return false;
      }
      if (!isEdit && (!password || password.length < 8)) {
        showMsg('Password must be at least 8 characters for new teacher', 'error');
        return false;
      }
      return true;
    }

    function resetForm() {
      document.getElementById('teacherForm').reset();
      document.getElementById('teacherId').value = '';
      document.getElementById('formTitle').textContent = 'Add New Teacher';
      document.getElementById('submitBtn').textContent = 'Add Teacher';
      document.getElementById('cancelEdit').style.display = 'none';
      document.getElementById('passwordNote').textContent = '(required for new teacher, leave blank to keep unchanged)';
      selectedCoursesArr = [];
      renderSelectedCourses();
      updateCourseDropdown();
    }

    window.editTeacher = function(id, name, email, username, department, courses) {
      document.getElementById('teacherId').value = id;
      document.getElementById('teacherName').value = name;
      document.getElementById('email').value = email;
      document.getElementById('username').value = username;
      document.getElementById('department').value = department;
      selectedCoursesArr = courses ? courses.map(c => c.id) : [];
      fetchCoursesForDepartment(department).then(() => {
        renderSelectedCourses();
        updateCourseDropdown();
      });
      document.getElementById('formTitle').textContent = 'Edit Teacher';
      document.getElementById('submitBtn').textContent = 'Update Teacher';
      document.getElementById('cancelEdit').style.display = '';
      document.getElementById('passwordNote').textContent = '(leave blank to keep unchanged)';
      document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
    };

    document.getElementById('cancelEdit').onclick = function() {
      resetForm();
    };

    window.deleteTeacher = async function(id) {
      if (!confirm('Are you sure you want to delete this teacher?')) return;

      const deleteBtn = event.target;
      const originalBtnText = deleteBtn.textContent;
      deleteBtn.textContent = 'Deleting...';
      deleteBtn.disabled = true;

      try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const response = await fetch('manage_teachers.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          showMsg('Teacher deleted successfully!', 'success');
          await fetchTeachers();
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

    // Add event listener for search input with debounce
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function(e) {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filterAndDisplayTeachers(e.target.value);
      }, 300);
    });

    // Initial setup
    fetchTeachers();
  </script>

</body>
</html>
