<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserReservationsControllerTest extends TestCase {

    use LazilyRefreshDatabase;

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

    /**
     *
     * @test
     */
    public function itListsReservationFilteredByDateRange()
    {
        $user = User::factory()->create();

        //within the range but for another user
        //...
        $reservations = Reservation::factory()->for($user)->createMany([
            [
                'start_date' => '2021-03-01',
                'end_date'   => '2021-03-15',
            ],
            [
                'start_date' => '2021-03-25',
                'end_date'   => '2021-04-15',
            ],
            [
                'start_date' => '2021-03-25',
                'end_date'   => '2021-03-29',
            ],
            [
                'start_date' => '2021-03-01',
                'end_date'   => '2021-04-15',
            ],

        ]);

        //within the range but for another user
        //...
        Reservation::factory()->create([
            'start_date' => '2021-03-05',
            'end_date'   => '2021-03-15',
        ]);

        //Outside the range
        //...
        Reservation::factory()->for($user)->create([
            'start_date' => '2021-02-05',
            'end_date'   => '2021-02-15',
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2021-04-15',
            'end_date'   => '2021-04-20',
        ]);


        $formDate = '2021-03-03';
        $toDate = '2021-04-04';

        $this->actingAs($user);

        $response = $this->getJson("/api/reservations?from_date={$formDate}&to_date={$toDate}");


        $response->assertOk()->assertJsonCount(4, 'data');

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());
    }

    /**
     *
     * @test
     */
    public function itListsReservationFilteredByStatus()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE,

        ]);

        Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/api/reservations?status=" . Reservation::STATUS_ACTIVE);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);

    }

    /**
     *
     * @test
     */
    public function itListsReservationFilteredByOffice()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($office)->for($user)->create();

        Reservation::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->getJson("/api/reservations?office_id=" . $office->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);

    }
}
