<?php

namespace Modules\Tally\Services\Demo;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Tally\Enums\TallyPermission;

/**
 * Token reuse across demo command runs.
 *
 * First run: mint a Sanctum token, persist its plaintext in storage/app/tally-demo/token.txt.
 * Later runs: read plaintext back, verify its hash still exists in personal_access_tokens.
 * If file missing or hash mismatch, mint a fresh token and overwrite the file.
 */
final class DemoTokenVault
{
    public static function ensureUser(): User
    {
        $permissions = array_map(fn (TallyPermission $p) => $p->value, TallyPermission::cases());

        return User::updateOrCreate(
            ['email' => DemoConstants::USER_EMAIL],
            [
                'name' => DemoConstants::USER_NAME,
                'password' => Hash::make(DemoConstants::USER_PASSWORD),
                'tally_permissions' => $permissions,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * Resolve the demo token, reusing the persisted one if still valid.
     *
     * @return array{token: string, reused: bool}
     */
    public static function resolve(bool $rotate = false): array
    {
        $user = self::ensureUser();

        if (! $rotate) {
            $existing = self::readVault();
            if ($existing !== null && self::tokenStillExists($existing)) {
                return ['token' => $existing, 'reused' => true];
            }
        }

        // Rotate: delete all old tokens, mint fresh, persist.
        $user->tokens()->delete();
        $plain = $user->createToken('tally-demo')->plainTextToken;
        self::writeVault($plain);

        return ['token' => $plain, 'reused' => false];
    }

    public static function clear(): void
    {
        $user = User::where('email', DemoConstants::USER_EMAIL)->first();
        if ($user) {
            $user->tokens()->delete();
        }

        $path = self::vaultPath();
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public static function vaultPath(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, DemoConstants::TOKEN_FILE));
    }

    private static function readVault(): ?string
    {
        $path = self::vaultPath();
        if (! File::exists($path)) {
            return null;
        }

        $contents = trim((string) File::get($path));

        return $contents === '' ? null : $contents;
    }

    private static function writeVault(string $token): void
    {
        $path = self::vaultPath();
        File::ensureDirectoryExists(dirname($path), 0o755, true);
        File::put($path, $token);

        if (function_exists('chmod')) {
            @chmod($path, 0o600);
        }
    }

    private static function tokenStillExists(string $plain): bool
    {
        $parts = explode('|', $plain, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$id, $raw] = $parts;
        $record = PersonalAccessToken::find($id);
        if (! $record) {
            return false;
        }

        return hash_equals($record->token, hash('sha256', $raw));
    }
}
