<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];

// Fetch user ID
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$user]);
$user_id = $stmt->fetchColumn();

// Fetch transaction history from database
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY time DESC");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle new transaction (via AJAX or form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $amount = floatval($_POST['amount']);
    $order_type = $_POST['order_type'];
    $price = floatval($_POST['price']);
    $time = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, price, time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $order_type, $amount, $price, $time]);
        header("Location: index.php"); // Refresh to show updated transactions
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('Error saving transaction: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Trading System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: auto; }
        .chart-container { width: 100%; height: 400px; }
        .order-form, .portfolio { background: #fff; padding: 20px; margin: 10px 0; border-radius: 5px; }
        .order-form input, .order-form select, .order-form button { padding: 10px; margin: 5px; }
        @media (max-width: 768px) { .chart-container { height: 300px; } .order-form { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crypto Trading System</h1>
        <p>Welcome, <?php echo htmlspecialchars($user); ?>! <a href="logout.php">Logout</a></p>
        <div class="chart-container">
            <canvas id="priceChart"></canvas>
        </div>
        <div class="order-form">
            <h3>Place Order</h3>
            <input type="text" id="amount" placeholder="Amount (BTC)" required>
            <select id="orderType" onchange="togglePriceInput()">
                <option value="market">Market Order</option>
                <option value="limit">Limit Order</option>
                <option value="stopLoss">Stop-Loss Order</option>
            </select>
            <input type="number" id="price" placeholder="Price (USD)" style="display:none;" required>
            <button onclick="placeOrder()">Execute Order</button>
        </div>
        <div class="portfolio">
            <h3>Portfolio</h3>
            <p id="balance">Balance: $0.00</p>
            <p id="profitLoss">Profit/Loss: $0.00</p>
            <h4>Transaction History</h4>
            <ul id="transactionHistory">
                <?php foreach ($transactions as $tx): ?>
                    <li><?php echo htmlspecialchars($tx['type']); ?>: <?php echo number_format($tx['amount'], 8); ?> BTC @ $<?php echo number_format($tx['price'], 2); ?> (<?php echo $tx['time']; ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <script>
        let priceData = { labels: [], data: [] };
        const ctx = document.getElementById('priceChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line', 
            data: { labels: priceData.labels, datasets: [{ label: 'BTC/USD', data: priceData.data, borderColor: 'blue', fill: false }] },
            options: { scales: { y: { beginAtZero: false } } }
        });

        function updateChart() {
            const time = new Date().toLocaleTimeString();
            const price = Math.random() * (60000 - 50000) + 50000; // Simulated BTC price
            priceData.labels.push(time);
            priceData.data.push(price);
            if (priceData.labels.length > 20) { priceData.labels.shift(); priceData.data.shift(); }
            chart.update();
            updatePortfolio();
        }
        setInterval(updateChart, 1000);

        let portfolio = { balance: 0, transactions: <?php echo json_encode($transactions); ?> };
        function togglePriceInput() {
            const orderType = document.getElementById('orderType').value;
            document.getElementById('price').style.display = orderType === 'market' ? 'none' : 'block';
        }

        function placeOrder() {
            const amount = parseFloat(document.getElementById('amount').value);
            const orderType = document.getElementById('orderType').value;
            const price = orderType === 'market' ? priceData.data[priceData.data.length - 1] : parseFloat(document.getElementById('price').value) || 0;
            if (amount <= 0 || (orderType !== 'market' && !price)) return alert('Invalid input');

            const transaction = { type: orderType, amount, price, time: new Date().toLocaleString(), cost: amount * price };
            // Send to server
            const formData = new FormData();
            formData.append('action', 'place_order');
            formData.append('amount', amount);
            formData.append('order_type', orderType);
            formData.append('price', price);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                portfolio.transactions.push(transaction);
                if (orderType === 'market' || (orderType === 'limit' && price <= priceData.data[priceData.data.length - 1])) {
                    portfolio.balance -= transaction.cost;
                }
                updatePortfolio();
            }).catch(err => alert('Error: ' + err));
        }

        function updatePortfolio() {
            let totalProfitLoss = 0;
            portfolio.transactions.forEach(t => {
                if (t.type === 'market' || t.type === 'limit') {
                    totalProfitLoss += (priceData.data[priceData.data.length - 1] - t.price) * t.amount;
                }
            });
            document.getElementById('balance').textContent = `Balance: $${portfolio.balance.toFixed(2)}`;
            document.getElementById('profitLoss').textContent = `Profit/Loss: $${totalProfitLoss.toFixed(2)}`;
            document.getElementById('transactionHistory').innerHTML = portfolio.transactions.map(t => `<li>${t.type.toUpperCase()}: ${t.amount} BTC @ $${t.price} (${t.time})</li>`).join('');
        }
        updatePortfolio();
    </script>
</body>
</html>
