<?php
include 'dbconnect.php';

$username = "admin1";
$password = password_hash("admin123", PASSWORD_DEFAULT);

$sql = "INSERT INTO admin_users (username, password) 
        VALUES ('$username', '$password')";

if(mysqli_query($conn, $sql)){
    echo "Admin created!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
