<?php
// Start the session to track login status
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit;
}

// Enable error reporting for debugging (optional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection details
$host = 'localhost';
$dbname = 'ashtiric_precedence';
$username = 'ashtiric_pre_user';
$password = 'Precedence@2025';

try {
    // Connect to database using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if a date is selected from the date picker
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';

// Modify the query to filter by the selected date if it's set
$sql = "SELECT meetings.*, time_slots.slot_time 
        FROM meetings 
        JOIN time_slots ON meetings.slot_id = time_slots.id";

if ($selectedDate) {
    // Add the condition to filter by date
    $sql .= " WHERE meetings.date = :selectedDate";
}

$sql .= " ORDER BY meetings.created_at DESC";

$meetingsStmt = $pdo->prepare($sql);

if ($selectedDate) {
    $meetingsStmt->bindParam(':selectedDate', $selectedDate);
}

$meetingsStmt->execute();
$meetings = $meetingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle accept/reject functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $meetingId = intval($_POST['meeting_id']);
    $action = $_POST['action']; // Either 'accept' or 'reject'

    // Ensure valid action value (only accept or reject)
    if (!in_array($action, ['accept', 'reject'])) {
        echo "<p class='error-message'>Invalid action</p>";
        exit;
    }

    // Start a transaction for accepting or rejecting the booking
    try {
        $pdo->beginTransaction();

        // Get meeting details
        $meetingStmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
        $meetingStmt->execute([$meetingId]);
        $meeting = $meetingStmt->fetch(PDO::FETCH_ASSOC);

        // Get the slot ID associated with the meeting
        $slotStmt = $pdo->prepare("SELECT slot_id FROM meetings WHERE id = ?");
        $slotStmt->execute([$meetingId]);
        $slotId = $slotStmt->fetchColumn();

        // Update the slot status based on the action (accept or reject)
        if ($action === 'accept') {
            $updateSlotStmt = $pdo->prepare("UPDATE time_slots SET status = 'Booked' WHERE id = ?");
            $updateSlotStmt->execute([$slotId]);

            // Send confirmation email to the user
            $to = $meeting['email'];
            $subject = "Meeting Confirmed - $meeting[first_name] $meeting[last_name]";
            $body = "
            <html>
            <body>
                <h2 style='color: #4CAF50;'>Your Meeting has been Confirmed</h2>
                <p>Dear $meeting[first_name] $meeting[last_name],</p>
                <p>Your meeting with us has been confirmed for <b>{$meeting['date']}</b> at <b>{$meeting['slot_time']}</b>.</p>
                <p>We look forward to your meeting.</p>
                <p>Best regards,<br>Precedence Team</p>
            </body>
            </html>
            ";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: contact@precedencekw.com" . "\r\n";
            $headers .= "Reply-To: contact@precedencekw.com" . "\r\n";
            mail($to, $subject, $body, $headers);
        } else {
            $updateSlotStmt = $pdo->prepare("UPDATE time_slots SET status = 'Available' WHERE id = ?");
            $updateSlotStmt->execute([$slotId]);

            // Send reschedule email to the user
            $to = $meeting['email'];
            $subject = "Meeting Reschedule Request - $meeting[first_name] $meeting[last_name]";
            $body = "
            <html>
            <body>
                <h2 style='color: #e74c3c;'>Your Meeting Cannot Be Held</h2>
                <p>Dear $meeting[first_name] $meeting[last_name],</p>
                <p>Unfortunately, your meeting scheduled for <b>{$meeting['date']}</b> at <b>{$meeting['slot_time']}</b> has been rejected.</p>
                <p>Please reschedule your meeting using our available slots.</p>
                <p>Best regards,<br>Precedence Team</p>
            </body>
            </html>
            ";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: contact@precedencekw.com" . "\r\n";
            $headers .= "Reply-To: contact@precedencekw.com" . "\r\n";
            mail($to, $subject, $body, $headers);
        }

        // Update the meeting status
        $updateMeetingStmt = $pdo->prepare("UPDATE meetings SET status = :status WHERE id = :meeting_id");
        $updateMeetingStmt->bindParam(':status', $action);
        $updateMeetingStmt->bindParam(':meeting_id', $meetingId);
        $updateMeetingStmt->execute();

        // Commit the transaction
        $pdo->commit();

        // Redirect to avoid form resubmission on page reload
        header("Location: admin.php" . ($selectedDate ? "?date=$selectedDate" : ""));
        exit;
    } catch (Exception $e) {
        // Rollback in case of error
        $pdo->rollBack();
        echo "<p class='error-message'>Error processing request: " . $e->getMessage() . "</p>";
    }
}

// Handle individual delete functionality
if (isset($_POST['delete_meeting'])) {
    $meetingId = intval($_POST['meeting_id']);

    try {
        // Start a transaction
        $pdo->beginTransaction();

        // Get the slot ID associated with the meeting
        $getSlotStmt = $pdo->prepare("SELECT slot_id FROM meetings WHERE id = ?");
        $getSlotStmt->execute([$meetingId]);
        $slotId = $getSlotStmt->fetchColumn();

        // Delete the meeting
        $deleteMeetingStmt = $pdo->prepare("DELETE FROM meetings WHERE id = ?");
        $deleteMeetingStmt->execute([$meetingId]);

        // Reset the slot status to 'Available'
        $resetSlotStmt = $pdo->prepare("UPDATE time_slots SET status = 'Available' WHERE id = ?");
        $resetSlotStmt->execute([$slotId]);

        // Commit the transaction
        $pdo->commit();

        // Redirect to avoid resubmitting the form on page reload
        header("Location: admin.php");
        exit;
    } catch (Exception $e) {
        // Rollback in case of error
        $pdo->rollBack();
        echo "<p class='error-message'>Error deleting meeting: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Bookings</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }

        table td {
            background-color: #fafafa;
        }

        .button {
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            color: white;
            cursor: pointer;
        }

        .accept-btn {
            background-color: #2ecc71;
        }

        .accept-btn:hover {
            background-color: #27ae60;
        }

        .reject-btn {
            background-color: #e74c3c;
        }

        .reject-btn:hover {
            background-color: #c0392b;
        }

        .delete-btn {
            background-color: #FF5722;
        }

        .delete-btn:hover {
            background-color: #D45A24;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .actions form {
            display: inline-block;
        }

        /* Styling the date filter form */
        form {
            margin-bottom: 20px;
        }

        label {
            font-weight: 600;
            margin-right: 10px;
        }

        input[type="date"] {
            padding: 5px;
            font-size: 16px;
        }

        /* Hide the table if no date is selected */
        .table-container {
            display: none;
        }

        .table-container.show {
            display: block;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Admin - Manage Bookings</h1>

    <!-- Date Picker Form -->
    <form method="GET" action="" id="date-form">
        <label for="appointment_date">Select Date:</label>
        <input type="date" id="appointment_date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
    </form>

    <div class="table-container <?php echo $selectedDate ? 'show' : ''; ?>">
        <?php if (empty($meetings)): ?>
            <p>No bookings for the selected date.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Topic</th>
                        <th>Date</th>
                        <th>Slot Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $meeting): ?>
                        <tr>
                            <td><?= htmlspecialchars($meeting['first_name']) ?></td>
                            <td><?= htmlspecialchars($meeting['last_name']) ?></td>
                            <td><?= htmlspecialchars($meeting['email']) ?></td>
                            <td><?= htmlspecialchars($meeting['company']) ?></td>
                            <td><?= htmlspecialchars($meeting['topic']) ?></td>
                            <td><?= htmlspecialchars($meeting['date']) ?></td>
                            <td><?= htmlspecialchars($meeting['slot_time']) ?></td>
                            <td><?= ucfirst($meeting['status']) ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($meeting['status'] == 'pending'): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                                            <button type="submit" name="action" value="accept" class="button accept-btn">Accept</button>
                                            <button type="submit" name="action" value="reject" class="button reject-btn">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span><?= ucfirst($meeting['status']) ?></span>
                                    <?php endif; ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                                        <button type="submit" name="delete_meeting" class="button delete-btn" onclick="return confirm('Are you sure you want to delete this meeting and reset the slot?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
