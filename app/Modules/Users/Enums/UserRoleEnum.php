<?php

namespace App\Modules\Users\Enums;

enum UserRoleEnum: string
{

    // Only roles with hardcoded behavior in the codebase belong in this enum.
    // The full list of available roles lives in the `roles` table — anything
    // can be added via the Roles tab. This enum is a code-side reference for
    // the three structural roles only:
    //
    //   - ADMIN    — Gate::before permission bypass.
    //   - CUSTOMER — distinct profile model, registration flow, and layout.
    //   - WEB      — default permission seed for newly registered users.
    //
    // Custom roles created from the UI (e.g. context_manager, agent_manager,
    // or anything else) are plain rows in the `roles` table; they don't need
    // (and shouldn't have) entries here.

    case WEB = 'web';
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::WEB => __('Web'),
            self::CUSTOMER => __('Customer'),
            self::ADMIN => __('Admin'),
        };
    }
}
