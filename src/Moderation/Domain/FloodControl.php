<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

/**
 * Flood control specification.
 *
 * Domain scar recovered from original FluxBB post.php:
 * - Each user group has a flood interval (g_post_flood), in seconds
 * - A user cannot post again until g_post_flood seconds have passed
 *   since their last post (user.last_post)
 * - Admins and moderators (isAdmmod) are EXEMPT from flood control
 * - The check happens BEFORE the post is processed
 *
 * Original code (post.php:65):
 *   if (!isset($_POST['preview']) && $pun_user['last_post'] != ''
 *       && (time() - $pun_user['last_post']) < $pun_user['g_post_flood'])
 *
 * @see https://github.com/fluxbb/fluxbb/issues/93 (flood by group permissions)
 */
class FloodControl
{
    /**
     * Check if the user is allowed to post based on flood control rules.
     *
     * @param int  $lastPostTimestamp Unix timestamp of the user's last post
     * @param int  $floodInterval     Flood interval in seconds (from user group)
     * @param bool $isAdmmod          Whether the user is admin or moderator (exempt)
     * @param int  $now               Current Unix timestamp
     * @return bool True if the user is allowed to post (not flooding)
     */
    public function isAllowed(
        int $lastPostTimestamp,
        int $floodInterval,
        bool $isAdmmod,
        int $now,
    ): bool {
        // Admins and moderators are always exempt from flood control
        if ($isAdmmod) {
            return true;
        }

        // If the user has never posted, allow
        if ($lastPostTimestamp === 0) {
            return true;
        }

        // Check if enough time has passed
        return ($now - $lastPostTimestamp) >= $floodInterval;
    }

    /**
     * Calculate the remaining cooldown time in seconds.
     *
     * @param int $lastPostTimestamp Unix timestamp of the user's last post
     * @param int $floodInterval     Flood interval in seconds
     * @param int $now               Current Unix timestamp
     * @return int Remaining seconds (0 if no flood)
     */
    public function remainingCooldown(
        int $lastPostTimestamp,
        int $floodInterval,
        int $now,
    ): int {
        if ($lastPostTimestamp === 0) {
            return 0;
        }

        $elapsed = $now - $lastPostTimestamp;
        return max(0, $floodInterval - $elapsed);
    }
}