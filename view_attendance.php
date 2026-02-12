<?php
// Load configuration first (before session_start)
require_once 'config.php';
require_once 'conn.php';
require_once 'includes/csrf.php';

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Validate worker
$worker_id = filter_input(INPUT_GET, 'worker_id', FILTER_VALIDATE_INT);
if (!$worker_id) {
    header("Location: workers.php");
    exit();
}

// Get selected month/year from URL or default to current
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Create DateTime objects
$current_date     = new DateTime("$year-$month-01");
$prev_month_date  = (clone $current_date)->modify('-1 month');
$next_month_date  = (clone $current_date)->modify('+1 month');

// First and last day of selected month
$first_day_of_month = $current_date->format('Y-m-01 00:00:00');
$last_day_of_month  = $current_date->format('Y-m-t 23:59:59');

// --- Handle "Mark as Paid" Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid' && isset($_POST['attendance_id'])) {
    requireCSRF();
    $attendance_id_paid = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
    if ($attendance_id_paid) {
        $stmt = $conn->prepare("UPDATE attendance SET salary_status = 'paid' WHERE id = ? AND worker_id = ?");
        $stmt->bind_param("ii", $attendance_id_paid, $worker_id);
        $stmt->execute();
        $stmt->close();
        header("Location: view_attendance.php?worker_id=$worker_id&month=$month&year=$year");
        exit();
    }
}

// Fetch worker's name
$stmt_name = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt_name->bind_param("i", $worker_id);
$stmt_name->execute();
$worker = $stmt_name->get_result()->fetch_assoc();
$stmt_name->close();
if (!$worker) {
    header("Location: workers.php");
    exit();
}

// Check active timer
$active_timer = null;
$stmt_active = $conn->prepare("SELECT id, login_time FROM attendance WHERE worker_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
$stmt_active->bind_param("i", $worker_id);
$stmt_active->execute();
$active_result = $stmt_active->get_result();
if ($active_result->num_rows > 0) {
    $active_timer = $active_result->fetch_assoc();
}
$stmt_active->close();

// --- Fetch Attendance Records for the Selected Month ---
$all_records = [];
$total_hours_by_day = [];
$total_regular_seconds_month = 0;
$total_overtime_seconds_month = 0;
$total_unpaid_seconds_month = 0;

$stmt_records = $conn->prepare("SELECT id, login_time, logout_time, salary_status 
                                FROM attendance 
                                WHERE worker_id = ? 
                                AND login_time BETWEEN ? AND ? 
                                ORDER BY login_time ASC");
$stmt_records->bind_param("iss", $worker_id, $first_day_of_month, $last_day_of_month);
$stmt_records->execute();
$result = $stmt_records->get_result();

while ($row = $result->fetch_assoc()) {
    $regular_seconds = 0;
    $overtime_seconds = 0;

    if ($row['logout_time']) {
        $login = new DateTime($row['login_time']);
        $logout = new DateTime($row['logout_time']);
        $day_date = $login->format('Y-m-d');
        $work_end_time = new DateTime($day_date . ' 17:00:00'); // 5 PM

        $total_seconds = $logout->getTimestamp() - $login->getTimestamp();

        if ($logout > $work_end_time) {
            $overtime_start = ($login > $work_end_time) ? $login : $work_end_time;
            $overtime_seconds = $logout->getTimestamp() - $overtime_start->getTimestamp();
        }

        $regular_seconds = $total_seconds - $overtime_seconds;

        $total_regular_seconds_month += $regular_seconds;
        $total_overtime_seconds_month += $overtime_seconds;
        if ($row['salary_status'] === 'unpaid') {
            $total_unpaid_seconds_month += $total_seconds;
        }

        $day_of_month = date('j', strtotime($row['login_time']));
        if (!isset($total_hours_by_day[$day_of_month])) {
            $total_hours_by_day[$day_of_month] = 0;
        }
        $total_hours_by_day[$day_of_month] += ($total_seconds / 3600);
    }

    $row['regular_seconds'] = $regular_seconds;
    $row['overtime_seconds'] = $overtime_seconds;
    $all_records[] = $row;
}
$stmt_records->close();
$conn->close();

$days_in_month = $current_date->format('t');
$first_day_of_week = $current_date->format('w');

function format_seconds($seconds) {
    if ($seconds < 0) $seconds = 0;
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf('%d hrs %02d mins', $h, $m);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance for <?= htmlspecialchars($worker['name']) ?></title>
    <link href="admin_style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Specific Calendar Styles overlaying on admin theme */
        .calendar-container { margin-bottom: 2.5rem; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .calendar-header a { font-weight: 600; text-decoration: none; color: var(--primary-color); }
        .calendar-grid { width: 100%; border-collapse: collapse; }
        .calendar-grid th { text-align: center; padding: 1rem; font-weight: 600; color: var(--text-medium); background: var(--light-bg); }
        .calendar-grid td { vertical-align: top; text-align: right; width: 14.28%; height: 120px; border: 1px solid var(--border-color); padding: 0.5rem; }
        .calendar-grid tr:hover td { background-color: transparent; }
        
        .day-number { font-size: 0.9rem; font-weight: 500; }
        .day-with-data { background-color: #fefce8; }
        .day-with-data .day-number { color: var(--primary-color); font-weight: 700; }
        
        .details-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .details-summary span { font-weight: 600; color: var(--text-dark); }
        
        .day-content { height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .day-total-hours { font-size: 0.8rem; color: var(--success-color); font-weight: 600; margin-top: 0.5rem; text-align: right; }
        .live-timer-display { font-size: 0.8rem; color: #ef4444; font-weight: 600; text-align: center; padding: 0.25rem; background-color: #fee2e2; border-radius: 4px; }
        
        /* Status Badges */
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-weight: 500; font-size: 0.8rem; text-transform: capitalize; }
        .status-paid { background-color: #dcfce7; color: #166534; } /* --paid-bg, --paid-text */
        .status-unpaid { background-color: #fef3c7; color: #92400e; } /* --unpaid-bg, --unpaid-text */
        .overtime-cell { background-color: #ffedd5; color: #9a3412; font-weight: 600; } /* --overtime-bg, --overtime-text */

        .no-records { padding: 3rem; text-align: center; color: var(--text-medium); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar_admin.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>Attendance: <?= htmlspecialchars($worker['name']) ?></h1>
                <div class="user-info">
                   <a href="workers.php" class="btn btn-secondary" style="margin-right: 15px;">&larr; Back to Workers</a>
                   Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></strong> | <a href="logout.php">Logout</a>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Summary Cards -->
                <div class="dashboard-grid">
                    <div class="card">
                        <h3>Regular Hours</h3>
                        <div class="value"><?= format_seconds($total_regular_seconds_month) ?></div>
                    </div>
                    <div class="card">
                        <h3>Overtime</h3>
                        <div class="value"><?= format_seconds($total_overtime_seconds_month) ?></div>
                    </div>
                    <div class="card <?= $total_unpaid_seconds_month > 0 ? 'highlight' : '' ?>">
                        <h3>Unpaid Time</h3>
                        <div class="value"><?= format_seconds($total_unpaid_seconds_month) ?></div>
                    </div>
                </div>

                <div class="card calendar-container">
                    <div class="calendar-header">
                        <a href="?worker_id=<?= $worker_id ?>&month=<?= $prev_month_date->format('m') ?>&year=<?= $prev_month_date->format('Y') ?>">&larr; Previous Month</a>
                        <h2><?= $current_date->format('F Y') ?></h2>
                        <a href="?worker_id=<?= $worker_id ?>&month=<?= $next_month_date->format('m') ?>&year=<?= $next_month_date->format('Y') ?>">Next Month &rarr;</a>
                    </div>
                    <table class="calendar-grid">
                        <thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead>
                        <tbody>
                            <tr>
                                <?php
                                for ($i = 0; $i < $first_day_of_week; $i++) echo '<td></td>';
                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    $is_active_day = isset($total_hours_by_day[$day]);
                                    $today = date('Y-m-d');
                                    $current_day_str = date('Y-m-d', strtotime("$year-$month-$day"));
                                    $is_today = ($today === $current_day_str);

                                    echo '<td class="' . ($is_active_day ? 'day-with-data' : '') . '">';
                                    echo '<div class="day-content">';
                                    echo '<div class="day-number">' . $day . '</div>';

                                    if ($is_active_day) {
                                        echo '<div class="day-total-hours">' . number_format($total_hours_by_day[$day], 2) . ' hrs</div>';
                                    }

                                    if ($is_today && $active_timer) {
                                        echo "<div id='liveTimerDisplay' class='live-timer-display' data-start-time='" . htmlspecialchars($active_timer['login_time']) . "'>--:--:--</div>";
                                    }

                                    echo '</div></td>';
                                    if (($day + $first_day_of_week) % 7 == 0) echo '</tr><tr>';
                                }
                                for ($i = ($days_in_month + $first_day_of_week) % 7; $i > 0 && $i < 7; $i++) echo '<td></td>';
                                ?>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card details-container">
                    <div class="details-header">
                        <h3>Detailed Log for <?= $current_date->format('F Y') ?></h3>
                        <!-- Summary moved to top cards -->
                    </div>
                    <div class="table-container" style="box-shadow: none; border: none; padding: 0;">
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Login</th><th>Logout</th>
                                    <th>Regular Hours</th><th>Overtime</th><th>Total Duration</th>
                                    <th>Status</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_records)): ?>
                                    <tr><td colspan="8" class="no-records">No attendance records found for this month.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_records as $record): ?>
                                        <tr>
                                            <td><?= date('D, j M', strtotime($record['login_time'])) ?></td>
                                            <td><?= date('h:i A', strtotime($record['login_time'])) ?></td>
                                            <td><?= $record['logout_time'] ? date('h:i A', strtotime($record['logout_time'])) : '<span class="live-timer-display">ACTIVE</span>' ?></td>
                                            <td><?= $record['logout_time'] ? format_seconds($record['regular_seconds']) : '—' ?></td>
                                            <td class="<?= $record['overtime_seconds'] > 0 ? 'overtime-cell' : '' ?>"><?= $record['logout_time'] ? format_seconds($record['overtime_seconds']) : '—' ?></td>
                                            <td>
                                                <?php
                                                if ($record['logout_time']) {
                                                    $total_duration = $record['regular_seconds'] + $record['overtime_seconds'];
                                                    echo format_seconds($total_duration);
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $record['salary_status'] ?>">
                                                    <?= htmlspecialchars($record['salary_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['salary_status'] === 'unpaid' && $record['logout_time']): ?>
                                                    <form method="POST" action="view_attendance.php?worker_id=<?= $worker_id ?>&month=<?= $month ?>&year=<?= $year ?>">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="mark_paid">
                                                        <input type="hidden" name="attendance_id" value="<?= $record['id'] ?>">
                                                        <button type="submit" class="btn btn-success" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Mark as Paid</button>
                                                    </form>
                                                <?php elseif($record['salary_status'] === 'paid'): ?>
                                                    <button class="btn btn-secondary" disabled style="padding: 0.4rem 0.8rem; font-size: 0.8rem; cursor: not-allowed; opacity: 0.7;">Paid</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const timerDisplay = document.getElementById('liveTimerDisplay');
        if (timerDisplay && timerDisplay.dataset.startTime) {
            const startTime = new Date(timerDisplay.dataset.startTime.replace(' ', 'T'));
            const updateTimerDisplay = () => {
                const now = new Date();
                const elapsed = Math.floor((now.getTime() - startTime.getTime()) / 1000);
                if (elapsed < 0) return;
                const hours = Math.floor(elapsed / 3600);
                const minutes = Math.floor((elapsed % 3600) / 60);
                const seconds = elapsed % 60;
                timerDisplay.textContent = `LIVE ${hours.toString().padStart(2,'0')}:${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
            };
            setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay();
        }
    });
    </script>
</body>
</html>


 