<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itUploadsAnImageAndStoresItUnderTheOffice(): void
    {
        Storage::fake('public');

        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->createQuietly();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::disk('public')->assertExists(
            $response->json('data.path')
        );
    }

    /**
     * @test
     */
    public function itDeletesAnImage(): void
    {
        Storage::disk('public')->put('/office_image.jpg', 'empty');

        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->createQuietly();

        $office->images()->create([
            'path' => 'image.jpg',
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg',
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertOk();

        $this->assertModelMissing($image);

        Storage::disk('public')->assertMissing('office_image.jpg');
    }

    /**
     * @test
     */
    public function itDoesntDeleteTheOnlyImage(): void
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->createQuietly();

        $image = $office->images()->create([
            'path' => 'office_image.jpg',
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'Cannot delete the only image']);
    }

    /**
     * @test
     */
    public function itDoesntDeleteTheFeaturedImage(): void
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->createQuietly();

        $office->images()->create([
            'path' => 'image.jpg',
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg',
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'Cannot delete the featured image']);
    }

    /**
     * @test
     */
    public function itDoesntDeleteTheImageThatBelongsToAnotherResource(): void
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->createQuietly();
        $office2 = Office::factory()->for($user)->createQuietly();


        $image = $office2->images()->create([
            'path' => 'office_image.jpg',
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'Cannot delete this image']);
    }
}
