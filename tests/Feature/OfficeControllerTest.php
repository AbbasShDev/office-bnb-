<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Tests\TestCase;

class OfficeControllerTest extends TestCase {

    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsOfficesInPaginatedWay()
    {
        Office::factory()->count(3)->create();

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json()['data'][0]['id']);
        $this->assertNotNull($response->json()['meta']);
        $this->assertNotNull($response->json()['links']);
    }

    /**
     * @test
     */
    public function itOnlyListsOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory()->count(3)->create();
        Office::factory()->create([
            'hidden' => true
        ]);
        Office::factory()->create([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

    }

    /**
     * @test
     */
    public function itFilterByUserId()
    {
        Office::factory()->count(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $response = $this->get('/api/offices?user_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json()['data'][0]['id']);

    }

    /**
     * @test
     */
    public function itFilterByVisitorId()
    {
        Office::factory()->count(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get('/api/offices?visitor_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json()['data'][0]['id']);

    }

    /**
     * @test
     */
    public function itIncludesImagesTagsUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.png']);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertIsArray($response->json()['data'][0]['tags']);
        $this->assertCount(1, $response->json()['data'][0]['tags']);
        $this->assertIsArray($response->json()['data'][0]['images']);
        $this->assertCount(1, $response->json()['data'][0]['images']);
        $this->assertEquals($user->id, $response->json()['data'][0]['user']['id']);

    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals(1, $response->json()['data'][0]['reservations_count']);

    }

    /**
     * @test
     */
    public function itOrderByDistanceWhenCoordinatesProvided()
    {

        $office1 = Office::factory()->create([
            'lat'   => '24.833178460583813',
            'lng'   => '39.58478492789992',
            'title' => 'Madinah'
        ]);
        $office2 = Office::factory()->create([
            'lat'   => '21.46627765714812',
            'lng'   => '39.86670085300202',
            'title' => 'Mecca'
        ]);

        $response = $this->get('/api/offices?lat=21.494254054088756&lng=39.19982639014518');

        $response->assertOk();
        $this->assertEquals('Mecca', $response->json()['data'][0]['title']);
        $this->assertEquals('Madinah', $response->json()['data'][1]['title']);


        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals('Madinah', $response->json()['data'][0]['title']);
        $this->assertEquals('Mecca', $response->json()['data'][1]['title']);

    }

    /**
     * @test
     */
    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.png']);
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get('/api/offices/' . $office->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json()['data']['reservations_count']);
        $this->assertIsArray($response->json()['data']['tags']);
        $this->assertCount(1, $response->json()['data']['tags']);
        $this->assertIsArray($response->json()['data']['images']);
        $this->assertCount(1, $response->json()['data']['images']);
        $this->assertEquals($user->id, $response->json()['data']['user']['id']);
    }

    /**
     * @test
     */
    public function itCreateOffice()
    {
        $user = User::factory()->createQuietly();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', [
            'title'            => 'Office in Jeddah',
            'description'      => 'This is description',
            'lat'              => '21.494254054088756',
            'lng'              => '39.19982639014518',
            'address_line1'    => 'Address',
            'price_per_day'    => 10_000,
            'monthly_discount' => 5,
            'tags'             => [$tag1->id, $tag2->id]
        ]);


        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Office in Jeddah');
        $response->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING);
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonCount(2, 'data.tags');
        $this->assertDatabaseHas('offices', [
            'title' => 'Office in Jeddah'
        ]);
    }
}
