# Sunset Policy Module

## INTRODUCTION

The Sunset Policy module is designed to manage the expiration and notification processes 
for content on Drupal sites, specifically targeting petitions and questionnaire nodes. 
It facilitates automatic queueing of notifications for content approaching its expiration 
date or already expired, enhancing content management workflows.

### HOW IT WORKS

* **Expiring Content Notification**: Automatically identifies nodes (petitions,
  questionnaires) that are nearing their expiration date and queues them 
  for notification.
* **Expired Content Handling**: Identifies expired nodes and queues them for 
  appropriate actions, such as archival or deletion.
* **Event-Driven Architecture**: Uses Drupal's event system to handle cron operations
  through event subscribers for better separation of concerns.
* **Service-Oriented Design**: Implements object-oriented services for business logic,
  reducing code duplication and improving maintainability.
* **Customizable Email Notifications**: Supports customized email notifications 
   for both expiring and expired content through Drupal's hook_mail system.

## ARCHITECTURE

### Services

The module uses several services to handle different aspects of the sunset policy:

1. **SunsetPolicyService** (`sunset_policy.service`): Handles the core business logic for:
   - Processing cron operations
   - Querying for expiring and expired content
   - Adding items to processing queues
   - Filtering nodes based on notification criteria

2. **SunsetPolicyMailService** (`sunset_policy.mail_service`): Manages email notifications:
   - Sending expiring notifications
   - Sending expired notifications
   - Building email message bodies
   - Managing manager email addresses
   - Handling content unpublishing and webform closure

### Event Subscribers

- **SunsetPolicyCronSubscriber**: Listens to Drupal's cron events and triggers
  the sunset policy processing through the service layer.

### Queue Workers

- **SunsetExpiringQueue**: Processes items in the expiring queue, sending
  notifications for content that will expire soon.
- **SunsetExpiredQueue**: Processes items in the expired queue, sending
  notifications and unpublishing expired content.

## REQUIREMENTS

* Drupal Core 9/10
* Node, DateTime, and other core modules for basic operations
* Queue UI module for queue management interface

## DEPENDENCIES

The module has the following service dependencies:
- `entity_type.manager`: For entity operations
- `queue`: For queue management
- `logger.factory`: For logging
- `plugin.manager.mail`: For email sending
- `renderer`: For template rendering
- `current_user`: For user context
- `request_stack`: For request information

## THEME_HOOKS

* `expiring_mail`: Theme hook for rendering emails for expiring content.
* `expired_mail`: Theme hook for rendering emails for expired content.

## CONFIGURATION

The module automatically processes content on cron runs. No additional configuration
is required, but the following fields must exist on the target content types:

- `field_expiration_date`: DateTime field for content expiration
- `field_last_notified`: DateTime field to track last notification
- `field_manager_multiref`: Reference field to manager terms
- `field_active_manager`: Boolean field on manager terms
- `field_email`: Email field on manager terms

## DEVELOPMENT

### Adding New Content Types

To add support for new content types, modify the `getExpiringNodeIds()` and
`getExpiredNodeIds()` methods in `SunsetPolicyService` to include the new
content type in the query conditions.

### Customizing Email Templates

Email templates can be customized by overriding the theme hooks:
- `expiring_mail`: For expiring content notifications
- `expired_mail`: For expired content notifications
