<?php

namespace Drupal\sunset_policy\EventSubscriber;

use Drupal\Core\Cron\Event\CronEvent;
use Drupal\sunset_policy\SunsetPolicyService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for sunset policy cron operations.
 */
class SunsetPolicyCronSubscriber implements EventSubscriberInterface {

  /**
   * The sunset policy service.
   *
   * @var \Drupal\sunset_policy\SunsetPolicyService
   */
  protected $sunsetPolicyService;

  /**
   * Constructs a new SunsetPolicyCronSubscriber object.
   *
   * @param \Drupal\sunset_policy\SunsetPolicyService $sunset_policy_service
   *   The sunset policy service.
   */
  public function __construct(SunsetPolicyService $sunset_policy_service) {
    $this->sunsetPolicyService = $sunset_policy_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CronEvent::class => 'onCron',
    ];
  }

  /**
   * Process sunset policy operations on cron.
   *
   * @param \Drupal\Core\Cron\Event\CronEvent $event
   *   The cron event.
   */
  public function onCron(CronEvent $event) {
    $this->sunsetPolicyService->processCron();
  }

} 