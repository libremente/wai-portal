<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Jobs\ProcessWebsitesList;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Websites index controller test.
 */
class SearchWebsiteListRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The super-admin user.
     *
     * @var User super-admin user
     */
    private $superAdmin;

    /**
     * The first user.
     *
     * @var User the user
     */
    private $firstUser;

    /**
     * The second user.
     *
     * @var User the user
     */
    private $secondUser;

    /**
     * The first public administration.
     *
     * @var PublicAdministration the public administration
     */
    private $firstPublicAdministration;

    /**
     * The second public administration.
     *
     * @var PublicAdministration the public administration
     */
    private $secondPublicAdministration;

    /**
     * The first website.
     *
     * @var Website the website
     */
    private $firstWebsite;

    /**
     * The second website.
     *
     * @var Website the website
     */
    private $secondWebsite;

    /**
     * Pre-test setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        Bouncer::dontCache();

        $this->superAdmin = factory(User::class)->create([
            'email_verified_at' => Carbon::now(),
        ]);
        Bouncer::scope()->to(0);
        $this->superAdmin->assign(UserRole::SUPER_ADMIN);
        $this->superAdmin->allow(UserPermission::ACCESS_ADMIN_AREA);

        $this->firstUser = factory(User::class)->create([
            'email_verified_at' => Carbon::now(),
        ]);
        $this->secondUser = factory(User::class)->create([
            'email_verified_at' => Carbon::now(),
        ]);
        $this->firstPublicAdministration = factory(PublicAdministration::class)->create();
        $this->secondPublicAdministration = factory(PublicAdministration::class)->create();
        $this->firstUser->publicAdministrations()->sync($this->firstPublicAdministration->id);
        $this->secondUser->publicAdministrations()->sync($this->secondPublicAdministration->id);

        Bouncer::scope()->to($this->firstPublicAdministration->id);
        $this->firstUser->assign(UserRole::ADMIN);
        $this->firstUser->allow(UserPermission::VIEW_LOGS);

        Bouncer::scope()->to($this->secondPublicAdministration->id);
        $this->secondUser->assign(UserRole::ADMIN);
        $this->secondUser->allow(UserPermission::VIEW_LOGS);

        $this->firstWebsite = factory(Website::class)->create([
            'slug' => Str::slug('www.sito1.it'),
            'public_administration_id' => $this->firstPublicAdministration->id,
        ]);

        $this->secondWebsite = factory(Website::class)->create([
            'slug' => Str::slug('www.sito2.it'),
            'public_administration_id' => $this->secondPublicAdministration->id,
        ]);

        (new ProcessWebsitesList())->handle();
    }

    /**
     * Test super-admin user search capabilities.
     */
    public function testSuperAdminSearch(): void
    {
        $response = $this->actingAs($this->superAdmin, 'web')
            ->json(
                'GET',
                route('admin.logs.search-website'),
                ['q' => 'www', 'p' => null]
            );

        $response->assertJsonFragment(
            [
                'id' => (string) $this->firstWebsite->id,
                'pa' => $this->firstPublicAdministration->ipa_code,
                'slug' => $this->firstWebsite->slug,
                'name' => $this->firstWebsite->name,
            ]
        );
        $response->assertJsonFragment(
            [
                'id' => (string) $this->secondWebsite->id,
                'pa' => $this->secondPublicAdministration->ipa_code,
                'slug' => $this->secondWebsite->slug,
                'name' => $this->secondWebsite->name,
            ]
        );
    }

    /**
     * Test first user search capabilities.
     */
    public function testFirstAdminsSearch(): void
    {
        $response = $this->actingAs($this->firstUser, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->firstPublicAdministration->id,
            ])
            ->json(
                'GET',
                route('logs.search-website'),
                ['q' => 'www']
            );

        $response->assertExactJson([
            [
                'id' => (string) $this->firstWebsite->id,
                'pa' => $this->firstPublicAdministration->ipa_code,
                'slug' => $this->firstWebsite->slug,
                'name' => $this->firstWebsite->name,
            ],
        ]);

        $response->assertDontSee(json_encode(
            [
                'id' => (string) $this->secondWebsite->id,
                'pa' => $this->secondPublicAdministration->ipa_code,
                'slug' => $this->secondWebsite->slug,
                'name' => $this->secondWebsite->name,
            ]
        ));
    }

    /**
     * Test second user search capabilities.
     */
    public function testSecondAdminsSearch(): void
    {
        $response = $this->actingAs($this->secondUser, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->secondPublicAdministration->id,
            ])
            ->json(
                'GET',
                route('logs.search-website'),
                ['q' => 'www']
            );

        $response->assertExactJson([
            [
                'id' => (string) $this->secondWebsite->id,
                'pa' => $this->secondPublicAdministration->ipa_code,
                'slug' => $this->secondWebsite->slug,
                'name' => $this->secondWebsite->name,
            ],
        ]);

        $response->assertDontSee(json_encode(
            [
                'id' => (string) $this->firstWebsite->id,
                'pa' => $this->firstPublicAdministration->ipa_code,
                'slug' => $this->firstWebsite->slug,
                'name' => $this->firstWebsite->name,
            ]
        ));
    }

    /**
     * Test super-admin user search capabilities using an I.P.A. code.
     */
    public function testIPACodeFilteringOnSuperAdmin(): void
    {
        $response = $this->actingAs($this->superAdmin, 'web')
            ->json(
                'GET',
                route('admin.logs.search-website'),
                ['q' => 'www', 'p' => $this->firstPublicAdministration->ipa_code]
            );

        $response->assertExactJson([
            [
                'id' => (string) $this->firstWebsite->id,
                'pa' => $this->firstPublicAdministration->ipa_code,
                'slug' => $this->firstWebsite->slug,
                'name' => $this->firstWebsite->name,
            ],
        ]);

        $response->assertDontSee(json_encode(
            [
                'id' => (string) $this->secondWebsite->id,
                'pa' => $this->secondPublicAdministration->ipa_code,
                'slug' => $this->secondWebsite->slug,
                'name' => $this->secondWebsite->name,
            ]
        ));
    }

    /**
     * Test first user search capabilities using an I.P.A. code.
     */
    public function testIPACodeFilteringOnAdmin(): void
    {
        $response = $this->actingAs($this->firstUser, 'web')
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->firstPublicAdministration->id,
            ])
            ->json(
                'GET',
                route('logs.search-website'),
                ['q' => 'www', 'p' => $this->secondPublicAdministration->ipa_code]
            );

        $response->assertExactJson([
            [
                'id' => (string) $this->firstWebsite->id,
                'pa' => $this->firstPublicAdministration->ipa_code,
                'slug' => $this->firstWebsite->slug,
                'name' => $this->firstWebsite->name,
            ],
        ]);

        $response->assertDontSee(json_encode(
            [
                'id' => (string) $this->secondWebsite->id,
                'pa' => $this->secondPublicAdministration->ipa_code,
                'slug' => $this->secondWebsite->slug,
                'name' => $this->secondWebsite->name,
            ]
        ));
    }
}
