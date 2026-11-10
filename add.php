
<?php
include "db.php";

$message = "";

$name = "";
$phone = "";
$email = "";
$address = "";

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
            "INSERT INTO contacts (name, phone, email, address)
             VALUES (?, ?, ?, ?)");

        mysqli_stmt_bind_param(
            $stmt, "ssss", $name, $phone, $email, $address
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            header("Location: index.php");
            exit();
        }

        $message = "Unable to save contact.";
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Contact</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2>Add New Contact</h2>

    <?php if ($message !== ""): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateContactForm(this)">
        <label>Name</label>
        <input type="text" name="name"
            value="<?php echo htmlspecialchars($name); ?>" required>

        <label>Phone</label>
        <input type="tel" name="phone"
            value="<?php echo htmlspecialchars($phone); ?>" required>

        <label>Email</label>
        <input type="email" name="email"
            value="<?php echo htmlspecialchars($email); ?>" required>

        <label>Address</label>
        <textarea name="address" required><?php
            echo htmlspecialchars($address);
        ?></textarea>

        <button class="btn" type="submit">Save Contact</button>
        <a href="index.php" class="btn secondary">Back</a>
    </form>
</div>
<script src="script.js"></script>
</body>
</html>
