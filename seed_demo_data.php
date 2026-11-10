
<?php
/**
 * CarePlus Demo Data Seeder
 *
 * Run from the project root:
 * php seed_demo_data.php
 *
 * Demo password for all seeded accounts:
 * CarePlusDemo2026!
 *
 * Use only in a development database.
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . "/config/database.php";

$demoPassword = "CarePlusDemo2026!";
$passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);

function runQuery(mysqli $conn, string $sql, string $types = "", array $values = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if ($types !== "") {
        $bind = [$types];

        foreach ($values as $key => &$value) {
            $bind[] = &$value;
        }

        call_user_func_array([$stmt, "bind_param"], $bind);
    }

    $stmt->execute();

    return $stmt;
}

function findId(mysqli $conn, string $sql, string $types, array $values): ?int
{
    $stmt = runQuery($conn, $sql, $types, $values);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row["id"] : null;
}

function ensureUser(
    mysqli $conn,
    string $name,
    string $email,
    string $phone,
    string $passwordHash,
    string $role
): int {
    $existingId = findId(
        $conn,
        "SELECT user_id AS id FROM users WHERE email = ? LIMIT 1",
        "s",
        [$email]
    );

    if ($existingId !== null) {
        return $existingId;
    }

    $stmt = runQuery(
        $conn,
        "INSERT INTO users
            (username, email, phone, password, role, status)
         VALUES (?, ?, ?, ?, ?, 'active')",
        "sssss",
        [$name, $email, $phone, $passwordHash, $role]
    );

    $id = $stmt->insert_id;
    $stmt->close();

    return (int)$id;
}

function ensureDepartment(
    mysqli $conn,
    string $name,
    string $description
): int {
    $id = findId(
        $conn,
        "SELECT department_id AS id
         FROM departments
         WHERE department_name = ?
         LIMIT 1",
        "s",
        [$name]
    );

    if ($id !== null) {
        return $id;
    }

    $stmt = runQuery(
        $conn,
        "INSERT INTO departments (department_name, description)
         VALUES (?, ?)",
        "ss",
        [$name, $description]
    );

    $id = $stmt->insert_id;
    $stmt->close();

    return (int)$id;
}

function ensurePatient(
    mysqli $conn,
    int $userId,
    string $dob,
    string $gender,
    string $address,
    string $emergencyContact
): int {
    $id = findId(
        $conn,
        "SELECT patient_id AS id FROM patients WHERE user_id = ? LIMIT 1",
        "i",
        [$userId]
    );

    if ($id !== null) {
        return $id;
    }

    $stmt = runQuery(
        $conn,
        "INSERT INTO patients
            (user_id, date_of_birth, gender, address, emergency_contact)
         VALUES (?, ?, ?, ?, ?)",
        "issss",
        [$userId, $dob, $gender, $address, $emergencyContact]
    );

    $id = $stmt->insert_id;
    $stmt->close();

    return (int)$id;
}

function ensureStaff(
    mysqli $conn,
    int $userId,
    int $departmentId,
    string $specialization,
    string $license
): int {
    $id = findId(
        $conn,
        "SELECT staff_id AS id FROM staff WHERE user_id = ? LIMIT 1",
        "i",
        [$userId]
    );

    if ($id !== null) {
        return $id;
    }

    $stmt = runQuery(
        $conn,
        "INSERT INTO staff
            (user_id, department_id, specialization, license_number)
         VALUES (?, ?, ?, ?)",
        "iiss",
        [$userId, $departmentId, $specialization, $license]
    );

    $id = $stmt->insert_id;
    $stmt->close();

    return (int)$id;
}

function ensureNotification(
    mysqli $conn,
    int $userId,
    string $message,
    string $type
): void {
    $id = findId(
        $conn,
        "SELECT notification_id AS id
         FROM notifications
         WHERE user_id = ? AND message = ?
         LIMIT 1",
        "is",
        [$userId, $message]
    );

    if ($id !== null) {
        return;
    }

    runQuery(
        $conn,
        "INSERT INTO notifications
            (user_id, message, notification_type, is_read)
         VALUES (?, ?, ?, 0)",
        "iss",
        [$userId, $message, $type]
    )->close();
}

try {
    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Departments
    |--------------------------------------------------------------------------
    */

    $generalMedicine = ensureDepartment(
        $conn,
        "General Medicine",
        "General consultations, diagnosis and treatment."
    );

    $maternity = ensureDepartment(
        $conn,
        "Maternity",
        "Maternal health and antenatal care."
    );

    $pediatrics = ensureDepartment(
        $conn,
        "Pediatrics",
        "Healthcare services for children."
    );

    $dental = ensureDepartment(
        $conn,
        "Dental Care",
        "Dental consultations and oral healthcare."
    );

    $eyeCare = ensureDepartment(
        $conn,
        "Eye Care",
        "Eye examinations and vision care."
    );

    /*
    |--------------------------------------------------------------------------
    | 2. Users
    |--------------------------------------------------------------------------
    */

    $adminUserId = ensureUser(
        $conn,
        "CarePlus Administrator",
        "admin@careplus.test",
        "+265991000001",
        $passwordHash,
        "admin"
    );

    $doctorOneUserId = ensureUser(
        $conn,
        "Dr. Grace Banda",
        "grace.banda@careplus.test",
        "+265991000002",
        $passwordHash,
        "doctor"
    );

    $doctorTwoUserId = ensureUser(
        $conn,
        "Dr. Peter Phiri",
        "peter.phiri@careplus.test",
        "+265991000003",
        $passwordHash,
        "doctor"
    );

    $doctorThreeUserId = ensureUser(
        $conn,
        "Dr. Thoko Zulu",
        "thoko.zulu@careplus.test",
        "+265991000004",
        $passwordHash,
        "doctor"
    );

    $patientOneUserId = ensureUser(
        $conn,
        "Mary Demo",
        "mary.patient@careplus.test",
        "+265991000101",
        $passwordHash,
        "patient"
    );

    $patientTwoUserId = ensureUser(
        $conn,
        "John Demo",
        "john.patient@careplus.test",
        "+265991000102",
        $passwordHash,
        "patient"
    );

    $patientThreeUserId = ensureUser(
        $conn,
        "Chisomo Demo",
        "chisomo.patient@careplus.test",
        "+265991000103",
        $passwordHash,
        "patient"
    );

    /*
    |--------------------------------------------------------------------------
    | 3. Patient profiles
    |--------------------------------------------------------------------------
    */

    $patientOneId = ensurePatient(
        $conn,
        $patientOneUserId,
        "1998-04-15",
        "Female",
        "Area 18, Lilongwe",
        "+265991100101"
    );

    $patientTwoId = ensurePatient(
        $conn,
        $patientTwoUserId,
        "1989-09-21",
        "Male",
        "Namiwawa, Blantyre",
        "+265991100102"
    );

    $patientThreeId = ensurePatient(
        $conn,
        $patientThreeUserId,
        "2015-02-10",
        "Other",
        "Zomba, Malawi",
        "+265991100103"
    );

    /*
    |--------------------------------------------------------------------------
    | 4. Staff profiles
    |--------------------------------------------------------------------------
    */

    $doctorOneId = ensureStaff(
        $conn,
        $doctorOneUserId,
        $generalMedicine,
        "General Practitioner",
        "DEMO-MED-001"
    );

    $doctorTwoId = ensureStaff(
        $conn,
        $doctorTwoUserId,
        $maternity,
        "Maternal Health",
        "DEMO-MED-002"
    );

    $doctorThreeId = ensureStaff(
        $conn,
        $doctorThreeUserId,
        $pediatrics,
        "Pediatrics",
        "DEMO-MED-003"
    );

    /*
    |--------------------------------------------------------------------------
    | 5. Appointments
    |--------------------------------------------------------------------------
    */

    $appointmentIds = [];

    $appointmentSamples = [
        [
            $patientOneId, $doctorOneId, $generalMedicine,
            date("Y-m-d", strtotime("+2 days")), "09:00:00",
            "in_person", "Routine health consultation", "approved"
        ],
        [
            $patientOneId, $doctorOneId, $generalMedicine,
            date("Y-m-d", strtotime("+7 days")), "10:30:00",
            "online", "Follow-up consultation", "pending"
        ],
        [
            $patientTwoId, $doctorTwoId, $maternity,
            date("Y-m-d", strtotime("+3 days")), "11:00:00",
            "in_person", "General maternity consultation demo", "pending"
        ],
        [
            $patientThreeId, $doctorThreeId, $pediatrics,
            date("Y-m-d", strtotime("+5 days")), "14:00:00",
            "in_person", "Routine pediatric consultation", "approved"
        ]
    ];

    foreach ($appointmentSamples as $sample) {
        [
            $patientId, $staffId, $departmentId, $date,
            $time, $type, $reason, $status
        ] = $sample;

        $existingId = findId(
            $conn,
            "SELECT appointment_id AS id
             FROM appointments
             WHERE patient_id = ?
               AND appointment_date = ?
               AND appointment_time = ?
               AND reason = ?
             LIMIT 1",
            "isss",
            [$patientId, $date, $time, $reason]
        );

        if ($existingId !== null) {
            $appointmentIds[] = $existingId;
            continue;
        }

        $stmt = runQuery(
            $conn,
            "INSERT INTO appointments
                (patient_id, staff_id, department_id,
                 appointment_date, appointment_time,
                 appointment_type, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            "iiisssss",
            [
                $patientId, $staffId, $departmentId, $date,
                $time, $type, $reason, $status
            ]
        );

        $appointmentIds[] = (int)$stmt->insert_id;
        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Medical records
    |--------------------------------------------------------------------------
    | These are explicitly fictional demonstration records.
    */

    $recordSamples = [
        [
            $patientOneId, $doctorOneId, $appointmentIds[0],
            "Routine consultation - demo",
            "General wellness advice",
            "Fictional demonstration record. Not a real diagnosis."
        ],
        [
            $patientTwoId, $doctorTwoId, $appointmentIds[2],
            "Sample maternal health visit",
            "Follow-up recommended",
            "Fictional demonstration record. Not a real diagnosis."
        ],
        [
            $patientThreeId, $doctorThreeId, $appointmentIds[3],
            "Routine pediatric visit - demo",
            "Routine follow-up",
            "Fictional demonstration record. Not a real diagnosis."
        ]
    ];

    $recordIds = [];

    foreach ($recordSamples as $sample) {
        [
            $patientId, $staffId, $appointmentId,
            $diagnosis, $treatment, $notes
        ] = $sample;

        $existingId = findId(
            $conn,
            "SELECT record_id AS id
             FROM medical_records
             WHERE appointment_id = ?
             LIMIT 1",
            "i",
            [$appointmentId]
        );

        if ($existingId !== null) {
            $recordIds[] = $existingId;
            continue;
        }

        $stmt = runQuery(
            $conn,
            "INSERT INTO medical_records
                (patient_id, staff_id, appointment_id,
                 diagnosis, treatment, clinical_notes, record_date)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE())",
            "iiisss",
            [
                $patientId, $staffId, $appointmentId,
                $diagnosis, $treatment, $notes
            ]
        );

        $recordIds[] = (int)$stmt->insert_id;
        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Prescriptions and prescription items
    |--------------------------------------------------------------------------
    */

    foreach ([
        [
            $recordIds[0], $doctorOneUserId,
            "Sample prescription instructions for demonstration only.",
            "Demo wellness item", "As directed by a licensed clinician"
        ],
        [
            $recordIds[1], $doctorTwoUserId,
            "Sample follow-up instructions for demonstration only.",
            "Demo care item", "Follow professional advice"
        ]
    ] as $sample) {
        [
            $recordId, $prescribedBy, $instructions,
            $medicineName, $additionalInstructions
        ] = $sample;

        $prescriptionId = findId(
            $conn,
            "SELECT prescription_id AS id
             FROM prescriptions
             WHERE record_id = ?
             LIMIT 1",
            "i",
            [$recordId]
        );

        if ($prescriptionId === null) {
            $stmt = runQuery(
                $conn,
                "INSERT INTO prescriptions
                    (record_id, prescribed_by, prescription_date,
                     instructions, status)
                 VALUES (?, ?, CURDATE(), ?, 'active')",
                "iis",
                [$recordId, $prescribedBy, $instructions]
            );

            $prescriptionId = (int)$stmt->insert_id;
            $stmt->close();
        }

        $itemId = findId(
            $conn,
            "SELECT item_id AS id
             FROM prescription_items
             WHERE prescription_id = ? AND medicine_name = ?
             LIMIT 1",
            "is",
            [$prescriptionId, $medicineName]
        );

        if ($itemId === null) {
            runQuery(
                $conn,
                "INSERT INTO prescription_items
                    (prescription_id, medicine_name, dosage,
                     frequency, duration, quantity, additional_instructions)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                "issssis",
                [
                    $prescriptionId,
                    $medicineName,
                    "Not specified",
                    "Not specified",
                    "Not specified",
                    1,
                    $additionalInstructions
                ]
            )->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Laboratory results
    |--------------------------------------------------------------------------
    */

    $labSamples = [
        [
            $patientOneId, $recordIds[0],
            "Demo laboratory panel",
            "Sample result - not a real clinical result",
            $doctorOneUserId,
            "Fictional data for testing the CarePlus interface",
            "reviewed"
        ],
        [
            $patientTwoId, $recordIds[1],
            "Demo screening test",
            "Pending demonstration result",
            null,
            "Awaiting demonstration review",
            "pending"
        ],
        [
            $patientThreeId, $recordIds[2],
            "Demo pediatric test",
            "Sample result - not a real clinical result",
            $doctorThreeUserId,
            "Fictional data for testing the CarePlus interface",
            "reviewed"
        ]
    ];

    foreach ($labSamples as $sample) {
        [
            $patientId, $recordId, $testName, $result,
            $reviewedBy, $reviewNotes, $status
        ] = $sample;

        $existingId = findId(
            $conn,
            "SELECT lab_result_id AS id
             FROM lab_results
             WHERE patient_id = ? AND test_name = ?
             LIMIT 1",
            "is",
            [$patientId, $testName]
        );

        if ($existingId !== null) {
            continue;
        }

        runQuery(
            $conn,
            "INSERT INTO lab_results
                (patient_id, record_id, test_name, result,
                 test_date, reviewed_by, review_notes, status)
             VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?)",
            "iississ",
            [
                $patientId, $recordId, $testName, $result,
                $reviewedBy, $reviewNotes, $status
            ]
        )->close();
    }

    /*
    |--------------------------------------------------------------------------
    | 9. Notifications
    |--------------------------------------------------------------------------
    */

    ensureNotification(
        $conn,
        $patientOneUserId,
        "Your demonstration appointment has been approved.",
        "appointment"
    );

    ensureNotification(
        $conn,
        $patientOneUserId,
        "A sample medical record is available in your dashboard.",
        "medical_record"
    );

    ensureNotification(
        $conn,
        $patientTwoUserId,
        "Your demonstration appointment request is pending.",
        "appointment"
    );

    ensureNotification(
        $conn,
        $patientThreeUserId,
        "Your demonstration appointment has been approved.",
        "appointment"
    );

    ensureNotification(
        $conn,
        $adminUserId,
        "CarePlus demo data has been prepared.",
        "system"
    );

    /*
    |--------------------------------------------------------------------------
    | 10. Audit logs
    |--------------------------------------------------------------------------
    */

    $auditAction = "DEMO_SEED";
    $entityType = "system";
    $details = "Fictional development data prepared by seed_demo_data.php";

    $existingAudit = findId(
        $conn,
        "SELECT log_id AS id
         FROM audit_logs
         WHERE action = ? AND entity_type = ? AND details = ?
         LIMIT 1",
        "sss",
        [$auditAction, $entityType, $details]
    );

    if ($existingAudit === null) {
        runQuery(
            $conn,
            "INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, NULL, ?, '127.0.0.1')",
            "isss",
            [$adminUserId, $auditAction, $entityType, $details]
        )->close();
    }

    $conn->commit();

    echo "\n========================================\n";
    echo " CarePlus demo data created successfully\n";
    echo "========================================\n\n";

    echo "DEMO LOGIN ACCOUNTS\n";
    echo "-------------------\n";
    echo "Admin:   admin@careplus.test\n";
    echo "Doctor:  grace.banda@careplus.test\n";
    echo "Patient: mary.patient@careplus.test\n\n";

    echo "Password for all demo accounts: " . $demoPassword . "\n\n";

    echo "Additional patients:\n";
    echo "john.patient@careplus.test\n";
    echo "chisomo.patient@careplus.test\n\n";

    echo "Important: These are fictional demo accounts and records.\n";
    echo "Do not use the demo password in a live deployment.\n\n";

} catch (Throwable $e) {
    $conn->rollback();

    error_log("CarePlus seeder error: " . $e->getMessage());

    echo "Seeding failed. No changes from this transaction were committed.\n";
    echo "Check your PHP error log and confirm that your database schema matches the seeder.\n";

    exit(1);
}
