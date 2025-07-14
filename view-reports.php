<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$db = new mysqli('localhost', 'root', '', 'attendance_system');
if ($db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

$studentsCount = $db->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Reports</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #7c3aed;
      --secondary-color: #4f46e5;
      --background-dark: #0f172a;
      --card-bg: #1e293b;
      --text-light: #f1f5f9;
      --text-muted: #94a3b8;
      --success-color: #10b981;
      --warning-color: #f59e0b;
      --danger-color: #ef4444;
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--background-dark);
      color: var(--text-light);
      line-height: 1.6;
    }
    header {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      padding: 1.5rem;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    header h1 {
      margin: 0;
      color: white;
      font-size: 2rem;
      font-weight: 600;
    }
    .container {
      padding: 2rem;
      max-width: 1200px;
      margin: auto;
    }
    .form-section {
      background-color: var(--card-bg);
      padding: 2rem;
      border-radius: 16px;
      margin-bottom: 2rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }
    .form-section:hover {
      transform: translateY(-5px);
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
    }
    label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--text-light);
      font-weight: 500;
    }
    input, select {
      width: 100%;
      padding: 0.75rem;
      border-radius: 8px;
      border: 2px solid #334155;
      background-color: #334155;
      color: white;
      transition: all 0.3s ease;
    }
    input:focus, select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
    }
    button {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .table-section {
      background-color: var(--card-bg);
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      position: relative;
    }
    .table-section h2 {
      margin-bottom: 1.5rem;
      color: var(--text-light);
      font-size: 1.5rem;
    }
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background-color: var(--card-bg);
      border-radius: 12px;
      overflow: hidden;
    }
    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #334155;
    }
    th {
      background-color: var(--primary-color);
      color: white;
      font-weight: 600;
    }
    tr:hover {
      background-color: #334155;
    }
    .percentage {
      font-weight: bold;
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
    }
    .percentage.high {
      background-color: rgba(16, 185, 129, 0.2);
      color: var(--success-color);
    }
    .percentage.medium {
      background-color: rgba(245, 158, 11, 0.2);
      color: var(--warning-color);
    }
    .percentage.low {
      background-color: rgba(239, 68, 68, 0.2);
      color: var(--danger-color);
    }
    .stats-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background-color: var(--card-bg);
      padding: 1.5rem;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .stat-card h3 {
      color: var(--text-muted);
      font-size: 1rem;
      margin-bottom: 0.5rem;
    }
    .stat-card .value {
      font-size: 2rem;
      font-weight: 600;
      color: var(--text-light);
    }
    .export-options {
      display: flex;
      gap: 1rem;
      margin-top: 1.5rem;
    }
    .export-btn {
      background-color: #334155;
      color: var(--text-light);
      padding: 0.5rem 1rem;
      border-radius: 6px;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
    }
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      .form-grid {
        grid-template-columns: 1fr;
      }
      .stats-cards {
        grid-template-columns: 1fr;
      }
    }
    .search-wrapper {
        position: relative;
    }
    .floating-search-box {
        position: absolute;
        top: 100%;
        right: 0;
        width: 300px;
        background-color: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        margin-top: 0.5rem;
    }
    .search-container {
        padding: 1rem;
    }
    .search-filters {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .search-select {
        flex: 1;
        padding: 0.5rem;
        border-radius: 6px;
        border: 2px solid #334155;
        background-color: #334155;
        color: white;
    }
    .search-input-group {
        display: flex;
        gap: 0.5rem;
    }
    .search-input-group input {
        flex: 1;
        padding: 0.5rem;
        border-radius: 6px;
        border: 2px solid #334155;
        background-color: #334155;
        color: white;
    }
    .search-btn {
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .search-btn:hover {
        background-color: var(--secondary-color);
    }
    .search-results {
        position: absolute;
        background-color: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        margin-top: 0.5rem;
    }
    .search-result-item {
        padding: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .search-result-item:hover {
        background-color: #334155;
    }
    .close-btn {
        background-color: transparent;
        color: var(--text-muted);
        border: none;
        cursor: pointer;
        padding: 0.5rem;
    }
    .close-btn:hover {
        color: var(--text-light);
    }
    .semester-filters {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .generate-btn-container {
        margin-top: 2rem;
        text-align: center;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .date-range-filters {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .date-input {
        width: 100%;
        padding: 0.75rem;
        border-radius: 8px;
        border: 2px solid #334155;
        background-color: #334155;
        color: white;
    }
    .date-input::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
    }
    .date-input::-webkit-datetime-edit {
        color: white;
    }
    .back-link {
      margin: 20px;
      padding: 8px 18px;
      border-radius: 6px;
      background:rgb(113, 19, 196);
      color: #fff;
      font-weight: bold;
      text-decoration: none;
      display: inline-block;
      text-align: left;
    }
    .back-link:hover {
      background:rgb(8, 14, 178);
    }
  </style>
</head>
<body>

  <header>
    <h1><i class="fas fa-chart-bar"></i> Student Attendance Reports</h1>
  </header>

  <div class="back-button">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
  </div>

  <div class="container">
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Total Students</h3>
        <div class="value"><?= $studentsCount ?></div>
      </div>
    </div>
    <div class="form-section">
      <form>
        <div class="form-grid">
          <div>
            <label for="reportType">Report Type</label>
            <select id="reportType">
              <option value="all">All Attendance Summary</option>
              <option value="monthly">Monthly Student Report</option>
              <option value="semester">Semester Student Report</option>
              <option value="custom">Custom Date Range Student Report</option>
              <option value="course_attendance">Course Attendance Report</option>
            </select>
          </div>
          <div id="monthSemesterGroup">
            <div id="monthSelectGroup">
              <label for="monthSelect">Select Month</label>
              <select id="monthSelect">
                <option value="01">January</option>
                <option value="02">February</option>
                <option value="03">March</option>
                <option value="04">April</option>
                <option value="05">May</option>
                <option value="06">June</option>
                <option value="07">July</option>
                <option value="08">August</option>
                <option value="09">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
              </select>
            </div>
            <div id="semesterSelectGroup" style="display: none;">
              <div class="semester-filters">
                <div>
                  <label for="semesterSelect">Select Semester</label>
                  <select id="semesterSelect">
                    <option value="spring">Spring</option>
                    <option value="summer">Summer</option>
                    <option value="fall">Fall</option>
                    <option value="winter">Winter</option>
                  </select>
                </div>
                <div>
                  <label for="yearSelect">Select Year</label>
                  <select id="yearSelect">
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                  </select>
                </div>
              </div>
            </div>
            <div id="customDateGroup" style="display: none;">
              <div class="date-range-filters">
                <div>
                  <label for="startDate">Start Date</label>
                  <input type="date" id="startDate" class="date-input">
                </div>
                <div>
                  <label for="endDate">End Date</label>
                  <input type="date" id="endDate" class="date-input">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="generate-btn-container">
          <button type="submit">
            <i class="fas fa-sync-alt"></i> Generate Report
          </button>
        </div>
      </form>
    </div>
    <div class="table-section" id="studentReportTable">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2><i class="fas fa-table"></i> Individual Attendance Summary</h2>
        <div class="export-options">
          <div class="search-wrapper">
            <button class="export-btn" onclick="toggleSearchBox()">
              <i class="fas fa-search"></i> Search
            </button>
            <div id="floatingSearchBox" class="floating-search-box" style="display: none;">
              <div class="search-container">
                <div class="search-filters">
                    <select id="searchType" class="search-select">
                        <option value="student">Student</option>
                        <option value="course">Course</option>
                    </select>
                </div>
                <div class="search-input-group">
                  <input type="text" id="floatingSearchInput" placeholder="Enter ID or Name">
                  <button class="search-btn" onclick="performSearch()">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
                <div id="floatingSearchResults" class="search-results" style="display: none;">
                  <!-- Search results will appear here -->
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Total Classes</th>
            <th>Present</th>
            <th>Absent</th>
            <th>Percentage</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>ST101</td>
            <td>Akash Ahmed</td>
            <td>20</td>
            <td>18</td>
            <td>2</td>
            <td class="percentage high">90%</td>
          </tr>
          <tr>
            <td>ST102</td>
            <td>Sadia Karim</td>
            <td>20</td>
            <td>15</td>
            <td>5</td>
            <td class="percentage medium">75%</td>
          </tr>
          <tr>
            <td>ST103</td>
            <td>Rahim Uddin</td>
            <td>20</td>
            <td>13</td>
            <td>7</td>
            <td class="percentage low">65%</td>
          </tr>
          <tr>
            <td>ST104</td>
            <td>Mitu Akter</td>
            <td>20</td>
            <td>20</td>
            <td>0</td>
            <td class="percentage high">100%</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="table-section" id="courseReportTable" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2><i class="fas fa-book"></i> Course Attendance Summary</h2>
        <div class="export-options">
        </div>
      </div>
      <div style="margin-bottom: 1rem;">
        <input type="text" id="courseSearchInput" placeholder="Search by course code or title..." style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 2px solid #334155; background-color: #334155; color: white;">
      </div>
      <table>
        <thead>
          <tr>
            <th>Course ID</th>
            <th>Course Code</th>
            <th>Course Title</th>
            <th>Total Classes</th>
            <th>Total Present</th>
            <th>Total Absent</th>
            <th>Attendance Percentage</th>
          </tr>
        </thead>
        <tbody id="courseAttendanceTableBody">
          <!-- Course attendance data will be dynamically loaded here -->
        </tbody>
      </table>
    </div>
  </div>
  <script>
    document.getElementById('reportType').addEventListener('change', function(e) {
        const monthSemesterGroup = document.getElementById('monthSemesterGroup');
        const monthSelectGroup = document.getElementById('monthSelectGroup');
        const semesterSelectGroup = document.getElementById('semesterSelectGroup');
        const customDateGroup = document.getElementById('customDateGroup');
        const studentReportTable = document.getElementById('studentReportTable');
        const courseReportTable = document.getElementById('courseReportTable');

        if (e.target.value === 'custom') {
            monthSelectGroup.style.display = 'none';
            semesterSelectGroup.style.display = 'none';
            customDateGroup.style.display = 'block';
            studentReportTable.style.display = 'block';
            courseReportTable.style.display = 'none';
        } else if (e.target.value === 'course_attendance') {
            monthSelectGroup.style.display = 'none';
            semesterSelectGroup.style.display = 'none';
            customDateGroup.style.display = 'none'; // Course report doesn't need date filters for now
            studentReportTable.style.display = 'none';
            courseReportTable.style.display = 'block';
        } else {
            customDateGroup.style.display = 'none';
            studentReportTable.style.display = 'block';
            courseReportTable.style.display = 'none';
            if (e.target.value === 'monthly') {
                monthSelectGroup.style.display = 'block';
                semesterSelectGroup.style.display = 'none';
            } else {
                monthSelectGroup.style.display = 'none';
                semesterSelectGroup.style.display = 'block';
            }
        }
    });

    function toggleSearchBox() {
        const searchBox = document.getElementById('floatingSearchBox');
        if (searchBox.style.display === 'none') {
            searchBox.style.display = 'block';
            document.getElementById('floatingSearchInput').focus();
        } else {
            searchBox.style.display = 'none';
            document.getElementById('floatingSearchResults').style.display = 'none';
        }
    }

    async function performSearch() {
        const searchType = document.getElementById('searchType').value;
        const searchTerm = document.getElementById('floatingSearchInput').value;
        if (!searchTerm) return;

        let url;
        let mapFunction;

        if (searchType === 'student') {
            url = `attendance_records.php?action=search_student&searchTerm=${encodeURIComponent(searchTerm)}`;
            mapFunction = item => `
                <div class="search-result-item" onclick="selectResult('student', '${item.id}', '${item.name}')">
                    <strong>${item.id}</strong> - ${item.name}
                </div>
            `;
        } else if (searchType === 'course') {
            url = `attendance_records.php?action=search_course&searchTerm=${encodeURIComponent(searchTerm)}`;
            mapFunction = item => `
                <div class="search-result-item" onclick="selectResult('course', '${item.id}', '${item.code}', '${item.title}')">
                    <strong>${item.code}</strong> - ${item.title}
                </div>
            `;
        }

        try {
            const response = await fetch(url);
            const data = await response.json();
            const resultsDiv = document.getElementById('floatingSearchResults');
            if (data.length > 0) {
                resultsDiv.innerHTML = data.map(mapFunction).join('');
            } else {
                resultsDiv.innerHTML = '<div class="search-result-item">No results found</div>';
            }
            resultsDiv.style.display = 'block';
        } catch (error) {
            console.error('Error searching:', error);
            document.getElementById('floatingSearchResults').innerHTML = 
                '<div class="search-result-item">Error searching</div>';
        }
    }

    function selectResult(type, id, codeOrName, title = null) {
        document.getElementById('floatingSearchBox').style.display = 'none';
        document.getElementById('floatingSearchResults').style.display = 'none';

        if (type === 'student') {
            document.getElementById('reportType').value = 'all'; // Reset to all for individual student view
            fetchReport({ studentId: id });
        } else if (type === 'course') {
            document.getElementById('reportType').value = 'course_attendance'; // Select course attendance report
            // Manually trigger the change event to update display logic
            document.getElementById('reportType').dispatchEvent(new Event('change'));
            fetchReport({ courseId: id });
        }
    }

    function fetchReport(params = {}) {
        const reportType = document.getElementById('reportType').value;
        let url = `attendance_records.php?action=fetch_report&reportType=${reportType}`;

        if (reportType === 'monthly' && document.getElementById('monthSelect')) {
            const month = document.getElementById('monthSelect').value;
            const year = new Date().getFullYear();
            url += `&month=${month}&year=${year}`;
        } else if (reportType === 'semester' && document.getElementById('semesterSelect')) {
            const semester = document.getElementById('semesterSelect').value;
            const year = document.getElementById('yearSelect').value;
            url += `&semester=${semester}&year=${year}`;
        } else if (reportType === 'custom' && document.getElementById('startDate')) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            url += `&startDate=${startDate}&endDate=${endDate}`;
        } else if (reportType === 'course_attendance') {
            // No specific date/month/semester filters for course attendance for now
            // You might add course search or filter later here
        } else if (reportType === 'all') {
            // No date filters for 'all' report
        }

        if (params.studentId) {
            url += `&studentId=${params.studentId}`;
        }
        if (params.courseId) {
            url += `&courseId=${params.courseId}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (reportType === 'course_attendance') {
                    updateCourseAttendanceTable(data);
                } else {
                    updateStudentAttendanceTable(data);
                }
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                alert('Error fetching attendance report');
            });
    }

    function updateStudentAttendanceTable(data) {
        const tableBody = document.querySelector('#studentReportTable tbody');
        tableBody.innerHTML = data.map(student => {
            const percentageClass = student.percentage >= 90 ? 'high' : 
                                  student.percentage >= 75 ? 'medium' : 'low';
            return `
                <tr>
                    <td>${student.student_id}</td>
                    <td>${student.name}</td>
                    <td>${student.total_classes}</td>
                    <td>${student.present}</td>
                    <td>${student.absent}</td>
                    <td class="percentage ${percentageClass}">${student.percentage}%</td>
                </tr>
            `;
        }).join('');
    }

    function updateCourseAttendanceTable(data) {
        const tableBody = document.getElementById('courseAttendanceTableBody');
        console.log('Data received by updateCourseAttendanceTable:', data);
        fetchedCourseAttendanceData = data; // Store the original fetched data
        filterCourseAttendanceTable(''); // Display all courses initially
    }

    function filterCourseAttendanceTable(searchTerm) {
        const tableBody = document.getElementById('courseAttendanceTableBody');
        const filteredData = fetchedCourseAttendanceData.filter(course => 
            course.course_code.toUpperCase().includes(searchTerm.toUpperCase()) || 
            course.course_title.toUpperCase().includes(searchTerm.toUpperCase())
        );

        tableBody.innerHTML = filteredData.map(course => {
            const percentageClass = course.percentage >= 90 ? 'high' : 
                                  course.percentage >= 75 ? 'medium' : 'low';
            return `
                <tr>
                    <td>${course.id}</td>
                    <td>${course.course_code}</td>
                    <td>${course.course_title}</td>
                    <td>${course.total_classes}</td>
                    <td>${course.total_present}</td>
                    <td>${course.total_absent}</td>
                    <td class="percentage ${percentageClass}">${course.percentage}%</td>
                </tr>
            `;
        }).join('');

        if (filteredData.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center;">No course records found for this search.</td>
                </tr>
            `;
        }
    }

    // Variable to store original course attendance data
    let fetchedCourseAttendanceData = [];

    // Event listener for the new course search input
    document.getElementById('courseSearchInput').addEventListener('input', function() {
        filterCourseAttendanceTable(this.value.trim());
    });

    // Set default dates for the date pickers
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    document.getElementById('startDate').value = firstDayOfMonth.toISOString().split('T')[0];
    document.getElementById('endDate').value = lastDayOfMonth.toISOString().split('T')[0];

    // Add form submit handler
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetchReport();
    });

    // Initial report load
    fetchReport();
  </script>

</body>
</html> 