<?php
namespace testman;

class AssertFailure extends \Exception{
	private $expectation;
	private $result;
	private $has = false;

	public function ab($expectation,$result){
		$this->expectation = $expectation;
		$this->result = $result;
		$this->has = true;
		return $this;
	}

	public function has(){
		return $this->has;
	}
	public function expectation(){
		return $this->expectation;
	}
	public function result(){
		return $this->result;
	}
}

