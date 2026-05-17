<?php
/**
 * AMPass - Password Generator Controller
 * The actual generation happens client-side for security.
 * This controller just serves the page.
 */

class GeneratorController {

    public function index(): void {
        $csrfToken = CSRF::generateToken();
        $data = ['csrfToken' => $csrfToken];
        require __DIR__ . '/../views/generator/index.php';
    }
}
