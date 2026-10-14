<?php

namespace Tests\Feature;

use App\Models\Dealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_lookup_dealer_by_id_realme(): void
    {
        $dealer = Dealer::first();
        if (! $dealer) {
            $dealer = Dealer::create([
                'id_realme' => 'TEST_ID_001',
                'dealer_name' => 'Toko Test Realme',
                'region' => 'BIG BANDUNG',
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/dealers/lookup?id_realme='.$dealer->id_realme);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'found' => true,
            'data' => [
                'id_realme' => $dealer->id_realme,
                'dealer_name' => $dealer->dealer_name,
            ],
        ]);
    }

    public function test_lookup_dealer_not_found(): void
    {
        $response = $this->getJson('/api/dealers/lookup?id_realme=NON_EXISTENT_99999');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'found' => false,
        ]);
    }

    public function test_can_search_dealer_suggestions(): void
    {
        $dealer = Dealer::firstOrCreate(
            ['id_realme' => 'TEST_ID_002'],
            ['dealer_name' => 'Dealer Bintang Komunika', 'region' => 'BIG BANDUNG', 'is_active' => true]
        );
        $query = substr($dealer->id_realme, 0, 4);

        $response = $this->getJson('/api/dealers/suggestions?query='.$query);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    public function test_can_list_customers(): void
    {
        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'stats',
        ]);
    }

    public function test_can_get_customer_options(): void
    {
        $response = $this->getJson('/api/customers/options');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }
}
