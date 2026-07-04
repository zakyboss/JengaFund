<?php
/**
 * Shared helpers for creating in-app notifications.
 */

function notifyUser(PDO $pdo, int $userId, string $type, string $message, ?int $relatedId = null): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, message, related_entity_id) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $type, $message, $relatedId]);
}

function notifyAllAdmins(PDO $pdo, string $type, string $message, ?int $relatedId = null): void
{
    $stmt = $pdo->query(
        "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL"
    );
    $insert = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, message, related_entity_id) VALUES (?, ?, ?, ?)"
    );

    while ($adminId = $stmt->fetchColumn()) {
        $insert->execute([(int) $adminId, $type, $message, $relatedId]);
    }
}

function notificationTypeLabel(string $type): string
{
    return ucwords(str_replace('_', ' ', $type));
}

function notificationCampaignIdFromMilestone(PDO $pdo, int $milestoneId): ?int
{
    $stmt = $pdo->prepare('SELECT campaign_id FROM milestones WHERE id = ? LIMIT 1');
    $stmt->execute([$milestoneId]);
    $campaignId = $stmt->fetchColumn();

    return $campaignId ? (int) $campaignId : null;
}

function notificationCampaignIdFromDonation(PDO $pdo, int $donationId): ?int
{
    $stmt = $pdo->prepare('SELECT campaign_id FROM donations WHERE id = ? LIMIT 1');
    $stmt->execute([$donationId]);
    $campaignId = $stmt->fetchColumn();

    return $campaignId ? (int) $campaignId : null;
}

/**
 * Resolve a notification to an app path (without base URL).
 */
function notificationPath(PDO $pdo, string $role, array $notification): ?string
{
    $type = $notification['type'] ?? '';
    $relatedId = isset($notification['related_entity_id']) ? (int) $notification['related_entity_id'] : 0;
    $message = strtolower($notification['message'] ?? '');

    switch ($type) {
        case 'account_approved':
        case 'account_rejected':
            return match ($role) {
                'student' => '/pages/Student/dashboard.php',
                'donor'   => '/pages/Donor/dashboard.php',
                default   => null,
            };

        case 'campaign_approved':
        case 'campaign_rejected':
            if ($relatedId > 0) {
                return match ($role) {
                    'student' => '/pages/Student/campaign_view.php?id=' . $relatedId,
                    'admin'   => '/pages/Admin/campaign_view.php?id=' . $relatedId,
                    default   => null,
                };
            }
            return null;

        case 'donation_received':
            if ($relatedId > 0 && $role === 'student') {
                return '/pages/Student/campaign_view.php?id=' . $relatedId;
            }
            return null;

        case 'donation_receipt':
            if ($role !== 'donor') {
                return null;
            }
            if ($relatedId > 0) {
                $campaignId = notificationCampaignIdFromDonation($pdo, $relatedId);
                if ($campaignId) {
                    return '/pages/Donor/campaign_detail.php?id=' . $campaignId;
                }
            }
            return '/pages/Donor/donations.php';

        case 'milestone_evaluated':
        case 'disbursement_completed':
            if ($relatedId > 0 && $role === 'student') {
                $campaignId = notificationCampaignIdFromMilestone($pdo, $relatedId);
                if ($campaignId) {
                    return '/pages/Student/campaign_view.php?id=' . $campaignId;
                }
            }
            return null;

        case 'project_update':
            if ($relatedId <= 0) {
                return null;
            }

            if ($role === 'admin') {
                if (str_contains($message, 'account awaiting approval')) {
                    return '/pages/Admin/user_view.php?id=' . $relatedId;
                }
                if (str_contains($message, 'campaign pending approval')) {
                    return '/pages/Admin/campaign_view.php?id=' . $relatedId;
                }
                if (str_contains($message, 'milestone evidence')) {
                    $campaignId = notificationCampaignIdFromMilestone($pdo, $relatedId);
                    if ($campaignId) {
                        return '/pages/Admin/campaign_view.php?id=' . $campaignId;
                    }
                }
                return null;
            }

            if ($role === 'student') {
                return '/pages/Student/campaign_view.php?id=' . $relatedId;
            }

            return null;

        default:
            return null;
    }
}

function notificationLink(PDO $pdo, string $role, array $notification, string $baseUrl): ?string
{
    $path = notificationPath($pdo, $role, $notification);
    if ($path === null) {
        return null;
    }

    return rtrim($baseUrl, '/') . $path;
}
