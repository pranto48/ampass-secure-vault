<?php
/**
 * AMPass - Application Router / Front Controller
 * Routes requests to appropriate controllers based on URL path.
 */

class App {
    private string $route;
    private string $method;

    public function __construct() {
        $this->route = trim($_GET['route'] ?? '', '/');
        $this->method = $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Run the application - route to appropriate controller
     */
    public function run(): void {
        // Parse route segments
        $segments = $this->route ? explode('/', $this->route) : ['dashboard'];
        $page = $segments[0] ?? 'dashboard';
        $action = $segments[1] ?? 'index';
        $param = $segments[2] ?? null;

        // API routes (return JSON)
        if ($page === 'api') {
            $this->handleAPI($segments);
            return;
        }

        // Public routes (no auth required)
        $publicRoutes = ['login', 'register', 'forgot-password', 'reset-password'];
        
        if (in_array($page, $publicRoutes)) {
            if (Session::isLoggedIn() && $page !== 'reset-password') {
                $this->redirect('dashboard');
                return;
            }
            $this->loadController($page, $action, $param);
            return;
        }

        // All other routes require authentication
        if (!Session::isLoggedIn()) {
            $this->redirect('login');
            return;
        }

        // Vault unlock required for most pages
        $noUnlockRequired = ['unlock', 'logout', 'settings', 'lock'];
        if (!Session::isVaultUnlocked() && !in_array($page, $noUnlockRequired)) {
            $this->redirect('unlock');
            return;
        }

        // Admin routes
        if ($page === 'admin') {
            if (!Session::isAdmin()) {
                http_response_code(403);
                $this->loadView('errors/403');
                return;
            }
            $this->loadController('admin', $action, $param);
            return;
        }

        // Load the requested controller
        $this->loadController($page, $action, $param);
    }

    /**
     * Handle API requests
     */
    private function handleAPI(array $segments): void {
        header('Content-Type: application/json');
        
        $resource = $segments[1] ?? '';
        $action = $segments[2] ?? 'index';
        $param = $segments[3] ?? null;

        // SECURITY: Validate resource and action names (prevent path traversal / injection)
        if (!preg_match('/^[a-zA-Z]+$/', $resource) || !preg_match('/^[a-zA-Z]+$/', $action)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        // API requires authentication (except specific endpoints)
        $publicAPI = ['auth'];
        if (!in_array($resource, $publicAPI) && !Session::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $controllerFile = __DIR__ . '/../controllers/api/' . ucfirst($resource) . 'ApiController.php';
        
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $className = ucfirst($resource) . 'ApiController';
            $controller = new $className();
            
            // SECURITY: Only allow explicitly defined public methods (not magic/internal methods)
            if (method_exists($controller, $action) && !str_starts_with($action, '__') && (new ReflectionMethod($controller, $action))->isPublic()) {
                $controller->$action($param);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
        }
    }

    /**
     * Load a controller
     */
    private function loadController(string $page, string $action = 'index', ?string $param = null): void {
        // SECURITY: Validate page and action names
        if (!preg_match('/^[a-zA-Z\-]+$/', $page) || !preg_match('/^[a-zA-Z\-]+$/', $action)) {
            $this->show404();
            return;
        }

        $controllerName = str_replace('-', '', ucwords($page, '-')) . 'Controller';
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';

        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controller = new $controllerName();
            
            $methodName = lcfirst(str_replace('-', '', ucwords($action, '-')));
            
            // SECURITY: Only call public methods, never internal/magic methods
            if (method_exists($controller, $methodName) && !str_starts_with($methodName, '__') && (new ReflectionMethod($controller, $methodName))->isPublic()) {
                $controller->$methodName($param);
            } elseif (method_exists($controller, 'index')) {
                $controller->index($param);
            } else {
                $this->show404();
            }
        } else {
            $this->show404();
        }
    }

    /**
     * Load a view file
     */
    public static function loadView(string $view, array $data = []): void {
        extract($data);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            http_response_code(500);
            echo "View not found: " . htmlspecialchars($view);
        }
    }

    /**
     * Redirect to a route
     */
    private function redirect(string $route): void {
        $baseUrl = rtrim(APP_URL, '/');
        header("Location: {$baseUrl}/{$route}");
        exit;
    }

    /**
     * Show 404 page
     */
    private function show404(): void {
        http_response_code(404);
        self::loadView('errors/404');
    }
}
