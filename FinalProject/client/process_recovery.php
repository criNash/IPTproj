<?php
session_start();

$servername = "sql204.byethost12.com";
$username = "b12_41435332";
$password = "wstaccount"; 
$dbname = "b12_41435332_finals";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// STEP 1: PAG-CHECK NG EMAIL
if(isset($_POST['btn_email'])){
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $res = $conn->query("SELECT * FROM clients WHERE email='$email'");
    
    if($res->num_rows > 0){
        $u = $res->fetch_assoc();
        $_SESSION['step'] = 'verify';
        $_SESSION['target_user'] = $email;
        
 
        $_SESSION['user_q'] = $u['security_question']; 
        
        header("Location: index.php");
    } else { 
        echo "<script>alert('Email not found!'); window.location='index.php?new=1';</script>"; 
    }
    exit();
}

if(isset($_POST['btn_verify'])){
    $email = $_SESSION['target_user'];
    $ans = mysqli_real_escape_string($conn, $_POST['ans']);
    $pin = mysqli_real_escape_string($conn, $_POST['pin']);
    
    $res = $conn->query("SELECT * FROM clients WHERE email='$email' AND (security_answer='$ans' OR recovery_pin='$pin')");
    
    if($res->num_rows > 0){ 
        $_SESSION['step'] = 'reset'; 
        header("Location: index.php"); // Lilipat sa Step 3 sa loob ng modal
    } else { 
        echo "<script>alert('WRONG PIN OR ANSWER, TRY AGAIN.'); window.location='index.php';</script>"; 
    }
    exit();
}

if(isset($_POST['btn_reset'])){
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];
    
    if($pass1 === $pass2){
        $new_hashed_password = password_hash($pass1, PASSWORD_BCRYPT);
        $email = $_SESSION['target_user'];
        
        $update = $conn->query("UPDATE clients SET password='$new_hashed_password' WHERE email='$email'");
        
        if($update){
            session_destroy(); // Burahin ang session para malinis
            echo "<script>alert('Success! Password updated. Login now!'); window.location='index.php';</script>";
        }
    } else { 
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>"; 
    }
    exit();
}
?>
