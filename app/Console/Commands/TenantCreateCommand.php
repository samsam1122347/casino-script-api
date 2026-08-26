<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {slug : URL-safe tenant slug (header X-Tenant-Slug)}
        {display_name : Public brand display name}
        {--tagline= : Marketing tagline}
        {--theme= : JSON object of CSS vars to merge (--color-brand, …)}
        {--force : Update display_name/tagline/theme if the slug exists}';

    protected $description = 'Create or update a whitelabel tenant (DB row in `tenants`)';

    /** @var array<string, string> */
    private function defaultTheme(): array
    {
        return [
            '--color-brand' => '#00f080',
            '--color-purple' => '#8a55ff',
            '--color-gold' => '#d4af37',
            '--color-orange' => '#ff7a32',
            '--color-pink' => '#ff3368',
        ];
    }

    public function handle(): int
    {
        $slug = Str::slug(trim((string) $this->argument('slug')));

        if ($slug === '') {
            $this->error('Slug cannot be empty.');

            return self::FAILURE;
        }

        if (strlen($slug) > 64) {
            $this->error('Slug is too long (max 64 characters after slug normalization).');

            return self::FAILURE;
        }

        $displayName = trim((string) $this->argument('display_name'));
        if ($displayName === '') {
            $this->error('display_name cannot be empty.');

            return self::FAILURE;
        }

        $tagline = $this->option('tagline') !== null ? trim((string) $this->option('tagline')) : null;

        $theme = $this->defaultTheme();
        $themeOption = $this->option('theme');
        if (is_string($themeOption) && $themeOption !== '') {
            try {
                /** @var mixed $decoded */
                $decoded = json_decode($themeOption, true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->error('Invalid JSON for --theme= (must be an object map of CSS vars).');

                return self::FAILURE;
            }
            if (! is_array($decoded)) {
                $this->error('--theme must decode to a JSON object.');

                return self::FAILURE;
            }
            /** @var array<string, mixed> $decoded */
            foreach ($decoded as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $theme[$key] = $value;
                }
            }
        }

        $existing = Tenant::query()->where('slug', $slug)->first();

        if ($existing && ! $this->option('force')) {
            $this->error("Tenant [{$slug}] already exists. Pass --force to update.");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'display_name' => $displayName,
                'tagline' => $tagline !== '' ? $tagline : null,
                'theme' => $theme,
            ],
        );

        $this->components->info(
            $existing && $this->option('force')
                ? "Tenant [{$slug}] updated (id {$tenant->id})."
                : "Tenant [{$slug}] created (id {$tenant->id}).",
        );
        $this->line('  display_name: '.$tenant->display_name);
        $this->line('  tagline: '.($tenant->tagline ?? '—'));
        $this->line('  theme keys: '.implode(', ', array_keys($tenant->theme ?? [])));

        return self::SUCCESS;
    }
}
