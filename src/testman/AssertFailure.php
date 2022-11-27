<?php
namespace testman;

class AssertFailure extends \Exception{
	private $expectation;
	private $result;
	private bool $has = false;

	public function ab($expectation, $result): self{
		$this->expectation = $expectation;
		$this->result = $result;
		$this->has = true;
		return $this;
	}

	public function has(): bool{
		return $this->has;
	}
	public function expectation(){
		return $this->expectation;
	}
	public function result(){
		return $this->result;
	}
}

