<?php
// Database connection
$host = 'localhost';
$dbname = 'ashtiric_precedence';
$username = 'ashtiric_pre_user';
$password = 'Precedence@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch all time slots for the initial load (for current date or a default date)
function fetchSlots($date) {
    global $pdo;

    // Query available slots for the selected date
    $stmt = $pdo->prepare("
        SELECT ts.id, ts.slot_time, ts.status
        FROM time_slots ts
        LEFT JOIN meetings m ON ts.id = m.slot_id AND m.date = ?
        WHERE ts.status = 'Available' OR (ts.status = 'Booked' AND m.date = ?)
    ");
    $stmt->execute([$date, $date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle the AJAX request for fetching slots
if (isset($_GET['fetch_slots'])) {
    $date = $_GET['date']; // Date selected from the calendar
    $slots = fetchSlots($date);

    echo json_encode(['success' => true, 'slots' => $slots]);
    exit;
}

// Handle meeting booking
if (isset($_POST['book_meeting'])) {
    $firstName = htmlspecialchars($_POST['first_name']);
    $lastName = htmlspecialchars($_POST['last_name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $company = htmlspecialchars($_POST['company']);
    $topic = htmlspecialchars($_POST['topic']);
    $date = $_POST['date'];
    $slotId = intval($_POST['slot_id']);

    // Validate date (ensure it is in the future)
    $currentDate = date('Y-m-d');
    if ($date < $currentDate) {
        die("<p class='error-message'>Please select a future date.</p>");
    }

    // Check if slot is available
    $slotCheckStmt = $pdo->prepare("SELECT status FROM time_slots WHERE id = ?");
    $slotCheckStmt->execute([$slotId]);
    $slotStatus = $slotCheckStmt->fetch(PDO::FETCH_ASSOC)['status'];

    if ($slotStatus === 'Available') {
        try {
            // Start a transaction
            $pdo->beginTransaction();

            // Book meeting
            $insertStmt = $pdo->prepare("
                INSERT INTO meetings (first_name, last_name, email, company, topic, date, slot_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$firstName, $lastName, $email, $company, $topic, $date, $slotId]);

            // Update slot status
            $updateSlotStmt = $pdo->prepare("UPDATE time_slots SET status = 'Booked' WHERE id = ?");
            $updateSlotStmt->execute([$slotId]);

            // Commit the transaction
            $pdo->commit();

            // Send email notification
            $to = "contact@precedencekw.com";
            $subject = "Meeting Requested: $firstName $lastName";
            $body = "
            <html>
            <body>
                <h2>Meeting Requested</h2>
                <p><b>Name:</b> $firstName $lastName</p>
                <p><b>Company:</b> $company</p>
                <p><b>Email:</b> $email</p>
                <p><b>Topic:</b> $topic</p>
                <p><b>Meeting Date:</b> $date</p>
                <p><b>Time Slot:</b> {$slotStatus}</p>
            </body>
            </html>
            ";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: $email" . "\r\n";
            $headers .= "Reply-To: $email" . "\r\n";
            mail($to, $subject, $body, $headers);

            echo "<p class='success-message'>Meeting booked successfully and email sent!</p>";
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $pdo->rollBack();
            echo "<p class='error-message'>Error booking meeting: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error-message'>Selected slot is no longer available.</p>";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" type="text/css" href="css/header.css">
    <title>Meeting Scheduler</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
      <!-- Favicons -->
  <link href="icons and logos/logo1.png" rel="icon">
  <link href="icons and logos/logo1.png" rel="apple-touch-icon">
    <style>
         .meeting-wrap {
            max-width: 100%;
           display:flex;
            padding: 40px;
            background-color: #000000;
            
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
        }
        
        p.success-message {
    position: absolute;
    color: #ffffff;
    top: 142px;
    font-size: 20px;
    right: 0;
    left: 0;
    margin: 0 auto;
    text-align: center;
}

        .tittel {
    padding-top: 75px;
    background: #000000;
}

.tittel h1 {
    margin: 0px;
    padding: 0px;
    text-align: center;
    color: #ffffff;
}
body {
    background-color: black;
}
.slot-item.selected {
    background-color: #27ae60; /* color for selected slot */
    color: white; /* White text color */
    border: 1px solid #1d8147; /* Darker border for selected slot */
}


        /* Calendar Styles */
        .calendar-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width:calc(50% - 20px);margin:0px -10px;
            margin-bottom: 40px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .calendar-day {
            padding: 10px;
            background-color: #ffffff;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .calendar-day:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-day.selected {
            background-color: #27ae60;
            color: white;
            font-weight: bold;
        }

        .calendar-day.past {
            background-color: #bdc3c7;
            color: #7f8c8d;
            cursor: not-allowed;
        }

        .calendar-day.holiday {
            background-color: #e74c3c;
            color: white;
            font-weight: bold;
            cursor: not-allowed;
        }

        /* Slot Picker */
        .slot-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            width:calc(50% - 20px);
            margin:0px 10px;
            margin-top: 20px;
        }
.slot-item {
    width:100%;
    margin: 0px 10px 10px;
    padding: 0px; transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: pointer;
}
        

        .slot-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .slot-item.booked {
            background-color: #e74c3c;
            color: white;
            border:1px solid #ffffff;padding:;
            text-align:center;
            cursor: not-allowed;
        }

       
       
.form-container button.close-btn {
    width: 39px;
    right:5%;
    top: 10px;
    margin: 0 auto;
}

        
        .slot-item.available {
    /* background-color: #2ecc71; */
    color: white;
    display: flex;
    flex-direction: column;
    JUSTIFY-CONTENT: CENTER;
    ALIGN-ITEMS: CENTER;
    border: 1px solid;
    }

        /* Popup Form */
        .form-popup {
            display: none; /* Initially hidden */
          
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        
        }

        .form-container {
            background-color:rgb(0, 0, 0);
            padding: 40px;
            border-radius: 12px;
            width: 400px;
            position:relative;
        }

        .form-popup.show {
            display: flex;border:1px solid #ffffff;border-radius:10px;
        }

        .form-container h2 {
            text-align: center;
            color:rgb(255, 255, 255);
            font-size: 1.8rem;
            margin-bottom: 30px;
        }

        .form-container label {
            font-weight: 500;
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
            color:rgb(255, 255, 255);
        }
        
        form
        {
            display:flex;
            flex-wrap:wrap;
        }

        .form-container input,
        .form-container select {
            width: 100%;
            padding: 10px;
            margin-bottom:10px;
            border-radius: 8px;
            border: 2px solid #ddd;
            font-size: 16px;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .form-container button:hover {
            background-color: #2ecc71;
        }

        .close-btn {
            background-color: transparent;
            border: none;
            color: #e74c3c;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 50%;
        }

        .close-btn:hover {
            background-color: #;
        }
        
        .calendar-container {
    width: 100%;
}
#currentMonth {
    font-size: 24px;
    font-weight: bold;
    color:rgb(255, 255, 255);
    text-align: center;
    margin-bottom: 20px; /* Space between the month label and calendar */
    padding: 10px 0;
 
}

div#slotContainer {
    width: 100%;
}
            @media(max-width:991px)
        {
            .meeting-wrap
            {
                flex-direction:column;
            }
        }
    </style>
</head>
<body>

<div class="tittel">
    <h1>Book a Meeting</h1>
</div>
<div class="meeting-wrap">
    <!-- Calendar -->
    <div class="calendar-container">
        <!-- Month display -->
        <div id="calendarHeader">
        <div id="currentMonth"></div>
        </div>
        <div id="calendarDays" class="calendar-days"></div>
    </div>

    <!-- Slot Picker -->
    <div class="slot-container" id="slotContainer">
        <!-- Available slots will be dynamically loaded here -->
    </div>

    <!-- Booking Form Popup -->
    <div id="formPopup" class="form-popup">
        <div class="form-container">
            <button class="close-btn" onclick="closeForm()">×</button>
            <h2>Book a Meeting</h2>
            <form method="POST" action="">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
                
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                
                <label for="company">Company:</label>
                <input type="text" id="company" name="company">
                
                <label for="topic">Location:</label>
                <select id="topic" name="topic" required>
                    <option value="Client Office">Client Office</option>
                    <option value="Online Meeting">Online Meeting</option>
                </select>
                
                <input type="hidden" id="date" name="date" required>
                <input type="hidden" id="slot_id" name="slot_id" required>

                <button type="submit" name="book_meeting">Book Meeting</button>
            </form>
        </div>
    </div>
</div>



<script>
    let selectedDate = null;

    // Function to select a date from calendar
    function selectDate(date) {
        selectedDate = date;
        document.getElementById('date').value = selectedDate;
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.remove('selected');
        });
        document.querySelector(`[data-date="${selectedDate}"]`).classList.add('selected');
        
        // Fetch available time slots for the selected date
        fetchTimeSlots(selectedDate);
    }

    // Function to fetch time slots for the selected date
    function fetchTimeSlots(date) {
        const slotContainer = document.getElementById('slotContainer');
        slotContainer.innerHTML = ''; // Clear previous slots

        // Send AJAX request to fetch slots for the selected date
        fetch('?fetch_slots=1&date=' + date)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.slots.forEach(slot => {
                        const slotDiv = document.createElement('div');
                        slotDiv.classList.add('slot-item');
                        slotDiv.classList.add(slot.status === 'Available' ? 'available' : 'booked');
                        slotDiv.dataset.id = slot.id;
                        slotDiv.innerHTML = `<strong>${slot.slot_time}</strong><small>${slot.status}</small>`;
                        slotDiv.addEventListener('click', () => selectSlot(slot.id));
                        slotContainer.appendChild(slotDiv);
                    });
                } else {
                    slotContainer.innerHTML = '<p>No available slots for this date.</p>';
                }
            })
            .catch(error => console.error('Error fetching slots:', error));
    }

// Function to select a time slot and show the booking form
function selectSlot(slotId) {
    // Remove 'selected' class from any previously selected slot
    document.querySelectorAll('.slot-item').forEach(slot => {
        slot.classList.remove('selected');
    });

    // Add the 'selected' class to the clicked slot
    const selectedSlot = document.querySelector(`.slot-item[data-id="${slotId}"]`);
    selectedSlot.classList.add('selected');
    
    document.getElementById('slot_id').value = slotId;
    
    // Show the booking form popup
    document.getElementById('formPopup').classList.add('show');
}


    // Close the booking form popup
    function closeForm() {
        document.getElementById('formPopup').classList.remove('show');
    }

    // Initialize the calendar and handle date selection
    function initCalendar() {
    const calendarDays = document.getElementById('calendarDays');
    const currentMonthElement = document.getElementById('currentMonth'); // Get the current month element
    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();

    // Array of month names
    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    // Display the current month in the HTML
    currentMonthElement.textContent = `${monthNames[currentMonth]} ${currentYear}`;

    for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        calendarDays.appendChild(emptyCell);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.classList.add('calendar-day');
        dayCell.textContent = day;
        const currentDate = new Date(currentYear, currentMonth, day);
        dayCell.dataset.date = `${currentYear}-${currentMonth + 1}-${day}`;

        // Check if it's a Friday (Day 5 of the week)
        if (currentDate.getDay() === 5) {
            dayCell.textContent = 'Holiday';  // Display "Holiday" for Fridays
            dayCell.classList.add('holiday');
            dayCell.style.cursor = 'not-allowed';  // Disable click on Fridays
            dayCell.onclick = () => {};  // No action for Fridays
        } else {
            // Normal behavior for other days
            if (currentDate < today) {
                dayCell.classList.add('past');
                dayCell.onclick = () => {};  // Disable click on past days
            } else {
                dayCell.addEventListener('click', () => selectDate(`${currentYear}-${currentMonth + 1}-${day}`));
            }
        }
        calendarDays.appendChild(dayCell);
    }
}

// Initialize the calendar when the page loads
window.onload = function() {
    initCalendar();
};

</script>
</body>
</html>
