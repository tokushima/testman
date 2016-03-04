<?php
namespace testman;

class Coverage{
	static private $db;
	static private $result = [];
	static private $linkvars = [];

	/**
	 * リンクファイルが存在するか
	 * @param mixed $vars
	 * @return boolean
	*/
	public static function has_link(&$vars){
		if(empty(self::$linkvars)){
			return false;
		}
		$vars = self::$linkvars;
		return true;
	}
	/**
	 * リンクファイル名
	 * @return string
	 */
	public static function link(){
		return '_test_link'.md5(__FILE__);
	}
	/**
	 * 測定を開始する
	 * @param string $output_xml 書き出し先
	 * @throws \RuntimeException
	 */
	public static function start($output_xml,$target_dir=null){
		if(!empty($output_xml)){
			if(extension_loaded('xdebug')){
				if(!is_dir($d = dirname($output_xml))){
					mkdir($d,0777,true);
				}
				file_put_contents($output_xml,'');
				self::$db = realpath($output_xml);
					
				$target_list_db = self::$db.'.target';
				$tmp_db = self::$db.'.tmp';
				file_put_contents($target_list_db,'');
				file_put_contents($tmp_db,'');
					
				if(empty($target_dir)){
					$target_dir = getcwd().'/lib';
				}
				if(!empty($target_dir) && is_dir($target_dir)){
					$fp = fopen($target_list_db,'w');

					foreach(new \RecursiveIteratorIterator(
							new \RecursiveDirectoryIterator(
									$target_dir,
									\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
							),\RecursiveIteratorIterator::SELF_FIRST
					) as $f){
						if($f->isFile() &&
							substr($f->getFilename(),-4) == '.php' &&
							ctype_upper(substr($f->getFilename(),0,1))
						){
							fwrite($fp,$f->getPathname().PHP_EOL);
						}
					}
					fclose($fp);
				}
				xdebug_start_code_coverage(XDEBUG_CC_UNUSED|XDEBUG_CC_DEAD_CODE);
				self::$linkvars['coverage_data_file'] = self::$db;
					
				return true;
			}else{
				throw new \RuntimeException('xdebug extension not loaded');
			}
		}
		return false;
	}
	/**
	 * 測定を終了する
	 */
	public static function stop(){
		if(is_file(self::$db)){
			$target_list_db = self::$db.'.target';
			$tmp_db = self::$db.'.tmp';

			$target_list = explode(PHP_EOL,trim(file_get_contents($target_list_db)));
			$fp = fopen($tmp_db,'a');

			foreach(xdebug_get_code_coverage() as $file_path => $lines){
				if(false !== ($i = array_search($file_path,$target_list))){
					fwrite($fp,json_encode([$i,$lines]).PHP_EOL);
				}
			}
			fclose($fp);
				
			foreach(explode(PHP_EOL,trim(file_get_contents($tmp_db))) as $json){
				if(!empty($json)){
					$cov = json_decode($json,true);
					if($cov !== false){
						$filename = $target_list[$cov[0]];
							
						if(!isset(self::$result[$filename])){
							self::$result[$filename] = ['covered_line_status'=>[],'uncovered_line_status'=>[],'test'=>1];
						}
						foreach($cov[1] as $line => $status){
							if($status == 1){
								self::$result[$filename]['covered_line_status'][] = $line;
							}else if($status != -2){
								self::$result[$filename]['uncovered_line_status'][] = $line;
							}
						}
					}
				}
			}
			foreach(self::$result as $filename => $cov){
				self::$result[$filename]['covered_line'] = array_unique(self::$result[$filename]['covered_line_status']);
				self::$result[$filename]['uncovered_line'] = array_diff(array_unique(self::$result[$filename]['uncovered_line_status']),self::$result[$filename]['covered_line']);
				unset(self::$result[$filename]['covered_line_status']);
				unset(self::$result[$filename]['uncovered_line_status']);
			}
			foreach($target_list as $filename){
				if(!isset(self::$result[$filename])){
					self::$result[$filename] = ['covered_line'=>[],'uncovered_line'=>[],'test'=>0];
				}
			}
			ksort(self::$result);
			unlink($target_list_db);
			unlink($tmp_db);

			xdebug_stop_code_coverage();
			return true;
		}
		return false;
	}
	/**
	 * 結果の取得
	 * @return string[]
	 */
	public static function get(){
		return self::$result;
	}
	/**
	 * startで指定した$work_dirに結果のXMLを出力する
	 */
	public static function output($written_xml){
		\testman\Std::println();
		\testman\Std::println_warning('Coverage: ');
			
		if($written_xml){
			$xml = new \SimpleXMLElement('<coverage></coverage>');
		}

		$total_covered = $total_lines = 0;
		foreach(self::get() as $filename => $resultset){
			$covered = count($resultset['covered_line']);
			$uncovered = count($resultset['uncovered_line']);
			$covered = (($resultset['test'] == 1) ? ceil($covered / ($covered + $uncovered) * 100) : 0);

			$total_lines += $covered + $uncovered;
			$total_covered += $covered;

			if(isset($xml)){
				$f = $xml->addChild('file');
				$f->addAttribute('name',str_replace(getcwd().DIRECTORY_SEPARATOR,'',$filename));
				$f->addAttribute('covered',$covered);
				$f->addAttribute('modify_date',date('Y/m/d H:i:s',filemtime($filename)));
				$f->addChild('covered_lines',implode(',',$resultset['covered_line']));
				$f->addChild('uncovered_lines',implode(',',$resultset['uncovered_line']));
				$f->addAttribute('test',($resultset['test'] == 1) ? 'true' : 'false');
			}

			$msg = sprintf(' %3d%% %s',$covered,$filename);
			if($covered == 100){
				\testman\Std::println_success($msg);
			}else if($covered > 50){
				\testman\Std::println_warning($msg);
			}else{
				\testman\Std::println_danger($msg);
			}
		}
		$covered_sum = ($total_covered == 0) ? 0 : ceil($total_covered/$total_lines*100);

		\testman\Std::println(str_repeat('-',70));
		\testman\Std::println_info(sprintf(' Covered %s%%',$covered_sum));
			
		if(isset($xml)){
			$xml->addAttribute('create_date',date('Y/m/d H:i:s'));
			$xml->addAttribute('covered',$covered_sum);
			$xml->addAttribute('lines',$total_lines);
			$xml->addAttribute('covered_lines',$total_covered);

			$save_path = realpath(self::$db);
			if(!is_dir(dirname($save_path))){
				mkdir(dirname($save_path),0777,true);
			}
			$dom = new \DOMDocument('1.0','utf-8');
			$node = $dom->importNode(dom_import_simplexml($xml), true);
			$dom->appendChild($node);
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->save($save_path);

			\testman\Std::println_primary(PHP_EOL.'Written Coverage: '.$save_path);
		}
	}

	public static function load($xml_file){
		$xml = \testman\Xml::extract(file_get_contents($xml_file),'coverage');
		$create_date = strtotime($xml->in_attr('create_date'));
			
		self::$result = [];
		foreach($xml->find('file') as $file){
			self::$result[$file->in_attr('name')] = [
				'covered_line'=>explode(',',$file->find_get('covered_lines')->value()),
				'uncovered_line'=>explode(',',$file->find_get('uncovered_lines')->value()),
				'test'=>(($file->in_attr('test') == 'true') ? 1 : 0),
			];
		}
		return $create_date;
	}

	public static function output_source($source_file,$coverage_date){
		if(!is_file($source_file)){
			throw new \testman\NotFoundException($source_file.' not found');
		}
		$source_file = realpath($source_file);
		$path = str_replace(getcwd().DIRECTORY_SEPARATOR,'',$source_file);
			
		if(!isset(self::$result[$path])){
			throw new \testman\NotFoundException('Coverage data not found');
		}
		if(filemtime($source_file) > $coverage_date){
			throw new \testman\NotFoundException('Updated ('.date('Y/m/d H:i:s',filemtime($source_file)).')');
		}
		$source_list = explode(PHP_EOL,file_get_contents($source_file));
			
		$covered = count(self::$result[$path]['covered_line']);
		$uncovered = count(self::$result[$path]['uncovered_line']);
		$covered = ((self::$result[$path]['test'] == 1) ? ceil($covered / ($covered + $uncovered) * 100) : 0);
			
		\testman\Std::println();
		\testman\Std::println_warning('Coverage: ');
			
			
		$msg = sprintf('  %d%% %s',$covered,$path);
		if($covered == 100){
			\testman\Std::println_success($msg);
		}else if($covered > 50){
			\testman\Std::println_warning($msg);
		}else{
			\testman\Std::println_danger($msg);
		}
			
		\testman\Std::println();
			
		$tab = strlen(sizeof($source_list));
		foreach($source_list as $no => $line){
			$i = $no + 1;
			$msg = sprintf('  %'.$tab.'s %s',$i,$line);

			if(self::$result[$path]['test'] !== 1){
				\testman\Std::println($msg);
			}else if(in_array($i,self::$result[$path]['covered_line'])){
				\testman\Std::println_success($msg);
			}else if(in_array($i,self::$result[$path]['uncovered_line'])){
				\testman\Std::println_danger($msg);
			}else{
				\testman\Std::println_white($msg);
			}
		}
		\testman\Std::println();
	}
}