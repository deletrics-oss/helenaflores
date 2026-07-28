<?php
/**
 * SimpleXLSXGen class for PHP
 * 
 * @license MIT
 * @author Shuchkin <sergey.shuchkin@gmail.com>
 * @see https://github.com/shuchkin/simplexlsxgen
 */

class SimpleXLSXGen
{

	public $curSheet;
	protected $defaultFont;
	protected $defaultFontSize;
	protected $rtl;
	protected $sheets;
	protected $template;
	protected $NF; // numFmts
	protected $NF_KEYS;
	protected $XF; // cellXfs
	protected $XF_KEYS;
	protected $BR_STYLE; // borders
	protected $SI; // shared strings
	protected $SI_KEYS;
	protected $extStr;
	const N_NORMAL = 0; // General
	const N_INT = 1; // 0
	const N_DEC = 2; // 0.00
	const N_PERCENT_INT = 9; // 0%
	const N_PERCENT_DEC = 10; // 0.00%
	const N_DATE = 14; // mm-dd-yy
	const N_TIME = 20; // h:mm
	const N_DATETIME = 22; // m/d/yy h:mm
	const F_NORMAL = 0;
	const F_HYPERLINK = 1;
	const F_BOLD = 2;
	const F_ITALIC = 4;
	const F_UNDERLINE = 8;
	const F_STRIKE = 16;
	const F_COLOR = 32;
	const F_WRAP = 64;
	const F_CENTER = 128;
	const A_LEFT = 0;
	const A_CENTER = 1;
	const A_RIGHT = 2;

	protected $fh; // Added property for fix

	public function __construct()
	{
		$this->curSheet = -1;
		$this->defaultFont = 'Calibri';
		$this->defaultFontSize = 10;
		$this->rtl = false;
		$this->sheets = array(array('name' => 'Sheet1', 'rows' => array(), 'hyperlinks' => array(), 'mergecells' => array(), 'colwidth' => array(), 'autofilter' => ''));
		$this->SI = array();		// sharedStrings stores values
		$this->SI_KEYS = array();	// sharedStrings stores keys -> index
		$this->extStr = array();
		$this->NF = array('General', '0', '0.00', '#,##0', '#,##0.00', '0%', '0.00%', '0.00E+00', '# ?/?', '# ??/??', 'mm-dd-yy', 'd-mmm-yy', 'd-mmm', 'mmm-yy', 'h:mm AM/PM', 'h:mm:ss AM/PM', 'h:mm', 'h:mm:ss', 'm/d/yy h:mm', '#,##0 ;(#,##0)', '#,##0 ;[Red](#,##0)', '#,##0.00;(#,##0.00)', '#,##0.00;[Red](#,##0.00)', 'mm:ss', '[h]:mm:ss', 'mmss.0', '##0.0E+0', '@');
		$this->NF_KEYS = array_flip($this->NF);
		$this->XF = array(array('numFmtId' => 0, 'fontId' => 0, 'fillId' => 0, 'borderId' => 0, 'xfId' => 0, 'alignment' => ''));
		$this->XF_KEYS = array('00000' => 0);
		$this->BR_STYLE = array('none', 'thin', 'medium', 'dashed', 'dotted', 'thick', 'double', 'hair', 'mediumDashed', 'dashDot', 'mediumDashDot', 'dashDotDot', 'mediumDashDotDot', 'slantDashDot');
		$this->template = array(
			'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>SimpleXLSXGen</Application></Properties>',
			'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>SimpleXLSXGen</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">{DATE}</dcterms:created></cp:coreProperties>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">{SHEETS}</Relationships>',
			'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><dimension ref="{REF}"/><sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/>{COLS}<sheetData>{ROWS}</sheetData>{AUTOFILTER}{MERGECELLS}{HYPERLINKS}</worksheet>',
			'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><fileVersion appName="xl" lastEdited="5" lowestEdited="5" rupBuild="9302"/><workbookPr defaultThemeVersion="124220"/><bookViews><workbookView xWindow="480" yWindow="60" windowWidth="18195" windowHeight="8505"/></bookViews><sheets>{SHEETS}</sheets><definedNames/><calcPr calcId="125725"/></workbook>',
			'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{CNT}" uniqueCount="{UCNT}">{STRINGS}</sst>',
			'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" mc:Ignorable="x14ac" xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac"><fonts count="1"><font><sz val="{FONTSIZE}"/><color theme="1"/><name val="{FONTNAME}"/><family val="2"/><scheme val="minor"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="{CNT}">{XF}</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/></styleSheet>'
		);
	}
	public static function fromArray(array $rows, $sheetName = null)
	{
		$xlsx = new SimpleXLSXGen();
		return $xlsx->addSheet($rows, $sheetName);
	}
	public function addSheet(array $rows, $name = null)
	{
		$this->curSheet++;
		if ($name === null) { // autogenerated sheet names
			$name = 'Sheet' . ($this->curSheet + 1);
		}
		$this->sheets[$this->curSheet] = array('name' => $name, 'rows' => $rows, 'hyperlinks' => array(), 'mergecells' => array(), 'colwidth' => array(), 'autofilter' => '');
		return $this;
	}
	public function setDeafultFont($fontname, $fontsize)
	{
		$this->defaultFont = $fontname;
		$this->defaultFontSize = $fontsize;
		return $this;
	}
	public function setRTL($rtl = true)
	{
		$this->rtl = $rtl;
		return $this;
	}
	public function __toString()
	{
		$fh = fopen('php://memory', 'wb');
		if (!$fh) {
			return '';
		}
		if (!$this->_write($fh)) {
			fclose($fh);
			return '';
		}
		$size = ftell($fh);
		fseek($fh, 0);
		return (string) fread($fh, $size);
	}
	public function saveAs($filename)
	{
		$fh = fopen($filename, 'wb');
		if (!$fh) {
			return false;
		}
		if (!$this->_write($fh)) {
			fclose($fh);
			return false;
		}
		fclose($fh);
		return true;
	}
	public function download()
	{
		return $this->downloadAs(gmdate('YmdHi') . '.xlsx');
	}
	public function downloadAs($filename)
	{
		$fh = fopen('php://memory', 'wb');
		if (!$fh) {
			return false;
		}
		if (!$this->_write($fh)) {
			fclose($fh);
			return false;
		}
		$size = ftell($fh);
		header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Length: ' . $size);
		fseek($fh, 0);
		fpassthru($fh);
		fclose($fh);
		return true;
	}
	protected function _write($fh)
	{
		$this->fh = $fh; // Assign file handle
		$dirSignature = "\x50\x4b\x05\x06"; // end of central dir signature
		$zipComments = 'Generated by SimpleXLSXGen';
		if (!$fh) {
			return false;
		}
		$cdrec = '';	// central directory content
		$entries = 0;	// number of zipped files
		$cnt_sheets = count($this->sheets);
		foreach ($this->template as $cfilename => $template) {
			if ($cfilename === 'xl/_rels/workbook.xml.rels') {
				$s = '';
				for ($i = 0; $i < $cnt_sheets; $i++) {
					$s .= '<Relationship Id="rId' . ($i + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
				}
				$s .= '<Relationship Id="rId' . ($cnt_sheets + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
				$s .= '<Relationship Id="rId' . ($cnt_sheets + 3) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
				$template = str_replace('{SHEETS}', $s, $template);
				$this->_addToFile($cfilename, $template, $cdrec);
				$entries++;
			} elseif ($cfilename === 'xl/workbook.xml') {
				$s = '';
				for ($i = 0; $i < $cnt_sheets; $i++) {
					$s .= '<sheet name="' . $this->sheets[$i]['name'] . '" sheetId="' . ($i + 1) . '" state="visible" r:id="rId' . ($i + 2) . '"/>';
				}
				$template = str_replace('{SHEETS}', $s, $template);
				$this->_addToFile($cfilename, $template, $cdrec);
				$entries++;
			} elseif ($cfilename === 'docProps/core.xml') {
				$template = str_replace('{DATE}', gmdate('Y-m-d\TH:i:s\Z'), $template);
				$this->_addToFile($cfilename, $template, $cdrec);
				$entries++;
			} elseif ($cfilename === 'xl/styles.xml') {
				$s = '';
				// $XF_KEYS stores hash -> numFmtId-fontId-fillId-borderId-alignment
				foreach ($this->XF as $xf) {
					$s .= '<xf numFmtId="' . $xf['numFmtId'] . '" fontId="' . $xf['fontId'] . '" fillId="' . $xf['fillId'] . '" borderId="' . $xf['borderId'] . '" xfId="0"';
					if ($xf['alignment']) {
						$s .= ' applyAlignment="1"><alignment ' . $xf['alignment'] . '/></xf>';
					} else {
						$s .= ' applyAlignment="0"/>';
					}
				}
				$template = str_replace(array('{CNT}', '{XF}', '{FONTNAME}', '{FONTSIZE}'), array(count($this->XF), $s, $this->defaultFont, $this->defaultFontSize), $template);
				$this->_addToFile($cfilename, $template, $cdrec);
				$entries++;
			} elseif ($cfilename === 'xl/worksheets/sheet1.xml') {
				for ($i = 0; $i < $cnt_sheets; $i++) {
					$sheet = $this->sheets[$i];
					$colWidths = $sheet['colwidth'];
					$autofilter = $sheet['autofilter'];
					$cols = '';
					if ($colWidths) {
						$cols .= '<cols>';
						foreach ($colWidths as $k => $v) {
							$cols .= '<col min="' . ($k + 1) . '" max="' . ($k + 1) . '" width="' . $v . '" customWidth="1"/>';
						}
						$cols .= '</cols>';
					}
					$mergeCells = '';
					if ($sheet['mergecells']) {
						$mergeCells = '<mergeCells count="' . count($sheet['mergecells']) . '">';
						foreach ($sheet['mergecells'] as $m) {
							$mergeCells .= '<mergeCell ref="' . $m . '"/>';
						}
						$mergeCells .= '</mergeCells>';
					}
					$hyperlinks = '';
					if ($sheet['hyperlinks']) {
						$hyperlinks = '<hyperlinks>';
						foreach ($sheet['hyperlinks'] as $h) {
							$hyperlinks .= '<hyperlink ref="' . $h['ref'] . '" r:id="' . $h['id'] . '" location="' . ($h['location'] ?? '') . '" display="' . ($h['display'] ?? '') . '"/>';
						}
						$hyperlinks .= '</hyperlinks>';
					}
					$rows = '';
					$idx = 0;
					$cnt = count($sheet['rows']);
					foreach ($sheet['rows'] as $r) {
						$rows .= '<row r="' . ($idx + 1) . '">';
						$cIdx = 0;
						foreach ($r as $v) {
							$cIdx++;
							if ($v === null || $v === '') {
								continue;
							}
							$cValue = $cType = $sStyle = '';
							if (is_int($v)) {
								$cType = 'n';
								$cValue = $v;
							} elseif (is_float($v)) {
								$cType = 'n';
								$cValue = $v;
							} elseif (preg_match('/^\\d{4}-\\d{2}-\\d{2}( \\d{2}:\\d{2}:\\d{2})?$/', $v)) {
								$cType = 'n';
								$cValue = $this->_date2excel($v);
								$sStyle = 's="' . $this->_F(self::N_DATETIME) . '"';
							} elseif (preg_match('/^[01]:\\d{2}:\\d{2}$/', $v)) {
								$cType = 'n';
								$cValue = $this->_date2excel($v);
								$sStyle = 's="' . $this->_F(self::N_TIME) . '"';
							} elseif ($v[0] === '=') {
								$cType = 'str';
								$cValue = $v;
							} elseif (strpos($v, '<') !== false && strpos($v, '>') !== false && $v[0] === '<') {
								$cType = 's';
								if (preg_match('/^<a href="([^"]+)">([^<]+)<\/a>$/', $v, $m)) {
									$cValue = $this->_S($m[2]);
									$hKey = 'rId' . (count($sheet['hyperlinks']) + 1);
									$sheet['hyperlinks'][] = array('ref' => $this->_idx2cell($idx, $cIdx - 1), 'id' => $hKey, 'location' => $m[1], 'display' => $m[2]);
									$sStyle = 's="' . $this->_F(self::F_HYPERLINK) . '"';
								} elseif (preg_match('/^<(b|i|u|s)>(.*)<\/\1>$/', $v, $m)) {
									$cValue = $this->_S($m[2]);
									$style = 0;
									if ($m[1] === 'b')
										$style = self::F_BOLD;
									if ($m[1] === 'i')
										$style = self::F_ITALIC;
									if ($m[1] === 'u')
										$style = self::F_UNDERLINE;
									if ($m[1] === 's')
										$style = self::F_STRIKE;
									$sStyle = 's="' . $this->_F(0, $style) . '"';
								} elseif (preg_match('/^<center>(.*)<\/center>$/', $v, $m)) {
									$cValue = $this->_S($m[1]);
									$sStyle = 's="' . $this->_F(0, self::F_CENTER) . '"';
								} else {
									$cValue = $this->_S($v);
								}
							} else {
								$cType = 's';
								$cValue = $this->_S($v);
							}
							$rows .= '<c r="' . $this->_idx2cell($idx, $cIdx - 1) . '" ' . $sStyle . ' t="' . $cType . '"><v>' . $cValue . '</v></c>';
						}
						$rows .= '</row>';
						$idx++;
					}
					$replace = array('{REF}', '{COLS}', '{ROWS}', '{AUTOFILTER}', '{MERGECELLS}', '{HYPERLINKS}');
					$with = array('A1:' . $this->_idx2cell($cnt > 0 ? $cnt - 1 : 0, $cIdx > 0 ? $cIdx - 1 : 0), $cols, $rows, $autofilter, $mergeCells, $hyperlinks);
					$template2 = str_replace($replace, $with, $template);
					$this->_addToFile('xl/worksheets/sheet' . ($i + 1) . '.xml', $template2, $cdrec);
					$entries++;
				}
			} elseif ($cfilename === 'xl/sharedStrings.xml') {
				if (!$this->SI) {
					continue;
				}
				$s = '';
				foreach ($this->SI as $val) {
					$s .= '<si><t>' . $this->_esc($val) . '</t></si>';
				}
				$template = str_replace(array('{CNT}', '{UCNT}', '{STRINGS}'), array(count($this->SI), count($this->SI), $s), $template);
				$this->_addToFile($cfilename, $template, $cdrec);
				$entries++;
			}
		}
		$cdrec .= $dirSignature . "\x00\x00\x00\x00";
		$cdrec .= pack('v', $entries);
		$cdrec .= pack('v', $entries);
		$cdrec .= pack('V', mb_strlen($cdrec, '8bit'));
		$cdrec .= pack('V', ftell($fh));
		$cdrec .= $zipComments;
		$cdrec .= pack('v', strlen($zipComments));
		fwrite($fh, $cdrec);
		return true;
	}
	protected function _addToFile($name, $data, &$cdrec)
	{
		$dtime = array_map("intval", explode(";", gmdate("s;i;H;d;m;Y")));
		$hexdtime = pack("v", ($dtime[0] >> 1) | ($dtime[1] << 5) | ($dtime[2] << 11));
		$hexddate = pack("v", $dtime[3] | ($dtime[4] << 5) | (($dtime[5] - 1980) << 9));
		$offset = ftell($this->fh);
		$fr = "\x50\x4b\x03\x04";
		$fr .= "\x14\x00";
		$fr .= "\x00\x00";
		$fr .= "\x08\x00";
		$fr .= $hexdtime;
		$fr .= $hexddate;
		$unc_len = mb_strlen($data, '8bit');
		$crc = crc32($data);
		$zdata = gzcompress($data);
		$zdata = substr(substr($zdata, 0, -4), 2);
		$c_len = mb_strlen($zdata, '8bit');
		$fr .= pack("V", $crc);
		$fr .= pack("V", $c_len);
		$fr .= pack("V", $unc_len);
		$fr .= pack("v", strlen($name));
		$fr .= pack("v", 0);
		$fr .= $name;
		$fr .= $zdata;
		$this->_writeRaw($fr);
		$cdrec .= "\x50\x4b\x01\x02";
		$cdrec .= "\x00\x00";
		$cdrec .= "\x14\x00";
		$cdrec .= "\x00\x00";
		$cdrec .= "\x08\x00";
		$cdrec .= $hexdtime;
		$cdrec .= $hexddate;
		$cdrec .= pack("V", $crc);
		$cdrec .= pack("V", $c_len);
		$cdrec .= pack("V", $unc_len);
		$cdrec .= pack("v", strlen($name));
		$cdrec .= pack("v", 0);
		$cdrec .= pack("v", 0);
		$cdrec .= pack("v", 0);
		$cdrec .= pack("v", 0);
		$cdrec .= pack("V", 32);
		$cdrec .= pack("V", $offset);
		$cdrec .= $name;
	}
	protected function _writeRaw($data)
	{
		fwrite($this->fh, $data);
	}
	protected function _esc($s)
	{
		return str_replace(array('&', '<', '>', '"', "\r"), array('&amp;', '&lt;', '&gt;', '&quot;', ''), $s);
	}
	protected function _F($format, $style = 0)
	{
		$id = $format . '-' . $style;
		if (!isset($this->XF_KEYS[$id])) {
			$v = array('numFmtId' => $format, 'fontId' => 0, 'fillId' => 0, 'borderId' => 0, 'alignment' => '');
			if ($style & self::F_BOLD)
				$v['fontId'] = 1;
			// 2-italic, 3-underline, 4-strike, 5-bolditalic, 6-boldunderline, ...
			if ($style & self::A_CENTER)
				$v['alignment'] = 'horizontal="center"';
			if ($style & self::A_RIGHT)
				$v['alignment'] = 'horizontal="right"';
			if ($style & self::F_WRAP)
				$v['alignment'] = 'wrapText="1"';
			$this->XF_KEYS[$id] = count($this->XF);
			$this->XF[] = $v;
		}
		return $this->XF_KEYS[$id];
	}
	protected function _S($s)
	{
		if (!isset($this->SI_KEYS[$s])) {
			$this->SI_KEYS[$s] = count($this->SI);
			$this->SI[] = $s;
		}
		return $this->SI_KEYS[$s];
	}
	protected function _idx2cell($row, $col)
	{
		$colName = '';
		while ($col >= 0) {
			$colName = chr(ord('A') + ($col % 26)) . $colName;
			$col = floor($col / 26) - 1;
		}
		return $colName . ($row + 1);
	}
	protected function _date2excel($date)
	{
		$d = new DateTime($date);
		$t = $d->getTimestamp();
		$t = ($t > 0) ? $t : 0;
		return 25569 + ($t / 86400);
	}
}
?>