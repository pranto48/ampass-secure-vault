<?php
/**
 * AMPass - Dashboard Controller
 */

require_once __DIR__ . '/../models/VaultItem.php';
require_once __DIR__ . '/../models/Folder.php';

class DashboardController {

    public function index(): void {
        $userId = Session::getUserId();
        
        $stats = VaultItem::getStats($userId);
        $recentItems = VaultItem::getRecentlyUsed($userId, 5);
        $favorites = VaultItem::getFavorites($userId);
        $typeCounts = VaultItem::countByType($userId);

        $data = [
            'stats' => $stats,
            'recentItems' => $recentItems,
            'favorites' => $favorites,
            'typeCounts' => $typeCounts
        ];

        require __DIR__ . '/../views/layouts/app.php';
    }

    protected function getContent(): string {
        return 'dashboard/index';
    }
}
