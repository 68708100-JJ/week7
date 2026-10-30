<?php
include 'condb.php';

try {
    $stmt = $conn->query(
        "SELECT emp AS emp_id, firstName, lastName, phone, username
         FROM employee ORDER BY emp DESC"
    );
    $datas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($datas);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "ไม่สามารถดึงข้อมูลพนักงานได้"]);
}
?>