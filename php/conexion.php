<?php
function db() {
  $DB_HOST = "localhost";
  $DB_USER = "root";
  $DB_PASS = "";
  $DB_NAME = "sgc"; // <-- CAMBIA ESTO

  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  if ($mysqli->connect_error) {
    throw new Exception("Error conexión BD: " . $mysqli->connect_error);
  }
  $mysqli->set_charset("utf8mb4");
  return $mysqli;
}
