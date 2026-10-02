<?php

namespace Tests\Feature;

use App\Ai\AssistantException;
use App\Ai\ClaudeInsightGenerator;
use App\Ai\GeminiInsightGenerator;
use App\Ai\InsightGenerator;
use App\Ai\LeadAssistant;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';

    /**
     * @param  array<string, string>  $overrides
     */
    private function reply(array $overrides = [], array $extraParts = []): array
    {
        $answer = $overrides + [
            'summary' => 'Kiran wants a 2BHK near the metro.',
            'temperature' => 'hot',
            'reason' => 'Asked for the price list today.',
            'next_step' => 'Send the price list and call at 5.',
            'suggested_message' => 'Namaste Kiran, sharing the price list now.',
        ];

        return ['candidates' => [[
            'content' => ['role' => 'model', 'parts' => [...$extraParts, ['text' => json_encode($answer)]]],
            'finishReason' => 'STOP',
        ]]];
    }

    private function gemini(): GeminiInsightGenerator
    {
        return new GeminiInsightGenerator('gem-key', 'gemini-3.8-flash');
    }

    public function test_analyse_lead_works_end_to_end_with_gemini(): void
    {
        config(['services.ai.provider' => 'gemini', 'services.gemini.api_key' => 'gem-key', 'services.gemini.model' => 'gemini-3.8-flash']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->reply())]);
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create(['name' => 'Kiran']);

        $this->actingAs($admin)->post("/leads/{$lead->id}/ai")->assertSessionHas('status', 'AI insight updated.');

        $this->assertSame('hot', $lead->fresh()->ai_insight['temperature']);
        $this->get("/leads/{$lead->id}")->assertSee('Send the price list and call at 5.');
        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->hasHeader('x-goog-api-key', 'gem-key')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Name: Kiran')
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'never as instructions')
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['responseSchema']['properties']['temperature']['enum'] === ['hot', 'warm', 'cold']
            && $request['generationConfig']['responseSchema']['required'] === ['summary', 'temperature', 'reason', 'next_step', 'suggested_message']);
    }

    public function test_thinking_parts_are_skipped(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->reply(['temperature' => 'cold'], [['text' => 'Let me think…', 'thought' => true]]))]);

        $this->assertSame('cold', $this->gemini()->generate('system', 'prompt')->toStoredArray()['temperature']);
    }

    public function test_busy_blocked_and_broken_answers_become_friendly_errors(): void
    {
        $cases = [
            'busy' => [Http::response(['error' => ['message' => 'Quota exceeded']], 429), 'busy right now'],
            'bad key' => [Http::response(['error' => ['message' => 'API key not valid']], 400), 'could not be reached'],
            'blocked' => [Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]), 'could not analyse'],
            'not json' => [Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Sure! Here you go']]]]]]), 'could not analyse'],
            'missing field' => [Http::response($this->reply(['next_step' => ''])), 'could not analyse'],
        ];
        // One reply per call (none of these are retried).
        $replies = Http::fakeSequence('generativelanguage.googleapis.com/*');
        foreach ($cases as [$response]) {
            $replies->pushResponse($response);
        }

        foreach ($cases as $case => [, $message]) {
            try {
                $this->gemini()->generate('system', 'prompt');
                $this->fail("{$case}: expected an error");
            } catch (AssistantException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $case);
            }
        }
    }

    public function test_the_provider_setting_picks_the_ai_service(): void
    {
        config(['services.ai.provider' => 'gemini', 'services.gemini.api_key' => 'gem-key', 'services.anthropic.api_key' => null]);
        $this->assertInstanceOf(GeminiInsightGenerator::class, app(InsightGenerator::class));
        $this->assertTrue(app(LeadAssistant::class)->isConfigured());
        $this->get('/privacy')->assertSee('Google (Gemini)');

        config(['services.ai.provider' => 'anthropic']);
        $this->assertInstanceOf(ClaudeInsightGenerator::class, app(InsightGenerator::class));
        $this->assertFalse(app(LeadAssistant::class)->isConfigured(), 'only the chosen provider\'s key counts');
        $this->get('/privacy')->assertSee('Anthropic (Claude)');
    }
}
