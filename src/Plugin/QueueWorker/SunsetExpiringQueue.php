<?php

namespace Drupal\sunset_policy\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Drupal\sunset_policy\SunsetPolicyMailService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sunset Policy Expiring Queue Worker.
 *
 * @QueueWorker(
 *   id = "sunset_expiring_queue",
 *   title = @Translation("Sunset Policy Expiring Queue"),
 *   cron = {"time" = 60}
 * )
 */
final class SunsetExpiringQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The mail service.
   *
   * @var \Drupal\sunset_policy\SunsetPolicyMailService
   */
  protected $mailService;

  /**
   * Constructs a new SunsetExpiringQueue object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\sunset_policy\SunsetPolicyMailService $mail_service
   *   The mail service.
   */
  public function __construct(
    EntityStorageInterface $node_storage,
    SunsetPolicyMailService $mail_service
  ) {
    $this->nodeStorage = $node_storage;
    $this->mailService = $mail_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('sunset_policy.mail_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $node = $this->nodeStorage->load($data['data']);
    if ($node instanceof NodeInterface) {
      $this->mailService->sendExpiringNotification($node);
    }
  }

}
