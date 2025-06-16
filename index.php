<?php
session_start();

// info database
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'keuangan_regita';

// koneksi ke database
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // verifikasi user
    if ($user && $user['password'] === $password) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email or password";
    }
}

// logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// diproses saat user sudah login
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // tambah data transaksi baru
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_transaction'])) {
            // transaksi baru
            $category_id = $_POST['category_id'];
            $amount = (float)$_POST['amount'];
            $description = $_POST['description'] ?? '';
            $date = $_POST['date'];
            
            try {
                $stmt = $db->prepare("INSERT INTO transactions (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $category_id, $amount, $description, $date]);
                $message = 'Transaction added successfully';
                header("Location: index.php?page=transaction"); // redirect untuk menghindari resubmission
                exit;
            } catch (PDOException $e) {
                $error = 'Error adding transaction: ' . $e->getMessage();
            }
        }
        
        // tambah kategori baru
        if (isset($_POST['add_category'])) {
            $name = $_POST['name'];
            $type = $_POST['type'];
            
            try {
                $stmt = $db->prepare("INSERT INTO categories (name, type) VALUES (?, ?)");
                $stmt->execute([$name, $type]);
                $message = 'Category added successfully';
                header("Location: index.php?page=category"); // redirect untuk menghindari resubmission
                exit;
            } catch (PDOException $e) {
                $error = 'Error adding category: ' . $e->getMessage();
            }
        }
        
        // update kategori
        if (isset($_POST['update_category'])) {
            $category_id = $_POST['category_id'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            
            try {
                $stmt = $db->prepare("UPDATE categories SET name = ?, type = ? WHERE category_id = ?");
                $stmt->execute([$name, $type, $category_id]);
                $message = 'Category updated successfully';
                header("Location: index.php?page=category"); // redirect untuk menghindari resubmission
                exit;
            } catch (PDOException $e) {
                $error = 'Error updating category: ' . $e->getMessage();
            }
        }
    }

    // hapus transaksi
    if (isset($_GET['delete'])) {
        $transaction_id = (int)$_GET['delete'];
        
        try {
            $stmt = $db->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?");
            $stmt->execute([$transaction_id, $user_id]);
            $message = 'Transaction deleted successfully';
            header("Location: index.php?page=transaction"); // redirect untuk menghindari resubmission
            exit;
        } catch (PDOException $e) {
            $error = 'Error deleting transaction: ' . $e->getMessage();
        }
    }
    
    // hapus kategori
    if (isset($_GET['delete_category'])) {
        $category_id = (int)$_GET['delete_category'];
        
        try {
            // Check if category is used in transactions
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
            $checkStmt->execute([$category_id]);
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                $error = 'Cannot delete category because it is used in transactions';
            } else {
                $stmt = $db->prepare("DELETE FROM categories WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $message = 'Category deleted successfully';
            }
            header("Location: index.php?page=category"); // redirect untuk menghindari resubmission
            exit;
        } catch (PDOException $e) {
            $error = 'Error deleting category: ' . $e->getMessage();
        }
    }

    // edit transaksi
    if (isset($_GET['edit'])) {
        $edit_id = (int)$_GET['edit'];

        $stmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND user_id = ?");
        $stmt->execute([$edit_id, $user_id]);
        $editTransaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editTransaction) {
            $error = "Transaction not found.";
        }
    }
    
    // edit kategori
    if (isset($_GET['edit_category'])) {
        $edit_id = (int)$_GET['edit_category'];

        $stmt = $db->prepare("SELECT * FROM categories WHERE category_id = ?");
        $stmt->execute([$edit_id]);
        $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editCategory) {
            $error = "Category not found.";
        }
    }

    // simpan perubahan edit transaksi
    if (isset($_POST['update_transaction'])) {
        $transaction_id = $_POST['transaction_id'];
        $category_id = $_POST['category_id'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $date = $_POST['date'];

        try {
            $stmt = $db->prepare("UPDATE transactions SET category_id = ?, amount = ?, description = ?, date = ? WHERE transaction_id = ? AND user_id = ?");
            $stmt->execute([$category_id, $amount, $description, $date, $transaction_id, $user_id]);
            $message = "Transaction updated successfully.";
            header("Location: index.php?page=transaction"); // redirect untuk menghindari resubmission
            exit;
        } catch (PDOException $e) {
            $error = 'Error updating transaction: ' . $e->getMessage();
        }
    }

    // menampilkan data transaksi untuk transaksi page
    if ($page === 'transaction' || $page === 'home') {
        $transactionsStmt = $db->prepare("
            SELECT t.*, c.name as category_name, c.type 
            FROM transactions t 
            JOIN categories c ON t.category_id = c.category_id 
            WHERE t.user_id = ?
            ORDER BY t.date DESC, t.transaction_id DESC
            LIMIT 50
        ");
        $transactionsStmt->execute([$user_id]);
        $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Menampilkan semua categories untuk forms dan category page
    $categoriesStmt = $db->query("SELECT * FROM categories ORDER BY type, name");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // menghitung saldo untuk home
    if ($page === 'home' || $page === 'transaction') {
        $incomeStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions t JOIN categories c ON t.category_id = c.category_id WHERE c.type = 'income' AND t.user_id = ?");
        $incomeStmt->execute([$user_id]);
        $income = $incomeStmt->fetchColumn();
        
        $expenseStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions t JOIN categories c ON t.category_id = c.category_id WHERE c.type = 'expense' AND t.user_id = ?");
        $expenseStmt->execute([$user_id]);
        $expense = $expenseStmt->fetchColumn();
        
        $balance = $income - $expense;
    }
}

// Helper function
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="./sft.png" type="image/x-icon">
    <title>SFT v202333500003 by Regita J</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color:rgb(238, 238, 238);
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .auth-container {
            max-width: 400px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .app-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1, h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .user-info {
            text-align: right;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .balance-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        .positive {
            color: #27ae60;
            font-weight: bold;
        }
        .negative {
            color: #e74c3c;
            font-weight: bold;
        }
        .form-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        button, .btn {
            background:rgb(91, 56, 218);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }
        button:hover, .btn:hover {
            background:rgb(60, 38, 147);
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-secondary {
            background:rgb(14, 131, 209);
        }
        .btn-secondary:hover {
            background:rgb(122, 160, 185);
        }
        .btn-warning {
            background: #f39c12;
        }
        .btn-warning:hover {
            background: #d35400;
        }
        a, .btn-cancel {
            background:rgb(226, 163, 163);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            border-left: 4px solid #28a745;
        }
        .error {
            padding: 15px;
            margin-bottom: 20px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 4px;
            border-left: 4px solid #dc3545;
        }
        .text-center {
            text-align: center;
        }
        .mt-3 {
            margin-top: 15px;
        }
        .nav-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .nav-links {
            display: flex;
            gap: 15px;
        }
        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            flex: 1;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="auth-container">
                <h1 class="text-center">Simple Finance Tracker by Regita J</h1>
                <h3 class="text-center">Login</h3>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
                <p class="mt-3 text-center">Don't have an account? <a href="register.php" class="btn btn-secondary">Register here</a></p>
            </div>
        <?php else: ?>
            <div class="app-container">
                <div class="nav-flex">
                    <div class="nav-links">
                        <a href="index.php?page=home" class="btn">Home</a>
                        <a href="index.php?page=transaction" class="btn">Transactions</a>
                        <a href="index.php?page=category" class="btn">Categories</a>
                    </div>
                    <div class="user-info">
                        Welcome, <strong style="margin"><?= htmlspecialchars($_SESSION['name']) ?></strong> 
                        <a href="?logout=1" class="btn btn-secondary">Logout</a>
                    </div>
                </div>
                
                <h1>Simple Finance Tracker by Regita J</h1>
                
                <?php if (isset($message)): ?>
                    <div class="message"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($page === 'home'): ?>
                    <!-- Home Page Content -->
                    <div class="balance-card">
                        <h2>Current Balance: <span class="<?= $balance >= 0 ? 'positive' : 'negative' ?>"><?= formatCurrency($balance) ?></span></h2>
                        <p>Income: <span class="positive"><?= formatCurrency($income) ?></span> | Expense: <span class="negative"><?= formatCurrency($expense) ?></span></p>
                    </div>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Recent Incomes</h3>
                            <?php 
                                $recentIncomes = array_filter($transactions, function($t) { return $t['type'] === 'income'; });
                                $recentIncomes = array_slice($recentIncomes, 0, 5);
                            ?>
                            <?php if (empty($recentIncomes)): ?>
                                <p>No recent income transactions</p>
                            <?php else: ?>
                                <ul style="text-align: left; padding-left: 20px;">
                                    <?php foreach ($recentIncomes as $income): ?>
                                        <li>
                                            <?= htmlspecialchars($income['date']) ?>: 
                                            <?= htmlspecialchars($income['category_name']) ?> - 
                                            <span class="positive"><?= formatCurrency($income['amount']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stat-card">
                            <h3>Recent Expenses</h3>
                            <?php 
                                $recentExpenses = array_filter($transactions, function($t) { return $t['type'] === 'expense'; });
                                $recentExpenses = array_slice($recentExpenses, 0, 5);
                            ?>
                            <?php if (empty($recentExpenses)): ?>
                                <p>No recent expense transactions</p>
                            <?php else: ?>
                                <ul style="text-align: left; padding-left: 20px;">
                                    <?php foreach ($recentExpenses as $expense): ?>
                                        <li>
                                            <?= htmlspecialchars($expense['date']) ?>: 
                                            <?= htmlspecialchars($expense['category_name']) ?> - 
                                            <span class="negative"><?= formatCurrency($expense['amount']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2>Recent Transactions</h2>
                    <?php if (empty($transactions)): ?>
                        <p>No transactions found.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($transactions, 0, 10) as $transaction): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transaction['date']) ?></td>
                                        <td><?= htmlspecialchars($transaction['category_name']) ?></td>
                                        <td><?= htmlspecialchars($transaction['description'] ?? '-') ?></td>
                                        <td class="<?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>">
                                            <?= formatCurrency($transaction['amount']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                <?php elseif ($page === 'transaction'): ?>
                    <!-- Transaction Page Content -->
                    <div class="balance-card">
                        <h2>Current Balance: <span class="<?= $balance >= 0 ? 'positive' : 'negative' ?>"><?= formatCurrency($balance) ?></span></h2>
                        <p>Income: <span class="positive"><?= formatCurrency($income) ?></span> | Expense: <span class="negative"><?= formatCurrency($expense) ?></span></p>
                    </div>
                    
                    <?php if (isset($_GET['edit'])): ?>
                        <!-- Tampilkan Form Edit -->
                        <div class="form-card">
                            <h2>Edit Transaction</h2>
                            <form method="post">
                                <input type="hidden" name="transaction_id" value="<?= $editTransaction['transaction_id'] ?>">
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select name="category_id" id="category_id" required>
                                        <option value="" disabled>--Select categories--</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>" <?= $category['category_id'] == $editTransaction['category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?> (<?= ucfirst($category['type']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                    <input type="number" name="amount" id="amount" step="0.01" min="0" value="<?= $editTransaction['amount'] ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea name="description" id="description"><?= htmlspecialchars($editTransaction['description']) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="date">Date</label>
                                    <input type="date" name="date" id="date" value="<?= $editTransaction['date'] ?>" required>
                                </div>
                                <button type="submit" name="update_transaction">Update Transaction</button>
                                <a href="index.php?page=transaction" class="btn btn-cancel">Cancel</a>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Tampilkan Form Tambah -->
                        <div class="form-card">
                            <h2>Add Transaction</h2>
                            <form method="post">
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select name="category_id" id="category_id" required>
                                        <option value="" disabled selected>--Select categories--</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>">
                                                <?= htmlspecialchars($category['name']) ?> (<?= ucfirst($category['type']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                    <input type="number" name="amount" id="amount" step="0.01" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea name="description" id="description" placeholder="Optional description"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="date">Date</label>
                                    <input type="date" name="date" id="date" required value="<?= date('Y-m-d') ?>">
                                </div>
                                <button type="submit" name="add_transaction">Add Transaction</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <h2>Transaction History</h2>
                    <?php if (empty($transactions)): ?>
                        <p>No transactions found. Add your first transaction above.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transaction['date']) ?></td>
                                        <td><?= htmlspecialchars($transaction['category_name']) ?></td>
                                        <td><?= htmlspecialchars($transaction['description'] ?? '-') ?></td>
                                        <td class="<?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>">
                                            <?= formatCurrency($transaction['amount']) ?>
                                        </td>
                                        <td>
                                            <a href="?page=transaction&edit=<?= $transaction['transaction_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="?page=transaction&delete=<?= $transaction['transaction_id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this transaction?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                <?php elseif ($page === 'category'): ?>
                    <!-- Category Page Content -->
                    <?php if (isset($_GET['edit_category'])): ?>
                        <!-- Tampilkan Form Edit Category -->
                        <div class="form-card">
                            <h2>Edit Category</h2>
                            <form method="post">
                                <input type="hidden" name="category_id" value="<?= $editCategory['category_id'] ?>">
                                <div class="form-group">
                                    <label for="name">Category Name</label>
                                    <input type="text" name="name" id="name" value="<?= htmlspecialchars($editCategory['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="type">Type</label>
                                    <select name="type" id="type" required>
                                        <option value="income" <?= $editCategory['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                        <option value="expense" <?= $editCategory['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_category">Update Category</button>
                                <a href="index.php?page=category" class="btn btn-cancel">Cancel</a>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Tampilkan Form Tambah Category -->
                        <div class="form-card">
                            <h2>Add New Category</h2>
                            <form method="post">
                                <div class="form-group">
                                    <label for="name">Category Name</label>
                                    <input type="text" name="name" id="name" required>
                                </div>
                                <div class="form-group">
                                    <label for="type">Type</label>
                                    <select name="type" id="type" required>
                                        <option value="" disabled selected>--Select type--</option>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>
                                <button type="submit" name="add_category">Add Category</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <h2>Existing Categories</h2>
                    <?php if (empty($categories)): ?>
                        <p>No categories found. Add your first category above.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['name']) ?></td>
                                        <td><?= ucfirst($category['type']) ?></td>
                                        <td>
                                            <a href="?page=category&edit_category=<?= $category['category_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="?page=category&delete_category=<?= $category['category_id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
