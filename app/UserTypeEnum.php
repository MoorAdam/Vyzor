<?php

namespace App;

enum UserTypeEnum: string
{
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
