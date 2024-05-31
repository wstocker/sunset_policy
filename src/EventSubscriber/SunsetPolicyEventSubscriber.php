<?php

namespace Drupal\sunset_policy\EventSubscriber;

use Drupal\sunset_policy\Event\NysSunsetPolicyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

class SunsetPolicyEventSubscriber implements EventSubscriberInterface {

  protected $queueFactory;

  public function __construct(QueueFactory $queueFactory) {
    $this->queueFactory = $queueFactory;
  }

  public static function getSubscribedEvents() {
    return [
      'nys_sunset_policy.event' => 'onSunsetPolicyEvent',
    ];
  }

  public function onSunsetPolicyEvent(NysSunsetPolicyEvent $event) {
    $expiringNids = $event->getExpiringNids();
    $expiredNids = $event->getExpiredNids();

    $expiringQueue = $this->queueFactory->get('sunset_expiring_queue');
    foreach ($expiringNids as $nid) {
      $expiringQueue->createItem(['data' => $nid]);
      $this->logger->notice('Node added to expiring queue. Nid: @nid', ['@nid' => $nid]);
    }

    $expiredQueue = $this->queueFactory->get('sunset_expired_queue');
    foreach ($expiredNids as $nid) {
      $expiredQueue->createItem(['data' => $nid]);
      $this->logger->notice('Node added to expired queue. Nid: @nid', ['@nid' => $nid]);
    }
  }

}
