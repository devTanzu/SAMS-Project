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
$teachersCount = $db->query("SELECT COUNT(*) as total FROM teachers")->fetch_assoc()['total'];
$coursesCount = $db->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --primary-color: #7a47e5;
      --sidebar-bg: #1e293b;
      --background-dark: #0f172a;
      --card-bg: #1e293b;
      --text-light: #f1f5f9;
      --text-muted: #94a3b8;
      --success-color: #10b981;
      --danger-color: #ef4444;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--background-dark);
      color: var(--text-light);
    }

    .dashboard {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }

    .sidebar {
      width: 250px;
      background: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      padding: 1.5rem 1rem;
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      z-index: 100;
    }

    .sidebar-header {
      padding-bottom: 1.5rem;
      border-bottom: 1px solid #334155;
      margin-bottom: 1.5rem;
    }

    .sidebar-header h2 {
      color: var(--primary-color);
      font-size: 1.7rem;
      margin: 0;
    }

    .nav-menu {
      list-style: none;
      padding: 0;
      margin: 0;
      flex: 1;
    }

    .nav-item {
      margin-bottom: 0.5rem;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 1rem;
      color: var(--text-light);
      text-decoration: none;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      font-size: 1.1rem;
      transition: background 0.2s;
      cursor: pointer;
    }

    .nav-link:hover,
    .nav-link.active {
      background: var(--primary-color);
      color: #fff;
    }

    .main-content {
      margin-left: 250px;
      width: calc(100% - 250px);
      padding: 2.5rem 2rem;
      transition: all 0.3s;
    }

    .hidden {
      display: none;
    }

    .welcome-section h1 {
      font-size: 2.2rem;
      margin-bottom: 0.5rem;
      color: var(--primary-color);
      text-align: center;
    }

    .welcome-section p {
      color: var(--text-muted);
      font-size: 1.1rem;
      
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }

    .stat-card {
      background: var(--card-bg);
      padding: 1.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
      text-align: center;
    }

    .stat-card h3 {
      color: var(--text-muted);
      font-size: 1rem;
      margin-bottom: 0.5rem;
    }

    .stat-card .value {
      font-size: 1.6rem;
      font-weight: 600;
    }

    .settings-boxes {
      display: flex;
      gap: 2rem;
      flex-wrap: wrap;
      justify-content: flex-start;
      margin: 2rem auto;
      max-width: 1000px;
    }

    .settings-form {
      background: var(--card-bg);
      padding: 2rem;
      border-radius: 12px;
      min-width: 260px;
      flex: 1 1 260px;
      box-sizing: border-box;
      max-width: 400px;
    }

    .settings-form h2 {
      color: var(--primary-color);
      margin-bottom: 1.5rem;
    }

    .settings-form label {
      display: block;
      margin: 1rem 0 0.5rem;
      font-weight: bold;
    }

    .settings-form input {
      width: 100%;
      padding: 0.75rem;
      border: none;
      border-radius: 8px;
      background: #334155;
      color: var(--text-light);
      font-size: 1rem;
    }

    .settings-form button {
      margin-top: 1.5rem;
      padding: 0.75rem 1.5rem;
      background: var(--primary-color);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      cursor: pointer;
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 200px;
        padding: 1rem;
      }

      .main-content {
        margin-left: 200px;
        width: calc(100% - 200px);
      }
    }

    @media (max-width: 576px) {
      .sidebar {
        position: absolute;
        width: 100%;
        height: auto;
        flex-direction: row;
        overflow-x: auto;
      }

      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1rem;
      }
    }

    @media (max-width: 900px) {
      .settings-boxes {
        flex-direction: column;
        gap: 1.5rem;
      }
    }

    /* Modern Card Styles for Profile & Settings */
    .profile-section-card, .settings-section-card {
      background: var(--card-bg);
      border-radius: 24px;
      padding: 2.5rem 2rem 2rem 2rem;
      min-width: 320px;
      max-width: 420px;
      margin: 0 auto;
      box-shadow: 0 4px 24px rgba(143,90,255,0.08);
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .profile-section-card h2, .settings-section-card h2 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      color: #fff;
    }
    .profile-section-card .form-group, .settings-section-card .form-group {
      margin-bottom: 1.5rem;
      width: 100%;
    }
    .profile-section-card label, .settings-section-card label {
      display: block;
      margin-bottom: 0.5rem;
      color: #f4f6fb;
      font-weight: 500;
    }
    .profile-section-card input, .settings-section-card input {
      width: 100%;
      padding: 0.8rem;
      background: #1e293b;
      color: #f4f6fb;
      border: 1px solid #444c5e;
      border-radius: 5px;
      font-size: 1rem;
      transition: border-color 0.3s;
    }
    .profile-section-card input:focus, .settings-section-card input:focus {
      outline: none;
      border-color: var(--primary-color);
    }
    .save-profile-btn, .save-btn {
      width: 100%;
      padding: 1rem;
      background: var(--primary-color);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 1.25rem;
      font-weight: 700;
      cursor: pointer;
      margin-top: 1rem;
      transition: background 0.2s, transform 0.2s;
    }
    .save-profile-btn:hover, .save-btn:hover {
      background: #6a3cd5;
      transform: translateY(-2px) scale(1.03);
    }
    @media (max-width: 600px) {
      .profile-section-card, .settings-section-card {
        min-width: 0;
        width: 100%;
        padding: 1.5rem 0.5rem 1.5rem 0.5rem;
      }
      .save-profile-btn, .save-btn {
        font-size: 1.1rem;
        padding: 1rem 0.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <h2><i class="fas fa-user-shield"></i> Admin</h2>
      </div>
      <ul class="nav-menu">
        <li class="nav-item"><a class="nav-link" onclick="showDashboard()"><i class="fas fa-home"></i> Dashboard</a></li>
        <li class="nav-item"><a href="manage_students.php" class="nav-link"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
        <li class="nav-item"><a href="manage_teachers.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
        <li class="nav-item"><a href="manage_courses.php" class="nav-link"><i class="fas fa-book"></i> Manage Courses</a></li>
        <li class="nav-item"><a href="manage_schedules.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Manage Schedules</a></li>
        <li class="nav-item"><a href="view-reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> View Reports</a></li>
        <li class="nav-item"><a class="nav-link" onclick="showProfile()" id="profileNav"><i class="fas fa-user"></i> Profile</a></li>
        <li class="nav-item"><a class="nav-link" onclick="showSettingsForm()" id="settingsNav"><i class="fas fa-cog"></i> Settings</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Dashboard Section -->
      <section id="dashboardSection">
        <div class="welcome-section">
          <h1>Welcome, Tanjina!</h1>
          <!-- <p>Manage your institution's students, teachers, courses, and reports all in one place.</p> -->
        </div>
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Total Students</h3>
            <div class="value" id="studentsCount"><?= $studentsCount ?></div>
          </div>
          <div class="stat-card">
            <h3>Total Teachers</h3>
            <div class="value" id="teachersCount"><?= $teachersCount ?></div>
          </div>
          <div class="stat-card">
            <h3>Total Courses</h3>
            <div class="value" id="coursesCount"><?= $coursesCount ?></div>
          </div>
          <div class="stat-card">
            <h3>Reports</h3>
            <div class="value" id="reportsCount">--</div>
          </div>
        </div>
      </section>

      <!-- Profile Section -->
      <section id="profileSection" class="hidden">
        <div class="profile-section-card">
          <h2>Edit Profile</h2>
          <div id="profileMsg"></div>
          <form class="profile-settings-form" autocomplete="off">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" id="name" value="Admin User" required />
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" value="admin@example.com" required />
            </div>
            <button type="button" class="save-profile-btn">Save Profile</button>
          </form>
        </div>
      </section>
      <!-- Settings Section -->
      <section id="settingsSection" class="hidden">
        <div class="settings-section-card">
          <h2>Change Password</h2>
          <form class="settings-form" autocomplete="off">
            <div class="form-group">
              <label for="currentPassword">Current Password</label>
              <input type="password" id="currentPassword" placeholder="Enter current password" required />
            </div>
            <div class="form-group">
              <label for="newPassword">New Password</label>
              <input type="password" id="newPassword" placeholder="Enter new password" required />
            </div>
            <div class="form-group">
              <label for="confirmPassword">Confirm Password</label>
              <input type="password" id="confirmPassword" placeholder="Confirm new password" required />
            </div>
            <button type="button" class="save-btn">Change Password</button>
          </form>
        </div>
      </section>
    </main>
  </div>

  <script>
    function showDashboard() {
      document.getElementById("dashboardSection").classList.remove("hidden");
      document.getElementById("profileSection").classList.add("hidden");
      document.getElementById("settingsSection").classList.add("hidden");
      setActiveNav(null);
    }
    function showProfile() {
      document.getElementById("dashboardSection").classList.add("hidden");
      document.getElementById("profileSection").classList.remove("hidden");
      document.getElementById("settingsSection").classList.add("hidden");
      setActiveNav('profileNav');
      fetchAdminProfile();
    }
    function showSettingsForm() {
      document.getElementById("dashboardSection").classList.add("hidden");
      document.getElementById("profileSection").classList.add("hidden");
      document.getElementById("settingsSection").classList.remove("hidden");
      setActiveNav('settingsNav');
      clearPasswordFields();
    }
    function setActiveNav(id) {
      document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
      if (id) document.getElementById(id).classList.add('active');
    }
    // Show dashboard by default
    showDashboard();

    // --- Profile AJAX ---
    function fetchAdminProfile() {
      fetch('admin_profile.php')
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            alert(data.error);
            return;
          }
          document.getElementById('name').value = data.name;
          document.getElementById('email').value = data.email;
        });
    }

    document.querySelector('.profile-settings-form').onsubmit = function(e) {
      e.preventDefault();
      updateAdminProfile();
    };
    document.querySelector('.save-profile-btn').onclick = function(e) {
      e.preventDefault();
      updateAdminProfile();
    };
    function updateAdminProfile() {
      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const saveBtn = document.querySelector('.save-profile-btn');
      if (!name || !email) {
        showProfileMsg('Name and email are required.', 'error');
        return;
      }
      const formData = new FormData();
      formData.append('action', 'update_profile');
      formData.append('name', name);
      formData.append('email', email);

      // Set loading state
      saveBtn.disabled = true;
      const originalText = saveBtn.textContent;
      saveBtn.textContent = 'Saving...';

      fetch('admin_profile.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showProfileMsg('Profile updated successfully!', 'success');
        } else {
          showProfileMsg(data.error || 'Failed to update profile.', 'error');
        }
      })
      .catch(() => {
        showProfileMsg('Network error. Please try again.', 'error');
      })
      .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
      });
    }

    // Helper to show messages
    function showProfileMsg(msg, type) {
      let msgDiv = document.getElementById('profileMsg');
      if (!msgDiv) {
        msgDiv = document.createElement('div');
        msgDiv.id = 'profileMsg';
        msgDiv.style.margin = '10px 0';
        msgDiv.style.textAlign = 'center';
        document.querySelector('.profile-section-card').insertBefore(msgDiv, document.querySelector('.profile-settings-form'));
      }
      msgDiv.textContent = msg;
      msgDiv.style.color = type === 'success' ? '#10b981' : '#ef4444';
      setTimeout(() => { msgDiv.textContent = ''; }, 3000);
    }

    // --- Settings (Change Password) AJAX ---
    document.querySelector('.settings-form').onsubmit = function(e) {
      e.preventDefault();
      changeAdminPassword();
    };
    document.querySelector('.save-btn').onclick = function(e) {
      e.preventDefault();
      changeAdminPassword();
    };
    function changeAdminPassword() {
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      if (!currentPassword || !newPassword || !confirmPassword) {
        alert('All password fields are required.');
        return;
      }
      if (newPassword !== confirmPassword) {
        alert('New passwords do not match.');
        return;
      }
      if (newPassword.length < 8) {
        alert('New password must be at least 8 characters.');
        return;
      }
      const formData = new FormData();
      formData.append('action', 'change_password');
      formData.append('currentPassword', currentPassword);
      formData.append('newPassword', newPassword);
      formData.append('confirmPassword', confirmPassword);
      fetch('admin_profile.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('Password changed successfully!');
          clearPasswordFields();
        } else {
          alert(data.error || 'Failed to change password.');
        }
      });
    }
    function clearPasswordFields() {
      document.getElementById('currentPassword').value = '';
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmPassword').value = '';
    }
  </script>
</body>
</html>
