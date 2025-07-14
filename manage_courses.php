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

// Create courses table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    credit INT NOT NULL,
    department VARCHAR(50) NOT NULL
)";

if (!$db->query($createTable)) {
    die("Error creating table: " . $db->error);
}

// Handle AJAX actions
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'];

    if ($action === 'fetch') {
        $result = $db->query("SELECT id, code, title, credit, department FROM courses ORDER BY id ASC");
        if (!$result) {
            echo json_encode(['error' => 'Failed to fetch courses: ' . $db->error]);
            exit;
        }
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        echo json_encode($courses);
        exit;
    }

    if ($action === 'add') {
        // Get the next available ID
        $result = $db->query("SELECT MAX(id) as max_id FROM courses");
        if (!$result) {
            echo json_encode(['error' => 'Failed to get next ID: ' . $db->error]);
            exit;
        }
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        $code = $db->real_escape_string($_POST['code']);
        $title = $db->real_escape_string($_POST['title']);
        $credit = (int)$_POST['credit'];
        $department = $db->real_escape_string($_POST['department']);

        // Check for duplicate course code
        $check = $db->query("SELECT COUNT(*) as count FROM courses WHERE code = '$code'");
        if (!$check) {
            echo json_encode(['error' => 'Failed to check duplicate code: ' . $db->error]);
            exit;
        }
        $count = $check->fetch_assoc()['count'];
        if ($count > 0) {
            echo json_encode(['error' => 'Course code already exists']);
            exit;
        }

        $sql = "INSERT INTO courses (id, code, title, credit, department) VALUES ($next_id, '$code', '$title', $credit, '$department')";
        if ($db->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to add course: ' . $db->error]);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $code = $db->real_escape_string($_POST['code']);
        $title = $db->real_escape_string($_POST['title']);
        $credit = (int)$_POST['credit'];
        $department = $db->real_escape_string($_POST['department']);

        // Check for duplicate course code (excluding current course)
        $check = $db->query("SELECT COUNT(*) as count FROM courses WHERE code = '$code' AND id != $id");
        if (!$check) {
            echo json_encode(['error' => 'Failed to check duplicate code: ' . $db->error]);
            exit;
        }
        $count = $check->fetch_assoc()['count'];
        if ($count > 0) {
            echo json_encode(['error' => 'Course code already exists']);
            exit;
        }

        $sql = "UPDATE courses SET code='$code', title='$title', credit=$credit, department='$department' WHERE id=$id";
        if ($db->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to update course: ' . $db->error]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        try {
            $db->begin_transaction();
            
            // Delete the course
            $sql = "DELETE FROM courses WHERE id=$id";
            if (!$db->query($sql)) {
                throw new Exception("Failed to delete course: " . $db->error);
            }
            
            // Reorder remaining IDs
            $db->query("SET @count = 0");
            $db->query("UPDATE courses SET id = @count:= @count + 1 ORDER BY id");
            
            // Reset auto increment
            $db->query("ALTER TABLE courses AUTO_INCREMENT = 1");
            
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Courses</title>
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
      box-sizing: border-box;
    }

    input[readonly] {
      background-color: #334155;
      color: white;
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

    .semester-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    .semester-group select {
      width: 100%;
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
  </style>
</head>
<body>

  <header>
    <h1>Manage Courses</h1>
  </header>

  <div style="text-align: left;">
    <a href="dashboard.php" class="back-btn" style="display:inline-block;margin-bottom:1rem;padding:0.5rem 1.2rem;background:#7a47e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
      &larr; Back to Dashboard
    </a>
  </div>

  <div class="container">

    <div class="form-section">
      <h2 id="formTitle">Add New Course</h2>
      <div id="formMsg"></div>
      <form id="courseForm" autocomplete="off">
        <input type="hidden" id="courseId" name="id" />
        <label for="department">Department</label>
        <select id="department" name="department" required onchange="updateCourseCodes()">
          <option value="">Select Department</option>
          <option value="CSE">CSE</option>
          <option value="EEE">EEE</option>
          <option value="BBA">BBA</option>
        </select>

        <label for="course_code">Select Course Code:</label>
        <select id="course_code" name="course_code" class="form-control" onchange="showCourseTitle()">
          <option value="">-- Select Course Code --</option>
        </select>

        <br>

        <label for="course_title">Course Title:</label>
        <input type="text" id="course_title" name="course_title" class="form-control" readonly>

        <label for="credit">Credit Hours</label>
        <input type="number" id="credit" name="credit" placeholder="E.g. 3" min="1" max="6" required>

        <button type="submit" id="submitBtn">Add Course</button>
        <button type="button" id="cancelEdit" style="display:none; margin-left:10px;">Cancel</button>
      </form>
    </div>

    <div class="table-section">
      <h2>Course List</h2>
      <div class="search-box" style="margin-bottom: 20px;">
        <input type="text" id="searchInput" placeholder="Search by code or title..." style="
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
            <th>Code</th>
            <th>Title</th>
            <th>Credit</th>
            <th>Department</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="courseTableBody"></tbody>
      </table>
    </div>

  </div>

  <script>
    let allCourses = []; // Store all courses data
    let isSubmitting = false; // Flag to prevent double submission

    // Map department to course codes and titles
    const departmentCourses = {
        "CSE": {
            "CSE101": "Introduction to Programming",
            "CSE102": "Data Structures and Algorithms",
            "CSE201": "Database Management Systems",
            "CSE202": "Computer Networks",
            "CSE203": "Operating Systems",
            "CSE301": "Software Engineering",
            "CSE302": "Artificial Intelligence",
            "CSE303": "Machine Learning",
            "CSE304": "Web Development",
            "CSE401": "Computer Graphics",
            "CSE402": "Cloud Computing",
            "CSE403": "Mobile App Development",
            "CSE404": "Information Security"
        },
        "BBA": {
            "BBA101": "Principles of Management",
            "BBA102": "Business Mathematics",
            "BBA201": "Financial Accounting",
            "BBA202": "Marketing Management",
            "BBA203": "Microeconomics",
            "BBA301": "Business Communication",
            "BBA302": "Organizational Behavior",
            "BBA303": "Business Statistics",
            "BBA304": "Human Resource Management",
            "BBA401": "Strategic Management",
            "BBA402": "International Business",
            "BBA403": "Business Ethics",
            "BBA404": "Project Management"
        },
        "EEE": {
            "EEE101": "Basic Electrical Engineering",
            "EEE102": "Circuit Theory",
            "EEE201": "Electronics I",
            "EEE203": "Digital Electronics",
            "EEE204": "Signals and Systems",
            "EEE301": "Power Systems",
            "EEE302": "Control Systems",
            "EEE303": "Electrical Machines",
            "EEE304": "Power Electronics",
            "EEE401": "Renewable Energy",
            "EEE402": "Smart Grid Technology",
            "EEE403": "Electric Drives",
            "EEE404": "High Voltage Engineering"
        }
    };

    function showMsg(msg, type) {
      const msgDiv = document.getElementById('formMsg');
      msgDiv.className = 'msg ' + (type || '');
      msgDiv.textContent = msg;
      setTimeout(() => { msgDiv.textContent = ''; msgDiv.className = 'msg'; }, 3000);
    }

    function validateForm(code, title, credit, department) {
      if (!code.trim()) {
        showMsg('Course code is required', 'error');
        return false;
      }
      if (!title.trim()) {
        showMsg('Course title is required', 'error');
        return false;
      }
      if (!department) {
        showMsg('Department is required', 'error');
        return false;
      }
      if (credit < 1 || credit > 6) {
        showMsg('Credit hours must be between 1 and 6', 'error');
        return false;
      }
      return true;
    }

    async function fetchCourses() {
      try {
        const response = await fetch('manage_courses.php?action=fetch');
        const data = await response.json();
        allCourses = data;
        filterAndDisplayCourses('');
      } catch (error) {
        showMsg('Error fetching courses: ' + error.message, 'error');
      }
    }

    function filterAndDisplayCourses(searchTerm) {
      const tbody = document.getElementById('courseTableBody');
      tbody.innerHTML = '';
      
      const filteredCourses = allCourses.filter(course => {
        const searchLower = searchTerm.toLowerCase();
        return course.code.toLowerCase().includes(searchLower) || 
               course.title.toLowerCase().includes(searchLower) ||
               course.department.toLowerCase().includes(searchLower);
      });

      filteredCourses.forEach(course => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${course.id}</td>
          <td>${course.code}</td>
          <td>${course.title}</td>
          <td>${course.credit}</td>
          <td>${course.department}</td>
          <td class="action-btns">
            <button onclick="editCourse(${course.id}, '${course.code.replace(/'/g, "&#39;")}', '${course.title.replace(/'/g, "&#39;")}', ${course.credit}, '${course.department}')" class="edit-btn">Edit</button>
            <button onclick="deleteCourse(${course.id})" class="delete-btn">Delete</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    document.getElementById('courseForm').onsubmit = async function(e) {
      e.preventDefault();
      
      if (isSubmitting) return;
      isSubmitting = true;

      const id = document.getElementById('courseId').value;
      const code = document.getElementById('course_code').value;
      const title = document.getElementById('course_title').value;
      const credit = document.getElementById('credit').value;
      const department = document.getElementById('department').value;
      const action = id ? 'edit' : 'add';

      if (!validateForm(code, title, credit, department)) {
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
        formData.append('code', code);
        formData.append('title', title);
        formData.append('credit', credit);
        formData.append('department', department);

        const response = await fetch('manage_courses.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          showMsg('Course ' + (id ? 'updated' : 'added') + ' successfully!', 'success');
          await fetchCourses();
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

    function resetForm() {
      document.getElementById('courseForm').reset();
      document.getElementById('courseId').value = '';
      document.getElementById('formTitle').textContent = 'Add New Course';
      document.getElementById('submitBtn').textContent = 'Add Course';
      document.getElementById('cancelEdit').style.display = 'none';
    }

    window.editCourse = function(id, code, title, credit, department) {
      document.getElementById('courseId').value = id;
      document.getElementById('course_code').value = code;
      document.getElementById('course_title').value = title;
      document.getElementById('credit').value = credit;
      document.getElementById('department').value = department;
      document.getElementById('formTitle').textContent = 'Edit Course';
      document.getElementById('submitBtn').textContent = 'Update Course';
      document.getElementById('cancelEdit').style.display = '';
      
      // Scroll to form
      document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
    };

    document.getElementById('cancelEdit').onclick = function() {
      resetForm();
    };

    window.deleteCourse = async function(id) {
      if (!confirm('Are you sure you want to delete this course?')) return;

      const deleteBtn = event.target;
      const originalBtnText = deleteBtn.textContent;
      deleteBtn.textContent = 'Deleting...';
      deleteBtn.disabled = true;

      try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const response = await fetch('manage_courses.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          showMsg('Course deleted successfully!', 'success');
          await fetchCourses();
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
        filterAndDisplayCourses(e.target.value);
      }, 300);
    });

    function updateCourseCodes() {
        const dept = document.getElementById('department').value;
        const courseCodeSelect = document.getElementById('course_code');
        courseCodeSelect.innerHTML = '<option value="">-- Select Course Code --</option>';
        document.getElementById('course_title').value = '';
        if (dept && departmentCourses[dept]) {
            for (const code in departmentCourses[dept]) {
                const option = document.createElement('option');
                option.value = code;
                option.text = code;
                courseCodeSelect.appendChild(option);
            }
        }
    }

    function showCourseTitle() {
        const dept = document.getElementById('department').value;
        const code = document.getElementById('course_code').value;
        if (dept && code && departmentCourses[dept] && departmentCourses[dept][code]) {
            document.getElementById('course_title').value = departmentCourses[dept][code];
        } else {
            document.getElementById('course_title').value = '';
        }
    }

    // Initial setup
    fetchCourses();
  </script>

</body>
</html>