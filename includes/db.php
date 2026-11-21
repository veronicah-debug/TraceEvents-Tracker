<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Africa/Nairobi');
$conn = mysqli_connect('localhost', 'root', '', 'trace_events_tracker');
mysqli_set_charset($conn, 'utf8mb4');