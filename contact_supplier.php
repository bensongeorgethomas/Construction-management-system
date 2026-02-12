<?php
require_once 'conn.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'conn.php';

$admin_id = $_SESSION['user_id'];
$report_id = filter_input(INPUT_GET, 'report_id', FILTER_VALIDATE_INT);
$report = null;

// Fetch the original worker report to provide context
if ($report_id) {
    $stmt = $conn->prepare("SELECT wr.*, u.name as worker_name FROM worker_reports wr JOIN users u ON wr.worker_id = u.id WHERE wr.id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all approved suppliers to populate the dropdown
$suppliers = $conn->query("SELECT id, name FROM users WHERE role = 'supplier' AND status = 'approved' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    require_once 'includes/csrf.php';
    requireCSRF();
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $urgency = $_POST['urgency'];

    if ($supplier_id && $subject && $message) {
        $stmt = $conn->prepare("INSERT INTO supplier_requests (admin_id, supplier_id, worker_report_id, subject, message, urgency) VALUES (?, ?, ?, ?, ?, ?)");
        // Use the report ID if it exists, otherwise null
        $bound_report_id = $report_id ?: null;
        $stmt->bind_param("iiisss", $admin_id, $supplier_id, $bound_report_id, $subject, $message, $urgency);
        
        if ($stmt->execute()) {
            // Mark the original worker report as 'reviewed' if it exists
            if ($report_id) {
                $conn->query("UPDATE worker_reports SET status = 'reviewed' WHERE id = $report_id");
            }
            $_SESSION['success'] = "Supply request sent successfully!";
            header("Location: review_submissions.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Supplier - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b; --primary-hover: #d97706; --dark-bg: #1f2937;
            --light-bg: #f9fafb; --card-bg: #ffffff; --text-dark: #111827;
            --text-medium: #4b5563; --border-color: #e5e7eb; --success-color: #10b981;
            --info-color: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-dark); }
        .container { max-width: 800px; margin: 0 auto; padding: 2rem; }
        .main-header { background-color: var(--card-bg); box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 1rem 2rem; margin-bottom: 2rem; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); text-decoration: none; }
        .logo span { color: var(--primary-color); }
        h1 { margin-bottom: 1.5rem; }
        .form-container { background: var(--card-bg); padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px_10px rgba(0,0,0,0.05); }
        .context-card { background: #f0f9ff; border-left: 5px solid var(--info-color); padding: 1.5rem; margin-bottom: 2rem; border-radius: 8px; }
        .context-card h3 { color: #0c4a6e; margin-bottom: 1rem; }
        .context-card p { margin-bottom: 0.5rem; color: #1e40af; }
        .context-card p:last-child { margin-bottom: 0; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color);
            font-size: 1rem; font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3);
            outline: none;
        }
        .btn { display: inline-block; padding: 0.85rem 1.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background-color 0.2s; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
    </style>
</head>
<body>
    <header class="main-header">
        <a href="#" class="logo">Construct<span>.</span></a>
    </header>
    <div class="container">
        <h1>Contact Supplier</h1>
        
        <div class="form-container">
            <?php if ($report): ?>
                <div class="context-card">
                    <h3>Reference Worker Report #<?= htmlspecialchars($report['id']) ?></h3>
                    <p><strong>Worker:</strong> <?= htmlspecialchars($report['worker_name']) ?></p>
                    <p><strong>Report Type:</strong> <?= htmlspecialchars($report['report_type']) ?></p>
                    <p><strong>Details:</strong> "<?= nl2br(htmlspecialchars($report['description'])) ?>"</p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="supplier_id">Select Supplier</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">-- Choose a supplier --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" value="Supply Request for: <?= htmlspecialchars($report['report_type'] ?? 'General Inquiry') ?>" required>
                </div>
                <div class="form-group">
                    <label for="urgency">Urgency</label>
                    <select id="urgency" name="urgency">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="8" required>Please supply the following materials as requested in the worker report:

<?= htmlspecialchars($report['description'] ?? '') ?>

Thank you.</textarea>
                </div>
                <button type="submit" name="send_request" class="btn btn-primary">Send Request</button>
            </form>
        </div>
    </div>
</body>
</html>
