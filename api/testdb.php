<?php

$host = "localhost";
$user = "root";
$pass = "root123";
$db = "bloomify";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die($conn->connect_error);
}

echo "CONNECTED";