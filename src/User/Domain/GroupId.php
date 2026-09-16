<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

/**
 * Group ID enum.
 *
 * Maps to the original FluxBB group constants:
 * - Unverified: 0  (email not confirmed)
 * - Admin:      1  (full access)
 * - Moderator:  2  (per-forum moderation powers)
 * - Guest:      3  (not logged in)
 * - Member:     4  (registered, verified user)
 */
enum GroupId: int
{
    /** Email not yet confirmed — limited permissions. */
    case Unverified = 0;

    /** Full access to all features and administration. */
    case Admin = 1;

    /** Per-forum moderation — can moderate assigned forums. */
    case Moderator = 2;

    /** Not logged in — read-only (if board allows guest reading). */
    case Guest = 3;

    /** Registered and verified — standard posting permissions. */
    case Member = 4;
}