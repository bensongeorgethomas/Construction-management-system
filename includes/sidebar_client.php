<aside class="sidebar" id="sidebar">
    <h2 class="sidebar-header">Construct.</h2>
    <nav class="sidebar-nav">
        <a href="client_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'client_dashboard.php' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
        </a>
        <a href="my_projects.php" class="<?= basename($_SERVER['PHP_SELF']) == 'my_projects.php' ? 'active' : '' ?>">
            <i data-lucide="briefcase"></i><span>My Projects</span>
        </a>
        <a href="my_tasks.php" class="<?= basename($_SERVER['PHP_SELF']) == 'my_tasks.php' ? 'active' : '' ?>">
            <i data-lucide="list-checks"></i><span>Tasks</span>
        </a>
        <a href="messages.php" class="<?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>">
            <i data-lucide="message-square"></i><span>Messages</span>
        </a>
        <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i data-lucide="user"></i><span>Profile</span>
        </a>
    </nav>         
</aside>
