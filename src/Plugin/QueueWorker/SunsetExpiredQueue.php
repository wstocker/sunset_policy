<?php

namespace Drupal\sunset_policy\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Drupal\sunset_policy\SunsetPolicyMailService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Custom Queue Worker.
*
* @QueueWorker(
*   id = "sunset_expired_queue",
*   title = @Translation("Sunset Policy Expired Queue"),
*   cron = {"time" = 60}
* )
*/
final class SunsetExpiredQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
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
   * Constructs a new SunsetExpiredQueue object.
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
   * Processes an item in the queue.
   *
   * @param mixed $data
   *   The queue item data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function processItem($data) {
    /**
     * @var \Drupal\node\NodeInterface $node
     */
    $node = $this->nodeStorage->load($data['data']);
    if ($node instanceof NodeInterface) {
      $this->mailService->sendExpiredNotification($node);
    }
  }

}
