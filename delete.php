
<?php
include "db.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($id && $id > 0) {
    $stmt = mysqli_prepare($conn,
        "DELETE FROM contacts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
header("Location: index.php");
exit();
?>
