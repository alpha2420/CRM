<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->registerOrganization();
    }

    public function test_the_palette_searches_only_leads_the_person_may_see(): void
    {
        $agent = $this->addAgent($this->admin->organization);
        Lead::factory()->for($this->admin->organization)->create(['name' => 'Priya Sharma', 'company' => 'Sunrise', 'assigned_to' => $agent->id]);
        Lead::factory()->for($this->admin->organization)->create(['name' => 'Priyanka Iyer', 'assigned_to' => $this->admin->id]);
        Lead::factory()->for($this->registerOrganization('Other')->organization)->create(['name' => 'Priya Outsider']);

        $this->actingAs($this->admin)->getJson('/search?q=p')->assertExactJson(['leads' => []]);
        $this->getJson('/search?q=priya')->assertOk()->assertJsonCount(2, 'leads')->assertJsonMissing(['name' => 'Priya Outsider']);
        $this->getJson('/search?q=sunrise')->assertJsonPath('leads.0.name', 'Priya Sharma')->assertJsonPath('leads.0.detail', fn (string $detail) => str_contains($detail, 'Sunrise'));

        $this->actingAs($agent)->getJson('/search?q=priya')->assertJsonCount(1, 'leads')->assertJsonPath('leads.0.name', 'Priya Sharma');
    }

    public function test_every_page_has_the_palette_shortcuts_and_theme_choice_for_the_role(): void
    {
        $page = $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('id="palette"', false)
            ->assertSee('Keyboard shortcuts')
            ->assertSee('data-theme-choice="dark"', false)
            ->assertSee('Switch light / dark mode');
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*try \{\s*let theme = localStorage/', $page->getContent(), 'the theme is applied by a script the CSP allows');
        $page->assertSee(route('reports'), false)->assertSee('Import leads from a spreadsheet');

        $agent = $this->addAgent($this->admin->organization);
        $this->actingAs($agent)->get('/dashboard')->assertSee('id="palette"', false)->assertDontSee('Import leads from a spreadsheet')->assertDontSee('>Reports<', false);
    }
}
