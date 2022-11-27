<?php
namespace testman;

class XmlIterator implements \Iterator{
	private $name = null;
	private ?string $plain = null;
	private ?\testman\Xml $tag = null;
	private int $offset = 0;
	private int $length = 0;
	private int $count = 0;

	public function __construct(string $tag_name, ?string $value, int $offset, int $length){
		$this->name = $tag_name;
		$this->plain = $value;
		$this->offset = $offset;
		$this->length = $length;
		$this->count = 0;
	}

	#[\ReturnTypeWillChange]
	public function key(){
		$this->tag->name();
	}

	#[\ReturnTypeWillChange]
	public function current(){
		$this->plain = substr($this->plain,0,$this->tag->cur()).substr($this->plain,$this->tag->cur() + strlen($this->tag->plain()));
		$this->count++;
		return $this->tag;
	}

	public function valid(): bool{
		if($this->length > 0 && ($this->offset + $this->length) <= $this->count){
			return false;
		}
		if(!empty($this->name) && is_string($this->name) && strpos($this->name,'|') !== false){
			$this->name = explode('|',$this->name);
		}
		if(is_array($this->name)){
			$tags = [];
			foreach($this->name as $name){
				try{
					$get_tag = \testman\Xml::extract($this->plain,$name);
					$tags[$get_tag->cur()] = $get_tag;
				}catch(\testman\NotFoundException $e){
				}
			}
			if(empty($tags)) return false;
			ksort($tags,SORT_NUMERIC);
			foreach($tags as $this->tag) return true;
		}
		try{
			$this->tag = \testman\Xml::extract($this->plain,$this->name);
			return true;
		}catch(\testman\NotFoundException $e){
		}
		return false;
	}
	public function next(): void{
	}
	public function rewind(): void{
		for($i=0;$i<$this->offset;$i++){
			if($this->valid()){
				$this->current();
			}
		}
	}
}
