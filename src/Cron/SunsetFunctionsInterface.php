<?php

namespace Drupal\sunset_policy\Cron;

/**
 * Drupal AI Chat Interface.
 */
interface SunsetFunctionsInterface
{

    /**
     * Get Expired content.
     *
     * @return array
     *   An array of node ids with expired content.
     */
    public function getExpired(): array;

    /**
     * Query Expired content.
     *
     * @return array
     *   An array of node ids with expired content.
     */
    public function queryExpired(): array;

    /**
     * Get Expiring content.
     *
     * @return array
     *   An array of node ids with expiring content.
     */
    public function getExpiring(): array;

    /**
     * Query Expiing content.
     *
     * @return array
     *   An array of node ids with expired content.
     */
    public function queryExpiring(): array;

}
