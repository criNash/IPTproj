<?php

//localhost port of connection
$server = "sql204.byethost12.com";  
$username = "b12_41435332";
$password = "wstaccount";
$db = "b12_41435332_finals";

// port of connection/ connecting the source code to the database
$conn = new mysqli($server, $username,$password,$db);

// error handling for failed connection in database
if ($conn->connect_error) {
    die("Connection failed. Reason: " . $conn->connect_error);
}
 
?>
