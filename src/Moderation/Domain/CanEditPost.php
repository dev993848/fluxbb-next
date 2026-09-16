<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

/**
 * Specification: whether a user can edit a post.
 *
 * Domain scar recovered from original FluxBB edit.php:
 *
 * - A user can edit their own post IF they have the g_edit_posts permission
 * - A user can edit their own post ONLY within o_edit_timeout seconds
 *   of posting (if set; 0 means no timeout)
 * - Moderators and admins can edit ANY post at ANY time
 * - Moderators CANNOT edit posts by admins
 *
 * Original code (edit.php:41-48):
 *   if (($pun_user['g_edit_posts'] == '0' ||
 *       $cur_post['poster_id'] != $pun_user['id'] ||
 *       $cur_post['closed'] == '1') &&
 *       !$is_admmod)
 *       message($lang_common['No permission'], false, '403 Forbidden');
 *
 * @see https://github.com/fluxbb/fluxbb/issues/44  (edit timeout)
 * @see https://github.com/fluxbb/fluxbb/issues/105 (mod can edit any post)
 */
class CanEditPost
{
    /**
     * Check if a user is allowed to edit a post.
     *
     * @param int  $posterId       The user ID of the post's author
     * @param int  $currentUserId  The current user's ID
     * @param int  $postTimestamp  Unix timestamp when the post was created
     * @param int  $editTimeout    Edit timeout in seconds (0 = no timeout)
     * @param bool $hasEditPerm    Whether the user has g_edit_posts permission
     * @param bool $isAdmmod       Whether the current user is admin/moderator
     * @param bool $isAdmin        Whether the current user is admin (mod cannot edit admin posts)
     * @param bool $posterIsAdmin  Whether the post author is an admin
     * @param bool $topicClosed    Whether the topic is closed
     * @param int  $now            Current Unix timestamp
     * @return bool True if the user is allowed to edit
     */
    public function isSatisfiedBy(
        int $posterId,
        int $currentUserId,
        int $postTimestamp,
        int $editTimeout,
        bool $hasEditPerm,
        bool $isAdmmod,
        bool $isAdmin,
        bool $posterIsAdmin,
        bool $topicClosed,
        int $now,
    ): bool {
        // Cannot edit if topic is closed (unless admin/mod)
        if ($topicClosed && !$isAdmmod) {
            return false;
        }

        // Admin/Mod can edit any post (EXCEPT mod cannot edit admin's posts)
        if ($isAdmmod) {
            return $isAdmin || !$posterIsAdmin;
        }

        // Regular user checks:
        // Must have edit permission
        if (!$hasEditPerm) {
            return false;
        }

        // Must be the post author
        if ($posterId !== $currentUserId) {
            return false;
        }

        // Must be within edit timeout (if set)
        if ($editTimeout > 0) {
            $elapsed = $now - $postTimestamp;
            if ($elapsed > $editTimeout) {
                return false;
            }
        }

        return true;
    }
}