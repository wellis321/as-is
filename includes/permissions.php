<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/entra_config.php';

/** @return array{viewer: int, editor: int, admin: int} */
function role_rank_map(): array
{
    return ['viewer' => 1, 'editor' => 2, 'admin' => 3];
}

function current_app_role(): string
{
    $role = $_SESSION['app_role'] ?? 'viewer';

    return in_array($role, ['viewer', 'editor', 'admin'], true) ? $role : 'viewer';
}

function app_role_label(string $role): string
{
    return match ($role) {
        'admin'  => 'Admin',
        'editor' => 'Editor',
        default  => 'Viewer',
    };
}

/** @return list<string> */
function current_entra_roles(): array
{
    $roles = $_SESSION['entra_roles'] ?? [];

    return is_array($roles) ? $roles : [];
}

function user_has_min_role(string $minRole): bool
{
    $ranks = role_rank_map();
    $current = $ranks[current_app_role()] ?? 0;
    $needed  = $ranks[$minRole] ?? 99;

    return $current >= $needed;
}

function require_min_role(string $minRole): void
{
    require_login();
    if (!user_has_min_role($minRole)) {
        flash('error', 'You do not have permission to access that page.');
        redirect(app_url('/documents.php'));
    }
}

function can_edit_maps(): bool
{
    return user_has_min_role('editor');
}

function can_delete_maps(): bool
{
    return user_has_min_role('admin');
}

function can_manage_systems(): bool
{
    return user_has_min_role('editor');
}

function is_microsoft_user(): bool
{
    return ($_SESSION['auth_provider'] ?? 'local') === 'microsoft';
}

/** Highest internal role from Entra app role claims. */
function map_entra_roles_to_app_role(array $entraRoles): ?string
{
    $map   = entra_role_map();
    $ranks = role_rank_map();
    $best  = null;
    $bestRank = 0;
    foreach ($entraRoles as $name) {
        if (!isset($map[$name])) {
            continue;
        }
        $internal = $map[$name];
        $rank     = $ranks[$internal] ?? 0;
        if ($rank > $bestRank) {
            $bestRank = $rank;
            $best     = $internal;
        }
    }

    return $best;
}
