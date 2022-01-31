<?php

namespace Tests\Stubs;

class ConcreteWithConstructorArgs extends Concrete {
	public $a;
	public $b;

	public function __construct( $a, $b = 'default' ) {
		$this->a = $a;
		$this->b = $b;
	}
}
