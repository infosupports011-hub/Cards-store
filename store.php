<?php
session_start();
require_once 'db.php';
$config = require_once 'config.php';

if (!isset($_SESSION['buyer_id'])) {
    header("Location: index.php");
    exit();
}

$buyer_id = $_SESSION['buyer_id'];
$stmt = $pdo->prepare("SELECT * FROM buyers WHERE id = ?");
$stmt->execute([$buyer_id]);
$buyer = $stmt->fetch();

if (!$buyer) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$page = $_GET['page'] ?? 'account_management';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_product_id'])) {
    $product_id = intval($_POST['buy_product_id']);

    $prod_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $prod_stmt->execute([$product_id]);
    $product = $prod_stmt->fetch();

    if ($product) {
        $bank_name = !empty($product['bank']) ? $product['bank'] : 'Bank (BIN: ' . $product['bins'] . ')';
        $price = floatval($product['price']);
        
        if ($buyer['balance_usd'] >= $price) {
            try {
                $pdo->beginTransaction();

                $new_balance = $buyer['balance_usd'] - $price;
                $update_balance = $pdo->prepare("UPDATE buyers SET balance_usd = ? WHERE id = ?");
                $update_balance->execute([$new_balance, $buyer_id]);

                $activate_buyer = $pdo->prepare("UPDATE buyers SET is_activated = 1 WHERE id = ?");
                $activate_buyer->execute([$buyer_id]);

                $pdo->commit();

                $stmt = $pdo->prepare("SELECT * FROM buyers WHERE id = ?");
                $stmt->execute([$buyer_id]);
                $buyer = $stmt->fetch();

                $message = "<div style='background-color:rgba(56,176,0,0.2); border:1px solid #38b000; color:#38b000; padding:15px; border-radius:6px; margin-bottom:15px;'>
                    <b>Purchase Successful! Deducted: \${$price} USD.</b><br>
                    <b>Unlocked Format Data:</b> <pre style='background:#050b14; padding:10px; color:#00ff66; margin-top:5px; font-family:monospace;'>Bins: {$product['bins']} | Type: {$product['card_type']} | Subtype: {$product['subtype']} | Expiry: {$product['expiry']} | Name: {$product['holder_name']} | Country: {$product['country']} | State: {$product['state']} | Full Address: {$product['full_address']} | Extra Info: {$product['extra_info']} | Zip: {$product['zip']} | Bank: {$bank_name} | Price: \${$product['price']}" . 
                    ($product['category'] == 'fullz' ? " | Bank Pin: {$product['bank_pin_code']} | Email/Pass: {$product['email_password']}" : "") . 
                    "</pre>
                </div>";

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div style='background-color:rgba(217,4,41,0.2); border:1px solid #d90429; color:#ffb703; padding:10px; border-radius:6px; margin-bottom:15px;'>Transaction Error! Try again.</div>";
            }
        } else {
            $message = "<div style='background-color:rgba(217,4,41,0.2); border:1px solid #d90429; color:#ffb703; padding:10px; border-radius:6px; margin-bottom:15px;'>Insufficient Balance! Please submit a top-up deposit request.</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_deposit'])) {
    $amount = floatval($_POST['deposit_amount']);
    $crypto = trim($_POST['crypto_type']);
    $tx_hash = trim($_POST['tx_hash']);

    if ($amount > 0 && !empty($tx_hash)) {
        $dep_stmt = $pdo->prepare("INSERT INTO deposits (buyer_id, amount_usd, crypto_type, tx_hash, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $dep_stmt->execute([$buyer_id, $amount, $crypto, $tx_hash]);
        $message = "<div style='background-color:rgba(255,183,3,0.15); border:1px solid #ffb703; color:#ffb703; padding:15px; border-radius:6px; margin-bottom:15px;'>
            ⏳ <b>Top-Up Deposit Submitted!</b><br>
            Your transaction is pending verification. It takes 15-20 minutes to confirm on the blockchain network before updating your USD wallet.
        </div>";
    } else {
        $message = "<div style='background-color:rgba(217,4,41,0.2); border:1px solid #d90429; color:#ffb703; padding:10px; border-radius:6px; margin-bottom:15px;'>Please enter valid amount and transaction hash.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cards - Dashboard</title>
    <style>
        body { background-color: #0d1b2a; color: #ffffff; font-family: Arial, sans-serif; margin: 0; display: flex; }
        .sidebar { width: 260px; background-color: #1b263b; height: 100vh; position: fixed; border-right: 1px solid #415a77; overflow-y: auto; }
        .sidebar h2 { text-align: center; color: #38b000; padding: 20px 0; border-bottom: 1px solid #415a77; margin: 0; font-size: 22px; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar ul li a { display: block; padding: 14px 20px; color: #adb5bd; text-decoration: none; border-bottom: 1px solid #132238; font-size: 14px; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background-color: #0077b6; color: #fff; }
        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); box-sizing: border-box; min-height: 100vh; }
        .top-header { font-size: 24px; font-weight: bold; color: #38b000; margin-bottom: 20px; border-bottom: 2px solid #415a77; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .top-right-panel { display: flex; align-items: center; gap: 15px; font-size: 14px; }
        .balance-badge { background-color: #1b263b; border: 1px solid #38b000; color: #38b000; padding: 6px 12px; border-radius: 20px; font-weight: bold; }
        .user-info { font-size: 13px; color: #adb5bd; }
        .content-box { background-color: #1b263b; border: 1px solid #415a77; border-radius: 10px; padding: 25px; box-sizing: border-box; overflow-x: auto; }
        .warning-box { background-color: rgba(255, 183, 3, 0.15); border: 1px solid #ffb703; color: #ffb703; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
        .crypto-box { background-color: #0d1b2a; padding: 12px; margin: 8px 0; border-radius: 6px; border: 1px solid #415a77; word-break: break-all; font-size: 12px; display: flex; align-items: center; gap: 10px; }
        .crypto-logo { font-size: 18px; font-weight: bold; min-width: 60px; text-align: center; }
        .format-table { width: 100%; border-collapse: collapse; margin-top: 15px; background-color: #0d1b2a; font-size: 12px; }
        .format-table th, .format-table td { border: 1px solid #415a77; padding: 8px; text-align: center; white-space: nowrap; }
        .format-table th { background-color: #1b263b; color: #38b000; }
        .action-btns { display: flex; gap: 5px; justify-content: center; }
        .btn-cart, .btn-buy { padding: 4px 8px; border: none; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: bold; }
        .btn-cart { background-color: #ffb703; color: #000; }
        .btn-buy { background-color: #38b000; color: #fff; }
        .logout-btn { background-color: #d90429; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
        .deposit-form { background-color: #0d1b2a; padding: 20px; border-radius: 8px; border: 1px solid #415a77; margin-top: 15px; }
        .deposit-form input, .deposit-form select { width: 100%; padding: 10px; margin: 8px 0 15px 0; background: #1b263b; border: 1px solid #415a77; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .seller-badge { color: #ffb703; font-weight: bold; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Cards</h2>
        <ul>
            <li><a href="store.php?page=account_management" class="<?php echo ($page == 'account_management') ? 'active' : ''; ?>">Account Management</a></li>
            <li><a href="store.php?page=account_balance" class="<?php echo ($page == 'account_balance') ? 'active' : ''; ?>">Account Balance & Deposit</a></li>
            <li><a href="store.php?page=cc_checking" class="<?php echo ($page == 'cc_checking') ? 'active' : ''; ?>">CC Checking</a></li>
            <li><a href="store.php?page=cc" class="<?php echo ($page == 'cc') ? 'active' : ''; ?>">CC Market</a></li>
            <li><a href="store.php?page=dumps" class="<?php echo ($page == 'dumps') ? 'active' : ''; ?>">Dumps Market</a></li>
            <li><a href="store.php?page=fullz" class="<?php echo ($page == 'fullz') ? 'active' : ''; ?>">Fullz Market</a></li>
            <li><a href="store.php?page=balance_checking" class="<?php echo ($page == 'balance_checking') ? 'active' : ''; ?>">Balance Checking</a></li>
            <li><a href="store.php?page=refund" class="<?php echo ($page == 'refund') ? 'active' : ''; ?>">Refund / Privacy Notice</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-header">
            <span>Cards Dashboard</span>
            
            <div class="top-right-panel">
                <div class="balance-badge">
                    Balance: $<span id="user-balance"><?php echo number_format($buyer['balance_usd'], 2); ?></span> USD
                </div>
                <div class="user-info">
                    User: <b><?php echo htmlspecialchars($buyer['username']); ?></b> | 
                    <a href="logout.php" class="logout-btn">Log Out</a>
                </div>
            </div>
        </div>

        <div class="content-box">
            <?php echo $message; ?>

            <?php if ($buyer['is_activated'] == 0): ?>
                <div class="warning-box">
                    ⚠️ <b>WARNING: Account Activation Required!</b><br>
                    Your account needs an activation deposit. Once activated, your account remains <b>safe and active forever</b> (even if unused for years). Unactivated accounts auto-delete after 5 days.
                    
                    <div class="crypto-box"><div class="crypto-logo" style="color:#f7931a;">₿ BTC</div> <div><b>Bitcoin (zPub):</b><br><code><?php echo $config['btc_zpub']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#bfbbbb;">Ł LTC</div> <div><b>Litecoin (LTC):</b><br><code><?php echo $config['ltc_address']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#c2a633;">🐕 DOGE</div> <div><b>Dogecoin (DOGE):</b><br><code><?php echo $config['doge_address']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#627eea;">Ξ ETH</div> <div><b>Ethereum (ETH):</b><br><code><?php echo $config['eth_address']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#ff6600;">ɱ XMR</div> <div><b>Monero (XMR):</b><br><code><?php echo $config['xmr_address']; ?></code></div></div>
                </div>
            <?php endif; ?>

            <?php if ($page == 'account_management'): ?>
                <h2>Account Management</h2>
                <p>Manage your account settings, credentials, and view your activation status.</p>
                <p><b>Status:</b> <?php echo ($buyer['is_activated'] == 1) ? '<span style="color:#38b000;">Activated (Permanent & Safe)</span>' : '<span style="color:#ffb703;">Unactivated (Pending Deposit)</span>'; ?></p>
                <p><b>Registered Email:</b> <?php echo htmlspecialchars($buyer['email']); ?></p>

            <?php elseif ($page == 'account_balance'): ?>
                <h2>Account Balance & Top-Up Deposits</h2>
                <p>Your current store wallet balance is: <b>$<?php echo number_format($buyer['balance_usd'], 2); ?> USD</b></p>
                <p style="color: #adb5bd; font-size: 13px;">Submit a crypto top-up deposit below to fund card purchases. It will stay in <b>Pending</b> status for 15-20 minutes until network verification is complete.</p>
                
                <div style="margin-bottom: 20px;">
                    <b>Official Deposit Addresses & Logos:</b>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#f7931a;">₿ BTC</div> <div><b>Bitcoin (zPub):</b> <code><?php echo $config['btc_zpub']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#bfbbbb;">Ł LTC</div> <div><b>Litecoin (LTC):</b> <code><?php echo $config['ltc_address']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#c2a633;">🐕 DOGE</div> <div><b>Dogecoin (DOGE):</b> <code><?php echo $config['doge_address']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#627eea;">Ξ ETH</div> <div><b>Ethereum (ETH):</b> <code><?php echo $config['eth_address']; ?></code></div></div>
                    <div class="crypto-box"><div class="crypto-logo" style="color:#ff6600;">ɱ XMR</div> <div><b>Monero (XMR):</b> <code><?php echo $config['xmr_address']; ?></code></div></div>
                </div>

                <div class="deposit-form">
                    <h3>Top-Up Deposit for Buying Cards</h3>
                    <form method="POST">
                        <label>Select Crypto Currency:</label>
                        <select name="crypto_type" required>
                            <option value="BTC">Bitcoin (BTC)</option>
                            <option value="LTC">Litecoin (LTC)</option>
                            <option value="DOGE">Dogecoin (DOGE)</option>
                            <option value="ETH">Ethereum (ETH)</option>
                            <option value="XMR">Monero (XMR)</option>
                        </select>

                        <label>Deposit Amount (USD):</label>
                        <input type="number" step="0.01" name="deposit_amount" placeholder="e.g. 50.00" required>

                        <label>Transaction Hash / ID (TXID):</label>
                        <input type="text" name="tx_hash" placeholder="Enter blockchain transaction hash" required>

                        <button type="submit" name="submit_deposit" class="btn">Submit Top-Up Deposit (Pending 15-20 mins)</button>
                    </form>
                </div>

                <h3 style="margin-top:25px;">Your Recent Deposit History</h3>
                <table class="format-table">
                    <tr>
                        <th>ID</th>
                        <th>Crypto</th>
                        <th>Amount ($ USD)</th>
                        <th>TX Hash</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    <?php
                    $dep_query = $pdo->prepare("SELECT * FROM deposits WHERE buyer_id = ? ORDER BY id DESC");
                    $dep_query->execute([$buyer_id]);
                    $deposits = $dep_query->fetchAll();
                    if ($deposits) {
                        foreach ($deposits as $dep) {
                            $status_color = ($dep['status'] == 'confirmed') ? '#38b000' : (($dep['status'] == 'pending') ? '#ffb703' : '#d90429');
                            echo "<tr>
                                <td>{$dep['id']}</td>
                                <td>{$dep['crypto_type']}</td>
                                <td>\${$dep['amount_usd']}</td>
                                <td style='max-width:150px; overflow:hidden; text-overflow:ellipsis;'>{$dep['tx_hash']}</td>
                                <td><b style='color:{$status_color}; text-transform:uppercase;'>{$dep['status']}</b></td>
                                <td>{$dep['created_at']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='color:#adb5bd; padding:15px;'>No deposit requests found.</td></tr>";
                    }
                    ?>
                </table>

            <?php elseif ($page == 'cc_checking'): ?>
                <h2>CC Checking</h2>
                <p>Live checking tool to verify card status.</p>

            <?php elseif ($page == 'cc' || $page == 'dumps' || $page == 'fullz'): ?>
                <?php 
                $cat = $page;
                $title_name = strtoupper($cat);
                $query = "SELECT p.*, s.nickname, s.rating_score FROM products p JOIN sellers s ON p.seller_id = s.id WHERE p.category = ? AND s.status != 'blacklisted' ORDER BY s.rating_score DESC, p.id DESC";
                $stmt_p = $pdo->prepare($query);
                $stmt_p->execute([$cat]);
                $items = $stmt_p->fetchAll();
                ?>
                <h2><?php echo $title_name; ?> Stock Market (Sorted by Top Seller Ratings)</h2>
                <p>Top-rated excellent sellers appear on top. Low-rated or fraudulent sellers are automatically monitored or blacklisted.</p>
                
                <table class="format-table">
                    <tr>
                        <th>Seller Nickname & Rating</th>
                        <th>Bins</th>
                        <th>Type</th>
                        <th>Subtype</th>
                        <th>Expiry</th>
                        <th>Name</th>
                        <th>Country</th>
                        <th>State</th>
                        <th>Full Address</th>
                        <th>Extra Info</th>
                        <th>Zip</th>
                        <th>Banks</th>
                        <?php if($cat == 'fullz'): ?>
                            <th>Bank Pin Code</th>
                            <th>Email & Password</th>
                        <?php endif; ?>
                        <th>Price ($)</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    if ($items) {
                        foreach ($items as $item) {
                            $display_bank = !empty($item['bank']) ? $item['bank'] : 'Bank (BIN: ' . $item['bins'] . ')';
                            $stars = str_repeat('⭐', round($item['rating_score']));
                            echo "<tr>
                                <td><span class='seller-badge'>" . htmlspecialchars($item['nickname']) . "</span><br><small>{$stars} ({$item['rating_score']})</small></td>
                                <td>{$item['bins']}</td>
                                <td>{$item['card_type']}</td>
                                <td>{$item['subtype']}</td>
                                <td>{$item['expiry']}</td>
                                <td>****</td>
                                <td>{$item['country']}</td>
                                <td>****</td>
                                <td>****</td>
                                <td>****</td>
                                <td>****</td>
                                <td>{$display_bank}</td>";
                            if($cat == 'fullz') {
                                echo "<td>****</td><td>****</td>";
                            }
                            echo "<td>\${$item['price']}</td>
                                <td>
                                    <div class='action-btns'>
                                        <button class='btn-cart' type='button'>Add</button>
                                        <form method='POST' style='display:inline;'>
                                            <input type='hidden' name='buy_product_id' value='{$item['id']}'>
                                            <button class='btn-buy' type='submit' onclick='return confirm(\"Buy this item for \${$item['price']} USD?\");'>Buy</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='16' style='color:#adb5bd; padding:20px;'>No stock available from active verified sellers right now.</td></tr>";
                    }
                    ?>
                </table>

            <?php elseif ($page == 'balance_checking'): ?>
                <h2>Balance Checking</h2>
                <p>Check available funds and real-time balances.</p>

            <?php elseif ($page == 'refund'): ?>
                <h2>Privacy Notice & Refund Policy</h2>
                <p style="color: #adb5bd; font-size: 13px;">Last updated: 2026</p>
                <hr style="border-color: #415a77;">
                <h3>1. Privacy Policy</h3>
                <p>We respect your privacy and protect all user data securely.</p>
                <h3>2. Refund Policy</h3>
                <p>All sales are final due to the digital nature of items.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
