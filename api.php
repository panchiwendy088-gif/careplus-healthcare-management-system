
<?php

/*
|--------------------------------------------------------------------------
| CarePlus Central Backend API
|--------------------------------------------------------------------------
| All database requests go through this file.
|
| Example:
| api.php?action=departments
| api.php?action=my_appointments
|--------------------------------------------------------------------------
*/

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/config/database.php";

/*
|--------------------------------------------------------------------------
| JSON response helper
|--------------------------------------------------------------------------
*/

function respond($success, $message, $data = null, $code = 200)
{
    http_response_code($code);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Request helpers
|--------------------------------------------------------------------------
*/

function getInput()
{
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        respond(false, "Request body must contain valid JSON.", null, 400);
    }

    return $data;
}

function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {
        respond(false, "Please log in first.", null, 401);
    }
}

function requireRole($roles)
{
    requireLogin();

    if (!in_array($_SESSION["role"], $roles, true)) {
        respond(false, "You are not authorized to perform this action.", null, 403);
    }
}

function currentUserId()
{
    return (int) $_SESSION["user_id"];
}

function requiredString($data, $key, $maxLength)
{
    $value = trim((string) ($data[$key] ?? ""));

    if ($value === "" || strlen($value) > $maxLength) {
        respond(
            false,
            "The field '$key' is required and must not exceed $maxLength characters.",
            null,
            422
        );
    }

    return $value;
}

/*
|--------------------------------------------------------------------------
| Route selection
|--------------------------------------------------------------------------
*/

$action = $_GET["action"] ?? "";

try {

    switch ($action) {

        /*
        |--------------------------------------------------------------------------
        | REGISTER A PATIENT
        |--------------------------------------------------------------------------
        | POST api.php?action=register
        |--------------------------------------------------------------------------
        */

        case "register":

            if ($_SERVER["REQUEST_METHOD"] !== "POST") {
                respond(false, "Use POST for registration.", null, 405);
            }

            $data = getInput();

            $fullName = requiredString($data, "full_name", 100);
            $email = strtolower(requiredString($data, "email", 150));
            $password = (string) ($data["password"] ?? "");
            $phone = trim((string) ($data["phone"] ?? ""));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respond(false, "Enter a valid email address.", null, 422);
            }

            if (strlen($password) < 8) {
                respond(false, "Password must contain at least 8 characters.", null, 422);
            }

            if ($phone !== "" && strlen($phone) > 20) {
                respond(false, "Phone number is too long.", null, 422);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (username, email, phone, password, role, status)
                    VALUES (?, ?, ?, ?, 'patient', 'active')"
                );

                $stmt->bind_param(
                    "ssss",
                    $fullName,
                    $email,
                    $phone,
                    $passwordHash
                );

                $stmt->execute();

                $userId = $conn->insert_id;

                $stmt->close();

                $stmt = $conn->prepare(
                    "INSERT INTO patients (user_id) VALUES (?)"
                );

                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                respond(true, "Account created successfully.", [
                    "user_id" => $userId
                ], 201);

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            break;


        /*
        |--------------------------------------------------------------------------
        | LOGIN
        |--------------------------------------------------------------------------
        | POST api.php?action=login
        |--------------------------------------------------------------------------
        */

        case "login":

            if ($_SERVER["REQUEST_METHOD"] !== "POST") {
                respond(false, "Use POST for login.", null, 405);
            }

            $data = getInput();

            $email = strtolower(requiredString($data, "email", 150));
            $password = (string) ($data["password"] ?? "");

            $stmt = $conn->prepare(
                "SELECT user_id, username, email, password, role, status
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                !$user ||
                !password_verify($password, $user["password"]) ||
                $user["status"] !== "active"
            ) {
                respond(false, "Invalid email or password.", null, 401);
            }

            session_regenerate_id(true);

            $_SESSION["user_id"] = (int) $user["user_id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["username"] = $user["username"];

            respond(true, "Login successful.", [
                "user_id" => (int) $user["user_id"],
                "name" => $user["username"],
                "email" => $user["email"],
                "role" => $user["role"]
            ]);

            break;


        /*
        |--------------------------------------------------------------------------
        | LOGOUT
        |--------------------------------------------------------------------------
        | POST api.php?action=logout
        |--------------------------------------------------------------------------
        */

        case "logout":

            if ($_SERVER["REQUEST_METHOD"] !== "POST") {
                respond(false, "Use POST for logout.", null, 405);
            }

            $_SESSION = [];

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();

                setcookie(session_name(), "", [
                    "expires" => time() - 42000,
                    "path" => $params["path"],
                    "domain" => $params["domain"],
                    "secure" => $params["secure"],
                    "httponly" => $params["httponly"],
                    "samesite" => "Lax"
                ]);
            }

            session_destroy();

            respond(true, "Logged out successfully.");

            break;


        /*
        |--------------------------------------------------------------------------
        | GET CURRENT USER
        |--------------------------------------------------------------------------
        | GET api.php?action=me
        |--------------------------------------------------------------------------
        */

        case "me":

            requireLogin();

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT user_id, username, email, phone, role, status
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || $user["status"] !== "active") {
                $_SESSION = [];
                session_destroy();

                respond(false, "Account is unavailable. Please log in again.", null, 401);
            }

            respond(true, "User retrieved successfully.", $user);

            break;


        /*
        |--------------------------------------------------------------------------
        | GET DEPARTMENTS
        |--------------------------------------------------------------------------
        | GET api.php?action=departments
        |--------------------------------------------------------------------------
        */

        case "departments":

            if ($_SERVER["REQUEST_METHOD"] !== "GET") {
                respond(false, "Use GET to retrieve departments.", null, 405);
            }

            $result = $conn->query(
                "SELECT department_id, department_name, description
                 FROM departments
                 ORDER BY department_name"
            );

            respond(true, "Departments retrieved successfully.", $result->fetch_all(MYSQLI_ASSOC));

            break;


        /*
        |--------------------------------------------------------------------------
        | BOOK AN APPOINTMENT
        |--------------------------------------------------------------------------
        | POST api.php?action=book_appointment
        |--------------------------------------------------------------------------
        */

        case "book_appointment":

            if ($_SERVER["REQUEST_METHOD"] !== "POST") {
                respond(false, "Use POST to book an appointment.", null, 405);
            }

            requireRole(["patient"]);

            $data = getInput();

            $departmentId = filter_var(
                $data["department_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            $date = trim((string) ($data["appointment_date"] ?? ""));
            $time = trim((string) ($data["appointment_time"] ?? ""));
            $type = $data["appointment_type"] ?? "in_person";
            $reason = requiredString($data, "reason", 5000);

            if (!$departmentId || $departmentId < 1) {
                respond(false, "Select a valid department.", null, 422);
            }

            $parsedDate = DateTime::createFromFormat("!Y-m-d", $date);

            if (
                !$parsedDate ||
                $parsedDate->format("Y-m-d") !== $date ||
                $date < date("Y-m-d")
            ) {
                respond(false, "Select a valid current or future appointment date.", null, 422);
            }

            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
                respond(false, "Enter a valid appointment time.", null, 422);
            }

            if (!in_array($type, ["in_person", "online"], true)) {
                respond(false, "Invalid appointment type.", null, 422);
            }

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT patient_id
                 FROM patients
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $patient = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$patient) {
                respond(false, "Patient profile not found.", null, 404);
            }

            $patientId = (int) $patient["patient_id"];

            $stmt = $conn->prepare(
                "INSERT INTO appointments
                (patient_id, department_id, appointment_date,
                 appointment_time, appointment_type, reason)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iissss",
                $patientId,
                $departmentId,
                $date,
                $time,
                $type,
                $reason
            );

            $stmt->execute();
            $appointmentId = $conn->insert_id;
            $stmt->close();

            respond(true, "Appointment booked successfully.", [
                "appointment_id" => $appointmentId,
                "status" => "pending"
            ], 201);

            break;


        /*
        |--------------------------------------------------------------------------
        | VIEW MY APPOINTMENTS
        |--------------------------------------------------------------------------
        | GET api.php?action=my_appointments
        |--------------------------------------------------------------------------
        */

        case "my_appointments":

            requireRole(["patient"]);

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT
                    a.appointment_id,
                    a.appointment_date,
                    a.appointment_time,
                    a.appointment_type,
                    a.reason,
                    a.status,
                    d.department_name,
                    s.staff_id,
                    u.username AS doctor_name
                 FROM appointments a
                 INNER JOIN patients p
                    ON a.patient_id = p.patient_id
                 INNER JOIN departments d
                    ON a.department_id = d.department_id
                 LEFT JOIN staff s
                    ON a.staff_id = s.staff_id
                 LEFT JOIN users u
                    ON s.user_id = u.user_id
                 WHERE p.user_id = ?
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            respond(true, "Appointments retrieved successfully.", $appointments);

            break;


        /*
        |--------------------------------------------------------------------------
        | CANCEL MY APPOINTMENT
        |--------------------------------------------------------------------------
        | POST api.php?action=cancel_appointment
        |--------------------------------------------------------------------------
        */

        case "cancel_appointment":

            if ($_SERVER["REQUEST_METHOD"] !== "POST") {
                respond(false, "Use POST to cancel an appointment.", null, 405);
            }

            requireRole(["patient"]);

            $data = getInput();

            $appointmentId = filter_var(
                $data["appointment_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$appointmentId || $appointmentId < 1) {
                respond(false, "Invalid appointment ID.", null, 422);
            }

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "UPDATE appointments a
                 INNER JOIN patients p ON a.patient_id = p.patient_id
                 SET a.status = 'cancelled'
                 WHERE a.appointment_id = ?
                   AND p.user_id = ?
                   AND a.status IN ('pending', 'approved')"
            );

            $stmt->bind_param("ii", $appointmentId, $userId);
            $stmt->execute();

            $changed = $stmt->affected_rows;
            $stmt->close();

            if (!$changed) {
                respond(false, "Appointment not found or cannot be cancelled.", null, 404);
            }

            respond(true, "Appointment cancelled successfully.");

            break;


        /*
        |--------------------------------------------------------------------------
        | VIEW MY MEDICAL RECORDS
        |--------------------------------------------------------------------------
        | GET api.php?action=medical_records
        |--------------------------------------------------------------------------
        */

        case "medical_records":

            requireRole(["patient"]);

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT
                    mr.record_id,
                    mr.diagnosis,
                    mr.treatment,
                    mr.clinical_notes,
                    mr.record_date,
                    u.username AS healthcare_provider
                 FROM medical_records mr
                 INNER JOIN patients p
                    ON mr.patient_id = p.patient_id
                 INNER JOIN staff s
                    ON mr.staff_id = s.staff_id
                 INNER JOIN users u
                    ON s.user_id = u.user_id
                 WHERE p.user_id = ?
                 ORDER BY mr.record_date DESC"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            respond(true, "Medical records retrieved successfully.", $records);

            break;


        /*
        |--------------------------------------------------------------------------
        | VIEW MY PRESCRIPTIONS
        |--------------------------------------------------------------------------
        | GET api.php?action=prescriptions
        |--------------------------------------------------------------------------
        */

        case "prescriptions":

            requireRole(["patient"]);

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT
                    pr.prescription_id,
                    pr.prescription_date,
                    pr.instructions,
                    pr.status,
                    pi.item_id,
                    pi.medicine_name,
                    pi.dosage,
                    pi.frequency,
                    pi.duration,
                    pi.quantity
                 FROM prescriptions pr
                 INNER JOIN medical_records mr
                    ON pr.record_id = mr.record_id
                 INNER JOIN patients p
                    ON mr.patient_id = p.patient_id
                 LEFT JOIN prescription_items pi
                    ON pr.prescription_id = pi.prescription_id
                 WHERE p.user_id = ?
                 ORDER BY pr.prescription_date DESC"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            respond(true, "Prescriptions retrieved successfully.", $prescriptions);

            break;


        /*
        |--------------------------------------------------------------------------
        | VIEW MY LAB RESULTS
        |--------------------------------------------------------------------------
        | GET api.php?action=lab_results
        |--------------------------------------------------------------------------
        */

        case "lab_results":

            requireRole(["patient"]);

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT
                    lr.lab_result_id,
                    lr.test_name,
                    lr.result,
                    lr.test_date,
                    lr.review_notes,
                    lr.status
                 FROM lab_results lr
                 INNER JOIN patients p
                    ON lr.patient_id = p.patient_id
                 WHERE p.user_id = ?
                 ORDER BY lr.test_date DESC"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            respond(true, "Laboratory results retrieved successfully.", $results);

            break;


        /*
        |--------------------------------------------------------------------------
        | VIEW MY NOTIFICATIONS
        |--------------------------------------------------------------------------
        | GET api.php?action=notifications
        |--------------------------------------------------------------------------
        */

        case "notifications":

            requireLogin();

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "SELECT
                    notification_id,
                    message,
                    notification_type,
                    is_read,
                    created_at,
                    read_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT 100"
            );

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            respond(true, "Notifications retrieved successfully.", $notifications);

            break;


        /*
        |--------------------------------------------------------------------------
        | MARK A NOTIFICATION AS READ
        |--------------------------------------------------------------------------
        | POST api.php?action=mark_notification_read
        |--------------------------------------------------------------------------
        */

        case "mark_notification_read":

            if ($_SERVER["REQUEST_METHOD"] !== "POST") {
                respond(false, "Use POST to update a notification.", null, 405);
            }

            requireLogin();

            $data = getInput();

            $notificationId = filter_var(
                $data["notification_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$notificationId || $notificationId < 1) {
                respond(false, "Invalid notification ID.", null, 422);
            }

            $userId = currentUserId();

            $stmt = $conn->prepare(
                "UPDATE notifications
                 SET is_read = TRUE, read_at = CURRENT_TIMESTAMP
                 WHERE notification_id = ?
                   AND user_id = ?"
            );

            $stmt->bind_param("ii", $notificationId, $userId);
            $stmt->execute();

            $changed = $stmt->affected_rows;
            $stmt->close();

            if (!$changed) {
                respond(false, "Notification not found or already read.", null, 404);
            }

            respond(true, "Notification marked as read.");

            break;


        /*
        |--------------------------------------------------------------------------
        | UNKNOWN ROUTE
        |--------------------------------------------------------------------------
        */

        default:

            respond(false, "Unknown API action.", [
                "available_actions" => [
                    "register",
                    "login",
                    "logout",
                    "me",
                    "departments",
                    "book_appointment",
                    "my_appointments",
                    "cancel_appointment",
                    "medical_records",
                    "prescriptions",
                    "lab_results",
                    "notifications",
                    "mark_notification_read"
                ]
            ], 404);
    }

} catch (mysqli_sql_exception $e) {

    error_log("CarePlus database error: " . $e->getMessage());

    if ((int) $e->getCode() === 1062) {
        respond(false, "That email address is already registered.", null, 409);
    }

    respond(false, "A database operation failed. Check the server error log.", null, 500);

} catch (Throwable $e) {

    error_log("CarePlus API error: " . $e->getMessage());

    respond(false, "An unexpected server error occurred.", null, 500);
}
