
<?php
include "db.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php");
    exit();
}

$stmt = mysqli_prepare($conn,
    "SELECT * FROM contacts WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$contact = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$contact) {
    header("Location: index.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if ($name === "" || $phone === "" ||
        $email === "" || $address === "") {
        $message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE contacts
             SET name=?, phone=?, email=?, address=?
             WHERE id=?");

        mysqli_stmt_bind_param(
            $stmt, "ssssi", $name, $phone, $email, $address, $id
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            header("Location: index.php");
            exit();
        }

        $message = "Unable to update contact.";
        mysqli_stmt_close($stmt);
    }

    $contact['name'] = $name;
    $contact['phone'] = $phone;
    $contact['email'] = $email;
    $contact['address'] = $address;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Contact</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2>Edit Contact</h2>

    <?php if ($message !== ""): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateContactForm(this)">
        <label>Name</label>
        <input type="text" name="name"
            value="<?php echo htmlspecialchars($contact['name']); ?>" required>

        <label>Phone</label>
        <input type="tel" name="phone"
            value="<?php echo htmlspecialchars($contact['phone']); ?>" required>

        <label>Email</label>
        <input type="email" name="email"
            value="<?php echo htmlspecialchars($contact['email']); ?>" required>

        <label>Address</label>
        <textarea name="address" required><?php
            echo htmlspecialchars($contact['address']);
        ?></textarea>

        <button class="btn" type="submit">Update Contact</button>
        <a href="index.php" class="btn secondary">Back</a>
    </form>
</div>
<script src="script.js"></script>
</body>
</html>
