<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApprovel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
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
    public function itListsOfficesTIncludingHiddenAndUnapprovedIfFilteringByCurrentlyLoggedInUser()
    {
        $user = User::factory()->create();

        Office::factory()->count(3)->for($user)->create();
        Office::factory()->for($user)->create([
            'hidden' => true
        ]);
        Office::factory()->for($user)->create([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $this->actingAs($user);

        $response = $this->get('/api/offices?user_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(5, 'data');

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
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

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

        Notification::assertSentTo($admin, OfficePendingApprovel::class);

    }

    /**
     * @test
     */
    public function itDoesNotAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->createQuietly();

        $token = $user->createToken('test', []);

        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ]);


        $response->assertStatus(403);

    }

    /**
     * @test
     */
    public function itUpdateOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $tags = Tag::factory(3)->create();
        $anotherTag = Tag::factory()->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'title' => 'Office updated',
            'tags'  => [$tags[0]->id, $anotherTag->id]
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonPath('data.title', 'Office updated');

    }

    /**
     * @test
     */
    public function itDoesNotUpdateOfficeThatIsNotBelongToUser()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'title' => 'Office updated',
        ]);

        $response->assertStatus(403);

    }

    /**
     * @test
     */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'lat' => 21.494254044088756,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('offices', [
            'id'              => $office->id,
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        Notification::assertSentTo($admin, OfficePendingApprovel::class);
    }

    /**
     * @test
     */
    public function itUpdateTheFeaturedImageOfAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'images.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'featured_image_id' => $image->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.featured_image_id',$image->id);

    }

    /**
     * @test
     */
    public function itDoesNotUpdateTheFeaturedImageThatBelongsToAnotherOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'images.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'featured_image_id' => $image->id,
        ]);

        $response->assertUnprocessable()->assertInvalid('featured_image_id');

    }

    /**
     * @test
     */
    public function itCanDeleteOffice()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertOk();

        $this->assertSoftDeleted($office);

    }

    /**
     * @test
     */
    public function itCanNotDeleteOfficeWithActiveReservations()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertNotSoftDeleted($office);

    }
}
