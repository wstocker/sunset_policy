## INTRODUCTION

The Sunset Policy module is designed to manage the expiration and notification processes 
for content on Drupal sites, specifically targeting petitions and questionnaire nodes. 
It facilitates automatic queueing of notifications for content approaching its expiration 
date or already expired, enhancing content management workflows.

### HOW IT WORKS


* Expiring Content Notification: Automatically identifies nodes (petitions,
  questionnaires) that are nearing their expiration date and queues them 
  for notification.
* Expired Content Handling: Identifies expired nodes and queues them for 
  appropriate actions, such as archival or deletion.
* When `location_email` is enabled on the source site, the email addresses
  stored for a location will be migrated into a new `email` field with `_email`
  field name suffix. The `email` field type is available in Drupal 8/9 by
  default.
* Customizable Email Notifications: Supports customized email notifications 
   for both expiring and expired content through Drupal's hook_mail system.


## REQUIREMENTS

* Drupal Core
* Node, DateTime, and other core modules for basic operations.

## THEME_HOOKS

* expiring_mail: Theme hook for rendering emails for expiring content.
* expired_mail: Theme hook for rendering emails for expired content.
