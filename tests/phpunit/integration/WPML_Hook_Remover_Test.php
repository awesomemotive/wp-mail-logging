<?php

namespace No3x\WPML\Tests\Integration;

use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_Hook_Remover;

class WPML_Hook_Remover_Test extends WPML_IntegrationTestCase {

    private $tag;
    private $callable;

    public function setUp() {
        parent::setUp();
        $this->tag = 'wp_mail';
        $this->callable = [$this, 'callback_function'];
        add_filter( $this->tag, $this->callable );
    }

    public function tearDown() {
        remove_filter( $this->tag, $this->callable );
        parent::tearDown();
    }

    public function callback_function() {}

    public function testRemove() {
        // The result is true if the hook was removed and it was in place before
        $result = (new WPML_Hook_Remover())->remove_class_hook($this->tag, __CLASS__, 'callback_function');
        $this->assertTrue($result);
    }

    public function testNotExistentHook() {
        // The result is true if the hook was removed and it was in place before
        $result = (new WPML_Hook_Remover())->remove_class_hook($this->tag, __CLASS__, 'not_existent');
        $this->assertFalse($result);
    }

}
