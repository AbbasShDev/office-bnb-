<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase {

    use RefreshDatabase;

    /**
     * @test
     */
    public function itUploadsAnImageAndStoreItUnderOffice()
    {

        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/image", [
            'image' => UploadedFile::fake()->image('image.png')
        ]);

        $response->assertCreated();

        Storage::assertExists([
            $response->json('data.path')
        ]);
    }

    /**
     * @test
     */
    public function itDeleteAnImage()
    {
        Storage::put('/office_image.jpg', 'empty');
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->images()->create([
            'path' => 'image.jpg'
        ]);
        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);
        $this->actingAs($user);

        $response = $this->delete("/api/offices/{$office->id}/image/{$image->id}");

        $response->assertok();
        $this->assertModelMissing($image);
        Storage::assertMissing('/office_image.jpg');
    }

    /**
     * @test
     */
    public function itDoesNotDeleteTheOnlyImage()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);
        $this->actingAs($user);

        $response = $this->delete("/api/offices/{$office->id}/image/{$image->id}");

        $response->assertStatus(302);

    }

    /**
     * @test
     */
    public function itDoesNotDeleteTheFeaturedImage()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->delete("/api/offices/{$office->id}/image/{$image->id}");

        $response->assertStatus(302);

    }

    /**
     * @test
     */
    public function itDoesNotDeleteAnImageThatBelongsToAnotherOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->delete("/api/offices/{$office->id}/image/{$image->id}");

        $response->assertStatus(302);

    }
}
