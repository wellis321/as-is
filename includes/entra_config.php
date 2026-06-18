<?php

declare(strict_types=1);

/** Whether Microsoft Entra sign-in is configured and enabled. */
function entra_enabled(): bool
{
    if (filter_var(getenv('ENTRA_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN) !== true) {
        return false;
    }

    return entra_tenant_id() !== '' && entra_client_id() !== '' && entra_client_secret() !== '';
}

function entra_tenant_id(): string
{
    return trim((string) (getenv('ENTRA_TENANT_ID') ?: ''));
}

function entra_client_id(): string
{
    return trim((string) (getenv('ENTRA_CLIENT_ID') ?: ''));
}

function entra_client_secret(): string
{
    return trim((string) (getenv('ENTRA_CLIENT_SECRET') ?: ''));
}

function entra_redirect_uri(): string
{
    $override = trim((string) (getenv('ENTRA_REDIRECT_URI') ?: ''));
    if ($override !== '') {
        return $override;
    }

    return app_url('/auth/microsoft/callback.php');
}

/** @return list<string> */
function entra_allowed_role_names(): array
{
    $raw = getenv('ENTRA_ALLOWED_ROLES') ?: 'SOR.Admin,SOR.Editor,SOR.Viewer';

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

/** Map Entra app role value → internal app_role. */
function entra_role_map(): array
{
    $map = [];
    foreach (entra_allowed_role_names() as $name) {
        if (str_ends_with($name, '.Admin')) {
            $map[$name] = 'admin';
        } elseif (str_ends_with($name, '.Editor')) {
            $map[$name] = 'editor';
        } elseif (str_ends_with($name, '.Viewer')) {
            $map[$name] = 'viewer';
        }
    }

    if ($map === []) {
        return [
            'SOR.Admin'  => 'admin',
            'SOR.Editor' => 'editor',
            'SOR.Viewer' => 'viewer',
        ];
    }

    return $map;
}
