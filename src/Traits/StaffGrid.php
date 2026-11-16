<?php

namespace Antlion\StoreLocation\Traits;

/**
 * Grid classes for the staff blocks' "Card columns" setting (empty = auto).
 *
 * Full class names on purpose: Tailwind finds classes by scanning source,
 * so the theme scans this module's src/ (see the README).
 */
trait StaffGrid
{
    private const array STAFF_GRID = [
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        5 => 'sm:grid-cols-3 lg:grid-cols-5',
        6 => 'sm:grid-cols-3 lg:grid-cols-6',
    ];

    private const string STAFF_GRID_AUTO = 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';

    public function getGridClass(): string
    {
        return 'grid grid-cols-1 gap-6 ' . (self::STAFF_GRID[(int) $this->Columns] ?? self::STAFF_GRID_AUTO);
    }

    /**
     * Heading for the block's items (staff cards, or department titles):
     * one level below the block title when it's shown. Follows the block's
     * heading level from silverwind-blocks when installed, else h2.
     */
    public function getCardHeading(): string
    {
        $level = $this->hasMethod('getHeadingLevel') ? (int) substr($this->getHeadingLevel(), 1) : 2;
        if ($this->Title && $this->ShowTitle) {
            $level++;
        }

        return 'h' . min(6, max(2, $level));
    }

    /** Staff cards under a department title */
    public function getStaffHeading(): string
    {
        return 'h' . min(6, (int) substr($this->getCardHeading(), 1) + 1);
    }
}
