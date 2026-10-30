<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function respond($success, $message, $data = null, $status = 200)
{
    http_response_code($status);
    $response = ["success" => $success, "message" => $message];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response);
    exit;
}

function getAssetId($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : null;
}

function getAssetFields()
{
    $asset_code = trim($_POST["asset_code"] ?? "");
    $asset_name = trim($_POST["asset_name"] ?? "");
    $purchase_date = trim($_POST["purchase_date"] ?? "");
    $price = trim($_POST["price"] ?? "");

    if ($asset_code === "" || strlen($asset_code) > 50) {
        respond(false, "กรุณากรอกรหัสครุภัณฑ์ไม่เกิน 50 ตัวอักษร", null, 400);
    }
    if ($asset_name === "" || mb_strlen($asset_name, "UTF-8") > 255) {
        respond(false, "กรุณากรอกชื่อครุภัณฑ์ไม่เกิน 255 ตัวอักษร", null, 400);
    }

    if ($purchase_date !== "") {
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $purchase_date);
        if (!$date || $date->format("Y-m-d") !== $purchase_date) {
            respond(false, "รูปแบบวันที่ซื้อไม่ถูกต้อง", null, 400);
        }
    } else {
        $purchase_date = null;
    }

    if ($price !== "") {
        if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) {
            respond(false, "กรุณากรอกราคาให้ถูกต้อง", null, 400);
        }
        $price = number_format((float) $price, 2, ".", "");
    } else {
        $price = null;
    }

    return [$asset_code, $asset_name, $purchase_date, $price];
}

function storeUploadedImage()
{
    if (!isset($_FILES["image"]) || $_FILES["image"]["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException("อัปโหลดรูปภาพไม่สำเร็จ");
    }
    if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException("รูปภาพต้องมีขนาดไม่เกิน 5 MB");
    }

    $imageInfo = getimagesize($_FILES["image"]["tmp_name"]);
    $allowedTypes = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG => "png",
        IMAGETYPE_WEBP => "webp",
        IMAGETYPE_GIF => "gif"
    ];
    if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
        throw new InvalidArgumentException("รองรับเฉพาะไฟล์ JPG, PNG, WebP หรือ GIF");
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException("ไม่สามารถเตรียมโฟลเดอร์อัปโหลดได้");
    }

    $filename = bin2hex(random_bytes(16)) . "." . $allowedTypes[$imageInfo[2]];
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target)) {
        throw new RuntimeException("ไม่สามารถบันทึกรูปภาพได้");
    }

    return $filename;
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

include "condb.php";

try {
    $method = $_SERVER["REQUEST_METHOD"];

    if ($method === "GET") {
        $stmt = $conn->query("SELECT * FROM assets ORDER BY asset_id DESC");
        respond(true, "ดึงข้อมูลครุภัณฑ์เรียบร้อย", $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($method !== "POST") {
        respond(false, "Method ไม่ถูกต้อง", null, 405);
    }

    $action = $_POST["action"] ?? "";
    if ($action === "delete") {
        $asset_id = getAssetId($_POST["asset_id"] ?? null);
        if ($asset_id === null) {
            respond(false, "ไม่พบรหัสครุภัณฑ์", null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM assets WHERE asset_id = :asset_id");
        $stmt->execute([":asset_id" => $asset_id]);
        if ($stmt->rowCount() === 0) {
            respond(false, "ไม่พบข้อมูลครุภัณฑ์", null, 404);
        }
        respond(true, "ลบครุภัณฑ์เรียบร้อย");
    }

    if ($action !== "add" && $action !== "update") {
        respond(false, "Action ไม่ถูกต้อง", null, 400);
    }

    [$asset_code, $asset_name, $purchase_date, $price] = getAssetFields();
    $asset_id = null;
    if ($action === "update") {
        $asset_id = getAssetId($_POST["asset_id"] ?? null);
        if ($asset_id === null) {
            respond(false, "ไม่พบรหัสครุภัณฑ์", null, 400);
        }

        $stmt = $conn->prepare("SELECT asset_id FROM assets WHERE asset_id = :asset_id");
        $stmt->execute([":asset_id" => $asset_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            respond(false, "ไม่พบข้อมูลครุภัณฑ์", null, 404);
        }
    }

    $filename = storeUploadedImage();
    if ($action === "add") {
        $stmt = $conn->prepare(
            "INSERT INTO assets (asset_code, asset_name, purchase_date, price, image)
             VALUES (:asset_code, :asset_name, :purchase_date, :price, :image)"
        );
        $stmt->execute([
            ":asset_code" => $asset_code,
            ":asset_name" => $asset_name,
            ":purchase_date" => $purchase_date,
            ":price" => $price,
            ":image" => $filename
        ]);
        respond(true, "เพิ่มครุภัณฑ์เรียบร้อย");
    }

    $imageSql = $filename === null ? "" : ", image = :image";
    $stmt = $conn->prepare(
        "UPDATE assets SET asset_code = :asset_code, asset_name = :asset_name,
         purchase_date = :purchase_date, price = :price $imageSql
         WHERE asset_id = :asset_id"
    );
    $params = [
        ":asset_code" => $asset_code,
        ":asset_name" => $asset_name,
        ":purchase_date" => $purchase_date,
        ":price" => $price,
        ":asset_id" => $asset_id
    ];
    if ($filename !== null) {
        $params[":image"] = $filename;
    }
    $stmt->execute($params);
    respond(true, "แก้ไขครุภัณฑ์เรียบร้อย");
} catch (InvalidArgumentException $e) {
    respond(false, $e->getMessage(), null, 400);
} catch (PDOException $e) {
    if (isset($filename) && $filename !== null) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
    error_log($e->getMessage());
    respond(false, "เกิดข้อผิดพลาดในการจัดการข้อมูลครุภัณฑ์", null, 500);
} catch (Throwable $e) {
    if (isset($filename) && $filename !== null) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
    error_log($e->getMessage());
    respond(false, "เกิดข้อผิดพลาดในการจัดการข้อมูลครุภัณฑ์", null, 500);
}
?>
