<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
} elseif ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once('dbconnect.php');

// Handle form submissions for adding categories and subcategories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
       
        switch ($action) {
            case 'add_category':
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $description = mysqli_real_escape_string($conn, $_POST['description']);
                $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : "NULL";
                $season = mysqli_real_escape_string($conn, $_POST['season']);
                
                // Check if category already exists
                $check_query = "SELECT * FROM tbl_categories WHERE name = '$name' AND deleted = 0";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $_SESSION['error'] = "Category already exists!";
                } else {
                    $query = "INSERT INTO tbl_categories (name, description, parent_id, season)
                            VALUES ('$name', '$description', $parent_id, '$season')";
                    if (mysqli_query($conn, $query)) {
                        $_SESSION['message'] = "Category added successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to add category: " . mysqli_error($conn);
                    }
                }
                break;
               
            case 'add_subcategory':
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $description = mysqli_real_escape_string($conn, $_POST['description']);
                $category_id = intval($_POST['category_id']);
                
                // Check if subcategory already exists
                $check_query = "SELECT * FROM tbl_subcategories WHERE name = '$name' AND category_id = $category_id AND deleted = 0";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $_SESSION['error'] = "Subcategory already exists!";
                } else {
                    $query = "INSERT INTO tbl_subcategories
                            (name, description, category_id)
                            VALUES ('$name', '$description', $category_id)";
                    if (mysqli_query($conn, $query)) {
                        $_SESSION['message'] = "Subcategory added successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to add subcategory: " . mysqli_error($conn);
                    }
                }
                break;
                
                case 'edit_category':
                    $category_id = intval($_POST['category_id']);
                    $name = mysqli_real_escape_string($conn, $_POST['name']);
                    $description = mysqli_real_escape_string($conn, $_POST['description']);
                    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : "NULL";
                    $season = mysqli_real_escape_string($conn, $_POST['season']);
                    
                    // Check if category name exists (excluding current category)
                    $check_query = "SELECT * FROM tbl_categories 
                                    WHERE name = '$name' 
                                    AND category_id != $category_id 
                                    AND deleted = 0";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $_SESSION['error'] = "A category with this name already exists!";
                    } else {
                        $query = "UPDATE tbl_categories SET 
                                  name = '$name', 
                                  description = '$description', 
                                  parent_id = $parent_id, 
                                  season = '$season' 
                                  WHERE category_id = $category_id";
                        if (mysqli_query($conn, $query)) {
                            $_SESSION['message'] = "Category updated successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to update category: " . mysqli_error($conn);
                        }
                    }
                    break;
                
                case 'edit_subcategory':
                    $subcategory_id = intval($_POST['subcategory_id']);
                    $name = mysqli_real_escape_string($conn, $_POST['name']);
                    $description = mysqli_real_escape_string($conn, $_POST['description']);
                    $category_id = intval($_POST['category_id']);
                    
                    // Check if subcategory name exists in the same category (excluding current subcategory)
                    $check_query = "SELECT * FROM tbl_subcategories 
                                    WHERE name = '$name' 
                                    AND category_id = $category_id 
                                    AND subcategory_id != $subcategory_id 
                                    AND deleted = 0";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $_SESSION['error'] = "A subcategory with this name already exists in the selected category!";
                    } else {
                        $query = "UPDATE tbl_subcategories SET 
                                  name = '$name', 
                                  description = '$description', 
                                  category_id = $category_id
                                  WHERE subcategory_id = $subcategory_id";
                        if (mysqli_query($conn, $query)) {
                            $_SESSION['message'] = "Subcategory updated successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to update subcategory: " . mysqli_error($conn);
                        }
                    }
                    break;
                
            case 'delete_category':
                $category_id = intval($_POST['category_id']);
                
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // First mark the category as deleted
                    $query = "UPDATE tbl_categories SET deleted = 1 WHERE category_id = $category_id";
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception("Failed to delete category");
                    }
                    
                    // Then mark all related subcategories as deleted
                    $query = "UPDATE tbl_subcategories SET deleted = 1 WHERE category_id = $category_id";
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception("Failed to delete related subcategories");
                    }
                    
                    // If everything is successful, commit the transaction
                    mysqli_commit($conn);
                    $_SESSION['message'] = "Category and its subcategories deleted successfully!";
                } catch (Exception $e) {
                    // If there's an error, rollback the changes
                    mysqli_rollback($conn);
                    $_SESSION['error'] = "Failed to delete: " . $e->getMessage();
                }
                break;
                
                case 'delete_subcategory':
                    $subcategory_id = intval($_POST['subcategory_id']);
                    $query = "UPDATE tbl_subcategories SET deleted = 1 WHERE subcategory_id = $subcategory_id";
                    if (mysqli_query($conn, $query)) {
                        $_SESSION['message'] = "Subcategory deleted successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to delete subcategory: " . mysqli_error($conn);
                    }
                    header("Location: manage-categories.php" . (isset($_GET['view']) ? "?view=" . $_GET['view'] : ""));
                    exit();
                    break;
        }
       
        header("Location: manage-categories.php" . (isset($_GET['view']) ? "?view=" . $_GET['view'] : ""));
        exit();
    }
}

// Fetch all active categories and subcategories for sidebar
$sidebar_query = "SELECT
    c.category_id,
    c.name as category_name,
    s.subcategory_id,
    s.name as subcategory_name
    FROM tbl_categories c
    LEFT JOIN tbl_subcategories s ON c.category_id = s.category_id
    WHERE c.deleted = 0 AND (s.deleted = 0 OR s.deleted IS NULL)
    ORDER BY c.name, s.name";
$sidebar_result = mysqli_query($conn, $sidebar_query);

$sidebar_categories = [];
while ($row = mysqli_fetch_assoc($sidebar_result)) {
    if (!isset($sidebar_categories[$row['category_id']])) {
        $sidebar_categories[$row['category_id']] = [
            'name' => $row['category_name'],
            'subcategories' => []
        ];
    }
    if ($row['subcategory_id']) {
        $sidebar_categories[$row['category_id']]['subcategories'][] = [
            'id' => $row['subcategory_id'],
            'name' => $row['subcategory_name']
        ];
    }
}

// Handle category filter for subcategories view
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Determine what to display based on the view parameter
$view = isset($_GET['view']) ? $_GET['view'] : 'categories';
$table_data = [];

if ($view === 'categories') {
    // Fetch categories
    $query = "SELECT c.*,
              (SELECT COUNT(*) FROM tbl_subcategories WHERE category_id = c.category_id AND deleted = 0) as subcategory_count,
              p.name as parent_name
              FROM tbl_categories c
              LEFT JOIN tbl_categories p ON c.parent_id = p.category_id
              WHERE c.deleted = 0
              ORDER BY c.name";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $table_data[] = $row;
    }
} else {
    // Fetch subcategories with optional category filter
   // Fetch subcategories with optional category filter
$where_clause = $selected_category ? "AND s.category_id = $selected_category" : "";
$query = "SELECT s.*, c.name as category_name
          FROM tbl_subcategories s
          JOIN tbl_categories c ON s.category_id = c.category_id
          WHERE s.deleted = 0 $where_clause  
          ORDER BY c.name, s.name";
$result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $table_data[] = $row;
    }
}

// Fetch categories for dropdown
$categories_query = "SELECT category_id, name FROM tbl_categories WHERE deleted = 0 ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Get seasons for dropdown
$seasons = array("Spring", "Summer", "Fall", "Winter", "All Seasons");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Perfume Paradise</title>
    <style>
        /* Previous CSS remains the same */
        .alert-modal {
    text-align: center;
    padding: 30px;
    max-width: 400px;
}

.alert-icon {
    margin: 0 auto 20px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 2px solid #e74c3c;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alert-icon svg {
    width: 30px;
    height: 30px;
}

.alert-title {
    color: #333;
    margin-bottom: 15px;
    font-size: 24px;
}

.alert-message {
    color: #666;
    margin-bottom: 25px;
    line-height: 1.5;
}

.alert-actions {
    display: flex;
    justify-content: center;
}

.btn-primary {
    background-color: #3498db;
    color: white;
    min-width: 100px;
    border: none;
    border-radius: 4px;
    padding: 10px 20px;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background-color: #2980b9;
}

/* Override existing styles for this specific modal */
#deleteSubcategoryModal .modal-content {
    margin: 15% auto;
}

#deleteSubcategoryModal .close {
    color: #aaa;
}
        .error-text {
            color: #f44336;
            font-size: 0.85em;
            margin-top: 5px;
            display: none; /* Hide by default */
        }
        
        .form-group input:invalid,
        .form-group select:invalid {
            border-color: #f44336;
        }
        .action-bar {
    margin-bottom: 20px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 15px;
}

.add-btn {
    background-color: #5a3921;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    display: flex;
    align-items: center;
}

.add-btn:hover {
    background-color: #7a5941;
}
        .sidebar a svg {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            flex-shrink: 0;
        }

        .sidebar-category svg {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            vertical-align: middle;
        }

        .sidebar-category {
            color: #fff;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 1px solid #3a375f;
            display: flex;
            align-items: center;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            width: 70%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .subcategory-count {
            background-color: #2d2a4b;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f7fc;
    color: #333;
}

.sidebar {
    width: 250px;
    background-color: #2d2a4b;
    height: 100vh;
    position: fixed;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar h2 {
    text-align: center;
    color: #fff;
    padding: 20px;
    background-color: #2d2a4b;
    margin: 0;
}

.sidebar a {
    display: flex;
    align-items: center;
    color: #fff;
    padding: 15px 20px;
    text-decoration: none;
    border-bottom: 1px solid #3a375f;
    transition: all 0.3s ease;
}

.sidebar a svg {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
}

.sidebar a:hover, .sidebar .active {
    background-color: #3a375f;
    color: #fff;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    width: calc(100% - 250px);
    background-color: #f4f7fc;
}

.header {
    background-color: #fff;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header h1 {
    color: #2d2a4b;
    margin: 0;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
    color: #666;
}

.stat-box .number {
    font-size: 2em;
    font-weight: bold;
    color: #2d2a4b;
}

table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background-color: #2d2a4b;
    color: #fff;
    font-weight: bold;
}

tr:hover {
    background-color: #f8f9ff;
}

.actions {
    display: flex;
    gap: 5px;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9em;
}

.btn-activate {
    background-color: #4CAF50;
    color: white;
}

.btn-deactivate {
    background-color: #ff9800;
    color: white;
}

.btn-delete {
    background-color: #f44336;
    color: white;
}

.btn-edit {
    background-color: #2196F3;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}

.status-active {
    color: #4CAF50;
}

.status-inactive {
    color: #ff9800;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #4CAF50;
    color: white;
}

.alert-error {
    background-color: #f44336;
    color: white;
}

.logout-btn {
    background-color: #f44336;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    transition: opacity 0.3s ease;
}

.logout-btn:hover {
    opacity: 0.9;
}
        /* Additional CSS for sidebar categories */
        .sidebar-category {
            color: #fff;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 1px solid #3a375f;
        }
        
        .sidebar-subcategories {
            display: none;
            background-color: #3a375f;
        }
        
        .sidebar-subcategories a {
            padding-left: 40px;
            font-size: 0.9em;
        }
        
        .sidebar-category.active + .sidebar-subcategories {
            display: block;
        }
        
        /* Filter dropdown styles */
        .filter-container {
            margin-bottom: 20px;
        }
        
        .filter-container select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-width: 200px;
        }
        
        /* Form validation styles */
        .error-text {
            color: #f44336;
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
        }
        
        .form-group input:invalid + .error-text,
        .form-group select:invalid + .error-text,
        .form-group textarea:invalid + .error-text {
            display: block;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Perfume Paradise</h2>
       
        <!-- Categories in sidebar -->
        <div class="categories-menu">
            <a href="?view=categories" class="<?php echo $view === 'categories' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24">
                    <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                </svg>
                Categories
            </a>
            <a href="?view=subcategories" class="<?php echo $view === 'subcategories' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24">
                    <path d="M4 20h16a2 2 0 002-2V8a2 2 0 00-2-2h-7L11 3H4a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                    <path d="M8 16h8a1 1 0 001-1v-3a1 1 0 00-1-1h-4l-1-1H8a1 1 0 00-1 1v4a1 1 0 001 1z"/>
                </svg>
                Subcategories
            </a>
            <a href="admindashboard.php">
                <svg viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
                </svg>
                Back
            </a>
        </div>
    </div>
    <div class="main-content">
    <div class="header">
        <h1><?php echo $view === 'categories' ? 'Categories' : 'Subcategories'; ?></h1>
    </div>

    <!-- Add this section right after the header -->
    <div class="action-bar">
        <?php if ($view === 'categories'): ?>
            <button onclick="openAddCategoryModal()" class="add-btn">+ Add Category</button>
        <?php else: ?>
            <button onclick="openAddSubcategoryModal()" class="add-btn">+ Add Subcategory</button>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
<?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($view === 'subcategories'): ?>
        <div class="filter-container">
            <select onchange="filterByCategory(this.value)">
                <option value="">All Categories</option>
                <?php
                mysqli_data_seek($categories_result, 0);
                while ($cat = mysqli_fetch_assoc($categories_result)):
                ?>
                <option value="<?php echo $cat['category_id']; ?>"
                        <?php echo $selected_category == $cat['category_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>

        <table>
    <thead>
        <tr>
            <?php if ($view === 'categories'): ?>
                <th>Name</th>
                <th>Description</th>
                <th>Parent Category</th>
                <th>Season</th>
                <th>Subcategories</th>
                <th>Actions</th>
            <?php else: ?>
                <th>Name</th>
                <th>Description</th>
                <th>Category</th>
                <th>Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($table_data)): ?>
            <tr>
                <td colspan="<?php echo $view === 'categories' ? 6 : 4; ?>" style="text-align: center;">No data found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($table_data as $row): ?>
                <?php if ($view === 'subcategories' && $row['deleted'] == 1) {
                    
                    continue;
                } ?>
                <tr data-subcategory-id="<?php echo $row['subcategory_id'] ?? ''; ?>">
                    <?php if ($view === 'categories'): ?>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo htmlspecialchars($row['parent_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['season']); ?></td>
                        <td><?php echo $row['subcategory_count']; ?></td>
                        <td class="actions">
                            <button class="btn btn-edit" onclick="openEditCategoryModal(<?php 
                                echo $row['category_id']; ?>, 
                                '<?php echo addslashes($row['name']); ?>', 
                                '<?php echo addslashes($row['description']); ?>',
                                '<?php echo $row['parent_id'] ?? ''; ?>',
                                '<?php echo addslashes($row['season']); ?>')">Edit</button>
                            <button class="btn btn-delete" onclick="openDeleteCategoryModal(<?php echo $row['category_id']; ?>)">Delete</button>
                        </td>
                    <?php else: ?>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td class="actions">
                            <button class="btn btn-edit" onclick="openEditSubcategoryModal(<?php 
                                echo $row['subcategory_id']; ?>, 
                                '<?php echo addslashes($row['name']); ?>', 
                                '<?php echo addslashes($row['description']); ?>',
                                <?php echo $row['category_id']; ?>)">Edit</button>
                            <button class="btn btn-delete" onclick="deleteSubcategory(<?php echo $row['subcategory_id']; ?>)">Delete</button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
        <!-- Add Category Modal -->
        <div id="addCategoryModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Add Category</h2>
                <form method="POST" action="" id="addCategoryForm" onsubmit="return validateCategoryForm()">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required>
                        <span class="error-text">Category name is required</span>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="parent_id">Parent Category</label>
                        <select id="parent_id" name="parent_id">
                            <option value="">None</option>
                            <?php
                            mysqli_data_seek($categories_result, 0);
                            while ($cat = mysqli_fetch_assoc($categories_result)):
                            ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="season">Season</label>
                        <select id="season" name="season" required>
                            <option value="">Select Season</option>
                            <?php foreach ($seasons as $season): ?>
                            <option value="<?php echo $season; ?>"><?php echo $season; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="error-text">Season is required</span>
                    </div>
                    <button type="submit" class="btn btn-activate">Add</button>
                </form>
            </div>
        </div>

        <!-- Add Subcategory Modal -->
        <div id="addSubcategoryModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Add Subcategory</h2>
                <form method="POST" action="" id="addSubcategoryForm" onsubmit="return validateSubcategoryForm()">
                    <input type="hidden" name="action" value="add_subcategory">
                    <div class="form-group">
                        <label for="subname">Name</label>
                        <input type="text" id="subname" name="name" required>
                        <span class="error-text">Subcategory name is required</span>
                    </div>
                    <div class="form-group">
                        <label for="subdescription">Description</label>
                        <textarea id="subdescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php
                            mysqli_data_seek($categories_result, 0);
                            while ($cat = mysqli_fetch_assoc($categories_result)):
                            ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <span class="error-text">Category is required</span>
                    </div>
                    <button type="submit" class="btn btn-activate">Add</button>
                </form>
            </div>
        </div>
        
        <!--Edit Category Modal -->
<div id="editCategoryModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Edit Category</h2>
        <form method="POST" action="" id="editCategoryForm" onsubmit="return validateEditCategoryForm()">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="form-group">
                <label for="edit_name">Name</label>
                <input type="text" id="edit_name" name="name" required>
                <span class="error-text">Category name is required</span>
            </div>
            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
    <label for="edit_parent_id">Parent Category</label>
    <select id="edit_parent_id" name="parent_id">
        <option value="">None</option>
        <?php
        mysqli_data_seek($categories_result, 0);
        while ($cat = mysqli_fetch_assoc($categories_result)):
            if ($cat['category_id'] != $_GET['category_id']):  // Prevent self-reference
        ?>
        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
        <?php
            endif;
        endwhile;
        ?>
    </select>
</div>
<div class="form-group">
    <label for="edit_season">Season</label>
    <select id="edit_season" name="season" required>
        <option value="">Select Season</option>
        <?php foreach ($seasons as $season): ?>
        <option value="<?php echo $season; ?>"><?php echo $season; ?></option>
        <?php endforeach; ?>
    </select>
    <span class="error-text">Season is required</span>
</div>
<button type="submit" class="btn btn-activate">Update</button>
</form>
</div>
</div>

<!-- Edit Subcategory Modal -->
<div id="editSubcategoryModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Edit Subcategory</h2>
        <form method="POST" action="" id="editSubcategoryForm" onsubmit="return validateEditSubcategoryForm()">
            <input type="hidden" name="action" value="edit_subcategory">
            <input type="hidden" name="subcategory_id" id="edit_subcategory_id">
            <div class="form-group">
                <label for="edit_subname">Name</label>
                <input type="text" id="edit_subname" name="name" required>
                <span class="error-text">Subcategory name is required</span>
            </div>
            <div class="form-group">
                <label for="edit_subdescription">Description</label>
                <textarea id="edit_subdescription" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="edit_category_id">Category</label>
                <select id="edit_category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php
                    mysqli_data_seek($categories_result, 0);
                    while ($cat = mysqli_fetch_assoc($categories_result)):
                    ?>
                    <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <span class="error-text">Category is required</span>
            </div>
            <button type="submit" class="btn btn-activate">Update</button>
        </form>
    </div>
</div>

<!-- Delete Category Modal -->
<div id="deleteCategoryModal" class="modal">
    <div class="modal-content alert-modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <div class="alert-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12" y2="16"></line>
            </svg>
        </div>
        <h2 class="alert-title">Delete Category</h2>
        <p class="alert-message">Are you sure you want to delete this category? This will also remove all associated subcategories. This action cannot be undone.</p>
        <div class="alert-actions">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" id="delete_category_id">
                <button type="submit" class="btn-primary" style="background-color: #e74c3c;">Delete</button>
                <button type="button" class="btn-primary" style="background-color: #95a5a6; margin-left: 10px;" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
</div>

<!-- Delete Subcategory Modal -->
<div id="deleteSubcategoryModal" class="modal">
    <div class="modal-content alert-modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <div class="alert-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12" y2="16"></line>
            </svg>
        </div>
        <h2 class="alert-title">Delete Subcategory</h2>
        <p class="alert-message">Are you sure you want to delete this subcategory? This action cannot be undone.</p>
        <div class="alert-actions">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_subcategory">
                <input type="hidden" name="subcategory_id" id="delete_subcategory_id">
                <button type="button" class="btn-primary" style="background-color: #e74c3c;" onclick="confirmDeleteSubcategory()">Delete</button>
                <button type="button" class="btn-primary" style="background-color: #95a5a6; margin-left: 10px;" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
</div>

<!-- Previous HTML remains the same until the script section -->

<script>
   // Modal functions
function openAddCategoryModal() {
    const modal = document.getElementById('addCategoryModal');
    modal.style.display = 'block';
    
    // Reset form
    const form = document.getElementById('addCategoryForm');
    form.reset();
    
    // Hide all error messages initially
    const errorTexts = form.querySelectorAll('.error-text');
    errorTexts.forEach(error => error.style.display = 'none');
    
    // Setup validation for this specific form
    setupFormValidation(form);
}

function openAddSubcategoryModal() {
    const modal = document.getElementById('addSubcategoryModal');
    modal.style.display = 'block';
    
    // Reset form
    const form = document.getElementById('addSubcategoryForm');
    form.reset();
    
    // Hide all error messages initially
    const errorTexts = form.querySelectorAll('.error-text');
    errorTexts.forEach(error => error.style.display = 'none');
    
    // Setup validation for this specific form
    setupFormValidation(form);
}

function openEditCategoryModal(id, name, description, parent_id, season) {
    const modal = document.getElementById('editCategoryModal');
    const form = document.getElementById('editCategoryForm');
    
    // Set form values
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_parent_id').value = parent_id || '';
    document.getElementById('edit_season').value = season;
    
    // Reset error messages
    form.querySelectorAll('.error-text').forEach(error => {
        error.style.display = 'none';
    });
    
    modal.style.display = 'block';
    setupFormValidation(form);
}

function openEditSubcategoryModal(id, name, description, category_id) {
    const modal = document.getElementById('editSubcategoryModal');
    const form = document.getElementById('editSubcategoryForm');
    
    // Set form values
    document.getElementById('edit_subcategory_id').value = id;
    document.getElementById('edit_subname').value = name;
    document.getElementById('edit_subdescription').value = description;
    document.getElementById('edit_category_id').value = category_id;
    
    // Reset error messages
    form.querySelectorAll('.error-text').forEach(error => {
        error.style.display = 'none';
    });
    
    modal.style.display = 'block';
    setupFormValidation(form);
}

function openDeleteCategoryModal(id) {
    document.getElementById('delete_category_id').value = id;
    document.getElementById('deleteCategoryModal').style.display = 'block';
}

function openDeleteSubcategoryModal(id) {
  
    const modal = document.getElementById('deleteSubcategoryModal');
    modal.style.display = 'block';
    
}
function deleteSubcategory(subcategory_id) {
    // Set the subcategory ID in the hidden input field
    document.getElementById('delete_subcategory_id').value = subcategory_id;
    
    // Open the delete confirmation modal
    openDeleteSubcategoryModal();
}

function confirmDeleteSubcategory() {
    const subcategory_id = document.getElementById('delete_subcategory_id').value;
    
    fetch('manage-categories.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_subcategory&subcategory_id=${subcategory_id}`,
    })
    .then(response => response.text())
    .then(() => {
        // Hide the deleted subcategory row
        const row = document.querySelector(`tr[data-subcategory-id="${subcategory_id}"]`);
        if (row) {
            row.style.display = 'none';
        }
        
        // Close the modal
        closeModal();
        
        // Reload the page to show the success message
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        // Close the modal
        closeModal();
        
        // Reload the page to show the error message
        window.location.reload();
    });
}
function closeModal() {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}

// Form validation functions
// Function to check if a string contains only letters, whitespaces, and apostrophes
function containsOnlyLetters(input) {
    return /^[A-Za-z\s']+$/.test(input);
}

// Update the validateCategoryForm function
function validateCategoryForm() {
    const form = document.getElementById('addCategoryForm');
    const name = form.querySelector('#name');
    const season = form.querySelector('#season');
    
    let isValid = true;
    
    // Reset error messages
    form.querySelectorAll('.error-text').forEach(error => {
        error.style.display = 'none';
    });
    
    // Validate category name
    if (!name.value.trim()) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Category name is required';
        errorText.style.display = 'block';
        isValid = false;
    } else if (!containsOnlyLetters(name.value.trim())) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Only letters, whitespaces, and apostrophes are allowed';
        errorText.style.display = 'block';
        isValid = false;
    }
    
    // Validate season
    if (!season.value) {
        const errorText = season.nextElementSibling;
        errorText.style.display = 'block';
        isValid = false;
    }
    
    return isValid;
}

// Update the validateSubcategoryForm function
function validateSubcategoryForm() {
    const form = document.getElementById('addSubcategoryForm');
    const name = form.querySelector('#subname');
    const category = form.querySelector('#category_id');
    
    let isValid = true;
    
    // Reset error messages
    form.querySelectorAll('.error-text').forEach(error => {
        error.style.display = 'none';
    });
    
    // Validate subcategory name
    if (!name.value.trim()) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Subcategory name is required';
        errorText.style.display = 'block';
        isValid = false;
    } else if (!containsOnlyLetters(name.value.trim())) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Only letters, whitespaces, and apostrophes are allowed';
        errorText.style.display = 'block';
        isValid = false;
    }
    
    // Validate category
    if (!category.value) {
        const errorText = category.nextElementSibling;
        errorText.style.display = 'block';
        isValid = false;
    }
    
    return isValid;
}

// Update the validateEditCategoryForm function
function validateEditCategoryForm() {
    const form = document.getElementById('editCategoryForm');
    const name = form.querySelector('#edit_name');
    const season = form.querySelector('#edit_season');
    
    let isValid = true;
    
    // Reset error messages
    form.querySelectorAll('.error-text').forEach(error => {
        error.style.display = 'none';
    });
    
    // Validate category name
    if (!name.value.trim()) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Category name is required';
        errorText.style.display = 'block';
        isValid = false;
    } else if (!containsOnlyLetters(name.value.trim())) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Only letters, whitespaces, and apostrophes are allowed';
        errorText.style.display = 'block';
        isValid = false;
    }
    
    // Validate season
    if (!season.value) {
        const errorText = season.nextElementSibling;
        errorText.style.display = 'block';
        isValid = false;
    }
    
    return isValid;
}

// Update the validateEditSubcategoryForm function
function validateEditSubcategoryForm() {
    const form = document.getElementById('editSubcategoryForm');
    const name = form.querySelector('#edit_subname');
    const category = form.querySelector('#edit_category_id');
    
    let isValid = true;
    
    // Reset error messages
    form.querySelectorAll('.error-text').forEach(error => {
        error.style.display = 'none';
    });
    
    // Validate subcategory name
    if (!name.value.trim()) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Subcategory name is required';
        errorText.style.display = 'block';
        isValid = false;
    } else if (!containsOnlyLetters(name.value.trim())) {
        const errorText = name.nextElementSibling;
        errorText.textContent = 'Only letters, whitespaces, and apostrophes are allowed';
        errorText.style.display = 'block';
        isValid = false;
    }
    
    // Validate category
    if (!category.value) {
        const errorText = category.nextElementSibling;
        errorText.style.display = 'block';
        isValid = false;
    }
    
    return isValid;
}
// Form validation setup
function setupFormValidation(form) {
    const requiredFields = form.querySelectorAll('input[required], select[required]');
    
    requiredFields.forEach(field => {
        // Remove existing event listeners
        field.removeEventListener('focus', onFieldFocus);
        field.removeEventListener('input', onFieldInput);
        field.removeEventListener('blur', onFieldBlur);
        
        // Add new event listeners
        field.addEventListener('focus', onFieldFocus);
        field.addEventListener('input', onFieldInput);
        field.addEventListener('blur', onFieldBlur);
    });
}

// Field validation event handlers
function onFieldFocus(event) {
    const errorText = event.target.nextElementSibling;
    if (errorText && errorText.classList.contains('error-text')) {
        errorText.style.display = event.target.value.trim() === '' ? 'block' : 'none';
    }
}

function onFieldInput(event) {
    const errorText = event.target.nextElementSibling;
    if (errorText && errorText.classList.contains('error-text')) {
        errorText.style.display = event.target.value.trim() === '' ? 'block' : 'none';
    }
}

function onFieldBlur(event) {
    const errorText = event.target.nextElementSibling;
    if (errorText && errorText.classList.contains('error-text')) {
        errorText.style.display = event.target.value.trim() === '' ? 'block' : 'none';
    }
}

// Filter function
function filterByCategory(categoryId) {
    window.location.href = '?view=subcategories' + (categoryId ? '&category_id=' + categoryId : '');
}

// Initialize validation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Setup form validations
    const forms = [
        'addCategoryForm',
        'addSubcategoryForm',
        'editCategoryForm',
        'editSubcategoryForm'
    ];
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            setupFormValidation(form);
            
            // Add form submit handlers
            form.addEventListener('submit', function(e) {
                let isValid = false;
                
                switch(formId) {
                    case 'addCategoryForm':
                        isValid = validateCategoryForm();
                        break;
                    case 'addSubcategoryForm':
                        isValid = validateSubcategoryForm();
                        break;
                    case 'editCategoryForm':
                        isValid = validateEditCategoryForm();
                        break;
                    case 'editSubcategoryForm':
                        isValid = validateEditSubcategoryForm();
                        break;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        }
    });
});
</script>
</body>
</html>