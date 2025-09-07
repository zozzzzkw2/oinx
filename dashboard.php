<?php
header('Content-Type: application/json');

// إعدادات الاتصال بقاعدة البيانات SQLite
$db_path = 'applications.db';

// إنشاء الاتصال بقاعدة البيانات
try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // إنشاء الجدول إذا لم يكن موجوداً
    $db->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        specialization TEXT NOT NULL,
        email TEXT NOT NULL,
        cv_filename TEXT NOT NULL,
        submission_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage()]);
    exit;
}

// معالجة الإجراءات المختلفة
$action = $_GET['action'] ?? '';

if ($action === 'submit_application') {
    // التحقق من صحة البيانات
    if (empty($_POST['fullName']) || empty($_POST['specialization']) || empty($_POST['email']) || empty($_FILES['cv'])) {
        echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
        exit;
    }
    
    // معالجة رفع الملف
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $cv_file = $_FILES['cv'];
    $file_extension = pathinfo($cv_file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $_POST['fullName']) . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($cv_file['tmp_name'], $file_path)) {
        // حفظ البيانات في قاعدة البيانات
        try {
            $stmt = $db->prepare("INSERT INTO applications (full_name, specialization, email, cv_filename) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['fullName'], $_POST['specialization'], $_POST['email'], $file_name]);
            
            echo json_encode(['success' => true, 'message' => 'تم تقديم الطلب بنجاح']);
        } catch(PDOException $e) {
            unlink($file_path); // حذف الملف إذا فشلت عملية الإدخال
            echo json_encode(['success' => false, 'message' => 'خطأ في حفظ البيانات: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في رفع الملف']);
    }
} elseif ($action === 'get_applications') {
    // التحقق من مفتاح المدير
    $admin_key = $_POST['admin_key'] ?? '';
    if ($admin_key !== 'ONIX_ADMIN_2023') { // يجب تغيير هذا المفتاح في البيئة الإنتاجية
        echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
        exit;
    }
    
    // جلب البيانات من قاعدة البيانات
    try {
        $search = $_POST['search'] ?? '';
        $filter = $_POST['filter'] ?? '';
        
        $query = "SELECT * FROM applications";
        $params = [];
        
        if (!empty($search) || !empty($filter)) {
            $conditions = [];
            
            if (!empty($search)) {
                $conditions[] = "full_name LIKE ?";
                $params[] = "%$search%";
            }
            
            if (!empty($filter) && $filter !== 'all') {
                $conditions[] = "specialization = ?";
                $params[] = $filter;
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
        }
        
        $query .= " ORDER BY submission_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $applications]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في جلب البيانات: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
}
?>