<?php

header('Content-Type: application/json; charset=UTF-8');
header('X-API-Version: 1.0.0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$allowedOrigins = [
    'http://localhost',
    'https://localhost',
    'http://localhost:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../system/Auth.php';
require_once __DIR__ . '/../system/RateLimiter.php';

$startTime = microtime(true);

try {
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = '/zyn-earn/api';
    if (strpos($requestUri, $basePath) === 0) {
        $route = substr($requestUri, strlen($basePath));
    } else {
        $route = $requestUri;
    }

    $route = '/' . trim($route, '/');
    if ($route === '/') {
        $route = '/' . ($_GET['route'] ?? '');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $clientIp = explode(',', $clientIp)[0];
    $clientIp = trim($clientIp);

    if (!$rateLimiter->check($clientIp, 'api')) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $rateLimiter->getRetryAfter($clientIp, 'api')
        ]);
        exit;
    }

    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $bearerToken = '';

    if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        $bearerToken = $matches[1];
    }

    $publicRoutes = [
        '/auth/login' => 'POST',
        '/auth/register' => 'POST',
        '/auth/verify' => 'POST',
        '/auth/reset-password' => 'POST',
        '/payments/webhook' => 'POST'
    ];

    $isPublicRoute = isset($publicRoutes[$route]) && $publicRoutes[$route] === $method;

    if (!$isPublicRoute) {
        $authenticated = false;

        if (!empty($apiKey)) {
            $stmt = $db->prepare("SELECT id, user_id, expires_at FROM api_keys WHERE api_key = ? AND status = 'active'");
            $stmt->execute([hash('sha256', $apiKey)]);
            $keyData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($keyData) {
                if (!$keyData['expires_at'] || strtotime($keyData['expires_at']) > time()) {
                    $authenticated = true;
                    $_REQUEST['auth_user_id'] = $keyData['user_id'];
                }
            }
        }

        if (!$authenticated && !empty($bearerToken)) {
            try {
                $auth = new Auth($db);
                $session = $auth->validateSession($bearerToken);
                if ($session) {
                    $authenticated = true;
                    $_REQUEST['auth_user_id'] = $session['user_id'];
                    $_REQUEST['auth_session'] = $session;

                    $auth->extendSession($bearerToken);
                }
            } catch (Exception $e) {
                $authenticated = false;
            }
        }

        if (!$authenticated) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'message' => 'Please provide a valid API key or authentication token'
            ]);
            exit;
        }
    }

    $allowedRoutes = [
        '/auth/login' => 'auth.php',
        '/auth/register' => 'auth.php',
        '/auth/verify' => 'auth.php',
        '/auth/reset-password' => 'auth.php',
        '/auth/profile' => 'auth.php',
        '/earnings' => 'earnings.php',
        '/earnings/stats' => 'earnings.php',
        '/earnings/claim' => 'earnings.php',
        '/earnings/balance' => 'earnings.php',
        '/payments/withdraw' => 'payments.php',
        '/payments/withdrawals' => 'payments.php',
        '/payments/deposit' => 'payments.php',
        '/payments/webhook' => 'payments.php',
        '/payments/methods' => 'payments.php',
        '/referrals' => 'referrals.php',
        '/referrals/stats' => 'referrals.php',
        '/referrals/tree' => 'referrals.php',
        '/referrals/claim-bonus' => 'referrals.php'
    ];

    if (!isset($allowedRoutes[$route])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Not Found',
            'message' => 'The requested endpoint does not exist',
            'available_endpoints' => array_keys($allowedRoutes)
        ]);
        exit;
    }

    $handlerFile = __DIR__ . '/' . $allowedRoutes[$route];

    if (!file_exists($handlerFile)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => 'Handler not found'
        ]);
        exit;
    }

    require_once $handlerFile;

    $handlerClass = 'API\\' . pathinfo($allowedRoutes[$route], PATHINFO_FILENAME);
    $handlerClass = 'API\\' . str_replace('.php', '', $allowedRoutes[$route]);

    $className = 'API_' . str_replace('.php', '', $allowedRoutes[$route]);
    if (class_exists($className)) {
        $handler = new $className($db, $method, $route);
        $response = $handler->handle();
    } else {
        $functionName = 'handle_' . str_replace('/', '_', trim($route, '/'));
        if (function_exists($functionName)) {
            $response = $functionName($db, $method, $route);
        } else {
            http_response_code(501);
            echo json_encode([
                'success' => false,
                'error' => 'Not Implemented',
                'message' => "Handler for {$route} is not implemented"
            ]);
            exit;
        }
    }

    $elapsed = round((microtime(true) - $startTime) * 1000, 2);

    if (is_array($response)) {
        $response['_meta'] = [
            'response_time_ms' => $elapsed,
            'api_version' => '1.0.0',
            'timestamp' => date('c')
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo $response;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'An internal database error occurred'
    ]);

    logError("API Database Error: " . $e->getMessage() . " in {$route}");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => APP_DEBUG ? $e->getMessage() : 'An unexpected error occurred'
    ]);

    logError("API Error: " . $e->getMessage() . " in {$route}");
}
