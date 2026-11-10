<?php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "contact_management_db"
);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>