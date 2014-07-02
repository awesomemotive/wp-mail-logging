#! /bin/sh

# Simpler test script for running tests iteratively.
# You must have executd first install-wp-tests.

export WP_TESTS_DIR=/tmp/wordpress-testing
export WP_DIR=/tmp/wordpress
SCRIPTS_DIR=`dirname $0`

bash $SCRIPTS_DIR/install-wp-tests.sh wordpress_test wp 'wp' localhost