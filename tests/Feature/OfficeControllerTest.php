<?php

namespace Tests\Feature;

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase {

    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsOffices()
    {
        Office::factory()->count(3)->create();

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3,'data');
        $this->assertNotNull($response->json()['data'][0]['id']);
    }
}
