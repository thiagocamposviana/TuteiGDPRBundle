# TuteiGDPRBundle

Just a bunch of proof of concepts and sample codes for symfony / ez platform related to GDPR.

/tutei/gdpr/youtube_test

How to load youtube-nocookie.com video after user consent.

This is because if the user is logged in on google it will generate cookies even before hitting play.

/tutei/gdpr/url_clean_test

Just some random code to validate query parameters before sending to google analytics.


Proof of concept for a command to delete a user and its contents (eZ Platform):

php bin/console tutei:user-purge $userId $contentTypeOrder --env=dev

Proof of concept for a command to disable a user and hide its contents (eZ Platform):

php bin/console tutei:user-hide $userId --env=dev