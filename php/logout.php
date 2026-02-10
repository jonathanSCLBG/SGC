<?php
session_start();
session_destroy();
header("Location: ../login.html"); // ajusta si tu login está en otra ruta
exit;
