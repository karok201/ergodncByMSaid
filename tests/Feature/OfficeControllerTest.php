<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsAllOfficesInPaginatedWay(): void
    {
        Office::factory(3)->create();

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function itOnlyListsOfficesThatAreNotHiddenAndApproved(): void
    {
        Office::factory(3)->create();

        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function itFiltersByHostId():void
    {
        Office::factory(3)->create();

        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get(
            '/api/offices?user_id='.$host->id
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itFiltersByUserId():void
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?visitor_id='.$user->id
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itIncludesImagesTagsAndUser(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices');

        $response->assertOk();

        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    /**
     * @test
     */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        $office1 = Office::factory()->create([
            'lat' => '47.2408955117935',
            'lng' => '38.90320203245686',
            'title' => 'Taganrog'
        ]);

        $office2 = Office::factory()->create([
            'lat' => '44.61749103372212',
            'lng' => '33.505314866001896',
            'title' => 'Sevastopol'
        ]);

        $response = $this->get('/api/offices?lat=38.720661384644046&lng=-9.16044783453807');

        $response->assertOk();
        $this->assertEquals('Sevastopol', $response->json('data')[0]['title']);
        $this->assertEquals('Taganrog', $response->json('data')[1]['title']);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals('Taganrog', $response->json('data')[0]['title']);
        $this->assertEquals('Sevastopol', $response->json('data')[1]['title']);
    }

    /**
     * @test
     */
    public function inShowsTheOffice(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices/'.$office->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    /**
     * @test
     */
    public function itCreatesAnOffice()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->createQuietly();
        $tags = Tag::factory(2)->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', Office::factory()->raw([
            'tags' => $tags->pluck('id')->toArray()
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     */
    public function itDoesntAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->createQuietly();

        $token = $user->createToken('test', []);

        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer '.$token->plainTextToken
        ]);

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function itAllowCreatingIfScopeIsProvided()
    {
        $user = User::factory()->createQuietly();
        $tags = Tag::factory(2)->create();

        $token = $user->createToken('test', ['office.create']);

        $response = $this->postJson('/api/offices',
            Office::factory()->raw([
                'tags' => $tags->pluck('id')->toArray()
            ]),
            [
                'Authorization' => 'Bearer '.$token->plainTextToken
            ]
        );

        $response->assertCreated();
    }

    /**
     * @test
     */
    public function itUpdatesAnOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $anotherTag = Tag::factory()->create();

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Amazing Office',
            'tags' => [$tags[0]->id, $anotherTag->id]
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonPath('data.title', 'Amazing Office');
    }

    /**
     * @test
     */
    public function itDoesntUpdateOfficeThatDoesntBelongToUser()
    {
        $user = User::factory()->createQuietly();
        $anotherUser = User::factory()->createQuietly();
        $office = Office::factory()->for($anotherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Amazing Office'
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'lat' => '47.2408955117935',
            'lng' => '38.90320203245686',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING,
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     */
    public function itCanDeleteOffices()
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertOk();

        $this->assertSoftDeleted($office);
    }

    /**
     * @test
     */
    public function itCannotDeleteAnOfficeThatHasReservations()
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertUnprocessable();

        $this->assertNotSoftDeleted($office);
    }

    /**
     * @test
     */
    public function itListsOfficesIncludingHiddenAndUnApprovedIfFilteringForTheLoggedInUser(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Office::factory(3)->for($user)->create();

        Office::factory()->for($user)->create(['hidden' => true]);
        Office::factory()->for($user)->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices?user_id='.$user->id);

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    /**
     * @test
     */
    public function itUpdatesTheFeaturedImageOfTheOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'featured_image_id' => $image->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.featured_image_id', $image->id);
    }

    /**
     * @test
     */
    public function itDoesntUpdateFeaturedImageThatBelongsToAnotherOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'featured_image_id' => $image->id,
        ]);

        $response->assertUnprocessable()->assertInvalid('featured_image_id');
    }
}
