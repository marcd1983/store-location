<?php

namespace Antlion\StoreLocation\Admin;

use SilverStripe\Admin\ModelAdmin;
use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Model\StoreDepartment;

class StaffAdmin extends ModelAdmin
{
    private static string $menu_title = 'Store Staff';
    private static string $url_segment = 'store-staff';

    private static array $managed_models = [
        StaffMember::class,
        StoreDepartment::class,
    ];
}
