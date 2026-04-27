<?php

namespace App\Modules\Users\Enums;

enum UserRoleEnum: string
{

    // Users can hold multiple roles; permissions are unioned across all of them.
    // - Admin: bypasses every permission check (Gate::before).
    // - Web: regular internal user.
    // - Customer: external user, no access by default.
    // - ContextManager: can view and manage AI contexts.
    // - AgentManager: can configure AI agents.

    case WEB = 'web';
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';
    case CONTEXT_MANAGER = 'context_manager';
    case AGENT_MANAGER = 'agent_manager';

    public function label(): string
    {
        return match ($this) {
            self::WEB => __('User'),
            self::CUSTOMER => __('Customer'),
            self::ADMIN => __('Admin'),
            self::CONTEXT_MANAGER => __('Context Manager'),
            self::AGENT_MANAGER => __('Agent Manager'),
        };
    }
}
