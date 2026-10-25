<?php

function getProfile(mysqli $connect2db, int $userId): ?array
{
    $stmt = mysqli_prepare($connect2db, "SELECT firstname, lastname, email, phone FROM accounts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function updateProfile(array $data, mysqli $connect2db, int $userId, &$resultClass, &$result): void
{
    $firstname = trim($data['firstname'] ?? '');
    $lastname  = trim($data['lastname'] ?? '');
    $email     = trim($data['email'] ?? '');

    if ($firstname === '' || $lastname === '' || $email === '') {
        $resultClass = "error";
        $result = "Please fill in all fields.";
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resultClass = "error";
        $result = "Please enter a valid email address.";
        return;
    }

    // Make sure the email isn't used by a different account
    $check = mysqli_prepare($connect2db, "SELECT id FROM accounts WHERE email = ? AND id != ? LIMIT 1");
    mysqli_stmt_bind_param($check, "si", $email, $userId);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    $taken = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);

    if ($taken) {
        $resultClass = "error";
        $result = "That email is already used by another account.";
        return;
    }

    $stmt = mysqli_prepare($connect2db, "UPDATE accounts SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "sssi", $firstname, $lastname, $email, $userId);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $resultClass = "error";
        $result = "Something went wrong. Please try again.";
        return;
    }
    mysqli_stmt_close($stmt);

    $_SESSION['user']['firstname'] = $firstname;
    $_SESSION['user']['lastname']  = $lastname;
    $_SESSION['user']['email']     = $email;

    $resultClass = "success";
    $result = "Updated Successfully";
}

function getStaffCount(mysqli $conn): int
{
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM accounts WHERE role = 'staff'");
    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function getCustomerCount(mysqli $conn): int
{
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM accounts WHERE role = 'customer'");
    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function getStaffList(mysqli $conn): array
{
    $list = [];
    $result = mysqli_query($conn, "SELECT id, firstname, lastname, email, created_at FROM accounts WHERE role = 'staff' ORDER BY created_at DESC");
    if ($result) while ($row = mysqli_fetch_assoc($result)) $list[] = $row;
    return $list;
}

function getAllAccounts(mysqli $conn): array
{
    $accounts = [];
    $result = mysqli_query($conn, "SELECT id, firstname, lastname, email, role, created_at FROM accounts ORDER BY created_at DESC");
    if ($result) while ($row = mysqli_fetch_assoc($result)) $accounts[] = $row;
    return $accounts;
}