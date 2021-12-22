<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class HostReservationsControllerTest extends TestCase {

    use LazilyRefreshDatabase;

    /**
     *
     * @test
     */
    public function itListsReservationsThatBelongsToAHost()
    {
        $host = User::factory()->create();

        //Create offices belongs to host
        //...
        $office1 = Office::factory()->for($host)->create();
        $office2 = Office::factory()->for($host)->create();

        Office::factory()->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        //Create reservation that belongs to host offices
        //...
        $reservations = Reservation::factory()->createMany([
            [
                'office_id' => $office1->id,
                'user_id'   => $user1->id,
            ],
            [
                'office_id' => $office1->id,
                'user_id'   => $user2->id,
            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user1->id,
            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user2->id,
            ],
        ]);

        Reservation::factory()->for($user1)->create();
        Reservation::factory()->for($user2)->create();


        $this->actingAs($host);

        $response = $this->getJson('/api/host/reservations');

        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]]);

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());

    }

    /**
     *
     * @test
     */
    public function itListsReservationFilteredByDateRange()
    {
        $host = User::factory()->create();

        //Create offices belongs to host
        //...
        $office1 = Office::factory()->for($host)->create();
        $office2 = Office::factory()->for($host)->create();

        Office::factory()->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        //within the range but for another user
        //...
        $reservations = Reservation::factory()->createMany([
            [
                'office_id'  => $office1->id,
                'user_id'    => $user1->id,
                'start_date' => '2021-03-01',
                'end_date'   => '2021-03-15',
            ],
            [
                'office_id'  => $office1->id,
                'user_id'    => $user2->id,
                'start_date' => '2021-03-25',
                'end_date'   => '2021-04-15',
            ],
            [
                'office_id'  => $office2->id,
                'user_id'    => $user1->id,
                'start_date' => '2021-03-25',
                'end_date'   => '2021-03-29',
            ],
            [
                'office_id'  => $office2->id,
                'user_id'    => $user2->id,
                'start_date' => '2021-03-25',
                'end_date'   => '2021-03-29',
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
        Reservation::factory()->for($office1)->for($user1)->create([
            'start_date' => '2021-02-05',
            'end_date'   => '2021-02-15',
        ]);

        Reservation::factory()->for($office2)->for($user2)->create([
            'start_date' => '2021-04-15',
            'end_date'   => '2021-04-20',
        ]);


        $formDate = '2021-3-3';
        $toDate = '2021-4-4';

        $this->actingAs($host);

        $response = $this->getJson("/api/host/reservations?from_date={$formDate}&to_date={$toDate}");


        $response->assertOk()->assertJsonCount(4, 'data');

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());
    }

    /**
     *
     * @test
     */
    public function itListsReservationFilteredByStatus()
    {
        $host = User::factory()->create();

        //Create offices belongs to host
        //...
        $office1 = Office::factory()->for($host)->create();
        $office2 = Office::factory()->for($host)->create();

        Office::factory()->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        //within the range but for another user
        //...
        $reservations = Reservation::factory()->createMany([
            [
                'office_id' => $office1->id,
                'user_id'   => $user1->id,
                'status'    => Reservation::STATUS_ACTIVE,

            ],
            [
                'office_id' => $office1->id,
                'user_id'   => $user2->id,
                'status'    => Reservation::STATUS_ACTIVE,

            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user1->id,
                'status'    => Reservation::STATUS_ACTIVE,

            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user2->id,
                'status'    => Reservation::STATUS_ACTIVE,

            ],

        ]);


        Reservation::factory()->createMany([
            [
                'office_id' => $office1->id,
                'user_id'   => $user1->id,
                'status'    => Reservation::STATUS_CANCELLED,

            ],
            [
                'office_id' => $office1->id,
                'user_id'   => $user2->id,
                'status'    => Reservation::STATUS_CANCELLED,

            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user1->id,
                'status'    => Reservation::STATUS_CANCELLED,

            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user2->id,
                'status'    => Reservation::STATUS_CANCELLED,

            ],

        ]);

        $this->actingAs($host);

        $response = $this->getJson("/api/host/reservations?status=" . Reservation::STATUS_ACTIVE);

        $response->assertOk()
            ->assertJsonCount(4, 'data');

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());

    }

    /**
     *
     * @test
     */
    public function itListsReservationFilteredByOffice()
    {
        $host = User::factory()->create();

        //Create offices belongs to host
        //...
        $office1 = Office::factory()->for($host)->create();
        $office2 = Office::factory()->for($host)->create();

        Office::factory()->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        //within the range but for another user
        //...
        $reservations = Reservation::factory()->createMany([
            [
                'office_id' => $office1->id,
                'user_id'   => $user1->id,

            ],
            [
                'office_id' => $office1->id,
                'user_id'   => $user2->id,

            ],
        ]);

        Reservation::factory()->createMany([
            [
                'office_id' => $office2->id,
                'user_id'   => $user1->id,

            ],
            [
                'office_id' => $office2->id,
                'user_id'   => $user2->id,

            ],
        ]);

        $this->actingAs($host);

        $response = $this->getJson("/api/host/reservations?office_id=" . $office1->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());

    }
}
