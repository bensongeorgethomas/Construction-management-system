<?php
require_once 'conn.php';

// --- SECURITY: WORKER ACCESS ONLY ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

$worker_id = $_SESSION['user_id'];
$worker_name = $_SESSION['name'];
$success_message = '';
$error_message = '';

// --- DATA FETCHING FOR DROPDOWNS ---
$clients = $conn->query("SELECT id, name FROM users WHERE role = 'client' AND status = 'approved' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
// Projects will be loaded via AJAX based on client selection.

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/csrf.php';
    requireCSRF();
    // Sanitize and retrieve form data
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $report_type = $_POST['report_type'] ?? '';
    $report_type_other = ($report_type === 'Other') ? trim($_POST['report_type_other']) : null;
    $description = trim($_POST['description'] ?? '');
    $urgency = $_POST['urgency'] ?? 'Medium';
    $leave_days = ($report_type === 'Worker Injury / Accident') ? filter_input(INPUT_POST, 'leave_days', FILTER_VALIDATE_INT) : null;
    $injury_type = ($report_type === 'Worker Injury / Accident') ? trim($_POST['injury_type']) : null;

    // --- File Upload Handling ---
    $file_path = null;
    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/worker_reports/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $file_name = basename($_FILES['report_file']['name']);
        $unique_name = uniqid('', true) . '_' . $file_name;
        $destination = $upload_dir . $unique_name;

        if (move_uploaded_file($_FILES['report_file']['tmp_name'], $destination)) {
            $file_path = $destination;
        } else {
            $error_message = "Failed to move uploaded file.";
        }
    }

    // --- Database Insertion ---
    if (empty($error_message) && $client_id && $project_id && !empty($report_type) && !empty($description)) {
        try {
            $sql = "INSERT INTO worker_reports (worker_id, client_id, project_id, report_type, report_type_other, description, urgency, file_path, leave_days_required, injury_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iiisssssis",
                $worker_id, $client_id, $project_id, $report_type, $report_type_other,
                $description, $urgency, $file_path, $leave_days, $injury_type
            );
            
            if ($stmt->execute()) {
                $success_message = "Your report has been submitted successfully! The admin has been notified.";
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
        } catch (Exception $e) {
            $error_message = "An error occurred: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill out all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Reporting System - Construct.</title>
    <style>
        :root {
            --primary-color: #f59e0b; --primary-hover: #d97706; --dark-bg: #1f2937;
            --light-bg: #f3f4f6; --card-bg: #ffffff; --text-dark: #111827;
            --text-medium: #4b5563; --border-color: #d1d5db; --safety-orange: #f97316;
            --high-urgency: #dc2626; --medium-urgency: #f59e0b; --low-urgency: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-medium); }
        .container { max-width: 800px; margin: 2rem auto; padding: 1rem; }
        .form-card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 2rem; }
        .card-header { text-align: center; margin-bottom: 2rem; }
        .card-header .icon { color: var(--primary-color); width: 50px; height: 50px; margin-bottom: 0.5rem; }
        .card-header h1 { font-size: 1.8rem; color: var(--text-dark); }
        .form-section { border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem; }
        .form-section h2 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--text-dark); }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 768px) { .form-grid { grid-template-columns: 1fr 1fr; } }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 1rem; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
        .form-group input[readonly] { background-color: #f9fafb; cursor: not-allowed; }
        .radio-group label { display: block; background-color: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 0.5rem; cursor: pointer; }
        .radio-group input[type="radio"]:checked + label { border-color: var(--primary-color); background-color: #fffbeb; box-shadow: 0 0 0 2px var(--primary-color); }
        .radio-group input[type="radio"] { display: none; }
        .conditional-field { display: none; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; }
        .btn { padding: 0.8rem 1.6rem; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-secondary { background-color: var(--border-color); color: var(--text-dark); }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; }
        .alert-success { background-color: #dcfce7; color: #166534; }
        .alert-error { background-color: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <header class="card-header">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h1>Worker Reporting System</h1>
                <p class="text-medium">Report any on-site issues directly to the admin.</p>
            </header>

            <?php if ($success_message): ?><p class="alert alert-success"><?= $success_message ?></p><?php endif; ?>
            <?php if ($error_message): ?><p class="alert alert-error"><?= $error_message ?></p><?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <section class="form-section">
                    <h2>Your Information</h2>
                    <div class="form-grid">
                        <div class="form-group"><label>Worker Name</label><input type="text" value="<?= htmlspecialchars($worker_name) ?>" readonly></div>
                        <div class="form-group"><label>Worker ID</label><input type="text" value="<?= htmlspecialchars($worker_id) ?>" readonly></div>
                        <div class="form-group"><label for="client_id">Select Client</label><select id="client_id" name="client_id" required><option value="">-- Select a Client --</option><?php foreach ($clients as $client) echo "<option value='{$client['id']}'>" . htmlspecialchars($client['name']) . "</option>"; ?></select></div>
                        <div class="form-group"><label for="project_id">Select Project</label><select id="project_id" name="project_id" required disabled><option value="">-- Select a Client First --</option></select></div>
                    </div>
                </section>

                <section class="form-section">
                    <h2>Report Type</h2>
                    <div class="radio-group form-grid">
                        <div class="form-group"><input type="radio" id="type_equip" name="report_type" value="Damaged / Broken Equipment"><label for="type_equip">Damaged Equipment</label></div>
                        <div class="form-group"><input type="radio" id="type_missing" name="report_type" value="Tools or Materials Missing"><label for="type_missing">Missing Tools/Materials</label></div>
                        <div class="form-group"><input type="radio" id="type_stock" name="report_type" value="Low Stock"><label for="type_stock">Low Stock</label></div>
                        <div class="form-group"><input type="radio" id="type_hazard" name="report_type" value="Safety Hazard"><label for="type_hazard">Safety Hazard</label></div>
                        <div class="form-group"><input type="radio" id="type_injury" name="report_type" value="Worker Injury / Accident"><label for="type_injury">Worker Injury / Accident</label></div>
                        <div class="form-group"><input type="radio" id="type_other" name="report_type" value="Other"><label for="type_other">Other</label></div>
                    </div>
                    <div class="form-group conditional-field" id="other_details_field"><label for="report_type_other">Please Specify</label><input type="text" id="report_type_other" name="report_type_other"></div>
                </section>

                <section class="form-section conditional-field" id="injury_details_section">
                    <h2>Injury Details</h2>
                    <div class="form-grid">
                        <div class="form-group"><label for="leave_days">Number of Leave Days Required</label><input type="number" id="leave_days" name="leave_days" min="0"></div>
                        <div class="form-group"><label for="injury_type">Type of Injury</label><input type="text" id="injury_type" name="injury_type" placeholder="e.g., Minor cut, fracture..."></div>
                    </div>
                </section>

                <section class="form-section">
                    <h2>Additional Details</h2>
                    <div class="form-grid">
                        <div class="form-group full-width"><label for="description">Description</label><textarea id="description" name="description" rows="5" placeholder="Explain the problem in detail..." required></textarea></div>
                        <div class="form-group"><label for="report_file">Upload Image/File (Optional)</label><input type="file" id="report_file" name="report_file"></div>
                        <div class="form-group"><label for="urgency">Urgency</label><select id="urgency" name="urgency"><option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option></select></div>
                    </div>
                </section>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Clear</button>
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const clientSelect = document.getElementById('client_id');
            const projectSelect = document.getElementById('project_id');

            clientSelect.addEventListener('change', function() {
                const clientId = this.value;
                projectSelect.disabled = true;
                projectSelect.innerHTML = '<option>Loading projects...</option>';

                if (clientId) {
                    fetch('get_projects.php?client_id=' + clientId)
                        .then(response => response.json())
                        .then(data => {
                            projectSelect.innerHTML = '<option value=\"\">-- Select a Project --</option>';
                            data.forEach(project => {
                                projectSelect.innerHTML += `<option value=\"${project.id}\">${project.name}</option>`;
                            });
                            projectSelect.disabled = false;
                        });
                } else {
                     projectSelect.innerHTML = '<option value=\"\">-- Select a Client First --</option>';
                }
            });

            const reportTypeRadios = document.querySelectorAll('input[name=\"report_type\"]');
            const injurySection = document.getElementById('injury_details_section');
            const otherField = document.getElementById('other_details_field');

            reportTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    injurySection.style.display = (this.value === 'Worker Injury / Accident') ? 'block' : 'none';
                    otherField.style.display = (this.value === 'Other') ? 'block' : 'none';
                });
            });
        });
    </script>
</body>
</html>