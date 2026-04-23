<?php

namespace App;

enum UserRoleEnum: string
{

    // Although the permissions are mostly managed in the database and via gates, 
    // these three roles are used because they have special purposes in the app:
    // - Admin: has access to everything, but no explicit permissions (Gate::before handles this).
    // - Customer: represents users that belong to a customer organization, with no access by default until permissions are granted.
    // - Web: regular users with permissions assigned in the database.
    
    case WEB = 'web';
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::WEB => __('User'),
            self::CUSTOMER => __('Customer'),
            self::ADMIN => __('Admin'),
        };
    }
}
