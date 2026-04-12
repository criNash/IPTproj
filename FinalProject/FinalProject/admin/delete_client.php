<?php
include 'dbconnect.php';
session_start();

if(isset($_GET['id']) && isset($_SESSION['admin'])){
    $id = $_GET['id'];
    $sql = "DELETE FROM clients WHERE id = $id";
    
    if(mysqli_query($conn, $sql)){
        echo "<script>alert('Client record deleted successfully.'); window.location='client_list.php';</script>";
    } else {
        echo "<script>alert('Error deleting record.'); window.location='client_list.php';</script>";
    }
}
?>