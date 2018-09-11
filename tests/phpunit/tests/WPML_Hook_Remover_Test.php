<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Tests\Helper\WPML_UnitTestCase;
use No3x\WPML\WPML_Hook_Remover;

class WPML_Hook_Remover_Test extends WPML_UnitTestCase {

    private $tag;
    private $callable;
    private $callable_regular;

    public function setUp() {
        parent::setUp();
        $this->tag = 'wp_mail';
        $this->callable = [$this, 'callback_function'];
        $this->callable_regular = 'callback_regular_function';
        add_filter( $this->tag, $this->callable );
        add_filter( $this->tag, $this->callable_regular );
    }

    public function tearDown() {
        remove_filter( $this->tag, $this->callable );
        remove_filter( $this->tag, $this->callable_regular );
        parent::tearDown();
    }

    public function callback_function() {}
    public function callback_regular_function() {}

    public function testRemove() {
        // The result is true if the hook was removed and it was in place before
        $result = (new WPML_Hook_Remover())->remove_hook($this->tag, $this->callable);
        $this->assertTrue($result);
    }

    public function testNotExistentHook() {
        // The result is true if the hook was removed and it was in place before
        $result = (new WPML_Hook_Remover())->remove_hook($this->tag, 'not_existent');
        $this->assertFalse($result);
    }

    public function testRemoveRegular() {
        // The result is true if the hook was removed and it was in place before
        $result = (new WPML_Hook_Remover())->remove_hook($this->tag, $this->callable_regular);
        $this->assertTrue($result);
    }
}
