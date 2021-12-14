<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserReservationsControllerTest extends TestCase {

    /**
     *
     * @test
     */
    public function itListsReservationsThatBelongsToAUser()
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->for($user)->create();

        $image = $reservation->office->images()->create([
            'path' => 'image.png'
        ]);
        $reservation->office()->update([
            'featured_image_id' => $image->id
        ]);

        Reservation::factory()->count(2)->for($user)->create();
        Reservation::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }
}
