<?php

namespace Drupal\sunset_policy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\webform\WebformInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for handling sunset policy email notifications.
 */
class SunsetPolicyMailService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new SunsetPolicyMailService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    RendererInterface $renderer,
    AccountInterface $current_user,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Send expiring notification for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to send notification for.
   *
   * @return bool
   *   TRUE if notification was sent successfully.
   */
  public function sendExpiringNotification(NodeInterface $node) {
    try {
      $manager_emails = $this->getManagerEmails($node);
      if (empty($manager_emails)) {
        $this->updateLastNotified($node);
        return FALSE;
      }

      $body = $this->buildExpiringMessageBody($node);
      $subject = 'Content will expire soon - ' . $node->getTitle();
      $params = ['subject' => $subject, 'body' => $body];
      $langcode = $this->currentUser->getPreferredLangcode();

      foreach ($manager_emails as $manager_email) {
        $this->mailManager->mail('sunset_policy', 'expiring_mail', $manager_email, $langcode, $params, NULL, TRUE);
        $this->updateLastNotified($node);
      }

      return TRUE;
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('sunset_policy')
        ->error('Unable to send expiring mail for node/@nid: @message', [
          '@nid' => $node->id(),
          '@message' => $e->getMessage(),
        ]);
      return FALSE;
    }
  }

  /**
   * Send expired notification for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to send notification for.
   *
   * @return bool
   *   TRUE if notification was sent successfully.
   */
  public function sendExpiredNotification(NodeInterface $node) {
    try {
      $manager_emails = $this->getManagerEmails($node);
      $langcode = $this->currentUser->getPreferredLangcode();

      if (!empty($manager_emails)) {
        $body = $this->buildExpiredMessageBody($node);
        $subject = 'Content has expired - ' . $node->getTitle();
        $params = ['subject' => $subject, 'body' => $body];

        foreach ($manager_emails as $manager_email) {
          $this->mailManager->mail('sunset_policy', 'expired_mail', $manager_email, $langcode, $params, NULL, TRUE);
        }
      }

      // Always unpublish expired content and close associated webform.
      $this->unpublishExpiredContent($node);
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('sunset_policy')
        ->error('Unable to send expired mail for node/@nid: @message', [
          '@nid' => $node->id(),
          '@message' => $e->getMessage(),
        ]);
      return FALSE;
    }
  }

  /**
   * Get manager emails for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get manager emails for.
   *
   * @return array
   *   Array of manager email addresses.
   */
  protected function getManagerEmails(NodeInterface $node) {
    $manager_emails = [];
    $manager_terms = $node->get('field_manager_multiref')->referencedEntities();

    foreach ($manager_terms as $manager_term) {
      if ($manager_term->get('field_active_manager')->getValue()[0]['value']) {
        $manager_emails[] = $manager_term->get('field_email')->getValue()[0]['value'];
      }
    }

    return $manager_emails;
  }

  /**
   * Build expiring message body.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to build message for.
   *
   * @return string
   *   The rendered message body.
   */
  protected function buildExpiringMessageBody(NodeInterface $node) {
    $host = $this->requestStack->getCurrentRequest()->getHost();
    $body = [
      'message' => [
        'expiring' => date('l M jS Y', strtotime($node->field_expiration_date->getValue()[0]['value'])),
        'alias' => $host . $node->toUrl()->toString(),
        'url' => $host . '/node/' . $node->id(),
        'title' => $node->getTitle(),
      ],
    ];

    $body_data = [
      '#theme' => 'expiring_mail',
      '#message' => $body['message'],
    ];

    return (string) $this->renderer->render($body_data);
  }

  /**
   * Build expired message body.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to build message for.
   *
   * @return string
   *   The rendered message body.
   */
  protected function buildExpiredMessageBody(NodeInterface $node) {
    $host = $this->requestStack->getCurrentRequest()->getHost();
    $body = [
      'message' => [
        'alias' => $host . $node->toUrl()->toString(),
        'url' => $host . '/node/' . $node->id(),
        'title' => $node->getTitle(),
      ],
    ];

    $body_data = [
      '#theme' => 'expired_mail',
      '#message' => $body['message'],
    ];

    return (string) $this->renderer->render($body_data);
  }

  /**
   * Update the last notified field for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to update.
   */
  protected function updateLastNotified(NodeInterface $node) {
    $node->set('field_last_notified', date('Y-m-d\TH:i:s', time()));
    $node->save();
  }

  /**
   * Unpublish expired content and close associated webform.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to unpublish.
   */
  protected function unpublishExpiredContent(NodeInterface $node) {
    $node->set('field_last_notified', date('Y-m-d\TH:i:s', time()));
    $node->setUnpublished();
    $node->save();

    // Close associated webform if it exists.
    if (!empty($node->get('webform'))) {
      $webform_id = $node->get('webform')->getValue()[0]['target_id'] ?? NULL;
      if ($webform_id) {
        $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
        if ($webform instanceof WebformInterface) {
          $webform->setStatus(WebformInterface::STATUS_CLOSED);
          $webform->save();
        }
      }
    }
  }

} 