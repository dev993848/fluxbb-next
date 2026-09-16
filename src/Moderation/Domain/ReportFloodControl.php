<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

/**
 * Report flood control specification.
 *
 * Domain scar recovered from original FluxBB issue #80:
 * "report flood protection"
 * Prevents a user from submitting multiple reports in rapid succession.
 *
 * Original implementation in moderate.php and functions.php:
 * - Flood interval is typically the same as the post flood interval
 * - Checked before processing the report
 */
class ReportFloodControl
{
    /**
     * Check if the user is allowed to submit a report.
     *
     * @param int  $lastReportTimestamp Unix timestamp of the user's last report
     * @param int  $floodInterval       Flood interval in seconds
     * @param bool $isAdmmod            Whether the user is admin/mod (exempt)
     * @param int  $now                 Current Unix timestamp
     * @return bool True if allowed to report
     */
    public function isAllowed(
        int $lastReportTimestamp,
        int $floodInterval,
        bool $isAdmmod,
        int $now,
    ): bool {
        if ($isAdmmod) {
            return true;
        }

        if ($lastReportTimestamp === 0) {
            return true;
        }

        return ($now - $lastReportTimestamp) >= $floodInterval;
    }
}