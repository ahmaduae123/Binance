<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please login first'); redirectTo('login.php');</script>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch wallet data
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallets = $stmt->fetchAll();

// Fetch transaction history
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Handle deposit/withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currency = $_POST['currency'];
    $amount = $_POST['amount'];
    $action = $_POST['action'];

    try {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, currency, amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $currency, $amount]);

        if ($action === 'deposit') {
            $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?");
            $stmt->execute([$amount, $user_id, $currency]);
        } else {
            $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?");
            $stmt->execute([$amount, $user_id, $currency]);
        }
        echo "<script>alert('$action successful!'); redirectTo('dashboard.php');</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// Handle buy/sell orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_type'])) {
    $order_type = $_POST['order_type'];
    $currency_pair = $_POST['currency_pair'];
    $amount = $_POST['amount'];
    $price = $_POST['price'];

    try {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, type, order_type, currency_pair, amount, price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $_POST['type'], $order_type, $currency_pair, $amount, $price]);
        echo "<script>alert('Order placed successfully!'); redirectTo('dashboard.php');</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Binance Clone</title>
    <style>
        body {
            margin: 0;
            font-family: 'Arial', sans-serif;
            background: #0a0c1b;
            color: #fff;
        }
        .navbar {
            background: #1b1e33;
            padding: 15px;
            display: flex;
            justify-content: space-between;
        }
        .navbar a {
            color: #f0b90b;
            text-decoration: none;
            margin: 0 20px;
        }
        .dashboard {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .section {
            background: #1b1e33;
            border-radius: 10px;
            padding: 20px;
            width: 100%;
            max-width: 400px;
        }
        .section h2 {
            color: #f0b90b;
            margin-top: 0;
        }
        .section input, .section select, .section button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 5px;
            background: #2c2f4e;
            color: #fff;
        }
        .section button {
            background: #f0b90b;
            color: #0a0c1b;
            cursor: pointer;
        }
        .section button:hover {
            background: #e0a80a;
        }
        canvas {
            width: 100% !important;
            max-height: 300px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #2c2f4e;
        }
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            .section {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div>Binance Clone</div>
        <div>
            <a href="#" onclick="redirectTo('index.php')">Home</a>
            <a href="#" onclick="redirectTo('logout.php')">Logout</a>
        </div>
    </div>
    <div class="dashboard">
        <!-- Wallet Section -->
        <div class="section">
            <h2>Wallet</h2>
            <?php foreach ($wallets as $wallet): ?>
                <p><?php echo $wallet['currency']; ?>: <?php echo $wallet['balance']; ?> (Address: <?php echo $wallet['wallet_address']; ?>)</p>
            <?php endforeach; ?>
            <form method="POST">
                <select name="currency">
                    <option value="BTC">BTC</option>
                    <option value="ETH">ETH</option>
                    <option value="BNB">BNB</option>
                </select>
                <input type="number" name="amount" placeholder="Amount" step="0.00000001" required>
                <button type="submit" name="action" value="deposit">Deposit</button>
                <button type="submit" name="action" value="withdrawal">Withdraw</button>
            </form>
        </div>
        <!-- Trading Section -->
        <div class="section">
            <h2>Trade</h2>
            <canvas id="tradingChart"></canvas>
            <form method="POST">
                <select name="type">
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                </select>
                <select name="order_type">
                    <option value="market">Market</option>
                    <option value="limit">Limit</option>
                    <option value="stop_loss">Stop-Loss</option>
                </select>
                <input type="text" name="currency_pair" placeholder="Currency Pair (e.g., BTC/USDT)" required>
                <input type="number" name="amount" placeholder="Amount" step="0.00000001" required>
                <input type="number" name="price" placeholder="Price (for limit/stop-loss)" step="0.01">
                <button type="submit">Place Order</button>
            </form>
        </div>
        <!-- Transaction History -->
        <div class="section">
            <h2>Transaction History</h2>
            <table>
                <tr>
                    <th>Type</th>
                    <th>Currency</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td><?php echo $tx['type']; ?></td>
                        <td><?php echo $tx['currency']; ?></td>
                        <td><?php echo $tx['amount']; ?></td>
                        <td><?php echo $tx['status']; ?></td>
                        <td><?php echo $tx['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function redirectTo(page) {
            window.location.href = page;
        }

        // Real-time trading chart
        const ctx = document.getElementById('tradingChart').getContext('2d');
        const tradingChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'BTC/USDT',
                    data: [60000, 62000, 65000, 63000, 64000, 65000],
                    borderColor: '#f0b90b',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: false }
                }
            }
        });
    </script>
</body>
</html>
