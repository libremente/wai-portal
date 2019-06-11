<?php

namespace App\Listeners;

use App\Enums\Logs\EventType;
use App\Events\Website\WebsiteActivated;
use App\Events\Website\WebsiteAdded;
use App\Events\Website\WebsiteArchived;
use App\Events\Website\WebsiteArchiving;
use App\Events\Website\WebsitePurged;
use App\Events\Website\WebsitePurging;
use App\Events\Website\WebsiteUnarchived;
use App\Traits\InteractsWithWebsiteIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;

/**
 * Websites related events subscriber.
 */
class WebsiteEventsSubscriber implements ShouldQueue
{
    use InteractsWithWebsiteIndex;

    /**
     * Website activated event callback.
     *
     * @param WebsiteAdded $event the event
     */
    public function onAdded(WebsiteAdded $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendWebsiteActivatedNotification($website);
//        }
//
//        //Notify Public Administration
//        $publicAdministration->sendWebsiteActivatedNotification($website);

        //Update Redisearch websites index
        $this->updateWebsiteIndex($website);

        logger()->notice(
            'Website ' . $website->getInfo() . ' added of type ' . $website->type->description,
            [
                'event' => EventType::WEBSITE_ADDED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website activated event callback.
     *
     * @param WebsiteActivated $event the event
     */
    public function onActivated(WebsiteActivated $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendWebsiteActivatedNotification($website);
//        }

        logger()->notice(
            'Website ' . $website->getInfo() . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website archiving event callback.
     *
     * @param WebsiteArchiving $event the event
     */
    public function onArchiving(WebsiteArchiving $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        //Notify website administrators
//        $users = $website->getAdministrators($website);
//        foreach ($users as $user) {
//            $user->sendWebsiteArchivingNotification($website, $event->getWebsite());
//        }

        logger()->notice(
            'Website ' . $website->getInfo() . ' reported as not active and scheduled for archiving',
            [
                'event' => EventType::WEBSITE_ARCHIVING,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website archived event callback.
     *
     * @param WebsiteArchived $event the event
     */
    public function onArchived(WebsiteArchived $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        //Notify website administrators
//        $users = $website->getAdministrators($website);
//        foreach ($users as $user) {
//            $user->sendWebsiteArchivedNotification($website);
//        }

        logger()->notice(
            'Website ' . $website->getInfo() . ' archived due to inactivity',
            [
                'event' => EventType::WEBSITE_ARCHIVED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website unarchived event callback.
     *
     * @param WebsiteUnarchived $event the event
     */
    public function onUnarchived(WebsiteUnarchived $event): void
    {
        $website = $event->getWebsite();
        //TODO: notificare qualcuno? è un'azione solo manuale
        logger()->notice(
            'Website ' . $website->getInfo() . ' manually unarchived',
            [
                'event' => EventType::WEBSITE_UNARCHIVED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website near-to-be-purged event callback.
     *
     * @param WebsitePurging $event the event
     */
    public function onPurging(WebsitePurging $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendWebsitePurgingNotification($website);
//        }

        logger()->notice(
            'Website ' . $website->getInfo() . ' scheduled purging',
            [
                'event' => EventType::WEBSITE_PURGING,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website purged event callback.
     *
     * @param WebsitePurged $event the event
     */
    public function onPurged(WebsitePurged $event): void
    {
        $website = json_decode($event->getWebsiteJson());
        $websiteInfo = '"' . $website->name . '" [' . $website->slug . ']';
        //NOTE: toJson: relationship attributes are snake_case
        logger()->notice(
            'Website ' . $websiteInfo . ' purged',
            [
                'event' => EventType::WEBSITE_PURGED,
                'website' => $website->id,
                'pa' => $website->public_administration->ipa_code,
            ]
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param Dispatcher $events the dispatcher
     */
    public function subscribe($events): void
    {
        $events->listen(
            'App\Events\Website\WebsiteAdded',
            'App\Listeners\WebsiteEventsSubscriber@onAdded'
        );
        $events->listen(
            'App\Events\Website\WebsiteActivated',
            'App\Listeners\WebsiteEventsSubscriber@onActivated'
        );
        $events->listen(
            'App\Events\Website\WebsiteArchiving',
            'App\Listeners\WebsiteEventsSubscriber@onArchiving'
        );
        $events->listen(
            'App\Events\Website\WebsiteArchived',
            'App\Listeners\WebsiteEventsSubscriber@onArchived'
        );
        $events->listen(
            'App\Events\Website\WebsiteUnarchived',
            'App\Listeners\WebsiteEventsSubscriber@onUnarchived'
        );
        $events->listen(
            'App\Events\Website\WebsitePurging',
            'App\Listeners\WebsiteEventsSubscriber@onPurging'
        );
        $events->listen(
            'App\Events\Website\WebsitePurged',
            'App\Listeners\WebsiteEventsSubscriber@onPurged'
        );
    }
}
