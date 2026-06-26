<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppTestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_whatsapp_test_requires_phone(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/settings/whatsapp/test', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['test_phone']);
    }

    public function test_whatsapp_test_uses_unsaved_form_config(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        Http::fake([
            'api.interakt.ai/*' => Http::response(['result' => true], 200),
        ]);

        $this->postJson('/api/v1/settings/whatsapp/test', [
            'test_phone' => '+919876543210',
            'provider' => 'interakt',
            'interakt_api_key' => 'test-api-key',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'interakt');
    }

    public function test_whatsapp_test_fails_when_provider_not_configured(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/settings/whatsapp/test', [
            'test_phone' => '+919876543210',
            'provider' => 'interakt',
            'interakt_api_key' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_whatsapp_test_accepts_numeric_meta_ids(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $this->postJson('/api/v1/settings/whatsapp/test', [
            'test_phone' => '+917887969118',
            'provider' => 'meta_cloud',
            'meta_phone_number_id' => 1096089260264884,
            'meta_waba_id' => 990756800263341,
            'meta_access_token' => 'test-access-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'meta_cloud');
    }
}
