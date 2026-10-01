<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers_and_only_the_lead_form_can_be_embedded(): void
    {
        $response = $this->get('/login');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));

        $admin = $this->registerOrganization();
        $form = new Integration(['type' => IntegrationType::WebForm, 'settings' => ['title' => 'Hi']]);
        $form->organization_id = $admin->organization_id;
        $form->save();

        $embed = $this->get("/f/{$form->webhook_key}");
        $embed->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString('frame-ancestors *', $embed->headers->get('Content-Security-Policy'));
    }

    public function test_two_factor_setup_login_replay_and_recovery(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin)->post('/profile/security/two-factor')->assertRedirect('/profile/security');
        $this->get('/profile/security')->assertSee('Scan this QR code')->assertSee('<svg', false);

        $this->post('/profile/security/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/profile/security/two-factor/confirm', ['code' => $this->currentCode($admin)])->assertSessionHas('recovery_codes');
        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactor());
        $recovery = $admin->two_factor_recovery_codes[0];
        $this->post('/logout');

        // Password alone is not enough any more.
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');

        // The code that was just used to confirm can't be replayed.
        $this->post('/two-factor-challenge', ['code' => $this->currentCode($admin)])->assertSessionHasErrors('code');
        $this->assertGuest();

        // A recovery code works once.
        $this->post('/two-factor-challenge', ['code' => $recovery])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotContains($recovery, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_turning_two_factor_off_needs_the_password(): void
    {
        $admin = $this->withTwoFactor($this->registerOrganization());

        $this->actingAs($admin)->delete('/profile/security/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->delete('/profile/security/two-factor', ['password' => 'password'])->assertSessionHas('status');
        $this->assertFalse($admin->fresh()->hasTwoFactor());
    }

    public function test_a_workspace_can_require_two_factor_for_everyone(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($admin)->put('/settings/workspace', ['name' => 'Acme', 'timezone' => 'Asia/Kolkata', 'require_two_factor' => '1']);
        $this->actingAs($agent->fresh())->get('/leads')->assertRedirect('/profile/security');
        $this->get('/profile/security')->assertOk()->assertSee('requires two-factor login');

        $agent = $this->withTwoFactor($agent->fresh());
        $this->actingAs($agent)->get('/leads')->assertOk();
        $this->delete('/profile/security/two-factor', ['password' => 'password'])->assertSessionHasErrors('password');
    }

    public function test_users_sign_out_their_other_devices(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->registerOrganization();
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $admin->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0', 'payload' => '', 'last_activity' => time()]);
        $token = $admin->remember_token;

        $this->actingAs($admin)->get('/profile/security')->assertSee('Chrome on Android');
        $this->post('/profile/security/sessions/logout-others', ['password' => 'password'])->assertSessionHas('status');

        $this->assertFalse(DB::table('sessions')->where('id', 'other-device')->exists());
        $this->assertNotSame($token, $admin->fresh()->remember_token);
    }

    private function withTwoFactor(User $user): User
    {
        $user->forceFill([
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
        ])->save();

        return $user;
    }

    private function currentCode(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
    }
}
