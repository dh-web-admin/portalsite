<?php
// Role-based page access rules
// Usage: can_access($role, $pageKey) where $pageKey is the filename without .php

function portal_all_pages(): array {
    return [
        'equipments',
        'Bid_tracking',
        'scheduling',
        'engineering',
        'employee_information',
        'for_sale',
        'project_checklist',
        'pictures',
        'forms',
        'manuals',
        'videos',
        'maps',
    ];
}

function allowed_pages_for_role(string $role): array {
    $all = portal_all_pages();
    switch ($role) {
        case 'admin':
        case 'projectmanager':
        case 'estimator':
        case 'accounting':
            return $all;
        case 'superintendent':
            return array_values(array_diff($all, ['Bid_tracking']));
        case 'foreman':
            return array_values(array_diff($all, ['Bid_tracking','maps','engineering']));
        case 'mechanic':
            return array_values(array_diff($all, ['Bid_tracking','maps','engineering','forms','project_checklist']));
        case 'operator':
        case 'laborer':
            return ['employee_information','manuals','videos'];
        default:
            // Unknown role: safest minimal access
            return ['employee_information'];
    }
}

function can_access(string $role, string $pageKey): bool {
    $allowed = allowed_pages_for_role($role);
    return in_array($pageKey, $allowed, true);
}
