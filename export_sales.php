<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="sales_report_' . $start_date . '_to_' . $end_date . '.xls"');
header('Cache-Control: max-age=0');

// Get detailed sales information
try {
    $query = "SELECT o.id as order_id, o.order_date, o.total_amount, o.status, 
              u.fullname as customer_name, u.email as customer_email, u.phone as customer_phone,
              COUNT(oi.id) as item_count, 
              GROUP_CONCAT(CONCAT(b.title, ' (', oi.quantity, ')') SEPARATOR ', ') as items,
              o.shipping_address, o.payment_method, o.payment_status
              FROM orders o
              JOIN users u ON o.customer_id = u.id
              JOIN order_items oi ON o.id = oi.order_id
              JOIN books b ON oi.book_id = b.id
              WHERE o.order_date BETWEEN ? AND ?
              GROUP BY o.id
              ORDER BY o.order_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Create Excel file content
    echo '<table border="1">';
    echo '<tr>
            <th colspan="11" style="background-color: #3498db; color: white; font-size: 16px; text-align: center;">
                Mega Book Store - Sales Report (' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)) . ')
            </th>
          </tr>';
    
    // Header row
    echo '<tr style="background-color: #f8f9fa; font-weight: bold;">
            <th>Order ID</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Items</th>
            <th>Item Count</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th>Shipping Address</th>
            <th>Payment Method</th>
            <th>Payment Status</th>
          </tr>';
    
    // Data rows
    $total_sales = 0;
    $order_count = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>#' . $row['order_id'] . '</td>
                    <td>' . date('M d, Y', strtotime($row['order_date'])) . '</td>
                    <td>' . htmlspecialchars($row['customer_name']) . '</td>
                    <td>' . htmlspecialchars($row['customer_email']) . '<br>' . htmlspecialchars($row['customer_phone']) . '</td>
                    <td>' . htmlspecialchars($row['items']) . '</td>
                    <td>' . $row['item_count'] . '</td>
                    <td>ETB ' . number_format($row['total_amount'], 2) . '</td>
                    <td>' . ucfirst($row['status']) . '</td>
                    <td>' . htmlspecialchars($row['shipping_address']) . '</td>
                    <td>' . htmlspecialchars($row['payment_method']) . '</td>
                    <td>' . ucfirst($row['payment_status']) . '</td>
                  </tr>';
            
            // Calculate totals for completed orders
            if ($row['status'] == 'delivered') {
                $total_sales += $row['total_amount'];
            }
            $order_count++;
        }
        
        // Summary row
        echo '<tr style="background-color: #e9f7fe; font-weight: bold;">
                <td colspan="5" style="text-align: right;">Summary:</td>
                <td>' . $order_count . ' orders</td>
                <td>ETB ' . number_format($total_sales, 2) . ' (completed sales)</td>
                <td colspan="4"></td>
              </tr>';
    } else {
        echo '<tr><td colspan="11" style="text-align: center;">No sales data found for the selected period</td></tr>';
    }
    
    echo '</table>';
    
} catch (Exception $e) {
    // Log error and output simple message for Excel file
    error_log("Error generating sales report: " . $e->getMessage());
    echo '<table><tr><td>Error generating report. Please try again later.</td></tr></table>';
}

// Close database connection
$conn->close();
?>
