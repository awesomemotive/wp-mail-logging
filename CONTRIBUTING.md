#Contribute To wp-mail-logging

Community made patches, localisations, bug reports and contributions are always welcome and are crucial to ensure the quality of wp-mail-logging.

When contributing please ensure you follow the guidelines below.

## Environment
All dependencies are managed via [composer](http://getcomposer.org).
To install the plugin with development dependencies run:

```composer install```

To get a working plugin installation run:

```composer install --no-dev --no-scripts --prefer-dist``` (perfect for making a release by zipping the resulting environment)

To execute unit tests use the phpunit version downloaded by composer:

```
cd /srv/www/wordpress-develop/public_html/src/wp-content/plugins/wp-mail-logging
vendor/phpunit/phpunit/phpunit
```
If you use VVV make sure to be in the `/srv` directory instead of `/vagrant/www`. Otherwise there are errors like:
```
PHP Fatal error:  Cannot redeclare composerRequire553f4f88fd2d0873cede8d164ece3593() (previously declared in /vagrant/www/wordpress-develop/public_html/src/wp-content/plugins/wp-mail-logging/vendor/composer/autoload_real.php:63) in /srv/www/wordpress-develop/public_html/src/wp-content/plugins/wp-mail-logging/vendor/composer/autoload_real.php on line 70
```
## IDE

Please insure to be conform with the development guidelines.
* use tabs and not spaces. The tab indent size should be 4 for all wp-mail-logging code.

## Getting Started

* Submit a ticket for your issue, assuming one does not already exist or fix an issue
  * Raise it on our [Issue Tracker](https://github.com/kgjerstad/wp-mail-logging/issues)
  * Clearly describe the issue including steps to reproduce the bug.
  * Make sure you fill in the earliest version that you know has the issue as well as the version of WordPress you're using.

## Making Changes

* Fork the repository on GitHub.
* Create a new branch like 'feature1'.
* Make the changes to your branch.
  * Ensure you stick to the [WordPress Coding Standards](http://codex.wordpress.org/WordPress_Coding_Standards).
  * Always leave the code cleaner than you found it. (The Boy Scouts Rule)
* Reference your issue (if present) and include a note about the fix in the commit message.
* If possible, and if applicable, please also add/update unit tests for your changes.
* Finally submit a pull request to the 'master' branch of the wp-mail-logging repository.

## Code Documentation

* We ensure that every wp-mail-logging function is documented well and follows the standards set by phpDoc.
* Please make sure that every function is documented so that when we update our API Documentation things don't go awry!
	* If you're adding/editing a function in a class, make sure to add `@access {private|public|protected}`.

At this point you're waiting on us to merge your pull request. We'll review all pull requests, and make suggestions and changes if necessary.

# Additional Resources
* [General GitHub Documentation](http://help.github.com/)
* [GitHub Pull Request documentation](http://help.github.com/send-pull-requests/)
* [PHPUnit Tests Guide](http://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)
