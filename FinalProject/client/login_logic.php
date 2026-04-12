<?php
session_start();
include 'dbconnect.php'; 

if(isset($_POST['login'])){
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // 1. Hanapin ang user sa 'clients' table
    $sql = "SELECT * FROM clients WHERE username='$username'";
    $result = mysqli_query($conn, $sql);

    if(mysqli_num_rows($result) > 0){
        $row = mysqli_fetch_assoc($result);
        
        // 2. I-verify kung tugma ang password sa hashed password sa DB
        if(password_verify($password, $row['password'])){
            
            // 3. Set Session for Client
            $_SESSION['client_user'] = $row['username'];
            $_SESSION['client_id'] = $row['id'];
            
            // Redirect sa Client Dashboard (Gawa tayo ng bago para hiwalay sa Admin)
            header("Location: client_home.php");
            exit();
        } else {
            echo "<script>alert('Invalid Password. Please try again.'); window.location='index.php';</script>";
        }
    } else {
        echo "<script>alert('User identity not found in Supreme Network.'); window.location='index.php';</script>";
    }
}
?>
