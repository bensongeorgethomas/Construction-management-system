<?php
require_once 'conn.php';
require_once 'includes/csrf.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. You must be an admin to view this page.");
}

$error_message = '';
$success_message = '';

// Fetch all approved clients
$clients = [];
if ($conn && !$conn->connect_error) {
    $client_sql = "SELECT id, name, phone FROM users WHERE role = 'client' AND status = 'approved' ORDER BY name ASC";
    $client_result = $conn->query($client_sql);
    if ($client_result) {
        $clients = $client_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle form submission
if ($conn && !$conn->connect_error && $_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCSRF();
    
    // --- NEW: BLUEPRINT UPLOAD HANDLING ---
    $blueprint_path = null; // Default to null
    if (isset($_FILES['blueprint']) && $_FILES['blueprint']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/blueprints/';
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['blueprint'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];

        // Validate file type and size
        if (in_array($file_ext, $allowed_exts) && $file['size'] < 5000000) { // 5MB limit
            // Create a unique filename to prevent overwriting
            $unique_name = uniqid('', true) . '.' . $file_ext;
            $destination = $upload_dir . $unique_name;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $blueprint_path = $destination; // Save the path for the database
            } else {
                $error_message = "Failed to move uploaded file.";
            }
        } else {
            $error_message = "Invalid file type or size is too large (Max 5MB). Allowed types: PDF, JPG, PNG.";
        }
    }
    // --- END BLUEPRINT HANDLING ---

    // Original Project Details
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $client_id = $_POST['client_id'] ?? '';
    $completion_percentage = isset($_POST['completion_percentage']) && $_POST['completion_percentage'] !== '' ? floatval($_POST['completion_percentage']) : 0.00;

    // New Land and Cost Details
    $sqft = isset($_POST['sqft']) && $_POST['sqft'] !== '' ? floatval($_POST['sqft']) : null;
    $cent = isset($_POST['cent']) && $_POST['cent'] !== '' ? floatval($_POST['cent']) : null;
    $acre = isset($_POST['acre']) && $_POST['acre'] !== '' ? floatval($_POST['acre']) : null;
    $rate_per_sqft = isset($_POST['rate']) && $_POST['rate'] !== '' ? floatval($_POST['rate']) : null;
    $total_cost_str = $_POST['total'] ?? '0';
    $total_cost = floatval(preg_replace('/[^\d.]/', '', $total_cost_str));
    $location = $_POST['location'] ?? '';
    $owner_name = $_POST['owner'] ?? '';
    $owner_contact = $_POST['contact'] ?? '';

    // Basic Validation
    if (empty($error_message) && ($name && $start_date && $end_date && $status && $client_id)) {
        // MODIFIED: Added blueprint_path to the query
        $sql = "INSERT INTO projects (
                    name, description, start_date, end_date, status, completion_percentage, client_id,
                    sqft, cent, acre, rate_per_sqft, total_cost, location, owner_name, owner_contact,
                    blueprint_path 
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error_message = "Prepare failed: " . $conn->error;
        } else {
            // FIXED: The type definition string now has 16 characters to match the 16 variables.
            $stmt->bind_param("sssssdisddddssss", 
                $name, $description, $start_date, $end_date, $status, $completion_percentage, $client_id,
                $sqft, $cent, $acre, $rate_per_sqft, $total_cost, $location, $owner_name, $owner_contact,
                $blueprint_path 
            );

            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Project added successfully!';
                header("Location: projects.php");
                exit;
            } else {
                $error_message = "Execute failed: " . $stmt->error;
            }
            $stmt->close();
        }
    } else if (empty($error_message)) {
        $error_message = "All required fields must be filled.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Project - Construct.</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b;
            --primary-hover-color: #d97706;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --white-bg: #ffffff;
            --text-dark: #111827;
            --text-medium: #4b5563;
            --text-light: #d1d5db;
            --border-color: #e5e7eb;
            --danger-bg: #fee2e2;
            --danger-text: #b91c1c;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); display: flex; }
        .sidebar { width: 260px; background-color: var(--dark-bg); color: var(--text-light); height: 100vh; padding: 1.5rem; position: fixed; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 2rem; }
        .header h1 { font-size: 2rem; color: var(--text-dark); }
        .form-container { background-color: var(--white-bg); padding: 2.5rem; border-radius: 12px; max-width: 900px; margin: 2rem auto; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-section-header { grid-column: 1 / -1; font-size: 1.1rem; font-weight: 600; color: var(--text-dark); padding-bottom: 0.75rem; border-bottom: 2px solid var(--border-color); margin-top: 1.5rem; margin-bottom: 0.5rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; color: var(--text-dark); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 1rem; font-family: 'Inter', sans-serif; }
        .form-group input[type="file"] { padding: 0.5rem; }
        .form-actions { margin-top: 2.5rem; display: flex; justify-content: flex-end; gap: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; font-size: 1rem; }
        .btn-primary { background-color: var(--primary-color); color: var(--white-bg); }
        .btn-secondary { background-color: var(--border-color); color: var(--text-dark); }
        .error-message { background-color: var(--danger-bg); color: var(--danger-text); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; }
    </style>
</head>
<body>
    <aside class="sidebar"></aside>

    <div class="main-content">
        <header class="header"><h1>Add New Project</h1></header>

        <div class="form-container">
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>

            <form method="POST" action="add_project.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="form-grid">
                    <h3 class="form-section-header">Core Project Information</h3>

                    <div class="form-group">
                        <label for="name">Project Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="client_id">Assign Client</label>
                        <select id="client_id" name="client_id" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id']; ?>" data-name="<?= htmlspecialchars($client['name']); ?>" data-phone="<?= htmlspecialchars($client['phone'] ?? ''); ?>">
                                    <?= htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="on-hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="completion_percentage">Completion Percentage</label><input type="number" id="completion_percentage" name="completion_percentage" step="0.01" min="0" max="100"></div>
                    <div class="form-group"><label for="start_date">Start Date</label><input type="date" id="start_date" name="start_date" required></div>
                    <div class="form-group"><label for="end_date">End Date</label><input type="date" id="end_date" name="end_date" required></div>
                    
                    <div class="form-group full-width">
                        <label for="description">Project Description</label>
                        <textarea id="description" name="description"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="blueprint">Upload Blueprint (PDF, JPG, PNG)</label>
                        <input type="file" id="blueprint" name="blueprint" accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <h3 class="form-section-header">Location & Ownership</h3>
                    <div class="form-group"><label for="owner">Owner Name</label><input type="text" id="owner" name="owner"></div>
                    <div class="form-group"><label for="contact">Contact Number</label><input type="tel" id="contact" name="contact"></div>
                    <div class="form-group full-width"><label for="location">Location / Address</label><input type="text" id="location" name="location"></div>

                    <h3 class="form-section-header">Land & Cost Details</h3>
                    <div class="form-group"><label for="sqft">Square Feet</label><input type="number" step="0.01" id="sqft" name="sqft" oninput="convertFromSqft()"></div>
                    <div class="form-group"><label for="cent">Cent</label><input type="number" step="0.01" id="cent" name="cent" oninput="convertFromCent()"></div>
                    <div class="form-group"><label for="acre">Acre</label><input type="number" step="0.01" id="acre" name="acre" oninput="convertFromAcre()"></div>
                    <div class="form-group"><label for="rate">Rate per Sq.Ft (₹)</label><input type="number" step="0.01" id="rate" name="rate" oninput="calculateCost()"></div>
                    <div class="form-group full-width"><label for="total">Total Estimated Cost (₹)</label><input type="text" id="total" name="total" readonly></div>
                </div>
                <div class="form-actions">
                    <a href="projects.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Project</button>
                </div>
            </form>
        </div>
    </div>
<script>
    // --- Get all required elements from the DOM ---
    const sqftInput = document.getElementById('sqft');
    const centInput = document.getElementById('cent');
    const acreInput = document.getElementById('acre');
    const rateInput = document.getElementById('rate');
    const totalInput = document.getElementById('total');

    // === NEW ELEMENTS FOR AUTOFILL (STEP 3) ===
    const clientSelect = document.getElementById('client_id');
    const ownerInput = document.getElementById('owner');
    const contactInput = document.getElementById('contact');


    // --- Land Area Conversion and Cost Calculation Script ---

    const SQFT_PER_CENT = 435.6;
    const CENTS_PER_ACRE = 100;
    const SQFT_PER_ACRE = SQFT_PER_CENT * CENTS_PER_ACRE;

    function formatCurrency(value) {
        if (isNaN(value) || value === 0) return '';
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(value);
    }
    function calculateCost() {
        const sqft = parseFloat(sqftInput.value) || 0;
        const rate = parseFloat(rateInput.value) || 0;
        totalInput.value = formatCurrency(sqft * rate);
    }
    function convertFromSqft() {
        const sqft = parseFloat(sqftInput.value);
        if (isNaN(sqft)) {
            centInput.value = '';
            acreInput.value = '';
        } else {
            centInput.value = (sqft / SQFT_PER_CENT).toFixed(3);
            acreInput.value = (sqft / SQFT_PER_ACRE).toFixed(4);
        }
        calculateCost();
    }
    function convertFromCent() {
        const cent = parseFloat(centInput.value);
        if (isNaN(cent)) {
            sqftInput.value = '';
            acreInput.value = '';
        } else {
            sqftInput.value = (cent * SQFT_PER_CENT).toFixed(2);
            acreInput.value = (cent / CENTS_PER_ACRE).toFixed(4);
        }
        calculateCost();
    }
    function convertFromAcre() {
        const acre = parseFloat(acreInput.value);
        if (isNaN(acre)) {
            sqftInput.value = '';
            centInput.value = '';
        } else {
            sqftInput.value = (acre * SQFT_PER_ACRE).toFixed(2);
            centInput.value = (acre * CENTS_PER_ACRE).toFixed(2);
        }
        calculateCost();
    }

    // === NEW FUNCTION AND EVENT LISTENER FOR AUTOFILL (STEP 3) ===

    /**
     * Updates the owner and contact fields based on the selected client.
     */
    function updateOwnerDetails() {
        const selectedOption = clientSelect.options[clientSelect.selectedIndex];
        
        // Get the data from the data-* attributes
        const ownerName = selectedOption.dataset.name || '';
        const ownerPhone = selectedOption.dataset.phone || '';
        
        // Set the values of the input fields
        ownerInput.value = ownerName;
        contactInput.value = ownerPhone;
    }

    // Add an event listener to the client dropdown
    clientSelect.addEventListener('change', updateOwnerDetails);

</script>

</body>
</html>
