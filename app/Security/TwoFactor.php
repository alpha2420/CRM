<?php

namespace App\Security;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time codes (TOTP, as used by Google Authenticator,
 * Microsoft Authenticator, 1Password, Authy) plus single-use recovery codes.
 */
final class TwoFactor
{
    private const RECOVERY_CODES = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Start setup: a new secret the user must confirm with a code.
     */
    public function begin(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /**
     * Finish setup. Returns the recovery codes to show once, or null if the
     * code was wrong.
     *
     * @return list<string>|null
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || ! $this->verifyCode($user, $code)) {
            return null;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /**
     * Accepts either a 6-digit app code or an unused recovery code.
     */
    public function check(User $user, string $input): bool
    {
        $input = trim($input);

        return preg_match('/^\d{6}$/', str_replace(' ', '', $input))
            ? $this->verifyCode($user, str_replace(' ', '', $input))
            : $this->useRecoveryCode($user, $input);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function qrCodeSvg(User $user): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    private function verifyCode(User $user, string $code): bool
    {
        // Accept the current window and one either side (clock drift), but
        // never a window at or before the last one used: no replays.
        $step = $this->google2fa->verifyKeyNewer($user->two_factor_secret, $code, $user->two_factor_last_step, 1);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step === true ? $this->google2fa->getTimestamp() : $step])->save();

        return true;
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $match = collect($codes)->first(fn (string $stored) => hash_equals($stored, Str::upper($code)));

        if ($match === null) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$match]))])->save();

        return true;
    }
}
