<?php

namespace App\Modules\Users\Enums;

enum PermissionEnum: string
{
    // Basics
    case VIEW_PROJECTS = 'basics.view-projects';

    // Users
    case VIEW_USERS = 'users.view-list';
    case CREATE_USER = 'users.create-user';
    case EDIT_USER = 'users.edit-user';
    case REMOVE_USER = 'users.remove-user';
    case VIEW_CUSTOMERS = 'users.view-customers';
    case CREATE_CUSTOMER = 'users.create-customer';
    case EDIT_CUSTOMER = 'users.edit-customer';
    case REMOVE_CUSTOMER = 'users.remove-customer';

    // Project
    case VIEW_ALL_PROJECTS = 'project.view-all';
    case EDIT_ALL_PROJECTS = 'project.edit-all';
    case VIEW_OWNED_PROJECTS = 'project.view-owned';
    case VIEW_COLLAB_PROJECTS = 'project.view-collab';
    case CHANGE_PROJECT_STATUS = 'project.change-status';
    case EDIT_PROJECT_DETAILS = 'project.edit-details';
    case DELETE_PROJECT = 'project.delete';
    case CREATE_PROJECT = 'project.create';

    // Clarity
    case VIEW_CLARITY_SNAPSHOTS = 'project.clarity.view-snapshots';
    case VIEW_CLARITY_TRENDS = 'project.clarity.view-trends';
    case FETCH_CLARITY_DATA = 'project.clarity.fetch-data';

    // Google Analytics
    case VIEW_GOOGLE_ANALYTICS = 'project.ga.view';
    case CONFIGURE_GOOGLE_ANALYTICS = 'project.ga.configure';
    case USE_GOOGLE_ANALYTICS_IN_REPORTS = 'project.ga.use-in-reports';

    // Report
    case VIEW_REPORTS = 'project.report.view';
    case CREATE_REPORT = 'project.report.create';
    case EDIT_REPORT = 'project.report.edit';
    case DELETE_REPORT = 'project.report.delete';

    // Heatmap
    case UPLOAD_HEATMAP = 'project.heatmap.upload';
    case VIEW_HEATMAPS = 'project.heatmap.view';
    case EDIT_HEATMAPS = 'project.heatmap.edit';
    case DELETE_HEATMAPS = 'project.heatmap.delete';

    // Context
    case VIEW_CONTEXTS = 'context.view';
    case ADD_CONTEXTS = 'context.add';
    case EDIT_CONTEXTS = 'context.edit';
    case DELETE_CONTEXTS = 'context.delete';

    // Agent
    case VIEW_AGENTS = 'agent.view';
    case ADD_AGENTS = 'agent.add';
    case EDIT_AGENTS = 'agent.edit';
    case DELETE_AGENTS = 'agent.delete';

    // Roles
    case VIEW_ROLES = 'roles.view';
    case ADD_ROLES = 'roles.add';
    case EDIT_ROLES = 'roles.edit';
    case DELETE_ROLES = 'roles.delete';
    case ASSIGN_ROLES = 'roles.assign';

    // Permissions
    case VIEW_PERMS = 'permissions.view';
    case EDIT_PERMS = 'permissions.edit';

    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'basics.') => 'basics',
            str_starts_with($this->value, 'users.') => 'users',
            str_starts_with($this->value, 'project.clarity.') => 'project.clarity',
            str_starts_with($this->value, 'project.ga.') => 'project.ga',
            str_starts_with($this->value, 'project.report.') => 'project.report',
            str_starts_with($this->value, 'project.heatmap.') => 'project.heatmap',
            str_starts_with($this->value, 'project.') => 'project',
            str_starts_with($this->value, 'context.') => 'context',
            str_starts_with($this->value, 'agent.') => 'agent',
            str_starts_with($this->value, 'roles.') => 'roles',
            str_starts_with($this->value, 'permissions.') => 'permissions',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::VIEW_PROJECTS => 'View the projects list',
            self::VIEW_USERS => 'View the users list',
            self::CREATE_USER => 'Create new users',
            self::EDIT_USER => 'Edit existing users',
            self::REMOVE_USER => 'Remove users',
            self::VIEW_CUSTOMERS => 'View the customers list',
            self::CREATE_CUSTOMER => 'Create new customers',
            self::EDIT_CUSTOMER => 'Edit existing customers',
            self::REMOVE_CUSTOMER => 'Remove customers',
            self::VIEW_ALL_PROJECTS => 'View all projects regardless of ownership',
            self::EDIT_ALL_PROJECTS => 'Edit and act on any project regardless of ownership',
            self::VIEW_OWNED_PROJECTS => 'View own projects',
            self::VIEW_COLLAB_PROJECTS => 'View projects as collaborator',
            self::CHANGE_PROJECT_STATUS => 'Change project status',
            self::EDIT_PROJECT_DETAILS => 'Edit project details',
            self::DELETE_PROJECT => 'Delete projects',
            self::CREATE_PROJECT => 'Create new projects',
            self::VIEW_CLARITY_SNAPSHOTS => 'View Clarity snapshots',
            self::VIEW_CLARITY_TRENDS => 'View Clarity trends',
            self::FETCH_CLARITY_DATA => 'Fetch Clarity data',
            self::VIEW_GOOGLE_ANALYTICS => 'View Google Analytics data',
            self::CONFIGURE_GOOGLE_ANALYTICS => 'Configure the GA property ID for projects',
            self::USE_GOOGLE_ANALYTICS_IN_REPORTS => 'Include GA data in AI reports',
            self::VIEW_REPORTS => 'View reports',
            self::CREATE_REPORT => 'Create or request reports',
            self::EDIT_REPORT => 'Edit reports',
            self::DELETE_REPORT => 'Delete reports',
            self::UPLOAD_HEATMAP => 'Upload heatmaps',
            self::VIEW_HEATMAPS => 'View heatmaps',
            self::EDIT_HEATMAPS => 'Edit heatmaps',
            self::DELETE_HEATMAPS => 'Delete heatmaps',
            self::VIEW_CONTEXTS => 'View contexts page',
            self::ADD_CONTEXTS => 'Create new contexts',
            self::EDIT_CONTEXTS => 'Edit existing contexts',
            self::DELETE_CONTEXTS => 'Delete contexts',
            self::VIEW_AGENTS => 'View agent configuration page',
            self::ADD_AGENTS => 'Create new agent configurations',
            self::EDIT_AGENTS => 'Edit agent configurations',
            self::DELETE_AGENTS => 'Delete agent configurations',
            self::VIEW_ROLES => 'View roles list',
            self::ADD_ROLES => 'Create new roles',
            self::EDIT_ROLES => 'Edit role label, description and permission set',
            self::DELETE_ROLES => 'Delete non-system roles',
            self::ASSIGN_ROLES => 'Assign or unassign roles to users',
            self::VIEW_PERMS => 'View permission list',
            self::EDIT_PERMS => 'Edit permission description',
        };
    }
}
