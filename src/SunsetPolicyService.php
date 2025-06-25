<?php

namespace Drupal\sunset_policy;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

/**
 * Service for handling sunset policy operations.
 */
class SunsetPolicyService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactoryInterface
   */
  protected $queueFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new SunsetPolicyService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactoryInterface $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactoryInterface $queue_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Process sunset policy cron operations.
   */
  public function processCron() {
    try {
      // Process expiring content.
      $this->processExpiringContent();
      
      // Process expired content.
      $this->processExpiredContent();
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('sunset_policy')
        ->error('Sunset policy queues not created. @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Process expiring content and add to queue.
   */
  protected function processExpiringContent() {
    $expiring_nids = $this->getExpiringNodeIds();
    if (!empty($expiring_nids)) {
      $expiring_queue = $this->queueFactory->get('sunset_expiring_queue');
      foreach ($expiring_nids as $nid) {
        $expiring_queue->createItem(['data' => $nid]);
      }
    }
  }

  /**
   * Process expired content and add to queue.
   */
  protected function processExpiredContent() {
    $expired_nids = $this->getExpiredNodeIds();
    if (!empty($expired_nids)) {
      $expired_queue = $this->queueFactory->get('sunset_expired_queue');
      foreach ($expired_nids as $nid) {
        $expired_queue->createItem(['data' => $nid]);
      }
    }
  }

  /**
   * Get node IDs for content that is expiring soon.
   *
   * @return array
   *   Array of node IDs.
   */
  public function getExpiringNodeIds() {
    $date = $this->getCurrentDateTime();
    $current = new \DateTime($date);
    $future = $current->modify('+2 day');
    $future = $future->format('Y-m-d\TH:i:s');

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $results = $query
      ->accessCheck(FALSE)
      ->condition('type', ['petition', 'webform'], 'IN')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_expiration_date', $date, '>=')
      ->condition('field_expiration_date', $future, '<=')
      ->execute();

    return $this->filterNodesByNotificationCriteria($results, 'expiring');
  }

  /**
   * Get node IDs for content that has expired.
   *
   * @return array
   *   Array of node IDs.
   */
  public function getExpiredNodeIds() {
    $date = $this->getCurrentDateTime();

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $results = $query
      ->accessCheck(FALSE)
      ->condition('type', ['petition', 'webform'], 'IN')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_expiration_date', $date, '<=')
      ->execute();

    return $this->filterNodesByNotificationCriteria($results, 'expired');
  }

  /**
   * Filter nodes based on notification criteria.
   *
   * @param array $node_ids
   *   Array of node IDs to filter.
   * @param string $type
   *   The type of filtering ('expiring' or 'expired').
   *
   * @return array
   *   Filtered array of node IDs.
   */
  protected function filterNodesByNotificationCriteria(array $node_ids, string $type) {
    $filtered_ids = [];
    
    if (empty($node_ids)) {
      return $filtered_ids;
    }

    foreach ($node_ids as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!$node || empty($node->field_expiration_date->getValue())) {
        continue;
      }

      $expiration_date = $node->field_expiration_date->getValue()[0]['value'];
      $last_notified = $node->field_last_notified->getValue();

      if ($type === 'expiring') {
        if ($this->shouldNotifyExpiring($node, $expiration_date, $last_notified)) {
          $filtered_ids[] = $nid;
        }
      }
      elseif ($type === 'expired') {
        if ($this->shouldNotifyExpired($node, $expiration_date, $last_notified)) {
          $filtered_ids[] = $nid;
        }
      }
    }

    return $filtered_ids;
  }

  /**
   * Check if expiring notification should be sent.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param string $expiration_date
   *   The expiration date.
   * @param array $last_notified
   *   The last notified field value.
   *
   * @return bool
   *   TRUE if notification should be sent.
   */
  protected function shouldNotifyExpiring(NodeInterface $node, string $expiration_date, array $last_notified) {
    if (empty($last_notified)) {
      return TRUE;
    }

    $last_notified_date = new \DateTime($last_notified[0]['value']);
    $future_last_notified = $last_notified_date->modify('+2 day');
    $future_last_notified = $future_last_notified->format('Y-m-d\TH:i:s');

    return $future_last_notified < $expiration_date;
  }

  /**
   * Check if expired notification should be sent.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param string $expiration_date
   *   The expiration date.
   * @param array $last_notified
   *   The last notified field value.
   *
   * @return bool
   *   TRUE if notification should be sent.
   */
  protected function shouldNotifyExpired(NodeInterface $node, string $expiration_date, array $last_notified) {
    if (empty($last_notified)) {
      return TRUE;
    }

    return $last_notified[0]['value'] < $expiration_date;
  }

  /**
   * Get current date time in storage format.
   *
   * @return string
   *   Current date time in storage format.
   */
  protected function getCurrentDateTime() {
    $date = new DrupalDateTime();
    $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

} 