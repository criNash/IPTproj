<?php
session_start();
include'dbconnect.php';

if(isset($_POST['username']) && isset($_POST['password']) && isset($_POST['confirm_password'])){

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if($password !== $confirm_password){
        echo "<script>alert('Passwords do not match!'); window.location='index.php';</script>";
        exit();
    }

    $sql = "SELECT * FROM admin_users WHERE username='$username'";
    $result = mysqli_query($conn, $sql);

    if(mysqli_num_rows($result) == 1){

        $row = mysqli_fetch_assoc($result);

        if(password_verify($password, $row['password'])){

            $_SESSION['admin'] = $row['username'];
            header("Location: dashboard.php");
            exit();

        } else {
            echo "<script>alert('Incorrect password!'); window.location='index.php';</script>";
        }

    } else {
        echo "<script>alert('Admin not found!'); window.location='index.php';</script>";
    }
}
?>
