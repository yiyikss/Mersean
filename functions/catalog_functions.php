<?php
// Admin functions for adding and removing services and barbers.
// "Removing" hides the service or barber (active = 0) instead of erasing the row,
// so old appointments still show the right names and nothing breaks.

// The icons a service can use (these exist in includes/ui_functions.php)
function serviceIcons(): array
{
    return ['scissors', 'spark', 'user', 'check', 'drop', 'smile', 'heart', 'clock'];
}

// category value in the database => label shown to people
function serviceCategories(): array
{
    return ['haircut' => 'Haircut', 'beard' => 'Beard & shave', 'package' => 'Package'];
}

// How many unfinished appointments (from today on) use this service or barber
function countOpenAppointments(mysqli $connect2db, string $column, int $id): int
{
    $column = $column === 'barber_id' ? 'barber_id' : 'service_id';   // fixed values only
    $today  = date('Y-m-d');

    $stmt = mysqli_prepare($connect2db, "
        SELECT COUNT(*) AS total FROM appointments
        WHERE $column = ? AND appointment_date >= ?
          AND status IN ('Pending', 'Confirmed', 'Ongoing')");
    mysqli_stmt_bind_param($stmt, "is", $id, $today);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0);
}

// ── Service reference photos ──

// Saves an uploaded JPG / PNG / WebP into assets/img/services/.
// Returns [fileName, errorMessage]. No file chosen is fine (fileName is then "").
function saveServiceImage(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['', ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['', "The image could not be uploaded. Please try a smaller file."];
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        return ['', "The image is too big. Please use a file under 3 MB."];
    }

    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $info  = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($types[$info[2]])) {
        return ['', "The image must be a JPG, PNG or WebP file."];
    }

    $folder = __DIR__ . '/../assets/img/services/';
    if (!is_dir($folder)) {
        @mkdir($folder, 0775, true);
    }

    $name = 'service_' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $folder . $name)) {
        return ['', "The image could not be saved. Check that assets/img/services is writable."];
    }
    return [$name, ''];
}

// Deletes an uploaded service image (only files this feature created).
function deleteServiceImage(?string $name): void
{
    if ($name && strpos($name, 'service_') === 0 && basename($name) === $name) {
        @unlink(__DIR__ . '/../assets/img/services/' . $name);
    }
}

// Web path for a service's image, or "" when it has none.
function serviceImagePath(array $service): string
{
    $name = $service['image'] ?? '';
    return $name !== '' && $name !== null ? 'assets/img/services/' . rawurlencode($name) : '';
}

// ── Services ──

// Returns "" if it worked, or an error message.
function addService(mysqli $connect2db, array $data, ?array $file = null): string
{
    $name        = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price       = $data['price'] ?? '';
    $duration    = $data['duration'] ?? '';
    $category    = $data['category'] ?? '';
    $icon        = $data['icon'] ?? 'scissors';
    $popular     = isset($data['popular']) ? 1 : 0;

    if ($name === '' || strlen($name) > 100) {
        return "Please enter a service name (up to 100 characters).";
    }
    if (!is_numeric($price) || $price <= 0 || $price > 99999) {
        return "Please enter a price greater than 0.";
    }
    if (!ctype_digit((string) $duration) || $duration < 5 || $duration > 480) {
        return "Duration must be between 5 and 480 minutes.";
    }
    if (!isset(serviceCategories()[$category])) {
        return "Please choose a category.";
    }
    if (!in_array($icon, serviceIcons(), true)) {
        $icon = 'scissors';
    }

    $price    = round((float) $price, 2);
    $duration = (int) $duration;

    [$image, $imageError] = saveServiceImage($file);
    if ($imageError !== '') {
        return $imageError;
    }
    $image = $image !== '' ? $image : null;

    $stmt = mysqli_prepare($connect2db, "
        INSERT INTO services (name, description, price, duration, category, icon, image, popular)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssdisssi", $name, $description, $price, $duration, $category, $icon, $image, $popular);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        deleteServiceImage($image);
    }

    return $ok ? "" : "Something went wrong. Please try again.";
}

// Returns "" if it worked, or an error message.
function updateService(mysqli $connect2db, int $id, array $data, ?array $file = null): string
{
    $name        = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price       = $data['price'] ?? '';
    $duration    = $data['duration'] ?? '';
    $category    = $data['category'] ?? '';
    $icon        = $data['icon'] ?? 'scissors';
    $popular     = isset($data['popular']) ? 1 : 0;

    if ($name === '' || strlen($name) > 100) {
        return "Please enter a service name (up to 100 characters).";
    }
    if (!is_numeric($price) || $price <= 0 || $price > 99999) {
        return "Please enter a price greater than 0.";
    }
    if (!ctype_digit((string) $duration) || $duration < 5 || $duration > 480) {
        return "Duration must be between 5 and 480 minutes.";
    }
    if (!isset(serviceCategories()[$category])) {
        return "Please choose a category.";
    }
    if (!in_array($icon, serviceIcons(), true)) {
        $icon = 'scissors';
    }

    $price    = round((float) $price, 2);
    $duration = (int) $duration;

    // Make sure this service exists, and remember its current image
    $stmt = mysqli_prepare($connect2db, "SELECT image FROM services WHERE id = ? AND active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$current) {
        return "That service was not found.";
    }
    $oldImage = $current['image'] ?? null;

    // New upload replaces the image; "remove" box clears it; otherwise keep it
    [$newImage, $imageError] = saveServiceImage($file);
    if ($imageError !== '') {
        return $imageError;
    }
    if ($newImage !== '') {
        $image = $newImage;
    } elseif (isset($data['remove_image'])) {
        $image = null;
    } else {
        $image = $oldImage;
    }

    $stmt = mysqli_prepare($connect2db, "
        UPDATE services
        SET name = ?, description = ?, price = ?, duration = ?, category = ?, icon = ?, image = ?, popular = ?
        WHERE id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, "ssdisssii", $name, $description, $price, $duration, $category, $icon, $image, $popular, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && $image !== $oldImage) {
        deleteServiceImage($oldImage);      // tidy up the replaced / removed file
    } elseif (!$ok) {
        deleteServiceImage($newImage);      // update failed, drop the new upload
    }

    return $ok ? "" : "Something went wrong. Please try again.";
}

// Returns "" if it worked, or an error message.
function deleteService(mysqli $connect2db, int $id): string
{
    if (countOpenAppointments($connect2db, 'service_id', $id) > 0) {
        return "This service still has upcoming appointments. Finish or cancel them first.";
    }

    $left = mysqli_fetch_assoc(mysqli_query($connect2db, "SELECT COUNT(*) AS total FROM services WHERE active = 1"));
    if ((int) $left['total'] <= 1) {
        return "Keep at least one service so customers can still book.";
    }

    $stmt = mysqli_prepare($connect2db, "UPDATE services SET active = 0 WHERE id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);

    return $changed ? "" : "That service was not found.";
}

// ── Barbers ──

// Saves an uploaded JPG into assets/img/. Returns [photoName, errorMessage].
// No file chosen is fine (photoName is then "").
function saveBarberPhoto(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['', ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['', "The photo could not be uploaded. Please try a smaller JPG."];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['', "The photo is too big. Please use a JPG under 2 MB."];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
        return ['', "The photo must be a JPG image."];
    }

    $name   = 'barber_' . bin2hex(random_bytes(8));          // the page adds ".jpg"
    $folder = __DIR__ . '/../assets/img/';
    if (!move_uploaded_file($file['tmp_name'], $folder . $name . '.jpg')) {
        return ['', "The photo could not be saved. Check that assets/img is writable."];
    }
    return [$name, ''];
}

// Returns "" if it worked, or an error message.
function addBarber(mysqli $connect2db, array $data, ?array $file = null): string
{
    $name      = trim($data['name'] ?? '');
    $specialty = trim($data['specialty'] ?? '');

    if ($name === '' || strlen($name) > 100) {
        return "Please enter the barber's name (up to 100 characters).";
    }
    if (strlen($specialty) > 150) {
        return "The specialty is too long (up to 150 characters).";
    }

    [$photo, $photoError] = saveBarberPhoto($file);
    if ($photoError !== '') {
        return $photoError;
    }

    $stmt = mysqli_prepare($connect2db, "INSERT INTO barbers (name, specialty, photo) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sss", $name, $specialty, $photo);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok ? "" : "Something went wrong. Please try again.";
}

// Returns "" if it worked, or an error message.
// Leave the photo empty to keep the current one.
function updateBarber(mysqli $connect2db, int $id, array $data, ?array $file = null): string
{
    $name      = trim($data['name'] ?? '');
    $specialty = trim($data['specialty'] ?? '');

    if ($name === '' || strlen($name) > 100) {
        return "Please enter the barber's name (up to 100 characters).";
    }
    if (strlen($specialty) > 150) {
        return "The specialty is too long (up to 150 characters).";
    }

    // Make sure this barber exists, and remember the old photo
    $stmt = mysqli_prepare($connect2db, "SELECT photo FROM barbers WHERE id = ? AND active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$current) {
        return "That barber was not found.";
    }

    [$photo, $photoError] = saveBarberPhoto($file);
    if ($photoError !== '') {
        return $photoError;
    }

    if ($photo !== '') {
        $stmt = mysqli_prepare($connect2db, "UPDATE barbers SET name = ?, specialty = ?, photo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $name, $specialty, $photo, $id);
    } else {
        $stmt = mysqli_prepare($connect2db, "UPDATE barbers SET name = ?, specialty = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $specialty, $id);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Tidy up the old uploaded photo (never touches the built-in photos like marco.jpg)
    if ($ok && $photo !== '' && strpos((string) $current['photo'], 'barber_') === 0) {
        @unlink(__DIR__ . '/../assets/img/' . $current['photo'] . '.jpg');
    }

    return $ok ? "" : "Something went wrong. Please try again.";
}

// Returns "" if it worked, or an error message.
function deleteBarber(mysqli $connect2db, int $id): string
{
    if (countOpenAppointments($connect2db, 'barber_id', $id) > 0) {
        return "This barber still has upcoming appointments. Finish or cancel them first.";
    }

    $left = mysqli_fetch_assoc(mysqli_query($connect2db, "SELECT COUNT(*) AS total FROM barbers WHERE active = 1"));
    if ((int) $left['total'] <= 1) {
        return "Keep at least one barber so customers can still book.";
    }

    $stmt = mysqli_prepare($connect2db, "UPDATE barbers SET active = 0 WHERE id = ? AND active = 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);

    return $changed ? "" : "That barber was not found.";
}