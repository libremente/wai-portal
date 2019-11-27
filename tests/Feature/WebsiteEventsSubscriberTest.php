<?php

namespace Tests\Feature;

use App\Enums\Logs\EventType;
use App\Enums\WebsiteStatus;
use App\Events\Website\PrimaryWebsiteNotTracking;
use App\Events\Website\WebsiteActivated;
use App\Events\Website\WebsiteAdded;
use App\Events\Website\WebsiteArchived;
use App\Events\Website\WebsiteArchiving;
use App\Events\Website\WebsiteDeleted;
use App\Events\Website\WebsitePurged;
use App\Events\Website\WebsitePurging;
use App\Events\Website\WebsiteRestored;
use App\Events\Website\WebsiteStatusChanged;
use App\Events\Website\WebsiteUnarchived;
use App\Events\Website\WebsiteUpdated;
use App\Events\Website\WebsiteUrlChanged;
use App\Models\PublicAdministration;
use App\Models\Website;
use App\Traits\InteractsWithRedisIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteEventsSubscriberTest extends TestCase
{
    use RefreshDatabase;

    private $publicAdministration;

    private $website;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicAdministration = factory(PublicAdministration::class)->create();
        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);
    }

    public function testWebsiteAdded(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' added of type ' . $this->website->type->description,
                [
                    'event' => EventType::WEBSITE_ADDED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteAdded($this->website));
    }

    public function testWebsiteActivated(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' activated',
                [
                    'event' => EventType::WEBSITE_ACTIVATED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteActivated($this->website));
    }

    public function testWebsiteUpdated(): void
    {
        $this->partialMock(InteractsWithRedisIndex::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('updateWebsitesIndex')
            ->with($this->website);

        event(new WebsiteUpdated($this->website));
    }

    public function testWebsiteStatusChanged(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' status changed from "' . WebsiteStatus::getDescription(WebsiteStatus::ARCHIVED) . '" to "' . $this->website->status->description . '"',
                [
                    'event' => EventType::WEBSITE_STATUS_CHANGED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteStatusChanged($this->website, WebsiteStatus::ARCHIVED));
    }

    public function testWebsiteUrlChanged(): void
    {
        $oldUrl = 'https://oldfakeurl.local';
        $this->expectLogMessage(
            'notice',
            [
                'Website' . $this->website->info . ' URL updated from ' . e($oldUrl) . ' to ' . e($this->website->url),
                [
                    'event' => EventType::WEBSITE_URL_CHANGED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteUrlChanged($this->website, $oldUrl));
    }

    public function testWebsiteArchiving(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' reported as not active and scheduled for archiving',
                [
                    'event' => EventType::WEBSITE_ARCHIVING,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteArchiving($this->website, 10));
    }

    public function testWebsiteArchivedManually(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' archived manually',
                [
                    'event' => EventType::WEBSITE_ARCHIVED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteArchived($this->website, true));
    }

    public function testWebsiteArchivedForInactivity(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' archived due to inactivity',
                [
                    'event' => EventType::WEBSITE_ARCHIVED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteArchived($this->website, false));
    }

    public function testWebsiteUnarchived(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' unarchived manually',
                [
                    'event' => EventType::WEBSITE_UNARCHIVED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteUnarchived($this->website));
    }

    public function testWebsitePurging(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' scheduled purging',
                [
                    'event' => EventType::WEBSITE_PURGING,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsitePurging($this->website));
    }

    public function testWebsitePurged(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website "' . e($this->website->name) . '" [' . $this->website->slug . '] purged',
                [
                    'event' => EventType::WEBSITE_PURGED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsitePurged($this->website->toJson()));
    }

    public function testWebsiteDeleted(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' deleted.',
                [
                    'event' => EventType::WEBSITE_DELETED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteDeleted($this->website));
    }

    public function testWebsiteRestored(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Website ' . $this->website->info . ' restored.',
                [
                    'event' => EventType::WEBSITE_RESTORED,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new WebsiteRestored($this->website));
    }

    public function testPrimaryWebsiteInactive(): void
    {
        $this->expectLogMessage(
            'notice',
            [
                'Primary website ' . $this->website->info . ' tracking inactive.',
                [
                    'event' => EventType::PRIMARY_WEBSITE_NOT_TRACKING,
                    'website' => $this->website->id,
                    'pa' => $this->website->publicAdministration->ipa_code,
                ],
            ]
        );

        event(new PrimaryWebsiteNotTracking($this->website));
    }
}
