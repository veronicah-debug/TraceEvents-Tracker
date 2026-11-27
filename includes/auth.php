<?php
// includes/auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function role_home(?string $role): string
{
    return match ($role) {
        'STUDENT'    => 'my-tickets.php',
        'ORGANIZER'  => 'organizer.php',
        'MANAGEMENT' => 'management.php',
        default      => 'index.php',
    };
}

function require_role(string $requiredRole): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header('Location: index.php?error=access');
        exit;
    }

    if ($_SESSION['role'] !== $requiredRole) {
        header('Location: index.php?error=access');
        exit;
    }
}

function log_access(
    mysqli $conn,
    ?int $userId,
    string $action,
    ?string $entityType,
    ?int $entityId,
    string $result,
    string $details = ''
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $conn->prepare(
            "INSERT INTO access_log
             (user_id, action, entity_type, entity_id, action_result, ip_address, details)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ississs', $userId, $action, $entityType, $entityId, $result, $ip, $details);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $ex) {
        error_log('Access log error: ' . $ex->getMessage());
    }
}

/**
 * Resolves an event place/venue image.
 * Priority:
 * 1. Uploaded custom image in Images/events/...
 * 2. Matching venue preset in Images/...
 * 3. Category default banner in Images/...
 */
function resolve_event_image(?string $imagePath, string $venue = '', string $category = 'OTHER'): string
{
    if (!empty($imagePath)) {
        $clean = ltrim(str_replace('\\', '/', $imagePath), '/');
        if (str_starts_with($clean, 'Images/') && file_exists(__DIR__ . '/../' . $clean)) {
            return $clean;
        }
    }

    $venueLower = strtolower($venue);
    $venuePresets = [
        'auditorium' => 'Images/venue-auditorium.jpg',
        'main hall'  => 'Images/venue-main-hall.jpg',
        'lab'        => 'Images/venue-lab.jpg',
        'student'    => 'Images/venue-student-center.jpg',
    ];

    foreach ($venuePresets as $keyword => $presetPath) {
        if (str_contains($venueLower, $keyword) && file_exists(__DIR__ . '/../' . $presetPath)) {
            return $presetPath;
        }
    }

    return match ($category) {
        'ACADEMIC' => 'Images/event-academic.jpg',
        'CULTURAL' => 'Images/event-cultural.jpg',
        'SPORTS'   => 'Images/event-sports.jpg',
        'SOCIAL'   => 'Images/event-social.jpg',
        default    => 'Images/event-default.jpg',
    };
}