<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
require __DIR__ . '/../includes/db_connect.php';

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header("Location: admin_add_media.php");
    exit;
}

if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $caption = trim($_POST['caption'] ?? '');
    $media_type = $_POST['media_type'] ?? 'image';
    $category = $_POST['category'] ?? 'other';
    $uploaded_by = $_SESSION['admin_name'] ?? 'Admin';

    if (!empty($_FILES['media_file']['name'])) {
        $uploads_dir = __DIR__ . '/../uploads/gallery/';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Invalid file type. Allowed: jpg, jpeg, png, webp, gif'); window.history.back();</script>";
            exit;
        }

        $fn = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($_FILES['media_file']['tmp_name'], $uploads_dir . $fn)) {
            $media_url = 'uploads/gallery/' . $fn;
            $stmt = $conn->prepare("INSERT INTO gallery_media (title, caption, media_type, category, media_url, thumbnail_url, uploaded_by, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $thumb = $media_type === 'video' ? null : $media_url;
            $stmt->bind_param("sssssss", $title, $caption, $media_type, $category, $media_url, $thumb, $uploaded_by);
            $stmt->execute();
            $stmt->close();
            header("Location: gallery_list.php");
            exit;
        }
    }
    echo "<script>alert('Upload failed.'); window.history.back();</script>";
    exit;
}

header("Location: gallery_list.php");
exit;
