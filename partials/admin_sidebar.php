<?php
// Shared Admin Control Panel sidebar
?>
<aside class="side-nav" role="navigation" aria-label="Admin control panel">
    <p class="adminnav">Admin Control Panel</p>
    <a href="dashboard.php" class="nav-btn">Home</a>
    <div class="nav-group" id="usersGroup">
        <div class="nav-toggle">
            <button class="nav-btn" id="usersToggle" type="button">Users ▾</button>
        </div>
        <div class="sub-nav">
            <a href="register_new.php" class="nav-btn">Add User</a>
            <a href="remove_user.php" class="nav-btn">Remove User</a>
            <a href="user_list.php" class="nav-btn">List Users</a>
        </div>
    </div>
    <a href="../auth/logout.php" class="nav-btn logout-btn">Logout</a>
</aside>
