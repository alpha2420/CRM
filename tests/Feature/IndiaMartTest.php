<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IndiaMartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-03-12 11:00', 'Asia/Kolkata'));
        $this->admin = $this->registerOrganization('Sunrise Sensors');
    }

    /** One enquiry as IndiaMART's API returns it. */
    private function enquiry(string $id, string $mobile, string $name = 'Mamilla Ganga Shekhar'): array
    {
        return [
            'UNIQUE_QUERY_ID' => $id, 'QUERY_TYPE' => 'W', 'QUERY_TIME' => '2030-03-12 10:56:07',
            'SENDER_NAME' => $name, 'SENDER_MOBILE' => $mobile, 'SENDER_EMAIL' => 'ganga@example.com',
            'SENDER_COMPANY' => 'VS Automation', 'SENDER_ADDRESS' => 'KTR Colony, Hyderabad', 'SENDER_CITY' => 'Hyderabad',
            'SENDER_STATE' => 'Telangana', 'SENDER_COUNTRY_ISO' => 'IN', 'SENDER_MOBILE_ALT' => '', 'SENDER_PHONE' => '',
            'QUERY_PRODUCT_NAME' => 'Capacitive Proximity Sensors',
            'QUERY_MESSAGE' => 'I want to buy Capacitive Proximity Sensors.<br> Quantity :   6<br> Probable Order Value :   Rs. 3,000 to 10,000<br>',
            'CALL_DURATION' => '', 'RECEIVER_MOBILE' => '',
        ];
    }

    private function reply(array $enquiries): array
    {
        return ['CODE' => 200, 'STATUS' => 'SUCCESS', 'MESSAGE' => '', 'TOTAL_RECORDS' => count($enquiries), 'RESPONSE' => $enquiries];
    }

    private function connect(): Integration
    {
        $this->actingAs($this->admin)->put('/settings/integrations/indiamart', ['crm_key' => 'gIhfk7cD+sb7kev'])->assertRedirect('/settings/integrations/indiamart');

        return Integration::query()->where('type', 'indiamart')->sole();
    }

    public function test_new_enquiries_become_leads_with_the_product_they_asked_about(): void
    {
        Http::fakeSequence('mapi.indiamart.com/*')->push($this->reply([$this->enquiry('2243945858', '+91-9100843314')]));
        $agent = $this->addAgent($this->admin->organization);
        $this->connect();
        $this->get('/settings/integrations/indiamart')->assertSee('Waiting for the first check');

        $this->artisan('crm:indiamart')->expectsOutput('Added 1 IndiaMART leads.');

        $lead = Lead::sole();
        $this->assertSame(['Mamilla Ganga Shekhar', '+919100843314', 'ganga@example.com', 'VS Automation', 'Hyderabad'], [$lead->name, $lead->phone, $lead->email, $lead->company, $lead->city]);
        $this->assertSame('IndiaMART', $lead->source->name);
        $this->assertSame('IndiaMART: Capacitive Proximity Sensors', $lead->campaign);
        $this->assertSame("Product: Capacitive Proximity Sensors\nI want to buy Capacitive Proximity Sensors.\n Quantity :   6\n Probable Order Value :   Rs. 3,000 to 10,000\nType: Direct enquiry · IndiaMART #2243945858", $lead->notes);
        $this->assertSame($agent->id, $lead->assigned_to, 'shared out like any new lead');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://mapi.indiamart.com/wservce/crm/crmListing/v2/')
            && $request['glusr_crm_key'] === 'gIhfk7cD+sb7kev'
            && $request['start_time'] === '11-Mar-203011:00:00' // the first check looks back one day, in IST
            && $request['end_time'] === '12-Mar-203011:00:00');
        $this->get('/settings/integrations/indiamart')->assertSee('Working: checked')->assertSee('1 new enquiry last time');
    }

    public function test_it_asks_at_most_every_5_minutes_with_overlap_and_never_adds_an_enquiry_twice(): void
    {
        $first = $this->enquiry('111', '+91-9100000001', 'Asha');
        Http::fakeSequence('mapi.indiamart.com/*')
            ->push($this->reply([$first]))
            ->push($this->reply([$first, $this->enquiry('222', '+91-9100000001', 'Asha'), $this->enquiry('333', '+91-9100000002', 'Ravi')]));
        $this->connect();

        $this->artisan('crm:indiamart')->expectsOutput('Added 1 IndiaMART leads.');
        $this->travel(2)->minutes();
        $this->artisan('crm:indiamart')->expectsOutput('Added 0 IndiaMART leads.');
        Http::assertSentCount(1);

        $this->travel(3)->minutes();
        $this->artisan('crm:indiamart')->expectsOutput('Added 1 IndiaMART leads.');
        Http::assertSent(fn (Request $request) => $request['start_time'] === '12-Mar-203010:55:00', 'five minutes of overlap');

        $this->assertSame(2, Lead::count(), 'enquiry 111 came twice but was added once');
        $this->assertStringContainsString('New enquiry via IndiaMART', Lead::query()->where('name', 'Asha')->sole()->activities()->sole()->note, 'a second enquiry from the same number joins the lead');
    }

    public function test_errors_are_shown_and_no_leads_is_not_an_error(): void
    {
        Http::fakeSequence('mapi.indiamart.com/*')
            ->push(['CODE' => 204, 'STATUS' => 'SUCCESS', 'MESSAGE' => 'There are no leads in the given time duration. Please try for a different duration.', 'TOTAL_RECORDS' => 0, 'RESPONSE' => []])
            ->push(['CODE' => 401, 'STATUS' => 'FAILURE', 'MESSAGE' => 'Invalid CRM key.']);
        $this->connect();

        $this->post('/settings/integrations/indiamart/test')->assertSessionHas('status', 'Connection works: 0 new enquiries added.');
        $this->post('/settings/integrations/indiamart/test')->assertSessionHasErrors(['connection' => 'IndiaMART allows one check every 5 minutes. New enquiries are fetched by themselves; try again in a few minutes.']);

        $this->travel(6)->minutes();
        $this->post('/settings/integrations/indiamart/test')->assertSessionHasErrors(['connection' => 'IndiaMART said: Invalid CRM key.']);
        $this->get('/settings/integrations/indiamart')->assertSee('IndiaMART said: Invalid CRM key.');
    }

    public function test_the_key_stays_secret_and_each_workspace_gets_its_own_copy_of_a_shared_buy_lead(): void
    {
        Http::fake(['mapi.indiamart.com/*' => Http::response($this->reply([$this->enquiry('999', '+91-9100000009')]))]);
        $this->connect();
        $this->get('/settings/integrations/indiamart')->assertDontSee('gIhfk7cD');
        $this->put('/settings/integrations/indiamart', ['crm_key' => '']);
        $this->assertSame('gIhfk7cD+sb7kev', Integration::query()->where('type', 'indiamart')->sole()->setting('crm_key'), 'a blank field keeps the key');

        $other = $this->registerOrganization('Other Traders');
        $this->actingAs($other)->put('/settings/integrations/indiamart', ['crm_key' => 'other-key']);

        $this->artisan('crm:indiamart')->expectsOutput('Added 2 IndiaMART leads.');
        $this->assertSame(2, Lead::withoutGlobalScopes()->count());
    }
}
