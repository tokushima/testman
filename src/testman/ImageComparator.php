<?php
namespace testman;

class ImageComparator{
	/**
	 * 2つの画像ファイルを比較する
	 * @param string $expected_file 期待画像のパス
	 * @param string $actual_file 実際の画像のパス
	 * @param bool $ignore_antialiasing アンチエイリアス差を無視するか
	 * @param int $page PDFの場合のページ番号（0始まり）
	 * @return array{diff_ratio: float, is_antialiasing: bool, analysis: array}
	 */
	public static function compare(string $expected_file, string $actual_file, bool $ignore_antialiasing = true, int $page = 0): array{
		self::validate_file($expected_file);
		self::validate_file($actual_file);

		$expected_img = self::load_image($expected_file, $page);
		$actual_img = self::load_image($actual_file, $page);

		try{
			return self::analyze($expected_img, $actual_img, $ignore_antialiasing);
		}finally{
			imagedestroy($expected_img);
			imagedestroy($actual_img);
		}
	}

	/**
	 * 2つの画像ファイルの差分画像を生成する
	 * @param string $expected_file 期待画像のパス
	 * @param string $actual_file 実際の画像のパス
	 * @param string $diff_output_file 差分画像の出力先パス（PNG）
	 * @param int $page PDFの場合のページ番号（0始まり）
	 */
	public static function diff(string $expected_file, string $actual_file, string $diff_output_file, int $page = 0): void{
		self::validate_file($expected_file);
		self::validate_file($actual_file);

		$expected_img = self::load_image($expected_file, $page);
		$actual_img = self::load_image($actual_file, $page);

		try{
			$diff_img = self::create_diff_image($expected_img, $actual_img);
			imagepng($diff_img, $diff_output_file);
			imagedestroy($diff_img);
		}finally{
			imagedestroy($expected_img);
			imagedestroy($actual_img);
		}
	}

	/**
	 * RGBのみ取得（アルファを除外）
	 */
	private static function rgb(int $c): array{
		return [
			($c >> 16) & 0xFF,
			($c >> 8) & 0xFF,
			$c & 0xFF,
		];
	}

	/**
	 * 差分解析を行う
	 * @param \GdImage $expected
	 * @param \GdImage $actual
	 * @param bool $ignore_antialiasing
	 * @return array{diff_ratio: float, is_antialiasing: bool, analysis: array}
	 */
	private static function analyze($expected, $actual, bool $ignore_antialiasing): array{
		// パレット画像をTrueColorに変換
		imagepalettetotruecolor($expected);
		imagepalettetotruecolor($actual);

		$w1 = imagesx($expected);
		$h1 = imagesy($expected);
		$w2 = imagesx($actual);
		$h2 = imagesy($actual);

		$w = max($w1, $w2);
		$h = max($h1, $h2);

		if($w === 0 || $h === 0){
			return ['diff_ratio' => 0.0, 'is_antialiasing' => false, 'analysis' => self::empty_analysis()];
		}

		$total = $w * $h;
		$edge_pixels = 0;
		$solid_pixels = 0;
		$max_channel_diff = 0;
		$total_diff_pixels = 0;

		for($y = 0; $y < $h; $y++){
			for($x = 0; $x < $w; $x++){
				// サイズが異なる場合、はみ出し部分はソリッド差異
				if($x >= $w1 || $y >= $h1 || $x >= $w2 || $y >= $h2){
					$total_diff_pixels++;
					$solid_pixels++;
					$max_channel_diff = 255;
					continue;
				}

				// RGBのみで比較（アルファ無視）
				list($r1, $g1, $b1) = self::rgb(imagecolorat($expected, $x, $y));
				list($r2, $g2, $b2) = self::rgb(imagecolorat($actual, $x, $y));

				if($r1 === $r2 && $g1 === $g2 && $b1 === $b2){
					continue;
				}

				$total_diff_pixels++;

				// チャンネル差分の最大値
				$dr = abs($r1 - $r2);
				$dg = abs($g1 - $g2);
				$db = abs($b1 - $b2);
				$ch_diff = max($dr, $dg, $db);
				if($ch_diff > $max_channel_diff){
					$max_channel_diff = $ch_diff;
				}

				// エッジ判定: 上下左右の隣接ピクセルとの色差が大きい = 色の境界付近（アンチエイリアス）
				$is_edge = false;
				if($x > 0 && $x < $w1 - 1 && $y > 0 && $y < $h1 - 1){
					// 上
					list($ar, $ag, $ab) = self::rgb(imagecolorat($expected, $x, $y - 1));
					$diff_above = abs($ar - $r1) + abs($ag - $g1) + abs($ab - $b1);

					// 下
					list($br, $bg, $bb) = self::rgb(imagecolorat($expected, $x, $y + 1));
					$diff_below = abs($br - $r1) + abs($bg - $g1) + abs($bb - $b1);

					// 左
					list($lr, $lg, $lb) = self::rgb(imagecolorat($expected, $x - 1, $y));
					$diff_left = abs($lr - $r1) + abs($lg - $g1) + abs($lb - $b1);

					// 右
					list($rr, $rg, $rb) = self::rgb(imagecolorat($expected, $x + 1, $y));
					$diff_right = abs($rr - $r1) + abs($rg - $g1) + abs($rb - $b1);

					$is_edge = ($diff_above > 30 || $diff_below > 30 || $diff_left > 30 || $diff_right > 30);
				}

				if($is_edge){
					$edge_pixels++;
				}else{
					$solid_pixels++;
				}
			}
		}

		$analysis = [
			'edge_pixels' => $edge_pixels,
			'solid_pixels' => $solid_pixels,
			'max_channel_diff' => $max_channel_diff,
			'total_diff_pixels' => $total_diff_pixels,
			'total_pixels' => $total,
		];

		$diff_ratio = ($total > 0) ? $total_diff_pixels / $total : 0.0;

		// アンチエイリアス判定:
		//   1. エッジピクセルが全差異の70%超
		//   2. maxChannelDiff <= 128 かつ diffRatio < 2%
		//   3. diffRatio < 0.5% かつ エッジが過半数（GS/CoreGraphicsのレンダリング差吸収）
		//   4. 差異ピクセルが極少数(<=10)かつ diffRatio < 0.01%（レンダリングエンジン間の孤立ピクセル差吸収）
		$is_antialiasing = false;
		if($total_diff_pixels > 0){
			$edge_ratio = $edge_pixels / $total_diff_pixels;
			$is_antialiasing = $edge_ratio > 0.7
				|| ($max_channel_diff <= 128 && $diff_ratio < 0.02)
				|| ($diff_ratio < 0.005 && $edge_pixels > $solid_pixels)
				|| ($total_diff_pixels <= 10 && $diff_ratio < 0.0001);
		}

		$effective_diff_ratio = $diff_ratio;
		if($ignore_antialiasing && $is_antialiasing){
			$effective_diff_ratio = 0.0;
		}

		return [
			'diff_ratio' => $effective_diff_ratio,
			'is_antialiasing' => $is_antialiasing,
			'analysis' => $analysis,
		];
	}

	private static function empty_analysis(): array{
		return [
			'edge_pixels' => 0,
			'solid_pixels' => 0,
			'max_channel_diff' => 0,
			'total_diff_pixels' => 0,
			'total_pixels' => 0,
		];
	}

	private static function validate_file(string $file): void{
		if(!is_file($file)){
			throw new NotFoundException('file not found: '.$file);
		}
	}

	private static function extension(string $file): string{
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if(!in_array($ext, ['png', 'jpg', 'jpeg', 'pdf'], true)){
			throw new InvalidArgumentException('unsupported image format: '.$ext);
		}
		return $ext;
	}

	/**
	 * @return \GdImage
	 */
	private static function load_image(string $file, int $page = 0){
		$ext = self::extension($file);

		if($ext === 'pdf'){
			return self::load_pdf($file, $page);
		}
		if($ext === 'png'){
			$img = @imagecreatefrompng($file);
		}else{
			$img = @imagecreatefromjpeg($file);
		}
		if($img === false){
			throw new InvalidArgumentException('failed to load image: '.$file);
		}
		// パレット画像をTrueColorに変換
		imagepalettetotruecolor($img);
		return $img;
	}

	/**
	 * @return \GdImage
	 */
	private static function load_pdf(string $file, int $page = 0){
		$pdf_converter = Conf::get('image_pdf_converter');
		if(is_callable($pdf_converter)){
			$png_path = call_user_func($pdf_converter, $file, $page);
			if(!is_file($png_path)){
				throw new InvalidArgumentException('PDF converter did not produce a file: '.$png_path);
			}
			$img = @imagecreatefrompng($png_path);
			if($img === false){
				throw new InvalidArgumentException('failed to load converted PDF image: '.$png_path);
			}
			return $img;
		}

		// Ghostscript
		$gs = self::find_gs();
		if($gs === null){
			throw new InvalidArgumentException(
				'PDF comparison requires Ghostscript (gs) or image_pdf_converter setting'.PHP_EOL.
				'  Install: brew install ghostscript (Mac) / apt install ghostscript (Linux) / choco install ghostscript (Windows)'
			);
		}

		$tmp = tempnam(sys_get_temp_dir(), 'testman_pdf_').'.png';
		$first_page = $page + 1;
		$dpi = intval(Conf::get('image_pdf_dpi', 72));
		$cmd = sprintf(
			'%s -dBATCH -dNOPAUSE -dQUIET -sDEVICE=png16m -r%d -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>&1',
			escapeshellarg($gs),
			$dpi,
			$first_page,
			$first_page,
			escapeshellarg($tmp),
			escapeshellarg($file)
		);
		exec($cmd, $output, $return_code);

		if($return_code !== 0 || !is_file($tmp)){
			@unlink($tmp);
			throw new InvalidArgumentException('Ghostscript failed to convert PDF: '.implode(PHP_EOL, $output));
		}

		$img = @imagecreatefrompng($tmp);
		unlink($tmp);

		if($img === false){
			throw new InvalidArgumentException('failed to load converted PDF image: '.$file);
		}
		return $img;
	}

	private static function find_gs(): ?string{
		// Windows
		if(PHP_OS_FAMILY === 'Windows'){
			foreach(['gswin64c', 'gswin32c', 'gs'] as $name){
				$result = exec('where '.$name.' 2>NUL', $o, $r);
				if($r === 0 && !empty($result)){
					return $name;
				}
			}
			return null;
		}
		// Mac / Linux
		exec('which gs 2>/dev/null', $output, $return_code);
		return ($return_code === 0 && !empty($output)) ? 'gs' : null;
	}

	/**
	 * 差分画像を生成する（一致=グレースケール薄く、差異=ハイライト色で合成）
	 * @param \GdImage $expected
	 * @param \GdImage $actual
	 * @return \GdImage
	 */
	private static function create_diff_image($expected, $actual){
		imagepalettetotruecolor($expected);
		imagepalettetotruecolor($actual);

		$w1 = imagesx($expected);
		$h1 = imagesy($expected);
		$w2 = imagesx($actual);
		$h2 = imagesy($actual);

		$w = max($w1, $w2);
		$h = max($h1, $h2);

		$diff = imagecreatetruecolor($w, $h);
		$red = imagecolorallocate($diff, 255, 0, 0);

		for($y = 0; $y < $h; $y++){
			for($x = 0; $x < $w; $x++){
				if($x >= $w1 || $y >= $h1 || $x >= $w2 || $y >= $h2){
					imagesetpixel($diff, $x, $y, $red);
					continue;
				}

				list($r1, $g1, $b1) = self::rgb(imagecolorat($expected, $x, $y));
				list($r2, $g2, $b2) = self::rgb(imagecolorat($actual, $x, $y));

				if($r1 !== $r2 || $g1 !== $g2 || $b1 !== $b2){
					// 差異ピクセル: actual画像と赤を50%合成
					$blended = imagecolorallocate($diff, intval((255 + $r2) / 2), intval($g2 / 2), intval($b2 / 2));
					imagesetpixel($diff, $x, $y, $blended);
				}else{
					// 一致ピクセル: グレースケール化して薄く表示
					$gray = intval(($r1 * 299 + $g1 * 587 + $b1 * 114) / 1000);
					$dimmed = intval(128 + $gray / 2);
					$color = imagecolorallocate($diff, $dimmed, $dimmed, $dimmed);
					imagesetpixel($diff, $x, $y, $color);
				}
			}
		}
		return $diff;
	}
}
