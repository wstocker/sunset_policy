<?php

namespace Drupal\sunset_policy\Cron;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;


/**
 * Drupal AI Chat Interface.
 */
class SunsetFunctions implements SunsetFunctionsInterface
{

    protected $logger;

    public function __construct(LoggerChannelFactoryInterface $logger_factory)
    {
        $this->entityTypeManager = $entityTypeManager;
        $this->logger = $logger_factory->get('sunset_policy');
    }
  
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('logger.factory')
        );
    }

    /**
     * Get expired content.
     *
     * @return array
     *   An array of node ids with expired content.
     */
    public function getExpired()
    {
        $results = $this->queryExpired();
        $node_ids = [];
        if (!empty($results)) {
            foreach ($results as $nid) {
                $node = $this->entityTypeManager->getStorage('node')->load($nid);
                if (!empty($node->field_expiration_date->getValue())) {
                    if ($node->field_last_notified->getValue()[0]['value'] <                  $node->field_expiration_date->getValue()[0]['value'] 
                        || empty($node->field_last_notified->getValue())
                    ) {
                        $node_ids[] = $nid;
                    }
                }
            }
        }
        return $node_ids;
    }
    /**
     * Query expired content.
     *
     * @return array
     *   An array of node ids with expired content.
     */
    public function queryExpired()
    {
        $date = new DrupalDateTime();
        $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

        $query = $this->entityTypeManager->getStorage('node')->getQuery();
        $results = $query
            ->accessCheck(false)
            ->condition('type', ['petition', 'webform'], 'IN')
            ->condition('status', NodeInterface::PUBLISHED)
            ->condition('field_expiration_date', $date, '<=')
            ->execute();

        return $results;
    }

    /**
     * Get expiring content.
     *
     * @return array
     *   An array of node ids with expiring content.
     */
    public function getExpiring()
    {
        $results = $this->queryExpiring();
        $node_ids = [];
        if (!empty($results)) {
            foreach ($results as $nid) {
                $node = $this->entityTypeManager->getStorage('node')->load($nid);
                if (!empty($node->field_expiration_date->getValue())) {
                    $last_notified = new \DateTime($node->field_last_notified->getValue()[0]['value']);
                    $future_last_notified = $last_notified->modify('+2 day');
                    $future_last_notified = $future_last_notified->format('Y-m-d\TH:i:s');
                    if ($future_last_notified < $node->field_expiration_date->getValue()[0]['value']) {
                        $node_ids[] = $nid;
                    }
                }
            }
        }
        return $node_ids;
    }
  
    /**
     * Query expiring content.
     *
     * @return array
     *   An array of node ids with expiring content.
     */
    public function queryExpiring()
    {
        $date = new DrupalDateTime();
        $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
        $current = new \DateTime($date);
        $future = $current->modify('+2 day');
        $future = $future->format('Y-m-d\TH:i:s');

        $query = $this->entityTypeManager->getStorage('node')->getQuery();
        $results = $query
            ->accessCheck(false)
            ->condition('type', ['petition', 'webform'], 'IN')
            ->condition('status', NodeInterface::PUBLISHED)
            ->condition('field_expiration_date', $date, '>=')
            ->condition('field_expiration_date', $future, '<=')
            ->execute();

        return $results;
    }

}
