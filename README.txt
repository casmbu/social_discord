CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * How it works
 * Support requests
 * Maintainers

INTRODUCTION
------------

Social Discord is a Discord authentication plus functionality integration for
Drupal. It is based on the Social Auth and Social API projects.

It adds to the site:
 * A new url: /user/login/discord.
 * A settings form on /admin/config/social-api/social-auth/discord page.
 * A Discord Logo in the Social Auth Login block.

REQUIREMENTS
------------

This module requires the following modules:

 * Social Auth (https://drupal.org/project/social_auth)
 * Social API (https://drupal.org/project/social_api)

INSTALLATION
------------

 * Install the dependencies: Social API and Social Auth.

 * Install as you would normally install a contributed Drupal module. See:
   https://drupal.org/documentation/install/modules-themes/modules-8
   for further information.

CONFIGURATION
-------------

 * Add your Discord project OAuth information in
   Configuration » User Authentication » Discord.

 * Place a Social Auth Login block in Structure » Block Layout.

 * If you already have a Social Auth Login block in the site, rebuild the cache.

 * Create a link to /user/login/discord.


HOW IT WORKS
------------

User can click on the Discord logo on the Social Auth Login block
You can also add a button or link anywhere on the site that points
to /user/login/discord, so theming and customizing the button or link
is very flexible.

When the user opens the /user/login/discord link, it automatically takes
user to Discord Accounts for authentication. Discord then returns the user to
Drupal site. If we have an existing Drupal user with the same email address
provided by Discord, that user is logged in. Otherwise a new Drupal user is
created.

SUPPORT REQUESTS
----------------

Before posting a support request, carefully read the installation
instructions provided in module documentation page.

Before posting a support request, check Recent log entries at
admin/reports/dblog

Once you have done this, you can post a support request at module issue queue:
https://github.com/casmbu/social_discord/issues

When posting a support request, please inform if you were able to see any errors
in Recent log entries.

MAINTAINERS
-----------

Current maintainers:
 * Tyson Gray (casmbu) - https://github.com/casmbu
