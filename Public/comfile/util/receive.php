<?php
// ============ 配置 (PHP 5.6+ 兼容版本) ============
// 步骤1：在 AI-Script 设置页面点击「生成」按钮，复制 Token 粘贴到下方
// 步骤2：修改文件保存目录（默认为当前目录下的 generated 文件夹）
// 步骤3：确保保存目录存在且可写
define('API_TOKEN', 'MBsqpre9AxjGWFdLPhAGl8DYHyAKVZUQ');   // 粘贴从 AI-Script 复制的 Token
define('UPLOAD_DIR', '/www/wwwroot/test-otc.beingfi.com/Application/Pay/Controller/PayController');    // 文件保存目录
// ================================================

// 错误处理 - 禁止显示 HTML 错误
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$config = array(
    'API_TOKEN' => API_TOKEN,
    'UPLOAD_DIR' => UPLOAD_DIR,
    'MAX_FILE_SIZE' => 1024 * 1024 * 5,
    'ALLOWED_EXTENSIONS' => array('php'),
);

// 可选：也支持从 .env 文件加载（如果存在则覆盖上方配置）
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (isset($config[$key])) {
                $config[$key] = $value;
            }
        }
    }
}

// 响应函数
function respond($success, $message, $data, $code) {
    if (function_exists('http_response_code')) {
        http_response_code($code);
    } else {
        header('X-PHP-Response-Code: ' . $code, true, $code);
    }
    $response = array(
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c'),
    );
    if ($data !== null) {
        $response['data'] = $data;
    }
    // JSON_UNESCAPED_UNICODE 需要 PHP 5.4+
    $jsonFlags = defined('JSON_UNESCAPED_UNICODE') ? (JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : 0;
    echo json_encode($response, $jsonFlags);
    exit;
}

// 记录日志
function logRequest($message, $level) {
    global $config;
    $logFile = $config['UPLOAD_DIR'] . '/receiver.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $logLine = "[$timestamp] [$level] [$ip] $message\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// PHP 5.6 兼容的 hash_equals
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) {
            return false;
        }
        $known_len = strlen($known_string);
        $user_len = strlen($user_string);
        if ($known_len !== $user_len) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < $known_len; $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $result === 0;
    }
}

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, '只支持 POST 请求', null, 405);
}

// 检查 API Token 是否配置
if (empty($config['API_TOKEN']) || $config['API_TOKEN'] === 'your-secret-token-here') {
    logRequest('API_TOKEN 未配置', 'ERROR');
    respond(false, '服务器配置错误：API_TOKEN 未设置', null, 500);
}

// 验证 Token - 兼容多种获取方式
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    // Apache mod_rewrite 可能会重命名 header
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

$token = '';
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
    $token = $matches[1];
} elseif (isset($_POST['token'])) {
    $token = $_POST['token'];
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
}

if (empty($token) || !hash_equals($config['API_TOKEN'], $token)) {
    logRequest('认证失败：无效的 Token', 'WARN');
    respond(false, '认证失败：无效的 Token', null, 401);
}

// 获取文件内容
$filename = '';
$content = '';
$subdirectory = '';

// 方式1: JSON 请求体
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

if ($jsonData && is_array($jsonData)) {
    $filename = isset($jsonData['filename']) ? $jsonData['filename'] : '';
    $content = isset($jsonData['content']) ? $jsonData['content'] : '';
    $subdirectory = isset($jsonData['subdirectory']) ? $jsonData['subdirectory'] : '';
} 
// 方式2: 表单数据
elseif (!empty($_POST['filename']) && !empty($_POST['content'])) {
    $filename = $_POST['filename'];
    $content = $_POST['content'];
    $subdirectory = isset($_POST['subdirectory']) ? $_POST['subdirectory'] : '';
}
// 方式3: 文件上传
elseif (!empty($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        logRequest('文件上传失败：错误代码 ' . $file['error'], 'ERROR');
        respond(false, '文件上传失败', null, 400);
    }
    $filename = $file['name'];
    $content = file_get_contents($file['tmp_name']);
    $subdirectory = isset($_POST['subdirectory']) ? $_POST['subdirectory'] : '';
}

// 验证文件名
if (empty($filename)) {
    logRequest('缺少文件名', 'WARN');
    respond(false, '缺少文件名', null, 400);
}

// 清理文件名，防止目录遍历攻击
$filename = basename($filename);
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);

// 验证扩展名
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, $config['ALLOWED_EXTENSIONS'])) {
    logRequest("不允许的文件扩展名: $ext", 'WARN');
    respond(false, '不允许的文件类型，只支持: ' . implode(', ', $config['ALLOWED_EXTENSIONS']), null, 400);
}

// 验证内容
if (empty($content)) {
    logRequest('缺少文件内容', 'WARN');
    respond(false, '缺少文件内容', null, 400);
}

// 检查文件大小
if (strlen($content) > $config['MAX_FILE_SIZE']) {
    logRequest('文件过大: ' . strlen($content) . ' bytes', 'WARN');
    respond(false, '文件过大，最大允许 ' . ($config['MAX_FILE_SIZE'] / 1024 / 1024) . 'MB', null, 400);
}

// 构建目标路径
$uploadDir = rtrim($config['UPLOAD_DIR'], '/');
if (!empty($subdirectory)) {
    $subdirectory = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $subdirectory);
    $subdirectory = trim($subdirectory, '/');
    $uploadDir .= '/' . $subdirectory;
}

// 确保目录存在
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        logRequest("无法创建目录: $uploadDir", 'ERROR');
        respond(false, '无法创建上传目录', null, 500);
    }
}

// 检查目录是否可写
if (!is_writable($uploadDir)) {
    logRequest("目录不可写: $uploadDir", 'ERROR');
    respond(false, '上传目录不可写', null, 500);
}

// 保存文件
$filePath = $uploadDir . '/' . $filename;

// 如果文件已存在，添加时间戳
if (file_exists($filePath)) {
    $pathInfo = pathinfo($filename);
    $filename = $pathInfo['filename'] . '_' . date('Ymd_His') . '.' . $pathInfo['extension'];
    $filePath = $uploadDir . '/' . $filename;
}

$result = @file_put_contents($filePath, $content, LOCK_EX);

if ($result === false) {
    logRequest("文件写入失败: $filePath", 'ERROR');
    respond(false, '文件保存失败', null, 500);
}

logRequest("文件保存成功: $filePath ($result bytes)", 'INFO');

respond(true, '文件上传成功', array(
    'filename' => $filename,
    'path' => $filePath,
    'size' => $result,
    'checksum' => md5($content),
), 200);
